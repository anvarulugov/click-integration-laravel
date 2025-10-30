# Click Integration Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/anvarulugov/click-integration-laravel.svg?style=flat-square)](https://packagist.org/packages/anvarulugov/click-integration-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/anvarulugov/click-integration-laravel.svg?style=flat-square)](https://packagist.org/packages/anvarulugov/click-integration-laravel)

Laravel-oriented port of the official [`click-integration-php`](https://github.com/click-llc/click-integration-php) library.  
It wraps the Click Shop API and Merchant API flows in familiar Laravel service bindings, configuration and facades, so you can integrate Click in first-party Laravel applications without rewriting the business logic from scratch.

> The official Click payment documentation lives at **[https://docs.click.uz](https://docs.click.uz)**.

## Features
- ✅ Laravel-native bindings & facade for the Click payment client  
- ✅ Multiple configured Click services (per `service_id` / merchant credentials)  
- ✅ Publishable configuration & migration stub for the `payments` table  
- ✅ REST helper mirroring the original Application router (`/prepare`, `/complete`, `/invoice/*`, etc.)  
- ✅ Extensible hooks: override any of the `Payments` lifecycle methods to customise behaviour

## Requirements
- PHP **8.4** or higher
- Laravel **11** or **12**

## Installation

```bash
composer require anvarulugov/click-integration-laravel
```

Publish the configuration (and optional migration) to tailor the package to your project:

```bash
php artisan vendor:publish --tag="click-integration-config"
php artisan vendor:publish --tag="click-integration-migrations"
```

Run the migration if you plan to use the bundled `payments` table schema:

```bash
php artisan migrate
```

Already have your own payments table? Skip the migration publish and adjust the repository as needed.

## Configuration

The published `config/click-integration.php` file mirrors the structure of the base PHP package, with first-class support for multiple services:

```php
return [
    'provider' => [
        'endpoint' => env('CLICK_API_ENDPOINT', 'https://api.click.uz/v2/merchant/'),
        'default_service' => env('CLICK_DEFAULT_SERVICE', 'default'),
        'services' => [
            'default' => [
                'merchant_id' => env('CLICK_MERCHANT_ID'),
                'service_id'  => env('CLICK_SERVICE_ID'),
                'user_id'     => env('CLICK_USER_ID'),
                'secret_key'  => env('CLICK_SECRET_KEY'),
            ],
            // 'secondary' => [
            //     'merchant_id' => env('CLICK_SECONDARY_MERCHANT_ID'),
            //     'service_id'  => env('CLICK_SECONDARY_SERVICE_ID'),
            //     'user_id'     => env('CLICK_SECONDARY_USER_ID'),
            //     'secret_key'  => env('CLICK_SECONDARY_SECRET_KEY'),
            // ],
        ],
    ],
    'database' => [
        'connection' => env('CLICK_DB_CONNECTION'),
        'table'      => env('CLICK_PAYMENTS_TABLE', 'payments'),
    ],
    'session' => [
        'header' => env('CLICK_SESSION_AUTH_HEADER', 'Auth'),
    ],
];
```

> **Security tip:** keep the credential values in environment variables / secret storage. Only the service keys themselves need to live in version control.

## Quick Start

### Resolve the payment service

```php
use Click\Integration\Facades\ClickIntegration;

// Uses the default service defined in config
$payments = ClickIntegration::payments();

// Target a specific service/merchant pair
$secondary = ClickIntegration::payments(['service' => 'secondary']);
```

### Shop API methods

```php
// Prepare
$prepareResponse = $payments->prepare($request->all());

// Complete
$completeResponse = $payments->complete($request->all());
```

### Merchant API methods

```php
$payments->create_invoice([
    'token'        => 'uuid-token',
    'phone_number' => '998901234567',
]);

$payments->check_invoice([
    'token'      => 'uuid-token',
    'invoice_id' => 2222,
]);

$payments->create_card_token([
    'token'       => 'uuid-token',
    'card_number' => '4444-4444-4444-4444',
    'expire_date' => '0128',
    'temporary'   => 1,
]);

$payments->verify_card_token([
    'token'    => 'uuid-token',
    'sms_code' => '12345',
]);

$payments->payment_with_card_token([
    'token'      => 'uuid-token',
    'card_token' => 'CARDTOKEN',
]);

$payments->delete_card_token([
    'token'      => 'uuid-token',
    'card_token' => 'CARDTOKEN',
]);

$payments->check_payment([
    'token'      => 'uuid-token',
    'payment_id' => 1111,
]);

$payments->merchant_trans_id([
    'token'             => 'uuid-token',
    'merchant_trans_id' => 7777,
]);

$payments->cancel([
    'token'      => 'uuid-token',
    'payment_id' => 1111,
]);
```

All methods return associative arrays mirroring Click’s API responses. Exceptions surface as `Click\Integration\Exceptions\ClickException` using Click’s official error codes.

## REST Application Helper

If you expose Click endpoints directly (like the original PHP library), you can use the application runner to dispatch the correct handler based on the request path:

```php
use Click\Integration\Facades\ClickIntegration;
use Illuminate\Support\Facades\Route;

Route::any('/click/{any?}', function () {
    return response()->json(
        ClickIntegration::application()->run()
    );
})->where('any', '.*');
```

Need to switch to a different service profile?

```php
return response()->json(
    ClickIntegration::application(['service' => 'secondary'])->run()
);
```

### Session helper

`Click\Integration\Application\Application::session()` mirrors the original library’s token-based guard:

```php
use Click\Integration\Application\Application;
use Click\Integration\Facades\ClickIntegration;

Application::session(env('CLICK_SESSION_TOKEN'), ['/prepare', '/complete'], function () {
    ClickIntegration::application()->run();
});
```

Requests for endpoints listed in the `$access` array bypass the session guard. All other routes must provide the header defined by `click-integration.session.header` (defaults to `Auth`).

## Extending the Payments client

Create your own class extending `Click\Integration\Services\Payments` to customise any of the lifecycle hooks (they mirror the base PHP package):

```php
use Click\Integration\Services\Payments as BasePayments;
use Psr\Http\Message\ResponseInterface;

class MyPayments extends BasePayments
{
    protected function on_invoice_creating(array $payload): ResponseInterface
    {
        // Inspect or modify payload before forwarding to Click
        return parent::on_invoice_creating($payload);
    }

    protected function on_invoice_created(array $request, ResponseInterface $response, string $token): ?array
    {
        $result = parent::on_invoice_created($request, $response, $token);

        // Add custom bookkeeping here...

        return $result;
    }
}
```

Bind your custom class in a service provider:

```php
$this->app->bind(\Click\Integration\Services\Payments::class, MyPayments::class);
```

## Testing

```bash
composer test
```

The package ships with Pest & PHPUnit support through Orchestra Testbench.

## Roadmap & Ideas
- Artisan commands to manage Click service credentials
- Example HTTP controllers for webhook-style integrations
- Demo application / Postman collection

Contributions and ideas are welcome—open an issue or submit a pull request!

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for a full list of updates.

## Credits

- [click-llc/click-integration-php](https://github.com/click-llc/click-integration-php) – original library  
- [Click LLC](https://click.uz)  
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for details.
