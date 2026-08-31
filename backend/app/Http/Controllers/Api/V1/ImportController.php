<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Import\Actions\CommitStatementImportAction;
use App\Domain\Import\Actions\PreviewStatementImportAction;
use App\Domain\Import\Actions\UndoStatementImportAction;
use App\Domain\Import\Models\ImportBatch;
use App\Domain\Import\Queries\ImportHistoryQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Imports\PreviewImportRequest;
use App\Http\Requests\Imports\StoreImportRequest;
use App\Http\Resources\ImportBatchResource;
use App\Http\Resources\ImportPreviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImportController extends Controller
{
    public function index(Request $request, ImportHistoryQuery $query): AnonymousResourceCollection
    {
        return ImportBatchResource::collection($query->handle($request->user()->id));
    }

    /** Le o arquivo e devolve o que ele traz, sem gravar nada. */
    public function preview(
        PreviewImportRequest $request,
        PreviewStatementImportAction $action,
    ): ImportPreviewResource {
        return new ImportPreviewResource($action->handle($request->toSource()));
    }

    public function store(StoreImportRequest $request, CommitStatementImportAction $action): JsonResponse
    {
        $batch = $action->handle($request->toData());

        return (new ImportBatchResource($batch->load(['account:id,nickname', 'creditCard:id,nickname'])->loadCount('transactions')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(ImportBatch $batch, UndoStatementImportAction $action): JsonResponse
    {
        $action->handle($batch);

        return response()->json(['message' => 'Importação desfeita.']);
    }
}
