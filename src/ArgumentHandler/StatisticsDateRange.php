<?php

namespace App\ArgumentHandler;

final readonly class StatisticsDateRange
{
    public \DateTimeImmutable $from;
    public \DateTimeImmutable $to;

    public function __construct(
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to   = null,
    ) {
        $this->to   = $to   ?? new \DateTimeImmutable('today 23:59:59');
        $this->from = $from ?? new \DateTimeImmutable('-30 days 00:00:00');
    }

    public static function fromRequest(array $query): self
    {
        try {
            $from = isset($query['from']) ? new \DateTimeImmutable($query['from'] . ' 00:00:00') : null;
            $to   = isset($query['to'])   ? new \DateTimeImmutable($query['to']   . ' 23:59:59') : null;
        } catch (\Throwable) {
            $from = null;
            $to   = null;
        }
        return new self($from, $to);
    }
}
