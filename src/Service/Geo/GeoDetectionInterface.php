<?php

namespace App\Service\Geo;

interface GeoDetectionInterface
{
    public function detect(): GeoLocation;
}
