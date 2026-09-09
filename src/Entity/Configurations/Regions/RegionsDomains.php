<?php

namespace App\Entity\Configurations\Regions;

use App\Entity\Configurations\Globals\Regions;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Configurations\Regions\RegionsDomainsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: RegionsDomainsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RegionsDomains
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Regions $region;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    public function getRegion(): Regions
    {
        return $this->region;
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function add(Regions $region, Domains $domain): self
    {
        $this->activate();
        $this->region = $region;
        $this->domain = $domain;
        return $this;
    }
}
