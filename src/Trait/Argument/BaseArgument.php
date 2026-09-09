<?php

namespace App\Trait\Argument;

trait BaseArgument
{
    public function getValue(string $property): mixed
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }

        throw new \Exception("La propiedad '{$property}' no existe.");
    }
}