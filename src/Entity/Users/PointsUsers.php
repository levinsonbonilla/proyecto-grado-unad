<?php

namespace App\Entity\Users;

use App\Entity\Products\Orders\Orders;
use App\Repository\Users\PointsUsersRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;

#[ORM\Entity(repositoryClass: PointsUsersRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PointsUsers
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Orders $orders;

    #[ORM\Column(length: 50)]
    private string $points;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Users $user;

    #[ORM\Column]
    private bool $isWon;

    public function getOrders(): Orders
    {
        return $this->orders;
    }

    public function getPoints(): string
    {
        return $this->points;
    }

    public function getUser(): Users
    {
        return $this->user;
    }

    public function isIsWon(): bool
    {
        return $this->isWon;
    }

    public function add(Orders $orders, Users $user, string $points, bool $isWon): self
    {
        $this->activate();
        $this->orders = $orders;
        $this->user   = $user;
        $this->points = $points;
        $this->isWon  = $isWon;
        return $this;
    }
}
