<?php

namespace App\Controller\Messages;

use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Form\Messages\ComposeType;
use App\Form\Messages\ReplyType;
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/messages', name: 'dashboard_messages')]
class MessagesController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function inbox(HelpMessagesRepository $helpMessagesRepository): Response
    {

        $user    = $this->getUser();
        $senders = $helpMessagesRepository->getSenders($user);
        return $this->render('dashboard/messages/inbox.html.twig', ['senders' => $senders]);
    }

    #[Route('/sent', name: '_sent', methods: ['GET'])]
    public function sent(): Response
    {
        return $this->render('dashboard/messages/sent.html.twig');
    }

    #[Route('/inbox/list', name: '_inbox_list', methods: ['POST', 'GET'])]
    public function inboxList(InboxMessagesInterface $inbox): Response
    {
        return $this->json($inbox->handler());
    }

    #[Route('/sent/list', name: '_sent_list', methods: ['POST', 'GET'])]
    public function sentList(SentMessagesInterface $sent): Response
    {
        return $this->json($sent->handler());
    }

    #[Route('/compose', name: '_compose', methods: ['GET', 'POST'])]
    public function compose(ComposeMessageInterface $compose): Response
    {
        $process = $compose->handler(ComposeType::class);
        if ($process->isProcess()) {
            $this->addFlash($process->getFlashType(), $process->getMessage());
        }
        return $this->render('dashboard/messages/compose.html.twig', [
            'form' => $process->getForm(),
        ]);
    }

    #[Route('/quick-compose', name: '_quick_compose', methods: ['POST'])]
    public function quickCompose(QuickComposeMessageInterface $quickCompose): Response
    {
        return $this->json($quickCompose->handler());
    }

    #[Route('/unread-count', name: '_unread_count', methods: ['GET'])]
    public function unreadCount(UnreadCountInterface $unreadCount): Response
    {
        return $this->json($unreadCount->handler());
    }

    #[Route('/available-users', name: '_available_users', methods: ['GET'])]
    public function availableUsers(AvailableUsersInterface $availableUsers): Response
    {
        return $this->json($availableUsers->handler());
    }

    #[Route('/{id}', name: '_view', methods: ['GET'])]
    public function view(HelpMessages $message, ViewThreadInterface $viewThread, ReplyMessageInterface $reply, ParameterBagInterface $params): Response
    {
        $thread       = $viewThread->handler($message);
        $replyProcess = $reply->handler($message, ReplyType::class);
        return $this->render('dashboard/messages/view.html.twig', [
            'thread'      => $thread,
            'rootMessage' => $message,
            'replyForm'   => $replyProcess->getForm(),
            'cdnBaseUrl'  => $params->get('cdn_base_url'),
        ]);
    }

    #[Route('/{id}/reply', name: '_reply', methods: ['POST'])]
    public function reply(HelpMessages $message, ReplyMessageInterface $reply): Response
    {
        $process = $reply->handler($message, ReplyType::class);
        if ($process->isProcess()) {
            $this->addFlash($process->getFlashType(), $process->getMessage());
        }
        return $this->redirectToRoute('dashboard_messages_view', ['id' => $message->getId()]);
    }

    #[Route('/{id}/mark-read', name: '_mark_read', methods: ['POST'])]
    public function markRead(HelpMessages $message, MarkReadMessageInterface $markRead): Response
    {
        return $this->json($markRead->handler($message));
    }
}
