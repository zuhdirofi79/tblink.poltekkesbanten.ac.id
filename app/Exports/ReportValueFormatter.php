<?php

namespace App\Exports;

use Carbon\Carbon;
use Throwable;

class ReportValueFormatter
{
    public static function timezone(): string
    {
        return (string) config('app.timezone', 'Asia/Jakarta');
    }

    public static function date(mixed $value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->timezone(self::timezone())->format('d/m/Y');
        } catch (Throwable) {
            return '';
        }
    }

    public static function ageYears(mixed $birthDate): string
    {
        if (! $birthDate) {
            return '';
        }

        try {
            $years = (int) round(abs(
                Carbon::parse($birthDate)->timezone(self::timezone())->diffInYears(now()->timezone(self::timezone()))
            ));

            return $years.' Tahun';
        } catch (Throwable) {
            return '';
        }
    }

    public static function durationMonths(mixed $from): string
    {
        if (! $from) {
            return '';
        }

        try {
            $months = (int) round(abs(
                Carbon::parse($from)->timezone(self::timezone())->diffInMonths(now()->timezone(self::timezone()))
            ));

            return $months.' Bulan';
        } catch (Throwable) {
            return '';
        }
    }

    public static function adherencePercent(array $kepatuhan): int|float
    {
        $percent = round((float) ($kepatuhan['percent'] ?? 0), 2);

        if (abs($percent - (int) $percent) < 0.001) {
            return (int) $percent;
        }

        return $percent;
    }

    public static function phone(?object $user): string
    {
        if (! $user) {
            return '';
        }

        $phone = trim((string) ($user->no_hp ?? ''));
        if ($phone !== '') {
            return $phone;
        }

        return trim((string) ($user->username ?? ''));
    }
}
