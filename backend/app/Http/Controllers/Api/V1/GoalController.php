<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Planning\Actions\ArchiveGoalAction;
use App\Domain\Planning\Actions\CreateGoalAction;
use App\Domain\Planning\Actions\DeleteGoalAction;
use App\Domain\Planning\Actions\UpdateGoalAction;
use App\Domain\Planning\Models\Goal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\ArchiveGoalRequest;
use App\Http\Requests\Planning\StoreGoalRequest;
use App\Http\Requests\Planning\UpdateGoalRequest;
use App\Http\Resources\GoalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoalController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return GoalResource::collection(Goal::query()->orderBy('is_archived')->orderBy('deadline')->get());
    }

    public function store(StoreGoalRequest $r, CreateGoalAction $a): JsonResponse
    {
        return (new GoalResource($a->handle($r->user(), $r->toData())))->response()->setStatusCode(201);
    }

    public function update(UpdateGoalRequest $r, Goal $goal, UpdateGoalAction $a): GoalResource
    {
        return new GoalResource($a->handle($goal, $r->toData()));
    }

    public function archive(ArchiveGoalRequest $r, Goal $goal, ArchiveGoalAction $a): GoalResource
    {
        return new GoalResource($a->handle($goal, $r->isArchived()));
    }

    public function destroy(Goal $goal, DeleteGoalAction $a): JsonResponse
    {
        $a->handle($goal);

        return response()->json(['message' => 'Meta excluída.']);
    }
}
