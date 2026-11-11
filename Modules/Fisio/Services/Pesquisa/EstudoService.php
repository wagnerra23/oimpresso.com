<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Pesquisa;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Study;
use Modules\Fisio\Entities\StudyMoment;
use Modules\Fisio\Entities\StudyParticipant;
use Modules\Fisio\Services\Consentimentos;
use Modules\Fisio\Services\Questionarios\CatalogoQuestionarios;

/**
 * Estudo de pesquisa da clínica (etapa 07 P-04, ADR 0437 §7): criar o estudo com os momentos de
 * avaliação, incluir o participante (com o aceite do TCLE na versão do estudo), registrar a
 * saída e calcular as pendências.
 *
 * Regras:
 * - tudo na empresa do estudo; paciente de outra empresa nunca entra (ADR 0093);
 * - o código do participante é aleatório e único no estudo, sem nada do nome ou do CPF;
 * - saída tem tipo e motivo, uma vez só, e a linha fica (perda de seguimento não se apaga);
 * - pendência é derivada: momento + data de entrada + questionário respondido na janela.
 *
 * O software organiza o estudo; não decide conduta (ADR 0437 §5).
 */
class EstudoService
{
    /** Letras e números sem os que se confundem (0/O, 1/I/L, 2/Z, 5/S, 8/B). */
    private const ALFABETO_CODIGO = 'ACDEFGHJKMNPQRTUVWXY3479';

    public function __construct(private readonly Consentimentos $consentimentos) {}

    /**
     * @param  array{title:string,cep_opinion_number:string,protocol_version:string,tcle_version:string,starts_on:string,ends_on?:?string,responsible_user_id?:?int}  $dados
     * @param  list<array{label:string,day_offset:int,window_days:int,instruments:list<string>}>  $momentos
     */
    public function criar(int $businessId, array $dados, array $momentos, ?int $autor): Study
    {
        foreach ($momentos as $m) {
            $fora = array_values(array_filter($m['instruments'], fn (string $c) => ! CatalogoQuestionarios::disponivel($c)));
            if ($fora !== []) {
                throw new InvalidArgumentException('instrumento fora da lista liberada: '.implode(', ', $fora));
            }
        }

        return DB::transaction(function () use ($businessId, $dados, $momentos, $autor): Study {
            $estudo = Study::withoutGlobalScopes()->create([ // SUPERADMIN: grava na empresa passada explicitamente.
                'business_id' => $businessId,
                'title' => $dados['title'],
                'cep_opinion_number' => $dados['cep_opinion_number'],
                'protocol_version' => $dados['protocol_version'],
                'tcle_version' => $dados['tcle_version'],
                'starts_on' => $dados['starts_on'],
                'ends_on' => $dados['ends_on'] ?? null,
                'responsible_user_id' => $dados['responsible_user_id'] ?? null,
                'status' => Study::STATUS_ATIVO,
                'created_by' => $autor,
            ]);
            foreach ($momentos as $i => $m) {
                StudyMoment::withoutGlobalScopes()->create([ // SUPERADMIN: mesma empresa do estudo.
                    'business_id' => $businessId,
                    'study_id' => $estudo->id,
                    'label' => $m['label'],
                    'day_offset' => $m['day_offset'],
                    'window_days' => $m['window_days'],
                    'instruments' => array_values(array_unique($m['instruments'])),
                    'sort' => $i,
                ]);
            }

            return $estudo;
        });
    }

