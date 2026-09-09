<?php

namespace App\Entity\Tenants\Domains;

use App\Repository\Tenants\Domains\PaymentMethodsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: PaymentMethodsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PaymentMethods
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    public const PROVIDER_MANUAL      = 'manual';
    public const PROVIDER_MOCK_GATEWAY = 'mock_gateway';

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Domains $domain = null;

    #[ORM\Column(length: 50)]
    private string $provider = self::PROVIDER_MANUAL;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function add(Domains $domain, string $name, string $provider = self::PROVIDER_MANUAL, ?string $instructions = null): self
    {
        $this->activate();
        $this->domain       = $domain;
        $this->name         = $name;
        $this->provider     = $provider;
        $this->instructions = $instructions;
        return $this;
    }

    public function edit(string $name, string $provider = self::PROVIDER_MANUAL, ?string $instructions = null): self
    {
        $this->name         = $name;
        $this->provider     = $provider;
        $this->instructions = $instructions;
        return $this;
    }
}
