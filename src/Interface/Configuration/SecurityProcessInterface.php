<?php

namespace App\Interface\Configuration;

use Symfony\Component\HttpFoundation\RedirectResponse;

interface SecurityProcessInterface
{
    public function redirectLogin(): ?RedirectResponse;

    public function RedirectLogout(): RedirectResponse;
}