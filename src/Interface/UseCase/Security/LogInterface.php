<?php

namespace App\Interface\UseCase\Security;

use App\Entity\Configurations\Globals\Logs;
use Throwable;

interface LogInterface
{
    public function handler(Throwable $throwable): ?Logs;

    public function deleteLogsFile(string $days): void;
}
