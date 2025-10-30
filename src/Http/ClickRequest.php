<?php

declare(strict_types=1);

namespace Click\Integration\Http;

use Click\Integration\Exceptions\ClickException;
use Click\Integration\Support\Helper;
use Illuminate\Container\Container;
use Illuminate\Http\Request as HttpRequest;

class ClickRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $request;

    public function __construct(
        private readonly Helper $helper,
        ?array $payload = null,
        ?HttpRequest $httpRequest = null
    ) {
        $this->request = $payload ?? $this->extractRequestPayload($httpRequest);
    }

    /**
     * @return array<string, mixed>
     */
    public function post(): array
    {
        return $this->request;
    }

    /**
     * Detects the request type for merchant API operations.
     *
     * @return array<string, mixed>|null
     *
     * @throws ClickException
     */
    public function payment(): ?array
    {
        if ($this->has('action')) {
            $payload = $this->request;
            $payload['type'] = ((int) $payload['action'] === 0) ? 'prepare' : 'complete';

            return $payload;
        }

        if ($this->has('phone_number')) {
            $phoneNumber = $this->helper->checkPhoneNumber((string) $this->request['phone_number']);
            if ($phoneNumber === null) {
                throw new ClickException(
                    'Incorrect phone number',
                    ClickException::ERROR_COULD_NOT_PERFORM
                );
            }

            $token = $this->requireToken();

            return [
                'type' => 'phone_number',
                'token' => $token,
                'phone_number' => $phoneNumber,
            ];
        }

        if ($this->has('card_number')) {
            $cardNumber = $this->helper->checkCardNumber((string) $this->request['card_number']);
            if ($cardNumber === null) {
                throw new ClickException(
                    'Incorrect card number',
                    ClickException::ERROR_COULD_NOT_PERFORM
                );
            }

            $token = $this->requireToken();
            $temporary = $this->has('temporary') ? (int) $this->request['temporary'] : 1;

            return [
                'type' => 'card_number',
                'token' => $token,
                'card_number' => $cardNumber,
                'expire_date' => $this->request['expire_date'] ?? null,
                'temporary' => $temporary,
            ];
        }

        if ($this->has('sms_code')) {
            $token = $this->requireToken();

            return [
                'type' => 'sms_code',
                'token' => $token,
                'sms_code' => (string) $this->request['sms_code'],
            ];
        }

        if ($this->has('card_token')) {
            $token = $this->requireToken();

            return [
                'type' => 'card_token',
                'token' => $token,
                'card_token' => $this->request['card_token'],
            ];
        }

        if ($this->has('delete_card_token')) {
            $token = $this->requireToken();

            return [
                'type' => 'delete_card_token',
                'token' => $token,
                'card_token' => $this->request['delete_card_token'],
            ];
        }

        if ($this->has('check_invoice_id')) {
            $token = $this->requireToken();

            return [
                'type' => 'check_invoice_id',
                'token' => $token,
                'invoice_id' => $this->request['check_invoice_id'],
            ];
        }

        if ($this->has('payment_id')) {
            return [
                'type' => 'check_payment',
                'payment_id' => (int) $this->request['payment_id'],
            ];
        }

        if ($this->has('merchant_trans_id')) {
            $token = $this->requireToken();

            return [
                'type' => 'merchant_trans_id',
                'token' => $token,
                'merchant_trans_id' => (int) $this->request['merchant_trans_id'],
            ];
        }

        if ($this->has('cancel_payment_id')) {
            return [
                'type' => 'cancel',
                'payment_id' => $this->request['cancel_payment_id'],
            ];
        }

        return null;
    }

    private function has(string $key): bool
    {
        return isset($this->request[$key]) && $this->request[$key] !== null && $this->request[$key] !== '';
    }

    private function requireToken(): int
    {
        if (! $this->has('token')) {
            throw new ClickException(
                'Could not make a payment without payment_id or token',
                ClickException::ERROR_COULD_NOT_PERFORM
            );
        }

        return (int) $this->request['token'];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    private function extractRequestPayload(?HttpRequest $httpRequest): array
    {
        $httpRequest ??= $this->resolveRequest();

        if (! $httpRequest instanceof HttpRequest) {
            return [];
        }

        $payload = $httpRequest->all();
        if (! empty($payload)) {
            return $payload;
        }

        $content = $httpRequest->getContent();
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new ClickException(
                'Incorrect JSON-RPC object',
                ClickException::ERROR_INVALID_JSON_RPC_OBJECT
            );
        }

        return $decoded;
    }

    private function resolveRequest(): ?HttpRequest
    {
        $container = Container::getInstance();

        if ($container->bound('request')) {
            $resolved = $container->make('request');

            if ($resolved instanceof HttpRequest) {
                return $resolved;
            }
        }

        return null;
    }
}
