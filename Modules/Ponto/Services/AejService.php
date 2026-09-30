<?php

namespace Modules\Ponto\Services;

use App\Util\OtelHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AEJ — Arquivo Eletrônico de Jornada, Portaria MTP 671/2021.
 *
 * Fonte do formato (âncora, não o código): "Leiaute do Arquivo Eletrônico de Jornada - AEJ", MTE,
 * versão "002" — registros 01 · 02 · 03 · 04 · 05 · 07 · 08 · 99 + assinatura; campos separados por
 * "|", ISO-8859-1, CRLF, sem linha em branco.
 *
 * De onde vem cada registro (ADR 0420, que emenda a 0413 só para o AEJ):
 *   02/03/05  `ponto_marcacoes` (+ REPs e colaboradores do mesmo empregador)
 *   04        turno da escala do dia (`ponto_escala_turnos`); sem turno, o previsto da apuração
 *   07        falta da apuração (tipo 2) + crédito/débito de banco de horas (tipo 3)
 *   08        identidade do PTRP em config/ponto_afd.php, sem default
 *
 * Dado legal ausente é RECUSA (\DomainException com contagens, sem PII), nunca preenchimento:
 * motivo de marcação manual / desconsiderada, CPF e nome do colaborador, horário do dia, nº do REP.
 *
 * Fora do arquivo, declarado na ADR 0420: DSR e folga de feriado (07 tipos 1/4), movimentos de BH
 * que não são inclusão/compensação, redução da hora noturna em durJornada, registro 06.
 */
class AejService
{
    private const MAP_FONTE = ['AFD' => 'O', 'AFDT' => 'O', 'REP_P' => 'O', 'INTEGRACAO' => 'T', 'MANUAL' => 'I'];
    private const MAP_TP_REP = ['REP_C' => '1', 'REP_A' => '2', 'REP_P' => '3'];

    public function configurado(): bool
    {
        return $this->ptrp() !== null;
    }

    public function gerar(int $businessId, Carbon $inicio, Carbon $fim): string
    {
        $ptrp = $this->ptrp();
        if ($ptrp === null) {
            throw new \DomainException('AEJ indisponível: identidade do PTRP não configurada (PONTO_PTRP_* e PONTO_REP_P_DESENVOLVEDOR_CNPJ).');
        }

        $business = DB::table('business')->where('id', $businessId)->first(['name', 'tax_number_1']);
        $doc = preg_replace('/\D/', '', (string) optional($business)->tax_number_1);
        if (! in_array(strlen($doc), [11, 14], true)) {
            throw new \DomainException('AEJ indisponível: CNPJ/CPF do empregador não cadastrado na empresa.');
        }

        return OtelHelper::span('ponto.report.aej', ['module' => 'Ponto', 'business_id' => $businessId],
            fn () => $this->montar($businessId, $inicio->copy()->startOfDay(), $fim->copy()->endOfDay(), $ptrp, $business->name, $doc));
    }

