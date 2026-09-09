<?php

namespace App\Entity\Configurations\Cities;

use App\Entity\Configurations\Globals\Cities;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Configurations\Cities\CitiesPaymentMethodsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: CitiesPaymentMethodsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CitiesPaymentMethods
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Cities $city;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private PaymentMethods $paymentMethods;

    public function getCity(): Cities
    {
        return $this->city;
    }

    public function getPaymentMethods(): PaymentMethods
    {
        return $this->paymentMethods;
    }

    public function add(Cities $city, PaymentMethods $paymentMethods): self
    {
        $this->activate();
        $this->city           = $city;
        $this->paymentMethods = $paymentMethods;
        return $this;
    }
}
