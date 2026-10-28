<?php

namespace Modules\Financeiro\Services;

use Illuminate\Support\Facades\DB;

/**
 * Lançamentos contábeis da competência, nos leiautes de importação do contador (US-FIN-077).
 *
 * Regime de caixa: um lançamento por baixa do mês (`fin_titulo_baixas.data_baixa`). Receber
 * debita o banco e credita a conta do título; pagar faz o inverso. Juros+multa e desconto saem
 * em linhas próprias, então o líquido no banco fecha com valor_baixa + juros + multa − desconto
 * (a mesma conta do DashboardController). Estorno (valor_baixa negativo) inverte débito e crédito.
 *
 * Valor sempre em centavos inteiros. Origem sem código no de-para (`fin_contabil_contas`) não é
 * omitida em silêncio: vira pendência e o arquivo não sai.
 */
class ExportacaoContabilService
{
    public const LAYOUTS = ['dominio', 'alterdata'];

    /** @return array{lancamentos: list<array>, pendencias: list<string>} */
    public function lancamentos(int $businessId, string $competencia): array
    {
        $inicio = $competencia.'-01';
        $fim = date('Y-m-t', strtotime($inicio));

        $baixas = DB::table('fin_titulo_baixas as b')
            ->join('fin_titulos as t', 't.id', '=', 'b.titulo_id')
            ->where('b.business_id', $businessId)
            ->where('t.business_id', $businessId)
            ->whereBetween('b.data_baixa', [$inicio, $fim])
            ->orderBy('b.data_baixa')->orderBy('b.id')
            ->get(['b.id', 'b.data_baixa', 'b.conta_bancaria_id', 'b.valor_baixa', 'b.juros', 'b.multa', 'b.desconto',
                't.tipo', 't.numero', 't.plano_conta_id', 't.cliente_descricao']);

        $depara = DB::table('fin_contabil_contas')->where('business_id', $businessId)
            ->get(['origem_tipo', 'origem_id', 'codigo'])
            ->mapWithKeys(fn ($r) => [$r->origem_tipo.':'.$r->origem_id => (string) $r->codigo])->all();
        $conta = function (string $tipo, ?int $id) use ($depara): ?string {
            return $depara[$tipo.':'.(int) $id] ?? null;
        };

        $lancamentos = [];
        $pendencias = [];
        foreach ($baixas as $b) {
            $ref = 'baixa #'.$b->id.' (título '.$b->numero.')';
            $banco = $b->conta_bancaria_id ? $conta('conta_bancaria', (int) $b->conta_bancaria_id) : null;
            $contrapartida = $b->plano_conta_id ? $conta('plano_conta', (int) $b->plano_conta_id) : null;
            if ($banco === null) {
                $pendencias[] = $ref.': conta bancária sem código contábil';
            }
            if ($contrapartida === null) {
                $pendencias[] = $ref.': plano de contas sem código contábil';
            }
            if ($banco === null || $contrapartida === null) {
                continue;
            }

            $estorno = (float) $b->valor_baixa < 0;
            $recebe = $b->tipo === 'receber';
            $historico = ($recebe ? 'Recebimento ' : 'Pagamento ').$b->numero.' '.$b->cliente_descricao;
            $linhas = [
                [$this->centavos($b->valor_baixa), $recebe ? [$banco, $contrapartida] : [$contrapartida, $banco], $historico],
            ];
            $acrescimo = $this->centavos($b->juros) + $this->centavos($b->multa);
            if ($acrescimo !== 0) {
                $juros = $conta('juros', 0);
                if ($juros === null) {
                    $pendencias[] = $ref.': conta de juros/multa sem código contábil';
                }
                $linhas[] = [$acrescimo, $recebe ? [$banco, $juros] : [$juros, $banco], 'Juros/multa '.$historico];
            }
            if ($this->centavos($b->desconto) !== 0) {
                $desc = $conta('desconto', 0);
                if ($desc === null) {
                    $pendencias[] = $ref.': conta de desconto sem código contábil';
                }
                $linhas[] = [$this->centavos($b->desconto), $recebe ? [$desc, $banco] : [$banco, $desc], 'Desconto '.$historico];
            }

            foreach ($linhas as [$valor, [$debito, $credito], $texto]) {
                if ($debito === null || $credito === null || $valor === 0) {
                    continue;
                }
                if ($estorno) {
                    [$debito, $credito] = [$credito, $debito];
                }
                $lancamentos[] = [
                    'data' => (string) substr((string) $b->data_baixa, 0, 10),
                    'debito' => $debito,
                    'credito' => $credito,
                    'centavos' => abs($valor),
                    'historico' => ($estorno ? 'Estorno ' : '').$texto,
                    'documento' => (string) $b->numero,
                ];
            }
        }

        return ['lancamentos' => $lancamentos, 'pendencias' => $pendencias];
    }

