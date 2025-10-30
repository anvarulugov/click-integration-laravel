<?php

declare(strict_types=1);

namespace Click\Integration\Facades;

use Illuminate\Support\Facades\Facade;

class ClickIntegration extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'click.integration';
    }
}
