<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use App\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\Questionnaire;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Services\Questionarios\CatalogoQuestionarios;
use Modules\Fisio\Services\ResponsavelPaciente;

/**
 * Pacientes do Fisio (US-FISIO-005). O paciente É um contact (ADR 0437 §4): aqui mora só o
 * perfil clínico 1:1. Nome e telefone são sempre lidos do contact, nunca copiados.
 *
 * "Excluir" é bloquear (Lei 13.787/2018, guarda de 20 anos): `destroy` só preenche
 * `blocked_at`, e a ficha continua legível.
 *
 * Escopo por empresa: `Patient` usa HasBusinessScope, então a ligação de rota de outra
 * empresa já responde 404 (UC-FPS-01). `business_id` vem sempre da sessão.
 */
class PacientesController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        // Filtro por responsável (UC-FPI-20): '' = todos, 'meus' = o usuário logado, ou o id de um profissional.
        $resp = trim((string) $request->query('responsavel', ''));
        $respId = $resp === 'meus' ? (int) $request->user()?->id : (ctype_digit($resp) ? (int) $resp : null);
        $biz = $this->businessId($request);

        return Inertia::render('Fisio/Pacientes/Index', [
            'filtros' => ['q' => $q, 'responsavel' => $respId !== null ? $resp : ''],
            'condicoes' => Patient::CONDITION_FLAGS,
            'pode_editar' => $this->podeEditar($request),
            'pacientes' => Inertia::defer(fn () => $this->listarPacientes($q, $respId)),
            'profissionais' => Inertia::defer(fn () => app(ResponsavelPaciente::class)->profissionais($biz)->all()),
            'contatos' => Inertia::defer(fn () => $this->contatosSemFicha($this->businessId($request))),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->exigirEdicao($request);
        $biz = $this->businessId($request);

        $dados = $request->validate([
            'contact_id' => [
                'required', 'integer',
                Rule::exists('contacts', 'id')->where('business_id', $biz),
                Rule::unique('fisio_patients', 'contact_id')->where('business_id', $biz),
            ],
            ...$this->regrasPerfil(),
        ], [
            'contact_id.unique' => 'Este contato já é paciente.',
            'contact_id.exists' => 'Contato não encontrado nesta empresa.',
        ]);

        $paciente = Patient::query()->create([
            'business_id' => $biz,
            'contact_id' => (int) $dados['contact_id'],
            'main_complaint' => $dados['main_complaint'] ?? null,
            'goals' => array_values($dados['goals'] ?? []),
            'sessions_per_week' => (int) $dados['sessions_per_week'],
            'condition_flags' => array_values($dados['condition_flags'] ?? []),
            'status' => 'ativo',
            // Quem cadastra é o responsável até alguém transferir (UC-FPS-30). Nunca do corpo.
            'responsible_user_id' => (int) $request->user()->id,
        ]);

        return redirect()->route('fisio.pacientes.show', $paciente->id)->with('status', 'Paciente cadastrado.');
    }

    public function show(Request $request, int $id): Response
    {
        $paciente = $this->paciente($id)->loadMissing(['contact:id,name,mobile', 'responsavel:id,first_name,last_name,username']);

        return Inertia::render('Fisio/Pacientes/Show', [
            'paciente' => [
                'id' => $paciente->id,
                'nome' => $paciente->contact?->name,
                'telefone' => $paciente->contact?->mobile,
                'main_complaint' => $paciente->main_complaint,
                'goals' => $paciente->goals ?? [],
                'sessions_per_week' => $paciente->sessions_per_week,
                'condition_flags' => $paciente->condition_flags ?? [],
                'bloqueado' => $paciente->estaBloqueado(),
                'responsavel' => $this->responsavelResumo($paciente),
            ],
            // Responsável e transferência (UC-FPS-30..32): queries → defer.
            'profissionais' => Inertia::defer(fn () => app(ResponsavelPaciente::class)->profissionais($this->businessId($request))->all(), 'responsavel'),
            'transferencias' => Inertia::defer(fn () => app(ResponsavelPaciente::class)->historico($paciente), 'responsavel'),
            'condicoes' => Patient::CONDITION_FLAGS,
            'pode_editar' => $this->podeEditar($request),
            'prontuario' => Inertia::defer(fn () => $this->linhaDoTempo($paciente)),
            // Catálogo estático (sem query): vai junto. O histórico é query → defer (US-FISIO-011).
            'instrumentos' => array_values(CatalogoQuestionarios::todos()),
            'questionarios' => Inertia::defer(fn () => $this->historicoQuestionarios($paciente)),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->exigirEdicao($request);
        $paciente = $this->paciente($id);

        $dados = $request->validate($this->regrasPerfil());

        // Bloqueado = ficha guardada só para leitura (Lei 13.787/2018; UC-FPS-18).
        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['main_complaint' => 'Paciente bloqueado: o perfil clínico não aceita alteração.']);
        }

        // Só muda o que veio: editar a meta semanal não apaga objetivos nem marcadores.
        $campos = ['sessions_per_week' => (int) $dados['sessions_per_week']];
        if (array_key_exists('main_complaint', $dados)) {
            $campos['main_complaint'] = $dados['main_complaint'];
        }
        foreach (['goals', 'condition_flags'] as $lista) {
            if (array_key_exists($lista, $dados)) {
                $campos[$lista] = array_values($dados[$lista] ?? []);
            }
        }
        $paciente->fill($campos)->save();

        return back()->with('status', 'Perfil clínico atualizado.');
    }

    /** Bloquear, nunca apagar. Repetir não muda a data do primeiro bloqueio (UC-FPS-07). */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->exigirEdicao($request);
        $paciente = $this->paciente($id);

        if (! $paciente->estaBloqueado()) {
            $paciente->blocked_at = now();
            $paciente->save();
        }

        return redirect()->route('fisio.pacientes.index')->with('status', 'Paciente bloqueado. O prontuário continua guardado.');
    }

    /** @return array<string, mixed> */
    private function regrasPerfil(): array
    {
        return [
            'main_complaint' => ['nullable', 'string', 'max:2000'],
            'goals' => ['nullable', 'array', 'max:10'],
            'goals.*' => ['string', 'max:255'],
            'sessions_per_week' => ['required', 'integer', 'between:1,7'],
            'condition_flags' => ['nullable', 'array'],
            'condition_flags.*' => ['string', Rule::in(Patient::CONDITION_FLAGS)],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function listarPacientes(string $q, ?int $responsavelId): array
    {
        return Patient::query()
            ->whereNull('blocked_at')
            ->with(['contact:id,name,mobile', 'responsavel:id,first_name,last_name,username'])
            ->when($q !== '', fn ($query) => $query->whereHas('contact', fn ($c) => $c->where('name', 'like', '%'.$q.'%')))
            ->when($responsavelId !== null, fn ($query) => $query->where('responsible_user_id', $responsavelId))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'nome' => $p->contact?->name,
                'telefone' => $p->contact?->mobile,
                'main_complaint' => $p->main_complaint,
                'sessions_per_week' => $p->sessions_per_week,
                'condition_flags' => $p->condition_flags ?? [],
                'responsavel' => $this->responsavelResumo($p),
            ])
            ->all();
    }

    /** @return array{id: int, nome: string}|null */
    private function responsavelResumo(Patient $p): ?array
    {
        $u = $p->responsavel;

        return $u !== null ? ['id' => (int) $u->id, 'nome' => ResponsavelPaciente::nome($u)] : null;
    }

    /** Contatos da empresa que ainda não têm ficha — origem do cadastro (UC-FPI-01). */
    private function contatosSemFicha(int $biz): array
    {
        $comFicha = Patient::query()->pluck('contact_id');

        return Contact::query()
            ->where('business_id', $biz)
            ->whereIn('type', ['customer', 'both'])
            ->whereNotIn('id', $comFicha)
            ->orderBy('name')
            ->limit(300)
            ->get(['id', 'name', 'mobile'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'nome' => $c->name, 'telefone' => $c->mobile])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function linhaDoTempo(Patient $paciente): array
    {
        return Record::query()
            ->where('patient_id', $paciente->id)
            ->with('signer:id,first_name,last_name')
            ->orderByDesc('signed_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Record $r) => [
                'id' => $r->id,
                'kind' => $r->kind,
                'body' => $r->body,
                'signed_at' => $r->signed_at?->toIso8601String(),
                'signed_crefito' => $r->signed_crefito,
                'assinado_por' => trim(($r->signer?->first_name ?? '').' '.($r->signer?->last_name ?? '')),
                'amends_id' => $r->amends_id !== null ? (int) $r->amends_id : null,
            ])
            ->all();
    }

    /**
     * Respostas de questionário do paciente, mais recente primeiro (UC-FPS-16). O escopo por empresa
     * vem do model; o filtro por paciente, daqui.
     *
     * @return list<array<string, mixed>>
     */
    private function historicoQuestionarios(Patient $paciente): array
    {
        return Questionnaire::query()
            ->where('patient_id', $paciente->id)
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Questionnaire $q) => [
                'id' => $q->id,
                'instrumento' => $q->instrument,
                'score' => $q->score !== null ? (float) $q->score : null,
                'detalhe' => (array) ($q->answers['detalhe'] ?? []),
                'answered_at' => $q->answered_at?->toIso8601String(),
            ])
            ->all();
    }

    /** Paciente de outra empresa não existe para esta: 404 pelo escopo (UC-FPS-01). */
    private function paciente(int $id): Patient
    {
        return Patient::query()->findOrFail($id);
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function podeEditar(Request $request): bool
    {
        return (bool) $request->user()?->can('fisio.editar');
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless($this->podeEditar($request), 403);
    }
}
