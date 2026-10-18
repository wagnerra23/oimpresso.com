<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;

/**
 * Editor do programa de exercícios do paciente (US-FISIO-006; HANDOFF F1 T-06).
 *
 * - Criar: programa `ativo`, `origin=manual` e `created_by` da sessão (nada disso vem do corpo).
 *   A instrução de parada é obrigatória — é o texto do PROFISSIONAL que o paciente vê com dor
 *   alta (ADR 0437 §5). Limiar de dor nulo = padrão de `config/fisio.php` (7). Um novo programa
 *   ativo encerra o anterior: só um ativo por paciente (UC-FPS-06).
 * - Item: herda a dose padrão do exercício e vai para o fim; dose editável dentro de limites;
 *   reordenar exige a lista exata e completa dos itens do programa; remover recompacta as
 *   posições e não toca check-in antigo (UC-FPS-08, UC-FPS-09).
 * - Contraindicação AVISA, nunca bloqueia (ADR 0437 §13, emenda 2): marcador do paciente que
 *   cruza com `contraindication_flags` do exercício responde 409 `contraindicacao` e nada grava;
 *   com `confirmar_contraindicacao`, grava quem, quando, quais marcadores e a justificativa
 *   escrita (UC-FPS-10). O software não decide conduta: informa, quem decide é o profissional.
 *
 * Escopo por empresa (ADR 0093): paciente e programa são resolvidos com o `business_id` da
 * sessão ANTES de validar — de outra empresa respondem 404 (UC-FPS-01). Escrita exige
 * `fisio.editar`. As escritas do editor respondem JSON (o editor é interativo).
 *
 * Protocolo-modelo (`origin=protocolo`) fica fora desta entrega: chega por rota própria quando
 * os protocolos revisados pela Dra. Dejeane existirem (BACKLOG em Show.casos.md).
 */
class ProgramasController extends Controller
{
    /** Limites de dose aceitos no editor. */
    private const LIMITES = [
        'sets' => [1, 20],
        'reps' => [1, 200],
        'hold_s' => [0, 600],
        'times_per_day' => [1, 10],
    ];

