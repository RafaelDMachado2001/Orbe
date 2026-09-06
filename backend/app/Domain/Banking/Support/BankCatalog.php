<?php

declare(strict_types=1);

namespace App\Domain\Banking\Support;

use App\Domain\Banking\Enums\BankKind;

/**
 * Os bancos que a tela oferece prontos.
 *
 * Nao e uma tabela: o banco de um usuario continua sendo uma linha em `banks`,
 * criada quando ele escolhe um daqui. O catalogo existe para poupar digitacao
 * e, principalmente, para que a mesma instituicao apareca com a mesma cor em
 * todas as contas de todos os usuarios — a cor do banco pinta cartao, grafico
 * e lista, e "Nubank" roxo em uma tela e verde em outra confunde a leitura.
 *
 * A lista e curada, nao exaustiva: quem usa um banco fora dela cadastra a mao,
 * e o resultado no banco de dados e exatamente o mesmo.
 *
 * @phpstan-type CatalogEntry array{slug: string, name: string, color: string, kind: BankKind}
 */
final class BankCatalog
{
    /** @var list<array{slug: string, name: string, color: string, kind: string}> */
    private const ENTRIES = [
        ['slug' => 'nubank', 'name' => 'Nubank', 'color' => '#A05BE0', 'kind' => 'digital'],
        ['slug' => 'inter', 'name' => 'Banco Inter', 'color' => '#FF7A00', 'kind' => 'digital'],
        ['slug' => 'c6-bank', 'name' => 'C6 Bank', 'color' => '#D8B65C', 'kind' => 'digital'],
        ['slug' => 'picpay', 'name' => 'PicPay', 'color' => '#21C25E', 'kind' => 'digital'],
        ['slug' => 'mercado-pago', 'name' => 'Mercado Pago', 'color' => '#00A9E0', 'kind' => 'digital'],
        ['slug' => 'nubank-pj', 'name' => 'Nubank PJ', 'color' => '#7C3FBF', 'kind' => 'digital'],
        ['slug' => 'pagbank', 'name' => 'PagBank', 'color' => '#3AA76D', 'kind' => 'digital'],
        ['slug' => 'neon', 'name' => 'Neon', 'color' => '#00A868', 'kind' => 'digital'],

        ['slug' => 'itau', 'name' => 'Itaú', 'color' => '#EC7000', 'kind' => 'tradicional'],
        ['slug' => 'bradesco', 'name' => 'Bradesco', 'color' => '#D6294A', 'kind' => 'tradicional'],
        ['slug' => 'banco-do-brasil', 'name' => 'Banco do Brasil', 'color' => '#E8CE3B', 'kind' => 'tradicional'],
        ['slug' => 'caixa', 'name' => 'Caixa Econômica', 'color' => '#2E8BC8', 'kind' => 'tradicional'],
        ['slug' => 'santander', 'name' => 'Santander', 'color' => '#E24A4A', 'kind' => 'tradicional'],
        ['slug' => 'sicredi', 'name' => 'Sicredi', 'color' => '#5AA82E', 'kind' => 'tradicional'],
        ['slug' => 'sicoob', 'name' => 'Sicoob', 'color' => '#12A19A', 'kind' => 'tradicional'],
        ['slug' => 'banrisul', 'name' => 'Banrisul', 'color' => '#3D6FB4', 'kind' => 'tradicional'],

        ['slug' => 'xp', 'name' => 'XP Investimentos', 'color' => '#E8B93B', 'kind' => 'corretora'],
        ['slug' => 'btg-pactual', 'name' => 'BTG Pactual', 'color' => '#4C7BC0', 'kind' => 'corretora'],
        ['slug' => 'rico', 'name' => 'Rico', 'color' => '#F26A3D', 'kind' => 'corretora'],
        ['slug' => 'clear', 'name' => 'Clear', 'color' => '#4FD1C5', 'kind' => 'corretora'],
        ['slug' => 'nuinvest', 'name' => 'NuInvest', 'color' => '#8E7BFF', 'kind' => 'corretora'],

        ['slug' => 'dinheiro', 'name' => 'Dinheiro em espécie', 'color' => '#35D68A', 'kind' => 'carteira'],
        ['slug' => 'carteira-digital', 'name' => 'Carteira digital', 'color' => '#6E7681', 'kind' => 'carteira'],
    ];

    /** @return list<CatalogEntry> */
    public static function all(): array
    {
        return array_map(
            static fn (array $entry): array => [...$entry, 'kind' => BankKind::from($entry['kind'])],
            self::ENTRIES,
        );
    }

    /** @return CatalogEntry|null */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $entry) {
            if ($entry['slug'] === $slug) {
                return $entry;
            }
        }

        return null;
    }
}
