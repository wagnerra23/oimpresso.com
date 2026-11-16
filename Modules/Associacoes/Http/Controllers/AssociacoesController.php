<?php

declare(strict_types=1);

namespace Modules\Associacoes\Http\Controllers;

use App\Contact;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Representante;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\DocumentosDaAssociacao;
use Modules\Associacoes\Services\PendenciaDiretoria;

/**
 * Associações da rede (US-ASSOC-002 · ADR 0451 D3/D4/D7).
 *
 * Alcance: toda consulta passa por {@see AssociacoesAcesso::restringir()} — a diretoria
 * (`associacoes.rede`) vê a rede; o representante vê só a dele. Associação fora do alcance,
 * de outra empresa ou de outra associação, responde 404 antes de validar qualquer coisa.
 *
 * Gravar exige `associacoes.editar`. Criar associação e trocar os representantes exige também
 * `associacoes.rede`: senão o representante poderia dar acesso à associação dele a quem quisesse.
 */
class AssociacoesController extends Controller
{
    public function __construct(
        private AssociacoesAcesso $acesso,
        private PendenciaDiretoria $pendencia,
        private DocumentosDaAssociacao $documentos,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $verId = ctype_digit((string) $request->query('ver', '')) ? (int) $request->query('ver') : null;

        return Inertia::render('Associacoes/Index', [
            'filtros' => ['q' => $q],
            'pode_editar' => $this->podeEditar($request),
            'pode_rede' => $this->acesso->veARede($user),
            'prazo_dias' => PendenciaDiretoria::prazoDias(),
            'associacoes' => Inertia::defer(fn () => $this->listar($request, $q)),
            'ficha' => Inertia::optional(fn () => $verId !== null ? $this->ficha($request, $verId) : null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->exigirEdicao($request);
        abort_unless($this->acesso->veARede($request->user()), 403);
        $dados = $request->validate($this->regras($request, null), $this->mensagens());

        $associacao = DB::transaction(function () use ($request, $dados) {
            $a = Associacao::query()->create($this->campos($dados) + ['business_id' => $this->empresa($request)]);
            $this->sincronizarRepresentantes($a, $dados['representantes'] ?? []);

            return $a;
        });

        return $this->voltarParaFicha((int) $associacao->id, 'Associação cadastrada.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $associacao = $this->alcancada($request, $id);
        $this->exigirEdicao($request);
        $rede = $this->acesso->veARede($request->user());
        $dados = $request->validate($this->regras($request, $associacao), $this->mensagens());

        DB::transaction(function () use ($associacao, $dados, $rede) {
            $associacao->fill($this->campos($dados))->save();
            if ($rede && array_key_exists('representantes', $dados)) {
                $this->sincronizarRepresentantes($associacao, $dados['representantes'] ?? []);
            }
        });

        return $this->voltarParaFicha((int) $associacao->id, 'Associação atualizada.');
    }

    /** D7: a associação avisou que a diretoria mudou; abre a pendência a partir da data. */
    public function diretoria(Request $request, int $id): RedirectResponse
    {
        $associacao = $this->alcancada($request, $id);
        $this->exigirEdicao($request);
        $dados = $request->validate(['mudou_em' => ['required', 'date', 'before_or_equal:today']], [
            'mudou_em.required' => 'Informe a data da mudança de diretoria.',
            'mudou_em.before_or_equal' => 'A data da mudança não pode ser depois de hoje.',
        ]);
        $this->pendencia->registrarMudanca($associacao, Carbon::parse($dados['mudou_em']));

        return $this->voltarParaFicha((int) $associacao->id, 'Mudança de diretoria registrada. A ata nova fecha a pendência.');
    }

    /** @return list<array<string, mixed>> */
    private function listar(Request $request, string $q): array
    {
        $query = $this->acesso->restringir(Associacao::query(), $request->user(), 'id')
            ->withCount(['documentos as documentos_na_lista' => fn ($d) => $d->whereNull('removido_em')])
            ->orderBy('nome');
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('nome', 'like', "%{$q}%")
                ->orWhere('sigla', 'like', "%{$q}%")->orWhere('cidade', 'like', "%{$q}%"));
        }
        /** @var \Illuminate\Database\Eloquent\Collection<int, Associacao> $associacoes */
        $associacoes = $query->limit(500)->get();
        $reps = $this->nomesRepresentantes($associacoes->pluck('id')->all());
        $contatos = $this->nomesContatos($associacoes->pluck('representante_contact_id')->filter()->unique()->all());

        return $associacoes->map(fn (Associacao $a) => [
            'id' => (int) $a->id,
            'nome' => $a->nome,
            'sigla' => $a->sigla,
            'cidade' => $a->cidade,
            'regiao' => $a->regiao,
            'situacao' => $a->situacao,
            'representante' => $contatos[$a->representante_contact_id] ?? ($reps[$a->id][0] ?? null),
            'documentos' => (int) $a->getAttribute('documentos_na_lista'),
            'pendencia' => $this->pendencia->situacao($a),
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function ficha(Request $request, int $id): array
    {
        $a = $this->alcancada($request, $id);
        $rede = $this->acesso->veARede($request->user());

        return [
            'id' => (int) $a->id,
            'nome' => $a->nome,
            'sigla' => $a->sigla,
            'cidade' => $a->cidade,
            'regiao' => $a->regiao,
            'cnpj' => $a->cnpj,
            'situacao' => $a->situacao,
            'diretoria_atualizada_em' => $a->diretoria_atualizada_em?->toDateString(),
            'pendencia' => $this->pendencia->situacao($a),
            'representantes' => Representante::query()->where('associacao_id', $a->id)->pluck('user_id')->map('intval')->values()->all(),
            'usuarios' => $rede ? $this->usuariosDaEmpresa($this->empresa($request)) : [],
            'documentos' => $this->documentos->daFicha($a),
        ];
    }

    /** Associação no alcance do usuário (empresa pelo escopo, associação pelo D4); fora → 404. */
    private function alcancada(Request $request, int $id): Associacao
    {
        /** @var Associacao $a */
        $a = $this->acesso->restringir(Associacao::query(), $request->user(), 'id')
            ->where('business_id', $this->empresa($request))
            ->findOrFail($id);

        return $a;
    }

    /** @return array<string, mixed> */
    private function regras(Request $request, ?Associacao $atual): array
    {
        $biz = $this->empresa($request);

        return [
            'nome' => ['required', 'string', 'max:191',
                Rule::unique('assoc_associacoes', 'nome')->where('business_id', $biz)->ignore($atual?->id)],
            'sigla' => ['nullable', 'string', 'max:30'],
            'cidade' => ['required', 'string', 'max:120'],
            'regiao' => ['nullable', 'string', 'max:120'],
            'cnpj' => ['nullable', 'string', 'max:18', 'regex:/^[0-9.\/-]{14,18}$/'],
            'situacao' => ['required', Rule::in(Associacao::SITUACOES)],
            'representante_contact_id' => ['nullable', 'integer',
                Rule::exists('contacts', 'id')->where('business_id', $biz)->whereNull('deleted_at')],
            'representantes' => ['sometimes', 'array', 'max:20'],
            'representantes.*' => ['integer', 'distinct',
                Rule::exists('users', 'id')->where('business_id', $biz)->where('user_type', 'user')],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'nome.required' => 'Informe o nome da associação.',
            'nome.unique' => 'Já existe uma associação com esse nome na rede.',
            'cidade.required' => 'Informe a cidade.',
            'cnpj.regex' => 'CNPJ inválido.',
            'situacao.in' => 'Escolha a situação.',
            'representantes.*.exists' => 'Representante precisa ser usuário desta empresa.',
        ];
    }

    /** @param array<string, mixed> $dados @return array<string, mixed> */
    private function campos(array $dados): array
    {
        return array_intersect_key($dados, array_flip(['nome', 'sigla', 'cidade', 'regiao', 'cnpj', 'situacao', 'representante_contact_id']));
    }

    /** @param list<int> $userIds */
    private function sincronizarRepresentantes(Associacao $a, array $userIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        Representante::query()->where('associacao_id', $a->id)->whereNotIn('user_id', $ids ?: [0])->delete();
        foreach ($ids as $uid) {
            Representante::query()->firstOrCreate(
                ['business_id' => (int) $a->business_id, 'associacao_id' => (int) $a->id, 'user_id' => $uid]
            );
        }
    }

    /** @param list<int> $ids @return array<int, list<string>> */
    private function nomesRepresentantes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $porAssoc = [];
        Representante::query()->whereIn('associacao_id', $ids)
            ->join('users', 'users.id', '=', 'assoc_representantes.user_id')
            ->orderBy('users.first_name')
            ->get(['assoc_representantes.associacao_id', 'users.first_name', 'users.last_name'])
            ->each(function ($r) use (&$porAssoc) {
                $porAssoc[(int) $r->getAttribute('associacao_id')][] = trim($r->getAttribute('first_name').' '.$r->getAttribute('last_name'));
            });

        return $porAssoc;
    }

    /** @param list<int> $ids @return array<int, string> */
    private function nomesContatos(array $ids): array
    {
        return $ids === [] ? [] : Contact::query()->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    /** @return list<array{id: int, nome: string}> */
    private function usuariosDaEmpresa(int $biz): array
    {
        return User::query()->where('business_id', $biz)->where('user_type', 'user')->where('status', 'active')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u) => ['id' => (int) $u->id, 'nome' => trim($u->first_name.' '.$u->last_name)])
            ->values()->all();
    }

    /** Volta à lista com a ficha aberta: a tela recarrega só `ficha` e `associacoes`. */
    private function voltarParaFicha(int $id, string $msg): RedirectResponse
    {
        return redirect()->to('/associacoes/lista?ver='.$id)->with('success', $msg);
    }

    private function empresa(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function podeEditar(Request $request): bool
    {
        $u = $request->user();

        return (bool) ($u?->can(AssociacoesAcesso::PERMISSAO_EDITAR) || $u?->can('superadmin'));
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless($this->podeEditar($request), 403);
    }
}
