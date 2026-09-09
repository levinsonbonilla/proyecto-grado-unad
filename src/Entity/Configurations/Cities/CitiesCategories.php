<?php

namespace App\Entity\Configurations\Cities;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Products\Categories\Categories;
use App\Repository\Configurations\Cities\CitiesCategoriesRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CitiesCategoriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CitiesCategories
{
    use IdFields { initializeUuid as private initializeId; }
    use ActiveFields;
    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Cities $city;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Categories $categories;

    public function getCity(): Cities { return $this->city; }
    public function getCategories(): Categories { return $this->categories; }

    public function add(Cities $city, Categories $categories): self
    {
        $this->activate();
        $this->city       = $city;
        $this->categories = $categories;
        return $this;
    }
}
