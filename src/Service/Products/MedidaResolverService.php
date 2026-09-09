<?php

namespace App\Service\Products;

use App\ArgumentHandler\MedidasArgument;
use App\Entity\Products\Medidas\Medidas;
use App\Entity\Tenants\Domains\Domains;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Repository\Products\Medidas\MedidasRepository;

final readonly class MedidaResolverService implements MedidaResolverInterface
{
    public function __construct(
        private MedidasRepository $medidasRepository,
        private CustomeEntityManagerInterface $customeEntityManager,
    ) {
    }

    public function resolve(Domains $domain, string $name): Medidas
    {
        $existing = $this->medidasRepository->findOneByDomainAndName($domain, $name);
        if ($existing !== null) {
            return $existing;
        }

        $medida = new Medidas();
        $medida->add(new MedidasArgument([
            'name' => $name,
        ], $domain));
        $this->customeEntityManager->add($medida, true);

        return $medida;
    }
}
