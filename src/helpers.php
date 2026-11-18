<?php
declare(strict_types=1);

/** Escape a value for HTML output (text and attributes). */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Format a euro amount, e.g. "28" or "28.5" -> "€28.00". */
function format_price(string|int|float $amount): string
{
    return '€' . number_format((float) $amount, 2, '.', ',');
}

/** A euro amount rounded to whole euros, e.g. "204.50" -> "€205" (for headline figures). */
function format_whole_euros(string|int|float $amount): string
{
    return '€' . number_format(round((float) $amount), 0, '.', ',');
}

/** A share as a whole percentage, e.g. 0.4375 -> "44%"; "–" when there is nothing to show. */
function format_percent(?float $share): string
{
    if ($share === null) {
        return '–';
    }
    return round($share * 100) . '%';
}

/** Send a JSON response and stop. */
function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
