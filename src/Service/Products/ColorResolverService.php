<?php

namespace App\Service\Products;

use App\ArgumentHandler\ColorsArgument;
use App\Entity\Products\Colors\Colors;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Products\Colors\ColorsRepository;

final readonly class ColorResolverService implements ColorResolverInterface
{
    public function __construct(
        private ColorsRepository $colorsRepository,
        private CustomeEntityManagerInterface $customeEntityManager,
    ) {
    }

    public function resolve(Domains $domain, string $name, ?string $hex): Colors
    {
        $existing = $this->colorsRepository->findOneByDomainAndName($domain, $name);
        if ($existing !== null) {
            return $existing;
        }

        $color = new Colors();
        $color->add(new ColorsArgument([
            'name' => $name,
            'hexCode' => !empty($hex) ? $hex : '#6775d6',
        ], $domain));
        $this->customeEntityManager->add($color, true);

        return $color;
    }
}
