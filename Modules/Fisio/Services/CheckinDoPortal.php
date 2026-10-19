<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use App\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Events\CheckinRegistrado;
use Modules\Fisio\Notifications\SinalDeAlertaNotification;

/**
 * "Como foi hoje" — o check-in do portal do paciente (US-FISIO-007b; ADR 0437 §5 a §7;
 * etapa 02 §3.5). Quem chama (PortalController) já passou pelos portões: link válido, PIN
 * (ou aparelho lembrado) e consentimento de teleatendimento vigente.
 *
 * Invariantes:
 *  - UM check-in por paciente e dia civil (UNIQUE no banco); reenviar no mesmo dia ATUALIZA.
 *  - Envio com a `chave` já aplicada ao dia (fila offline) não grava nada e responde sucesso.
 *  - Só itens do programa ATIVO do próprio paciente, na própria clínica; repetidos contam 1x.
 *  - Cada dia vira 1 `fisio_records` kind=telemonitoramento (assíncrono, canal portal,
 *    assinado pelo sistema). Reenvio com relato diferente = 1 `adendo` apontando para ele;
 *    reenvio idêntico não grava nada no prontuário (UC-FPP-10).
 *  - O jogo recebe o check-in SÓ pelo evento `CheckinRegistrado` (JogoService lança a
 *    diferença positiva no ledger); aqui nada toca o ledger.
 *  - Sinal de alerta novo notifica o profissional na hora (UC-FPP-13).
 *  - Dor ≥ limiar do programa ou sinal de alerta → modo `pausa`: o paciente vê SÓ a
 *    `stop_instruction` escrita pelo profissional, nada calculado (UC-FPP-19, Anvisa).
 *  - Escopo: tudo filtra pelo business do paciente (que veio assinado no token).
 */
final class CheckinDoPortal
{
    public const MODO_NORMAL = 'normal';

    public const MODO_PAUSA = 'pausa';

    public const ESFORCOS = ['facil', 'adequado', 'dificil'];

    public const NOTA_MAX = 280;

    public function __construct(private readonly JogoService $jogo) {}

    /**
     * Grava (ou atualiza) o check-in do dia, o telemonitoramento e dispara o jogo.
     *
     * @param  array{done_item_ids: list<int|string>, vezes?: array<int|string,int|string>, pain: ?int, effort: string, note?: ?string, red_flags?: ?list<string>, crisis?: ?bool, chave?: ?string}  $dados  já validado no formato
     * @return array{checkin: Checkin, criado: bool, xp_ganho: int}
     *
     * @throws ValidationException item fora do programa ativo, ou crise sem versão de crise
     */
    public function registrar(Patient $paciente, array $dados): array
    {
        // Reenvio da fila offline com a chave que JÁ foi aplicada: a resposta é que se perdeu.
        // Nada é gravado de novo — nem check-in, nem prontuário, nem jogo (UC-FPP-30).
        $chave = ($dados['chave'] ?? '') !== '' ? (string) $dados['chave'] : null;
        $hoje = $chave !== null ? $this->checkinDeHoje($paciente) : null;
        if ($hoje !== null && $hoje->client_key === $chave) {
            return ['checkin' => $hoje, 'criado' => false, 'xp_ganho' => 0];
        }

        $programa = $this->programaAtivo($paciente);
        $feitos = array_values(array_unique(array_map('intval', $dados['done_item_ids'])));

        $permitidos = $programa === null ? [] : $this->idsDosItens($programa);
        if (array_diff($feitos, $permitidos) !== []) {
            throw ValidationException::withMessages(['done_item_ids' => 'Há exercício que não faz parte do seu programa de hoje.']);
        }
        $crise = (bool) ($dados['crisis'] ?? false);
        if ($crise && ($programa === null || ! $this->temVersaoDeCrise($programa))) {
            throw ValidationException::withMessages(['crisis' => 'Seu programa não tem versão de crise.']);
        }

        $flags = array_values(array_unique(array_map('strval', (array) ($dados['red_flags'] ?? []))));
        $nota = isset($dados['note']) && trim((string) $dados['note']) !== '' ? trim((string) $dados['note']) : null;
        $vezes = $this->vezesDosFeitos($feitos, (array) ($dados['vezes'] ?? []));
        $campos = [
            'program_id' => $programa?->id,
            'done_item_ids' => $feitos,
            'pain' => isset($dados['pain']) ? (int) $dados['pain'] : null,
            'effort' => (string) $dados['effort'],
            'note' => $nota,
            'red_flags' => $flags,
            'crisis' => $crise,
            'client_key' => $chave,
        ];
        $dia = DiaLocal::de(now());
        $xpAntes = $this->jogo->xpTotal($paciente);

        try {
            [$checkin, $criado, $flagsAntes] = $this->gravar($paciente, $dia, $campos, $vezes);
        } catch (UniqueConstraintViolationException) {
            // Dois envios simultâneos do mesmo dia: o segundo vira atualização do primeiro.
            [$checkin, $criado, $flagsAntes] = $this->gravar($paciente, $dia, $campos, $vezes);
        }

        // Ponto de entrada ÚNICO do jogo (US-FISIO-008): depois de gravar, criar OU reenviar.
        event(new CheckinRegistrado((int) $checkin->business_id, (int) $checkin->id, $vezes));

        $novos = array_values(array_diff($flags, $flagsAntes));
        if ($novos !== []) {
            $this->avisarProfissional($paciente, $programa, $checkin, $flags);
        }

        return ['checkin' => $checkin, 'criado' => $criado, 'xp_ganho' => max(0, $this->jogo->xpTotal($paciente) - $xpAntes)];
    }

