<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services\Banking\Drivers;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\RecurringBilling\Contracts\BankStatementDriverContract;
use Modules\RecurringBilling\Dto\StatementLineDto;

/**
 * Driver de extrato por arquivo OFX — o caminho genérico dos bancos sem API.
 *
 * Mesmo contrato do `InterStatementDriver`: devolve `StatementLineDto[]` e quem grava
 * é o `StatementPersister`, então a Conciliação lê a linha igual à do Inter
 * (`fin_extrato_lancamentos`, valor positivo + tipo C/D).
 *
 * Medido em 2026-10-27 (prod): Bradesco, Caixa, BB, C6, Cora — nenhum com credencial
 * de API cadastrada; todos exportam OFX pelo internet banking.
 *
 * Regras de VALOR (regra mestre, memory/proibicoes.md):
 *  - O sinal vem do TRNAMT, como manda a especificação OFX e como o backfill da
 *    ADR 0236 já faz (`BackfillExtratoOfxCommand`). O TRNTYPE não decide o sinal.
 *  - Separador: OFX usa ponto decimal. Bancos que exportam vírgula ("-1.234,56")
 *    são aceitos: havendo vírgula, ela é o decimal e o ponto é milhar.
 *  - Chave anti-duplicata = FITID, no formato do backfill (`ofx-<FITID>` /
 *    `ofx:<FITID>`), para a linha migrada e a importada não duplicarem.
 *
 * @see US-FIN-084
 */
class OfxStatementDriver implements BankStatementDriverContract
{
    public function __construct(
        private readonly string $conteudo,
    ) {}

    public function fetchStatement(Carbon $from, Carbon $to): Collection
    {
        return $this->linhas()
            ->filter(fn (StatementLineDto $l) => $l->data->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay()))
            ->values();
    }

    /** Todas as transações do arquivo, sem recorte de período. */
    public function linhas(): Collection
    {
        $texto = mb_check_encoding($this->conteudo, 'UTF-8')
            ? $this->conteudo
            : mb_convert_encoding($this->conteudo, 'UTF-8', 'Windows-1252');

        // SGML (OFX 1.x) e XML (OFX 2.x): o bloco termina no </STMTTRN>, no próximo
        // <STMTTRN> ou no fim da lista — cobre arquivo sem a tag de fechamento.
        preg_match_all('#<STMTTRN>(.*?)(?=</STMTTRN>|<STMTTRN>|</BANKTRANLIST>|\z)#is', $texto, $m);

        return collect($m[1])
            ->map(fn (string $bloco) => $this->parse($bloco))
            ->filter()
            ->values();
    }

    private function parse(string $bloco): ?StatementLineDto
    {
        $valor = self::valor($this->tag($bloco, 'TRNAMT'));
        $data = $this->tag($bloco, 'DTPOSTED');
        if ($valor === null || $data === null || ! preg_match('/^\d{8}/', $data)) {
            return null;
        }

        $fitid = $this->tag($bloco, 'FITID') ?: 'auto-'.md5($bloco);
        $descricao = trim(implode(' - ', array_filter([$this->tag($bloco, 'NAME'), $this->tag($bloco, 'MEMO')])));

        return new StatementLineDto(
            data: Carbon::createFromFormat('Ymd', substr($data, 0, 8))->startOfDay(),
            valor: abs($valor),
            tipo: $valor < 0 ? 'D' : 'C',
            descricao: mb_substr($descricao !== '' ? $descricao : 'Transação importada', 0, 500),
            contraparteDocumento: null,
            contraparteNome: null,
            idempotencyKey: 'ofx-'.$fitid,
            raw: [
                'fitid'   => $fitid,
                'trntype' => $this->tag($bloco, 'TRNTYPE'),
                'trnamt'  => $this->tag($bloco, 'TRNAMT'),
                'checknum' => $this->tag($bloco, 'CHECKNUM'),
            ],
        );
    }

    private function tag(string $bloco, string $tag): ?string
    {
        return preg_match('#<'.$tag.'>\s*([^<\r\n]*)#i', $bloco, $m) && trim($m[1]) !== ''
            ? trim($m[1])
            : null;
    }

    /** "-30000.00", "-30.000,00", "1234,5" → float com sinal; lixo → null. */
    public static function valor(?string $bruto): ?float
    {
        if ($bruto === null) {
            return null;
        }
        $s = str_replace([' ', "\u{00A0}", 'R$'], '', $bruto);
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        }

        return is_numeric($s) ? round((float) $s, 2) : null;
    }
}
