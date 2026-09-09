<?php

namespace App\Tests\Unit\Service\Products;

use App\Service\Products\VariantLabelFormatterService;
use PHPUnit\Framework\TestCase;

class VariantLabelFormatterServiceTest extends TestCase
{
    private VariantLabelFormatterService $service;

    protected function setUp(): void
    {
        $this->service = new VariantLabelFormatterService();
    }

    public function testFormatWithColorAndSizeReturnsBoth(): void
    {
        $result = $this->service->format('Negro', 'M');

        $this->assertSame(' (Negro, M)', $result);
    }

    public function testFormatWithOnlyColorReturnsColorOnly(): void
    {
        $result = $this->service->format('Negro', null);

        $this->assertSame(' (Negro)', $result);
    }

    public function testFormatWithOnlySizeReturnsSizeOnly(): void
    {
        $result = $this->service->format(null, 'M');

        $this->assertSame(' (M)', $result);
    }

    public function testFormatWithNeitherReturnsEmptyString(): void
    {
        $result = $this->service->format(null, null);

        $this->assertSame('', $result);
    }

    public function testFormatWithEmptyStringsReturnsEmptyString(): void
    {
        $result = $this->service->format('', '');

        $this->assertSame('', $result);
    }
}