    /**
     * Modo do portal hoje: `pausa` quando o check-in do dia tem dor ≥ limiar do programa ou
     * sinal de alerta; `normal` caso contrário (inclusive sem check-in ainda).
     */
    public function modo(Patient $paciente, ?Checkin $hoje): string
    {
        if ($hoje === null) {
            return self::MODO_NORMAL;
        }
        if (! empty($hoje->red_flags)) {
            return self::MODO_PAUSA;
        }
        if ($hoje->pain === null) {
            return self::MODO_NORMAL;
        }
        $programa = $this->programaAtivo($paciente);
        $limiar = $this->jogo->limiarDeDor($programa, $this->historicoParaLimiar($paciente, $programa));

        return (int) $hoje->pain >= $limiar ? self::MODO_PAUSA : self::MODO_NORMAL;
    }

    /** Instrução de parada escrita pelo profissional no programa ativo (texto estático). */
    public function instrucao(Patient $paciente): ?string
    {
        $texto = $this->programaAtivo($paciente)?->stop_instruction;

        return is_string($texto) && trim($texto) !== '' ? $texto : null;
    }

    public function checkinDeHoje(Patient $paciente): ?Checkin
    {
        return Checkin::withoutGlobalScopes() // SUPERADMIN: portal público; filtra pelo business do token.
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', $paciente->id)
            ->where('date', DiaLocal::de(now()))
            ->first();
    }

    public function programaAtivo(Patient $paciente): ?Program
    {
        return Program::withoutGlobalScopes() // SUPERADMIN: portal público sem sessão; filtra pelo business do token.
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', $paciente->id)
            ->where('status', Program::STATUS_ATIVO)
            ->orderByDesc('id')
            ->first();
    }

    /** @return list<string> versão de crise escrita pelo profissional (protocolo 11) */
    public function versaoDeCrise(?Program $programa): array
    {
        return $programa === null ? [] : array_values(array_filter(array_map('strval', (array) ($programa->crisis_items ?? [])), fn (string $l) => trim($l) !== ''));
    }

    private function temVersaoDeCrise(Program $programa): bool
    {
        return $this->versaoDeCrise($programa) !== [];
    }

    /**
     * @param  array<string, mixed>  $campos
     * @param  array<int,int>  $vezes
     * @return array{0: Checkin, 1: bool, 2: list<string>}
     */
    private function gravar(Patient $paciente, string $dia, array $campos, array $vezes): array
    {
        $biz = (int) $paciente->business_id;

        return DB::transaction(function () use ($paciente, $biz, $dia, $campos, $vezes): array {
            $checkin = Checkin::withoutGlobalScopes() // SUPERADMIN: portal público; filtra pelo business do token.
                ->where('business_id', $biz)
                ->where('patient_id', $paciente->id)
                ->where('date', $dia)
                ->lockForUpdate()
                ->first();

            $criado = $checkin === null;
            $flagsAntes = $criado ? [] : array_values(array_map('strval', (array) ($checkin->red_flags ?? [])));
            if ($criado) {
                $checkin = Checkin::query()->create(['business_id' => $biz, 'patient_id' => (int) $paciente->id, 'date' => $dia] + $campos);
            } else {
                $checkin->fill($campos)->save();
            }

            $this->registrarNoProntuario($checkin, $this->corpoDoRelato($checkin, $vezes));

            return [$checkin, $criado, $flagsAntes];
        });
    }

