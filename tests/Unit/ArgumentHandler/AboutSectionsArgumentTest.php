<?php

namespace App\Tests\Unit\ArgumentHandler;

use App\ArgumentHandler\AboutSectionsArgument;
use App\Entity\Tenants\Domains\Domains;
use PHPUnit\Framework\TestCase;

class AboutSectionsArgumentTest extends TestCase
{
    public function testConstructorWithAllFields(): void
    {
        $domain = new Domains();
        $argument = new AboutSectionsArgument([
            'title' => 'Misión',
            'text' => 'Nuestro texto',
            'image' => 'https://cdn.example.com/a.jpg',
            'position' => '3',
        ], $domain);

        $this->assertSame('Misión', $argument->getValue('title'));
        $this->assertSame('Nuestro texto', $argument->getValue('text'));
        $this->assertSame('https://cdn.example.com/a.jpg', $argument->getValue('image'));
        $this->assertSame(3, $argument->getValue('position'));
        $this->assertSame($domain, $argument->getValue('domain'));
    }

    public function testConstructorTrimsTitleAndText(): void
    {
        $argument = new AboutSectionsArgument(['title' => '  Hola  ', 'text' => "\n Texto \n"], new Domains());

        $this->assertSame('Hola', $argument->getValue('title'));
        $this->assertSame('Texto', $argument->getValue('text'));
    }

    public function testMissingPositionUsesDefaultPosition(): void
    {
        $argument = new AboutSectionsArgument(['title' => 'A', 'text' => 'B'], new Domains(), 7);

        $this->assertSame(7, $argument->getValue('position'));
    }

    public function testEmptyPositionUsesDefaultPosition(): void
    {
        $argument = new AboutSectionsArgument(['title' => 'A', 'text' => 'B', 'position' => ''], new Domains(), 4);

        $this->assertSame(4, $argument->getValue('position'));
    }

    public function testExplicitZeroPositionIsRespected(): void
    {
        $argument = new AboutSectionsArgument(['title' => 'A', 'text' => 'B', 'position' => '0'], new Domains(), 4);

        $this->assertSame(0, $argument->getValue('position'));
    }

    public function testMissingImageIsNull(): void
    {
        $argument = new AboutSectionsArgument(['title' => 'A', 'text' => 'B'], new Domains());

        $this->assertNull($argument->getValue('image'));
    }

    public function testConstructorThrowsWhenTitleMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        new AboutSectionsArgument(['text' => 'Solo texto'], new Domains());
    }

    public function testConstructorThrowsWhenTextMissing(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionCode(400);

        new AboutSectionsArgument(['title' => 'Solo título'], new Domains());
    }
}
