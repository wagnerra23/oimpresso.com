<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Entities\Protocol;
use Modules\Fisio\Services\LinguagemAnvisa;

/**
 * Protocolos-modelo da clínica (US-FISIO-006; ADR 0437 §5, P58; etapas/01-biblioteca.md B-09).
 *
 * Dois tipos convivem na mesma tabela, com o mesmo escopo da biblioteca:
 *  - modelo do produto (`business_id` nulo): leitura para toda clínica, editável por nenhuma.
 *    O conteúdo clínico é da responsável técnica (Dra. Dejeane) e chega por seed revisado;
 *    este controller não cria modelo global.
 *  - protocolo da clínica (`business_id` da sessão): cópia de um modelo (`origin_protocol_id`)
 *    ou montado a partir de um programa. A clínica edita só o dela; revisar o modelo nunca
 *    altera a cópia.
 *
 * Itens: lista de {exercise_id, sets, reps, hold_s, times_per_day, notes}. Exercício precisa
 * ser global ou da própria clínica. O software não escolhe nem ajusta nada sozinho.
 */
class ProtocolosController extends Controller
{
    /** Copia um protocolo visível (modelo do produto ou da própria clínica) para a clínica. */
    public function copiar(Request $request, int $id): JsonResponse
    {
        // Visível = modelo global ou da própria empresa (mesmo filtro do escopo, explícito aqui).
        $biz = $this->businessId($request);
        $origem = Protocol::query()->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))->findOrFail($id);
        $this->exigirEdicao($request);

        $copia = Protocol::query()->create([
            'business_id' => $this->businessId($request),
            'origin_protocol_id' => $origem->id,
            'name' => mb_substr($origem->name.' (cópia da clínica)', 0, 160),
            'region' => $origem->region,
            'items' => $this->itensVisiveis((array) $origem->items),
            'notes' => $origem->notes,
        ]);

        return response()->json(['id' => $copia->id], 201);
    }

    /** Salva os itens do programa como protocolo da clínica (útil enquanto não há modelo global). */
    public function salvarDoPrograma(Request $request, int $id): JsonResponse
    {
        $biz = $this->businessId($request);
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        $this->exigirEdicao($request);

        $dados = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'region' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        LinguagemAnvisa::exigirLimpo(['name' => $dados['name'], 'notes' => $dados['notes'] ?? null]);

        $itens = ProgramItem::query()->where('business_id', $biz)->where('program_id', $programa->id)->orderBy('position')->get()
            ->map(fn (ProgramItem $i) => [
                'exercise_id' => (int) $i->exercise_id,
                'sets' => $i->sets, 'reps' => $i->reps, 'hold_s' => $i->hold_s,
                'times_per_day' => $i->times_per_day, 'notes' => $i->notes,
            ])->all();
        if ($itens === []) {
            throw ValidationException::withMessages(['programa' => 'O programa não tem exercícios para virar protocolo.']);
        }
        // Observação de item gravada antes do lint (B-06) não vira protocolo com promessa clínica.
        LinguagemAnvisa::exigirLimpo(['programa' => array_column($itens, 'notes')]);

        $protocolo = Protocol::query()->create([
            'business_id' => $this->businessId($request),
            'name' => trim($dados['name']),
            'region' => $dados['region'] ?? null,
            'items' => $itens,
            'notes' => $dados['notes'] ?? null,
        ]);

        return response()->json(['id' => $protocolo->id], 201);
    }

    /** Edita o protocolo da clínica. Modelo do produto responde 403: é leitura. */
    public function update(Request $request, int $id): JsonResponse
    {
        $biz = $this->businessId($request);
        $protocolo = Protocol::query()->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))->findOrFail($id);
        $this->exigirEdicao($request);
        abort_if($protocolo->business_id === null, 403, 'Protocolo-modelo do produto não se altera. Copie para a clínica e edite a cópia.');

        $dados = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'region' => ['sometimes', 'nullable', 'string', 'max:40'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array', 'min:1', 'max:40'],
            'items.*.exercise_id' => ['required_with:items', 'integer',
                Rule::exists('fisio_exercises', 'id')->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))],
            'items.*.sets' => ['nullable', 'integer', 'between:1,20'],
            'items.*.reps' => ['nullable', 'integer', 'between:1,200'],
            'items.*.hold_s' => ['nullable', 'integer', 'between:0,600'],
            'items.*.times_per_day' => ['nullable', 'integer', 'between:1,10'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ], ['items.*.exercise_id.exists' => 'Exercício não encontrado na biblioteca desta clínica.']);
        LinguagemAnvisa::exigirLimpo([
            'name' => $dados['name'] ?? null,
            'notes' => $dados['notes'] ?? null,
            'items' => array_column((array) ($dados['items'] ?? []), 'notes'),
        ]);

        if (array_key_exists('items', $dados)) {
            $dados['items'] = array_values(array_map(fn (array $i) => [
                'exercise_id' => (int) $i['exercise_id'],
                'sets' => $i['sets'] ?? null, 'reps' => $i['reps'] ?? null, 'hold_s' => $i['hold_s'] ?? null,
                'times_per_day' => $i['times_per_day'] ?? null, 'notes' => $i['notes'] ?? null,
            ], $dados['items']));
        }
        $protocolo->fill($dados)->save();

        return response()->json(['id' => $protocolo->id]);
    }

    /**
     * Mantém na cópia só itens com exercício que esta clínica enxerga (global ou dela).
     *
     * @param  array<int, mixed>  $itens
     * @return list<array<string, mixed>>
     */
    private function itensVisiveis(array $itens): array
    {
        $ids = array_filter(array_map(fn ($i) => is_array($i) ? (int) ($i['exercise_id'] ?? 0) : 0, $itens));
        $visiveis = \Modules\Fisio\Entities\Exercise::query()->whereIn('id', $ids)->pluck('id')->map('intval')->all();

        return array_values(array_filter($itens, fn ($i) => is_array($i) && in_array((int) ($i['exercise_id'] ?? 0), $visiveis, true)));
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
    }
}
