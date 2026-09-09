<?php

namespace App\Entity\Configurations\Globals;

use App\ArgumentHandler\CitiesArgument;
use App\Repository\Configurations\Globals\CitiesRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: CitiesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Cities
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\Column(length: 5000)]
    private string $names;

    #[ORM\Column(length: 5000)]
    private string $description;

    #[ORM\ManyToOne(inversedBy: 'cities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Regions $region = null;

    #[ORM\Column(length: 150)]
    private string $name;

    public function getName(?string $locale): string
    {
        if ($data = json_decode($this->names, true)) {
            $names = $data['names'] ?? $data;
            return $names[$locale] ?? $names['en'] ?? reset($names) ?? '';
        }
        return $this->names;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getRegion(): ?Regions
    {
        return $this->region;
    }

    public function add(CitiesArgument $argument) : Cities
    {
        $this->activate();
        $this->names = $argument->getNames();
        $this->description = $argument->getDescription();
        $this->region = $argument->getRegion();
        $this->name = $argument->getName();
        return $this;
    }
}
