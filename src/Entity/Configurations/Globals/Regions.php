<?php

namespace App\Entity\Configurations\Globals;

use App\ArgumentHandler\RegionsArgument;
use App\Repository\Configurations\Globals\RegionsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: RegionsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Regions
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\Column(length: 5000)]
    private string $name;

    #[ORM\Column(length: 5000)]
    private string $description;

    #[ORM\Column(length: 25)]
    private string $isoCode;

    #[ORM\ManyToOne(inversedBy: 'regions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Countries $country = null;

    #[ORM\OneToMany(targetEntity: Cities::class, mappedBy: 'region')]
    private Collection $cities;

    public function __construct()
    {
        $this->cities = new ArrayCollection();
    }

    public function getName(?string $locale): string
    {
        if ($names = json_decode($this->name, true)) {
            return $names[$locale] ?? $names[array_key_first($names)] ?? $this->name;
        }
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIsoCode(): string
    {
        return $this->isoCode;
    }

    public function getCountry(): ?Countries
    {
        return $this->country;
    }

    public function getCities(): Collection
    {
        return $this->cities;
    }

    public function add(RegionsArgument $argument) : Regions
    {
        $this->activate();
        $this->name = $argument->getName();
        $this->description = $argument->getDescription();
        $this->country = $argument->getCountry();
        $this->isoCode = $argument->getIsoCode();
        return $this;
    }
}
