<?php

declare(strict_types=1);

namespace Ecommerce\Import\Infrastructure\Http;

use Ecommerce\Import\Application\ImportProducts;
use Ecommerce\Import\Application\RowResult;
use Ecommerce\Import\Application\RowStatus;
use Illuminate\Http\JsonResponse;

final class ImportProductsController
{
    public function __invoke(ImportProductsRequest $request, ImportProducts $import): JsonResponse
    {
        $report = $import->handle($request->csvPath());

        return new JsonResponse([
            'summary' => [
                'created' => $report->count(RowStatus::Created),
                'updated' => $report->count(RowStatus::Updated),
                'unchanged' => $report->count(RowStatus::Unchanged),
                'failed' => $report->count(RowStatus::Failed),
                'blank_rows' => $report->blankRows,
            ],
            'new_categories' => $report->newCategories,
            'rows' => array_map(fn (RowResult $row): array => [
                'row' => $row->row,
                'sku' => $row->sku,
                'status' => $row->status->value,
                'errors' => $row->errors,
                'note' => $row->note,
            ], $report->rows),
        ]);
    }
}
