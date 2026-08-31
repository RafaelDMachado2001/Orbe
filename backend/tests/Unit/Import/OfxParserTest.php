<?php

declare(strict_types=1);

use App\Domain\Import\Exceptions\StatementParseException;
use App\Domain\Import\Parsers\OfxParser;
use App\Domain\Ledger\Enums\MovementDirection;

function ofxSgml(string $transactions): string
{
    return <<<OFX
    OFXHEADER:100
    DATA:OFXSGML
    VERSION:102
    ENCODING:USASCII
    CHARSET:1252

    <OFX>
    <BANKMSGSRSV1><STMTTRNRS><STMTRS>
    <CURDEF>BRL
    <BANKACCTFROM><BANKID>0260<ACCTID>1234567<ACCTTYPE>CHECKING</BANKACCTFROM>
    <BANKTRANLIST>
    <DTSTART>20260801
    <DTEND>20260831
    {$transactions}
    </BANKTRANLIST>
    </STMTRS></STMTTRNRS></BANKMSGSRSV1>
    </OFX>
    OFX;
}

it('le o extrato OFX em SGML, que e o que os bancos brasileiros exportam', function (): void {
    $statement = (new OfxParser)->parse(ofxSgml(<<<'TRN'
    <STMTTRN>
    <TRNTYPE>DEBIT
    <DTPOSTED>20260803120000[-3:BRT]
    <TRNAMT>-284.90
    <FITID>202608030001
    <MEMO>MERCADO PAO DE ACUCAR
    </STMTTRN>
    <STMTTRN>
    <TRNTYPE>CREDIT
    <DTPOSTED>20260805120000[-3:BRT]
    <TRNAMT>7200.00
    <FITID>202608050001
    <MEMO>SALARIO
    </STMTTRN>
    TRN));

    expect($statement->entries)->toHaveCount(2);

    [$first, $second] = $statement->entries;

    expect($first->description)->toBe('MERCADO PAO DE ACUCAR')
        ->and($first->amount)->toBe(284.90)
        ->and($first->direction)->toBe(MovementDirection::Saida)
        ->and($first->externalId)->toBe('202608030001')
        ->and($second->direction)->toBe(MovementDirection::Entrada)
        ->and($second->amount)->toBe(7200.0)
        ->and($statement->periodStart()?->toDateString())->toBe('2026-08-03')
        ->and($statement->periodEnd()?->toDateString())->toBe('2026-08-05');
});

it('le tambem o OFX 2.x em XML, com as tags fechadas', function (): void {
    $xml = <<<'OFX'
    <?xml version="1.0" encoding="UTF-8"?>
    <OFX>
      <CREDITCARDMSGSRSV1><CCSTMTTRNRS><CCSTMTRS>
        <BANKTRANLIST>
          <STMTTRN>
            <TRNTYPE>DEBIT</TRNTYPE>
            <DTPOSTED>20260812</DTPOSTED>
            <TRNAMT>-129.90</TRNAMT>
            <MEMO>NETFLIX.COM</MEMO>
          </STMTTRN>
        </BANKTRANLIST>
      </CCSTMTRS></CCSTMTTRNRS></CREDITCARDMSGSRSV1>
    </OFX>
    OFX;

    $entries = (new OfxParser)->parse($xml)->entries;

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->description)->toBe('NETFLIX.COM')
        ->and($entries[0]->amount)->toBe(129.90);
});

/**
 * O cabecalho CHARSET:1252 e a regra: gravar esses bytes como se fossem UTF-8
 * corromperia todo acento das descricoes.
 */
it('converte o arquivo em Windows-1252 antes de ler', function (): void {
    $utf8 = ofxSgml(<<<'TRN'
    <STMTTRN>
    <TRNTYPE>DEBIT
    <DTPOSTED>20260803
    <TRNAMT>-42,00
    <MEMO>FARMÁCIA SÃO JOÃO
    </STMTTRN>
    TRN);

    $entries = (new OfxParser)->parse((string) mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8'))->entries;

    expect($entries[0]->description)->toBe('FARMÁCIA SÃO JOÃO');
});

it('usa o TRNTYPE quando o valor vem sem sinal', function (): void {
    $entries = (new OfxParser)->parse(ofxSgml(<<<'TRN'
    <STMTTRN>
    <TRNTYPE>DEBIT
    <DTPOSTED>20260803
    <TRNAMT>50.00
    <MEMO>TARIFA MENSAL
    </STMTTRN>
    TRN))->entries;

    expect($entries[0]->direction)->toBe(MovementDirection::Saida);
});

it('descarta linha sem data, sem valor ou zerada', function (): void {
    $entries = (new OfxParser)->parse(ofxSgml(<<<'TRN'
    <STMTTRN>
    <TRNTYPE>OTHER
    <DTPOSTED>20260803
    <TRNAMT>0.00
    <MEMO>SALDO DO DIA
    </STMTTRN>
    <STMTTRN>
    <TRNTYPE>DEBIT
    <TRNAMT>-10.00
    <MEMO>SEM DATA
    </STMTTRN>
    <STMTTRN>
    <TRNTYPE>DEBIT
    <DTPOSTED>20260804
    <TRNAMT>-10.00
    <MEMO>VALIDA
    </STMTTRN>
    TRN))->entries;

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->description)->toBe('VALIDA');
});

it('recusa um arquivo que nao e OFX', function (): void {
    (new OfxParser)->parse('data;descricao;valor');
})->throws(StatementParseException::class, 'não parece um OFX');

it('recusa um OFX sem nenhum lancamento aproveitavel', function (): void {
    (new OfxParser)->parse(ofxSgml(<<<'TRN'
    <STMTTRN>
    <TRNTYPE>OTHER
    <DTPOSTED>20260803
    <TRNAMT>0.00
    <MEMO>SALDO
    </STMTTRN>
    TRN));
})->throws(StatementParseException::class, 'nenhum lançamento');
