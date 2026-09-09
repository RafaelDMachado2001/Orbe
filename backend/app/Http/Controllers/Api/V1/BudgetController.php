<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Planning\Actions\CreateBudgetAction;
use App\Domain\Planning\Actions\DeleteBudgetAction;
use App\Domain\Planning\Actions\UpdateBudgetAction;
use App\Domain\Planning\Models\Budget;
use App\Domain\Planning\Queries\BudgetStatusQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\BudgetIndexRequest;
use App\Http\Requests\Planning\StoreBudgetRequest;
use App\Http\Requests\Planning\UpdateBudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\BudgetStatusResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BudgetController extends Controller
{
    public function index(BudgetIndexRequest $r, BudgetStatusQuery $q): AnonymousResourceCollection
    {
        return BudgetStatusResource::collection($q->handle($r->user()->id, CarbonImmutable::createFromFormat('Y-m-d', $r->month().'-01')));
    }

    public function store(StoreBudgetRequest $r, CreateBudgetAction $a): JsonResponse
    {
        return (new BudgetResource($a->handle($r->user(), $r->toData())))->response()->setStatusCode(201);
    }

    public function update(UpdateBudgetRequest $r, Budget $budget, UpdateBudgetAction $a): BudgetResource
    {
        return new BudgetResource($a->handle($budget, $r->toData()));
    }

    public function destroy(Budget $budget, DeleteBudgetAction $a): JsonResponse
    {
        $a->handle($budget);

        return response()->json(['message' => 'Orçamento excluído.']);
    }
}
