<?php

namespace App\Tests\Unit\Entity\Users;

use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use PHPUnit\Framework\TestCase;

class HelpMessagesTest extends TestCase
{
    public function testAddWithSubjectStoresIt(): void
    {
        $toUser   = $this->createMock(Users::class);
        $fromUser = $this->createMock(Users::class);

        $message = (new HelpMessages())->add($toUser, $fromUser, 'Hola', 'Consulta sobre mi pedido');

        $this->assertSame('Consulta sobre mi pedido', $message->getSubject());
    }

    public function testAddWithoutSubjectLeavesItNull(): void
    {
        $toUser   = $this->createMock(Users::class);
        $fromUser = $this->createMock(Users::class);

        $message = (new HelpMessages())->add($toUser, $fromUser, 'Hola');

        $this->assertNull($message->getSubject());
    }

    public function testAddWithBlankSubjectIsTreatedAsNull(): void
    {
        $toUser   = $this->createMock(Users::class);
        $fromUser = $this->createMock(Users::class);

        $message = (new HelpMessages())->add($toUser, $fromUser, 'Hola', '');

        $this->assertNull($message->getSubject());
    }

    public function testReplyInheritsSubjectFromParentThread(): void
    {
        $user1 = $this->createMock(Users::class);
        $user2 = $this->createMock(Users::class);

        $root = (new HelpMessages())->add($user1, $user2, 'Mensaje original', 'Problema con mi pedido');

        $reply = (new HelpMessages())->addReply($user2, $user1, 'Aquí va mi respuesta', $root);

        $this->assertTrue($reply->isReply());
        $this->assertSame('Problema con mi pedido', $reply->getSubject());
    }

    public function testReplyToAReplyStillResolvesRootSubject(): void
    {
        $user1 = $this->createMock(Users::class);
        $user2 = $this->createMock(Users::class);

        $root       = (new HelpMessages())->add($user1, $user2, 'Mensaje original', 'Asunto raíz');
        $firstReply = (new HelpMessages())->addReply($user2, $user1, 'Primera respuesta', $root);
        $secondReply = (new HelpMessages())->addReply($user1, $user2, 'Segunda respuesta', $firstReply);

        $this->assertSame('Asunto raíz', $secondReply->getSubject());
    }
}