    public function store(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->businessId($request);
        // Paciente de outra empresa: 404 pelo escopo, antes de validar (UC-FPS-01).
        $paciente = Patient::query()->where('business_id', $businessId)->findOrFail($id);
        $this->exigirEdicao($request);

        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['stop_instruction' => 'Paciente bloqueado: a ficha é só leitura.']);
        }

        $dados = $request->validate([
            'phase' => ['nullable', 'string', 'max:40'],
            'starts_at' => ['nullable', 'date'],
            'stop_instruction' => ['required', 'string', 'max:2000'],
            'pain_alert_threshold' => ['nullable', 'integer', 'between:1,10'],
        ], [
            'stop_instruction.required' => 'Escreva o que o paciente deve fazer se sentir dor forte ou um sinal de alerta.',
        ]);

        $programa = DB::transaction(function () use ($dados, $paciente, $businessId, $request): Program {
            // Só um programa ativo por paciente: o anterior sai de uso por status, nunca por DELETE.
            Program::query()
                ->where('business_id', $businessId)
                ->where('patient_id', $paciente->id)
                ->where('status', Program::STATUS_ATIVO)
                ->update(['status' => Program::STATUS_ENCERRADO]);

            return Program::query()->create([
                'business_id' => $businessId,
                'patient_id' => $paciente->id,
                'phase' => $dados['phase'] ?? null,
                'status' => Program::STATUS_ATIVO,
                'origin' => 'manual',
                'stop_instruction' => trim((string) $dados['stop_instruction']),
                'pain_alert_threshold' => isset($dados['pain_alert_threshold']) ? (int) $dados['pain_alert_threshold'] : null,
                'created_by' => $request->user()->id,
                'starts_at' => $dados['starts_at'] ?? now()->toDateString(),
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json(['id' => $programa->id], 201);
        }

        return back()->with('status', 'Programa criado.');
    }

    public function adicionarItem(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->businessId($request);
        $programa = $this->programaEditavel($request, $id, $businessId);

        $dados = $request->validate([
            'exercise_id' => ['required', 'integer'],
            'confirmar_contraindicacao' => ['sometimes', 'boolean'],
            'justificativa' => ['nullable', 'string', 'max:2000'],
        ]);

        // Biblioteca global (business_id nulo) ou exercício próprio DESTA empresa; o de outra é recusado.
        $exercicio = Exercise::query()
            ->where(fn ($q) => $q->where('business_id', $businessId)->orWhereNull('business_id'))
            ->find((int) $dados['exercise_id']);
        if ($exercicio === null) {
            throw ValidationException::withMessages(['exercise_id' => 'Exercício não encontrado na biblioteca desta clínica.']);
        }

        $paciente = Patient::query()->where('business_id', $businessId)->findOrFail($programa->patient_id);
        $cruzamento = array_values(array_intersect(
            (array) ($exercicio->contraindication_flags ?? []),
            (array) ($paciente->condition_flags ?? []),
        ));
        $confirmado = (bool) ($dados['confirmar_contraindicacao'] ?? false);

        if ($cruzamento !== [] && ! $confirmado) {
            // Aviso, não bloqueio: o profissional decide e confirma (ADR 0437 §13, emenda 2).
            if ($request->wantsJson()) {
                return response()->json([
                    'erro' => 'contraindicacao',
                    'flags' => $cruzamento,
                    'mensagem' => 'Este exercício está marcado como contraindicado para uma condição do paciente. Confirme se quer prescrever mesmo assim.',
                ], 409);
            }

            return back()->withErrors(['exercise_id' => 'Contraindicação: confirme para prescrever mesmo assim.'])->with('contraindicacao', $cruzamento);
        }

        $item = DB::transaction(function () use ($programa, $exercicio, $cruzamento, $dados, $request, $businessId): ProgramItem {
            // Trava o programa para a posição não colidir com outra adição simultânea.
            Program::query()->where('business_id', $businessId)->whereKey($programa->id)->lockForUpdate()->first();
            $ultima = (int) ProgramItem::query()->where('business_id', $businessId)->where('program_id', $programa->id)->max('position');

            return ProgramItem::query()->create([
                'program_id' => $programa->id,
                'exercise_id' => $exercicio->id,
                'sets' => $exercicio->default_sets,
                'reps' => $exercicio->default_reps,
                'hold_s' => $exercicio->default_hold_s,
                'times_per_day' => $exercicio->times_per_day ?? 1,
                'notes' => null,
                'position' => $ultima + 1,
                'contraindication_ack_flags' => $cruzamento !== [] ? $cruzamento : null,
                'contraindication_ack_by' => $cruzamento !== [] ? $request->user()->id : null,
                'contraindication_ack_at' => $cruzamento !== [] ? now() : null,
                'contraindication_justification' => $cruzamento !== [] && filled($dados['justificativa'] ?? null)
                    ? trim((string) $dados['justificativa']) : null,
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json(['id' => $item->id], 201);
        }

        return back()->with('status', 'Exercício adicionado.');
    }

    public function atualizarItem(Request $request, int $id, int $item): JsonResponse|RedirectResponse
    {
        $businessId = $this->businessId($request);
        $programa = $this->programaEditavel($request, $id, $businessId);
        $registro = ProgramItem::query()->where('business_id', $businessId)->where('program_id', $programa->id)->findOrFail($item);

        $regras = ['notes' => ['sometimes', 'nullable', 'string', 'max:1000']];
        foreach (self::LIMITES as $campo => [$min, $max]) {
            $regras[$campo] = ['sometimes', 'integer', "between:{$min},{$max}"];
        }
        $dados = $request->validate($regras);

        // Só muda o que veio: editar repetições não zera as séries.
        $registro->fill($dados)->save();

        if ($request->wantsJson()) {
            return response()->json(['id' => $registro->id]);
        }

        return back()->with('status', 'Dose atualizada.');
    }

    public function reordenar(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $businessId = $this->businessId($request);
        $programa = $this->programaEditavel($request, $id, $businessId);

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $pedidos = array_map('intval', $dados['ids']);

        DB::transaction(function () use ($programa, $pedidos, $businessId): void {
            $atuais = ProgramItem::query()->where('business_id', $businessId)->where('program_id', $programa->id)
                ->lockForUpdate()->pluck('id')->map('intval')->all();

            $a = $atuais;
            $b = $pedidos;
            sort($a);
            sort($b);
            // Lista exata e completa: item de outro programa ou faltando = recusa, nada muda.
            if ($a !== $b) {
                throw ValidationException::withMessages(['ids' => 'A nova ordem precisa conter exatamente os exercícios deste programa.']);
            }

            foreach ($pedidos as $i => $itemId) {
                ProgramItem::query()->where('business_id', $businessId)->whereKey($itemId)->update(['position' => $i + 1]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['ids' => $pedidos]);
        }

        return back()->with('status', 'Ordem atualizada.');
    }

    public function removerItem(Request $request, int $id, int $item): JsonResponse|RedirectResponse
    {
        $businessId = $this->businessId($request);
        $programa = $this->programaEditavel($request, $id, $businessId);
        $registro = ProgramItem::query()->where('business_id', $businessId)->where('program_id', $programa->id)->findOrFail($item);

        DB::transaction(function () use ($registro, $programa, $businessId): void {
            // O check-in guarda os ids feitos em JSON (`done_item_ids`): remover o item não reescreve o histórico.
            $registro->delete();

            $restantes = ProgramItem::query()->where('business_id', $businessId)->where('program_id', $programa->id)
                ->orderBy('position')->orderBy('id')->lockForUpdate()->pluck('id');
            foreach ($restantes->values() as $i => $itemId) {
                ProgramItem::query()->where('business_id', $businessId)->whereKey($itemId)->update(['position' => $i + 1]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json(['removido' => $item]);
        }

        return back()->with('status', 'Exercício removido do programa.');
    }

    /**
     * Envio do programa pelo WhatsApp: é da US-FISIO-009. A rota existe (contrato de URIs da F1),
     * resolve o programa com o escopo da empresa (404 fora dela) e responde 501 até lá.
     */
    public function enviar(Request $request, int $id): JsonResponse
    {
        $businessId = $this->businessId($request);
        Program::query()->where('business_id', $businessId)->findOrFail($id);

        return response()->json(['message' => 'O envio pelo WhatsApp chega na US-FISIO-009.'], 501);
    }

    /** Programa da empresa (404 fora dela), com permissão de edição e ainda ativo. */
    private function programaEditavel(Request $request, int $id, int $businessId): Program
    {
        $programa = Program::query()->where('business_id', $businessId)->findOrFail($id);
        $this->exigirEdicao($request);

        if ($programa->status !== Program::STATUS_ATIVO) {
            throw ValidationException::withMessages(['programa' => 'Programa encerrado não se edita. Crie um novo programa.']);
        }

        return $programa;
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
