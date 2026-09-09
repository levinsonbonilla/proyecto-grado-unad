<?php

namespace App\Interface\Configuration;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;

interface LocationInterface
{
    public function getCountry(): ?Countries;

    public function getRegion(): ?Regions;

    public function getCity(): ?Cities;

    public function getLanguage(): string;
}