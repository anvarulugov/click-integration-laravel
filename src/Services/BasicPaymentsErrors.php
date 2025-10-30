<?php

declare(strict_types=1);

namespace Click\Integration\Services;

use Click\Integration\Http\ClickRequest;
use Click\Integration\Repositories\PaymentRepository;
use Click\Integration\Support\Helper;
use Click\Integration\Support\PaymentStatus;

class BasicPaymentsErrors
{
    protected array $provider = [];

    public function __construct(
        protected PaymentRepository $model,
        protected Helper $helper,
        protected ClickRequest $request
    ) {
    }

    public function setProvider(array $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    protected function request_check(array $request): array
    {
        if ($this->is_not_possible_data($request)) {
            return [
                'error' => -8,
                'error_note' => 'Error in request from click',
            ];
        }

        $secretKey = $this->provider['secret_key'] ?? '';
        $signString = md5(
            $request['click_trans_id'] .
            $request['service_id'] .
            $secretKey .
            $request['merchant_trans_id'] .
            ((int) $request['action'] === 1 ? ($request['merchant_prepare_id'] ?? '') : '') .
            $request['amount'] .
            $request['action'] .
            $request['sign_time']
        );

        if ($signString !== $request['sign_string']) {
            return [
                'error' => -1,
                'error_note' => 'SIGN CHECK FAILED!',
            ];
        }

        if (! in_array((int) $request['action'], [0, 1], true)) {
            return [
                'error' => -3,
                'error_note' => 'Action not found',
            ];
        }

        $payment = $this->model->findByMerchantTransId((string) $request['merchant_trans_id']);
        if ($payment === null) {
            return [
                'error' => -5,
                'error_note' => 'User does not exist',
            ];
        }

        if ((int) $request['action'] === 1) {
            $prepareId = (int) ($request['merchant_prepare_id'] ?? 0);
            $payment = $this->model->findById($prepareId);

            if ($payment === null) {
                return [
                    'error' => -6,
                    'error_note' => 'Transaction does not exist',
                ];
            }
        }

        if (($payment['status'] ?? null) === PaymentStatus::CONFIRMED) {
            return [
                'error' => -4,
                'error_note' => 'Already paid',
            ];
        }

        $amount = (float) ($request['amount'] ?? 0);
        $expectedAmount = isset($payment['total']) ? (float) $payment['total'] : 0.0;
        if (abs($expectedAmount - $amount) > 0.01) {
            return [
                'error' => -2,
                'error_note' => 'Incorrect parameter amount',
            ];
        }

        if (($payment['status'] ?? null) === PaymentStatus::REJECTED) {
            return [
                'error' => -9,
                'error_note' => 'Transaction cancelled',
            ];
        }

        return [
            'error' => 0,
            'error_note' => 'Success',
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    private function is_not_possible_data(array $request): bool
    {
        $required = [
            'click_trans_id',
            'service_id',
            'merchant_trans_id',
            'amount',
            'action',
            'error',
            'error_note',
            'sign_time',
            'sign_string',
            'click_paydoc_id',
        ];

        foreach ($required as $key) {
            if (! isset($request[$key])) {
                return true;
            }
        }

        if ((int) $request['action'] === 1 && ! isset($request['merchant_prepare_id'])) {
            return true;
        }

        return false;
    }
}
