<?php
/**
 * Shared module catalog helpers for CLI scripts.
 */

/**
 * Load modules from the canonical modules_corrected.json file.
 *
 * @return array<int, array<string, mixed>>
 */
function load_module_catalog(string $path): array {
    if (!file_exists($path)) {
        throw new RuntimeException("modules_corrected.json not found at {$path}");
    }

    $raw = json_decode((string)file_get_contents($path), true);
    if (!is_array($raw)) {
        throw new RuntimeException("Invalid modules_corrected.json structure");
    }

    return $raw;
}

/**
 * Normalize program list from a module row.
 *
 * @return array<int, string>
 */
function module_programs(array $module): array {
    $programs = [];

    if (isset($module['programs']) && is_array($module['programs'])) {
        $programs = $module['programs'];
    } elseif (!empty($module['program'])) {
        $programs = [$module['program']];
    }

    $programs = array_map(static function ($program): string {
        return strtoupper(trim((string)$program));
    }, $programs);

    $programs = array_values(array_unique(array_filter($programs, static function (string $program): bool {
        return $program !== '';
    })));

    return $programs;
}

/**
 * Split modules into shared / BIT-only / BCS-only buckets.
 *
 * @return array{shared: array<int, array<string, mixed>>, bit_only: array<int, array<string, mixed>>, bcs_only: array<int, array<string, mixed>>}
 */
function split_modules_by_program(array $modules): array {
    $shared = [];
    $bit_only = [];
    $bcs_only = [];

    foreach ($modules as $module) {
        if (!is_array($module)) {
            continue;
        }

        $programs = module_programs($module);
        $has_bit = in_array('BIT', $programs, true);
        $has_bcs = in_array('BCS', $programs, true);

        if ($has_bit && $has_bcs) {
            $shared[] = $module;
        } elseif ($has_bit) {
            $bit_only[] = $module;
        } elseif ($has_bcs) {
            $bcs_only[] = $module;
        }
    }

    return [
        'shared' => $shared,
        'bit_only' => $bit_only,
        'bcs_only' => $bcs_only,
    ];
}

/**
 * Resolve year/semester from a module row.
 *
 * @return array{0:int,1:int}
 */
function resolve_year_semester(array $module): array {
    if (!empty($module['year']) && !empty($module['semester_num'])) {
        return [(int)$module['year'], (int)$module['semester_num']];
    }

    $semester_name = (string)($module['semester'] ?? '');
    $map = [
        'Semester I' => [1, 1],
        'Semester II' => [1, 2],
        'Semester III' => [2, 1],
        'Semester IV' => [2, 2],
        'Semester V' => [3, 1],
        'Semester VI' => [3, 2],
    ];
    if (isset($map[$semester_name])) {
        return $map[$semester_name];
    }

    $roman = strtoupper(trim((string)($module['semester_roman'] ?? '')));
    $roman_map = [
        'I' => [1, 1],
        'II' => [1, 2],
        'III' => [2, 1],
        'IV' => [2, 2],
        'V' => [3, 1],
        'VI' => [3, 2],
    ];
    if (isset($roman_map[$roman])) {
        return $roman_map[$roman];
    }

    return [1, 1];
}
