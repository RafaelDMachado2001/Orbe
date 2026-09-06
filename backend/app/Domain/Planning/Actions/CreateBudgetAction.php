<?php
declare(strict_types=1);
namespace App\Domain\Planning\Actions;
use App\Domain\Planning\DTOs\BudgetData;
use App\Domain\Planning\Models\Budget;
use App\Models\User;
final class CreateBudgetAction { public function handle(User $user, BudgetData $data): Budget { return Budget::query()->create(['user_id'=>$user->id,'category_id'=>$data->categoryId,'reference_month'=>$data->referenceMonth.'-01','limit_amount'=>$data->limitAmount]); } }
