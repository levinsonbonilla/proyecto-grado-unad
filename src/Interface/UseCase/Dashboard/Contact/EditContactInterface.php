<?php

namespace App\Interface\UseCase\Dashboard\Contact;

use App\Entity\Tenants\Domains\Domains;
use App\ReturnHandler\FormReturn;

interface EditContactInterface
{
    public function handler(Domains $domain): FormReturn;
}
