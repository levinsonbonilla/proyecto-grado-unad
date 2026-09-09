<?php

namespace App\Tests\Unit\Entity\Configurations\Globals;

use App\ArgumentHandler\LogsArgument;
use App\Entity\Configurations\Globals\Logs;
use PHPUnit\Framework\TestCase;

class LogsTest extends TestCase
{
    private function buildArgument(array $overrides = []): LogsArgument
    {
        return new LogsArgument(array_merge([
            'message' => 'Error de prueba',
            'code'    => 500,
            'line'    => 42,
            'file'    => '/src/Foo.php',
            'trace'   => 'trace...',
            'complete' => 'complete...',
        ], $overrides));
    }

    public function testAddGeneratesIdImmediately(): void
    {

        $log = new Logs();
        $log->add($this->buildArgument());

        $this->assertNotEmpty((string) $log->getId());
    }

    public function testAddActivatesEntity(): void
    {
        $log = new Logs();
        $log->add($this->buildArgument());

        $this->assertTrue($log->isActive());
    }

    public function testGetShortReferenceIsShortAndDoesNotLeakFullUuid(): void
    {

        $log = new Logs();
        $log->add($this->buildArgument());

        $reference = $log->getShortReference();

        $this->assertSame(8, strlen($reference));
        $this->assertNotSame((string) $log->getId(), $reference);
        $this->assertStringContainsString($reference, strtoupper(str_replace('-', '', (string) $log->getId())));
    }

    public function testGetShortReferenceIsStableForSameId(): void
    {
        $log = new Logs();
        $log->add($this->buildArgument());

        $this->assertSame($log->getShortReference(), $log->getShortReference());
    }
}