    private function montar(int $biz, Carbon $inicio, Carbon $fim, array $ptrp, string $razao, string $doc): string
    {
        $falta = [];
        $recusar = function (string $motivo) use (&$falta) {
            $falta[$motivo] = ($falta[$motivo] ?? 0) + 1;
        };

        $marcacoes = DB::table('ponto_marcacoes')->where('business_id', $biz)
            ->whereBetween('momento', [$inicio, $fim])->where('origem', '!=', 'ANULACAO')
            ->orderBy('colaborador_config_id')->orderBy('momento')->get();
        $anulacoes = DB::table('ponto_marcacoes')->where('business_id', $biz)->where('origem', 'ANULACAO')
            ->whereIn('marcacao_anulada_id', $marcacoes->pluck('id'))->pluck('motivo_anulacao', 'marcacao_anulada_id');
        $apuracoes = DB::table('ponto_apuracao_dia')->where('business_id', $biz)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])->get()
            ->keyBy(fn ($a) => $a->colaborador_config_id . '|' . substr((string) $a->data, 0, 10));
        $bh = DB::table('ponto_banco_horas_movimentos')->where('business_id', $biz)
            ->whereBetween('data_referencia', [$inicio->toDateString(), $fim->toDateString()])
            ->whereIn('tipo', ['CREDITO', 'DEBITO'])->orderBy('data_referencia')->get();

        $colabIds = $marcacoes->pluck('colaborador_config_id')->merge($apuracoes->pluck('colaborador_config_id'))
            ->merge($bh->pluck('colaborador_config_id'))->unique()->sort()->values();
        $colabs = DB::table('ponto_colaborador_config as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.business_id', $biz)->whereIn('c.id', $colabIds)
            ->get(['c.id', 'c.cpf', 'c.escala_atual_id', 'u.first_name', 'u.last_name'])->keyBy('id');

        // 03 — vínculos
        $vinculo = [];
        $reg03 = [];
        foreach ($colabIds as $id) {
            $c = $colabs->get($id);
            $cpf = preg_replace('/\D/', '', (string) optional($c)->cpf);
            $nome = $this->texto(trim(optional($c)->first_name . ' ' . optional($c)->last_name), 150);
            if (strlen($cpf) !== 11) {
                $recusar('colaborador sem CPF');
                continue;
            }
            if ($nome === '') {
                $recusar('colaborador sem nome');
                continue;
            }
            $vinculo[$id] = count($vinculo) + 1;
            $reg03[] = ['03', $vinculo[$id], $cpf, $nome];
        }

        // 02 — REPs usados
        $reps = DB::table('ponto_reps')->where('business_id', $biz)
            ->whereIn('id', $marcacoes->pluck('rep_id')->filter()->unique())->get()->keyBy('id');
        $idRep = [];
        $reg02 = [];
        $repDe = function ($m) use ($reps, &$idRep, &$reg02, $recusar, $ptrp) {
            if ($m->rep_id === null && $m->origem !== 'REP_P') {
                return '';
            }
            $chave = $m->rep_id ?? 'REP_P';
            if (! isset($idRep[$chave])) {
                $rep = $m->rep_id ? $reps->get($m->rep_id) : null;
                $tp = $rep ? (self::MAP_TP_REP[$rep->tipo] ?? null) : '3';
                $nr = $rep ? trim((string) $rep->identificador) : $ptrp['inpi'];
                // REP inferido de AFD sem cabeçalho não tem nº de fabricação (AfdParserService::repFallback).
                if ($tp === null || $nr === '' || strlen($nr) > 17 || str_starts_with($nr, 'AFD-')) {
                    $recusar('marcação de REP sem número de fabricação/INPI');

                    return null;
                }
                $idRep[$chave] = count($idRep) + 1;
                $reg02[] = ['02', $idRep[$chave], $tp, $nr];
            }

            return $idRep[$chave];
        };

        // 04 + 05 — marcações, com o horário contratual do dia na primeira entrada
        $horarios = [];
        $reg05 = [];
        foreach ($marcacoes->groupBy(fn ($m) => $m->colaborador_config_id . '|' . substr((string) $m->momento, 0, 10)) as $dia => $doDia) {
            [$colab, $data] = explode('|', $dia);
            if (! isset($vinculo[$colab])) {
                continue; // já recusado no 03
            }
            $seq = 0;
            foreach ($doDia as $m) {
                $motivo = '';
                if ($anulacoes->has($m->id)) {
                    $tp = 'D';
                    $motivo = $this->texto((string) $anulacoes->get($m->id), 150);
                    if ($motivo === '') {
                        $recusar('marcação anulada sem motivo gravado');
                    }
                } elseif (in_array($m->tipo, ['ENTRADA', 'ALMOCO_FIM'], true)) {
                    $tp = 'E';
                } elseif (in_array($m->tipo, ['SAIDA', 'ALMOCO_INICIO'], true)) {
                    $tp = 'S';
                } else {
                    $recusar('marcação de intercorrência sem sentido entrada/saída');
                    continue;
                }
                $fonte = self::MAP_FONTE[$m->origem] ?? 'T';
                if ($fonte === 'I') {
                    $recusar('marcação manual sem motivo gravado');
                }
                if ($tp === 'E') {
                    $seq++;
                }
                $cod = '';
                if ($tp === 'E' && $seq === 1) {
                    $cod = $this->horario($biz, (int) $colab, $data, $apuracoes, $colabs, $horarios);
                    if ($cod === null) {
                        $recusar('dia sem horário contratual (sem turno na escala nem previsto na apuração)');
                        $cod = '';
                    }
                }
                $rep = $repDe($m);
                $reg05[] = ['05', $vinculo[$colab], $this->dh(Carbon::parse($m->momento)), (string) $rep,
                    $tp, max($seq, 1), $fonte, $cod, $motivo];
            }
        }

        // 07 — ausências e banco de horas
        $reg07 = [];
        foreach ($apuracoes as $a) {
            if ((int) $a->falta_minutos > 0 && isset($vinculo[$a->colaborador_config_id])) {
                $reg07[] = ['07', $vinculo[$a->colaborador_config_id], '2', substr((string) $a->data, 0, 10), '', ''];
            }
        }
        foreach ($bh as $mov) {
            if (isset($vinculo[$mov->colaborador_config_id]) && (int) $mov->minutos !== 0) {
                $reg07[] = ['07', $vinculo[$mov->colaborador_config_id], '3', substr((string) $mov->data_referencia, 0, 10),
                    abs((int) $mov->minutos), $mov->tipo === 'CREDITO' ? '1' : '2'];
            }
        }

        if ($falta !== []) {
            $partes = [];
            foreach ($falta as $motivo => $n) {
                $partes[] = "{$n} {$motivo}";
            }
            throw new \DomainException('AEJ indisponível — dado legal ausente no período: ' . implode('; ', $partes) . '.');
        }

        $reg04 = array_values($horarios);
        $linhas = [['01', strlen($doc) === 14 ? '1' : '2', $doc, '', '', $this->texto($razao, 150),
            $inicio->toDateString(), $fim->toDateString(), $this->dh(now()), '002']];
        array_push($linhas, ...$reg02, ...$reg03, ...$reg04, ...$reg05, ...$reg07);
        $linhas[] = ['08', $ptrp['nome'], $ptrp['versao'], strlen($ptrp['cnpj']) === 14 ? '1' : '2',
            $ptrp['cnpj'], $ptrp['razao'], $ptrp['email']];
        $linhas[] = ['99', 1, count($reg02), count($reg03), count($reg04), count($reg05), 0, count($reg07), 1];

        $texto = implode("\r\n", array_map(fn ($l) => implode('|', $l), $linhas)) . "\r\n"
            . str_pad('ASSINATURA_DIGITAL_EM_ARQUIVO_P7S', 100) . "\r\n";

        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }

    /** Código do horário contratual do dia (registra o 04 na 1ª vez) ou null se não houver fonte. */
    private function horario(int $biz, int $colab, string $data, $apuracoes, $colabs, array &$horarios): ?string
    {
        $ap = $apuracoes->get($colab . '|' . $data);
        $escala = $ap->escala_id ?? optional($colabs->get($colab))->escala_atual_id;
        $turno = $escala ? DB::table('ponto_escala_turnos as t')->join('ponto_escalas as e', 'e.id', '=', 't.escala_id')
            ->where('e.business_id', $biz)->where('t.escala_id', $escala)
            ->where('t.dia_semana', Carbon::parse($data)->dayOfWeek)->first(['t.*']) : null;

        if ($turno) {
            $pares = $turno->hora_almoco_inicio && $turno->hora_almoco_fim
                ? [[$turno->hora_entrada, $turno->hora_almoco_inicio], [$turno->hora_almoco_fim, $turno->hora_saida]]
                : [[$turno->hora_entrada, $turno->hora_saida]];
            $cod = 'E' . $escala . 'D' . $turno->dia_semana;
        } elseif ($ap && $ap->prevista_entrada && $ap->prevista_saida) {
            $pares = [[$ap->prevista_entrada, $ap->prevista_saida]];
            $cod = 'P' . $this->hhmm($ap->prevista_entrada) . $this->hhmm($ap->prevista_saida) . (int) $ap->prevista_carga_minutos;
        } else {
            return null;
        }

        if (! isset($horarios[$cod])) {
            $dur = $turno ? array_sum(array_map(fn ($p) => $this->minutos($p[0], $p[1]), $pares)) : (int) $ap->prevista_carga_minutos;
            $horarios[$cod] = array_merge(['04', $cod, $dur], ...array_map(fn ($p) => [$this->hhmm($p[0]), $this->hhmm($p[1])], $pares));
        }

        return $cod;
    }

    /** ['nome','versao','cnpj','razao','email','inpi'] ou null se a identidade do PTRP estiver incompleta. */
    private function ptrp(): ?array
    {
        $p = [
            'nome'   => $this->texto((string) config('ponto_afd.ptrp_nome'), 150),
            'versao' => $this->texto((string) config('ponto_afd.ptrp_versao'), 8),
            'cnpj'   => preg_replace('/\D/', '', (string) config('ponto_afd.desenvolvedor_cnpj')),
            'razao'  => $this->texto((string) config('ponto_afd.ptrp_razao'), 150),
            'email'  => $this->texto((string) config('ponto_afd.ptrp_email'), 50),
            'inpi'   => trim((string) config('ponto_afd.rep_p_inpi')),
        ];

        foreach (['nome', 'versao', 'razao', 'email'] as $k) {
            if ($p[$k] === '') {
                return null;
            }
        }

        return in_array(strlen($p['cnpj']), [11, 14], true) ? $p : null;
    }

    /** Campo alfanumérico: sem o delimitador "|" e sem quebra de linha, cortado no tamanho do leiaute. */
    private function texto(string $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/[|\r\n]+/', ' ', $v)), 0, $max);
    }

    private function dh(Carbon $c): string
    {
        return $c->format('Y-m-d\TH:i') . ':00' . $c->format('O');
    }

    private function hhmm($hora): string
    {
        return substr(str_replace(':', '', (string) $hora), 0, 4);
    }

    /** Minutos entre dois horários do mesmo turno; virada de meia-noite soma 24h. */
    private function minutos($de, $ate): int
    {
        $a = Carbon::parse('2000-01-01 ' . $de);
        $b = Carbon::parse('2000-01-01 ' . $ate);

        return (int) ($b->lt($a) ? $b->addDay() : $b)->diffInMinutes($a, true);
    }
}
