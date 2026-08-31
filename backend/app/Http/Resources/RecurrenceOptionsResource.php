<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\RecurrenceFrequency;
use App\Domain\Ledger\Enums\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contas, cartoes e categorias reaproveitados da TransactionOptionsQuery, mais
 * as listas de enum proprias da regra fixa.
 */
class RecurrenceOptionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $options */
        $options = $this->resource;

        return [
            'accounts' => $options['accounts'],
            'cards' => $options['cards'],
            'categories' => $options['categories'],
            'frequencies' => $this->enumOptions(RecurrenceFrequency::cases()),
            'methods' => $this->enumOptions(PaymentMethod::cases()),
            'types' => $this->enumOptions([TransactionType::Despesa, TransactionType::Receita]),
        ];
    }

    /**
     * @param  array<int, RecurrenceFrequency|PaymentMethod|TransactionType>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            static fn (RecurrenceFrequency|PaymentMethod|TransactionType $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }
}
