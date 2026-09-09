<?php

namespace App\Interface\UseCase\Store\Profile;

interface EditUserProfileInterface
{
    public function handler(array $data): array;
}
