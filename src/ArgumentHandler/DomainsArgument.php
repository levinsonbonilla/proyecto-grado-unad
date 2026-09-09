<?php

namespace App\ArgumentHandler;

use App\Entity\Tenants\Tenants;
use App\Util\ArrayUtil;

final class DomainsArgument
{
    private readonly string $domain;
    private readonly ?string $name;
    private readonly string $logo;
    private readonly string $notificationEmail;
    private readonly string $supportEmail;
    private readonly ?string $facebookUrl;
    private readonly ?string $instagramUrl;
    private readonly ?string $pinterestUrl;

    public function __construct(
        array $data,
        private readonly Tenants $tenant
    ) {
        $requireKeys = ["domain", "logo", "notificationEmail", "supportEmail"];
        if (!ArrayUtil::validateKeys($requireKeys, $data)) {
            throw new \Exception(
                "ocurrio un error se esperaba: ". json_encode($requireKeys)
                ." se recibio: " . json_encode($data),
                400
            );
        }

        $this->domain = $data["domain"];

        $this->name = ArrayUtil::validateExistKey($data, "name") ?: null;
        $this->logo = $data["logo"];
        $this->notificationEmail = $data["notificationEmail"];
        $this->supportEmail = $data["supportEmail"];

        $this->facebookUrl = ArrayUtil::validateExistKey($data, "facebookUrl") ?: null;
        $this->instagramUrl = ArrayUtil::validateExistKey($data, "instagramUrl") ?: null;
        $this->pinterestUrl = ArrayUtil::validateExistKey($data, "pinterestUrl") ?: null;
    }

    public function getTenant(): Tenants
    {
        return $this->tenant;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getlogo(): string
    {
        return $this->logo;
    }

    public function getNotificationEmail(): string
    {
        return $this->notificationEmail;
    }

    public function getSupportEmail(): string
    {
        return $this->supportEmail;
    }

    public function getFacebookUrl(): ?string
    {
        return $this->facebookUrl;
    }

    public function getInstagramUrl(): ?string
    {
        return $this->instagramUrl;
    }

    public function getPinterestUrl(): ?string
    {
        return $this->pinterestUrl;
    }
}
