<?php

namespace App\Entity\Products\Orders;

use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Repository\Products\Orders\OrdersProductsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: OrdersProductsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrdersProducts
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Orders $orders;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Products $product;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?ProductsColors $productColor = null;

    #[ORM\Column(type: Types::BIGINT)]
    private string $quantity;

    #[ORM\Column(type: Types::BIGINT)]
    private string $unitPrice;

    #[ORM\Column(type: Types::BIGINT)]
    private string $totalPrice;

    public function getOrders(): Orders
    {
        return $this->orders;
    }

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function getProductColor(): ?ProductsColors
    {
        return $this->productColor;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function add(Orders $orders, Products $product, string $quantity, string $unitPrice, string $totalPrice, ?ProductsColors $productColor = null): self
    {
        $this->activate();
        $this->orders        = $orders;
        $this->product       = $product;
        $this->productColor  = $productColor;
        $this->quantity      = $quantity;
        $this->unitPrice     = $unitPrice;
        $this->totalPrice    = $totalPrice;
        return $this;
    }
}
