<?php

namespace Barstec\Cashbill\Tests\Unit;

use Barstec\Cashbill\Events\TransactionStatusChanged;
use Barstec\Cashbill\Events\TransactionSuccessfullyCompleted;
use Barstec\Cashbill\Http\Controllers\CashbillNotificationController;
use Barstec\Cashbill\Http\Models\Transaction;
use Barstec\Cashbill\Tests\BaseTestCase;
use Illuminate\Support\Facades\Event;

class CashbillNotificationControllerTest extends BaseTestCase
{
    protected function generateSign(string $cmd, string $args): string
    {
        return md5($cmd . $args . config('cashbill.secret_key'));
    }

    /** @test */
    public function it_returns_403_when_sign_is_invalid()
    {
        $response = $this->getJson('/api/cashbill/notification?cmd=transactionStatusChanged&args=order123&sign=invalid_sign');

        $response->assertStatus(403);
    }

    /** @test */
    public function it_returns_403_when_cmd_is_missing()
    {
        $sign = $this->generateSign('transactionStatusChanged', 'order123');
        $response = $this->getJson('/api/cashbill/notification?cmd=&args=order123&sign=' . $sign);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_handles_successful_notification()
    {
        Event::fake([
            TransactionSuccessfullyCompleted::class,
            TransactionStatusChanged::class,
        ]);

        // Utwórz transakcję w bazie
        Transaction::create([
            'order_id' => 'notification_test_123',
            'title' => 'Notification Test',
            'amount' => 25.00,
            'currency_code' => 'PLN',
            'status' => 'Start',
        ]);

        // Mock API response dla Order::update()
        \Illuminate\Support\Facades\Http::fake([
            'pay.cashbill.pl/*' => \Illuminate\Support\Facades\Http::response([
                'orderId' => 'notification_test_123',
                'paymentChannel' => 'card',
                'amount' => ['value' => 25.00, 'currencyCode' => 'PLN'],
                'title' => 'Notification Test',
                'description' => 'Test description',
                'personalData' => [
                    'firstName' => 'John',
                    'surname' => 'Doe',
                    'email' => 'john@example.com',
                    'country' => 'PL',
                    'city' => 'Warsaw',
                    'postcode' => '00-001',
                    'street' => 'Test St',
                    'house' => '1',
                    'flat' => '',
                    'ip' => '127.0.0.1',
                ],
                'additionalData' => 'test',
                'status' => 'PositiveFinish',
                'details' => ['bankId' => 'bank_1']
            ], 200)
        ]);

        $args = 'notification_test_123,PositiveFinish';
        $sign = $this->generateSign('transactionStatusChanged', $args);

        $response = $this->getJson('/api/cashbill/notification?cmd=transactionStatusChanged&args=' . $args . '&sign=' . $sign);

        $response->assertStatus(200);
        $response->assertSee('OK');

        // Sprawdź że status został zaktualizowany
        $transaction = Transaction::where('order_id', 'notification_test_123')->first();
        $this->assertEquals('PositiveFinish', $transaction->status);
    }

    /** @test */
    public function it_dispatches_transaction_completed_event_on_positive_finish()
    {
        Event::fake([
            TransactionSuccessfullyCompleted::class,
        ]);

        Transaction::create([
            'order_id' => 'event_test_456',
            'title' => 'Event Test',
            'amount' => 50.00,
            'currency_code' => 'PLN',
            'status' => 'PositiveAuthorization',
        ]);

        // Mock API response dla Order::update()
        \Illuminate\Support\Facades\Http::fake([
            'pay.cashbill.pl/*' => \Illuminate\Support\Facades\Http::response([
                'orderId' => 'event_test_456',
                'paymentChannel' => 'card',
                'amount' => ['value' => 50.00, 'currencyCode' => 'PLN'],
                'title' => 'Event Test',
                'description' => 'Test',
                'personalData' => [
                    'firstName' => 'John',
                    'surname' => 'Doe',
                    'email' => 'john@example.com',
                    'country' => 'PL',
                    'city' => 'Warsaw',
                    'postcode' => '00-001',
                    'street' => 'Test St',
                    'house' => '1',
                    'flat' => '',
                    'ip' => '127.0.0.1',
                ],
                'additionalData' => 'test',
                'status' => 'PositiveFinish',
                'details' => ['bankId' => 'bank_1']
            ], 200)
        ]);

        $args = 'event_test_456,PositiveFinish';
        $sign = $this->generateSign('transactionStatusChanged', $args);

        $response = $this->getJson('/api/cashbill/notification?cmd=transactionStatusChanged&args=' . $args . '&sign=' . $sign);

        $response->assertStatus(200);

        Event::assertDispatched(TransactionSuccessfullyCompleted::class);
    }

    /** @test */
    public function it_dispatches_status_changed_event_on_other_status()
    {
        Event::fake([
            TransactionStatusChanged::class,
        ]);

        Transaction::create([
            'order_id' => 'status_test_789',
            'title' => 'Status Test',
            'amount' => 30.00,
            'currency_code' => 'PLN',
            'status' => 'created',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'pay.cashbill.pl/*' => \Illuminate\Support\Facades\Http::response([
                'orderId' => 'status_test_789',
                'paymentChannel' => 'transfer',
                'amount' => ['value' => 30.00, 'currencyCode' => 'PLN'],
                'title' => 'Status Test',
                'description' => 'Test',
                'personalData' => [
                    'firstName' => '',
                    'surname' => '',
                    'email' => '',
                    'country' => '',
                    'city' => '',
                    'postcode' => '',
                    'street' => '',
                    'house' => '',
                    'flat' => '',
                    'ip' => '',
                ],
                'additionalData' => '',
                'status' => 'Start',
                'details' => ['bankId' => null]
            ], 200)
        ]);

        $args = 'status_test_789,Start';
        $sign = $this->generateSign('transactionStatusChanged', $args);

        $response = $this->getJson('/api/cashbill/notification?cmd=transactionStatusChanged&args=' . $args . '&sign=' . $sign);

        $response->assertStatus(200);

        Event::assertDispatched(TransactionStatusChanged::class, function ($event) {
            return $event->status === 'Start';
        });
    }
}
