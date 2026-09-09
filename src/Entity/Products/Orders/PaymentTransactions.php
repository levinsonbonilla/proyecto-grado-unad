<?php

namespace App\Entity\Products\Orders;

use App\Entity\Configurations\Globals\Status;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Products\Orders\PaymentTransactionsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: PaymentTransactionsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PaymentTransactions
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Orders $orders;

    #[ORM\ManyToOne]
    private ?PaymentMethods $paymentMethod = null;

    #[ORM\Column(type: Types::BIGINT)]
    private string $amount;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Status $status;

    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $gatewayReference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $gatewayResponse = null;

    public function getOrders(): Orders
    {
        return $this->orders;
    }

    public function getPaymentMethod(): ?PaymentMethods
    {
        return $this->paymentMethod;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getStatus(): Status
    {
        return $this->status;
    }

    public function getGatewayReference(): ?string
    {
        return $this->gatewayReference;
    }

    public function getGatewayResponse(): ?string
    {
        return $this->gatewayResponse;
    }

    public function add(Orders $orders, Status $status, string $amount, ?PaymentMethods $paymentMethod = null): self
    {
        $this->initializeId();
        $this->activate();
        $this->orders        = $orders;
        $this->status        = $status;
        $this->amount        = $amount;
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function edit(Status $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setGatewayData(?string $gatewayReference, ?string $gatewayResponse): self
    {
        $this->gatewayReference = $gatewayReference;
        $this->gatewayResponse  = $gatewayResponse;
        return $this;
    }
}
