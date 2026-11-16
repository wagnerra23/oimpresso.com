<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

/**
 * Conferência da natureza de operação — a mesma lista de erros, avisos e informações do protótipo
 * aprovado (`prototipo-ui/cowork/Felipe/Configuracao de Tributos.html`, função `validar`), agora no
 * servidor: erro bloqueia o salvar, aviso e informação não.
 *
 * ⚠️ As regras vieram do protótipo e NÃO passaram por revisão fiscal (RUNBOOK-tributacao §Naturezas,
 * verificado 2026-11-15). Mudar uma regra aqui é mudar o que a empresa consegue salvar: a fonte da
 * mudança é a revisão fiscal registrada, não o código.
 *
 * Entrada: a natureza no formato da tela (`nome`, `tipo` S|E, `fin` 1–4, `ativo`, `ref`, `cfop`
 * {in,out,ext}, `icms`, `ipi`, `pis`, `cofins`, `ibs`). Percentuais em pontos (3 = 3%), como a tela.
 */
final class ConferenciaNaturezaService
{
    public const ERRO = 'erro';
    public const AVISO = 'aviso';
    public const INFO = 'info';

    /** Final do CFOP que é devolução (x201, x202, x411…). */
    private const CFOP_DEVOLUCAO = ['201', '202', '410', '411', '553', '556', '660', '661', '662'];

    private const CST_COM_ST = ['10', '30', '70', '201', '202', '203'];

    private const SEM_ICMS_PROPRIO = ['30', '40', '41', '50', '60', '102', '103', '202', '203', '300', '400', '500'];

    private const CST_IPI_SAIDA = ['50', '51', '52', '53', '54', '55', '99'];
    private const CST_IPI_ENTRADA = ['00', '01', '02', '03', '04', '05', '49'];
    private const CST_PC_SAIDA = ['01', '02', '04', '06', '07', '08', '09', '49'];
    private const CST_PC_ENTRADA = ['50', '70', '98', '99'];

    /** Padrão de PIS/COFINS por regime, em pontos percentuais. */
    private const PC_PADRAO = ['LP' => ['pis' => 0.65, 'cofins' => 3.0], 'LR' => ['pis' => 1.65, 'cofins' => 7.6]];

    private const ROTULO_DESTINO = ['in' => 'dentro do estado', 'out' => 'outro estado', 'ext' => 'exterior'];

