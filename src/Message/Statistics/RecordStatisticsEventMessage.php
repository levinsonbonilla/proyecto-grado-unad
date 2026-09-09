<?php

namespace App\Message\Statistics;

final readonly class RecordStatisticsEventMessage
{
    public function __construct(
        public string  $domainId,
        public string  $eventName,
        public ?string $eventTarget,
        public ?string $page,
        public ?array  $metadata,
        public ?string $sessionId,
        public ?string $userId = null,
    ) {}
}
