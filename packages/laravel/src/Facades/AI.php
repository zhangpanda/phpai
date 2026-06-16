<?php

declare(strict_types=1);

namespace PHPAI\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PHPAI\Chat\ChatInterface;

/**
 * @method static \PHPAI\Chat\Response send(array $messages, array $options = [])
 *
 * @see \PHPAI\Chat\ChatInterface
 */
final class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChatInterface::class;
    }
}
