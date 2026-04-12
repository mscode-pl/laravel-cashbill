<?php

namespace Barstec\Cashbill\Tests\Unit;

use Barstec\Cashbill\Exceptions\CashbillException;
use Barstec\Cashbill\Payload;
use Barstec\Cashbill\PersonalData;
use Barstec\Cashbill\Tests\BaseTestCase;

class PayloadTest extends BaseTestCase
{
    /** @test */
    public function it_creates_payload_with_defaults()
    {
        $payload = new Payload();

        $this->assertEquals('PLN', $payload->getCurrency());
        $this->assertEquals('PL', $payload->getLanguageCode());
    }

    /** @test */
    public function it_sets_and_gets_title()
    {
        $payload = $this->createPayload();

        $this->assertEquals('Test Payment', $payload->getTitle());
    }

    /** @test */
    public function it_sets_and_gets_amount()
    {
        $payload = $this->createPayload();

        $this->assertEquals(10.00, $payload->getAmount());
    }

    /** @test */
    public function it_sets_and_gets_currency()
    {
        $payload = $this->createPayload();

        $this->assertEquals('PLN', $payload->getCurrency());
    }

    /** @test */
    public function it_sets_and_gets_personal_data()
    {
        $personalData = new PersonalData();
        $personalData->setFirstName('Jane');
        $personalData->setSurname('Smith');
        $personalData->setEmail('jane@example.com');

        $payload = new Payload();
        $payload->setPersonalData($personalData);

        $this->assertEquals('Jane', $payload->getPersonalData()->getFirstName());
        $this->assertEquals('jane@example.com', $payload->getPersonalData()->getEmail());
    }

    /** @test */
    public function it_generates_complete_payload_array()
    {
        $payload = $this->createPayload([
            'title' => 'Order #123',
            'amount' => 99.99,
            'currency' => 'EUR',
            'description' => 'Premium package',
            'additionalData' => 'product_456',
            'personalData' => [
                'firstName' => 'Alice',
                'surname' => 'Wonder',
                'email' => 'alice@example.com',
            ]
        ]);

        $result = $payload->getAll();

        $this->assertEquals('Order #123', $result['title']);
        $this->assertEquals(99.99, $result['amount.value']);
        $this->assertEquals('EUR', $result['amount.currencyCode']);
        $this->assertEquals('Premium package', $result['description']);
        $this->assertEquals('product_456', $result['additionalData']);
        $this->assertEquals('Alice', $result['personalData.firstName']);
        $this->assertEquals('Wonder', $result['personalData.surname']);
        $this->assertEquals('alice@example.com', $result['personalData.email']);
    }

    /** @test */
    public function it_sets_return_urls()
    {
        $payload = new Payload();
        $payload->setReturnUrl('https://example.com/success');
        $payload->setNegativeReturnUrl('https://example.com/failure');

        $this->assertEquals('https://example.com/success', $payload->getReturnUrl());
        $this->assertEquals('https://example.com/failure', $payload->getNegativeReturnUrl());
    }

    /** @test */
    public function it_sets_additional_data()
    {
        $payload = new Payload();
        $payload->setAdditionalData('order_789');

        $this->assertEquals('order_789', $payload->getAdditionalData());
    }
}
