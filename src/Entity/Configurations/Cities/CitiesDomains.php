<?php

namespace App\Entity\Configurations\Cities;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Configurations\Cities\CitiesDomainsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: CitiesDomainsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CitiesDomains
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Cities $city;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    public function getCity(): Cities
    {
        return $this->city;
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function add(Cities $city, Domains $domain): self
    {
        $this->activate();
        $this->city   = $city;
        $this->domain = $domain;
        return $this;
    }
}
