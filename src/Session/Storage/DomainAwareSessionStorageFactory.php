<?php

namespace App\Session\Storage;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final class DomainAwareSessionStorageFactory implements SessionStorageFactoryInterface
{
    private const COOKIE_NAME_PREFIX = 'THSESS';

    public function __construct(
        private readonly SessionStorageFactoryInterface $decorated,
        private readonly string $sessionCookieDomain,
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        $storage = $this->decorated->createStorage($request);

        if ($this->sessionCookieDomain === '' && $request !== null) {
            $storage->setName($this->resolveSessionName($request));
        }

        return $storage;
    }

    private function resolveSessionName(Request $request): string
    {

        return self::COOKIE_NAME_PREFIX . strtoupper(substr(hash('crc32b', $request->getHttpHost()), 0, 8));
    }
}
