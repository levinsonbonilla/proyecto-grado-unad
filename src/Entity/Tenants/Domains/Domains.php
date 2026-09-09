<?php

namespace App\Entity\Tenants\Domains;

use App\ArgumentHandler\DomainsArgument;
use App\Entity\Tenants\Tenants;
use App\Repository\Tenants\Domains\DomainsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: DomainsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Domains
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne(inversedBy: 'domains')]
    private Tenants $tenant;

    #[ORM\Column(length: 500)]
    private string $domain;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 500, nullable: true)]
    private string $logo;

    #[ORM\Column(length: 500)]
    private string $notificationEmail;

    #[ORM\Column(length: 500)]
    private string $supportEmail;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $facebookUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $instagramUrl = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $pinterestUrl = null;

    public function getTenant(): Tenants
    {
        return $this->tenant;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDisplayName(): string
    {
        return $this->name ?: $this->domain;
    }

    public function getLogo(): string
    {
        return $this->logo;
    }

    public function getNotificationEmail(): string
    {
        return $this->notificationEmail;
    }

    public function getSupportEmail(): string
    {
        return $this->supportEmail;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function getPinterestUrl(): ?string
    {
        return $this->pinterestUrl;
    }

    public function add(DomainsArgument $argument) : Domains
    {
        $this->activate();
        $this->tenant = $argument->getTenant();
        $this->domain = $argument->getDomain();
        $this->name = $argument->getName();
        $this->logo = $argument->getlogo();
        $this->notificationEmail = $argument->getNotificationEmail();
        $this->supportEmail = $argument->getSupportEmail();
        $this->facebookUrl = $argument->getFacebookUrl();
        $this->instagramUrl = $argument->getInstagramUrl();
        $this->pinterestUrl = $argument->getPinterestUrl();
        return $this;
    }

    public function edit(DomainsArgument $argument): Domains
    {
        $this->domain = $argument->getDomain();
        $this->name = $argument->getName();
        $this->logo = $argument->getLogo();
        $this->notificationEmail = $argument->getNotificationEmail();
        $this->supportEmail = $argument->getSupportEmail();
        $this->facebookUrl = $argument->getFacebookUrl();
        $this->instagramUrl = $argument->getInstagramUrl();
        $this->pinterestUrl = $argument->getPinterestUrl();
        return $this;
    }
}
