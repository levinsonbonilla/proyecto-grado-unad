<?php

namespace App\Entity\Tenants\Others;

use App\ArgumentHandler\SlidesArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Tenants\Others\SlidesRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\ORM\Mapping as ORM;
use phpDocumentor\Reflection\Types\Integer;

#[ORM\Entity(repositoryClass: SlidesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Slides
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 3000, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private string $image;

    #[ORM\Column(length: 255)]
    private string $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    #[ORM\Column(length: 255)]
    private int $position;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getDomain(): ?Domains
    {
        return $this->domain;
    }

    public function add(SlidesArgument $argument): void
    {
        $this->name = $argument->getValue("name");
        $this->description = $argument->getValue("description");
        $this->image = $argument->getValue("image");
        $this->type = $argument->getValue("type");
        $this->position = $argument->getValue("position");
        $this->domain = $argument->getValue("domains");
        $this->activate();
    }

    public function edit(?string $name, ?string $description, ?string $type, ?int $position = null): void
    {
        $this->name = $name;
        $this->description = $description;
        if ($type !== null) {
            $this->type = $type;
        }
        if ($position !== null) {
            $this->position = $position;
        }
    }

    public function setImage(string $image): void
    {
        $this->image = $image;
    }

}
