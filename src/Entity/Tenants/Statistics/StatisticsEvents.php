<?php

namespace App\Entity\Tenants\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Repository\Tenants\Statistics\StatisticsEventsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Table(name: 'statistics_events')]
#[ORM\Index(columns: ['domain_id', 'created_at'], name: 'idx_stats_events_domain_date')]
#[ORM\Index(columns: ['domain_id', 'event_name'], name: 'idx_stats_events_domain_name')]
#[ORM\Index(columns: ['session_id'], name: 'idx_stats_events_session')]
#[ORM\Entity(repositoryClass: StatisticsEventsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StatisticsEvents
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    private ?Domains $domain = null;

    #[ORM\Column(length: 100)]
    private string $eventName;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $eventTarget = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $page = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\ManyToOne]
    private ?Users $user = null;

    public function getDomain(): ?Domains
    {
        return $this->domain;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getEventTarget(): ?string
    {
        return $this->eventTarget;
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getUser(): ?Users
    {
        return $this->user;
    }

    public function add(
        ?Domains $domain,
        string $eventName,
        ?string $eventTarget = null,
        ?string $page = null,
        ?array $metadata = null,
        ?string $sessionId = null,
        ?Users $user = null,
    ): self {
        $this->activate();
        $this->domain      = $domain;
        $this->eventName   = $eventName;
        $this->eventTarget = $eventTarget;
        $this->page        = $page;
        $this->metadata    = $metadata;
        $this->sessionId   = $sessionId;
        $this->user        = $user;
        return $this;
    }
}
