<?php

declare(strict_types=1);

namespace Click\Integration\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class Configs
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    public function __construct(
        private readonly ConfigRepository $repository,
        array $overrides = []
    ) {
        $this->config = array_replace_recursive(
            $repository->get('click-integration', []),
            $overrides
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getProviderConfigs(): array
    {
        return $this->config['provider'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProviderCredentials(?string $service = null): array
    {
        $provider = $this->getProviderConfigs();

        $services = $provider['services'] ?? null;
        if (is_array($services) && $services !== []) {
            $serviceKey = $service ?? $this->getDefaultServiceKey();

            if ($serviceKey !== null && isset($services[$serviceKey]) && is_array($services[$serviceKey])) {
                return $services[$serviceKey];
            }

            return [];
        }

        if (isset($provider['click']) && is_array($provider['click'])) {
            $click = $provider['click'];

            if ($this->isAssoc($click) && isset($click['service_id'])) {
                return $click;
            }

            if ($service !== null && isset($click[$service]) && is_array($click[$service])) {
                return $click[$service];
            }

            $firstKey = $this->firstKey($click);
            if ($firstKey !== null && isset($click[$firstKey]) && is_array($click[$firstKey])) {
                return $click[$firstKey];
            }
        }

        return [];
    }

    public function getDefaultServiceKey(): ?string
    {
        $provider = $this->getProviderConfigs();
        $services = $provider['services'] ?? null;

        if (is_array($services) && $services !== []) {
            $default = $provider['default_service'] ?? null;
            if ($default !== null && isset($services[$default])) {
                return $default;
            }

            return $this->firstKey($services);
        }

        if (isset($provider['click']) && is_array($provider['click'])) {
            $click = $provider['click'];

            if ($this->isAssoc($click) && isset($click['service_id'])) {
                return 'default';
            }

            if ($this->isAssoc($click)) {
                $default = $provider['default_service'] ?? $this->firstKey($click);

                if ($default !== null && isset($click[$default])) {
                    return $default;
                }
            }

            if ($click !== []) {
                return 'default';
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function getAvailableServiceKeys(): array
    {
        $provider = $this->getProviderConfigs();
        $services = $provider['services'] ?? null;

        if (is_array($services) && $services !== []) {
            return array_keys($services);
        }

        if (isset($provider['click']) && is_array($provider['click'])) {
            $click = $provider['click'];

            if ($this->isAssoc($click) && isset($click['service_id'])) {
                return ['default'];
            }

            if ($this->isAssoc($click)) {
                return array_keys($click);
            }

            if ($click !== []) {
                return ['default'];
            }
        }

        return [];
    }

    public function getProviderEndpoint(): string
    {
        $provider = $this->getProviderConfigs();

        return $provider['endpoint'] ?? 'https://api.click.uz/v2/merchant/';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDatabaseConfigs(): array
    {
        return $this->config['database'] ?? [];
    }

    public function getDatabaseConnectionName(): ?string
    {
        $database = $this->getDatabaseConfigs();

        return $database['connection'] ?? null;
    }

    public function getDatabaseTable(): string
    {
        $database = $this->getDatabaseConfigs();

        return $database['table'] ?? 'payments';
    }

    public function getSessionHeader(): string
    {
        $session = $this->config['session'] ?? [];

        return $session['header'] ?? 'Auth';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }

    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function firstKey(array $array): ?string
    {
        $key = array_key_first($array);

        return $key !== null ? (string) $key : null;
    }
}
