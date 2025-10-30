<?php

declare(strict_types=1);

namespace Click\Integration\Services;

use Click\Integration\Exceptions\ClickException;
use Click\Integration\Http\ClickRequest;
use Click\Integration\Repositories\PaymentRepository;
use Click\Integration\Support\Configs;
use Click\Integration\Support\Helper;
use Click\Integration\Support\PaymentStatus;
use GuzzleHttp\Client;

class Payments extends BasePayments
{
    protected array $provider = [];

    public function __construct(
        PaymentRepository $repository,
        Helper $helper,
        ClickRequest $request,
        protected Configs $configs
    ) {
        parent::__construct($repository, $helper, $request);
    }

    /**
     * @throws ClickException
     */
    public function initProvider(array $provider): void
    {
        foreach (['merchant_id', 'service_id', 'user_id', 'secret_key'] as $key) {
            if (! isset($provider[$key])) {
                throw new ClickException(
                    sprintf('Missing provider configuration: %s', $key),
                    ClickException::ERROR_INSUFFICIENT_PRIVILEGE
                );
            }
        }

        $this->provider = $provider;
        $this->setProvider($provider);

        $timestamp = $this->helper->timestamp;
        $authSignature = sprintf(
            '%s:%s:%s',
            $provider['user_id'],
            sha1($timestamp.$provider['secret_key']),
            $timestamp
        );

        $this->client = new Client([
            'base_uri' => $this->configs->getProviderEndpoint(),
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Auth' => $authSignature,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function create_invoice(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $payment = $this->model->findByToken($token);
        if ($payment === null) {
            throw new ClickException(
                'Transaction does not exist',
                ClickException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        if (! in_array($payment['status'] ?? null, [PaymentStatus::INPUT, PaymentStatus::REFUNDED], true)) {
            return [
                'error_code' => -31300,
                'error_note' => 'Payment in processing',
            ];
        }

        $json = [
            'service_id' => $this->provider['service_id'],
            'merchant_trans_id' => $this->get_merchant_trans_id($token),
            'phone_number' => $request['phone_number'],
            'amount' => (float) ($payment['total'] ?? 0),
        ];

        $response = $this->on_invoice_creating($json);
        $result = $this->on_invoice_created($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function create_card_token(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $payment = $this->model->findByToken($token);
        if ($payment === null) {
            throw new ClickException(
                'Transaction does not exist',
                ClickException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        if (! in_array($payment['status'] ?? null, [PaymentStatus::INPUT, PaymentStatus::REFUNDED], true)) {
            return [
                'error_code' => -31300,
                'error_note' => 'Payment in processing',
            ];
        }

        $json = [
            'service_id' => $this->provider['service_id'],
            'card_number' => $request['card_number'],
            'expire_date' => $request['expire_date'],
            'temporary' => $request['temporary'] ?? 1,
        ];

        $response = $this->on_card_token_creating($json);
        $result = $this->on_card_token_created($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function verify_card_token(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $payment = $this->model->findByToken($token);
        if ($payment === null) {
            throw new ClickException(
                'Transaction does not exist',
                ClickException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        if (($payment['status'] ?? null) !== PaymentStatus::WAITING) {
            throw new ClickException(
                'Payment is not stable to perform',
                ClickException::ERROR_COULD_NOT_PERFORM
            );
        }

        $json = [
            'service_id' => $this->provider['service_id'],
            'card_token' => $payment['card_token'],
            'sms_code' => $request['sms_code'],
        ];

        $response = $this->on_card_token_verifying($json);
        $result = $this->on_card_token_verified($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function payment_with_card_token(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $payment = $this->model->findByToken($token);
        if ($payment === null) {
            throw new ClickException(
                'Transaction does not exist',
                ClickException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        if (($payment['card_token'] ?? null) !== ($request['card_token'] ?? null)) {
            throw new ClickException(
                'Incorrect card token',
                ClickException::ERROR_COULD_NOT_PERFORM
            );
        }

        $json = [
            'service_id' => $this->provider['service_id'],
            'card_token' => $request['card_token'],
            'amount' => (float) ($payment['total'] ?? 0),
            'merchant_trans_id' => $this->get_merchant_trans_id($token),
        ];

        $response = $this->on_card_token_paying($json);
        $result = $this->on_card_token_payed($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function delete_card_token(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $payment = $this->model->findByToken($token);
        if ($payment === null) {
            throw new ClickException(
                'Transaction does not exist',
                ClickException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        if (($payment['card_token'] ?? null) !== ($request['card_token'] ?? null)) {
            throw new ClickException(
                'Incorrect card token',
                ClickException::ERROR_COULD_NOT_PERFORM
            );
        }

        $json = [
            'service_id' => $this->provider['service_id'],
            'card_token' => $request['card_token'],
        ];

        $response = $this->on_card_token_deleting($json);
        $result = $this->on_card_token_deleted($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function check_invoice(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $payment = $this->model->findByToken($token);
        if ($payment === null) {
            throw new ClickException(
                'Transaction does not exist',
                ClickException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        if (($payment['invoice_id'] ?? null) !== ($request['invoice_id'] ?? null)) {
            throw new ClickException(
                'Incorrect invoice id',
                ClickException::ERROR_COULD_NOT_PERFORM
            );
        }

        $json = [
            'service_id' => $this->provider['service_id'],
            'invoice_id' => $request['invoice_id'],
        ];

        $response = $this->on_invoice_checking($json);
        $result = $this->on_invoice_checked($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function check_payment(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $this->ensureToken($request);

        $json = [
            'service_id' => $this->provider['service_id'],
            'payment_id' => $request['payment_id'],
        ];

        $response = $this->on_payment_checking($json);
        $result = $this->on_payment_checked($json, $response, $request['token']);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function merchant_trans_id(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $json = [
            'service_id' => $this->provider['service_id'],
            'merchant_trans_id' => $request['merchant_trans_id'],
        ];

        $response = $this->on_checking_with_merchant_trans_id($json);
        $result = $this->on_checked_with_merchant_trans_id($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ClickException
     */
    public function cancel(?array $request = null): array
    {
        $this->ensureProviderInitialized();
        $request ??= $this->request->post();
        $token = $this->ensureToken($request);

        $json = [
            'service_id' => $this->provider['service_id'],
            'payment_id' => $request['payment_id'],
        ];

        $response = $this->on_canceling($json);
        $result = $this->on_canceled($json, $response, $token);

        if ($result !== null) {
            return $result;
        }

        throw new ClickException(
            $response->getReasonPhrase(),
            ClickException::ERROR_INSUFFICIENT_PRIVILEGE
        );
    }

    /**
     * @throws ClickException
     */
    public function payment(): array
    {
        $this->ensureProviderInitialized();

        $check = $this->request->payment();

        if ($check === null || ! isset($check['type'])) {
            throw new ClickException(
                'Could not detect the method',
                ClickException::ERROR_INSUFFICIENT_PRIVILEGE
            );
        }

        return match ($check['type']) {
            'phone_number' => $this->create_invoice($check),
            'card_number' => $this->create_card_token($check),
            'sms_code' => $this->verify_card_token($check),
            'card_token' => $this->payment_with_card_token($check),
            'delete_card_token' => $this->delete_card_token($check),
            'check_invoice_id' => $this->check_invoice($check),
            'check_payment' => $this->check_payment($check),
            'merchant_trans_id' => $this->merchant_trans_id($check),
            'cancel' => $this->cancel($check),
            default => throw new ClickException(
                'Could not detect the method',
                ClickException::ERROR_INSUFFICIENT_PRIVILEGE
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $request
     *
     * @throws ClickException
     */
    private function ensureToken(array $request): string
    {
        if (! isset($request['token'])) {
            throw new ClickException(
                'Could not make a payment without payment_id or token',
                ClickException::ERROR_COULD_NOT_PERFORM
            );
        }

        return (string) $request['token'];
    }

    /**
     * @throws ClickException
     */
    private function ensureProviderInitialized(): void
    {
        if (empty($this->provider)) {
            throw new ClickException(
                'Could not perform the request without provider',
                ClickException::ERROR_COULD_NOT_PERFORM
            );
        }
    }
}
