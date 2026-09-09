<?php

namespace App\Entity\Configurations\Globals;

use App\ArgumentHandler\CountriesArgument;
use App\Repository\Configurations\Globals\CountriesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: CountriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Countries
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

    #[ORM\OneToMany(targetEntity: Regions::class, mappedBy: 'country')]
    private Collection $regions;

    public function __construct()
    {
        $this->regions = new ArrayCollection();
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

    public function getRegions(): Collection
    {
        return $this->regions;
    }

    public function add(CountriesArgument $argument) : Countries
    {
        $this->activate();
        $this->name = $argument->getName();
        $this->description = $argument->getDescription();
        $this->isoCode = $argument->getIsoCode();
        return $this;
    }
}