    /**
     * Telemonitoramento do dia (1º envio) ou adendo (reenvio com relato diferente). O registro
     * original é assinado pelo sistema e fica intacto (append-only, Res. COFFITO 414/2012).
     *
     * @param  array<string, mixed>  $corpo
     */
    private function registrarNoProntuario(Checkin $checkin, array $corpo): void
    {
        $biz = (int) $checkin->business_id;
        $doDia = Record::withoutGlobalScopes() // SUPERADMIN: portal público; filtra pelo business do check-in.
            ->where('business_id', $biz)
            ->where('patient_id', $checkin->patient_id)
            ->where('checkin_id', $checkin->id)
            ->orderBy('id')
            ->get();
        $tele = $doDia->firstWhere('kind', Record::KIND_TELEMONITORAMENTO);
        $base = [
            'business_id' => $biz,
            'patient_id' => (int) $checkin->patient_id,
            'modality' => 'assincrona',
            'channel' => 'portal',
            'signed_by' => null,
            'signed_crefito' => null,
            'signed_at' => now(),
            'checkin_id' => (int) $checkin->id,
        ];

        if ($tele === null) {
            Record::query()->create($base + ['kind' => Record::KIND_TELEMONITORAMENTO, 'body' => $corpo, 'amends_id' => null]);

            return;
        }

        $ultimo = $doDia->last() ?? $tele;
        if ($this->mesmoRelato((array) $ultimo->body, $corpo)) {
            return;
        }
        Record::query()->create($base + ['kind' => Record::KIND_ADENDO, 'body' => $corpo, 'amends_id' => (int) $tele->id]);
    }

    /**
     * Corpo resumido do relato do paciente (etapa 02 §3.5): itens, vezes, dor, esforço,
     * observação, sinais e crise. Autor: o próprio paciente pelo portal.
     *
     * @param  array<int,int>  $vezes
     * @return array<string, mixed>
     */
    private function corpoDoRelato(Checkin $c, array $vezes): array
    {
        return [
            'autor' => 'paciente_portal',
            'itens_feitos' => array_values(array_map('intval', (array) ($c->done_item_ids ?? []))),
            // Texto legível na ficha e no PDF do prontuário: "item 12: 2x".
            'vezes' => array_map(fn (int $id, int $n) => "item {$id}: {$n}x", array_keys($vezes), $vezes),
            'pain' => $c->pain !== null ? (int) $c->pain : null,
            'effort' => $c->effort,
            'note' => $c->note,
            'red_flags' => array_values((array) ($c->red_flags ?? [])),
            'crisis' => (bool) $c->crisis,
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function mesmoRelato(array $a, array $b): bool
    {
        $norm = static function (array $x): string {
            ksort($x);

            return (string) json_encode($x);
        };

        return $norm($a) === $norm($b);
    }

    /**
     * @param  list<int>  $feitos
     * @param  array<int|string,mixed>  $vezes
     * @return array<int,int> só dos itens marcados como feitos (sem número = 1)
     */
    private function vezesDosFeitos(array $feitos, array $vezes): array
    {
        $out = [];
        foreach ($feitos as $id) {
            $out[$id] = max(1, (int) ($vezes[$id] ?? $vezes[(string) $id] ?? 1));
        }

        return $out;
    }

    /** @return list<int> */
    private function idsDosItens(Program $programa): array
    {
        return ProgramItem::withoutGlobalScopes() // SUPERADMIN: portal público; filtra pelo business do programa.
            ->where('business_id', (int) $programa->business_id)
            ->where('program_id', $programa->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<array{date:string, done_count:int, pain:?int, red_flags:list<string>, crise:bool}> */
    private function historicoParaLimiar(Patient $paciente, ?Program $programa): array
    {
        if ($programa === null) {
            return [];
        }
        $out = [];
        foreach (Checkin::withoutGlobalScopes() // SUPERADMIN: portal público; filtra pelo business do token.
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', $paciente->id)
            ->where('date', '>=', JogoService::inicioDoPrograma($programa))
            ->orderBy('date')
            ->get() as $c) {
            $out[] = JogoService::paraCalculo($c);
        }

        return $out;
    }

    /**
     * Profissional do programa e o responsável pelo paciente, da MESMA clínica.
     *
     * @param  list<string>  $flags
     */
    private function avisarProfissional(Patient $paciente, ?Program $programa, Checkin $checkin, array $flags): void
    {
        $ids = array_values(array_unique(array_filter([
            $programa?->created_by !== null ? (int) $programa->created_by : null,
            $paciente->responsible_user_id !== null ? (int) $paciente->responsible_user_id : null,
        ])));
        if ($ids === []) {
            return;
        }
        $destinatarios = User::query()->whereIn('id', $ids)->where('business_id', (int) $paciente->business_id)->get();
        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::send($destinatarios, new SinalDeAlertaNotification((int) $paciente->business_id, (int) $paciente->id, (int) $checkin->id, $flags));
    }
}
