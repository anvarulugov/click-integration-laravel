<?php

declare(strict_types=1);

namespace Click\Integration;

use Click\Integration\Application\Application as ClickApplication;
use Click\Integration\Http\ClickRequest;
use Click\Integration\Repositories\PaymentRepository;
use Click\Integration\Services\Payments;
use Click\Integration\Support\Configs;
use Click\Integration\Support\Helper;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

class ClickIntegration
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly DatabaseManager $database
    ) {}

    public function payments(array $overrides = []): Payments
    {
        $service = $overrides['service'] ?? null;
        $configOverrides = $overrides;
        unset($configOverrides['service']);

        $configs = new Configs($this->config, $configOverrides);
        $helper = new Helper($configs);
        $request = new ClickRequest($helper);
        $repository = new PaymentRepository($this->database, $configs);

        $payments = new Payments(
            repository: $repository,
            helper: $helper,
            request: $request,
            configs: $configs
        );

        $provider = $configs->getProviderCredentials($service);
        if ($service !== null && empty($provider)) {
            throw new InvalidArgumentException(sprintf('Click service "%s" is not configured.', $service));
        }

        if (! empty($provider)) {
            $payments->initProvider($provider);
        }

        return $payments;
    }

    public function application(array $overrides = []): ClickApplication
    {
        $configOverrides = $overrides;
        unset($configOverrides['service']);

        $configs = new Configs($this->config, $configOverrides);
        $helper = new Helper($configs);

        return new ClickApplication(
            $this->payments($overrides),
            $helper
        );
    }
}
