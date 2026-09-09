<?php

namespace App\Message\Statistics;

final readonly class RecordStatisticsMessage
{
    public function __construct(
        public string  $domainId,
        public ?string $ip,
        public ?string $userAgent,
        public ?string $lang,
        public ?string $page,
        public ?string $referrer,
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public ?string $sessionId,
        public ?string $country,
        public ?string $region,
        public ?string $city,
        public ?string $countryIsoCode = null,
        public ?string $userId = null,
    ) {}
}
