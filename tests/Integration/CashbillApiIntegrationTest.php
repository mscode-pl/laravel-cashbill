<?php

namespace Barstec\Cashbill\Tests\Integration;

use Barstec\Cashbill\Events\TransactionCreated;
use Barstec\Cashbill\Events\TransactionStatusChanged;
use Barstec\Cashbill\Events\TransactionSuccessfullyCompleted;
use Barstec\Cashbill\Http\Models\Transaction;
use Barstec\Cashbill\Order;
use Barstec\Cashbill\Payload;
use Barstec\Cashbill\Payment;
use Barstec\Cashbill\Tests\BaseTestCase;
use Illuminate\Support\Facades\Event;

/**
 * Testy integracyjne z rzeczywistym API Cashbill
 * 
 * Wymagają ustawienia zmiennych środowiskowych:
 * - CASHBILL_MODE=dev
 * - CASHBILL_SHOP_ID=<twój_shop_id>
 * - CASHBILL_SECRET_KEY=<twój_secret_key>
 * 
 * Uruchomienie: vendor/bin/phpunit --testsuite=Integration
 */
class CashbillApiIntegrationTest extends BaseTestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Force dev mode for integration tests
        $app['config']->set('cashbill.mode', 'dev');

        // Use real credentials if set in environment
        $shopId = env('CASHBILL_SHOP_ID');
        $secretKey = env('CASHBILL_SECRET_KEY');

        if ($shopId && $secretKey) {
            $app['config']->set('cashbill.shop_id', $shopId);
            $app['config']->set('cashbill.secret_key', $secretKey);
        }
    }

    protected function setUp(): void
    {
        // Load .env.test file if it exists (PHPUnit doesn't do this automatically)
        $envTestPath = dirname(__DIR__, 2) . '/.env.test';
        if (file_exists($envTestPath)) {
            $lines = file($envTestPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos(trim($line), '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    $_SERVER[$key] = $value;
                    $_ENV[$key] = $value;
                }
            }
        }

        parent::setUp();

        // Check if real credentials were provided via environment
        $shopId = $_ENV['CASHBILL_SHOP_ID'] ?? $_SERVER['CASHBILL_SHOP_ID'] ?? null;
        $secretKey = $_ENV['CASHBILL_SECRET_KEY'] ?? $_SERVER['CASHBILL_SECRET_KEY'] ?? null;

        if (empty($shopId) || empty($secretKey) || $shopId === 'test_shop_id' || $secretKey === 'test_secret_key') {
            $this->markTestSkipped('CashBill credentials not configured. Copy .env.test.example to .env.test and set CASHBILL_SHOP_ID and CASHBILL_SECRET_KEY');
        }
    }

    /** @test */
    public function it_creates_real_payment_and_gets_redirect_url()
    {
        Event::fake([TransactionCreated::class]);

        $payload = $this->createPayload([
            'title' => 'Integration Test Payment',
            'amount' => 1.00,
            'description' => 'Test payment from automated tests',
            'additionalData' => 'integration_test_' . time(),
        ]);

        $payment = new Payment($payload);

        // Wywołaj beginTransaction przez refleksję
        $reflection = new \ReflectionClass($payment);
        $method = $reflection->getMethod('beginTransaction');
        $method->setAccessible(true);
        $redirectUrl = $method->invoke($payment);

        // Sprawdź że otrzymano URL przekierowania
        $this->assertNotEmpty($redirectUrl);
        $this->assertStringContainsString('cashbill.pl', $redirectUrl);

        // Sprawdź że event został wywołany
        Event::assertDispatched(TransactionCreated::class, function ($event) {
            return !empty($event->orderId)
                && $event->payload->getTitle() === 'Integration Test Payment';
        });

        return $redirectUrl;
    }

    /** @test */
    public function it_fetches_payment_details_for_existing_order()
    {
        // Najpierw utwórz rzeczywistą transakcję
        $payload = $this->createPayload([
            'title' => 'Payment Details Test',
            'amount' => 2.50,
            'additionalData' => 'details_test_' . time(),
        ]);

        $payment = new Payment($payload);
        $reflection = new \ReflectionClass($payment);
        $method = $reflection->getMethod('beginTransaction');
        $method->setAccessible(true);
        $redirectUrl = $method->invoke($payment);

        // Pobierz orderId z bazy
        $transaction = Transaction::where('title', 'Payment Details Test')->latest()->first();
        $this->assertNotNull($transaction);

        $orderId = $transaction->order_id;

        // Teraz zaktualizuj status przez Order
        $order = new Order($orderId);
        $paymentDetails = $order->update();

        $this->assertNotNull($paymentDetails);
        $this->assertEquals($orderId, $paymentDetails->getOrderId());
        $this->assertEquals('Payment Details Test', $paymentDetails->getTitle());
        $this->assertEquals(2.50, $paymentDetails->getAmount());
        $this->assertEquals('PLN', $paymentDetails->getCurrency());

        echo "\n\n=== Payment Details ===\n";
        echo "Order ID: {$paymentDetails->getOrderId()}\n";
        echo "Status: {$paymentDetails->getStatus()}\n";
        echo "Amount: {$paymentDetails->getAmount()} {$paymentDetails->getCurrency()}\n";
        echo "Title: {$paymentDetails->getTitle()}\n";
        echo "Payment Channel: {$paymentDetails->getPaymentChannel()}\n";
        echo "========================\n\n";
    }

    /** @test */
    public function it_handles_multiple_payment_creations()
    {
        $orderIds = [];
        $identifiers = [];

        // Utwórz kilka transakcji
        for ($i = 1; $i <= 3; $i++) {
            $identifier = "batch_test_$i";
            $identifiers[] = $identifier;
            $payload = $this->createPayload([
                'title' => "Batch Test Payment #$i",
                'amount' => $i * 5.00,
                'additionalData' => $identifier,
            ]);

            $payment = new Payment($payload);
            $reflection = new \ReflectionClass($payment);
            $method = $reflection->getMethod('beginTransaction');
            $method->setAccessible(true);
            $method->invoke($payment);

            $transaction = Transaction::where('title', "Batch Test Payment #$i")->first();
            $this->assertNotNull($transaction, "Transaction #$i not found in database");
            $orderIds[] = $transaction->order_id;
        }

        $this->assertCount(3, $orderIds);

        // Sprawdź każdą transakcję
        foreach ($orderIds as $orderId) {
            $order = new Order($orderId);
            $details = $order->update();

            $this->assertNotNull($details);
            $this->assertEquals($orderId, $details->getOrderId());

            echo "\nOrder: $orderId | Status: {$details->getStatus()} | Amount: {$details->getAmount()}\n";
        }
    }
}
