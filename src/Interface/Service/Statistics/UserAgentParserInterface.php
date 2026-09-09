<?php

namespace App\Interface\Service\Statistics;

use App\Service\Statistics\UserAgentData;

interface UserAgentParserInterface
{
    public function parse(string $userAgent): UserAgentData;
}
