<?php

namespace App\Entity\Configurations\Regions;

use App\Entity\Configurations\Globals\Regions;
use App\Entity\Products\Categories\Categories;
use App\Repository\Configurations\Regions\RegionsCategoriesRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegionsCategoriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RegionsCategories
{
    use IdFields { initializeUuid as private initializeId; }
    use ActiveFields;
    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Regions $region;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Categories $categories;

    public function getRegion(): Regions { return $this->region; }
    public function getCategories(): Categories { return $this->categories; }

    public function add(Regions $region, Categories $categories): self
    {
        $this->activate();
        $this->region     = $region;
        $this->categories = $categories;
        return $this;
    }
}
