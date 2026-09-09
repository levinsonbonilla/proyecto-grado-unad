<?php

namespace App\ArgumentHandler;

use App\Entity\Configurations\Globals\Countries;
use App\Util\ArrayUtil;

final class LogsArgument
{
    private ?int $code = null;
    private string $message;
    private ?int $line = null;
    private ?string $file = null;
    private ?string $trace = null;
    private ?string $complete = null;

    public function __construct(
        array $data
    ) {
        $requireKeys = ["message"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: ". json_encode($requireKeys) 
                ." se recibio: " . json_encode($data),
                400
            );
        }

        $this->code =  (int) ArrayUtil::validateExistKey($data, "code");
        $this->message = $data["message"]; 
        $this->line =  (int) ArrayUtil::validateExistKey($data, "line");
        $this->file =  ArrayUtil::validateExistKey($data, "file");
        $this->trace =  ArrayUtil::validateExistKey($data, "trace");
        $this->complete =  ArrayUtil::validateExistKey($data, "complete");

        ArrayUtil::validateExistKey($data, "nit");
    }

    public function getCode(): ?int
    {
        return $this->code;
    }

    public function getMessage(): string
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
}
