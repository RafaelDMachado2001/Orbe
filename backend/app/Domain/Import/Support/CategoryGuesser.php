<?php

declare(strict_types=1);

namespace App\Domain\Import\Support;

use App\Domain\Ledger\Enums\CategoryType;
use App\Domain\Ledger\Enums\MovementDirection;
use App\Domain\Ledger\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Sugere a categoria de uma linha do extrato pelo texto que o banco escreveu.
 *
 * E palpite, nao regra: a sugestao chega marcada na tela e o usuario troca
 * antes de confirmar. Por isso o casamento e por palavra-chave e nao por um
 * modelo — errar aqui custa um clique, e uma lista legivel e corrigivel vale
 * mais do que acerto que ninguem consegue auditar.
 *
 * As palavras apontam para o nome da categoria, nao para um id: assim o
 * palpite continua funcionando depois de o usuario renomear ou recriar as
 * categorias padrao, e cai fora sozinho quando a categoria nao existe.
 */
final class CategoryGuesser
{
    /** @var array<string, list<string>> */
    private const EXPENSE_KEYWORDS = [
        'Alimentação' => [
            'mercado', 'supermerc', 'padaria', 'restaurante', 'ifood', 'rappi', 'lanche',
            'pizza', 'acougue', 'hortifruti', 'atacad', 'pao de acucar', 'carrefour',
            'assai', 'bar ', 'cafe', 'burger', 'mc donalds', 'subway', 'zedelivery',
        ],
        'Transporte' => [
            'uber', '99app', '99 tecnologia', 'taxi', 'posto ', 'combustivel', 'gasolina',
            'ipiranga', 'shell', 'estacionamento', 'pedagio', 'sem parar', 'conectcar',
            'metro', 'onibus', 'cabify', 'localiza', 'movida',
        ],
        'Moradia' => [
            'aluguel', 'condominio', 'energia', 'enel', 'cemig', 'copel', 'light ',
            'cpfl', 'sabesp', 'copasa', 'saneamento', 'comgas', 'iptu', 'internet',
            'vivo fibra', 'claro net', 'oi fibra',
        ],
        'Saúde' => [
            'farmacia', 'drogaria', 'droga raia', 'drogasil', 'pacheco', 'hospital',
            'clinica', 'laboratorio', 'unimed', 'amil', 'bradesco saude', 'dentista',
            'psicolog', 'fisioterap',
        ],
        'Educação' => [
            'escola', 'colegio', 'faculdade', 'universidade', 'curso', 'udemy',
            'alura', 'coursera', 'livraria', 'mensalidade',
        ],
        'Assinaturas' => [
            'netflix', 'spotify', 'amazon prime', 'disney', 'hbo', 'globoplay',
            'youtube premium', 'apple.com', 'icloud', 'google one', 'microsoft',
            'adobe', 'openai', 'chatgpt', 'dropbox', 'notion',
        ],
        'Lazer' => [
            'cinema', 'teatro', 'ingresso', 'steam', 'playstation', 'xbox', 'nintendo',
            'hotel', 'airbnb', 'booking', 'decolar', 'latam', 'gol linhas', 'azul via',
        ],
        'Compras' => [
            'mercado livre', 'mercadolivre', 'magazine luiza', 'magalu', 'americanas',
            'shopee', 'aliexpress', 'amazon', 'shopping', 'renner', 'riachuelo',
            'c&a', 'zara', 'centauro', 'netshoes', 'leroy',
        ],
        'Equipamento' => ['kabum', 'pichau', 'terabyte', 'apple store', 'dell', 'lenovo'],
        'Impostos e taxas' => [
            'tarifa', 'iof', 'juros', 'anuidade', 'taxa ', 'darf', 'das ', 'inss',
            'multa', 'encargo', 'cesta de servicos', 'manutencao de conta',
        ],
    ];

    /** @var array<string, list<string>> */
    private const INCOME_KEYWORDS = [
        'Salário' => ['salario', 'folha de pagamento', 'remuneracao', 'provento', 'holerite', 'adiantamento salarial'],
        'Serviços PJ' => ['nota fiscal', 'servicos prestados', 'honorario', 'pagamento pj', 'fatura emitida'],
        'Aluguel recebido' => ['aluguel recebido', 'locacao', 'imobiliaria'],
        'Rendimentos' => [
            'rendimento', 'cdb', 'tesouro', 'dividendo', 'juros sobre capital',
            'poupanca', 'cashback', 'resgate', 'aplicacao automatica',
        ],
    ];

    /** @var array<string, int> nome normalizado da categoria => id */
    private array $byName = [];

    /** @param  Collection<int, Category>  $categories */
    public function __construct(Collection $categories)
    {
        foreach ($categories as $category) {
            $this->byName[$this->key($category->name, $category->type)] = $category->id;
        }
    }

    public function guess(string $description, MovementDirection $direction): ?int
    {
        $haystack = Str::ascii(mb_strtolower($description));
        $type = $direction === MovementDirection::Entrada ? CategoryType::Receita : CategoryType::Despesa;
        $keywords = $type === CategoryType::Receita ? self::INCOME_KEYWORDS : self::EXPENSE_KEYWORDS;

        foreach ($keywords as $name => $terms) {
            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) {
                    return $this->byName[$this->key($name, $type)] ?? null;
                }
            }
        }

        return null;
    }

    private function key(string $name, CategoryType $type): string
    {
        return $type->value.'|'.Str::ascii(mb_strtolower(trim($name)));
    }
}
