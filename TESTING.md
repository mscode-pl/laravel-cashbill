# Testing CashBill Package

This package includes comprehensive tests to ensure everything works correctly, both with mocked data and with real CashBill API integration.

## Test Structure

The test suite is divided into two parts:

### 1. **Unit Tests** (`tests/Unit/`)
Tests that use HTTP mocking to simulate CashBill API responses. No real API calls are made, so these tests run fast and don't require credentials.

**What's tested:**
- Payload creation and validation
- Payment initialization with proper error handling
- Order status updates and detection
- Notification webhook handling
- Event dispatching
- Signature verification

**Run unit tests:**
```bash
vendor/bin/phpunit --testsuite=Unit
```

### 2. **Integration Tests** (`tests/Integration/`)
Tests that make **real API calls** to CashBill's testing environment. These verify that the package works correctly with the actual payment system.

**What's tested:**
- Real payment creation with CashBill API
- Fetching payment details from live system
- Batch payment processing
- End-to-end transaction flow

**Prerequisites:**

1. Copy the example test environment file:
```bash
cp .env.test.example .env.test
```

2. Fill in your CashBill credentials in `.env.test`:
```env
CASHBILL_MODE=dev
CASHBILL_SHOP_ID=your_shop_id_here
CASHBILL_SECRET_KEY=your_secret_key_here
```

You can obtain these values from your CashBill account settings. The `CASHBILL_MODE=dev` ensures you're using the testing environment, so no real money is processed.

**Run integration tests:**
```bash
vendor/bin/phpunit --testsuite=Integration
```

> **Note:** Integration tests will be automatically skipped if credentials are not configured.

## Setup

### 1. Install dependencies

```bash
composer install
```

This will install both production dependencies and dev dependencies (including `orchestra/testbench` for Laravel package testing).

### 2. Run all tests

```bash
# Run everything (unit + integration)
vendor/bin/phpunit

# Run only unit tests (no credentials needed)
vendor/bin/phpunit --testsuite=Unit

# Run integration tests (credentials required)
vendor/bin/phpunit --testsuite=Integration
```

### 3. Run specific test file

```bash
vendor/bin/phpunit tests/Unit/PaymentTest.php
```

## Configuration

Test configuration is located in `phpunit.xml.dist`. Key settings:
- Uses SQLite in-memory database for fast tests
- Loads migrations automatically
- Sets default test environment variables

## Writing Your Own Tests

If you're using this package in your Laravel application, you can test it in two ways:

### Option 1: Mock the HTTP calls (recommended for CI/CD)

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'pay.cashbill.pl/*' => Http::response([
        'id' => 'test_order_id',
        'redirectUrl' => 'https://pay.cashbill.pl/test/redirect'
    ], 200)
]);

$payload = new Payload();
$payload->setTitle('Test Order');
$payload->setAmount(10.00);

$payment = new Payment($payload);
$response = $payment->redirect();

// Assert redirect URL
$this->assertEquals('https://pay.cashbill.pl/test/redirect', $response->getTargetUrl());
```

### Option 2: Use real credentials (for manual testing)

Set up your `.env` file with CashBill credentials and create a test route:

```php
Route::get('/test-cashbill', function () {
    $payload = new Payload();
    $payload->setTitle('Test Payment');
    $payload->setAmount(1.00);
    $payload->setAdditionalData('manual_test_' . time());
    
    $payment = new Payment($payload);
    return $payment->redirect();
});
```

Visit the route in your browser to verify the redirect works correctly.

## CI/CD Integration

For GitHub Actions or other CI systems, you can run tests with secrets:

```yaml
# .github/workflows/tests.yml
- name: Run tests
  env:
    CASHBILL_MODE: dev
    CASHBILL_SHOP_ID: ${{ secrets.CASHBILL_SHOP_ID }}
    CASHBILL_SECRET_KEY: ${{ secrets.CASHBILL_SECRET_KEY }}
  run: vendor/bin/phpunit
```

## Troubleshooting

### Tests are skipped
If you see "skipped" messages, it means CashBill credentials are not set. Either:
- Set credentials in `.env.test` for integration tests
- Or run only unit tests with `--testsuite=Unit`

### HTTP request failed errors
- Check that your `CASHBILL_SHOP_ID` and `CASHBILL_SECRET_KEY` are correct
- Verify you're in `dev` mode for testing
- Check your internet connection

### Migration errors
Make sure migrations are loaded. The test base class handles this automatically.
