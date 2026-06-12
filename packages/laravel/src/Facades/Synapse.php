<?php

declare(strict_types=1);

namespace Synapse\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Synapse\Chat\ChatInterface;

/**
 * @method static \Synapse\Chat\Response send(array $messages, array $options = [])
 *
 * @see \Synapse\Chat\ChatInterface
 */
final class Synapse extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChatInterface::class;
    }
}
