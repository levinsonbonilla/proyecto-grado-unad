<?php

namespace App\Entity\Products\Categories;

use App\ArgumentHandler\CategoriesArgument;
use App\ArgumentHandler\GeneralArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Products\Categories\CategoriesRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: CategoriesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Categories
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = NULl;

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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function add(CategoriesArgument $argument): Categories
    {
        $this->activate();
        $this->name = $argument->getName();
        $this->description = $argument->getDescription();
        $this->domain = $argument->getDomain();
        $this->image = $argument->getImage();

        return $this;
    }

    public function edit(CategoriesArgument $argument): Categories
    {
        $this->name = $argument->getName();
        $this->description = $argument->getDescription();
        if ($argument->getImage() !== null) {
            $this->image = $argument->getImage();
        }
        return $this;
    }
}
