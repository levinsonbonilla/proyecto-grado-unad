<?php

namespace App\Handler\UseCase\Store\Profile;

use App\ArgumentHandler\UsersArgument;
use App\Exception\GenericException;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Interface\UseCase\Store\Profile\EditUserProfileInterface;
use App\Repository\Configurations\Globals\CitiesRepository;
use App\Repository\Configurations\Globals\CountriesRepository;
use App\Repository\Configurations\Globals\RegionsRepository;
use App\Util\StringUtil;
use Symfony\Bundle\SecurityBundle\Security;

final class EditUserProfileUseCase implements EditUserProfileInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CountriesRepository $countriesRepository,
        private readonly RegionsRepository $regionsRepository,
        private readonly CitiesRepository $citiesRepository,
        private readonly CustomeEntityManagerInterface $em,
        private readonly LogInterface $log,
    ) {}

    public function handler(array $data): array
    {
        try {
            $user = $this->security->getUser();
            if ($user === null) {
                return ['success' => false, 'message' => 'No autenticado.'];
            }

            $name     = trim($data['name'] ?? '');
            $lastName = trim($data['lastName'] ?? '');
            if (empty($name) || empty($lastName)) {
                return ['success' => false, 'message' => 'Nombre y apellido son obligatorios.'];
            }

            $country = null;
            $region  = null;
            $city    = null;

            if (!empty($data['countryId'])) {
                $country = $this->countriesRepository->find(StringUtil::convertToUuid($data['countryId']));
            }
            if (!empty($data['regionId'])) {
                $region = $this->regionsRepository->find(StringUtil::convertToUuid($data['regionId']));
            }
            if (!empty($data['cityId'])) {
                $city = $this->citiesRepository->find(StringUtil::convertToUuid($data['cityId']));
            }

            $argument = new UsersArgument(
                [
                    'email'          => $user->getEmail(),
                    'name'           => $name,
                    'lastName'       => $lastName,
                    'phone'          => $data['phone'] ?? null,
                    'address'        => $data['address'] ?? null,
                    'neighborhood'   => $data['neighborhood'] ?? null,
                    'prefix'         => $data['prefix'] ?? null,
                    'dateOfBirth'    => $data['dateOfBirth'] ?? null,
                    'validatedEmail' => $user->isValidatedEmail(),
                ],
                $user->getDomain(),
                $country,
                $region,
                $city,
            );

            $user->edit($argument);
            $this->em->add($user, true);

            return ['success' => true, 'message' => 'Perfil actualizado correctamente.'];
        } catch (GenericException $e) {
            $this->log->handler($e);
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $th) {
            $this->log->handler($th);
            return ['success' => false, 'message' => 'Error al actualizar el perfil.'];
        }
    }
}
