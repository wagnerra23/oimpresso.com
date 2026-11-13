<?php

declare(strict_types=1);

namespace Modules\Associacoes\Http\Controllers;

use App\Contact;
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
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Associada;
use Modules\Associacoes\Entities\Representante;
use Modules\Associacoes\Services\AssociacoesAcesso;
use Modules\Associacoes\Services\Consentimentos;

/**
 * Associadas da rede (US-ASSOC-003; ADR 0451 D3, D4 e D5).
 *
 *  - A associada É um contato do núcleo: nome e celular moram no `contacts`. Sem CPF e sem dado
 *    clínico. Origem no cadastro igual ao paciente do Fisio (UC-FPI-05..07): contato achado pela
 *    busca no servidor ou criado na hora pelo ContactUtil, sem duplicar celular na empresa.
 *  - Toda consulta passa por {@see AssociacoesAcesso::restringir()}: o representante só vê e só
 *    cadastra na associação dele; a diretoria (`associacoes.rede`) vê a rede.
 *  - O celular sai MASCARADO na lista. O nome só aparece para quem não representa a associação
 *    se a associada aceitou `visivel_rede` (D5: "ter o nome visível para a Uniafibro").
 *  - Desligar é situação + data; associada não se apaga. Consentimento também não (revoga).
 *  - Gravar exige `associacoes.editar`. Nome e celular não vão para log.
 */
class AssociadasController extends Controller
{
    public function __construct(private readonly AssociacoesAcesso $acesso, private readonly Consentimentos $consentimentos) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $associacaoId = ctype_digit((string) $request->query('associacao', '')) ? (int) $request->query('associacao') : null;
        $situacao = in_array($request->query('situacao'), Associada::SITUACOES, true) ? (string) $request->query('situacao') : '';

