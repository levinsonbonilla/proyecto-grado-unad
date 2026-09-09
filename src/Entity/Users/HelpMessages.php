<?php

namespace App\Entity\Users;

use App\Repository\Users\HelpMessagesRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HelpMessagesRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HelpMessages
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne(inversedBy: 'helpMessages')]
    #[ORM\JoinColumn(nullable: false)]
    private Users $toUser;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Users $fromUser;

    #[ORM\Column]
    private bool $isRead;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\ManyToOne(targetEntity: HelpMessages::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?HelpMessages $parentMessage = null;

    #[ORM\OneToMany(targetEntity: HelpMessageImages::class, mappedBy: 'helpMessage', cascade: ['persist'])]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function getToUser(): Users
    {
        return $this->toUser;
    }

    public function getFromUser(): Users
    {
        return $this->fromUser;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSubject(): ?string
    {
        return $this->subject ?? $this->parentMessage?->getSubject();
    }

    public function getParentMessage(): ?HelpMessages
    {
        return $this->parentMessage;
    }

    public function getImages(): Collection
    {
        return $this->images->filter(fn(HelpMessageImages $img) => $img->isActive());
    }

    public function isReply(): bool
    {
        return $this->parentMessage !== null;
    }

    public function add(Users $toUser, Users $fromUser, string $message, ?string $subject = null): self
    {
        $this->activate();
        $this->toUser   = $toUser;
        $this->fromUser = $fromUser;
        $this->isRead   = false;
        $this->message  = $message;
        $this->subject  = $subject !== null && $subject !== '' ? $subject : null;
        return $this;
    }

    public function addReply(Users $toUser, Users $fromUser, string $message, HelpMessages $parent): self
    {
        $this->add($toUser, $fromUser, $message);
        $this->parentMessage = $parent;
        return $this;
    }

    public function markAsRead(): self
    {
        $this->isRead = true;
        return $this;
    }

    public function markAsUnread(): self
    {
        $this->isRead = false;
        return $this;
    }
}
