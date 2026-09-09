<?php

namespace App\Tests\Unit\Handler\Configuration;

use App\Handler\Configuration\ListDataTableHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ListDataTableHandlerTest extends TestCase
{
    private function buildHandler(array $query): ListDataTableHandler
    {
        $request = Request::create('/list', 'GET', $query);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        return new ListDataTableHandler($requestStack);
    }

    public function testGetStartReadsTheStartParamSentByDataTables(): void
    {

        $handler = $this->buildHandler(['start' => '30', 'length' => '10', 'draw' => '4']);

        $this->assertSame('30', $handler->getStart());
        $this->assertSame('10', $handler->getLength());
        $this->assertSame('4', $handler->getDraw());
    }

    public function testGetStartDefaultsToZeroWithoutQueryParams(): void
    {

        $handler = $this->buildHandler([]);

        $this->assertSame('0', $handler->getStart());
    }
}
