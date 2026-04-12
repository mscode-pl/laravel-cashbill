<?php

namespace Barstec\Cashbill\Tests;

use Barstec\Cashbill\CashbillServiceProvider;
use Barstec\Cashbill\Events\TransactionCreated;
use Barstec\Cashbill\Events\TransactionStatusChanged;
use Barstec\Cashbill\Events\TransactionSuccessfullyCompleted;
use Barstec\Cashbill\Exceptions\CashbillException;
use Barstec\Cashbill\Http\Models\Transaction;
use Barstec\Cashbill\Order;
use Barstec\Cashbill\Payload;
use Barstec\Cashbill\Payment;
use Barstec\Cashbill\PaymentDetails;
use Barstec\Cashbill\PersonalData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

abstract class BaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Set env vars before application boots
        $_SERVER['CASHBILL_MODE'] = $_SERVER['CASHBILL_MODE'] ?? 'dev';
        $_ENV['CASHBILL_MODE'] = $_ENV['CASHBILL_MODE'] ?? 'dev';

        // Only set default test credentials if not provided via env
        if (!env('CASHBILL_SHOP_ID') && !isset($_SERVER['CASHBILL_SHOP_ID'])) {
            $_SERVER['CASHBILL_SHOP_ID'] = 'test_shop_id';
            $_ENV['CASHBILL_SHOP_ID'] = 'test_shop_id';
        }
        if (!env('CASHBILL_SECRET_KEY') && !isset($_SERVER['CASHBILL_SECRET_KEY'])) {
            $_SERVER['CASHBILL_SECRET_KEY'] = 'test_secret_key';
            $_ENV['CASHBILL_SECRET_KEY'] = 'test_secret_key';
        }

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashbillServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Database setup for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function createPayload(array $overrides = []): Payload
    {
        $payload = new Payload();
        $payload->setTitle($overrides['title'] ?? 'Test Payment');
        $payload->setAmount($overrides['amount'] ?? 10.00);
        $payload->setCurrency($overrides['currency'] ?? 'PLN');
        $payload->setDescription($overrides['description'] ?? 'Test description');
        $payload->setAdditionalData($overrides['additionalData'] ?? 'test_data_123');

        if (isset($overrides['personalData'])) {
            $personalData = new PersonalData();
            $personalData->setFirstName($overrides['personalData']['firstName'] ?? 'John');
            $personalData->setSurname($overrides['personalData']['surname'] ?? 'Doe');
            $personalData->setEmail($overrides['personalData']['email'] ?? 'john@example.com');
            $payload->setPersonalData($personalData);
        }

        return $payload;
    }
}
