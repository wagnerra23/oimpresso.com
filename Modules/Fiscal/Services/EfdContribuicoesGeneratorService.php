<?php

declare(strict_types=1);

namespace Modules\Fiscal\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Fiscal\Services\Sped\CarregaEmitente;
use Modules\Fiscal\Services\Sped\EmitenteSped;
use Modules\NfeBrasil\Models\NfeEmissao;
use RuntimeException;

/**
 * US-FISCAL-011 — EFD-Contribuições (PIS/COFINS) do Lucro Presumido, regime cumulativo, consolidado.
 *
 * Leiaute: Guia Prático da EFD-Contribuições v1.35 (18/06/2021), leiaute 006 (períodos desde
 * 01/01/2020, tabela 3.1.1). Ordem dos campos conferida com o `nfephp-org/sped-efd` (Elements/
 * Contribuicoes) como segunda fonte. Validação no PVA oficial: não rodada.
 *
 * Desenho ([W] 2026-10-27, "use os padrões mais comuns"):
 *  - 0110: COD_INC_TRIB 2 (cumulativo), COD_TIPO_CONT 1 (alíquota básica), IND_REG_CUM 2
 *    (competência, escrituração consolidada) → as receitas vão no F550 por CST e alíquota;
 *  - CST 01, PIS 0,65% e COFINS 3,00% (COD_CONT 51, tabela do M210 no Guia);
 *  - receita = NF-e (55) e NFC-e (65) autorizadas no mês, sem as notas de devolução;
 *  - base SEM exclusão do ICMS (Tema 69 STF) — o padrão conservador; a exclusão é decisão do contador;
 *  - M205/M605: códigos de receita da DCTF 810902 (PIS) e 217201 (COFINS) cumulativos.
 *
 * Recusa em vez de inventar: regime diferente de lucro presumido, contador sem CPF (0100 exige),
 * emitente incompleto (EmitenteSped) e mês sem receita (o caminho do 0120 não está implementado).
 */
class EfdContribuicoesGeneratorService
{
    use CarregaEmitente;

    /** NT/Guia da EFD-Contribuições, tabela 3.1.1: código 006 desde 01/01/2020. */
    public const COD_VER = '006';

    public const ALIQ_PIS = 0.65;

    public const ALIQ_COFINS = 3.00;

    public const COD_REC_PIS = '810902';

    public const COD_REC_COFINS = '217201';

    /** @var array<string,int> */
    private array $contadores = [];

    public function gerar(int $businessId, int $ano, int $mes): string
    {
        if (! (bool) config('fiscal.efd_contribuicoes_enabled', false)) {
            throw new RuntimeException('EFD-Contribuições desligada (fiscal.efd_contribuicoes_enabled).');
        }
        $this->validar($businessId, $ano, $mes);

        $regime = $this->carregarRegime($businessId);
        if ($regime !== 'lucro_presumido') {
            throw new InvalidArgumentException(match ($regime) {
                'simples', 'mei' => 'Empresa do Simples Nacional/MEI é dispensada da EFD-Contribuições.',
                'lucro_real'      => 'Lucro Real (não cumulativo) não implementado: os créditos vêm das notas de entrada.',
                default           => 'Regime tributário não configurado em Fiscal › Configuração.',
            });
        }

        $business = $this->carregarBusiness($businessId) ?? throw new RuntimeException("Business {$businessId} não encontrado.");
        $emitente = $this->emitente($business, $businessId);
        $contador = $this->carregarContador($businessId);
        if ($contador === null || strlen(preg_replace('/\D/', '', (string) ($contador->cpf ?? '')) ?? '') !== 11) {
            throw new InvalidArgumentException('Falta o CPF do contador (registro 0100 é obrigatório). Cadastre em Fiscal › Contador.');
        }

        $ini = CarbonImmutable::create($ano, $mes, 1)->startOfMonth();
        $fim = $ini->endOfMonth();
        $porModelo = $this->carregarReceitas($businessId, $ini, $fim);
        $receita = round(array_sum(array_column($porModelo, 'valor')), 2);
        if ($receita <= 0) {
            throw new InvalidArgumentException("Sem receita em {$mes}/{$ano}: a escrituração sem dados (registro 0120) não está implementada.");
        }

        $pis = round($receita * self::ALIQ_PIS / 100, 2);
        $cofins = round($receita * self::ALIQ_COFINS / 100, 2);

        return $this->montar($emitente, $contador, $ini, $fim, $porModelo, $receita, $pis, $cofins);
    }

