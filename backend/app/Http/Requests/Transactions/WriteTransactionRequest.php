<?php

declare(strict_types=1);

namespace App\Http\Requests\Transactions;

use App\Domain\Cards\DTOs\CardPurchaseData;
use App\Domain\Ledger\DTOs\InstallmentPlanData;
use App\Domain\Ledger\DTOs\TransactionData;
use App\Domain\Ledger\DTOs\TransferData;
use App\Domain\Ledger\Enums\AmountMode;
use App\Domain\Ledger\Enums\EntryKind;
use App\Domain\Ledger\Enums\PaymentMethod;
use App\Domain\Ledger\Enums\TransactionStatus;
use App\Domain\Ledger\Models\Category;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Regras comuns a criar e editar um lancamento.
 *
 * O que vale depende da aba (EntryKind): uma compra no cartao exige cartao e
 * numero de parcelas, um emprestimo exige credor e parcelas, uma transferencia
 * exige duas contas diferentes, e as demais exigem conta. Concentrar isso aqui
 * evita que criar e editar divirjam com o tempo.
 */
abstract class WriteTransactionRequest extends FormRequest
{
    abstract public function kind(): EntryKind;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $kind = $this->kind();
        $userId = $this->user()->id;

        return [
            ...$this->sharedRules($kind, $userId),
            ...$this->accountRules($kind, $userId),
            ...$this->cardRules($kind, $userId),
            ...$this->installmentRules($kind),
            ...$this->transferRules($kind, $userId),
        ];
    }

    /** @return array<string, mixed> */
    private function sharedRules(EntryKind $kind, int $userId): array
    {
        return [
            'description' => ['required', 'string', 'min:2', 'max:120'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'competence_date' => ['required', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::enum(TransactionStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
            'category_id' => [
                $kind === EntryKind::Transferencia ? 'prohibited' : 'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $userId),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function accountRules(EntryKind $kind, int $userId): array
    {
        $needsAccount = in_array(
            $kind,
            [EntryKind::Receita, EntryKind::Despesa, EntryKind::Emprestimo],
            true,
        );

        return [
            'account_id' => [
                Rule::requiredIf($needsAccount),
                $needsAccount ? 'integer' : 'prohibited',
                Rule::exists('accounts', 'id')->where('user_id', $userId),
            ],
            // A compra no cartao entra na fatura, nao na conta: quem define
            // quando o dinheiro sai e o pagamento da fatura.
            'method' => [
                $kind === EntryKind::Cartao ? 'prohibited' : 'nullable',
                Rule::enum(PaymentMethod::class),
            ],
            // Um parcelamento tem uma data de pagamento por parcela, entao
            // uma unica data no formulario nao teria a quem pertencer.
            'paid_date' => [
                $needsAccount && $this->plannedInstallments() === 1 ? 'nullable' : 'prohibited',
                'date_format:Y-m-d',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function cardRules(EntryKind $kind, int $userId): array
    {
        $isCard = $kind === EntryKind::Cartao;

        return [
            'credit_card_id' => [
                Rule::requiredIf($isCard),
                $isCard ? 'integer' : 'prohibited',
                Rule::exists('credit_cards', 'id')->where('user_id', $userId),
            ],
        ];
    }

    /**
     * Parcelamento. O cartao para em 36 vezes porque e o teto das bandeiras;
     * um emprestimo em conta vai a 360, que cobre um financiamento de 30 anos.
     *
     * @return array<string, mixed>
     */
    private function installmentRules(EntryKind $kind): array
    {
        $isCard = $kind === EntryKind::Cartao;
        $isLoan = $kind === EntryKind::Emprestimo;
        $accepts = $isCard || $kind->acceptsInstallmentPlan();

        return [
            'installments' => [
                Rule::requiredIf($isCard || $isLoan),
                $accepts ? 'integer' : 'prohibited',
                'between:1,'.($isCard ? 36 : InstallmentPlanData::MAX_INSTALLMENTS),
            ],
            // So faz sentido dizer "o valor e o da parcela" quando existem
            // parcelas para multiplicar.
            'amount_mode' => [
                $accepts ? 'nullable' : 'prohibited',
                Rule::enum(AmountMode::class),
            ],
            'lender' => [
                Rule::requiredIf($isLoan),
                $isLoan ? 'string' : 'prohibited',
                'max:80',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function transferRules(EntryKind $kind, int $userId): array
    {
        $isTransfer = $kind === EntryKind::Transferencia;
        $exists = Rule::exists('accounts', 'id')->where('user_id', $userId);

        return [
            'from_account_id' => [
                Rule::requiredIf($isTransfer),
                $isTransfer ? 'integer' : 'prohibited',
                $exists,
            ],
            'to_account_id' => [
                Rule::requiredIf($isTransfer),
                $isTransfer ? 'integer' : 'prohibited',
                'different:from_account_id',
                $exists,
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'description.required' => 'Descreva o lançamento.',
            'amount.required' => 'Informe o valor.',
            'amount.gt' => 'O valor precisa ser maior que zero.',
            'competence_date.required' => 'Informe a data.',
            'competence_date.date_format' => 'A data deve estar no formato AAAA-MM-DD.',
            'account_id.required' => 'Escolha a conta do lançamento.',
            'credit_card_id.required' => 'Escolha o cartão da compra.',
            'installments.required' => 'Informe em quantas parcelas a compra foi feita.',
            'installments.between' => 'Número de parcelas fora do limite permitido.',
            'lender.required' => 'Informe quem concedeu o empréstimo.',
            'from_account_id.required' => 'Escolha a conta de origem.',
            'to_account_id.required' => 'Escolha a conta de destino.',
            'to_account_id.different' => 'A conta de destino precisa ser diferente da origem.',
        ];
    }

    /**
     * Uma despesa nao se classifica com categoria de receita. A checagem fica
     * na validacao, e nao no banco, porque a resposta precisa apontar o campo.
     *
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categoryId = $this->integer('category_id');
                $expected = $this->kind()->categoryType();

                if ($categoryId === 0 || $expected === null || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $category = Category::query()->find($categoryId);

                if ($category !== null && $category->type !== $expected) {
                    $validator->errors()->add(
                        'category_id',
                        "Escolha uma categoria de {$expected->label()}.",
                    );
                }
            },
        ];
    }

    public function toTransactionData(): TransactionData
    {
        return new TransactionData(
            accountId: $this->integer('account_id'),
            description: $this->descriptionValue(),
            amount: $this->amountValue(),
            type: $this->kind()->transactionType(),
            competenceDate: $this->competenceDate(),
            categoryId: $this->integer('category_id') ?: null,
            status: $this->statusValue(),
            method: $this->enum('method', PaymentMethod::class),
            paidDate: $this->paidDate(),
            notes: $this->notesValue(),
        );
    }

    public function toCardPurchaseData(): CardPurchaseData
    {
        return new CardPurchaseData(
            creditCardId: $this->integer('credit_card_id'),
            description: $this->descriptionValue(),
            amount: $this->totalAmount(),
            purchaseDate: $this->competenceDate(),
            installments: $this->integer('installments'),
            categoryId: $this->integer('category_id') ?: null,
            status: $this->statusValue(),
            notes: $this->notesValue(),
        );
    }

    public function toInstallmentPlanData(): InstallmentPlanData
    {
        $isLoan = $this->kind() === EntryKind::Emprestimo;

        return new InstallmentPlanData(
            accountId: $this->integer('account_id'),
            description: $this->descriptionValue(),
            totalAmount: $this->totalAmount(),
            installments: $this->plannedInstallments(),
            firstDate: $this->competenceDate(),
            categoryId: $this->integer('category_id') ?: null,
            status: $this->statusValue(),
            method: $this->enum('method', PaymentMethod::class),
            notes: $this->notesValue(),
            isLoan: $isLoan,
            lender: $isLoan ? trim((string) $this->input('lender')) : null,
        );
    }

    /** Quantas parcelas o lancamento tera; sem parcelamento, uma. */
    public function plannedInstallments(): int
    {
        return max(1, $this->integer('installments', 1));
    }

    public function toTransferData(): TransferData
    {
        return new TransferData(
            fromAccountId: $this->integer('from_account_id'),
            toAccountId: $this->integer('to_account_id'),
            amount: $this->amountValue(),
            date: $this->competenceDate(),
            description: $this->descriptionValue(),
            notes: $this->notesValue(),
        );
    }

    private function descriptionValue(): string
    {
        return trim((string) $this->input('description'));
    }

    private function amountValue(): float
    {
        return round((float) $this->input('amount'), 2);
    }

    /**
     * O valor do lancamento inteiro. Quem digitou a prestacao ("24x de R$ 480")
     * tem a multiplicacao feita aqui, na fronteira, para que o dominio receba
     * sempre o total.
     */
    private function totalAmount(): float
    {
        $mode = $this->enum('amount_mode', AmountMode::class) ?? AmountMode::Total;

        return $mode->totalFor($this->amountValue(), $this->plannedInstallments());
    }

    private function competenceDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', (string) $this->input('competence_date'))
            ->startOfDay();
    }

    /**
     * Confirmado sem data de pagamento assume a propria competencia — o
     * extrato nao pode ter um lancamento confirmado sem quando.
     */
    private function paidDate(): ?CarbonImmutable
    {
        $paidDate = $this->input('paid_date');

        if (is_string($paidDate) && $paidDate !== '') {
            return CarbonImmutable::createFromFormat('Y-m-d', $paidDate)->startOfDay();
        }

        return $this->statusValue() === TransactionStatus::Confirmado
            ? $this->competenceDate()
            : null;
    }

    private function statusValue(): TransactionStatus
    {
        return $this->enum('status', TransactionStatus::class) ?? TransactionStatus::Confirmado;
    }

    private function notesValue(): ?string
    {
        $notes = trim((string) $this->input('notes'));

        return $notes === '' ? null : $notes;
    }
}
