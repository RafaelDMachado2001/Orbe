<?php

declare(strict_types=1);

use App\Domain\Banking\Models\Account;
use App\Domain\Banking\Models\Bank;
use App\Domain\Cards\Models\CreditCard;
use App\Domain\Identity\Actions\RegisterUserAction;
use App\Domain\Identity\DTOs\RegisterUserData;
use App\Domain\Ledger\Enums\TransactionType;
use App\Domain\Ledger\Models\Category;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-08-20 09:00:00'));

    // Passa pelo cadastro real para o usuario nascer com o plano de
    // categorias — e delas que sai a sugestao da tela de conferencia.
    $this->user = app(RegisterUserAction::class)->handle(new RegisterUserData(
        name: 'Rafael Machado',
        email: 'rafael@versofinancas.app',
        password: 'verso1234',
    ));

    $this->bank = Bank::factory()->create(['user_id' => $this->user->id, 'name' => 'Nubank']);

    $this->account = Account::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Conta corrente',
        'initial_balance' => 10000,
    ]);

    $this->card = CreditCard::factory()->create([
        'user_id' => $this->user->id,
        'bank_id' => $this->bank->id,
        'nickname' => 'Nubank Ultravioleta',
        'closing_day' => 28,
        'due_day' => 8,
    ]);
});

function ofxFile(string $transactions, string $name = 'extrato.ofx'): UploadedFile
{
    $contents = <<<OFX
    OFXHEADER:100
    DATA:OFXSGML
    <OFX>
    <BANKMSGSRSV1><STMTTRNRS><STMTRS>
    <BANKTRANLIST>
    {$transactions}
    </BANKTRANLIST>
    </STMTRS></STMTTRNRS></BANKMSGSRSV1>
    </OFX>
    OFX;

    return UploadedFile::fake()->createWithContent($name, $contents);
}

function ofxEntry(string $date, string $amount, string $memo): string
{
    return "<STMTTRN>\n<DTPOSTED>{$date}\n<TRNAMT>{$amount}\n<MEMO>{$memo}\n</STMTTRN>";
}

it('le o extrato e devolve as linhas sem gravar nada', function (): void {
    $file = ofxFile(implode("\n", [
        ofxEntry('20260803', '-284.90', 'MERCADO PAO DE ACUCAR'),
        ofxEntry('20260805', '7200.00', 'SALARIO AGOSTO'),
    ]));

    $response = $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', ['file' => $file, 'account_id' => $this->account->id])
        ->assertOk()
        ->assertJsonPath('data.format', 'ofx')
        ->assertJsonPath('data.period_start', '2026-08-03')
        ->assertJsonPath('data.period_end', '2026-08-05')
        ->assertJsonPath('data.summary.total', 2)
        ->assertJsonPath('data.summary.selected', 2)
        ->assertJsonPath('data.summary.income', 7200)
        ->assertJsonPath('data.summary.expense', 284.9)
        ->assertJsonPath('data.rows.0.description', 'MERCADO PAO DE ACUCAR')
        ->assertJsonPath('data.rows.0.direction', 'saida')
        ->assertJsonPath('data.rows.1.direction', 'entrada');

    // Conferir nao escreve: e o passo que existe justamente para o usuario
    // decidir antes de qualquer coisa entrar no extrato.
    expect($this->user->transactions()->count())->toBe(0);

    $alimentacao = Category::query()->ownedBy($this->user->id)->where('name', 'Alimentação')->firstOrFail();
    $salario = Category::query()->ownedBy($this->user->id)->where('name', 'Salário')->firstOrFail();

    expect($response->json('data.rows.0.category_id'))->toBe($alimentacao->id)
        ->and($response->json('data.rows.1.category_id'))->toBe($salario->id);
});

it('marca como repetida a linha que ja existe no destino', function (): void {
    $this->account->transactions()->create([
        'user_id' => $this->user->id,
        'description' => 'Mercado (lancado a mao)',
        'amount' => 284.90,
        'type' => TransactionType::Despesa,
        'direction' => 'saida',
        'status' => 'confirmado',
        'competence_date' => '2026-08-03',
        'is_installment_parent' => false,
    ]);

    $file = ofxFile(implode("\n", [
        ofxEntry('20260803', '-284.90', 'MERCADO PAO DE ACUCAR'),
        ofxEntry('20260804', '-32.40', 'UBER TRIP'),
    ]));

    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', ['file' => $file, 'account_id' => $this->account->id])
        ->assertOk()
        // A descricao do banco nao bate com a digitada, e mesmo assim e a
        // mesma despesa: o casamento e por data, valor e direcao.
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.0.selected', false)
        ->assertJsonPath('data.rows.1.is_duplicate', false)
        ->assertJsonPath('data.rows.1.selected', true)
        ->assertJsonPath('data.summary.duplicates', 1)
        ->assertJsonPath('data.summary.selected', 1);
});

