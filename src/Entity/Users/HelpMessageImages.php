<?php

namespace App\Entity\Users;

use App\Repository\Users\HelpMessageImagesRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HelpMessageImagesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HelpMessageImages
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne(targetEntity: HelpMessages::class, inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false)]
    private HelpMessages $helpMessage;

    #[ORM\Column(length: 500)]
    private string $imageUrl;

    public function add(HelpMessages $message, string $imageUrl): self
    {
        $this->activate();
        $this->helpMessage = $message;
        $this->imageUrl    = $imageUrl;
        return $this;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function getHelpMessage(): HelpMessages
    {
        return $this->helpMessage;
    }
}
