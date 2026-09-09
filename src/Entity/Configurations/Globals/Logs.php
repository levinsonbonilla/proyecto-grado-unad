<?php

namespace App\Entity\Configurations\Globals;

use App\ArgumentHandler\LogsArgument;
use App\Repository\Configurations\Globals\LogsRepository;
use App\Trait\Entity\ActiveFields;
use App\Trait\Entity\DateFields;
use App\Trait\Entity\IdFields;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Logs
{
    use IdFields {
        initializeUuid as private initializeId;
    }

    use ActiveFields;

    use DateFields;

    #[ORM\Column(nullable: true)]
    private ?int $code = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(nullable: true)]
    private ?int $line = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $file = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $trace = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $complete = null;

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getLine(): ?int
    {
        return $this->line;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function getTrace(): ?string
    {
        return $this->trace;
    }

    public function getComplete(): ?string
    {
        return $this->complete;
    }

    public function add(LogsArgument $argument) : Logs
    {

        $this->initializeId();
        $this->activate();
        $this->code = $argument->getCode();
        $this->message = $argument->getMessage();
        $this->line = $argument->getLine();
        $this->file = $argument->getFile();
        $this->trace = $argument->getTrace();
        $this->complete = $argument->getComplete();

        return $this;
    }

    public function getShortReference(): string
    {
        return strtoupper(substr(str_replace('-', '', (string) $this->getId()), 0, 8));
    }

}
