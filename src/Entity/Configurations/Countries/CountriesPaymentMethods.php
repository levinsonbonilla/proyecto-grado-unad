<?php

namespace App\Entity\Configurations\Countries;

use App\Entity\Configurations\Globals\Countries;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Configurations\Countries\CountriesPaymentMethodsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: CountriesPaymentMethodsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CountriesPaymentMethods
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Countries $country;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private PaymentMethods $paymentMethods;

    public function getCountry(): Countries
    {
        return $this->country;
    }

    public function getPaymentMethods(): PaymentMethods
    {
        return $this->paymentMethods;
    }

    public function add(Countries $country, PaymentMethods $paymentMethods): self
    {
        $this->activate();
        $this->country        = $country;
        $this->paymentMethods = $paymentMethods;
        return $this;
    }
}
