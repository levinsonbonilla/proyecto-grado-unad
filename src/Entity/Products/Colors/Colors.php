<?php

namespace App\Entity\Products\Colors;

use App\ArgumentHandler\ColorsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Products\Colors\ColorsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: ColorsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Colors
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

    #[ORM\Column(length: 7)]
    private string $hexCode;

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getHexCode(): string
    {
        return $this->hexCode;
    }

    public function add(ColorsArgument $argument): Colors
    {
        $this->activate();
        $this->domain = $argument->getDomain();
        $this->name = $argument->getName();
        $this->hexCode = $argument->getHexCode();
        return $this;
    }

    public function edit(ColorsArgument $argument): Colors
    {
        $this->name = $argument->getName();
        $this->hexCode = $argument->getHexCode();
        return $this;
    }
}
