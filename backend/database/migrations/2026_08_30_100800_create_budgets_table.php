<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->decimal('limit_amount', 15, 2);
            $table->timestamps();

            $table->unique(['category_id', 'reference_month']);
            $table->index(['user_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
