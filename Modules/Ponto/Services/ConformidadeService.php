<?php

namespace Modules\Ponto\Services;

use Carbon\Carbon;
use Modules\Ponto\Entities\ApuracaoDia;
use Modules\Ponto\Entities\Colaborador;

/**
 * Painel de Conformidade CLT — as 6 verificações da competência (thread 05 do playbook Ponto).
 *
 * SOMENTE LEITURA (ADR 0413 D0): nada aqui escreve. A correção acontece em Intercorrências
 * ou no Espelho.
 *
 * NÃO reimplementa apuração. Quem calcula as regras CLT é o `ApuracaoService`
 * (RN-003 Art. 71, RN-004 Art. 66, RN-006 Art. 59) e grava o resultado em
 * `ponto_apuracao_dia` (`*_violacao_minutos` + `divergencias[].chave`). Este service só
 * AGREGA o que já foi gravado — se a apuração do dia não rodou, o painel não inventa.
 *
 * NSR fora de sequência fica "não medido" (`medido: false`): a apuração não expõe a
 * sequência de NSR por colaborador, e a thread proíbe query nova para suprir isso
 * ("PARAR SE uma regra exigir dado que ApuracaoService não expõe → '—'").
 *
 * Tier 0 (ADR 0093): `business_id` explícito em toda query, além do global scope.
 */
class ConformidadeService
{
    /**
     * id => [titulo, artigo, tom]. `artigo` null = sem fonte legal citável: vira conferência,
     * não apontamento (Lei da thread 05: "sem artigo, não é apontamento").
     */
    public const VERIFICACOES = [
        'jornada_aberta' => ['Jornada sem fechamento', 'CLT Art. 74 §2º — registro de entrada e saída', 'warn'],
        'interjornada'   => ['Interjornada abaixo do mínimo', 'CLT Art. 66 — 11h entre jornadas', 'danger'],
        'intrajornada'   => ['Intrajornada abaixo do mínimo', 'CLT Art. 71 — 1h acima de 6h de jornada', 'danger'],
        'he'             => ['Hora extra acima do limite diário', 'CLT Art. 59 — até 2h/dia', 'warn'],
        'nsr'            => ['NSR fora de sequência', 'Portaria MTP 671/2021 Anexo I — NSR sequencial', 'danger'],
        'sem_pis'        => ['Colaborador ativo sem PIS', null, 'warn'],
    ];

    /**
     * @return array{mes:string, verificacoes:array<int,array<string,mixed>>, casos:array<string,array<int,array<string,mixed>>>}
     */
    public function competencia(int $businessId, string $mes): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $mes . '-01')->startOfDay();
        $fim = $inicio->copy()->endOfMonth();

        $casos = array_fill_keys(array_keys(self::VERIFICACOES), []);

        $colabs = Colaborador::where('business_id', $businessId)
            ->with('user:id,first_name,last_name')
            ->get()
            ->keyBy('id');

        $interMin = (int) config('pontowr2.clt.interjornada_minima_horas', 11) * 60;
        $intraMin = (int) config('pontowr2.clt.intrajornada_minima_minutos', 60);
        $heLimite = (int) config('pontowr2.clt.limite_he_diaria_horas', 2) * 60;

        $dias = ApuracaoDia::where('business_id', $businessId)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->orderBy('data')
            ->get();

        foreach ($dias as $d) {
            $c = $colabs->get($d->colaborador_config_id);
            if (! $c) {
                continue; // colaborador de outro tenant nunca chega aqui (where acima); defesa
            }
            $chaves = array_column(is_array($d->divergencias) ? $d->divergencias : [], 'chave');
            $dia = $d->data->toDateString();

            if (((int) $d->qtd_marcacoes) % 2 === 1) {
                $casos['jornada_aberta'][] = $this->caso($c, $dia, $d->qtd_marcacoes . ' marcações', 'par', 'jornada sem registro de saída');
            } elseif (in_array('falta', $chaves, true)) {
                $casos['jornada_aberta'][] = $this->caso($c, $dia, 'sem marcação', 'par', 'dia previsto sem marcação e sem intercorrência');
            }
            if ((int) $d->interjornada_violacao_minutos > 0) {
                $casos['interjornada'][] = $this->caso($c, $dia, $this->hm($interMin - (int) $d->interjornada_violacao_minutos), $this->hm($interMin), 'intervalo desde a última saída do dia anterior');
            }
            if ((int) $d->intrajornada_violacao_minutos > 0) {
                $casos['intrajornada'][] = $this->caso($c, $dia, $this->hm((int) $d->realizada_intrajornada_minutos), $this->hm($intraMin), 'intervalo de refeição concedido');
            }
            if (in_array('he_acima_limite', $chaves, true)) {
                $he = (int) $d->realizada_trabalhada_minutos - (int) $d->prevista_carga_minutos;
                $casos['he'][] = $this->caso($c, $dia, $this->hm($he), $this->hm($heLimite), 'conferir intercorrência que autorize o excedente');
            }
        }

        foreach ($colabs as $c) {
            if ($c->controla_ponto && $c->desligamento === null && trim((string) $c->pis) === '') {
                $casos['sem_pis'][] = $this->caso($c, null, 'sem PIS', '—', 'a importação de AFD rejeita a marcação sem PIS cadastrado');
            }
        }

        $verificacoes = [];
        foreach (self::VERIFICACOES as $id => [$titulo, $artigo, $tom]) {
            $medido = $id !== 'nsr';
            $verificacoes[] = [
                'id'     => $id,
                'titulo' => $titulo,
                'artigo' => $artigo,
                'tom'    => $tom,
                'medido' => $medido,
                'total'  => $medido ? count($casos[$id]) : null,
            ];
        }

        return ['mes' => $mes, 'verificacoes' => $verificacoes, 'casos' => $casos];
    }

    private function caso(Colaborador $c, ?string $dia, string $apurado, string $limite, string $detalhe): array
    {
        return [
            'colaborador_id' => $c->id,
            'nome'           => trim(optional($c->user)->first_name . ' ' . optional($c->user)->last_name) ?: '—',
            'matricula'      => $c->matricula,
            'dia'            => $dia,
            'apurado'        => $apurado,
            'limite'         => $limite,
            'detalhe'        => $detalhe,
        ];
    }

    private function hm(int $min): string
    {
        $min = max(0, $min);

        return sprintf('%dh%02d', intdiv($min, 60), $min % 60);
    }
}
