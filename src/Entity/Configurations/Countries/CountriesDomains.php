<?php

namespace App\Entity\Configurations\Countries;

use App\Entity\Configurations\Globals\Countries;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Configurations\Countries\CountriesDomainsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: CountriesDomainsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CountriesDomains
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Countries $country;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    public function getCountry(): Countries
    {
        return $this->country;
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function add(Countries $country, Domains $domain): self
    {
        $this->activate();
        $this->country = $country;
        $this->domain  = $domain;
        return $this;
    }
}
