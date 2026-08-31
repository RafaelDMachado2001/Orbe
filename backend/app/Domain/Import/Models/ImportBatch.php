<?php

declare(strict_types=1);

namespace App\Domain\Import\Models;

use App\Domain\Banking\Models\Account;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Import\Enums\ImportFormat;
use App\Domain\Import\Enums\ImportTarget;
use App\Domain\Ledger\Models\Transaction;
use App\Support\Concerns\BelongsToUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma importacao concluida.
 *
 * Guarda o cabecalho, nao as linhas: quem representa o dinheiro e a propria
 * transaction, marcada com import_batch_id. Duplicar as linhas aqui criaria
 * duas versoes do mesmo lancamento para divergir com o tempo.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $account_id
 * @property int|null $credit_card_id
 * @property string $filename
 * @property ImportFormat $format
 * @property int $imported_count
 * @property int $skipped_count
 * @property CarbonImmutable|null $period_start
 * @property CarbonImmutable|null $period_end
 * @property CarbonImmutable $created_at
 * @property-read Account|null $account
 * @property-read CreditCard|null $creditCard
 * @property-read int|null $transactions_count
 * @property-read int|null $locked_count
 */
class ImportBatch extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id',
        'account_id',
        'credit_card_id',
        'filename',
        'format',
        'imported_count',
        'skipped_count',
        'period_start',
        'period_end',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => ImportFormat::class,
            'imported_count' => 'integer',
            'skipped_count' => 'integer',
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function target(): ImportTarget
    {
        return $this->credit_card_id === null ? ImportTarget::Conta : ImportTarget::Cartao;
    }

    public function destinationLabel(): string
    {
        return $this->credit_card_id === null
            ? ($this->account->nickname ?? 'Conta removida')
            : ($this->creditCard->nickname ?? 'Cartão removido');
    }
}
