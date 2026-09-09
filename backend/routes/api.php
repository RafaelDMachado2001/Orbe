<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AccountOptionsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CardOptionsController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CreditCardController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ForecastController;
use App\Http\Controllers\Api\V1\GoalContributionController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\RecurrenceController;
use App\Http\Controllers\Api\V1\RecurrenceOptionsController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionOptionsController;
use App\Http\Controllers\Api\V1\TransactionStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', static fn (): array => [
        'status' => 'ok',
        'service' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);

    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:6,1');

        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:6,1');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard', DashboardController::class);
        Route::get('forecast', ForecastController::class);

        Route::get('reports/annual', [ReportController::class, 'annual']);
        Route::get('reports/annual/export', [ReportController::class, 'exportAnnual']);

        // "options" antes de "{account}", senao a rota curinga tentaria
        // carregar uma conta de id "options".
        Route::get('accounts/options', AccountOptionsController::class);
        Route::patch('accounts/{account}/archive', [AccountController::class, 'archive']);
        Route::post('accounts/{account}/adjustments', [AccountController::class, 'adjust']);

        Route::get('accounts', [AccountController::class, 'index']);
        Route::post('accounts', [AccountController::class, 'store']);
        Route::get('accounts/{account}', [AccountController::class, 'show']);
        Route::put('accounts/{account}', [AccountController::class, 'update']);
        Route::delete('accounts/{account}', [AccountController::class, 'destroy']);

        Route::post('banks', [BankController::class, 'store']);
        Route::put('banks/{bank}', [BankController::class, 'update']);
        Route::delete('banks/{bank}', [BankController::class, 'destroy']);
        Route::apiResource('budgets', BudgetController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::apiResource('goals', GoalController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('goals/{goal}/archive', [GoalController::class, 'archive']);
        Route::get('goals/{goal}/contributions', [GoalContributionController::class, 'index']);
        Route::post('goals/{goal}/contributions', [GoalContributionController::class, 'store']);
        Route::delete('goals/{goal}/contributions/{contribution}', [GoalContributionController::class, 'destroy']);

        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        // "options" antes de "{transaction}", senao a rota curinga engoliria
        // a palavra e tentaria carregar um lancamento de id "options".
        Route::get('transactions/options', TransactionOptionsController::class);
        Route::patch('transactions/{transaction}/status', TransactionStatusController::class);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::post('transactions', [TransactionController::class, 'store']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
        Route::put('transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);

        Route::get('cards/options', CardOptionsController::class);
        Route::get('cards', [CreditCardController::class, 'index']);
        Route::post('cards', [CreditCardController::class, 'store']);
        Route::get('cards/{card}', [CreditCardController::class, 'show']);
        Route::put('cards/{card}', [CreditCardController::class, 'update']);
        Route::patch('cards/{card}/archive', [CreditCardController::class, 'archive']);
        Route::delete('cards/{card}', [CreditCardController::class, 'destroy']);
        Route::get('cards/{card}/invoices', [CreditCardController::class, 'invoices']);

        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'pay']);
        Route::delete('invoices/{invoice}/payments', [InvoiceController::class, 'undoPayment']);
        Route::post('invoices/{invoice}/close', [InvoiceController::class, 'close']);

        // Importacao de extrato: a previa le o arquivo e nao grava nada; so
        // POST /imports escreve, com as linhas que a tela confirmou.
        Route::get('imports', [ImportController::class, 'index']);
        Route::post('imports/preview', [ImportController::class, 'preview']);
        Route::post('imports', [ImportController::class, 'store']);
        Route::delete('imports/{batch}', [ImportController::class, 'destroy']);

        Route::get('recurrences/options', RecurrenceOptionsController::class);
        Route::post('recurrences/launch', [RecurrenceController::class, 'materializeAll']);
        Route::get('recurrences', [RecurrenceController::class, 'index']);
        Route::post('recurrences', [RecurrenceController::class, 'store']);
        Route::get('recurrences/{recurrence}', [RecurrenceController::class, 'show']);
        Route::put('recurrences/{recurrence}', [RecurrenceController::class, 'update']);
        Route::patch('recurrences/{recurrence}/status', [RecurrenceController::class, 'toggle']);
        Route::post('recurrences/{recurrence}/launch', [RecurrenceController::class, 'materialize']);
        Route::delete('recurrences/{recurrence}', [RecurrenceController::class, 'destroy']);
    });
});
