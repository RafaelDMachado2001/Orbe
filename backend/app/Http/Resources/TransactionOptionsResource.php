<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Ledger\Enums\EntryKind;
use App\Domain\Ledger\Enums\MovementOrigin;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Enums\TransactionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contas, cartoes e categorias do usuario, mais as listas fixas de enum.
 *
 * Os enums viajam junto para que a tela nunca escreva "Compra no cartão" a
 * mao: o rotulo sai do mesmo lugar que a regra.
 */
class TransactionOptionsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $options */
        $options = $this->resource;

        return [
            ...$options,
            'kinds' => $this->enumOptions(EntryKind::cases()),
            'types' => $this->enumOptions(TransactionType::cases()),
            'statuses' => $this->enumOptions(TransactionStatus::cases()),
            'methods' => $this->enumOptions(PaymentMethod::cases()),
            'origins' => $this->enumOptions(MovementOrigin::cases()),
        ];
    }

    /**
     * @param  array<int, EntryKind|TransactionType|TransactionStatus|PaymentMethod|MovementOrigin>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            static fn (EntryKind|TransactionType|TransactionStatus|PaymentMethod|MovementOrigin $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }
}