        return Inertia::render('Associacoes/Associadas', [
            'filtros' => ['associacao' => $associacaoId !== null ? (string) $associacaoId : '', 'situacao' => $situacao],
            'pode_editar' => $this->podeEditar($request),
            'situacoes' => Associada::SITUACOES,
            'termos' => $this->consentimentos->textos(),
            'associacoes' => Inertia::defer(fn () => $this->acesso->restringir(Associacao::query(), $user, 'id')
                ->orderBy('nome')->get(['id', 'nome', 'cidade'])
                ->map(fn (Associacao $a) => ['id' => (int) $a->id, 'nome' => (string) $a->nome, 'cidade' => (string) $a->cidade])
                ->all()),
            'associadas' => Inertia::defer(fn () => $this->listar($request, $associacaoId, $situacao)),
        ]);
    }

    /** Busca de contato para o cadastro: nome ou celular, até 20, da empresa, sem ser associada. */
    public function contatos(Request $request): JsonResponse
    {
        $this->exigirEdicao($request);
        $biz = $this->businessId($request);
        $q = trim((string) $request->query('q', ''));
        $termo = '%'.addcslashes($q, '%_\\').'%';
        $numero = preg_replace('/\D/', '', $q) ?? '';

        $contatos = Contact::query()
            ->where('contacts.business_id', $biz)
            ->whereIn('contacts.type', ['customer', 'both'])
            ->whereNotExists(fn ($s) => $s->from('assoc_associadas')
                ->whereColumn('assoc_associadas.contact_id', 'contacts.id')
                ->where('assoc_associadas.business_id', $biz))
            ->when($q !== '', fn ($w) => $w->where(function ($o) use ($termo, $numero) {
                $o->where('contacts.name', 'like', $termo)->orWhere('contacts.mobile', 'like', $termo);
                if (strlen($numero) >= 3) {
                    $o->orWhere('contacts.mobile', 'like', '%'.$numero.'%');
                }
            }))
            ->orderBy('contacts.name')
            ->limit(20)
            ->get(['contacts.id', 'contacts.name', 'contacts.mobile'])
            ->map(fn ($c) => ['id' => (int) $c->id, 'nome' => (string) $c->name, 'telefone' => self::mascarar((string) $c->mobile)])
            ->all();

        return response()->json(['contatos' => $contatos]);
    }

    public function store(Request $request, ContactUtil $contactUtil): RedirectResponse
    {
        $this->exigirEdicao($request);
        $biz = $this->businessId($request);
        $user = $request->user();
        $visiveis = $this->acesso->associacoesVisiveis($user);

        $dados = $request->validate([
            'associacao_id' => ['required', 'integer', Rule::exists('assoc_associacoes', 'id')->where('business_id', $biz)],
            'entrou_em' => ['required', 'date', 'before_or_equal:today'],
            'contact_id' => [
                'nullable', 'required_without:novo_contato', 'integer',
                Rule::exists('contacts', 'id')->where('business_id', $biz)->whereNull('deleted_at'),
                Rule::unique('assoc_associadas', 'contact_id')->where('business_id', $biz),
            ],
            'novo_contato' => ['nullable', 'array', 'required_without:contact_id'],
            'novo_contato.nome' => ['required_with:novo_contato', 'nullable', 'string', 'max:191'],
            'novo_contato.telefone' => ['required_with:novo_contato', 'nullable', 'string', 'max:30'],
            'novo_contato.cidade' => ['nullable', 'string', 'max:120'],
            'consentimentos' => ['required', 'array'],
            'consentimentos.'.Consentimentos::ASSOCIADA => ['accepted'],
            'consentimentos.*' => ['boolean'],
        ], [
            'associacao_id.exists' => 'Associação não encontrada.',
            'contact_id.unique' => 'Este contato já é associada.',
            'contact_id.exists' => 'Contato não encontrado nesta empresa.',
            'contact_id.required_without' => 'Escolha um contato ou cadastre um novo.',
            'novo_contato.required_without' => 'Escolha um contato ou cadastre um novo.',
            'novo_contato.nome.required_with' => 'Informe o nome.',
            'novo_contato.telefone.required_with' => 'Informe o celular.',
            'consentimentos.required' => 'O aceite para fazer parte da associação é obrigatório.',
            'consentimentos.'.Consentimentos::ASSOCIADA.'.accepted' => 'O aceite para fazer parte da associação é obrigatório.',
        ]);

        // Representante só cadastra na associação dele (D4). 403, não 422: é alcance, não dado.
        abort_if($visiveis !== null && ! in_array((int) $dados['associacao_id'], $visiveis, true), 403);

        $novo = isset($dados['contact_id']) ? null : $this->novoContatoSemDuplicar($biz, (array) ($dados['novo_contato'] ?? []));
        // Só as finalidades da lista fechada da config; as marcadas com true.
        $aceites = array_values(array_filter(
            $this->consentimentos->finalidades(),
            fn (string $f) => filter_var($dados['consentimentos'][$f] ?? false, FILTER_VALIDATE_BOOLEAN),
        ));

        DB::transaction(function () use ($dados, $biz, $novo, $user, $contactUtil, $aceites) {
            $contactId = $novo === null ? (int) $dados['contact_id'] : $this->criarContato($contactUtil, $biz, (int) $user->id, $novo);

            $associada = Associada::query()->create([
                'business_id' => $biz,
                'associacao_id' => (int) $dados['associacao_id'],
                'contact_id' => $contactId,
                'entrou_em' => $dados['entrou_em'],
                'situacao' => 'ativa',
            ]);
            foreach ($aceites as $f) {
                $this->consentimentos->conceder($associada, $f, 'cadastro');
            }
        });

        return redirect()->route('associacoes.associadas.index')->with('success', 'Associada cadastrada.');
    }

    /** Desligar: situação + data. Nunca apaga; repetir não muda a data do primeiro desligamento. */
    public function desligar(Request $request, int $id): RedirectResponse
    {
        $this->exigirEdicao($request);
        $dados = $request->validate(['desligada_em' => ['required', 'date', 'before_or_equal:today']]);
        $associada = $this->acesso->restringir(Associada::query(), $request->user())->findOrFail($id);

        if ($associada->situacao !== 'desligada') {
            $associada->fill(['situacao' => 'desligada', 'desligada_em' => $dados['desligada_em']])->save();
        }

        return back()->with('success', 'Associada desligada. O cadastro continua guardado.');
    }

    /** @return list<array<string, mixed>> */
    private function listar(Request $request, ?int $associacaoId, string $situacao): array
    {
        $user = $request->user();
        $linhas = $this->acesso->restringir(Associada::query(), $user)
            ->with(['contato:id,name,mobile,city', 'associacao:id,nome,cidade'])
            ->when($associacaoId !== null, fn ($q) => $q->where('associacao_id', $associacaoId))
            ->when($situacao !== '', fn ($q) => $q->where('situacao', $situacao))
            ->orderByDesc('entrou_em')->orderByDesc('id')
            ->limit(500)
            ->get();

        // Nome visível: quem representa a associação vê sempre; a diretoria só com `visivel_rede` (D5).
        $representadas = Representante::query()->where('user_id', (int) $user->id)->pluck('associacao_id')->map(static fn ($v) => (int) $v)->all();
        $comAceiteRede = $this->consentimentos->comVigente($this->businessId($request), $linhas->pluck('id')->map(static fn ($v) => (int) $v)->all(), Consentimentos::VISIVEL_REDE);

        return $linhas->map(function (Associada $a) use ($representadas, $comAceiteRede) {
            $nomeVisivel = in_array((int) $a->associacao_id, $representadas, true) || in_array((int) $a->id, $comAceiteRede, true);

            return [
                'id' => (int) $a->id,
                'nome' => $nomeVisivel ? (string) $a->contato?->name : null,
                'celular' => self::mascarar((string) $a->contato?->mobile),
                'cidade' => (string) ($a->contato?->city ?: $a->associacao?->cidade),
                'associacao' => (string) $a->associacao?->nome,
                'situacao' => (string) $a->situacao,
                'entrou_em' => $a->entrou_em?->format('Y-m-d'),
                'desligada_em' => $a->desligada_em?->format('Y-m-d'),
            ];
        })->all();
    }

    /** "(48) 9****-**12": DDD e os 2 últimos dígitos, o resto oculto. Vazio fica vazio. */
    public static function mascarar(string $celular): string
    {
        $d = preg_replace('/\D/', '', $celular) ?? '';
        if (strlen($d) > 11 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }
        if (strlen($d) < 6) {
            return $d === '' ? '' : '****';
        }

        return '('.substr($d, 0, 2).') '.$d[2].'****-**'.substr($d, -2);
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array{nome: string, celular: string, cidade: string}
     */
    private function novoContatoSemDuplicar(int $biz, array $entrada): array
    {
        $nome = trim((string) ($entrada['nome'] ?? ''));
        $celular = preg_replace('/\D/', '', (string) ($entrada['telefone'] ?? '')) ?? '';
        if ($nome === '') {
            throw ValidationException::withMessages(['novo_contato.nome' => 'Informe o nome.']);
        }
        if (strlen($celular) < 10 || strlen($celular) > 13) {
            throw ValidationException::withMessages(['novo_contato.telefone' => 'Celular inválido: informe DDD e número.']);
        }
        $soNumero = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(mobile, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '')";
        $existe = DB::table('contacts')->where('business_id', $biz)->whereNull('deleted_at')->whereRaw($soNumero.' = ?', [$celular])->exists();
        if ($existe) {
            throw ValidationException::withMessages([
                'novo_contato.telefone' => 'Já existe um contato com este celular. Escolha-o em "Contato existente".',
            ]);
        }

        return ['nome' => $nome, 'celular' => $celular, 'cidade' => trim((string) ($entrada['cidade'] ?? ''))];
    }

    /** @param  array{nome: string, celular: string, cidade: string}  $novo */
    private function criarContato(ContactUtil $contactUtil, int $biz, int $userId, array $novo): int
    {
        $criado = $contactUtil->createNewContact(array_filter([
            'business_id' => $biz,
            'type' => 'customer',
            'is_customer' => 1,
            'name' => $novo['nome'],
            'mobile' => $novo['celular'],
            'city' => $novo['cidade'] !== '' ? $novo['cidade'] : null,
            'contact_status' => 'active',
            'created_by' => $userId,
        ], static fn ($v) => $v !== null));
        $contact = $criado['data'];
        $contactUtil->activityLog($contact, 'added');

        return (int) $contact->id;
    }

    private function businessId(Request $request): int
    {
        return (int) $request->session()->get('user.business_id');
    }

    private function podeEditar(Request $request): bool
    {
        $u = $request->user();

        return $u !== null && ($u->can(AssociacoesAcesso::PERMISSAO_EDITAR) || $u->can('superadmin'));
    }

    private function exigirEdicao(Request $request): void
    {
        abort_unless($this->podeEditar($request), 403);
    }
}
