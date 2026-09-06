<?php

declare(strict_types=1);

use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\SystemCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Categorias de ajuste de saldo para quem ja tinha conta.
 *
 * Conciliar o saldo com o extrato do banco cria um lancamento, e todo
 * lancamento precisa de uma categoria — sem ela, a diferenca apareceria sem
 * classificacao no relatorio do mes. Sao duas porque o tipo da categoria e o
 * que separa receita de despesa no resto do app: conciliar para cima e uma
 * entrada, para baixo e uma saida.
 */
return new class extends Migration
{
    /** @var list<array{key: SystemCategory, type: CategoryType}> */
    private const ADJUSTMENTS = [
        ['key' => SystemCategory::AjusteEntrada, 'type' => CategoryType::Receita],
        ['key' => SystemCategory::AjusteSaida, 'type' => CategoryType::Despesa],
    ];

    public function up(): void
    {
        $now = now();
        $rows = [];

        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            foreach (self::ADJUSTMENTS as $adjustment) {
                $exists = DB::table('categories')
                    ->where('user_id', $userId)
                    ->where('system_key', $adjustment['key']->value)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $rows[] = [
                    'user_id' => (int) $userId,
                    'name' => $adjustment['key']->defaultName(),
                    'type' => $adjustment['type']->value,
                    'color' => '#6E7681',
                    'icon' => 'scale',
                    'is_system' => true,
                    'system_key' => $adjustment['key']->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('categories')->insert($rows);
        }
    }

    /**
     * Os lancamentos de ajuste ficam: eles explicam um saldo que ja existe.
     * A categoria sai apenas quando nada aponta para ela.
     */
    public function down(): void
    {
        $keys = array_map(
            static fn (array $adjustment): string => $adjustment['key']->value,
            self::ADJUSTMENTS,
        );

        DB::table('categories')
            ->whereIn('system_key', $keys)
            ->whereNotExists(fn ($query) => $query
                ->from('transactions')
                ->whereColumn('transactions.category_id', 'categories.id'))
            ->delete();
    }
};