    /** Inclui o paciente e grava o aceite do TCLE (canal clínica). */
    public function incluir(Study $estudo, Patient $paciente, ?int $autor, ?string $ip, ?CarbonInterface $entrada = null): StudyParticipant
    {
        if ((int) $estudo->business_id !== (int) $paciente->business_id) {
            throw new InvalidArgumentException('paciente de outra empresa');
        }
        if ($this->participante($estudo, $paciente) !== null) {
            throw new InvalidArgumentException('paciente já incluído neste estudo');
        }

        return DB::transaction(function () use ($estudo, $paciente, $autor, $ip, $entrada): StudyParticipant {
            $aceite = $this->consentimentos->concederPesquisa($paciente, $estudo, $ip, 'clinica');

            return StudyParticipant::withoutGlobalScopes()->create([ // SUPERADMIN: mesma empresa do estudo.
                'business_id' => (int) $estudo->business_id,
                'study_id' => (int) $estudo->id,
                'patient_id' => (int) $paciente->id,
                'code' => $this->novoCodigo($estudo),
                'entered_on' => ($entrada ?? now())->toDateString(),
                'consent_id' => $aceite['consentimento']->id,
                'created_by' => $autor,
            ]);
        });
    }

    /** Registra a saída uma vez só, com tipo e motivo. A linha fica. */
    public function registrarSaida(StudyParticipant $p, string $tipo, string $motivo, ?int $autor, ?CarbonInterface $data = null): StudyParticipant
    {
        if (! in_array($tipo, StudyParticipant::SAIDAS, true)) {
            throw new InvalidArgumentException('tipo de saída inválido');
        }
        if (trim($motivo) === '') {
            throw new InvalidArgumentException('motivo da saída é obrigatório');
        }
        if ($p->exited_on !== null) {
            throw new InvalidArgumentException('participante já saiu do estudo');
        }
        $p->forceFill([
            'exited_on' => ($data ?? now())->toDateString(),
            'exit_type' => $tipo,
            'exit_reason' => mb_substr(trim($motivo), 0, 500),
            'exited_by' => $autor,
        ])->save();

        return $p;
    }

    /** Revogação da pesquisa pelo portal: o participante sai do estudo naquela data. */
    public function sairPorRevogacao(Patient $paciente, Study $estudo): ?StudyParticipant
    {
        $p = $this->participante($estudo, $paciente);
        if ($p === null || $p->exited_on !== null) {
            return $p;
        }

        return $this->registrarSaida($p, StudyParticipant::SAIDA_REVOGACAO, 'Consentimento de pesquisa revogado pelo paciente no portal.', null);
    }

    public function participante(Study $estudo, Patient $paciente): ?StudyParticipant
    {
        return StudyParticipant::withoutGlobalScopes() // SUPERADMIN: roda também no portal, sem sessão; filtra pela empresa do estudo.
            ->where('business_id', (int) $estudo->business_id)
            ->where('study_id', (int) $estudo->id)
            ->where('patient_id', (int) $paciente->id)
            ->first();
    }

