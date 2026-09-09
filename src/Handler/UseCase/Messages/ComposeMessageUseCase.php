<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessageImages;
use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Messages\ComposeMessageInterface;
use App\Interface\UseCase\Messages\MessageNotificationInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ComposeMessageUseCase implements ComposeMessageInterface
{
    public function __construct(
        private readonly RequestStack $request,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly LogInterface $log,
        private readonly Security $security,
        private readonly UsersRepository $usersRepository,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly S3ManagerInterface $s3Manager,
        private readonly ParameterBagInterface $parameters,
        private readonly MessageNotificationInterface $messageNotification,
    ) {
    }

    public function handler(string $type): FormReturn
    {

        $currentUser  = $this->security->getUser();
        $domain       = $this->getDomainData->getDomainCache();
        $isSuperAdmin = $this->security->isGranted('ROLE_SUPER_ADMIN');

        $availableUsers = $isSuperAdmin
            ? $this->usersRepository->findActiveByDomain($domain, $currentUser)
            : $this->usersRepository->findSuperAdminsByDomain($domain);

        $form = $this->formFactory->create($type, null, [
            'is_super_admin'  => $isSuperAdmin,
            'available_users' => $availableUsers,
        ]);

        $message = null;
        $isError = false;
        $isProcess = false;
        $isServerError = false;
        $uploadedS3Images = [];

        try {
            $request = $this->request->getCurrentRequest();
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $isProcess = true;
                if ($form->isValid()) {
                    $data       = $request->get($form->getName(), []);
                    $files      = $request->files->get($form->getName(), []);
                    $imageFiles = isset($files['images']) ? (array) $files['images'] : [];

                    $recipients = $this->resolveRecipients($isSuperAdmin, $data, $availableUsers);

                    $imageUrls = [];
                    foreach ($imageFiles as $file) {
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
                        $helpMessage->add($toUser, $currentUser, $data['message'], $data['subject'] ?? null);
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
                            $this->messageNotification->notify($toUser, $currentUser, $data['message']);
                        }
                    }

                    $message = $this->translator->trans('created_successfully', [], 'messages');

                    $form = $this->formFactory->create($type, null, [
                        'is_super_admin'  => $isSuperAdmin,
                        'available_users' => $availableUsers,
                    ]);
                }
            }
        } catch (GenericException $e) {
            foreach ($uploadedS3Images as $url) {
                $this->s3Manager->delete($url);
            }
            $this->log->handler($e);
            $message = $e->getMessage();
            $isError = true;
        } catch (\Throwable $th) {
            foreach ($uploadedS3Images as $url) {
                $this->s3Manager->delete($url);
            }
            $log     = $this->log->handler($th);
            $message = $this->translator->trans('registration_error_with_id_message', [], 'login') . ' ' . $log?->getShortReference();
            $isError = true;
            $isServerError = true;
        }

        return new FormReturn($form, $message, $isError, $isProcess, $isServerError);
    }

    private function resolveRecipients(bool $isSuperAdmin, array $data, array $availableUsers): array
    {
        if ($isSuperAdmin) {
            $toUserIds = (array) ($data['toUsers'] ?? []);
            if (empty($toUserIds)) {
                throw new GenericException('Selecciona al menos un destinatario', 400);
            }
            return array_filter(array_map(
                fn($id) => $this->usersRepository->find($id),
                $toUserIds
            ));
        }

        if (empty($availableUsers)) {
            throw new GenericException('No hay superadministradores disponibles', 400);
        }

        if (count($availableUsers) === 1) {
            return $availableUsers;
        }

        $toUserId = $data['toUser'] ?? null;
        if (empty($toUserId)) {
            throw new GenericException('Selecciona un destinatario', 400);
        }

        return array_filter([$this->usersRepository->find($toUserId)]);
    }
}
