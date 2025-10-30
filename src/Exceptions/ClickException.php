<?php

declare(strict_types=1);

namespace Click\Integration\Exceptions;

use Exception;

class ClickException extends Exception
{
    public const ERROR_INTERNAL_SYSTEM = -32400;

    public const ERROR_INSUFFICIENT_PRIVILEGE = -32504;

    public const ERROR_INVALID_JSON_RPC_OBJECT = -32600;

    public const ERROR_METHOD_NOT_FOUND = -32601;

    public const ERROR_INVALID_AMOUNT = -31001;

    public const ERROR_TRANSACTION_NOT_FOUND = -31003;

    public const ERROR_INVALID_ACCOUNT = -31050;

    public const ERROR_COULD_NOT_CANCEL = -31007;

    public const ERROR_COULD_NOT_PERFORM = -31008;

    /**
     * @var array<string, mixed>
     */
    private array $error;

    public function __construct(string $errorNote, int $errorCode)
    {
        parent::__construct($errorNote, $errorCode);

        $this->error = [
            'error_code' => $errorCode,
        ];

        if ($errorNote !== '') {
            $this->error['error_note'] = $errorNote;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function error(): array
    {
        return $this->error;
    }
}
