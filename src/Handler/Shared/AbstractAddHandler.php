<?php

namespace App\Handler\Shared;

use App\Exception\GenericException;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractAddHandler
{
    protected ?string $s3Image = null;

    public function __construct(
        protected readonly RequestStack $request,
        protected readonly FormFactoryInterface $formFactory,
        protected readonly LogInterface $log,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    protected function process(string $type, ?array $additionalData = null): FormReturn
    {
        $form = $this->formFactory->create($type);
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
                    $images = $request->files->get($form->getName(), []);
                    $additionalData = array_merge($additionalData ?? [], [
                        'image' => reset($images)
                    ]);
                    $this->add(
                        $request->get($form->getName(), []),
                        $additionalData
                    );
                    $message = $this->successMessage();
                    $form = $this->formFactory->create($type);
                }
            }
        } catch (GenericException $e) {
            $log = $this->log->handler($e);
            $message = $e->getMessage();
            $isError = true;
            $this->rollbackOnError();
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            $message = $this->translator->trans("registration_error_with_id_message", [], 'login') .
                ' ' . $log?->getShortReference();
            $isError = true;
            $isServerError = true;
            $this->rollbackOnError();
        }

        return new FormReturn($form, $message, $isError, $isProcess, $isServerError);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans('created_successfully', [], 'messages');
    }

    protected function rollbackOnError(): void {}

    abstract protected function add(array $data, array $additionalData): void;
}
