<?php

declare(strict_types=1);

namespace Click\Integration\Application;

use Click\Integration\Exceptions\ClickException;
use Click\Integration\Services\Payments;
use Click\Integration\Support\Configs;
use Click\Integration\Support\Helper;
use Illuminate\Container\Container;
use Illuminate\Http\Request;

class Application
{
    public function __construct(
        private readonly Payments $model,
        private readonly Helper $helper
    ) {
    }

    /**
     * @throws ClickException
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return $this->requestHandler($this->model);
    }

    /**
     * @param callable():mixed $callback
     *
     * @return mixed
     */
    public static function session(
        string $token,
        array $access,
        callable $callback,
        ?Configs $configs = null,
        ?Helper $helper = null
    ) {
        try {
            $container = Container::getInstance();

            if ($configs === null) {
                if (! $container->bound('config')) {
                    throw new ClickException(
                        'Configuration repository is not available',
                        ClickException::ERROR_INTERNAL_SYSTEM
                    );
                }

                $configs = new Configs($container->make('config'));
            }

            $helper ??= new Helper($configs);

            $request = self::resolveRequest();
            if ($request === null) {
                throw new ClickException(
                    'Session could not perform without request context',
                    ClickException::ERROR_INTERNAL_SYSTEM
                );
            }

            $url = $request->getRequestUri();
            if (in_array($url, $access, true)) {
                return $callback();
            }

            $headerName = $configs->getSessionHeader();
            $authHeader = $request->headers->get($headerName);

            if ($authHeader === null) {
                throw new ClickException(
                    'Session could not perform without Auth token',
                    ClickException::ERROR_INTERNAL_SYSTEM
                );
            }

            if ($authHeader !== $token) {
                throw new ClickException(
                    'Authorization error',
                    ClickException::ERROR_INTERNAL_SYSTEM
                );
            }

            return $callback();
        } catch (ClickException $exception) {
            return $exception->error();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function requestHandler(Payments $model): array
    {
        return match ($this->helper->url) {
            '/prepare' => $model->prepare(),
            '/complete' => $model->complete(),
            '/payment' => $model->payment(),
            '/invoice/create' => $model->create_invoice(),
            '/invoice/check' => $model->check_invoice(),
            '/payment/status' => $model->check_payment(),
            '/payment/merchant_trans_id', '/payment/merchant_train_id' => $model->merchant_trans_id(),
            '/cancel' => $model->cancel(),
            '/card/create' => $model->create_card_token(),
            '/card/verify' => $model->verify_card_token(),
            '/card/payment' => $model->payment_with_card_token(),
            '/card/delete' => $model->delete_card_token(),
            default => throw new ClickException(
                'Incorrect request',
                ClickException::ERROR_METHOD_NOT_FOUND
            ),
        };
    }

    private static function resolveRequest(): ?Request
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
}
