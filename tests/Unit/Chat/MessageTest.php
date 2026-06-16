<?php

declare(strict_types=1);

namespace PHPAI\Tests\Unit\Chat;

use PHPUnit\Framework\TestCase;
use PHPAI\Chat\Message;
use PHPAI\Chat\Role;

final class MessageTest extends TestCase
{
    public function testFactoryMethods(): void
    {
        $system = Message::system('sys');
        $user = Message::user('hi');
        $assistant = Message::assistant('hello');
        $tool = Message::tool('result', 'call-123');

        $this->assertSame(Role::System, $system->role);
        $this->assertSame(Role::User, $user->role);
        $this->assertSame(Role::Assistant, $assistant->role);
        $this->assertSame(Role::Tool, $tool->role);
        $this->assertSame('call-123', $tool->toolCallId);
    }
}
