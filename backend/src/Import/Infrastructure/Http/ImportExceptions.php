<?php

declare(strict_types=1);

namespace Ecommerce\Import\Infrastructure\Http;

use Ecommerce\Import\Application\CsvTooLarge;
use Ecommerce\Import\Application\InvalidCsvHeader;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ImportExceptions
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(fn (InvalidCsvHeader $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? self::invalidFile($exception->getMessage())
            : null);
        $exceptions->render(fn (CsvTooLarge $exception, Request $request): ?JsonResponse => $request->is('api/*')
            ? self::invalidFile($exception->getMessage())
            : null);
    }

    private static function invalidFile(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message, 'errors' => ['file' => [$message]]], 422);
    }
}
