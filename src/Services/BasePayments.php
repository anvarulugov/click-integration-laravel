<?php

declare(strict_types=1);

namespace Click\Integration\Services;

use Click\Integration\Exceptions\ClickException;
use Click\Integration\Support\PaymentStatus;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class BasePayments extends BasicPaymentMethods
{
    protected ?ClientInterface $client = null;

    /**
     * @throws ClickException
     */
    protected function get_merchant_trans_id(string $token): int
    {
        $payment = $this->model->findByToken($token);

        if ($payment === null || ! isset($payment['id'])) {
            throw new ClickException(
                'Transaction does not exist',
                ClickException::ERROR_TRANSACTION_NOT_FOUND
            );
        }

        $id = (int) $payment['id'];
        $this->model->updateById($id, [
            'merchant_trans_id' => $id,
        ]);

        return $id;
    }

    protected function on_user_is_exist(array $payment): ?bool
    {
        return null;
    }

    protected function on_invoice_creating(array $data): ResponseInterface
    {
        return $this->client->request('POST', 'invoice/create', [
            'json' => $data,
        ]);
    }

    protected function on_invoice_created(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::WAITING,
                'status_note' => $result['error_note'] ?? null,
                'invoice_id' => $result['invoice_id'] ?? null,
                'phone_number' => $request['phone_number'] ?? null,
            ]);
        } else {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::ERROR,
                'status_note' => $result['error_note'] ?? null,
            ]);
        }

        return $result;
    }

    protected function on_invoice_checking(array $data): ResponseInterface
    {
        $url = 'invoice/status/'.$data['service_id'].'/'.$data['invoice_id'];

        return $this->client->request('GET', $url);
    }

    protected function on_invoice_checked(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $status = (int) ($result['status'] ?? 0);
            $update = [
                'status_note' => $result['error_note'] ?? null,
            ];

            if ($status > 0) {
                $update['status'] = PaymentStatus::CONFIRMED;
            } elseif ($status === -99) {
                $update['status'] = PaymentStatus::REJECTED;
            } else {
                $update['status'] = PaymentStatus::ERROR;
            }

            $this->model->updateByToken($token, $update);
        }

        return $result;
    }

    protected function on_canceling(array $data): ResponseInterface
    {
        $url = 'payment/reversal/'.$data['service_id'].'/'.$data['payment_id'];

        return $this->client->request('DELETE', $url);
    }

    protected function on_canceled(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::REJECTED,
                'status_note' => $result['error_note'] ?? null,
                'payment_id' => $result['payment_id'] ?? null,
            ]);
        } else {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::ERROR,
                'status_note' => $result['error_note'] ?? null,
            ]);
        }

        return $result;
    }

    protected function on_card_token_creating(array $data): ResponseInterface
    {
        return $this->client->request('POST', 'card_token/request', [
            'json' => $data,
        ]);
    }

    protected function on_card_token_created(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::CONFIRMED,
                'status_note' => $result['error_note'] ?? null,
                'card_token' => $result['card_token'] ?? null,
                'phone_number' => $result['phone_number'] ?? null,
            ]);
        } else {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::ERROR,
                'status_note' => $result['error_note'] ?? null,
            ]);
        }

        return $result;
    }

    protected function on_card_token_verifying(array $data): ResponseInterface
    {
        return $this->client->request('POST', 'card_token/verify', [
            'json' => $data,
        ]);
    }

    protected function on_card_token_verified(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::CONFIRMED,
                'status_note' => $result['error_note'] ?? null,
                'card_number' => $result['card_number'] ?? null,
            ]);
        } else {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::ERROR,
                'status_note' => $result['error_note'] ?? null,
            ]);
        }

        return $result;
    }

    protected function on_card_token_paying(array $data): ResponseInterface
    {
        return $this->client->request('POST', 'card_token/payment', [
            'json' => $data,
        ]);
    }

    protected function on_card_token_payed(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::CONFIRMED,
                'status_note' => $result['error_note'] ?? null,
                'payment_id' => $result['payment_id'] ?? null,
            ]);
        } else {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::ERROR,
                'status_note' => $result['error_note'] ?? null,
            ]);
        }

        return $result;
    }

    protected function on_card_token_deleting(array $data): ResponseInterface
    {
        $url = 'card_token/'.$data['service_id'].'/'.$data['card_token'];

        return $this->client->request('DELETE', $url);
    }

    protected function on_card_token_deleted(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $this->model->updateByToken($token, [
                'card_token' => null,
                'status_note' => $result['error_note'] ?? null,
            ]);
        } else {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::ERROR,
                'status_note' => $result['error_note'] ?? null,
            ]);
        }

        return $result;
    }

    protected function on_payment_checking(array $data): ResponseInterface
    {
        $url = 'payment/status/'.$data['service_id'].'/'.$data['payment_id'];

        return $this->client->request('GET', $url);
    }

    protected function on_payment_checked(array $request, ResponseInterface $response, string $token): ?array
    {
        return $this->on_invoice_checked($request, $response, $token);
    }

    protected function on_checking_with_merchant_trans_id(array $data): ResponseInterface
    {
        $url = 'payment/status_by_mti/'.$data['service_id'].'/'.$data['merchant_trans_id'];

        return $this->client->request('GET', $url);
    }

    protected function on_checked_with_merchant_trans_id(array $request, ResponseInterface $response, string $token): ?array
    {
        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $result = $this->decodeResponse($response);

        if ((int) ($result['error_code'] ?? -1) === 0) {
            $this->model->updateByToken($token, [
                'payment_id' => $result['payment_id'] ?? null,
                'merchant_trans_id' => $result['merchant_trans_id'] ?? null,
                'status_note' => $result['error_note'] ?? null,
            ]);
        } else {
            $this->model->updateByToken($token, [
                'status' => PaymentStatus::ERROR,
                'status_note' => $result['error_note'] ?? null,
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
