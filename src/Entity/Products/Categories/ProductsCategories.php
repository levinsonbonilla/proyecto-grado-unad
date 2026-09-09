<?php

namespace App\Entity\Products\Categories;

use App\Entity\Products\Products;
use App\Repository\Products\Categories\ProductsCategoriesRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: ProductsCategoriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProductsCategories
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Products $product;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Categories $category;

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function getCategory(): Categories
    {
        return $this->category;
    }

    public function add(Products $product, Categories $category): ProductsCategories
    {
        $this->activate();
        $this->product = $product;
        $this->category = $category;
        return $this;
    }
}
