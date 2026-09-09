<?php

namespace App\Entity\Products;

use App\ArgumentHandler\ProductsArgument;
use App\Entity\Products\Colors\ProductsColors;
use App\Entity\Products\Others\ImagesProducts;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Products\ProductsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: ProductsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Products
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::BIGINT)]
    private string $basePrice;

    #[ORM\Column(type: Types::BIGINT)]
    private string $publicPrice;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $stock = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $lowStockAlertSent = false;

    #[ORM\OneToMany(targetEntity: ImagesProducts::class, mappedBy: 'product')]
    private Collection $imagesProducts;

    #[ORM\OneToMany(targetEntity: ProductsColors::class, mappedBy: 'product')]
    private Collection $productsColors;

    public function __construct()
    {
        $this->imagesProducts = new ArrayCollection();
        $this->productsColors = new ArrayCollection();
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getBasePrice(): string
    {
        return $this->basePrice;
    }

    public function getPublicPrice(): string
    {
        return $this->publicPrice;
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

    public function getImagesProducts(): Collection
    {
        return $this->imagesProducts;
    }

    public function getProductsColors(): Collection
    {
        return $this->productsColors;
    }

    public function add(ProductsArgument $argument): Products
    {
        $this->activate();
        $this->domain = $argument->getValue("domain");
        $this->name = $argument->getValue("name");
        $this->description = $argument->getValue("description");
        $this->basePrice = $argument->getValue("basePrice");
        $this->publicPrice = $argument->getValue("publicPrice");
        $this->stock = $argument->getValue("stock");
        return $this;
    }

    public function edit(ProductsArgument $argument): Products
    {
        $domain = $argument->getValue("domain");
        if ($domain !== null) {
            $this->domain = $domain;
        }

        $name = $argument->getValue("name");
        if ($name !== null && trim((string) $name) !== '') {
            $this->name = (string) $name;
        }

        $description = $argument->getValue("description");
        if ($description !== null && trim((string) $description) !== '') {
            $this->description = (string) $description;
        }

        $basePrice = $argument->getValue("basePrice");
        if ($basePrice !== null && trim((string) $basePrice) !== '') {
            $this->basePrice = (string) $basePrice;
        }

        $publicPrice = $argument->getValue("publicPrice");
        if ($publicPrice !== null && trim((string) $publicPrice) !== '') {
            $this->publicPrice = (string) $publicPrice;
        }

        $this->stock = $argument->getValue("stock");

        $this->lowStockAlertSent = false;

        return $this;
    }

}
