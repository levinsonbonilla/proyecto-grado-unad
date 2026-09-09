<?php

namespace App\Service\Statistics;

use App\Interface\Service\Statistics\UserAgentParserInterface;
use DeviceDetector\Cache\PSR6Bridge;
use DeviceDetector\DeviceDetector;
use Symfony\Contracts\Cache\CacheInterface;

final readonly class UserAgentParserService implements UserAgentParserInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function parse(string $userAgent): UserAgentData
    {
        $dd = new DeviceDetector($userAgent);
        $dd->setCache(new PSR6Bridge($this->cache));
        $dd->parse();

        if ($dd->isBot()) {
            return new UserAgentData(device: 'bot', browser: 'bot', os: 'bot');
        }

        $clientInfo = $dd->getClient();
        $osInfo     = $dd->getOs();

        return new UserAgentData(
            device:  $this->resolveDevice($dd),
            browser: is_array($clientInfo) ? ($clientInfo['name'] ?? 'Unknown') : 'Unknown',
            os:      is_array($osInfo)     ? ($osInfo['name']   ?? 'Unknown') : 'Unknown',
        );
    }

    private function resolveDevice(DeviceDetector $dd): string
    {
        if ($dd->isTablet())  return 'tablet';
        if ($dd->isMobile())  return 'mobile';
        if ($dd->isDesktop()) return 'desktop';
        return 'other';
    }
}
