<?php

declare(strict_types=1);

namespace Bareapi\Tests\Unit\Service;

use Bareapi\Service\FilterParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class FilterParserTest extends TestCase
{
    public function testParsesFiltersOrderingLimitAndOffset(): void
    {
        $parser = new FilterParser();
        $request = Request::create('/api/v1/repository/tags?creator.name=Jan&name%5Border%5D=desc&limit=10&offset=5');

        $query = $parser->parse($request);

        $this->assertSame([
            'creator.name' => 'Jan',
        ], $query->filters());
        $this->assertSame('name', $query->orderBy());
        $this->assertSame('desc', $query->orderDirection());
        $this->assertSame(10, $query->limit());
        $this->assertSame(5, $query->offset());
    }

    public function testRejectsInvalidLimit(): void
    {
        $parser = new FilterParser();
        $request = Request::create('/api/v1/repository/tags?limit=bad');

        $this->expectException(\InvalidArgumentException::class);
        $parser->parse($request);
    }

    public function testDecodesPlusAsSpaceInFormEncodedQueryStrings(): void
    {
        $parser = new FilterParser();
        $request = Request::create('/api/v1/repository/tags?name=Foo+Bar');

        $query = $parser->parse($request);

        $this->assertSame([
            'name' => 'Foo Bar',
        ], $query->filters());
    }
}
