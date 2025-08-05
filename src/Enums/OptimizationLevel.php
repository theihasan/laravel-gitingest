<?php

declare(strict_types=1);

namespace Ihasan\LaravelGitingest\Enums;

enum OptimizationLevel: string
{
    case NONE = 'none';
    case BASIC = 'basic';
    case MEDIUM = 'medium';
    case AGGRESSIVE = 'aggressive';

    public function getCompressionRatio(): float
    {
        return match ($this) {
            self::NONE => 1.0,
            self::BASIC => 0.9,
            self::MEDIUM => 0.7,
            self::AGGRESSIVE => 0.5,
        };
    }

    public function shouldRemoveComments(): bool
    {
        return match ($this) {
            self::NONE, self::BASIC => false,
            self::MEDIUM, self::AGGRESSIVE => true,
        };
    }

    public function shouldRemoveEmptyLines(): bool
    {
        return match ($this) {
            self::NONE => false,
            self::BASIC, self::MEDIUM, self::AGGRESSIVE => true,
        };
    }

    public function shouldMinifyContent(): bool
    {
        return match ($this) {
            self::NONE, self::BASIC => false,
            self::MEDIUM, self::AGGRESSIVE => true,
        };
    }

    public function getDisplayName(): string
    {
        return match ($this) {
            self::NONE => 'No Optimization',
            self::BASIC => 'Basic Optimization',
            self::MEDIUM => 'Medium Optimization',
            self::AGGRESSIVE => 'Aggressive Optimization',
        };
    }
}
