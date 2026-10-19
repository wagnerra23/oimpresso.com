<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Entities\Protocol;
use Modules\Fisio\Entities\Record;

/**
 * Editor de programa de exercícios (US-FISIO-006; ADR 0437 §5 e §13.2).
 *
 * Escritas em JSON (o editor é interativo). Toda escrita exige `fisio.editar`. Programa, item e
 * paciente de outra empresa respondem 404 pelo escopo dos models (ADR 0093); `business_id` vem
 * da sessão, nunca do corpo.
 *
 * Regras que moram aqui:
 *  - `origin` é `manual` na criação; `protocolo` só pela rota de aplicar protocolo.
 *  - Só um programa ativo por paciente: criar um novo encerra o anterior (sem apagar nada).
 *  - `stop_instruction` é obrigatória: é o único texto que o paciente vê em dia de dor alta.
 *  - Contraindicação AVISA, nunca bloqueia: a 1ª tentativa responde 409 com as marcações que
 *    cruzaram; com `confirmar_contraindicacao` o item grava quem confirmou, quais marcações,
 *    quando e a justificativa, e a confirmação vira um registro novo no prontuário (append-only).
 *    O paciente não vê nada disso.
 *  - O software não sugere exercício nem dose: adicionar herda a dose padrão do exercício e
 *    quem muda é o profissional (ADR 0437 §5, P10).
 */
class ProgramasController extends Controller
{
    private const MAX_ITENS_CRISE = 10;

