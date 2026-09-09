<?php

namespace App\Entity\Products\Colors;

use App\ArgumentHandler\ProductColorArgument;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Products\Products;
use App\Repository\Products\Colors\ProductsColorsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductsColorsRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'uniq_product_color_medida', columns: ['product_id', 'color_id', 'medida_id'])]
class ProductsColors
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne(inversedBy: 'productsColors')]
    #[ORM\JoinColumn(nullable: false)]
    private Products $product;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Colors $color = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Medidas $medida = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $stock = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $orderColumn = null;

    #[ORM\Column(type: 'boolean')]
    private bool $lowStockAlertSent = false;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $variantGroupId = null;

    public function getProduct(): Products
    {
        return $this->product;
    }

    public function getColor(): ?Colors
    {
        return $this->color;
    }

    public function getMedida(): ?Medidas
    {
        return $this->medida;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function hasStockTracking(): bool
    {
        return $this->stock !== null;
    }

    public function isOutOfStock(): bool
    {
        return $this->stock === 0;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getVariantGroupId(): ?Uuid
    {
        return $this->variantGroupId;
    }

    public function getOrderColumn(): ?int
    {
        return $this->orderColumn;
    }

    public function add(ProductColorArgument $argument): ProductsColors
    {
        $this->activate();
        $this->product = $argument->getProduct();
        $this->color = $argument->getColor();
        $this->medida = $argument->getMedida();
        $this->stock = $argument->getStock();
        $this->image = $argument->getImage();
        $this->orderColumn = $argument->getOrderColumn();
        if ($argument->getVariantGroupId() !== null) {
            $this->variantGroupId = Uuid::fromString($argument->getVariantGroupId());
        }
        return $this;
    }

    public function edit(ProductColorArgument $argument): ProductsColors
    {
        $this->color = $argument->getColor();
        $this->medida = $argument->getMedida();
        $this->stock = $argument->getStock();
        $this->orderColumn = $argument->getOrderColumn();

        $this->lowStockAlertSent = false;
        if ($argument->getImage() !== null) {
            $this->image = $argument->getImage();
        }

        if ($argument->getVariantGroupId() !== null) {
            $this->variantGroupId = Uuid::fromString($argument->getVariantGroupId());
        }
        return $this;
    }

    public function decreaseStock(int $quantity): void
    {
        if ($this->stock === null) {
            return;
        }
        $this->stock = max(0, $this->stock - $quantity);
    }

    public function hasLowStockAlertSent(): bool
    {
        return $this->lowStockAlertSent;
    }

    public function markLowStockAlertSent(): void
    {
        $this->lowStockAlertSent = true;
    }
}
