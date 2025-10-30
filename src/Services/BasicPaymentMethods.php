<?php

declare(strict_types=1);

namespace Click\Integration\Services;

use Click\Integration\Support\PaymentStatus;

class BasicPaymentMethods extends BasicPaymentsErrors
{
    /**
     * @param array<string, mixed>|null $request
     *
     * @return array<string, mixed>
     */
    public function prepare(?array $request = null): array
    {
        $request ??= $this->request->post();
        $payment = $this->model->findByMerchantTransId((string) $request['merchant_trans_id']);

        $merchantConfirmId = $payment['id'] ?? 0;
        $merchantPrepareId = $payment['id'] ?? 0;

        $result = $this->request_check($request);

        $response = array_merge($result, [
            'click_trans_id' => $request['click_trans_id'],
            'merchant_trans_id' => $request['merchant_trans_id'],
            'merchant_confirm_id' => $merchantConfirmId,
            'merchant_prepare_id' => $merchantPrepareId,
        ]);

        if ($response['error'] === 0 && isset($payment['id'])) {
            $this->model->updateById((int) $payment['id'], [
                'status' => PaymentStatus::WAITING,
            ]);
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $request
     *
     * @return array<string, mixed>
     */
    public function complete(?array $request = null): array
    {
        $request ??= $this->request->post();
        $payment = $this->model->findByMerchantTransId((string) $request['merchant_trans_id']);

        $merchantConfirmId = $payment['id'] ?? 0;
        $merchantPrepareId = $payment['id'] ?? 0;

        $result = $this->request_check($request);

        $response = array_merge($result, [
            'click_trans_id' => $request['click_trans_id'],
            'merchant_trans_id' => $request['merchant_trans_id'],
            'merchant_confirm_id' => $merchantConfirmId,
            'merchant_prepare_id' => $merchantPrepareId,
        ]);

        if ((int) ($request['error'] ?? 0) < 0 && ! in_array($response['error'], [-4, -9], true) && isset($payment['id'])) {
            $this->model->updateById((int) $payment['id'], [
                'status' => PaymentStatus::REJECTED,
            ]);

            return [
                'error' => -9,
                'error_note' => 'Transaction cancelled',
            ];
        }

        if ($response['error'] === 0 && isset($payment['id'])) {
            $this->model->updateById((int) $payment['id'], [
                'status' => PaymentStatus::CONFIRMED,
            ]);
        }

        return $response;
    }
}
