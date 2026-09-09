<?php

namespace App\Entity\Products\Others;

use App\ArgumentHandler\ImageProductArgument;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Products;
use App\Repository\Products\Others\ImagesProductsRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImagesProductsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ImagesProducts
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne(inversedBy: 'imagesProducts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Products $product = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $orderColumn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private string $image;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'product_color_id', nullable: true)]
    private ?ProductsColors $productColor = null;

    public function getProduct(): ?Products
    {
        return $this->product;
    }

    public function getProductColor(): ?ProductsColors
    {
        return $this->productColor;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getOrderColumn(): ?int
    {
        return $this->orderColumn;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function add(ImageProductArgument $argument): self
    {
        $this->activate();
        $this->product = $argument->getValue("product");
        $this->description = $argument->getValue("description");
        $this->orderColumn = $argument->getValue("orderColumn");
        $this->image = $argument->getValue("image");
        $this->productColor = $argument->getValue("productColor");
        return $this;
    }

    public function edit(ImageProductArgument $argument): self
    {
        $this->description = $argument->getValue("description");
        $this->orderColumn = $argument->getValue("orderColumn");
        $this->image = $argument->getValue("image");
        $this->productColor = $argument->getValue("productColor");
        return $this;
    }

}
