<?php

namespace App\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

trait IdFields
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    public function getId(): Uuid
    {
        return $this->id;
    }

    protected function initializeUuid(): void
    {
        if (!isset($this->id)) {
            $this->id = Uuid::v4();
        }
    }

}