    /**
     * @param  array<string,mixed>  $n  natureza no formato da tela
     * @param  string  $regime  LP · LR · SN
     * @param  list<string>  $outrasAtivas  nomes das OUTRAS naturezas ativas da empresa
     * @return list<array{nivel:string,secao:string,mensagem:string}>
     */
    public function conferir(array $n, string $regime, array $outrasAtivas): array
    {
        $a = [];
        $add = function (string $nivel, string $secao, string $mensagem) use (&$a): void {
            $a[] = ['nivel' => $nivel, 'secao' => $secao, 'mensagem' => $mensagem];
        };

        $sn = $regime === 'SN';
        $entrada = ($n['tipo'] ?? 'S') === 'E';
        $fin = (string) ($n['fin'] ?? '1');
        $ref = (bool) ($n['ref'] ?? false);
        $ativo = (bool) ($n['ativo'] ?? true);
        $nome = trim((string) ($n['nome'] ?? ''));
        $cfop = array_map(fn ($c) => trim((string) $c), (array) ($n['cfop'] ?? []) + ['in' => '', 'out' => '', 'ext' => '']);
        $icms = (array) ($n['icms'] ?? []);
        $st = (array) ($icms['st'] ?? []);
        $ipi = (array) ($n['ipi'] ?? []);
        $ibs = (array) ($n['ibs'] ?? []);
        $cst = (string) ($sn ? ($icms['csosn'] ?? '') : ($icms['cst'] ?? ''));
        $temSt = in_array($cst, self::CST_COM_ST, true);
        $semIcmsProprio = in_array($cst, self::SEM_ICMS_PROPRIO, true);

        // Passo 1 — natureza
        if ($nome === '') {
            $add(self::ERRO, 'nat', 'Dê um nome à natureza. É o nome que aparece no lançamento e vai no campo natOp da nota.');
        }
        if ($nome !== '' && $ativo && in_array(mb_strtolower($nome), array_map(fn ($o) => mb_strtolower(trim($o)), $outrasAtivas), true)) {
            $add(self::ERRO, 'nat', 'Já existe uma natureza ativa com este nome.');
        }
        if ($fin === '4' && ! $ref) {
            $add(self::ERRO, 'nat', 'Devolução exige nota referenciada. Marque "Pede a chave da nota de origem".');
        }

        // Passo 2 — CFOP por destino
        $esperado = $entrada ? ['in' => '1', 'out' => '2', 'ext' => '3'] : ['in' => '5', 'out' => '6', 'ext' => '7'];
        if ($cfop['in'] === '' && $cfop['out'] === '') {
            $add(self::ERRO, 'cfop', 'Informe ao menos um CFOP, dentro ou fora do estado.');
        }
        foreach (['in', 'out', 'ext'] as $d) {
            $c = $cfop[$d];
            if ($c === '') {
                continue;
            }
            if (! preg_match('/^\d{4}$/', $c)) {
                $add(self::ERRO, 'cfop', "CFOP {$c} precisa de 4 dígitos.");
            } elseif ($c[0] !== $esperado[$d]) {
                $add(self::ERRO, 'cfop', "CFOP {$c} não serve para \"" . self::ROTULO_DESTINO[$d] . '". Em '
                    . ($entrada ? 'entrada' : 'saída') . " para esse destino o CFOP começa com {$esperado[$d]} (ex.: {$esperado[$d]}" . substr($c, 1) . ').');
            }
        }
        $ehDevolucao = false;
        foreach (['in', 'out'] as $d) {
            if ($cfop[$d] !== '' && in_array(substr($cfop[$d], 1), self::CFOP_DEVOLUCAO, true)) {
                $ehDevolucao = true;
            }
        }
        if ($fin === '4' && ($cfop['in'] !== '' || $cfop['out'] !== '') && ! $ehDevolucao) {
            $add(self::ERRO, 'cfop', 'Finalidade devolução pede CFOP de devolução (x201, x202, x411…).');
        }
        if ($fin !== '4' && $ehDevolucao) {
            $add(self::ERRO, 'cfop', 'CFOP de devolução com finalidade que não é devolução. Use a finalidade 4 · Devolução no passo 1.');
        }

        // Passo 3 — ICMS
        $cfopsDentroFora = [$cfop['in'], $cfop['out']];
        if ($cst === '') {
            $add(self::ERRO, 'icms', $sn ? 'Informe o CSOSN.' : 'Informe o CST do ICMS.');
        }
        if (array_intersect($cfopsDentroFora, ['5405', '6404']) && ! in_array($cst, ['60', '500'], true)) {
            $add(self::ERRO, 'icms', 'CFOP de ST já retido pede ' . ($sn ? 'CSOSN 500' : 'CST 60') . '.');
        }
        foreach ($cfopsDentroFora as $c) {
            if (preg_match('/^[56]40[13]$/', $c) && ! $temSt) {
                $add(self::ERRO, 'icms', 'CFOP de venda como substituto pede ' . ($sn ? 'CSOSN 201, 202 ou 203' : 'CST 10, 30 ou 70') . '.');
                break;
            }
        }
        if ($temSt && ($st['mvaOrig'] ?? 'produto') === 'fixa' && empty($st['mva']) && (string) ($st['modbcst'] ?? '4') === '4') {
            $add(self::ERRO, 'icms', 'MVA fixa sem percentual.');
        }
        if (! $sn && in_array($cst, ['20', '70'], true) && empty($icms['red'])) {
            $add(self::ERRO, 'icms', "CST {$cst} é com redução de base. Informe o percentual.");
        }
        if (! $sn && $cst === '51' && empty($icms['pDif'])) {
            $add(self::AVISO, 'icms', 'CST 51 sem percentual de diferimento.');
        }
        $origemIcms = (string) ($icms['origem'] ?? 'uf');
        if ($origemIcms === 'fixa' && ! $semIcmsProprio && empty($icms['p']) && ! ($sn && ! in_array($cst, ['101', '201', '900'], true))) {
            $add(self::ERRO, 'icms', 'Alíquota fixa de ICMS sem percentual.');
        }
        if ($origemIcms === 'fixa' && $cfop['out'] !== '' && ! $semIcmsProprio) {
            $add(self::AVISO, 'icms', 'Alíquota de ICMS fixa numa natureza com CFOP interestadual. Fora do estado a alíquota muda pela UF; "Pela UF" evita erro.');
        }
        if ($fin === '4' && $origemIcms !== 'origem') {
            $add(self::INFO, 'icms', 'Na devolução, o comum é repetir o ICMS da nota de origem.');
        }
        if ($origemIcms === 'origem' && ! $ref) {
            $add(self::AVISO, 'icms', 'Alíquota "da nota de origem" sem nota referenciada. No lançamento não haverá nota para copiar.');
        }

        // IPI
        $ipiCst = (string) ($ipi['cst'] ?? '');
        if (! empty($ipi['on']) && ($ipi['origem'] ?? 'produto') === 'fixa' && in_array($ipiCst, ['50', '00'], true) && empty($ipi['p'])) {
            $add(self::ERRO, 'ipi', 'IPI tributado com alíquota fixa zerada.');
        }
        if (! empty($ipi['on']) && ! in_array($ipiCst, $entrada ? self::CST_IPI_ENTRADA : self::CST_IPI_SAIDA, true)) {
            $add(self::ERRO, 'ipi', "CST do IPI {$ipiCst} não é de " . ($entrada ? 'entrada' : 'saída') . '.');
        }

        // PIS e COFINS
        foreach (['pis' => 'PIS', 'cofins' => 'COFINS'] as $k => $rotulo) {
            $x = (array) ($n[$k] ?? []);
            $c = (string) ($sn ? ($x['cstSN'] ?? '') : ($x['cst'] ?? ''));
            if (! in_array($c, $entrada ? self::CST_PC_ENTRADA : self::CST_PC_SAIDA, true)) {
                $add(self::ERRO, 'pc', "CST do {$rotulo} {$c} não é de " . ($entrada ? 'entrada (50 a 99)' : 'saída (01 a 49)') . '.');
            }
            if (! $entrada && in_array($c, ['01', '02'], true) && ($x['origem'] ?? 'regime') === 'fixa' && empty($x['p'])) {
                $add(self::ERRO, 'pc', "{$rotulo} com CST {$c} e alíquota fixa zerada.");
            }
            if (! $sn && ($x['origem'] ?? 'regime') === 'fixa' && ! empty($x['p']) && isset(self::PC_PADRAO[$regime])
                && (float) $x['p'] !== self::PC_PADRAO[$regime][$k]) {
                $add(self::AVISO, 'pc', "{$rotulo} fixo diferente do padrão do regime ("
                    . number_format(self::PC_PADRAO[$regime][$k], 2, ',', '') . '%).');
            }
        }
        $remessa = (bool) array_filter($cfopsDentroFora, fn ($c) => (bool) preg_match('/^[56]9(10|15|49)$/', $c));
        if ($remessa) {
            foreach (['pis', 'cofins'] as $k) {
                $x = (array) ($n[$k] ?? []);
                if (in_array((string) ($sn ? ($x['cstSN'] ?? '') : ($x['cst'] ?? '')), ['01', '02'], true)) {
                    $add(self::AVISO, 'pc', 'Remessa com PIS/COFINS de receita. Remessa sem venda costuma usar CST 49.');
                    break;
                }
            }
        }

        // IBS e CBS
        if (! empty($ibs['on']) && ! preg_match('/^\d{6}$/', preg_replace('/\D/', '', (string) ($ibs['cClass'] ?? '')))) {
            $add(self::ERRO, 'ibs', 'Informe o cClassTrib do IBS/CBS com 6 dígitos.');
        }
        if (! $sn && empty($ibs['on'])) {
            $add(self::INFO, 'ibs', 'IBS e CBS desligados. Em 2026 os grupos já vão no XML do regime normal.');
        }

        return $a;
    }

    /** @param list<array{nivel:string}> $alertas */
    public static function temErro(array $alertas): bool
    {
        foreach ($alertas as $x) {
            if ($x['nivel'] === self::ERRO) {
                return true;
            }
        }

        return false;
    }

    /** Regime do `nfe_business_configs` no código que a conferência usa. */
    public static function regimeDaConfig(?string $regime): string
    {
        return match ($regime) {
            'simples', 'mei' => 'SN',
            'lucro_real'     => 'LR',
            default          => 'LP',
        };
    }
}
