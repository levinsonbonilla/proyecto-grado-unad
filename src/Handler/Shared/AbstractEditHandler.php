<?php

namespace App\Handler\Shared;

use App\Exception\GenericException;
use App\Interface\UseCase\Security\LogInterface;
use App\ReturnHandler\FormReturn;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractEditHandler
{
    public function __construct(
        protected readonly RequestStack $request,
        protected readonly FormFactoryInterface $formFactory,
        protected readonly LogInterface $log,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    protected function process(object $entity): FormReturn
    {
        $form = $this->formFactory->create($this->formType());
        $form = $this->complementForm($form);
        $message = null;
        $isError = false;
        $isProcess = false;
        $isServerError = false;

        try {
            $form->handleRequest($this->request->getCurrentRequest());

            if ($form->isSubmitted()) {
                $isProcess = true;
                if ($form->isValid()) {
                    $data = $this->request->getCurrentRequest()->get($this->formName(), []);
                    $this->edit($data, $entity);
                    $message = $this->successMessage();
                }
            } else {
                $form = $this->assembleForm($form, $entity);
            }
        } catch (GenericException $e) {
            $log = $this->log->handler($e);
            $message = $e->getMessage();
            $isError = true;
        } catch (\Throwable $th) {
            $log = $this->log->handler($th);
            $message = $this->translator->trans('edit_error', [], 'users') .
                ' ' . $log?->getShortReference();
            $isError = true;
            $isServerError = true;
        }

        return new FormReturn($form, $message, $isError, $isProcess, $isServerError);
    }

    protected function complementForm(FormInterface $form): FormInterface
    {
        return $form;
    }

    protected function successMessage(): string
    {
        return $this->translator->trans('updated_successfully', [], 'messages');
    }

    abstract protected function formType(): string;

    abstract protected function formName(): string;

    abstract protected function edit(array $data, object $entity): void;

    abstract protected function assembleForm(FormInterface $form, object $entity): FormInterface;
}
