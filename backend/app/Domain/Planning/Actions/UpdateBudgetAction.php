<?php
declare(strict_types=1);
namespace App\Domain\Planning\Actions;
use App\Domain\Planning\DTOs\BudgetData;
use App\Domain\Planning\Models\Budget;
final class UpdateBudgetAction { public function handle(Budget $budget, BudgetData $data): Budget { $budget->update(['category_id'=>$data->categoryId,'reference_month'=>$data->referenceMonth.'-01','limit_amount'=>$data->limitAmount]); return $budget->refresh(); } }
