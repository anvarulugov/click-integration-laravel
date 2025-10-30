<?php

declare(strict_types=1);

namespace Click\Integration\Repositories;

use Click\Integration\Support\Configs;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

class PaymentRepository
{
    private ConnectionInterface $connection;

    private string $table;

    public function __construct(DatabaseManager $databaseManager, Configs $configs)
    {
        $connectionName = $configs->getDatabaseConnectionName();
        $this->connection = $connectionName
            ? $databaseManager->connection($connectionName)
            : $databaseManager->connection();

        $this->table = $configs->getDatabaseTable();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $paymentId): ?array
    {
        $record = $this->connection->table($this->table)->where('id', $paymentId)->first();

        return $record ? (array) $record : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array
    {
        $record = $this->connection->table($this->table)->where('token', $token)->first();

        return $record ? (array) $record : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByMerchantTransId(string $merchantTransId): ?array
    {
        $record = $this->connection
            ->table($this->table)
            ->where('merchant_trans_id', $merchantTransId)
            ->first();

        return $record ? (array) $record : null;
    }

    public function updateById(int $paymentId, array $attributes): int
    {
        return $this->performUpdate(['id' => $paymentId], $attributes);
    }

    public function updateByToken(string $token, array $attributes): int
    {
        return $this->performUpdate(['token' => $token], $attributes);
    }

    private function performUpdate(array $conditions, array $attributes): int
    {
        if (empty($attributes)) {
            return 0;
        }

        $attributes['modified'] = date('Y-m-d H:i:s');

        return $this->connection
            ->table($this->table)
            ->where($conditions)
            ->update($attributes);
    }
}
