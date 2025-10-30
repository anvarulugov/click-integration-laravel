<?php

declare(strict_types=1);

namespace Click\Integration\Support;

use Illuminate\Container\Container;
use Illuminate\Http\Request;

class Helper
{
    public string $endpoint;

    public string $method;

    public int $timestamp;

    public string $url;

    private ?Request $request;

    public function __construct(
        private readonly Configs $configs,
        ?Request $request = null
    ) {
        $this->endpoint = $configs->getProviderEndpoint();
        $this->request = $request ?? $this->resolveRequest();
        $this->method = $this->request?->getMethod() ?? 'GET';
        $this->timestamp = (int) ($this->request?->server('REQUEST_TIME') ?? time());
        $this->url = $this->request?->getRequestUri() ?? '/';
    }

    private function resolveRequest(): ?Request
    {
        $container = Container::getInstance();

        if ($container->bound('request')) {
            $resolved = $container->make('request');

            if ($resolved instanceof Request) {
                return $resolved;
            }
        }

        return null;
    }

    public function checkCardNumber(string $cardNumber): ?string
    {
        if (preg_match('/^[0-9]{12}$/', $cardNumber) === 1) {
            return $cardNumber;
        }

        if (preg_match('/^[0-9]{4}-[0-9]{4}-[0-9]{4}-[0-9]{4}$/', $cardNumber) === 1) {
            return str_replace('-', '', $cardNumber);
        }

        return null;
    }

    public function checkPhoneNumber(string $phoneNumber): ?string
    {
        $normalized = ltrim($phoneNumber, '+');

        if (preg_match('/^[0-9]{12}$/', $normalized) === 1) {
            return $normalized;
        }

        if (preg_match('/^[0-9]{9}$/', $normalized) === 1) {
            return '998' . $normalized;
        }

        if (preg_match('/^[0-9]{8}$/', $normalized) === 1) {
            return '9989' . $normalized;
        }

        return null;
    }
}
