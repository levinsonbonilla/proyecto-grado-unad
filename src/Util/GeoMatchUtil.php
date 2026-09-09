<?php

namespace App\Util;

use App\Service\Geo\GeoLocation;

final class GeoMatchUtil
{
    public static function matches(array $countryIds, array $regionIds, array $cityIds, GeoLocation $geo): bool
    {
        $countryMatch = empty($countryIds) || ($geo->country !== null && in_array($geo->getCountryId(), $countryIds, true));
        $regionMatch  = empty($regionIds)  || ($geo->region  !== null && in_array($geo->getRegionId(),  $regionIds, true));
        $cityMatch    = empty($cityIds)    || ($geo->city    !== null && in_array($geo->getCityId(),    $cityIds,   true));

        return $countryMatch && $regionMatch && $cityMatch;
    }
}
