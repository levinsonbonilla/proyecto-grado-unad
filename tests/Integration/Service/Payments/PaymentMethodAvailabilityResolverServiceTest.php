<?php

namespace App\Tests\Integration\Service\Payments;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use App\Service\Geo\GeoLocation;
use App\Service\Payments\PaymentMethodAvailabilityResolverInterface;
use App\Tests\Integration\IntegrationTestCase;

class PaymentMethodAvailabilityResolverServiceTest extends IntegrationTestCase
{
    private function mainDomain(): Domains
    {
        $domain = $this->em->getRepository(Domains::class)
            ->findOneBy(['domain' => 'http://localhost:8060', 'active' => true]);
        $this->assertNotNull($domain, 'fixture del domain principal debe existir');
        return $domain;
    }

    private function tarjetaDeCredito(): PaymentMethods
    {
        $pm = $this->em->getRepository(PaymentMethods::class)->findOneBy(['name' => 'Tarjeta de crédito']);
        $this->assertNotNull($pm, 'fixture "Tarjeta de crédito" debe existir');
        return $pm;
    }

    private function linkedLocation(PaymentMethods $pm): GeoLocation
    {
        $countryLink = static::getContainer()->get(CountriesPaymentMethodsRepository::class)->findAllByPaymentMethod($pm)[0] ?? null;
        $regionLink  = static::getContainer()->get(RegionsPaymentMethodsRepository::class)->findAllByPaymentMethod($pm)[0] ?? null;
        $cityLink    = static::getContainer()->get(CitiesPaymentMethodsRepository::class)->findAllByPaymentMethod($pm)[0] ?? null;

        $this->assertNotNull($countryLink, 'fixture debe vincular "Tarjeta de crédito" a un país');
        $this->assertNotNull($regionLink, 'fixture debe vincular "Tarjeta de crédito" a una región');
        $this->assertNotNull($cityLink, 'fixture debe vincular "Tarjeta de crédito" a una ciudad');

        return new GeoLocation($countryLink->getCountry(), $regionLink->getRegion(), $cityLink->getCity());
    }

    public function testResolveIncludesGeoRestrictedMethodWhenLocationMatches(): void
    {
        $pm       = $this->tarjetaDeCredito();
        $resolver = static::getContainer()->get(PaymentMethodAvailabilityResolverInterface::class);

        $result = $resolver->resolve($this->mainDomain(), $this->linkedLocation($pm));

        $names = array_map(fn (PaymentMethods $item) => $item->getName(), $result);
        $this->assertContains('Tarjeta de crédito', $names);
        $this->assertContains('Efectivo', $names, 'método sin restricción geo debe estar siempre disponible');
        $this->assertContains('Transferencia bancaria', $names, 'método sin restricción geo debe estar siempre disponible');
    }

    public function testResolveExcludesGeoRestrictedMethodWhenNoLocationKnown(): void
    {
        $pm       = $this->tarjetaDeCredito();
        $resolver = static::getContainer()->get(PaymentMethodAvailabilityResolverInterface::class);

        $result = $resolver->resolve($this->mainDomain(), new GeoLocation(null, null, null));

        $names = array_map(fn (PaymentMethods $item) => $item->getName(), $result);
        $this->assertNotContains('Tarjeta de crédito', $names, 'restringido por geo, no debe listarse sin ubicación');
        $this->assertContains('Efectivo', $names);
    }

    public function testResolveExcludesGeoRestrictedMethodWhenCityDoesNotMatch(): void
    {
        $pm            = $this->tarjetaDeCredito();
        $linkedGeo     = $this->linkedLocation($pm);
        $linkedCityId  = (string) $linkedGeo->city->getId();

        $otherCity = $this->em->getRepository(Cities::class)->createQueryBuilder('c')
            ->where('c.id != :id')
            ->setParameter('id', \App\Util\UUIDUtil::convertIdToSearch($linkedCityId))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        $this->assertNotNull($otherCity, 'fixtures deben traer al menos dos ciudades para este caso');

        $resolver = static::getContainer()->get(PaymentMethodAvailabilityResolverInterface::class);
        $result   = $resolver->resolve(
            $this->mainDomain(),
            new GeoLocation($linkedGeo->country, $linkedGeo->region, $otherCity)
        );

        $names = array_map(fn (PaymentMethods $item) => $item->getName(), $result);
        $this->assertNotContains('Tarjeta de crédito', $names, 'restringido a otra ciudad, no debe listarse para esta');
    }

    public function testIsAvailableMatchesResolveForSingleMethod(): void
    {
        $pm       = $this->tarjetaDeCredito();
        $resolver = static::getContainer()->get(PaymentMethodAvailabilityResolverInterface::class);

        $this->assertTrue($resolver->isAvailable($pm, $this->linkedLocation($pm)));
        $this->assertFalse($resolver->isAvailable($pm, new GeoLocation(null, null, null)));
    }
}
