<?php

namespace Barstec\Cashbill\Tests\Unit;

use Barstec\Cashbill\Exceptions\CashbillException;
use Barstec\Cashbill\Http\Models\Transaction;
use Barstec\Cashbill\Order;
use Barstec\Cashbill\PaymentDetails;
use Barstec\Cashbill\Tests\BaseTestCase;
use Illuminate\Support\Facades\Http;

class OrderTest extends BaseTestCase
{
    /** @test */
    public function it_throws_exception_for_invalid_order_id()
    {
        $this->expectException(CashbillException::class);
        $this->expectExceptionMessage('Transaction order ID: invalid_id is invalid');

        $order = new Order('invalid_id');
        $order->update();
    }

    /** @test */
    public function it_updates_order_and_returns_payment_details()
    {
        // Utworzenie transakcji w bazie
        Transaction::create([
            'order_id' => 'test_order_123',
            'title' => 'Test Order',
            'amount' => 50.00,
            'currency_code' => 'PLN',
            'status' => 'created',
        ]);

        Http::fake([
            'pay.cashbill.pl/*' => Http::response([
                'orderId' => 'test_order_123',
                'paymentChannel' => 'bank_transfer',
                'amount' => ['value' => 50.00, 'currencyCode' => 'PLN'],
                'title' => 'Test Order',
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
                'additionalData' => 'test_data',
                'status' => 'Start',
                'details' => ['bankId' => 'bank_1']
            ], 200)
        ]);

        $order = new Order('test_order_123');
        $details = $order->update();

        $this->assertInstanceOf(PaymentDetails::class, $details);
        $this->assertEquals('test_order_123', $details->getOrderId());
        $this->assertEquals('Start', $details->getStatus());
        $this->assertEquals(50.00, $details->getAmount());
        $this->assertEquals('PLN', $details->getCurrency());
        $this->assertEquals('bank_transfer', $details->getPaymentChannel());
    }

    /** @test */
    public function it_detects_status_change()
    {
        Transaction::create([
            'order_id' => 'test_order_456',
            'title' => 'Test Order',
            'amount' => 100.00,
            'currency_code' => 'PLN',
            'status' => 'created',
        ]);

        Http::fake([
            'pay.cashbill.pl/*' => Http::response([
                'orderId' => 'test_order_456',
                'paymentChannel' => 'card',
                'amount' => ['value' => 100.00, 'currencyCode' => 'PLN'],
                'title' => 'Test Order',
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
                'status' => 'PositiveAuthorization',
                'details' => ['bankId' => null]
            ], 200)
        ]);

        $order = new Order('test_order_456');
        $order->update();

        $this->assertTrue($order->statusChanged());
    }

    /** @test */
    public function it_detects_no_status_change()
    {
        Transaction::create([
            'order_id' => 'test_order_789',
            'title' => 'Test Order',
            'amount' => 75.00,
            'currency_code' => 'PLN',
            'status' => 'PositiveFinish',
        ]);

        Http::fake([
            'pay.cashbill.pl/*' => Http::response([
                'orderId' => 'test_order_789',
                'paymentChannel' => 'card',
                'amount' => ['value' => 75.00, 'currencyCode' => 'PLN'],
                'title' => 'Test Order',
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
                'status' => 'PositiveFinish',
                'details' => ['bankId' => null]
            ], 200)
        ]);

        $order = new Order('test_order_789');
        $order->update();

        $this->assertFalse($order->statusChanged());
    }

    /** @test */
    public function it_detects_positive_finish()
    {
        Transaction::create([
            'order_id' => 'test_order_success',
            'title' => 'Test Order',
            'amount' => 200.00,
            'currency_code' => 'PLN',
            'status' => 'PositiveAuthorization',
        ]);

        Http::fake([
            'pay.cashbill.pl/*' => Http::response([
                'orderId' => 'test_order_success',
                'paymentChannel' => 'card',
                'amount' => ['value' => 200.00, 'currencyCode' => 'PLN'],
                'title' => 'Test Order',
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
                'status' => 'PositiveFinish',
                'details' => ['bankId' => null]
            ], 200)
        ]);

        $order = new Order('test_order_success');
        $order->update();

        $this->assertTrue($order->isPositiveFinish());
    }

    /** @test */
    public function it_recognizes_final_statuses()
    {
        $order = new Order('any_order');

        $this->assertTrue($order->isFinalStatus('PositiveFinish'));
        $this->assertTrue($order->isFinalStatus('NegativeFinish'));
        $this->assertTrue($order->isFinalStatus('Fraud'));
        $this->assertFalse($order->isFinalStatus('Start'));
        $this->assertFalse($order->isFinalStatus('created'));
    }
}
