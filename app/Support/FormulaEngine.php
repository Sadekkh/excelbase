<?php

namespace App\Support;

use App\Models\Field;
use App\Models\Row;
use Illuminate\Support\Collection;

class FormulaEngine
{
    public static function evaluate(Row $row, Field $formulaField, iterable $fields): string
    {
        $expr = trim((string) ($formulaField->options['formula'] ?? ''));
        if ($expr === '') {
            return '';
        }

        $map = [];
        foreach ($fields as $field) {
            if ($field->id === $formulaField->id) {
                continue;
            }
            $raw = $row->rawValue($field);
            $map[$field->name] = is_array($raw) ? implode(', ', $raw) : (string) ($raw ?? '');
        }

        $expr = preg_replace_callback('/\{([^}]+)\}/', function ($m) use ($map) {
            return $map[trim($m[1])] ?? '';
        }, $expr) ?? $expr;

        return self::resolve($expr);
    }

    public static function generate(string $prompt, iterable $fields): string
    {
        $list = Collection::make($fields)
            ->filter(fn ($field) => $field instanceof Field && ! in_array($field->type, ['formula', 'ai'], true))
            ->values();
        if ($list->isEmpty()) {
            return '';
        }

        $needle = mb_strtolower($prompt);
        $named = $list->filter(fn (Field $field) => str_contains($needle, mb_strtolower($field->name)));
        $first = $named->first() ?? $list->first();
        $numbers = $list->filter(fn (Field $field) => in_array($field->type, ['number', 'rating'], true))->values();

        if (preg_match('/upper|caps|uppercase/i', $prompt)) {
            return 'UPPER({'.$first->name.'})';
        }
        if (preg_match('/lower|lowercase/i', $prompt)) {
            return 'LOWER({'.$first->name.'})';
        }
        if (preg_match('/\blen\b|length|characters/i', $prompt)) {
            return 'LEN({'.$first->name.'})';
        }
        if (preg_match('/concat|combine|join|merge/i', $prompt)) {
            $pair = $named->count() >= 2 ? $named->take(2)->values() : $list->take(2)->values();
            if ($pair->count() >= 2) {
                return 'CONCAT({'.$pair[0]->name.'}, " ", {'.$pair[1]->name.'})';
            }
        }
        if (preg_match('/percent|commission|10%|0\.1/i', $prompt) && $numbers->isNotEmpty()) {
            return '{'.$numbers->first()->name.'} * 0.1';
        }
        if (preg_match('/\*|times|multipl|product/i', $prompt) && $numbers->count() >= 2) {
            return '{'.$numbers[0]->name.'} * {'.$numbers[1]->name.'}';
        }
        if (preg_match('/\+|plus|sum|add/i', $prompt) && $numbers->count() >= 2) {
            return '{'.$numbers[0]->name.'} + {'.$numbers[1]->name.'}';
        }
        if (preg_match('/if|when|greater|above/i', $prompt) && $numbers->isNotEmpty()) {
            return 'IF({'.$numbers->first()->name.'}>0, "Yes", "No")';
        }

        return 'UPPER({'.$first->name.'})';
    }

