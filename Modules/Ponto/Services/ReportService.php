<?php

namespace Modules\Ponto\Services;

use App\Util\OtelHelper;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;
use Modules\Ponto\Entities\Marcacao;
use RuntimeException;

/**
 * Gerador de relatórios do módulo Ponto.
 *
 * Hoje:
 *   - espelhoPdf(): espelho mensal por colaborador (PDF via barryvdh/laravel-dompdf)
 *   - afd(): AFD do REP-P por colaborador (Portaria MTP 671/2021) — ADR 0413 W7
 *
 * Stub (retorna 501):
 *   - aej(), he(), bancoHoras(), atrasos(), esocial()
 *   (o AFDT saiu: formato da Portaria 1510/2009 — ADR 0413 W7)
 *
 * Wave 12 — instrumentação OTel canônica (ADR 0155 D9.a + ADR 0156 errata).
 * Span `ponto.report.espelho_pdf` expõe latência de render DomPDF (operação cara)
 * + multi-tenant Tier 0 (business_id no attribute). PII redacted: só
 * employee_id numérico no span, NUNCA cpf/pis/matricula.
 */
class ReportService
{
    /**
     * Gera PDF do espelho de ponto de um colaborador no mês informado.
     *
     * @param Colaborador $colaborador
     * @param string $mes formato YYYY-MM
     * @return \Barryvdh\DomPDF\PDF (objeto DomPDF — chamar ->stream() ou ->download())
     */
    public function espelhoPdf(Colaborador $colaborador, $mes)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            throw new RuntimeException('Mês inválido (formato esperado YYYY-MM).');
        }

        // Wave 12 — OTel span (zero-cost se config('otel.enabled')=false).
        // PII: business_id + employee_id apenas, sem cpf/pis/nome.
        return OtelHelper::span('ponto.report.espelho_pdf', [
            'module'      => 'Ponto',
            'business_id' => (int) ($colaborador->business_id ?? 0),
            'employee_id' => (int) $colaborador->id,
            'mes'         => $mes,
        ], function () use ($colaborador, $mes) {
            list($ano, $mesNum) = explode('-', $mes);
            $inicio = Carbon::createFromDate((int) $ano, (int) $mesNum, 1)->startOfMonth();
            $fim    = $inicio->copy()->endOfMonth();

            $apuracoes = ApuracaoDia::where('colaborador_config_id', $colaborador->id)
                ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
                ->orderBy('data')
                ->get();

            $marcacoes = Marcacao::where('colaborador_config_id', $colaborador->id)
                ->whereBetween('momento', [$inicio->toDateTimeString(), $fim->copy()->endOfDay()->toDateTimeString()])
                ->whereNotIn('origem', [Marcacao::ORIGEM_ANULACAO])
                ->orderBy('momento')
                ->get()
                ->groupBy(function ($m) {
                    return $m->momento->toDateString();
                });

            // Totais mensais
            $totais = [
                'trabalhado'       => 0,
                'atraso'           => 0,
                'falta'            => 0,
                'he_diurna'        => 0,
                'he_noturna'       => 0,
                'adicional_not'    => 0,
                'bh_credito'       => 0,
                'bh_debito'        => 0,
                'dsr_repercussao'  => 0,
            ];
            foreach ($apuracoes as $a) {
                $totais['trabalhado']      += (int) $a->realizada_trabalhada_minutos;
                $totais['atraso']          += (int) $a->atraso_minutos;
                $totais['falta']           += (int) $a->falta_minutos;
                $totais['he_diurna']       += (int) $a->he_diurna_minutos;
                $totais['he_noturna']      += (int) $a->he_noturna_minutos;
                $totais['adicional_not']   += (int) $a->adicional_noturno_minutos;
                $totais['bh_credito']      += (int) $a->banco_horas_credito_minutos;
                $totais['bh_debito']       += (int) $a->banco_horas_debito_minutos;
                $totais['dsr_repercussao'] += (int) $a->dsr_repercussao_minutos;
            }

            $data = [
                'colaborador' => $colaborador,
                'mes'         => $mes,
                'inicio'      => $inicio,
                'fim'         => $fim,
                'apuracoes'   => $apuracoes,
                'marcacoes'   => $marcacoes,
                'totais'      => $totais,
                'gerado_em'   => now(),
            ];

            $pdf = PDF::loadView('pontowr2::reports.espelho-pdf', $data);
            $pdf->setPaper('a4', 'portrait');
            return $pdf;
        });
    }

    /**
     * Nome de arquivo sugerido para o espelho.
     */
    public function espelhoPdfNome(Colaborador $colaborador, $mes)
    {
        $matricula = $colaborador->matricula ?: ('colab-' . $colaborador->id);
        return "espelho-ponto_{$matricula}_{$mes}.pdf";
    }

    /**
     * O AFD está pronto para sair? Só com a identidade do REP-P configurada
     * (config/ponto_afd.php). O catálogo usa isto pra não prometer o que não entrega.
     */
    public function afdConfigurado(): bool
    {
        return $this->afdIdentidade() !== null;
    }

    /**
     * AFD do REP-P de UM colaborador no período — Portaria MTP 671/2021, leiaute do
     * Arquivo Fonte de Dados (tipo 1 · tipo 7 · trailer · linha da assinatura).
     *
     * Escopo decidido por [W] (ADR 0413 W7 + 2026-09-30, thread 12):
     *   - POR COLABORADOR: o NSR e o hash do REP-P são sequenciais por colaborador
     *     ([W] 2026-09-29), então cada cadeia é um arquivo — um AFD da empresa teria NSR repetido.
     *   - SÓ REP-P: o AFD de REP-C é o arquivo do próprio relógio. Ao importar, o
     *     MarcacaoService troca o NSR do arquivo pelo contador interno — regerar mentiria o NSR.
     *   - Lido de `ponto_marcacoes`, nunca da apuração (ADR 0413 "Como se reconhece violação").
     *   - Independe do fechamento da competência (ADR 0413 W3).
     *   - SEM .p7s: não há certificado ICP-Brasil (ADR 0413 D2). O arquivo termina na linha
     *     que o leiaute reserva para a assinatura; o .p7s não é gerado, e o catálogo diz isso.
     *
     * Campos do tipo 7 derivados, não inventados: coletor "01" (aplicativo mobile — a única
     * entrada REP-P grava `dispositivo_id` "mobile:*"), on-line "0" (o `momento` é carimbado
     * pelo servidor, então não há marcação off-line), DH de gravação = `created_at`.
     *
     * ⚠️ Premissa, a mesma do AfdParserService: hash SHA-256 = sha256(posições 001-073 + hash
     * do tipo 7 anterior), com "" antes do primeiro. A norma lista os campos, não o separador.
     *
     * ⚠️ Fora do arquivo, e o NSR mostra o buraco: as anulações (entram na sequência por decisão
     * [W], mas o leiaute do REP-P não tem registro de anulação) e o legado com NSR de `microtime`.
     *
     * @return string conteúdo ISO-8859-1, linhas terminadas em CRLF
     * @throws \DomainException quando falta dado legal (config, CNPJ do empregador, CPF)
     */
    public function afd(Colaborador $colaborador, Carbon $inicio, Carbon $fim): string
    {
        $identidade = $this->afdIdentidade();
        if ($identidade === null) {
            throw new \DomainException(
                'AFD indisponível: nº INPI do REP-P e CNPJ do desenvolvedor não configurados '
                . '(PONTO_REP_P_INPI / PONTO_REP_P_DESENVOLVEDOR_CNPJ).'
            );
        }

        $business = \App\Business::query()->find($colaborador->business_id);
        $docEmpregador = preg_replace('/\D/', '', (string) optional($business)->tax_number_1);
        if (! in_array(strlen($docEmpregador), [11, 14], true)) {
            throw new \DomainException('AFD indisponível: CNPJ/CPF do empregador não cadastrado na empresa.');
        }

        $cpf = preg_replace('/\D/', '', (string) $colaborador->cpf);
        if (strlen($cpf) !== 11) {
            throw new \DomainException('AFD indisponível: colaborador sem CPF cadastrado (o leiaute 671 identifica por CPF).');
        }

        return OtelHelper::span('ponto.report.afd', [
            'module'      => 'Ponto',
            'business_id' => (int) $colaborador->business_id,
            'employee_id' => (int) $colaborador->id,
        ], function () use ($colaborador, $inicio, $fim, $identidade, $business, $docEmpregador, $cpf) {
            $linhas = [$this->afdCabecalho($identidade, $business->name, $docEmpregador, $inicio, $fim)];

            // A cadeia inteira até o fim do período: o hash de cada tipo 7 depende do anterior,
            // então o primeiro registro do período precisa do hash do último registro antes dele.
            $marcacoes = NsrService::filtroCadeiaRepP(Marcacao::query(), (int) $colaborador->business_id, (int) $colaborador->id)
                ->where('origem', Marcacao::ORIGEM_REP_P)
                ->where('momento', '<=', $fim->copy()->endOfDay())
                ->orderBy('nsr')
                ->get();

            $hashAnterior = '';
            $tipo7 = 0;
            foreach ($marcacoes as $m) {
                $dados = sprintf('%09d', $m->nsr) . '7' . $this->afdDataHora($m->momento)
                    . '0' . $cpf . $this->afdDataHora($m->created_at ?? $m->momento) . '01' . '0';
                $hashAnterior = hash('sha256', $dados . $hashAnterior);

                if ($m->momento->gte($inicio->copy()->startOfDay())) {
                    $linhas[] = $dados . $hashAnterior;
                    $tipo7++;
                }
            }

            // Trailer: "999999999" + contadores dos tipos 2..7 + "9" na posição 064.
            $linhas[] = '999999999' . str_repeat('0', 9 * 5) . sprintf('%09d', $tipo7) . '9';
            // Leiaute AFD, registro de assinatura (001-100): o texto literal + espaços até 100.
            $linhas[] = str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100);

            return implode("\r\n", $linhas) . "\r\n";
        });
    }

    /**
     * Nome pela regra do leiaute (item 10.3): "AFD" + nº INPI + CNPJ/CPF do empregador + "REP_P".
     * Não leva o colaborador: o nome é fixado pela norma, então os AFDs de colaboradores
     * diferentes saem com o mesmo nome (a pasta de destino é quem os separa).
     */
    public function afdNome(Colaborador $colaborador): string
    {
        $inpi = (string) optional($this->afdIdentidade())['inpi'];
        $doc = preg_replace('/\D/', '', (string) optional(\App\Business::query()->find($colaborador->business_id))->tax_number_1);

        return 'AFD' . $inpi . $doc . 'REP_P.txt';
    }

    /** ['inpi' => string, 'cnpj' => string] ou null se a configuração estiver incompleta. */
    private function afdIdentidade(): ?array
    {
        $inpi = trim((string) config('ponto_afd.rep_p_inpi'));
        $cnpj = preg_replace('/\D/', '', (string) config('ponto_afd.desenvolvedor_cnpj'));

        if ($inpi === '' || strlen($inpi) > 17 || strlen($cnpj) !== 14) {
            return null;
        }

        return ['inpi' => $inpi, 'cnpj' => $cnpj];
    }

    /** Tipo 1 — 302 posições, CRC-16/KERMIT nas 4 últimas, calculado sobre os bytes ISO-8859-1. */
    private function afdCabecalho(array $identidade, string $razaoSocial, string $doc, Carbon $inicio, Carbon $fim): string
    {
        $razao = mb_convert_encoding(mb_substr(trim($razaoSocial), 0, 150), 'ISO-8859-1', 'UTF-8');

        $dados = '000000000' . '1'
            . (strlen($doc) === 14 ? '1' : '2') . str_pad($doc, 14)        // A: alinhado à esquerda, espaços
            . str_repeat(' ', 14)                                   // CNO/CAEPF: não há
            . str_pad($razao, 150)
            . str_pad($identidade['inpi'], 17)
            . $inicio->format('Y-m-d') . $fim->format('Y-m-d')
            . $this->afdDataHora(now()) . '004'                     // versão: a do AfdLeiaute671ContratoTest
            . '1' . $identidade['cnpj']
            . str_repeat(' ', 30);                                  // modelo: só REP-C

        return $dados . AfdParserService::crc16Kermit($dados);
    }

    /** "AAAA-MM-ddThh:mm:00ZZZZZ" — segundos zerados, como o leiaute manda. */
    private function afdDataHora(Carbon $momento): string
    {
        return $momento->format('Y-m-d\TH:i') . ':00' . $momento->format('O');
    }

    // ---- Stubs (501) ----

    public function aej($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Gerador AEJ ainda não implementado.');
    }

    public function he($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Relatório de HE ainda não implementado.');
    }

    public function bancoHoras($businessId, Carbon $data)
    {
        throw new RuntimeException('Relatório de BH ainda não implementado.');
    }

    public function atrasos($businessId, Carbon $inicio, Carbon $fim)
    {
        throw new RuntimeException('Relatório de atrasos/faltas ainda não implementado.');
    }

    public function esocial($businessId, Carbon $competencia)
    {
        throw new RuntimeException('Geração de eventos eSocial ainda não implementada.');
    }
}
