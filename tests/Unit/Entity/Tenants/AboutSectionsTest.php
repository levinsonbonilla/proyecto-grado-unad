<?php

namespace App\Tests\Unit\Entity\Tenants;

use App\ArgumentHandler\AboutSectionsArgument;
use App\Entity\Tenants\Domains\Domains;
use App\Entity\Tenants\Others\AboutSections;
use PHPUnit\Framework\TestCase;

class AboutSectionsTest extends TestCase
{
    private function buildSection(array $overrides = []): AboutSections
    {
        $data = array_merge([
            'title' => 'Nuestra historia',
            'text' => 'Texto de prueba',
            'image' => 'https://cdn.example.com/about.jpg',
            'position' => 2,
        ], $overrides);

        return (new AboutSections())->add(new AboutSectionsArgument($data, new Domains()));
    }

    public function testAddSetsAllFieldsAndActivates(): void
    {
        $section = $this->buildSection();

        $this->assertSame('Nuestra historia', $section->getTitle());
        $this->assertSame('Texto de prueba', $section->getText());
        $this->assertSame('https://cdn.example.com/about.jpg', $section->getImage());
        $this->assertSame(2, $section->getPosition());
        $this->assertTrue($section->isActive());
    }

    public function testAddWithoutImageLeavesItNull(): void
    {
        $section = $this->buildSection(['image' => null]);

        $this->assertNull($section->getImage());
    }

    public function testEditUpdatesTitleTextAndPosition(): void
    {
        $section = $this->buildSection();
        $section->edit('Nuevo título', 'Nuevo texto', 5);

        $this->assertSame('Nuevo título', $section->getTitle());
        $this->assertSame('Nuevo texto', $section->getText());
        $this->assertSame(5, $section->getPosition());
    }

    public function testEditWithoutPositionKeepsCurrentPosition(): void
    {
        $section = $this->buildSection();
        $section->edit('Otro título', 'Otro texto');

        $this->assertSame(2, $section->getPosition());
    }

    public function testSetImageReplacesAndClearsImage(): void
    {
        $section = $this->buildSection();
        $section->setImage('https://cdn.example.com/otra.jpg');
        $this->assertSame('https://cdn.example.com/otra.jpg', $section->getImage());

        $section->setImage(null);
        $this->assertNull($section->getImage());
    }

    public function testDeactivateAndActivateToggleStatus(): void
    {
        $section = $this->buildSection();
        $section->deactivate();
        $this->assertFalse($section->isActive());

        $section->activate();
        $this->assertTrue($section->isActive());
    }
}
