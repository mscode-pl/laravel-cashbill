<?php

namespace Barstec\Cashbill\Tests\Unit;

use Barstec\Cashbill\Events\TransactionCreated;
use Barstec\Cashbill\Exceptions\CashbillException;
use Barstec\Cashbill\Payload;
use Barstec\Cashbill\Payment;
use Barstec\Cashbill\Tests\BaseTestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class PaymentTest extends BaseTestCase
{
    /** @test */
    public function it_throws_exception_when_title_is_missing()
    {
        $this->expectException(CashbillException::class);
        $this->expectExceptionMessage('Title of transaction is missing');

        $payload = new Payload();
        // Nie ustawiamy title
        $payload->setAmount(10.00);

        $payment = new Payment($payload);
        $payment->redirect();
    }

    /** @test */
    public function it_throws_exception_when_amount_is_missing()
    {
        $this->expectException(CashbillException::class);
        $this->expectExceptionMessage('Amount of transaction is missing');

        $payload = new Payload();
        $payload->setTitle('Test');
        // Nie ustawiamy amount

        $payment = new Payment($payload);
        $payment->redirect();
    }

    /** @test */
    public function it_throws_exception_when_amount_is_invalid()
    {
        $this->expectException(CashbillException::class);
        $this->expectExceptionMessage('Amount must be float, greater than 0 value');

        $payload = $this->createPayload(['amount' => -5.00]);

        $payment = new Payment($payload);
        $payment->redirect();
    }

    /** @test */
    public function it_throws_exception_when_currency_is_invalid_length()
    {
        $this->expectException(CashbillException::class);
        $this->expectExceptionMessage('Invalid currency code');

        $payload = $this->createPayload(['currency' => 'PL']);

        $payment = new Payment($payload);
        $payment->redirect();
    }

    /** @test */
    public function it_throws_exception_when_credentials_are_missing()
    {
        config(['cashbill.shop_id' => '']);
        config(['cashbill.secret_key' => '']);

        $this->expectException(CashbillException::class);
        $this->expectExceptionMessage('Environment variables CASHBILL_SHOP_ID and CASHBILL_SECRET_KEY are missing');

        $payload = $this->createPayload();
        $payment = new Payment($payload);
        $payment->redirect();
    }

    /** @test */
    public function it_begins_transaction_and_dispatches_event()
    {
        Event::fake([TransactionCreated::class]);

        Http::fake([
            'pay.cashbill.pl/*' => Http::response([
                'id' => 'order_test_123',
                'redirectUrl' => 'https://pay.cashbill.pl/test/redirect'
            ], 200)
        ]);

        $payload = $this->createPayload();
        $payment = new Payment($payload);

        // Testujemy beginTransaction zamiast redirect, bo redirect zwraca RedirectResponse
        $reflection = new \ReflectionClass($payment);
        $method = $reflection->getMethod('beginTransaction');
        $method->invoke($payment);

        Event::assertDispatched(TransactionCreated::class, function ($event) {
            return $event->orderId === 'order_test_123'
                && $event->payload->getTitle() === 'Test Payment';
        });
    }

    /** @test */
    public function it_redirects_to_payment_url()
    {
        Http::fake([
            'pay.cashbill.pl/*' => Http::response([
                'id' => 'order_test_456',
                'redirectUrl' => 'https://pay.cashbill.pl/test/redirect_456'
            ], 200)
        ]);

        $payload = $this->createPayload();
        $payment = new Payment($payload);
        $response = $payment->redirect();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://pay.cashbill.pl/test/redirect_456', $response->getTargetUrl());
    }

    /** @test */
    public function it_signs_payload_correctly()
    {
        $payload = $this->createPayload();
        $payment = new Payment($payload);

        $reflection = new \ReflectionClass($payment);
        $method = $reflection->getMethod('sign');
        $method->setAccessible(true);

        $testData = ['title' => 'Test', 'amount' => '10.00'];
        $sign = $method->invoke($payment, $testData);

        // sign = sha1(imploded_values + secret_key)
        $expectedSign = sha1(implode('', $testData) . config('cashbill.secret_key'));

        $this->assertEquals($expectedSign, $sign);
    }
}
