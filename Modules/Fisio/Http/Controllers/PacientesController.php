<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use App\Utils\ContactUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Consent;
use Modules\Fisio\Entities\Patient;

/**
 * Pacientes do Fisio — US-FISIO-005 (ADR 0437 §3 e §4; HANDOFF-F1 T-05).
 *
 * O paciente É um `contact`: `fisio_patients` guarda só o perfil clínico 1:1 (único por
 * business). Nome e telefone nunca são copiados — a ficha lê do contact. "Excluir" é
 * BLOQUEAR (`blocked_at`): guarda de 20 anos do prontuário (Lei 13.787/2018).
 *
 * Tier 0 (ADR 0093): `business_id` vem SEMPRE da sessão. Paciente de outra empresa não é
 * encontrado pelo escopo do model (404) — nunca é filtrado na mão depois de carregado.
 * Ler exige `fisio.access` (middleware FisioLiberado); gravar exige `fisio.editar`.
 *
 * Contrato: Pacientes/Index.casos.md (UC-FPI-01..04) · Pacientes/Show.casos.md (UC-FPS-01..07).
 */
class PacientesController extends Controller
{
    /** Status do perfil clínico (o bloqueio é `blocked_at`, não status). */
    public const STATUS = ['ativo', 'alta'];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $situacao = $request->query('situacao') === 'bloqueados' ? 'bloqueados' : 'ativos';

