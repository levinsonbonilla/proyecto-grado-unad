<?php

namespace App\Entity\Products\Orders;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Configurations\Globals\Countries;
use App\Entity\Configurations\Globals\Regions;
use App\Entity\Configurations\Globals\Status;
use App\Entity\Users\Users;
use App\Repository\Products\Orders\OrdersRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: OrdersRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Orders
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Users $user;

    #[ORM\Column(type: Types::BIGINT)]
    private string $totalAmount;

    #[ORM\Column(type: Types::TEXT)]
    private string $shippingAddress;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Countries $shippingCountry;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Regions $shippingRegion;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Cities $shippingCity;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Status $status;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $trackingCarrier = null;

    public function getUser(): Users
    {
        return $this->user;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function getShippingAddress(): string
    {
        return $this->shippingAddress;
    }

    public function getShippingCountry(): Countries
    {
        return $this->shippingCountry;
    }

    public function getShippingRegion(): Regions
    {
        return $this->shippingRegion;
    }

    public function getShippingCity(): Cities
    {
        return $this->shippingCity;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function getTrackingCarrier(): ?string
    {
        return $this->trackingCarrier;
    }

    public function add(
        Users $user,
        string $totalAmount,
        string $shippingAddress,
        Countries $shippingCountry,
        Regions $shippingRegion,
        Cities $shippingCity,
        Status $status,
    ): self {
        $this->initializeId();
        $this->activate();
        $this->user            = $user;
        $this->totalAmount     = $totalAmount;
        $this->shippingAddress = $shippingAddress;
        $this->shippingCountry = $shippingCountry;
        $this->shippingRegion  = $shippingRegion;
        $this->shippingCity    = $shippingCity;
        $this->status          = $status;
        return $this;
    }

    public function edit(Status $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setTracking(?string $trackingNumber, ?string $trackingCarrier): self
    {
        $this->trackingNumber  = $trackingNumber;
        $this->trackingCarrier = $trackingCarrier;
        return $this;
    }
}
