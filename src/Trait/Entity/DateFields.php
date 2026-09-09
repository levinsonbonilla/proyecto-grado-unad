<?php

namespace App\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;

trait DateFields
{
    #[ORM\Column]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column]
    protected \DateTimeImmutable $updatedAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->create();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->update();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    protected function create(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    protected function update(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
