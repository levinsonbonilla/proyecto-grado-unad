<?php

namespace App\ArgumentHandler;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Tenants\Domains\Domains;
use App\Exception\GenericException;
use App\Util\ArrayUtil;

final class UsersArgument
{
    private string $email;
    private array $roles;
    private string $name;
    private string $lastName;
    private ?string $password = null;
    private ?\DateTimeImmutable $dateOfBirth = null;
    private ?string $phone = null;
    private ?string $address = null;
    private ?string $profilePicture = null;
    private ?string $neighborhood = null;
    private ?string $prefix = null;
    private ?string $points = null;
    private ?bool $validatedEmail;
    public function __construct(
        array $data,
        private readonly Domains $domain,
        private readonly ?Countries $country = null,
        private readonly ?Regions $region = null,
        private readonly ?Cities $city = null
    ) {
        $requireKeys = ["email", "name", "lastName"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new GenericException(
                "ocurrio un error se esperaba: ". json_encode($requireKeys)
                ." se recibio: " . json_encode($data),
                400
            );
        }

        $this->email = $data["email"];
        $this->roles = $data["roles"] ?? [];
        $this->password = ArrayUtil::validateExistKey($data, "password");
        $this->name = $data["name"];
        $this->lastName = $data["lastName"];
        $rawDate = ArrayUtil::validateExistKey($data, "dateOfBirth");
        $this->dateOfBirth = $rawDate !== null ? new \DateTimeImmutable($rawDate) : null;
        $this->phone = ArrayUtil::validateExistKey($data, "phone");
        $this->address = ArrayUtil::validateExistKey($data, "address");
        $this->profilePicture = ArrayUtil::validateExistKey($data, "profilePicture");
        $this->neighborhood = ArrayUtil::validateExistKey($data, "neighborhood");
        $this->prefix = ArrayUtil::validateExistKey($data, "prefix");
        $this->points = ArrayUtil::validateExistKey($data, "points");
        $this->validatedEmail = $data["validatedEmail"] ?? null;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getDateOfBirth(): ?\DateTimeImmutable
    {
        return $this->dateOfBirth;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function getCountry(): ?Countries
    {
        return $this->country;
    }

    public function getRegion(): ?Regions
    {
        return $this->region;
    }

    public function getCity(): ?Cities
    {
        return $this->city;
    }

    public function getNeighborhood(): ?string
    {
        return $this->neighborhood;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function getPoints(): ?string
    {
        return $this->points;
    }

    public function validatedEmail(): ?bool
    {
        return $this->validatedEmail;
    }
}
