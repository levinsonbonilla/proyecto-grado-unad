<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessageImages;
use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Messages\MessageNotificationInterface;
use App\Interface\UseCase\Messages\QuickComposeMessageInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class QuickComposeMessageUseCase implements QuickComposeMessageInterface
{
    public function __construct(
        private readonly RequestStack $request,
        private readonly Security $security,
        private readonly UsersRepository $usersRepository,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly S3ManagerInterface $s3Manager,
        private readonly ParameterBagInterface $parameters,
        private readonly LogInterface $log,
        private readonly TranslatorInterface $translator,
        private readonly MessageNotificationInterface $messageNotification,
    ) {
    }

    public function handler(): array
    {
        $uploadedS3Images = [];
        try {
            $request     = $this->request->getCurrentRequest();
            $messageText = trim($request->request->get('message', ''));

            if (empty($messageText)) {
                return ['success' => false, 'message' => 'El mensaje no puede estar vacío'];
            }

            $currentUser  = $this->security->getUser();
            $domain       = $this->getDomainData->getDomainCache();
            $isSuperAdmin = $this->security->isGranted('ROLE_SUPER_ADMIN');

            $recipients = $this->resolveRecipients($request, $isSuperAdmin, $domain);
            if (empty($recipients)) {
                return ['success' => false, 'message' => 'No se pudo determinar el destinatario'];
            }

            $imageUrls = [];
            foreach ((array) $request->files->get('images', []) as $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }
                $url = $this->s3Manager->create($file, $this->parameters->get('upload_messages'));
                if (empty($url)) {
                    throw new \Exception('Error al subir imagen');
                }
                $uploadedS3Images[] = $url;
                $imageUrls[]        = $url;
            }

            foreach ($recipients as $toUser) {
                if (!$toUser) {
                    continue;
                }
                $helpMessage = new HelpMessages();
                $helpMessage->add($toUser, $currentUser, $messageText);
                $this->customeEntityManager->add($helpMessage, false);

                foreach ($imageUrls as $url) {
                    $img = new HelpMessageImages();
                    $img->add($helpMessage, $url);
                    $this->customeEntityManager->add($img, false);
                }
            }

            $this->customeEntityManager->flush();

            foreach ($recipients as $toUser) {
                if ($toUser) {
                    $this->messageNotification->notify($toUser, $currentUser, $messageText);
                }
            }

            return ['success' => true, 'message' => $this->translator->trans('created_successfully', [], 'messages')];

        } catch (\Throwable $th) {
            foreach ($uploadedS3Images as $url) {
                $this->s3Manager->delete($url);
            }
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al enviar el mensaje'];
        }
    }

    private function resolveRecipients(Request $request, bool $isSuperAdmin, $domain): array
    {
        if ($isSuperAdmin) {
            $toUserIds = $request->request->all('toUsers');
            if (empty($toUserIds)) {
                return [];
            }
            return array_filter(array_map(
                fn($id) => $this->usersRepository->find($id),
                $toUserIds
            ));
        }

        $superAdmins = $this->usersRepository->findSuperAdminsByDomain($domain);
        if (empty($superAdmins)) {
            return [];
        }

        if (count($superAdmins) === 1) {
            return $superAdmins;
        }

        $toUserId = $request->request->get('toUser');
        if (empty($toUserId)) {
            return [];
        }

        return array_filter([$this->usersRepository->find($toUserId)]);
    }
}
