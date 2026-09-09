<?php

namespace App\Interface\Configuration;

interface MailerInterface
{
    public function sendEmailGeneric(
        string $subject,
        string $to,
        string $template,
        array $data,
        ?array $repliesTo = [],
        ?array $bccs = []
    ): void;

}