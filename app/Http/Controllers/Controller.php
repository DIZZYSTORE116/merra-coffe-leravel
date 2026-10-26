<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['error' => $message], $status);
    }

    /** Ambil string dari input (non-string menjadi ''), di-trim dan opsional dipotong. */
    protected function clean(mixed $value, ?int $max = null): string
    {
        $text = is_string($value) ? trim($value) : '';

        return $max === null ? $text : mb_substr($text, 0, $max);
    }
}
