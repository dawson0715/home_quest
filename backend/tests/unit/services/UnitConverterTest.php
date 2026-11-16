<?php

declare(strict_types=1);

namespace tests\unit\services;

use app\services\UnitConverter;
use Codeception\Test\Unit;

final class UnitConverterTest extends Unit
{
    public function testBaseUnitsAndConversion(): void
    {
        $this->assertSame('g', UnitConverter::baseUnit('kg'));
        $this->assertSame('ml', UnitConverter::baseUnit('l'));
        $this->assertSame('pz', UnitConverter::baseUnit('pz'));
        $this->assertSame(1500.0, UnitConverter::toBase(1.5, 'kg'));
        $this->assertSame(250.0, UnitConverter::toBase(0.25, 'l'));
        $this->assertSame(3.0, UnitConverter::toBase(3, 'cucchiaio'));
    }

    public function testHumanize(): void
    {
        $this->assertSame([1.25, 'kg'], UnitConverter::humanize(1250, 'g'));
        $this->assertSame([800.0, 'g'], UnitConverter::humanize(799.6, 'g'));
        $this->assertSame([1.5, 'l'], UnitConverter::humanize(1500, 'ml'));
        $this->assertSame([4.0, 'pz'], UnitConverter::humanize(3.4, 'pz'));
        $this->assertSame([3.0, 'pz'], UnitConverter::humanize(3.0000001, 'pz'));
        $this->assertSame([null, 'qb'], UnitConverter::humanize(null, 'qb'));
    }
}
