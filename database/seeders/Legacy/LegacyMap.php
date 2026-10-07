<?php

namespace Database\Seeders\Legacy;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * v1 → v2 code mappings and value clean-up (legacy seed spec §3, docs/11 §4).
 */
final class LegacyMap
{
    /**
     * tbl_post id → designation code.
     */
    public const POSTS = [
        1 => 'MD', 2 => 'CEO', 3 => 'ED', 4 => 'CHAIRMAN', 5 => 'HEAD_DESIGN', 6 => 'HEAD_PROJECT_OPS', 7 => 'HEAD_ACCOUNTS',
        8 => 'HEAD_HR_ADMIN', 9 => 'HEAD_SUPPLY_CHAIN', 10 => 'HEAD_MKT_SALES', 11 => 'STRUCTURAL_ENGINEER', 12 => 'HEAD_CUSTOMER_REL',
        13 => 'PROJECT_ENGINEER', 14 => 'ARCHITECT', 15 => 'JR_PROJECT_ENGINEER', 16 => 'DEPUTY_PROJECT_ENGINEER',
        17 => 'TECH_SUPPORT_ENGINEER', 18 => 'HEAD_LOGISTIC',
    ];

    /**
     * tbl_department id → department code.
     */
    public const DEPARTMENTS = [
        1 => 'DESIGN', 2 => 'PROJECT_OPS', 3 => 'MKT_SALES', 4 => 'ACCOUNTS', 5 => 'HR_ADMIN', 6 => 'CUSTOMER_REL',
        7 => 'SUPPLY_CHAIN', 8 => 'LOGISTIC',
    ];

    /**
     * tbl_software id → service code. Internal work types and test rows have no service.
     */
    public const SERVICES = [
        1 => 'BD', 2 => 'BDRA', 3 => 'INT-DESIGN', 6 => 'ENGG', 12 => 'RENOVATION', 13 => 'CETP', 14 => 'INT-EXT',
        15 => 'INT-WORK', 16 => 'BLE', 17 => 'UPS', 18 => 'DSW', 19 => 'SOIL', 21 => 'ESTIMATE',
    ];

    /**
     * Role for a v1 user of type `u`, by the employee's department code (spec L6).
     */
    public const USER_ROLES = [
        'DESIGN' => 'engineer', 'PROJECT_OPS' => 'engineer', 'SUPPLY_CHAIN' => 'engineer', 'LOGISTIC' => 'engineer',
        'MKT_SALES' => 'sales_executive', 'CUSTOMER_REL' => 'sales_executive', 'ACCOUNTS' => 'accountant', 'HR_ADMIN' => 'hr_admin',
    ];

    /**
     * Client code prefixes → business line code, most specific first.
     */
    private const BUSINESS_LINES = [
        'SOC-CON-BLE' => 'CON-BLE', 'SOC-CON-UPS' => 'CON-UPS', 'SOC-CON' => 'CON', 'SOC-BD&RA' => 'BDRA', 'SOC-BD' => 'BD',
        'SOC-CETP' => 'CETP', 'SOC-CTEP' => 'CETP', 'CETP-' => 'CETP', 'SOC-EBC' => 'EBC', 'SOC-AMZ' => 'AMZ', 'SOC-TSE' => 'TSE',
        'SOC-AGENT' => 'AGENT', 'SOC-DSW' => 'DSW',
    ];

    /**
     * tbl_type id → [project type, default business line, task type] (legacy seed spec §7.1).
     */
    public const TYPES = [
        1 => ['RESIDENTIAL', 'BD', 'DESIGN'], 2 => ['RESIDENTIAL', 'BDRA', 'APPROVAL_FILE'], 3 => ['INTERIOR', 'INT', 'DESIGN'],
        4 => ['RENOVATION', 'CON', 'OTHER'], 5 => ['CONSULTANCY', 'CON', 'STRUCTURAL_CALC'], 6 => ['INTERNAL', 'MGT', 'ACCOUNTS'],
        7 => ['INTERNAL', 'MGT', 'LOGISTIC'], 8 => ['INTERNAL', 'MGT', 'INVENTORY'], 9 => ['INTERNAL', 'MGT', 'SUPPLY_CHAIN'],
        10 => ['INTERNAL', 'MGT', 'HR_ADMIN'], 11 => ['INTERNAL', 'MS', 'CUSTOMER_RELATION'], 12 => ['INTERNAL', 'MS', 'OTHER'],
        13 => ['INTERIOR', 'INT', 'OTHER'], 14 => ['INTERIOR', 'INT', 'OTHER'], 15 => ['ESTIMATE', 'CON-BLE', 'ESTIMATE'],
        16 => ['ESTIMATE', 'CON-UPS', 'ESTIMATE'], 18 => ['TRAINING', 'CETP', 'OTHER'], 19 => ['SURVEY', 'DSW', 'SURVEY'],
        20 => ['SURVEY', 'CON', 'SURVEY'], 21 => ['TRAINING', 'CETP', 'OTHER'], 22 => ['ESTIMATE', 'CON', 'ESTIMATE'],
    ];

    /**
     * Fallback for unknown types.
     */
    public const DEFAULT_TYPE = ['RESIDENTIAL', 'BD', 'OTHER'];

    /**
     * Project number prefixes only projects use, beyond the client code prefixes.
     */
    private const PROJECT_LINES = ['SOC-MANAGEMENT' => 'MGT', 'SOC-M&S' => 'MS', 'ATP-' => 'CETP'];

    private const SOURCES = ['L' => 'LEAFLET', 'FF' => 'F2F', 'F' => 'REFERENCE', 'FB' => 'FACEBOOK'];

    private const LEVELS = ['entry' => 'ENTRY', 'middle' => 'MID', 'top' => 'TOP'];

    public static function businessLineFor(?string $clientCode): ?string
    {
        $code = Str::upper((string) preg_replace('/\s+/', '', (string) $clientCode));

        foreach (self::BUSINESS_LINES as $prefix => $line) {
            if (str_starts_with($code, $prefix)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Business line of a v1 project number: the client prefixes plus the project-only ones.
     */
    public static function projectBusinessLineFor(string $projectNumber): ?string
    {
        $code = Str::upper((string) preg_replace('/\s+/', '', $projectNumber));

        foreach (self::PROJECT_LINES as $prefix => $line) {
            if (str_starts_with($code, $prefix)) {
                return $line;
            }
        }

        return self::businessLineFor($projectNumber);
    }

    public static function sourceFor(?string $code): string
    {
        return self::SOURCES[Str::upper(trim((string) $code))] ?? 'OTHER';
    }

    public static function levelFor(?string $level): ?string
    {
        return self::LEVELS[Str::lower(trim((string) $level))] ?? null;
    }

    /**
     * A v1 date or datetime as Y-m-d, or null. Zero dates are dropped and two-digit years
     * stored as 00YY (e.g. 0026-08-31) are read as 20YY.
     */
    public static function date(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        $value = (string) preg_replace('/^00(\d{2})-/', '20$1-', $value);

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        return $date->year >= 1900 ? $date->toDateString() : null;
    }

    /**
     * A v1 datetime, or null (same rules as date()).
     */
    public static function dateTime(?string $value): ?Carbon
    {
        $date = self::date($value);

        if ($date === null) {
            return null;
        }

        $time = preg_match('/\d{2}:\d{2}(:\d{2})?$/', trim((string) $value), $match) === 1 ? $match[0] : '00:00:00';

        return Carbon::parse($date.' '.$time);
    }
}