/**
 * Duas compras iguais no mesmo dia sao comuns. Com uma no banco e duas no
 * arquivo, so a primeira e repetida — senao a segunda ficaria de fora.
 */
it('consome uma repeticao por lancamento ja existente', function (): void {
    $this->account->transactions()->create([
        'user_id' => $this->user->id,
        'description' => 'Cafe',
        'amount' => 12.00,
        'type' => TransactionType::Despesa,
        'direction' => 'saida',
        'status' => 'confirmado',
        'competence_date' => '2026-08-03',
        'is_installment_parent' => false,
    ]);

    $file = ofxFile(implode("\n", [
        ofxEntry('20260803', '-12.00', 'CAFETERIA'),
        ofxEntry('20260803', '-12.00', 'CAFETERIA'),
    ]));

    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', ['file' => $file, 'account_id' => $this->account->id])
        ->assertOk()
        ->assertJsonPath('data.rows.0.is_duplicate', true)
        ->assertJsonPath('data.rows.1.is_duplicate', false);
});

it('bloqueia credito na fatura quando o destino e um cartao', function (): void {
    $file = ofxFile(implode("\n", [
        ofxEntry('20260812', '-129.90', 'NETFLIX.COM'),
        ofxEntry('20260814', '450.00', 'PAGAMENTO FATURA'),
    ]));

    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', ['file' => $file, 'credit_card_id' => $this->card->id])
        ->assertOk()
        ->assertJsonPath('data.rows.0.is_importable', true)
        ->assertJsonPath('data.rows.1.is_importable', false)
        ->assertJsonPath('data.rows.1.selected', false)
        ->assertJsonPath('data.summary.blocked', 1)
        ->assertJsonPath('data.summary.selected', 1);
});

it('devolve o mapeamento adivinhado do CSV para a tela poder corrigir', function (): void {
    $csv = UploadedFile::fake()->createWithContent('extrato.csv', <<<'CSV'
    Data;Histórico;Valor;Saldo
    03/08/2026;Uber viagem;-32,40;5.715,10
    CSV);

    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', ['file' => $csv, 'account_id' => $this->account->id])
        ->assertOk()
        ->assertJsonPath('data.format', 'csv')
        ->assertJsonPath('data.mapping.date_column', 0)
        ->assertJsonPath('data.mapping.description_column', 1)
        ->assertJsonPath('data.mapping.amount_column', 2)
        ->assertJsonPath('data.mapping.delimiter', ';')
        ->assertJsonPath('data.headers', ['Data', 'Histórico', 'Valor', 'Saldo'])
        ->assertJsonPath('data.rows.0.amount', 32.4);
});

it('aceita o mapeamento corrigido na tela', function (): void {
    $csv = UploadedFile::fake()->createWithContent('extrato.csv', <<<'CSV'
    Data;Valor;Histórico
    03/08/2026;-32,40;Uber viagem
    CSV);

    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', [
            'file' => $csv,
            'account_id' => $this->account->id,
            'mapping' => [
                'date_column' => 0,
                'description_column' => 2,
                'amount_column' => 1,
                'delimiter' => ';',
                'has_header' => true,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.rows.0.description', 'Uber viagem');
});

it('recusa um formato que nao sabe ler', function (): void {
    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', [
            'file' => UploadedFile::fake()->createWithContent('extrato.pdf', '%PDF-1.4'),
            'account_id' => $this->account->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'OFX ou CSV'));
});

it('exige exatamente um destino', function (array $payload): void {
    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', [
            'file' => ofxFile(ofxEntry('20260803', '-10.00', 'TESTE')),
            ...$payload,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('account_id');
})->with([
    'nenhum' => [[]],
    'os dois' => [fn () => ['account_id' => test()->account->id, 'credit_card_id' => test()->card->id]],
]);

it('nao deixa importar para a conta de outro usuario', function (): void {
    $other = User::factory()->create();
    $otherBank = Bank::factory()->create(['user_id' => $other->id]);
    $otherAccount = Account::factory()->create(['user_id' => $other->id, 'bank_id' => $otherBank->id]);

    $this->actingAs($this->user, 'sanctum')
        ->post('/api/v1/imports/preview', [
            'file' => ofxFile(ofxEntry('20260803', '-10.00', 'TESTE')),
            'account_id' => $otherAccount->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('account_id');
});

it('exige autenticacao', function (): void {
    $this->post('/api/v1/imports/preview', [
        'file' => ofxFile(ofxEntry('20260803', '-10.00', 'TESTE')),
        'account_id' => $this->account->id,
    ])->assertUnauthorized();
});
