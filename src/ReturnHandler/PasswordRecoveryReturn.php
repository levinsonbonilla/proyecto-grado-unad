<?php

namespace App\ReturnHandler;

use App\Entity\Users\Users;
use Symfony\Component\Form\FormInterface;

final class PasswordRecoveryReturn
{
    public function __construct(
        private readonly FormInterface $form,
        private readonly ?Users $user = null,
        private readonly ?string $message = null,
        private readonly ?bool $isError = false,
        private readonly bool $isServerError = false,
    ) {
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function isError(): ?bool
    {
        return $this->isError;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function isServerError(): bool
    {
        return $this->isServerError;
    }

    public function getFlashType(): string
    {
        if (!$this->isError) {
            return 'success';
        }

        return $this->isServerError ? 'error' : 'warning';
    }
}
