<?php

namespace App\Services\Rps;

use Illuminate\Validation\ValidationException;

class AiJsonRepair
{
    public static function decode(string $text, string $provider = 'AI'): array
    {
        $candidate = trim($text);

        // Remove common markdown fences.
        $candidate = preg_replace('/^\s*```(?:json)?\s*/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s*```\s*$/', '', $candidate) ?? $candidate;
        $candidate = trim($candidate);

        // Some models prepend/append prose despite JSON mode.
        $first = strpos($candidate, '{');
        $last = strrpos($candidate, '}');

        if ($first !== false && $last !== false && $last > $first) {
            $candidate = substr($candidate, $first, $last - $first + 1);
        }

        try {
            $decoded = json_decode(
                $candidate,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (\JsonException) {
            // Continue to local repair.
        }

        $repaired = self::escapeControlCharactersInsideStrings($candidate);

        // Remove trailing commas before object/array closing tokens.
        $repaired = preg_replace(
            '/,\s*([}\]])/',
            '$1',
            $repaired
        ) ?? $repaired;

        try {
            $decoded = json_decode(
                $repaired,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (is_array($decoded)) {
                return $decoded;
            }
        } catch (\JsonException $error) {
            throw ValidationException::withMessages([
                'ai' => "{$provider}: output JSON belum dapat dipulihkan: "
                    .$error->getMessage(),
            ]);
        }

        throw ValidationException::withMessages([
            'ai' => "{$provider}: output AI bukan objek JSON yang dapat diproses.",
        ]);
    }

    private static function escapeControlCharactersInsideStrings(
        string $json
    ): string {
        $out = '';
        $inString = false;
        $escaped = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            $ord = ord($char);

            if ($escaped) {
                $out .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\' && $inString) {
                $out .= $char;
                $escaped = true;
                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                $out .= $char;
                continue;
            }

            if ($inString && $ord < 32) {
                $out .= match ($char) {
                    "\n" => '\\n',
                    "\r" => '\\r',
                    "\t" => '\\t',
                    default => ' ',
                };
                continue;
            }

            $out .= $char;
        }

        return $out;
    }
}
