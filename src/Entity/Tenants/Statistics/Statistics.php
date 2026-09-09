<?php

namespace App\Entity\Tenants\Statistics;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Repository\Tenants\Statistics\StatisticsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Table(name: 'statistics')]
#[ORM\Index(columns: ['domain_id', 'created_at'], name: 'idx_stats_domain_date')]
#[ORM\Index(columns: ['domain_id', 'country'], name: 'idx_stats_domain_country')]
#[ORM\Index(columns: ['domain_id', 'browser'], name: 'idx_stats_domain_browser')]
#[ORM\Index(columns: ['domain_id', 'device'], name: 'idx_stats_domain_device')]
#[ORM\Index(columns: ['session_id'], name: 'idx_stats_session')]
#[ORM\Entity(repositoryClass: StatisticsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Statistics
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    private ?Domains $domain = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $device = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $browser = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $operativeSystem = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lang = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $page = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $referrer = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referrerDomain = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $utmSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $utmMedium = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $utmCampaign = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $countryIsoCode = null;

    #[ORM\ManyToOne]
    private ?Users $user = null;

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function getOperativeSystem(): ?string
    {
        return $this->operativeSystem;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function getCountryIsoCode(): ?string { return $this->countryIsoCode; }
    public function getPage(): ?string { return $this->page; }
    public function getReferrer(): ?string { return $this->referrer; }
    public function getReferrerDomain(): ?string { return $this->referrerDomain; }
    public function getUtmSource(): ?string { return $this->utmSource; }
    public function getUtmMedium(): ?string { return $this->utmMedium; }
    public function getUtmCampaign(): ?string { return $this->utmCampaign; }
    public function getSessionId(): ?string { return $this->sessionId; }
    public function getUser(): ?Users { return $this->user; }

    public function add(
        ?Domains $domain         = null,
        ?string $ip              = null,
        ?string $device          = null,
        ?string $country         = null,
        ?string $region          = null,
        ?string $city            = null,
        ?string $browser         = null,
        ?string $operativeSystem = null,
        ?string $lang            = null,
        ?string $page            = null,
        ?string $referrer        = null,
        ?string $referrerDomain  = null,
        ?string $utmSource       = null,
        ?string $utmMedium       = null,
        ?string $utmCampaign     = null,
        ?string $sessionId       = null,
        ?string $countryIsoCode  = null,
        ?Users $user             = null,
    ): self {
        $this->activate();
        $this->domain          = $domain;
        $this->ip              = $ip;
        $this->device          = $device;
        $this->country         = $country;
        $this->region          = $region;
        $this->city            = $city;
        $this->browser         = $browser;
        $this->operativeSystem = $operativeSystem;
        $this->lang            = $lang;
        $this->page            = $page;
        $this->referrer        = $referrer;
        $this->referrerDomain  = $referrerDomain;
        $this->utmSource       = $utmSource;
        $this->utmMedium       = $utmMedium;
        $this->utmCampaign     = $utmCampaign;
        $this->sessionId       = $sessionId;
        $this->countryIsoCode  = $countryIsoCode;
        $this->user            = $user;
        return $this;
    }
}
