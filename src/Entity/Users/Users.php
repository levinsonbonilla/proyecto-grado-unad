<?php

namespace App\Entity\Users;

use App\ArgumentHandler\UsersArgument;
use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Tenants\Domains\Domains;
use App\Repository\Users\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use App\Util\InjectCriteria;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    public const DEFAULT_IMAGE_PROFILE = "default_images/blank-profile-picture.png";

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateOfBirth = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\ManyToOne]
    private ?Countries $country = null;

    #[ORM\ManyToOne]
    private ?Regions $region = null;

    #[ORM\ManyToOne]
    private ?Cities $city = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $neighborhood = null;

    #[ORM\Column(length: 15, nullable: true)]
    private ?string $prefix = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $points = null;

    #[ORM\Column()]
    private bool $validatedEmail;

    #[ORM\OneToMany(
        targetEntity: UsersDomains::class,
        mappedBy: 'user',
        cascade: ['persist']
    )]
    private Collection $userDomains;

    #[ORM\OneToMany(targetEntity: HelpMessages::class, mappedBy: 'toUser')]
    private Collection $helpMessages;

    public function __construct(
        private readonly UserPasswordHasherInterface  $userPasswordHasher
    ) {
        $this->userDomains = new ArrayCollection();
        $this->helpMessages = new ArrayCollection();
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    #[\Override]
    public function getRoles(): array
    {
        $roles = $this->roles;

        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    #[\Override]
    public function eraseCredentials(): void
    {

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

    public function getFullName(): string
    {
        return $this->name." ".$this->lastName;
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

    public function getProfilePicture(bool $isRealPicture = false): ?string
    {
        if (empty($this->profilePicture) && !$isRealPicture) {
            return self::DEFAULT_IMAGE_PROFILE;
        }

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

    public function isValidatedEmail(): bool
    {
        return $this->validatedEmail;
    }

    public function getUserDomains(): array|ArrayCollection|Collection
    {
        return $this->userDomains;
    }

    public function getDomains(): array|ArrayCollection|Collection
    {
        return $this->getUserDomains()->map(fn (
            UsersDomains $usersDomains
        ) => $usersDomains->getDomain());
    }

    public function getDomainsActives(): ArrayCollection|Collection
    {
        return $this->getDomains()->matching(InjectCriteria::addCriteria());
    }

    public function getUserDomainsActives(): ArrayCollection|Collection
    {
        return $this->userDomains->matching(InjectCriteria::addCriteria());
    }

    public function getUsersDomainsActivesByDomain(Domains $domain): UsersDomains | bool
    {
        $usersDomains = $this->getUserDomains()->filter(
            fn (UsersDomains $ud) =>
            $ud->isActive() &&
                $ud->getDomain()->getId() == $domain->getId()
        );

        return $usersDomains->first();
    }

    public function getUsersDomainsByDomain(Domains $domain): UsersDomains | bool
    {
        $usersDomains = $this->getUserDomains()->filter(
            fn (UsersDomains $ud) =>
            $ud->getDomain()->getId() == $domain->getId()
        );

        return $usersDomains->first();
    }

    public function getHelpMessages(): Collection
    {
        $fields = [
            "active" => true,
            "isRead" => false
        ];
        return $this->helpMessages->matching(InjectCriteria::addCriteria($fields));
    }

    public function add(UsersArgument $argument): Users
    {
        $this->activate();
        $this->email = $argument->getEmail();
        $this->roles = $argument->getRoles();
        $this->password = $this->userPasswordHasher->hashPassword($this, $argument->getPassword());
        $this->domain = $argument->getDomain();
        $this->name = $argument->getName();
        $this->lastName = $argument->getLastName();
        $this->dateOfBirth = $argument->getDateOfBirth();
        $this->phone = $argument->getPhone();
        $this->address = $argument->getAddress();
        $this->profilePicture = $argument->getProfilePicture();
        $this->country = $argument->getCountry();
        $this->region = $argument->getRegion();
        $this->city = $argument->getCity();
        $this->neighborhood = $argument->getNeighborhood();
        $this->prefix = $argument->getPrefix();
        $this->points = $argument->getPoints();
        $this->validatedEmail = $argument->validatedEmail() ?? false;

        return $this;
    }

    public function edit(UsersArgument $argument): Users
    {
        $this->name = $argument->getName();
        $this->lastName = $argument->getLastName();
        $this->dateOfBirth = $argument->getDateOfBirth() ?? $this->dateOfBirth;
        $this->phone = $argument->getPhone() ?? $this->phone;
        $this->address = $argument->getAddress() ?? $this->address;
        $this->profilePicture = $argument->getProfilePicture() ?? $this->profilePicture;
        $this->country = $argument->getCountry() ?? $this->country;
        $this->region = $argument->getRegion() ?? $this->region;
        $this->city = $argument->getCity() ?? $this->city;
        $this->neighborhood = $argument->getNeighborhood() ?? $this->neighborhood;
        $this->prefix = $argument->getPrefix() ?? $this->prefix;
        $this->validatedEmail = $argument->validatedEmail() ?? $this->validatedEmail;

        return $this;
    }

    public function validateEmail(): void
    {
        $this->validatedEmail = true;
    }

    public function passwordChange(string $password): void
    {
        $this->password = $password;
    }

    public function rolesChange(array $roles): void
    {
        $this->roles = $roles;
    }

    public function isSuperAdmin(Domains $domain): bool
    {
        $userDomain = $this->getUsersDomainsActivesByDomain($domain);
        if (!$userDomain) {
            return false;
        }
        return in_array('ROLE_SUPER_ADMIN', $userDomain->getRoles() ?? [], true);
    }
}
