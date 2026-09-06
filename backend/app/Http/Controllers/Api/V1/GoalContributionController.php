<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Planning\Actions\AddGoalContributionAction;
use App\Domain\Planning\Actions\DeleteGoalContributionAction;
use App\Domain\Planning\Models\Goal;
use App\Domain\Planning\Models\GoalContribution;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\GoalContributionRequest;
use App\Http\Resources\GoalContributionResource;
use App\Http\Resources\GoalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoalContributionController extends Controller
{
    public function index(Goal $goal): AnonymousResourceCollection
    {
        return GoalContributionResource::collection(
            $goal->contributions()->orderByDesc('contributed_at')->orderByDesc('id')->get(),
        );
    }

    public function store(GoalContributionRequest $request, Goal $goal, AddGoalContributionAction $action): JsonResponse
    {
        $action->handle($goal, $request->toData());

        return (new GoalResource($goal->refresh()))->response()->setStatusCode(201);
    }

    public function destroy(Goal $goal, GoalContribution $contribution, DeleteGoalContributionAction $action): JsonResponse
    {
        abort_if($contribution->goal_id !== $goal->id, 404);

        $action->handle($contribution);

        return response()->json(['message' => 'Aporte desfeito.']);
    }
}
