<?php

namespace App\Entity\Users;

use App\ArgumentHandler\UsersDomainsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Repository\Users\UsersDomainsRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UsersDomainsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UsersDomains
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne(inversedBy: 'userDomains')]
    #[ORM\JoinColumn(nullable: false)]
    private Users $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Domains $domain;

    #[ORM\Column(nullable: true)]
    private ?array $roles = [];

    public function getUser(): Users
    {
        return $this->user;
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function rolesChange(array $roles): void
    {
        $this->roles = $roles;
    }

    public function add(UsersDomainsArgument $argument): UsersDomains
    {
        $this->activate();
        $this->user = $argument->getUser();
        $this->domain = $argument->getDomain();
        $this->roles = $argument->getRoles();
        return $this;
    }
}
