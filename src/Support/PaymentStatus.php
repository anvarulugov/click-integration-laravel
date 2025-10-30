<?php

declare(strict_types=1);

namespace Click\Integration\Support;

class PaymentStatus
{
    public const INPUT = 'input';
    public const WAITING = 'waiting';
    public const PREAUTH = 'preauth';
    public const CONFIRMED = 'confirmed';
    public const REJECTED = 'rejected';
    public const REFUNDED = 'refunded';
    public const ERROR = 'error';
}
