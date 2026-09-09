<?php

namespace App\Service\Geo;

use App\Interface\Configuration\LocationInterface;

final class GeoDetectionService implements GeoDetectionInterface
{
    public function __construct(
        private readonly LocationInterface $location,
    ) {}

    public function detect(): GeoLocation
    {
        return new GeoLocation(
            country: $this->location->getCountry(),
            region:  $this->location->getRegion(),
            city:    $this->location->getCity(),
        );
    }
}
