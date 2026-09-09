<?php

namespace App\Entity\Products\Others;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Entity\Users\Users;
use App\Repository\Products\Others\ShoppingCartRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use DateTimeImmutable;

#[ORM\Entity(repositoryClass: ShoppingCartRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ShoppingCart
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductsColors $productColor = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column]
    private DateTimeImmutable $addedAt;

    public function getUser(): Users
    {
        return $this->user;
    }

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function getProductColor(): ?ProductsColors
    {
        return $this->productColor;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getAddedAt(): DateTimeImmutable
    {
        return $this->addedAt;
    }

    public function add(Users $user, Products $product, int $quantity = 1, ?ProductsColors $productColor = null): self
    {
        $this->activate();
        $this->user         = $user;
        $this->product      = $product;
        $this->productColor = $productColor;
        $this->quantity     = $quantity;
        $this->addedAt      = new DateTimeImmutable();
        return $this;
    }

    public function updateQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }
}
