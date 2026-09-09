<?php

namespace App\Controller\Store;

use App\Entity\Users\Users;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Message\Statistics\RecordStatisticsEventMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/api/track', name: 'store_track')]
class TrackingController extends AbstractController
{
    #[Route('/event', name: '_event', methods: ['POST'])]
    public function event(
        Request $request,
        GetDomainDataInterface $getDomainData,
        MessageBusInterface $bus,
        Security $security,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? [];
        $eventName = (string) ($data['eventName'] ?? '');

        if ($eventName === '' || strlen($eventName) > 100) {
            return $this->json(['success' => false], 400);
        }

        $user = $security->getUser();
        $metadata = $data['metadata'] ?? null;

        $bus->dispatch(new RecordStatisticsEventMessage(
            domainId: (string) $getDomainData->getDomainCache()->getId(),
            eventName: $eventName,
            eventTarget: isset($data['eventTarget']) ? (string) $data['eventTarget'] : null,
            page: isset($data['page']) ? (string) $data['page'] : $request->headers->get('referer'),
            metadata: is_array($metadata) ? $metadata : null,
            sessionId: $request->hasSession() ? $request->getSession()->getId() : null,
            userId: $user instanceof Users ? (string) $user->getId() : null,
        ));

        return $this->json(['success' => true]);
    }
}
