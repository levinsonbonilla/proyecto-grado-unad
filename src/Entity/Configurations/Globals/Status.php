<?php

namespace App\Entity\Configurations\Globals;

use App\Repository\Configurations\Globals\StatusRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: StatusRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Status
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\Column(length: 50)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $description;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function add(string $name, string $description): self
    {
        $this->activate();
        $this->name        = $name;
        $this->description = $description;
        return $this;
    }

    public function edit(string $name, string $description): self
    {
        $this->name        = $name;
        $this->description = $description;
        return $this;
    }
}