    public static function ai(Row $row, Field $aiField, iterable $fields): string
    {
        $sourceId = (int) ($aiField->options['source_field_id'] ?? 0);
        $mode = $aiField->options['mode'] ?? $aiField->options['ai_mode'] ?? 'summarize';
        $parts = [];
        foreach ($fields as $field) {
            if (in_array($field->type, ['formula', 'ai', 'lookup', 'count'], true)) {
                continue;
            }
            if ($sourceId && (int) $field->id !== $sourceId) {
                continue;
            }
            $raw = $row->rawValue($field);
            $text = is_array($raw) ? implode(', ', $raw) : (string) ($raw ?? '');
            if (trim($text) !== '') {
                $parts[] = $field->name.': '.$text;
            }
        }
        $text = trim(implode(' · ', $parts));
        if ($text === '') {
            return '';
        }

        return match ($mode) {
            'extract_email' => self::firstMatch('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text),
            'classify', 'sentiment' => self::classify($text),
            default => self::summarize($text),
        };
    }

    private static function resolve(string $expr): string
    {
        $expr = trim($expr);
        $upper = strtoupper($expr);

        if (preg_match('/^UPPER\((.*)\)$/is', $expr, $m)) {
            return mb_strtoupper(self::resolve($m[1]));
        }
        if (preg_match('/^LOWER\((.*)\)$/is', $expr, $m)) {
            return mb_strtolower(self::resolve($m[1]));
        }
        if (preg_match('/^LEN\((.*)\)$/is', $expr, $m)) {
            return (string) mb_strlen(self::resolve($m[1]));
        }
        if (preg_match('/^CONCAT(?:ENATE)?\((.*)\)$/is', $expr, $m)) {
            return implode('', array_map(fn ($part) => self::resolve($part), self::splitArgs($m[1])));
        }
        if (preg_match('/^IF\((.*)\)$/is', $expr, $m)) {
            $args = self::splitArgs($m[1]);
            if (count($args) < 3) {
                return $expr;
            }

            return self::truthy($args[0]) ? self::resolve($args[1]) : self::resolve($args[2]);
        }

        if (preg_match('/^[\d\s\.\+\-\*\/]+$/', $expr)) {
            try {
                return (string) self::safeMath($expr);
            } catch (\Throwable) {
                return $expr;
            }
        }

        return trim($expr, " \t\n\r\"'");
    }

    /**
     * @return list<string>
     */
    private static function splitArgs(string $inner): array
    {
        $args = [];
        $buf = '';
        $quote = null;
        $depth = 0;
        $len = strlen($inner);
        for ($i = 0; $i < $len; $i++) {
            $ch = $inner[$i];
            if ($quote !== null) {
                if ($ch === $quote) {
                    $quote = null;
                } else {
                    $buf .= $ch;
                }

                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;

                continue;
            }
            if ($ch === '(') {
                $depth++;
                $buf .= $ch;

                continue;
            }
            if ($ch === ')') {
                $depth--;
                $buf .= $ch;

                continue;
            }
            if ($ch === ',' && $depth === 0) {
                $args[] = trim($buf);
                $buf = '';

                continue;
            }
            $buf .= $ch;
        }
        if (trim($buf) !== '') {
            $args[] = trim($buf);
        }

        return $args;
    }

    private static function truthy(string $cond): bool
    {
        $cond = trim($cond);
        if (preg_match('/^(.+?)(>=|<=|!=|==|=|>|<)(.+)$/', $cond, $m)) {
            $left = self::resolve(trim($m[1]));
            $right = self::resolve(trim($m[3]));
            $op = $m[2] === '=' ? '==' : $m[2];
            if (is_numeric($left) && is_numeric($right)) {
                $leftN = 0 + $left;
                $rightN = 0 + $right;

                return match ($op) {
                    '>' => $leftN > $rightN,
                    '<' => $leftN < $rightN,
                    '>=' => $leftN >= $rightN,
                    '<=' => $leftN <= $rightN,
                    '!=' => $leftN != $rightN,
                    default => $leftN == $rightN,
                };
            }

            return match ($op) {
                '!=' => $left !== $right,
                default => $left === $right,
            };
        }

        $value = self::resolve($cond);

        return $value !== '' && $value !== '0' && strcasecmp($value, 'false') !== 0;
    }

    private static function summarize(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if (mb_strlen($text) <= 140) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, 137)).'…';
    }

    private static function classify(string $text): string
    {
        $t = mb_strtolower($text);
        $pos = preg_match_all('/\b(great|love|won|success|good|excellent|happy|closed)\b/', $t);
        $neg = preg_match_all('/\b(bad|lost|churn|block|issue|angry|risk|frozen)\b/', $t);
        if ($pos > $neg) {
            return 'Positive';
        }
        if ($neg > $pos) {
            return 'Negative';
        }

        return 'Neutral';
    }

    private static function firstMatch(string $pattern, string $text): string
    {
        return preg_match($pattern, $text, $m) ? $m[0] : '';
    }

    private static function safeMath(string $expr): float|int
    {
        $expr = preg_replace('/\s+/', '', $expr) ?? '';
        if (! preg_match('/^\d+(\.\d+)?([\+\-\*\/]\d+(\.\d+)?)*$/', $expr)) {
            throw new \RuntimeException('Invalid formula');
        }
        preg_match_all('/\d+(?:\.\d+)?|[\+\-\*\/]/', $expr, $m);
        $tokens = $m[0];
        $value = (float) array_shift($tokens);
        while ($tokens) {
            $op = array_shift($tokens);
            $rhs = (float) array_shift($tokens);
            $value = match ($op) {
                '+' => $value + $rhs,
                '-' => $value - $rhs,
                '*' => $value * $rhs,
                '/' => $rhs == 0.0 ? 0 : $value / $rhs,
                default => $value,
            };
        }

        return $value == (int) $value ? (int) $value : $value;
    }
}