    public function store(Request $request, int $id): JsonResponse
    {
        $biz = $this->businessId($request);
        $paciente = Patient::query()->where('business_id', $biz)->findOrFail($id);
        $this->exigirEdicao($request);
        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['paciente' => 'Paciente bloqueado não recebe programa novo.']);
        }

        $dados = $request->validate([
            'phase' => ['nullable', 'string', 'max:40'],
            'starts_at' => ['nullable', 'date'],
            ...$this->regrasCabecalho(true),
        ], $this->mensagens());

        $programa = DB::transaction(function () use ($request, $paciente, $dados) {
            // Um ativo por paciente: o anterior sai de uso por status, nunca por DELETE.
            Program::query()
                ->where('patient_id', $paciente->id)
                ->where('status', Program::STATUS_ATIVO)
                ->update(['status' => Program::STATUS_ENCERRADO]);

            return Program::query()->create([
                'business_id' => $this->businessId($request),
                'patient_id' => $paciente->id,
                'phase' => $dados['phase'] ?? null,
                'status' => Program::STATUS_ATIVO,
                // Ignora `origin` do corpo: protocolo entra só por aplicarProtocolo().
                'origin' => 'manual',
                'stop_instruction' => trim($dados['stop_instruction']),
                'pain_alert_threshold' => $dados['pain_alert_threshold'] ?? null,
                'pain_alert_mode' => $dados['pain_alert_mode'] ?? 'absoluto',
                'crisis_items' => $this->listaDeCrise($dados['crisis_items'] ?? null),
                // Da sessão, nunca do corpo.
                'created_by' => $request->user()->id,
                'starts_at' => $dados['starts_at'] ?? now()->toDateString(),
            ]);
        });

        return response()->json(['id' => $programa->id], 201);
    }

    /** Edita o cabeçalho: instrução de parada, limiar e modo do alerta de dor, versão de crise, fase. */
    public function update(Request $request, int $id): JsonResponse
    {
        // Programa de outra empresa → 404. O escopo do model já filtra; o where explícito é a
        // segunda camada, legível no próprio método (ADR 0093).
        $biz = $this->businessId($request);
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        $this->exigirEdicao($request);
        $this->exigirAtivo($programa);

        $dados = $request->validate([
            'phase' => ['sometimes', 'nullable', 'string', 'max:40'],
            ...$this->regrasCabecalho(false),
        ], $this->mensagens());

        $campos = [];
        foreach (['phase', 'pain_alert_threshold', 'pain_alert_mode'] as $c) {
            if (array_key_exists($c, $dados)) {
                $campos[$c] = $dados[$c];
            }
        }
        if (array_key_exists('stop_instruction', $dados)) {
            $campos['stop_instruction'] = trim((string) $dados['stop_instruction']);
        }
        if (array_key_exists('crisis_items', $dados)) {
            $campos['crisis_items'] = $this->listaDeCrise($dados['crisis_items']);
        }
        $programa->fill($campos)->save();

        return response()->json(['id' => $programa->id]);
    }

    public function adicionarItem(Request $request, int $id): JsonResponse
    {
        // Programa de outra empresa → 404. O escopo do model já filtra; o where explícito é a
        // segunda camada, legível no próprio método (ADR 0093).
        $biz = $this->businessId($request);
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        $this->exigirEdicao($request);
        $this->exigirAtivo($programa);

        $dados = $request->validate([
            'exercise_id' => ['required', 'integer', $this->regraExercicioVisivel($request)],
            'confirmar_contraindicacao' => ['sometimes', 'boolean'],
            'contraindication_justification' => ['nullable', 'string', 'max:2000'],
            'crefito' => ['nullable', 'string', 'max:40'],
        ], ['exercise_id.exists' => 'Exercício não encontrado na biblioteca desta clínica.']);

        $exercicio = Exercise::query()->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))->findOrFail((int) $dados['exercise_id']);
        $paciente = Patient::query()->where('business_id', $biz)->findOrFail($programa->patient_id);
        $flags = $this->cruzamento($exercicio, $paciente);

        if ($flags !== [] && ! $request->boolean('confirmar_contraindicacao')) {
            return $this->avisoContraindicacao([[
                'exercise_id' => $exercicio->id, 'nome' => $exercicio->name, 'flags' => $flags,
            ]]);
        }

        $item = DB::transaction(function () use ($request, $programa, $paciente, $exercicio, $flags, $dados) {
            $item = $this->criarItem($programa, $exercicio, [], $flags, $request, $dados['contraindication_justification'] ?? null);
            if ($flags !== []) {
                $this->registrarConfirmacao($request, $paciente, [[$exercicio, $flags]], $dados);
            }

            return $item;
        });

        return response()->json(['id' => $item->id], 201);
    }

    /** Dose: séries, repetições, sustentação, vezes por dia e observação. Só muda o que veio. */
    public function atualizarItem(Request $request, int $id, int $item): JsonResponse
    {
        $biz = $this->businessId($request);
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        $linha = ProgramItem::query()->where('business_id', $biz)->where('program_id', $programa->id)->findOrFail($item);
        $this->exigirEdicao($request);
        $this->exigirAtivo($programa);

        $dados = $request->validate([
            'sets' => ['sometimes', 'integer', 'between:1,20'],
            'reps' => ['sometimes', 'nullable', 'integer', 'between:1,200'],
            'hold_s' => ['sometimes', 'nullable', 'integer', 'between:0,600'],
            'times_per_day' => ['sometimes', 'integer', 'between:1,10'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], [
            'sets.between' => 'Séries de 1 a 20.',
            'reps.between' => 'Repetições de 1 a 200.',
            'hold_s.between' => 'Sustentação de 0 a 600 segundos.',
            'times_per_day.between' => 'Vezes por dia de 1 a 10.',
        ]);

        $linha->fill($dados)->save();

        return response()->json(['id' => $linha->id]);
    }

    /** Grava exatamente a ordem pedida. A lista tem que ser os itens deste programa, todos, sem repetição. */
    public function reordenar(Request $request, int $id): JsonResponse
    {
        // Programa de outra empresa → 404. O escopo do model já filtra; o where explícito é a
        // segunda camada, legível no próprio método (ADR 0093).
        $biz = $this->businessId($request);
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        $this->exigirEdicao($request);
        $this->exigirAtivo($programa);

        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $pedidos = array_map('intval', $dados['ids']);
        $atuais = ProgramItem::query()->where('program_id', $programa->id)->pluck('id')->map('intval')->all();

        $a = $pedidos;
        $b = $atuais;
        sort($a);
        sort($b);
        if ($a !== $b) {
            throw ValidationException::withMessages(['ids' => 'A nova ordem precisa conter todos os exercícios deste programa, e só eles.']);
        }

        DB::transaction(function () use ($programa, $pedidos) {
            foreach ($pedidos as $i => $itemId) {
                ProgramItem::query()->where('program_id', $programa->id)->whereKey($itemId)->update(['position' => $i + 1]);
            }
        });

        return response()->json(['ids' => $pedidos]);
    }

    /**
     * Tira o exercício do programa e recompacta as posições. Check-in antigo que citou o item
     * não muda: `done_item_ids` é histórico do paciente.
     */
    public function removerItem(Request $request, int $id, int $item): JsonResponse
    {
        $biz = $this->businessId($request);
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        $linha = ProgramItem::query()->where('business_id', $biz)->where('program_id', $programa->id)->findOrFail($item);
        $this->exigirEdicao($request);
        $this->exigirAtivo($programa);

        DB::transaction(function () use ($programa, $linha) {
            $linha->delete();
            $this->recompactar($programa);
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Aplica um protocolo-modelo (global ou da clínica) ao programa: os itens do protocolo entram
     * no fim, com a dose do protocolo (ou a padrão do exercício), e o programa passa a `origin=protocolo`.
     * A lógica do protocolo fica visível ao profissional e nada muda sozinho (ADR 0437 §5, P58).
     * Exercício do protocolo que a clínica não enxerga é pulado e devolvido em `ignorados`.
     */
    public function aplicarProtocolo(Request $request, int $id): JsonResponse
    {
        // Programa de outra empresa → 404. O escopo do model já filtra; o where explícito é a
        // segunda camada, legível no próprio método (ADR 0093).
        $biz = $this->businessId($request);
        $programa = Program::query()->where('business_id', $biz)->findOrFail($id);
        $this->exigirEdicao($request);
        $this->exigirAtivo($programa);

        $dados = $request->validate([
            'protocol_id' => ['required', 'integer'],
            'confirmar_contraindicacao' => ['sometimes', 'boolean'],
            'contraindication_justification' => ['nullable', 'string', 'max:2000'],
            'crefito' => ['nullable', 'string', 'max:40'],
        ]);
        // Protocolo de outra empresa não existe para esta (escopo global OU da sessão).
        $protocolo = Protocol::query()->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))->find((int) $dados['protocol_id']);
        if ($protocolo === null) {
            throw ValidationException::withMessages(['protocol_id' => 'Protocolo não encontrado nesta clínica.']);
        }

        $paciente = Patient::query()->where('business_id', $biz)->findOrFail($programa->patient_id);
        $linhas = [];
        $ignorados = [];
        foreach ((array) $protocolo->items as $i => $linha) {
            $exId = is_array($linha) ? (int) ($linha['exercise_id'] ?? 0) : 0;
            $exercicio = $exId > 0 ? Exercise::query()->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz))->find($exId) : null;
            if ($exercicio === null) {
                $ignorados[] = $i + 1;

                continue;
            }
            $linhas[] = [$exercicio, $linha, $this->cruzamento($exercicio, $paciente)];
        }
        if ($linhas === []) {
            throw ValidationException::withMessages(['protocol_id' => 'Este protocolo não tem exercícios disponíveis para esta clínica.']);
        }

        $avisos = array_values(array_filter(array_map(
            fn (array $l) => $l[2] === [] ? null : ['exercise_id' => $l[0]->id, 'nome' => $l[0]->name, 'flags' => $l[2]],
            $linhas,
        )));
        if ($avisos !== [] && ! $request->boolean('confirmar_contraindicacao')) {
            return $this->avisoContraindicacao($avisos);
        }

        $ids = DB::transaction(function () use ($request, $programa, $paciente, $linhas, $dados) {
            $ids = [];
            $confirmados = [];
            foreach ($linhas as [$exercicio, $linha, $flags]) {
                $ids[] = $this->criarItem($programa, $exercicio, (array) $linha, $flags, $request, $dados['contraindication_justification'] ?? null)->id;
                if ($flags !== []) {
                    $confirmados[] = [$exercicio, $flags];
                }
            }
            $programa->origin = 'protocolo';
            $programa->save();
            if ($confirmados !== []) {
                $this->registrarConfirmacao($request, $paciente, $confirmados, $dados);
            }

            return $ids;
        });

        return response()->json(['ids' => $ids, 'ignorados' => $ignorados], 201);
    }

    /**
     * @param  array<string, mixed>  $dose  dose vinda do protocolo; o que faltar herda do exercício
     * @param  list<string>  $flags
     */
    private function criarItem(Program $programa, Exercise $exercicio, array $dose, array $flags, Request $request, ?string $justificativa): ProgramItem
    {
        $posicao = (int) ProgramItem::query()->where('program_id', $programa->id)->max('position') + 1;
        $inteiro = fn (string $chave, ?int $padrao) => isset($dose[$chave]) && is_numeric($dose[$chave]) ? (int) $dose[$chave] : $padrao;

        return ProgramItem::query()->create([
            'program_id' => $programa->id,
            'exercise_id' => $exercicio->id,
            'sets' => $inteiro('sets', $exercicio->default_sets),
            'reps' => $inteiro('reps', $exercicio->default_reps),
            'hold_s' => $inteiro('hold_s', $exercicio->default_hold_s),
            'times_per_day' => $inteiro('times_per_day', $exercicio->times_per_day ?? 1) ?? 1,
            'notes' => isset($dose['notes']) && is_string($dose['notes']) ? mb_substr($dose['notes'], 0, 500) : null,
            'position' => $posicao,
            'contraindication_ack_flags' => $flags === [] ? null : $flags,
            'contraindication_ack_by' => $flags === [] ? null : $request->user()->id,
            'contraindication_ack_at' => $flags === [] ? null : now(),
            'contraindication_justification' => $flags === [] ? null : $this->textoOuNulo($justificativa),
        ]);
    }

    /**
     * Registro novo no prontuário: o profissional manteve exercício com contraindicação marcada.
     * Assinado pelo usuário da sessão e relógio do servidor; não se edita depois (append-only).
     *
     * @param  list<array{0: Exercise, 1: list<string>}>  $confirmados
     * @param  array<string, mixed>  $dados
     */
    private function registrarConfirmacao(Request $request, Patient $paciente, array $confirmados, array $dados): void
    {
        $lista = array_map(fn (array $c) => $c[0]->name.' ('.implode(', ', $c[1]).')', $confirmados);

        Record::query()->create([
            'business_id' => $this->businessId($request),
            'patient_id' => $paciente->id,
            'kind' => Record::KIND_EVOLUCAO,
            'body' => [
                'conduta' => 'Exercício mantido no programa apesar de contraindicação marcada: '.implode('; ', $lista).'.',
                'justificativa' => $this->textoOuNulo($dados['contraindication_justification'] ?? null) ?? 'Não informada.',
                'origem' => 'aviso_contraindicacao',
            ],
            'signed_by' => $request->user()->id,
            'signed_crefito' => $this->textoOuNulo($dados['crefito'] ?? null),
            'signed_at' => now(),
        ]);
    }

    /** @param list<array{exercise_id: int, nome: string, flags: list<string>}> $avisos */
    private function avisoContraindicacao(array $avisos): JsonResponse
    {
        return response()->json([
            'erro' => 'contraindicacao',
            // Formato de um item só preserva o contrato do teste (UC-FPS-10); `itens` traz o detalhe.
            'flags' => array_values(array_unique(array_merge(...array_map(fn (array $a) => $a['flags'], $avisos)))),
            'itens' => $avisos,
            'mensagem' => 'Há contraindicação marcada para este paciente. A decisão é sua: para manter, escreva a justificativa — ela vai para o prontuário.',
        ], 409);
    }

    /** @return list<string> marcações do paciente que o exercício tem como contraindicação */
    private function cruzamento(Exercise $exercicio, Patient $paciente): array
    {
        return array_values(array_intersect(
            (array) ($exercicio->contraindication_flags ?? []),
            (array) ($paciente->condition_flags ?? []),
        ));
    }

    private function recompactar(Program $programa): void
    {
        ProgramItem::query()->where('program_id', $programa->id)->orderBy('position')->orderBy('id')->pluck('id')
            ->each(fn ($itemId, $i) => ProgramItem::query()->whereKey($itemId)->update(['position' => $i + 1]));
    }

    /** Exercício global ou da empresa da sessão; o de outra clínica não existe para esta. */
    private function regraExercicioVisivel(Request $request): \Illuminate\Validation\Rules\Exists
    {
        $biz = $this->businessId($request);

        return Rule::exists('fisio_exercises', 'id')->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $biz));
    }

    /** @return array<string, mixed> */
    private function regrasCabecalho(bool $criando): array
    {
        $modo = $criando ? [] : ['sometimes'];

        return [
            'stop_instruction' => [...($criando ? ['required'] : ['sometimes', 'required']), 'string', 'min:5', 'max:2000'],
            'pain_alert_threshold' => [...$modo, 'nullable', 'integer', 'between:1,10'],
            'pain_alert_mode' => [...$modo, Rule::in(['absoluto', 'sobre_basal'])],
            'crisis_items' => [...$modo, 'nullable', 'array', 'max:'.self::MAX_ITENS_CRISE],
            'crisis_items.*' => ['string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'stop_instruction.required' => 'Escreva o que o paciente deve fazer em dia de dor forte. É o único texto que ele vê nesse dia.',
            'stop_instruction.min' => 'A instrução de parada está curta demais.',
            'pain_alert_threshold.between' => 'Limiar de dor de 1 a 10.',
        ];
    }

    /** @return list<string>|null */
    private function listaDeCrise(mixed $itens): ?array
    {
        $limpos = array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) ($itens ?? [])), fn (string $t) => $t !== ''));

        return $limpos === [] ? null : $limpos;
    }

    private function textoOuNulo(mixed $v): ?string
    {
        $t = trim((string) ($v ?? ''));

        return $t === '' ? null : $t;
    }

    private function exigirAtivo(Program $programa): void
    {
        if ($programa->status !== Program::STATUS_ATIVO) {
            throw ValidationException::withMessages(['programa' => 'Programa encerrado não se edita. Crie um programa novo.']);
        }
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