    /**
     * Pendências do estudo em `$hoje`: um item por participante × momento que ainda não está
     * completo. `situacao`: `futuro` (janela não abriu), `aberto` (na janela) ou `vencido`.
     * Quem saiu do estudo não tem pendência: o que faltou sai como ausente na exportação.
     *
     * @return list<array{participant_id:int,code:string,patient_id:int,moment_id:int,moment:string,inicio:string,fim:string,faltando:list<string>,situacao:string}>
     */
    public function pendencias(Study $estudo, CarbonInterface $hoje): array
    {
        $biz = (int) $estudo->business_id;
        $momentos = StudyMoment::withoutGlobalScopes() // SUPERADMIN: empresa do estudo, explícita.
            ->where('business_id', $biz)->where('study_id', $estudo->id)
            ->orderBy('sort')->orderBy('day_offset')->get();
        $participantes = StudyParticipant::withoutGlobalScopes() // SUPERADMIN: empresa do estudo, explícita.
            ->where('business_id', $biz)->where('study_id', $estudo->id)->whereNull('exited_on')->orderBy('code')->get();
        if ($momentos->isEmpty() || $participantes->isEmpty()) {
            return [];
        }

        $respostas = DB::table('fisio_questionnaires')
            ->where('business_id', $biz)
            ->whereIn('patient_id', $participantes->pluck('patient_id')->all())
            ->get(['patient_id', 'instrument', 'answered_at'])
            ->groupBy('patient_id');

        $dia = CarbonImmutable::parse($hoje->toDateString());
        $out = [];
        foreach ($participantes as $p) {
            $entrada = CarbonImmutable::parse($p->entered_on->toDateString());
            foreach ($momentos as $m) {
                $alvo = $entrada->addDays($m->day_offset);
                $inicio = $alvo->subDays($m->window_days);
                $fim = $alvo->addDays($m->window_days);
                $doPaciente = $respostas->get($p->patient_id, collect());
                $faltando = array_values(array_filter((array) $m->instruments, fn (string $instr) => ! $doPaciente->contains(
                    fn ($r) => $r->instrument === $instr
                        && ($d = CarbonImmutable::parse($r->answered_at)->startOfDay())->greaterThanOrEqualTo($inicio)
                        && $d->lessThanOrEqualTo($fim)
                )));
                if ($faltando === []) {
                    continue;
                }
                $out[] = [
                    'participant_id' => (int) $p->id,
                    'code' => (string) $p->code,
                    'patient_id' => (int) $p->patient_id,
                    'moment_id' => (int) $m->id,
                    'moment' => (string) $m->label,
                    'inicio' => $inicio->toDateString(),
                    'fim' => $fim->toDateString(),
                    'faltando' => $faltando,
                    'situacao' => $dia->lessThan($inicio) ? 'futuro' : ($dia->greaterThan($fim) ? 'vencido' : 'aberto'),
                ];
            }
        }

        return $out;
    }

    /**
     * Avaliações do estudo em aberto para o paciente hoje (etapa 07 P-05): só a janela que já
     * abriu e ainda não fechou, de estudo ativo em que ele está. É o que o portal e o WhatsApp
     * cobram; o questionário em si é aplicado pela clínica enquanto o texto validado não está no
     * catálogo (`texto_pendente`, ADR 0437 §8).
     *
     * @return list<array{study_id:int,participant_id:int,moment_id:int,momento:string,ate:string,questionarios:list<string>}>
     */
    public function avaliacoesAbertas(Patient $paciente, CarbonInterface $hoje): array
    {
        $estudos = Study::withoutGlobalScopes() // SUPERADMIN: roda no portal e em job, sem sessão; empresa do paciente.
            ->where('business_id', (int) $paciente->business_id)
            ->where('status', Study::STATUS_ATIVO)
            ->whereIn('id', StudyParticipant::withoutGlobalScopes() // SUPERADMIN: idem.
                ->where('business_id', (int) $paciente->business_id)
                ->where('patient_id', (int) $paciente->id)->whereNull('exited_on')->select('study_id'))
            ->get();

        $out = [];
        foreach ($estudos as $estudo) {
            foreach ($this->pendencias($estudo, $hoje) as $p) {
                if ($p['patient_id'] !== (int) $paciente->id || $p['situacao'] !== 'aberto') {
                    continue;
                }
                $out[] = [
                    'study_id' => (int) $estudo->id,
                    'participant_id' => $p['participant_id'],
                    'moment_id' => $p['moment_id'],
                    'momento' => $p['moment'],
                    'ate' => $p['fim'],
                    'questionarios' => array_map(
                        fn (string $c) => (string) (CatalogoQuestionarios::definicao($c)['nome'] ?? $c),
                        $p['faltando'],
                    ),
                ];
            }
        }

        return $out;
    }

    private function novoCodigo(Study $estudo): string
    {
        $max = strlen(self::ALFABETO_CODIGO) - 1;
        do {
            $codigo = 'P-';
            for ($i = 0; $i < 6; $i++) {
                $codigo .= self::ALFABETO_CODIGO[random_int(0, $max)];
            }
            $existe = StudyParticipant::withoutGlobalScopes() // SUPERADMIN: unicidade dentro do estudo.
                ->where('study_id', (int) $estudo->id)->where('code', $codigo)->exists();
        } while ($existe);

        return $codigo;
    }
}
