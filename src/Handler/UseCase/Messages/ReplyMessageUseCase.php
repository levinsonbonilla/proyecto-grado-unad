<?php

namespace App\Handler\UseCase\Messages;

use App\Entity\Users\HelpMessageImages;
use App\Entity\Users\HelpMessages;
use App\Entity\Users\Users;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Messages\ReplyMessageInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ReplyMessageUseCase extends AbstractAddHandler implements ReplyMessageInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly Security $security,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly S3ManagerInterface $s3Manager,
        private readonly ParameterBagInterface $parameters,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(HelpMessages $parent, string $type): FormReturn
    {
        return $this->process($type, ['parent' => $parent]);
    }

    protected function add(array $data, array $additionalData): void
    {

        $currentUser = $this->security->getUser();
        $parent      = $additionalData['parent'];

        $toUser = ((string) $parent->getFromUser()->getId() === (string) $currentUser->getId())
            ? $parent->getToUser()
            : $parent->getFromUser();

        $reply = new HelpMessages();
        $reply->addReply($toUser, $currentUser, $data['message'], $parent);
        $this->customeEntityManager->add($reply, false);

        $files = $this->request->getCurrentRequest()->files->get('reply', []);
        foreach ((array) ($files['images'] ?? []) as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $url = $this->s3Manager->create($file, $this->parameters->get('upload_messages'));
            if (!empty($url)) {
                $img = new HelpMessageImages();
                $img->add($reply, $url);
                $this->customeEntityManager->add($img, false);
            }
        }

        $this->customeEntityManager->flush();
    }
}
