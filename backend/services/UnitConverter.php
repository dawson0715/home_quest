<?php

declare(strict_types=1);

namespace app\services;

/**
 * Quantities in base units: mass in g, volume in ml; pieces, spoons and "q.b."
 * are their own families and never converted (docs/02-architettura.md §6).
 */
final class UnitConverter
{
    private const TO_BASE = [
        'g' => ['g', 1], 'kg' => ['g', 1000],
        'ml' => ['ml', 1], 'l' => ['ml', 1000],
        'pz' => ['pz', 1], 'cucchiaio' => ['cucchiaio', 1], 'cucchiaino' => ['cucchiaino', 1], 'qb' => ['qb', 1],
    ];

    /** Base unit of the family: g, ml, pz, cucchiaio, cucchiaino or qb. */
    public static function baseUnit(string $unit): string
    {
        return self::TO_BASE[$unit][0] ?? $unit;
    }

    public static function toBase(float $quantity, string $unit): float
    {
        return $quantity * (self::TO_BASE[$unit][1] ?? 1);
    }

    /**
     * Readable quantity: 1250 g → [1.25, kg]; 3.4 pz → [4, pz]; qb → [null, qb].
     *
     * @return array{0: float|null, 1: string}
     */
    public static function humanize(?float $baseQuantity, string $baseUnit): array
    {
        if ($baseUnit === 'qb' || $baseQuantity === null) {
            return [null, $baseUnit];
        }
        return match ($baseUnit) {
            'g' => $baseQuantity >= 1000 ? [round($baseQuantity / 1000, 2), 'kg'] : [round($baseQuantity), 'g'],
            'ml' => $baseQuantity >= 1000 ? [round($baseQuantity / 1000, 2), 'l'] : [round($baseQuantity), 'ml'],
            'pz' => [ceil(round($baseQuantity, 2)), 'pz'],
            default => [round($baseQuantity, 2), $baseUnit],
        };
    }
}