    /** @param list<array{modelo:string, valor:float, qtd:int}> $porModelo */
    private function montar(EmitenteSped $e, object $contador, CarbonImmutable $ini, CarbonImmutable $fim, array $porModelo, float $receita, float $pis, float $cofins): string
    {
        $this->contadores = [];
        $l = [];
        $v = fn (float $x): string => number_format($x, 2, ',', '');
        $a = fn (float $x): string => number_format($x, 4, ',', '');

        // Bloco 0
        $l[] = $this->linha('0000', [self::COD_VER, '0', '', '', $ini->format('dmY'), $fim->format('dmY'),
            self::semAcento($e->nome), $e->cnpj, $e->uf, $e->codMun, '', '00', '2']);
        $l[] = $this->linha('0001', ['0']);
        $l[] = $this->linha('0100', [self::semAcento((string) $contador->nome), preg_replace('/\D/', '', (string) $contador->cpf),
            (string) ($contador->crc ?? ''), '', '', '', '', '', '', '', '', (string) ($contador->email ?? ''), '']);
        $l[] = $this->linha('0110', ['2', '', '1', '2']);
        $l[] = $this->linha('0140', ['', self::semAcento($e->nome), $e->cnpj, $e->uf, $e->ie, $e->codMun, '', '']);
        $l[] = $this->linha('0990', [(string) (count($l) + 1)]);

        // Blocos sem dados: A (ISS), C (documentos detalhados), D (transporte)
        foreach (['A', 'C', 'D'] as $b) {
            $l[] = $this->linha("{$b}001", ['1']);
            $l[] = $this->linha("{$b}990", ['2']);
        }

        // Bloco F — receita consolidada por CST e alíquota (Lucro Presumido, competência)
        $inicioF = count($l);
        $l[] = $this->linha('F001', ['0']);
        $l[] = $this->linha('F010', [$e->cnpj]);
        $l[] = $this->linha('F550', [$v($receita), '01', '0,00', $v($receita), $a(self::ALIQ_PIS), $v($pis),
            '01', '0,00', $v($receita), $a(self::ALIQ_COFINS), $v($cofins), '', '', '', '']);
        $l[] = $this->linha('F990', [(string) (count($l) - $inicioF + 1)]);

        $l[] = $this->linha('I001', ['1']);
        $l[] = $this->linha('I990', ['2']);

        // Bloco M — apuração (só os campos do cumulativo têm valor)
        $inicioM = count($l);
        $l[] = $this->linha('M001', ['0']);
        foreach ([['M200', 'M205', 'M210', self::COD_REC_PIS, self::ALIQ_PIS, $pis],
                  ['M600', 'M605', 'M610', self::COD_REC_COFINS, self::ALIQ_COFINS, $cofins]] as [$cons, $rec, $det, $codRec, $aliq, $valor]) {
            // 01-12: NC_PER, CRED_DESC, CRED_DESC_ANT, NC_DEV, RET_NC, OUT_DED_NC, NC_REC, CUM_PER, RET_CUM, OUT_DED_CUM, CUM_REC, TOT_REC
            $l[] = $this->linha($cons, ['0,00', '0,00', '0,00', '0,00', '0,00', '0,00', '0,00', $v($valor), '0,00', '0,00', $v($valor), $v($valor)]);
            $l[] = $this->linha($rec, ['12', $codRec, $v($valor)]);
            // COD_CONT, REC_BRT, BC_CONT, AJUS_ACRES_BC, AJUS_REDUC_BC, BC_CONT_AJUS, ALIQ, QUANT_BC, ALIQ_QUANT,
            // CONT_APUR, AJUS_ACRES, AJUS_REDUC, CONT_DIFER, CONT_DIFER_ANT, CONT_PER
            $l[] = $this->linha($det, ['51', $v($receita), $v($receita), '0,00', '0,00', $v($receita), $a($aliq), '', '',
                $v($valor), '0,00', '0,00', '0,00', '0,00', $v($valor)]);
        }
        $l[] = $this->linha('M990', [(string) (count($l) - $inicioM + 1)]);

        $l[] = $this->linha('P001', ['1']);
        $l[] = $this->linha('P990', ['2']);

        // Bloco 1 — o 1900 é obrigatório desde abril/2013 para o Lucro Presumido consolidado
        $inicio1 = count($l);
        $l[] = $this->linha('1001', ['0']);
        foreach ($porModelo as $m) {
            $l[] = $this->linha('1900', [$e->cnpj, $m['modelo'], '', '', '00', $v($m['valor']), (string) $m['qtd'], '01', '01', '', '', '']);
        }
        $l[] = $this->linha('1990', [(string) (count($l) - $inicio1 + 1)]);

        // Bloco 9
        $inicio9 = count($l);
        $l[] = $this->linha('9001', ['0']);
        $regs = $this->contadores;
        foreach ($regs as $reg => $qtd) {
            $l[] = $this->linha('9900', [(string) $reg, (string) $qtd]);
        }
        $l[] = $this->linha('9900', ['9900', (string) (count($regs) + 3)]);
        $l[] = $this->linha('9900', ['9990', '1']);
        $l[] = $this->linha('9900', ['9999', '1']);
        $l[] = $this->linha('9990', [(string) (count($l) - $inicio9 + 2)]); // + o próprio 9990 e o 9999
        $l[] = $this->linha('9999', [(string) (count($l) + 1)]);

        return implode('', $l);
    }

