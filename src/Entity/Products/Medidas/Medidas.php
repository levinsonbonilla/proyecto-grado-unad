<?php

namespace App\Entity\Products\Medidas;

use App\ArgumentHandler\MedidasArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Products\Medidas\MedidasRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: MedidasRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Medidas
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

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function add(MedidasArgument $argument): Medidas
    {
        $this->activate();
        $this->domain = $argument->getDomain();
        $this->name = $argument->getName();
        return $this;
    }

    public function edit(MedidasArgument $argument): Medidas
    {
        $this->name = $argument->getName();
        return $this;
    }
}