    /** Conteúdo do arquivo já no encoding do leiaute. */
    public function gerar(string $layout, string $cnpj, array $lancamentos): string
    {
        return match ($layout) {
            'dominio' => $this->dominio($cnpj, $lancamentos),
            'alterdata' => $this->alterdata($lancamentos),
            default => throw new \InvalidArgumentException("Leiaute desconhecido: {$layout}"),
        };
    }

    /**
     * Domínio Sistemas, "Leiaute Domínio Sistemas com Separador" (suporte.dominioatendimento.com,
     * solução 672): registros 0000 / 6000 / 6100, separador '|', data dd/mm/aaaa, decimal com
     * vírgula. Um 6000 tipo X (um débito p/ um crédito) por 6100. ISO-8859-1 + CRLF, como o
     * exemplo oficial `exemplo_arquivo__lancamento_lote.txt`.
     */
    private function dominio(string $cnpj, array $lancamentos): string
    {
        $linhas = [$this->pipe(['0000', preg_replace('/\D/', '', $cnpj)])];
        foreach ($lancamentos as $l) {
            $linhas[] = $this->pipe(['6000', 'X', '', '', '']);
            $linhas[] = $this->pipe(['6100', $this->dataBr($l['data']), $l['debito'], $l['credito'],
                $this->valorBr($l['centavos']), '', $this->texto($l['historico'], '|'), '', '', '']);
        }

        return mb_convert_encoding(implode("\r\n", $linhas)."\r\n", 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Alterdata, "Importação Personalizada - Texto" (ajuda.alterdata.com.br, artigo 150435497):
     * colunas A..J entre aspas e separadas por vírgula — lançamento automático, débito, crédito,
     * data, valor, código do histórico, complemento, CC débito, CC crédito, documento. UTF-8 +
     * CRLF, como o arquivo-modelo oficial.
     */
    private function alterdata(array $lancamentos): string
    {
        $linhas = [];
        foreach ($lancamentos as $l) {
            $campos = ['', $l['debito'], $l['credito'], $this->dataBr($l['data']), $this->valorBr($l['centavos']),
                '', $this->texto($l['historico'], '"'), '', '', $this->texto($l['documento'], '"')];
            $linhas[] = '"'.implode('","', $campos).'"';
        }

        return implode("\r\n", $linhas).($linhas ? "\r\n" : '');
    }

    private function centavos($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    private function pipe(array $campos): string
    {
        return '|'.implode('|', $campos).'|';
    }

    private function dataBr(string $iso): string
    {
        return substr($iso, 8, 2).'/'.substr($iso, 5, 2).'/'.substr($iso, 0, 4);
    }

    private function valorBr(int $centavos): string
    {
        return intdiv($centavos, 100).','.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Tira o separador do leiaute e quebras de linha do texto livre. */
    private function texto(?string $s, string $separador): string
    {
        return mb_substr(trim(str_replace([$separador, "\r", "\n"], ' ', (string) $s)), 0, 200);
    }
}
