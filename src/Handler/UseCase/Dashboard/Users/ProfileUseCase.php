<?php

namespace App\Handler\UseCase\Dashboard\Users;

use App\ArgumentHandler\UsersArgument;
use App\Exception\GenericException;
use App\Form\Dashboard\ProfileType;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Dashboard\Users\ProfileInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProfileUseCase implements ProfileInterface
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly RequestStack $request,
        private readonly TranslatorInterface $translator,
        private readonly LogInterface $log,
        private readonly Security $security,
        private readonly GetDomainDataInterface $getDomains,
        private readonly ParameterBagInterface $parameters,
        private readonly S3ManagerInterface $s3Manager,
        private readonly CustomeEntityManagerInterface $customeEntityManager
    ) {}

    public function handler(): FormReturn
    {
        $form = $this->formFactory->create(ProfileType::class);
        $message = null;
        $isError = false;
        $isProcess = false;
        $isServerError = false;
        $request = $this->request->getCurrentRequest();

        try {
            $form->handleRequest($request);
            if ($form->isSubmitted()) {
                $isProcess = true;
                if ($form->isValid()) {
                    $image = $request->files->get("profile", []);
                    $this->editUserProcess(
                        $request->get("profile", []),
                        reset($image)
                    );
                    $message = $this->translator->trans("registration_successful", [], 'users');
                }
            } else {
                $form = $this->assembleForm($form);
            }
        } catch (GenericException $e) {
            $log = $this->log->handler($e);
            $message = $e->getMessage();
            $isError = true;
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            $message = $this->translator->trans("registration_error_with_id_message", [], 'login') .
                ' ' . $log?->getShortReference();
            $isError = true;
            $isServerError = true;
        }

        $return = new FormReturn(
            $form,
            $message,
            $isError,
            $isProcess,
            $isServerError
        );

        return $return;
    }

    private function editUserProcess(array $data, ?UploadedFile $file = null): void
    {
        $user = $this->security->getUser();
        if (isset($data["password"]) && !empty($data["password"])) {
            if (!isset($data["confirm_password"])
            || empty($data["confirm_password"])
            || $data["password"] !== $data["confirm_password"]) {
                throw new GenericException($this->translator->trans("confirmation_does_not_match_message",[],"login"), 400);
            }
        }

        $data["profilePicture"] = $this->picture($file);
        $argument = new UsersArgument($data, $this->getDomains->getDomain());
        $user->edit($argument);
        $this->customeEntityManager->add($user, true);
    }

    private function assembleForm(FormInterface $form): FormInterface
    {
        $user = $this->security->getUser();
        $form->get('email')->setData($user->getEmail());
        $form->get('name')->setData($user->getName());
        $form->get('lastName')->setData($user->getLastName());
        $form->get('dateOfBirth')->setData($user->getDateOfBirth());
        $form->get('address')->setData($user->getAddress());
        $form->get('phone')->setData($user->getPhone());
        $form->get('prefix')->setData($user->getPrefix());

        return $form;
    }

    private function picture(?UploadedFile $file = null): ?string
    {
        if (empty($file)) {
            return $file;
        }

        $oldPicture = $this->security->getUser()->getProfilePicture(true);
        if (!empty($oldPicture)) {
            $this->s3Manager->delete($oldPicture);
        }

        $s3Image = $this->s3Manager->create($file, $this->parameters->get("upload_users"));
        if (empty($s3Image)) {
            throw new \Exception("Ocurrió un error durante la creación de una imagen", 500);
        }

        return $s3Image;
    }
}
