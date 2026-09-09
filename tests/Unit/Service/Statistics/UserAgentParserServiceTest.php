<?php

namespace App\Tests\Unit\Service\Statistics;

use App\Service\Statistics\UserAgentParserService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class UserAgentParserServiceTest extends TestCase
{
    private UserAgentParserService $service;

    protected function setUp(): void
    {
        $this->service = new UserAgentParserService(new ArrayAdapter());
    }

    public function testParseReturnsBotForGooglebot(): void
    {
        $ua     = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $result = $this->service->parse($ua);

        $this->assertSame('bot', $result->device);
        $this->assertSame('bot', $result->browser);
        $this->assertSame('bot', $result->os);
    }

    public function testParseReturnsDesktopForChromeOnWindows(): void
    {
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
        $result = $this->service->parse($ua);

        $this->assertSame('desktop', $result->device);
        $this->assertStringContainsStringIgnoringCase('Chrome', $result->browser);
    }

    public function testParseReturnsMobileForAndroidChrome(): void
    {
        $ua     = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
        $result = $this->service->parse($ua);

        $this->assertSame('mobile', $result->device);
    }

    public function testParseReturnsTabletForIpad(): void
    {
        $ua     = 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1';
        $result = $this->service->parse($ua);

        $this->assertSame('tablet', $result->device);
    }

    public function testParseReturnsUnknownForEmptyUserAgent(): void
    {
        $result = $this->service->parse('');

        $this->assertNotSame('bot', $result->device);
    }

    public function testParseReturnsBrowserName(): void
    {
        $ua     = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0';
        $result = $this->service->parse($ua);

        $this->assertStringContainsStringIgnoringCase('Firefox', $result->browser);
    }
}
