<?php

namespace App\Entity\Configurations\Regions;

use App\Entity\Configurations\Globals\Regions;
use App\Entity\Tenants\Domains\PaymentMethods;
use App\Repository\Configurations\Regions\RegionsPaymentMethodsRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: RegionsPaymentMethodsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RegionsPaymentMethods
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Regions $region;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private PaymentMethods $paymentMethods;

    public function getRegion(): Regions
    {
        return $this->region;
    }

    public function getPaymentMethods(): PaymentMethods
    {
        return $this->paymentMethods;
    }

    public function add(Regions $region, PaymentMethods $paymentMethods): self
    {
        $this->activate();
        $this->region         = $region;
        $this->paymentMethods = $paymentMethods;
        return $this;
    }
}
