<?php

namespace App\Entity\Products\Others;

use App\Entity\Products\Products;
use App\Entity\Users\Users;
use App\Repository\Products\Others\FavoriteProductsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: FavoriteProductsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FavoriteProducts
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Users $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Products $product;

    public function getUser(): Users
    {
        return $this->user;
    }

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function add(Users $user, Products $product): self
    {
        $this->activate();
        $this->user    = $user;
        $this->product = $product;
        return $this;
    }
}
