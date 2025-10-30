<?php

declare(strict_types=1);

namespace Click\Integration;

use Click\Integration\Http\ClickRequest;
use Click\Integration\Repositories\PaymentRepository;
use Click\Integration\Services\Payments;
use Click\Integration\Support\Configs;
use Click\Integration\Support\Helper;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ClickIntegrationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('click-integration')
            ->hasConfigFile('click-integration')
            ->hasMigration('create_click_payments_table');
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(ClickIntegration::class, function ($app) {
            return new ClickIntegration(
                config: $app->make(ConfigRepository::class),
                database: $app->make(DatabaseManager::class)
            );
        });

        $this->app->bind(Payments::class, function ($app) {
            $configs = new Configs($app->make(ConfigRepository::class));
            $helper = new Helper($configs);
            $request = new ClickRequest($helper);
            $repository = new PaymentRepository($app->make(DatabaseManager::class), $configs);

            $payments = new Payments(
                repository: $repository,
                helper: $helper,
                request: $request,
                configs: $configs
            );

            $provider = $configs->getProviderCredentials();
            if (! empty($provider)) {
                $payments->initProvider($provider);
            }

            return $payments;
        });

        $this->app->alias(ClickIntegration::class, 'click.integration');
    }
}
