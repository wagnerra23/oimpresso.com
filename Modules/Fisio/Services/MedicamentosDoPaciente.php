<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\MedicamentoPaciente;
use Modules\Fisio\Entities\Patient;

/**
 * Medicamentos em uso, estruturados (US-FISIO-063, parte 2).
 *
 * Convive com o texto livre "Medicamentos em uso" da avaliação, que não muda: aquele é o que o
 * profissional escreveu e assinou num dia; esta lista é o que está em uso agora.
 *
 * Nada aqui interpreta: o sistema não confere interação, dose máxima nem contraindicação
 * (ADR 0437 §5). Ele guarda o que o profissional digitou ou conferiu.
 *
 * Alterar grava o antes e o depois no `activity_log`; tirar da lista grava motivo e quem tirou.
 */
final class MedicamentosDoPaciente
{
    public const LOG = 'fisio';

    /** @return array<string, mixed> */
    public static function regras(bool $parcial = false): array
    {
        $nome = $parcial ? ['sometimes', 'required', 'string', 'max:120'] : ['required', 'string', 'max:120'];

        return [
            'nome' => $nome,
            'dose' => ['nullable', 'string', 'max:60'],
            'frequencia' => ['nullable', 'string', 'max:120'],
            'desde' => ['nullable', 'date'],
            // A ordem desde/até é conferida contra o que fica gravado (pode vir só um dos dois).
            'ate' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    public static function mensagens(): array
    {
        return [
            'nome.required' => 'Diga o nome do medicamento.',
        ];
    }

    /**
     * @param  array<string, mixed>  $dados  nome, dose, frequencia, desde, ate
     * @param  array{source?: string, document_id?: ?int, confirmed_by?: ?int}  $origem
     */
    public function incluir(Patient $paciente, array $dados, User $autor, array $origem = []): MedicamentoPaciente
    {
        $cols = $this->colunas($dados);
        self::exigirOrdem($cols['started_on'], $cols['ended_on']);
        $m = MedicamentoPaciente::withoutGlobalScopes() // SUPERADMIN: grava na empresa do PRÓPRIO paciente, conferido antes.
            ->create(array_merge($cols, [
                'business_id' => (int) $paciente->business_id,
                'patient_id' => (int) $paciente->id,
                'source' => $origem['source'] ?? MedicamentoPaciente::MANUAL,
                'document_id' => $origem['document_id'] ?? null,
                'created_by' => (int) $autor->id,
                'confirmed_by' => $origem['confirmed_by'] ?? null,
                'confirmed_at' => isset($origem['confirmed_by']) ? now() : null,
            ]));

        return $m;
    }

    /** Muda só o que veio e grava o antes e o depois. Tirado da lista não muda. */
    public function alterar(MedicamentoPaciente $m, array $dados, User $autor): MedicamentoPaciente
    {
        $novos = array_intersect_key($this->colunas($dados), array_flip($this->chavesQueVieram($dados)));
        $antes = [];
        foreach ($novos as $col => $valor) {
            $atual = $m->getAttribute($col);
            $atual = $atual instanceof \DateTimeInterface ? $atual->format('Y-m-d') : $atual;
            if ($atual !== $valor) {
                $antes[$col] = $atual;
            }
        }
        if ($antes === []) {
            return $m;
        }
        $desde = array_key_exists('started_on', $novos) ? $novos['started_on'] : $m->started_on?->toDateString();
        $ate = array_key_exists('ended_on', $novos) ? $novos['ended_on'] : $m->ended_on?->toDateString();
        self::exigirOrdem($desde, $ate);
        $m->forceFill($novos)->save();

        activity(self::LOG)
            ->performedOn($m)
            ->causedBy($autor)
            ->withProperties(['patient_id' => (int) $m->patient_id, 'antes' => $antes, 'depois' => array_intersect_key($novos, $antes)])
            ->log('fisio.medicamento.alterado');

        return $m;
    }

    public function tirar(MedicamentoPaciente $m, User $autor, string $motivo): bool
    {
        if ($m->removed_at !== null) {
            return false;
        }
        $m->forceFill(['removed_at' => now(), 'removed_by' => (int) $autor->id, 'removed_reason' => $motivo])->save();

        activity(self::LOG)
            ->performedOn($m)
            ->causedBy($autor)
            ->withProperties(['patient_id' => (int) $m->patient_id, 'medication_id' => (int) $m->id])
            ->log('fisio.medicamento.tirado_da_lista');

        return true;
    }

    /**
     * Para a ficha: os que não saíram da lista; em uso primeiro, depois os encerrados.
     *
     * @return list<array<string, mixed>>
     */
    public function daFicha(Patient $paciente): array
    {
        $hoje = now()->toDateString();
        $lista = MedicamentoPaciente::query()
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', (int) $paciente->id)
            ->whereNull('removed_at')
            ->orderByDesc('id')
            ->get();
        $conferidores = DB::table('users')->whereIn('id', $lista->pluck('confirmed_by')->filter()->unique()->values()->all())
            ->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn ($u) => [(int) $u->id => trim($u->first_name.' '.$u->last_name)]);

        return $lista->map(fn (MedicamentoPaciente $m) => [
            'id' => $m->id,
            'nome' => $m->name,
            'dose' => $m->dose,
            'frequencia' => $m->frequency,
            'desde' => $m->started_on?->toDateString(),
            'ate' => $m->ended_on?->toDateString(),
            'em_uso' => $m->ended_on === null || $m->ended_on->toDateString() >= $hoje,
            'origem' => $m->source,
            'documento_id' => $m->document_id,
            'conferido_por' => $m->confirmed_by ? ($conferidores[$m->confirmed_by] ?? null) : null,
        ])->sortByDesc('em_uso')->values()->all();
    }

    private static function exigirOrdem(?string $desde, ?string $ate): void
    {
        if ($desde !== null && $ate !== null && substr($ate, 0, 10) < substr($desde, 0, 10)) {
            throw ValidationException::withMessages(['ate' => 'O fim não pode ser antes do início.']);
        }
    }

    /**
     * Nome do request → coluna; texto vazio vira nulo.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, ?string>
     */
    private function colunas(array $dados): array
    {
        $mapa = ['nome' => 'name', 'dose' => 'dose', 'frequencia' => 'frequency', 'desde' => 'started_on', 'ate' => 'ended_on'];
        $out = [];
        foreach ($mapa as $campo => $col) {
            $v = $dados[$campo] ?? null;
            $v = is_string($v) ? trim($v) : $v;
            $out[$col] = ($v === '' || $v === null) ? null : (string) $v;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return list<string> colunas cujo campo veio no request
     */
    private function chavesQueVieram(array $dados): array
    {
        $mapa = ['nome' => 'name', 'dose' => 'dose', 'frequencia' => 'frequency', 'desde' => 'started_on', 'ate' => 'ended_on'];

        return array_values(array_map(fn ($c) => $mapa[$c], array_filter(array_keys($mapa), fn ($c) => array_key_exists($c, $dados))));
    }
}
