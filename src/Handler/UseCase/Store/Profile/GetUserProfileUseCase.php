<?php

namespace App\Handler\UseCase\Store\Profile;

use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Profile\GetUserProfileInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class GetUserProfileUseCase implements GetUserProfileInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly LogInterface $log,
    ) {}

    public function handler(): array
    {
        try {
            $user = $this->security->getUser();
            if ($user === null) {
                return [];
            }

            $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'es';

            return [
                'name'         => $user->getName(),
                'lastName'     => $user->getLastName(),
                'email'        => $user->getEmail(),
                'phone'        => $user->getPhone(),
                'address'      => $user->getAddress(),
                'neighborhood' => $user->getNeighborhood(),
                'prefix'       => $user->getPrefix(),
                'dateOfBirth'  => $user->getDateOfBirth()?->format('Y-m-d'),
                'countryId'    => $user->getCountry() ? (string) $user->getCountry()->getId() : null,
                'countryName'  => $user->getCountry()?->getName($locale),
                'regionId'     => $user->getRegion() ? (string) $user->getRegion()->getId() : null,
                'regionName'   => $user->getRegion()?->getName($locale),
                'cityId'       => $user->getCity() ? (string) $user->getCity()->getId() : null,
                'cityName'     => $user->getCity()?->getName($locale),
                'profileImage' => $user->getProfilePicture(),
                'points'       => $user->getPoints(),
            ];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return [];
        }
    }
}