        return Inertia::render('Fisio/Pacientes/Index', [
            'filtros' => ['q' => $q, 'situacao' => $situacao],
            'pode_editar' => $request->user()->can('fisio.editar'),
            'condition_flags' => $this->marcadores(),
            'contagem' => Inertia::defer(fn () => [
                'ativos' => Patient::query()->whereNull('blocked_at')->count(),
                'bloqueados' => Patient::query()->whereNotNull('blocked_at')->count(),
            ]),
            'pacientes' => Inertia::defer(fn () => $this->lista($q, $situacao)),
        ]);
    }

    /** Contatos da empresa que ainda não são pacientes — para "criar a partir de contato". */
    public function contatos(Request $request): JsonResponse
    {
        $this->exigirEdicao($request);
        $biz = $this->biz($request);
        $q = trim((string) $request->query('q', ''));

        $contatos = DB::table('contacts')
            ->where('contacts.business_id', $biz)
            ->whereNull('contacts.deleted_at')
            ->whereIn('contacts.type', ['customer', 'both'])
            ->whereNotExists(fn ($s) => $s->from('fisio_patients')
                ->whereColumn('fisio_patients.contact_id', 'contacts.id')
                ->where('fisio_patients.business_id', $biz))
            ->when($q !== '', fn ($w) => $w->where(fn ($o) => $o
                ->where('contacts.name', 'like', '%'.$this->like($q).'%')
                ->orWhere('contacts.mobile', 'like', '%'.$this->like($q).'%')))
            ->orderBy('contacts.name')
            ->limit(20)
            ->get(['contacts.id', 'contacts.name', 'contacts.mobile']);

        return response()->json(['contatos' => $contatos->map(fn ($c) => [
            'id' => (int) $c->id, 'nome' => (string) $c->name, 'telefone' => (string) $c->mobile,
        ])->all()]);
    }

    public function store(Request $request, ContactUtil $contactUtil): RedirectResponse
    {
        $this->exigirEdicao($request);
        $biz = $this->biz($request);

        $dados = $request->validate([
            'contact_id' => [
                'nullable', 'required_without:novo_contato.nome', 'integer',
                Rule::exists('contacts', 'id')->where('business_id', $biz)->whereNull('deleted_at'),
                Rule::unique('fisio_patients', 'contact_id')->where('business_id', $biz),
            ],
            'novo_contato' => ['nullable', 'array'],
            'novo_contato.nome' => ['nullable', 'required_without:contact_id', 'string', 'max:191'],
            'novo_contato.telefone' => ['nullable', 'required_with:novo_contato.nome', 'string', 'max:30'],
            ...$this->regrasPerfil(),
        ], [
            'contact_id.exists' => __('fisio::fisio.contato_invalido'),
            'contact_id.unique' => __('fisio::fisio.contato_ja_paciente'),
        ]);

        $paciente = DB::transaction(function () use ($dados, $biz, $request, $contactUtil) {
            $contactId = $dados['contact_id'] ?? null;
            if ($contactId === null) {
                // Contato novo pelo caminho canônico do UltimatePOS (referência, created_by).
                $criado = $contactUtil->createNewContact([
                    'business_id' => $biz,
                    'type' => 'customer',
                    'name' => $dados['novo_contato']['nome'],
                    'mobile' => $dados['novo_contato']['telefone'],
                    'contact_status' => 'active',
                    'created_by' => (int) $request->user()->id,
                ]);
                $contactId = (int) $criado['data']->id;
            }

            return Patient::query()->create([
                'business_id' => $biz,
                'contact_id' => (int) $contactId,
                'status' => 'ativo',
                'blocked_at' => null,
            ] + $this->perfil($dados));
        });

        return redirect('/fisio/pacientes/'.$paciente->id)
            ->with('status', ['success' => 1, 'msg' => __('fisio::fisio.paciente_criado')]);
    }

    public function show(Request $request, int $id): Response
    {
        $paciente = Patient::query()->with('contact')->findOrFail($id);

        return Inertia::render('Fisio/Pacientes/Show', [
            'paciente' => $this->ficha($paciente),
            'pode_editar' => $request->user()->can('fisio.editar'),
            'condition_flags' => $this->marcadores(),
            'red_flags' => collect((array) config('fisio.red_flags', []))
                ->map(fn ($c) => ['value' => $c, 'label' => (string) __("fisio::fisio.red_flags.{$c}")])->values()->all(),
            'prontuario' => Inertia::defer(fn () => app(ProntuarioController::class)->linhaDoTempo($paciente)),
            'serie_dor' => Inertia::defer(fn () => $this->serieDor($paciente)),
            'consentimentos' => Inertia::defer(fn () => $this->consentimentos($paciente)),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->exigirEdicao($request);
        $paciente = Patient::query()->findOrFail($id);

        $dados = $request->validate([
            ...$this->regrasPerfil(),
            'status' => ['nullable', Rule::in(self::STATUS)],
        ]);

        if ($paciente->estaBloqueado()) {
            throw ValidationException::withMessages(['main_complaint' => __('fisio::fisio.paciente_ja_bloqueado')]);
        }

        // Edição parcial: só o que veio no corpo muda (não zera objetivos nem marcadores ausentes).
        $mudar = array_intersect_key($this->perfil($dados), array_flip(array_keys($dados)));
        $paciente->update($mudar + (isset($dados['status']) ? ['status' => $dados['status']] : []));

        return back()->with('status', ['success' => 1, 'msg' => __('fisio::fisio.paciente_salvo')]);
    }

    /**
     * "Excluir" = bloquear. A linha, o prontuário, os programas e os check-ins ficam
     * (guarda de 20 anos). Idempotente: bloquear de novo não mexe no `blocked_at`.
     */
    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->exigirEdicao($request);
        $paciente = Patient::query()->findOrFail($id);

        if (! $paciente->estaBloqueado()) {
            $paciente->update(['blocked_at' => now()]);
        }

        return redirect('/fisio/pacientes')
            ->with('status', ['success' => 1, 'msg' => __('fisio::fisio.paciente_bloqueado')]);
    }

    /** @return array<string, mixed> */
    private function regrasPerfil(): array
    {
        return [
            'main_complaint' => ['nullable', 'string', 'max:2000'],
            'goals' => ['nullable', 'array', 'max:10'],
            'goals.*' => ['required', 'string', 'max:300'],
            // 1..7: zero dividiria a adesão por zero; a semana tem 7 dias.
            'sessions_per_week' => ['required', 'integer', 'between:1,7'],
            'condition_flags' => ['nullable', 'array'],
            'condition_flags.*' => ['required', 'string', 'distinct', Rule::in((array) config('fisio.condition_flags', []))],
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function perfil(array $dados): array
    {
        return [
            'main_complaint' => $dados['main_complaint'] ?? null,
            'goals' => array_values(array_filter((array) ($dados['goals'] ?? []), fn ($g) => trim((string) $g) !== '')),
            'sessions_per_week' => (int) $dados['sessions_per_week'],
            'condition_flags' => array_values((array) ($dados['condition_flags'] ?? [])),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lista(string $q, string $situacao): array
    {
        return Patient::query()
            ->join('contacts', 'contacts.id', '=', 'fisio_patients.contact_id')
            ->when($situacao === 'bloqueados', fn ($w) => $w->whereNotNull('fisio_patients.blocked_at'), fn ($w) => $w->whereNull('fisio_patients.blocked_at'))
            ->when($q !== '', fn ($w) => $w->where(fn ($o) => $o
                ->where('contacts.name', 'like', '%'.$this->like($q).'%')
                ->orWhere('contacts.mobile', 'like', '%'.$this->like($q).'%')))
            ->select([
                'fisio_patients.*', 'contacts.name as contato_nome', 'contacts.mobile as contato_telefone',
                'ultimo_registro' => DB::table('fisio_records')->selectRaw('MAX(signed_at)')
                    ->whereColumn('fisio_records.patient_id', 'fisio_patients.id')
                    ->whereColumn('fisio_records.business_id', 'fisio_patients.business_id'),
            ])
            ->orderBy('contacts.name')
            ->limit(500)
            ->get()
            ->map(fn (Patient $p) => [
                'id' => (int) $p->id,
                'nome' => (string) $p->getAttribute('contato_nome'),
                'telefone' => (string) $p->getAttribute('contato_telefone'),
                'queixa' => $p->main_complaint,
                'sessions_per_week' => (int) $p->sessions_per_week,
                'condition_flags' => array_values((array) $p->condition_flags),
                'status' => $p->status,
                'bloqueado' => $p->estaBloqueado(),
                'ultimo_registro' => $p->getAttribute('ultimo_registro'),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function ficha(Patient $p): array
    {
        $contato = $p->contact;

        return [
            'id' => (int) $p->id,
            'contact_id' => (int) $p->contact_id,
            'nome' => (string) ($contato->name ?? ''),
            'telefone' => (string) ($contato->mobile ?? ''),
            'email' => $contato->email ?? null,
            'main_complaint' => $p->main_complaint,
            'goals' => array_values((array) $p->goals),
            'sessions_per_week' => (int) $p->sessions_per_week,
            'condition_flags' => array_values((array) $p->condition_flags),
            'status' => $p->status,
            'bloqueado' => $p->estaBloqueado(),
            'blocked_at' => $p->blocked_at?->toIso8601String(),
        ];
    }

    /** Série de dor dos check-ins — SÓ na visão do profissional (ADR 0437 §5). @return list<array{date: string, pain: int}> */
    private function serieDor(Patient $p): array
    {
        return Checkin::query()->where('patient_id', $p->id)->whereNotNull('pain')->orderBy('date')
            ->get(['date', 'pain'])
            ->map(fn (Checkin $c) => ['date' => $c->date->toDateString(), 'pain' => (int) $c->pain])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function consentimentos(Patient $p): array
    {
        return Consent::query()->where('patient_id', $p->id)->orderBy('purpose')->orderByDesc('granted_at')
            ->get(['purpose', 'version', 'granted_at', 'revoked_at', 'channel'])
            ->map(fn (Consent $c) => [
                'purpose' => $c->purpose,
                'version' => $c->version,
                'granted_at' => $c->granted_at?->toIso8601String(),
                'revoked_at' => $c->revoked_at?->toIso8601String(),
                'channel' => $c->channel,
            ])->all();
    }

    /** @return list<array{value: string, label: string}> */
    private function marcadores(): array
    {
        return collect((array) config('fisio.condition_flags', []))
            ->map(fn ($c) => ['value' => $c, 'label' => (string) __("fisio::fisio.condition_flags.{$c}")])->values()->all();
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless($request->user()->can('fisio.editar'), 403);
    }

    private function biz(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function like(string $q): string
    {
        return addcslashes($q, '%_\\');
    }
}
