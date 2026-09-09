<?php

namespace App\Entity\Tenants;

use App\ArgumentHandler\TenantsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Tenants\TenantsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: TenantsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Tenants
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column]
    private bool $isPrincipal = false;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nit = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $prefix = null;

    #[ORM\OneToMany(targetEntity: Domains::class, mappedBy: 'tenant')]
    private Collection $domains;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getNit(): ?string
    {
        return $this->nit;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function isPrincipal(): bool
    {
        return $this->isPrincipal;
    }

    public function markAsPrincipal(): self
    {
        $this->isPrincipal = true;
        return $this;
    }

    public function unmarkAsPrincipal(): self
    {
        $this->isPrincipal = false;
        return $this;
    }

    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function add(TenantsArgument $argument) : Tenants
    {
        $this->activate();
        $this->name = $argument->getName();
        $this->description = $argument->getDescription();
        $this->nit = $argument->getNit();
        $this->phone = $argument->getPhone();
        $this->prefix = $argument->getPrefix();
        return $this;
    }

    public function edit(TenantsArgument $argument): Tenants
    {
        $this->name = $argument->getName();
        $this->description = $argument->getDescription();
        $this->phone = $argument->getPhone();
        $this->prefix = $argument->getPrefix();
        return $this;
    }
}
