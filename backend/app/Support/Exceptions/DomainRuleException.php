<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * Uma regra de negocio recusou a operacao.
 *
 * Nao e erro de servidor nem de autorizacao: o registro existe, pertence ao
 * usuario e a requisicao esta bem formada — o dominio e que nao permite o que
 * foi pedido. A borda HTTP traduz qualquer filha desta classe em 422 com a
 * propria mensagem, entao a regra e escrita uma vez, no dominio, e a interface
 * so a exibe.
 */
abstract class DomainRuleException extends RuntimeException {}
