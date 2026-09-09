<?php

namespace App\Service\Statistics;

final readonly class UserAgentData
{
    public function __construct(
        public string $device  = 'unknown',
        public string $browser = 'unknown',
        public string $os      = 'unknown',
    ) {}
}
