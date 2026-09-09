<?php

namespace App\Interface\UseCase\Security;

use Symfony\Component\Form\FormInterface;

interface RecoveryInterface
{
    public function handler(): FormInterface;
}