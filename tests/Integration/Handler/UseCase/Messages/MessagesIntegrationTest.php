<?php

namespace App\Tests\Integration\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessageImages;
use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Handler\UseCase\Messages\AvailableUsersUseCase;
use App\Handler\UseCase\Messages\ComposeMessageUseCase;
use App\Handler\UseCase\Messages\InboxMessagesUseCase;
use App\Handler\UseCase\Messages\MarkReadMessageUseCase;
use App\Handler\UseCase\Messages\QuickComposeMessageUseCase;
use App\Handler\UseCase\Messages\ReplyMessageUseCase;
use App\Handler\UseCase\Messages\SentMessagesUseCase;
use App\Handler\UseCase\Messages\UnreadCountUseCase;
use App\Handler\UseCase\Messages\ViewThreadUseCase;
use App\Interface\UseCase\Messages\AvailableUsersInterface;
use App\Interface\UseCase\Messages\ComposeMessageInterface;
use App\Interface\UseCase\Messages\InboxMessagesInterface;
use App\Interface\UseCase\Messages\MarkReadMessageInterface;
use App\Interface\UseCase\Messages\QuickComposeMessageInterface;
use App\Interface\UseCase\Messages\ReplyMessageInterface;
use App\Interface\UseCase\Messages\SentMessagesInterface;
use App\Interface\UseCase\Messages\UnreadCountInterface;
use App\Interface\UseCase\Messages\ViewThreadInterface;
use App\Repository\Users\HelpMessagesRepository;
use App\Tests\Integration\IntegrationTestCase;

class MessagesIntegrationTest extends IntegrationTestCase
{

    public function testAllUseCasesAreRegisteredInContainer(): void
    {
        $this->assertInstanceOf(ComposeMessageUseCase::class,      static::getContainer()->get(ComposeMessageInterface::class));
        $this->assertInstanceOf(ReplyMessageUseCase::class,        static::getContainer()->get(ReplyMessageInterface::class));
        $this->assertInstanceOf(InboxMessagesUseCase::class,       static::getContainer()->get(InboxMessagesInterface::class));
        $this->assertInstanceOf(SentMessagesUseCase::class,        static::getContainer()->get(SentMessagesInterface::class));
        $this->assertInstanceOf(ViewThreadUseCase::class,          static::getContainer()->get(ViewThreadInterface::class));
        $this->assertInstanceOf(MarkReadMessageUseCase::class,     static::getContainer()->get(MarkReadMessageInterface::class));
        $this->assertInstanceOf(QuickComposeMessageUseCase::class, static::getContainer()->get(QuickComposeMessageInterface::class));
        $this->assertInstanceOf(UnreadCountUseCase::class,         static::getContainer()->get(UnreadCountInterface::class));
        $this->assertInstanceOf(AvailableUsersUseCase::class,      static::getContainer()->get(AvailableUsersInterface::class));
    }

    public function testFixtureUsersExistInDatabase(): void
    {
        $users = $this->em->getRepository(Users::class)->findAll();
        $this->assertGreaterThanOrEqual(2, count($users), 'Deben existir al menos 2 usuarios en el fixture');

        $superAdmin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);
        $admin      = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);

        $this->assertNotNull($superAdmin, 'Debe existir el usuario superadmin del fixture');
        $this->assertNotNull($admin, 'Debe existir el usuario admin del fixture');
    }

    public function testFixtureMessagesExistInDatabase(): void
    {
        $messages = $this->em->getRepository(HelpMessages::class)->findAll();
        $this->assertGreaterThanOrEqual(3, count($messages), 'Deben existir al menos 3 mensajes en el fixture');
    }

    public function testFixtureRootMessageExistsAndIsUnread(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $this->assertNotNull($admin);

        $unreadMessages = $this->em->getRepository(HelpMessages::class)->findBy([
            'toUser' => $admin,
            'isRead' => false,
        ]);

        $this->assertCount(1, $unreadMessages, 'El admin debe tener exactamente 1 mensaje no leído');
    }

    public function testFixtureReplyIsLinkedToParent(): void
    {
        $replies = array_filter(
            $this->em->getRepository(HelpMessages::class)->findAll(),
            fn(HelpMessages $m) => $m->isReply()
        );

        $this->assertNotEmpty($replies, 'Debe existir al menos una respuesta (reply) en el fixture');

        $reply = reset($replies);
        $this->assertNotNull($reply->getParentMessage());
    }

    public function testFixtureImageAttachedToSentMessage(): void
    {
        $images = $this->em->getRepository(HelpMessageImages::class)->findAll();
        $this->assertCount(1, $images, 'Debe existir exactamente 1 imagen adjunta en el fixture');
        $this->assertSame('uploads/messages/test-screenshot.png', $images[0]->getImageUrl());
        $this->assertTrue($images[0]->isActive());
    }

    public function testCountUnreadReturnsCorrectCountForAdmin(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);

        $repo = $this->em->getRepository(HelpMessages::class);

        $count = $repo->countUnread($admin);

        $this->assertSame(1, $count, 'El admin debe tener 1 mensaje no leído');
    }

    public function testCountUnreadReturnsZeroForSuperAdminInbox(): void
    {
        $superAdmin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'levinson.bonilla22@gmail.com']);

        $repo = $this->em->getRepository(HelpMessages::class);

        $count = $repo->countUnread($superAdmin);

        $this->assertSame(0, $count, 'El superAdmin no debe tener mensajes no leídos (el suyo llegó leído)');
    }

    public function testGetThreadReturnsRootAndReply(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $rootMessage = $this->em->getRepository(HelpMessages::class)->findOneBy([
            'toUser'        => $admin,
            'parentMessage' => null,
        ]);

        $this->assertNotNull($rootMessage, 'Debe existir el mensaje raíz del fixture');

        $repo   = $this->em->getRepository(HelpMessages::class);
        $thread = $repo->getThread($rootMessage);

        $this->assertCount(2, $thread, 'El hilo debe contener el mensaje raíz y su respuesta');
    }

    public function testMarkReadUseCaseMarksUnreadMessageAsRead(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);
        $message = $this->em->getRepository(HelpMessages::class)->findOneBy([
            'toUser' => $admin,
            'isRead' => false,
        ]);

        $this->assertNotNull($message, 'Precondición: debe existir un mensaje no leído');
        $this->assertFalse($message->isRead());

        $service = static::getContainer()->get(MarkReadMessageInterface::class);
        $result  = $service->handler($message);

        $this->assertTrue($result['success']);
        $this->em->refresh($message);
        $this->assertTrue($message->isRead(), 'El mensaje debe quedar marcado como leído');
    }

    public function testGetInboxReturnsOnlyRootMessagesForAdmin(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);

        $repo  = $this->em->getRepository(HelpMessages::class);
        $items = $repo->getInbox($admin, false);

        $this->assertCount(1, $items, 'La bandeja del admin debe tener 1 mensaje raíz (no replies)');
    }

    public function testGetSentReturnsOnlyRootMessagesForAdmin(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);

        $repo  = $this->em->getRepository(HelpMessages::class);
        $items = $repo->getSent($admin, false);

        $this->assertCount(1, $items, 'Los enviados del admin deben tener 1 mensaje raíz');
    }

    public function testGetInboxCountMatchesItems(): void
    {
        $admin = $this->em->getRepository(Users::class)->findOneBy(['email' => 'admin.test@proyecto-grado.test']);

        $repo  = $this->em->getRepository(HelpMessages::class);
        $count = $repo->getInbox($admin, true);
        $items = $repo->getInbox($admin, false);

        $this->assertSame((int) $count['total'], count($items));
    }
}
