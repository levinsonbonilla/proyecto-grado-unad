<?php

namespace App\Entity\Tenants\Others;

use App\ArgumentHandler\AboutSectionsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Tenants\Others\AboutSectionsRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AboutSectionsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AboutSections
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
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $text;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position = 0;

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function add(AboutSectionsArgument $argument): self
    {
        $this->domain = $argument->getValue('domain');
        $this->title = $argument->getValue('title');
        $this->text = $argument->getValue('text');
        $this->image = $argument->getValue('image');
        $this->position = $argument->getValue('position');
        $this->activate();
        return $this;
    }

    public function edit(string $title, string $text, ?int $position = null): self
    {
        $this->title = $title;
        $this->text = $text;
        if ($position !== null) {
            $this->position = $position;
        }
        return $this;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }
}
