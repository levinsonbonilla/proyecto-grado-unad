<?php

namespace App\ArgumentHandler;

use App\Entity\Tenants\Domains\Domains;
use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Util\ArrayUtil;

final class UsersDomainsArgument
{
    private Users $user;
    private Domains $domain;
    private array $roles;

    public function __construct(array $data)
    {
        $requireKeys = ["user", "domain", "roles"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new GenericException(
                "ocurrio un error se esperaba: " . json_encode($requireKeys)
                    . " se recibio: " . json_encode($data),
                400
            );
        }

        $this->user = $data["user"];
        $this->domain = $data["domain"];
        $this->roles = $data["roles"];
    }

    public function getUser(): Users
    {
        return $this->user;
    }

    public function getDomain(): Domains
    {
        return $this->domain;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}