    protected function carregarRegime(int $businessId): ?string
    {
        return DB::table('nfe_business_configs')->where('business_id', $businessId)->value('regime');
    }

    protected function carregarBusiness(int $businessId): ?object
    {
        return DB::table('business')->where('id', $businessId)->first();
    }

    protected function carregarContador(int $businessId): ?object
    {
        return DB::table('nfe_contadores')->where('business_id', $businessId)->first();
    }

    /**
     * Receita do mês por modelo: NF-e/NFC-e autorizadas, fora as notas de devolução
     * (`metadata.devolucao`, gravado pelo `DevolucaoService`).
     *
     * @return list<array{modelo:string, valor:float, qtd:int}>
     */
    protected function carregarReceitas(int $businessId, CarbonImmutable $ini, CarbonImmutable $fim): array
    {
        $por = [];
        NfeEmissao::query()
            ->where('business_id', $businessId)
            ->where('status', 'autorizada')
            ->whereIn('modelo', ['55', '65'])
            ->whereBetween('emitido_em', [$ini, $fim->endOfDay()])
            ->get(['modelo', 'valor_total', 'metadata'])
            ->each(function ($n) use (&$por) {
                if (! empty(((array) ($n->metadata ?? []))['devolucao'])) {
                    return;
                }
                $por[$n->modelo] ??= ['modelo' => (string) $n->modelo, 'valor' => 0.0, 'qtd' => 0];
                $por[$n->modelo]['valor'] += (float) $n->valor_total;
                $por[$n->modelo]['qtd']++;
            });
        ksort($por);

        return array_values($por);
    }

    private function validar(int $businessId, int $ano, int $mes): void
    {
        if ($ano < 2020 || $mes < 1 || $mes > 12) {
            throw new InvalidArgumentException("Competência inválida: {$mes}/{$ano} (leiaute 006 vale desde 01/2020).");
        }
        if (! CarbonImmutable::create($ano, $mes, 1)->endOfMonth()->isPast()) {
            throw new InvalidArgumentException("Competencia em aberto: {$mes}/{$ano} ainda nao encerrou.");
        }
        $sessao = session('user.business_id');
        if ($sessao !== null && (int) $sessao !== $businessId) {
            throw new RuntimeException("Cross-tenant attempt: session biz={$sessao} tentou gerar EFD-Contribuições biz={$businessId}");
        }
    }

    private function linha(string $reg, array $campos): string
    {
        $this->contadores[$reg] = ($this->contadores[$reg] ?? 0) + 1;

        return '|' . $reg . '|' . implode('|', array_map('strval', $campos)) . "|\r\n";
    }

    /** O 0000 aceita só letras sem acento, dígitos e alguns sinais (Guia v1.35, registro 0000, campo 08). */
    private static function semAcento(string $texto): string
    {
        $t = strtr($texto, ['Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ã' => 'A', 'É' => 'E', 'Ê' => 'E', 'Í' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ç' => 'C', 'á' => 'a', 'à' => 'a',
            'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ç' => 'c']);

        return preg_replace("/[^A-Za-z0-9 \\/,.\\-@:&*+_<>()!?'\$%]/", '', $t) ?? '';
    }
}
