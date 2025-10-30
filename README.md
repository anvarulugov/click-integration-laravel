# Click Integration Laravel

Laravel-oriented port of the [`click-integration-php`](https://github.com/click-llc/click-integration-php) library.  
It packages the core Click Shop API logic (payments, invoices, card tokens, reversals) behind Laravel service bindings and configuration so the integration can be dropped into any Laravel application with minimal effort.

## Installation

```bash
composer require click/click-integration-laravel
```

Publish the configuration (and optional migration) to tailor the package to your project:

```bash
php artisan vendor:publish --tag="click-integration-config"
php artisan vendor:publish --tag="click-integration-migrations"
```

Then run the migration if you need the default `payments` table:

```bash
php artisan migrate
```

## Configuration

The published `config/click-integration.php` file exposes the same settings as the original package:

```php
return [
    'provider' => [
        'endpoint' => env('CLICK_API_ENDPOINT', 'https://api.click.uz/v2/merchant/'),
        'default_service' => env('CLICK_DEFAULT_SERVICE', 'default'),
        'services' => [
            'default' => [
                'merchant_id' => env('CLICK_MERCHANT_ID'),
                'service_id' => env('CLICK_SERVICE_ID'),
                'user_id' => env('CLICK_USER_ID'),
                'secret_key' => env('CLICK_SECRET_KEY'),
            ],
            'secondary' => [
                'merchant_id' => env('CLICK_SECONDARY_MERCHANT_ID'),
                'service_id' => env('CLICK_SECONDARY_SERVICE_ID'),
                'user_id' => env('CLICK_SECONDARY_USER_ID'),
                'secret_key' => env('CLICK_SECONDARY_SECRET_KEY'),
            ],
        ],
    ],
    'database' => [
        'connection' => env('CLICK_DB_CONNECTION'),
        'table' => env('CLICK_PAYMENTS_TABLE', 'payments'),
    ],
    'session' => [
        'header' => env('CLICK_SESSION_AUTH_HEADER', 'Auth'),
    ],
];
```

## Usage

Resolve the payment service from the container (or use the facade) and call the same methods that existed in the base PHP library:

```php
use Click\Integration\Facades\ClickIntegration;

$payments = ClickIntegration::payments();

// Merchant API helpers
$payments->create_invoice([
    'token' => 'uuid-token',
    'phone_number' => '998901234567',
]);

$payments->check_invoice([
    'token' => 'uuid-token',
    'invoice_id' => 12345,
]);

// Shop API helpers
$payments->prepare($request->all());
$payments->complete($request->all());
```

### Working with multiple services

Each set of credentials can be grouped under a service key. Resolve a tailored instance by passing the desired key:

```php
$primary = ClickIntegration::payments();
$secondary = ClickIntegration::payments(['service' => 'secondary']);

$secondary->create_invoice([
    'token' => 'uuid-token',
    'phone_number' => '998901234567',
]);

// REST helper for the secondary service
return response()->json(
    ClickIntegration::application(['service' => 'secondary'])->run()
);
```

To wire the full REST application layer (auto-dispatching `/prepare`, `/card/create`, etc.) you can use the application wrapper:

```php
use Click\Integration\Facades\ClickIntegration;

return response()->json(
    ClickIntegration::application()->run()
);
```

All methods return associative arrays mirroring the responses from the Click API. Exceptions are thrown as `Click\Integration\Exceptions\ClickException` with the same error codes defined by Click.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent updates.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE.md) for details.
