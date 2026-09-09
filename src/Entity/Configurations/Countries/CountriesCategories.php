<?php

namespace App\Entity\Configurations\Countries;

use App\Entity\Configurations\Globals\Countries;
use App\Entity\Products\Categories\Categories;
use App\Repository\Configurations\Countries\CountriesCategoriesRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountriesCategoriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CountriesCategories
{
    use IdFields { initializeUuid as private initializeId; }
    use ActiveFields;
    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Countries $country;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Categories $categories;

    public function getCountry(): Countries { return $this->country; }
    public function getCategories(): Categories { return $this->categories; }

    public function add(Countries $country, Categories $categories): self
    {
        $this->activate();
        $this->country    = $country;
        $this->categories = $categories;
        return $this;
    }
}
