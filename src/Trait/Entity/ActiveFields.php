<?php

namespace App\Trait\Entity;

use Doctrine\ORM\Mapping as ORM;

trait ActiveFields
{
    #[ORM\Column]
    protected bool $active;

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function activate() : void
    {
        $this->active = true;
    }

    public function deactivate() : void
    {
        $this->active = false;
    }
}
