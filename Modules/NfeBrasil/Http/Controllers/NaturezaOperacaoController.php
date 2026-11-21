<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\NfeBrasil\Http\Requests\UpsertNaturezaRequest;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeOperacaoFiscal;
use Modules\NfeBrasil\Services\Concerns\ResolveUfEmitente;
use Modules\NfeBrasil\Services\Tributacao\ConferenciaNaturezaService;

/**
 * Naturezas de operação na Tributação — onda 3a (RUNBOOK-tributacao §Naturezas).
 *
 * Atrás da flag `useV2NfeNaturezas` (GrowthBook, por empresa, desligada por padrão): com ela
 * desligada as três rotas respondem 404, como se não existissem.
 *
 * Não muda imposto: a tela grava a forma dela em `config_tela` e nas colunas que o motor não lê
 * (onda 2). Só edita naturezas que o motor ainda não usa (`editavelPelaTela`); a Venda padrão e as
 * que vieram do CSV aparecem só para leitura até a onda 4. O slug nasce com prefixo `tela-` para
 * nunca coincidir com os que a emissão procura pelo nome (`venda`, `bonificacao`).
 */
class NaturezaOperacaoController extends Controller
{
    use ResolveUfEmitente;

    public const FLAG = 'useV2NfeNaturezas';

    public function __construct(private readonly ConferenciaNaturezaService $conferencia)
    {
    }

    /** GET /nfe-brasil/tributacao/naturezas */
    public function index(Request $request): JsonResponse
    {
        $businessId = $this->liberado($request);
        $regime = $this->regime($businessId);
        $todas = $this->daEmpresa($businessId)->orderBy('nome')->get();

        return response()->json([
            'regime'    => $regime,
            'empresa'   => $this->empresa($businessId),
            'naturezas' => $todas->map(function (NfeOperacaoFiscal $op) use ($todas, $regime) {
                $tela = $this->paraTela($op);
                // Só confere o que a tela edita: a Venda e as do CSV não têm os campos da tela, e
                // aparecer com "pendência" por isso seria acusar o que ninguém configurou aqui.
                $alertas = $op->editavelPelaTela()
                    ? $this->conferencia->conferir($tela, $regime, $this->outrasAtivas($todas, $op->id))
                    : null;

                return [
                    'id'          => $op->id,
                    'editavel'    => $op->editavelPelaTela(),
                    'padrao'      => $op->padrao,
                    'natureza'    => $tela,
                    'pendencias'  => $alertas === null ? null
                        : count(array_filter($alertas, fn ($a) => $a['nivel'] === ConferenciaNaturezaService::ERRO)),
                ];
            })->values(),
        ]);
    }

    /**
     * GET /nfe-brasil/tributacao/naturezas/lancamento — as naturezas que se escolhem ao emitir a NF-e
     * (onda 5). Mesmo filtro que a emissão aceita (`NfeService::naturezaDoLancamento`): criadas pela
     * tela, ativas, de saída, finalidade normal e sem nota referenciada. Permissão de EMITIR, não de
     * configurar a tributação — quem emite é o vendedor.
     */
    public function paraLancamento(Request $request): JsonResponse
    {
        $businessId = $this->liberado($request);

        $naturezas = $this->daEmpresa($businessId)
            ->whereNotNull('config_tela')
            ->where(fn ($q) => $q->whereNull('ativa')->orWhere('ativa', true))
            ->where(fn ($q) => $q->whereNull('tipo')->orWhere('tipo', 'saida'))
            ->where('finalidade', 1)
            ->where(fn ($q) => $q->whereNull('pede_nota_referenciada')->orWhere('pede_nota_referenciada', false))
            ->orderBy('nome')
            ->get(['id', 'nome', 'cfop', 'cfop_interestadual']);

        return response()->json(['naturezas' => $naturezas->map(fn (NfeOperacaoFiscal $n) => [
            'id' => $n->id, 'nome' => $n->nome, 'cfop' => $n->cfop, 'cfop_interestadual' => $n->cfop_interestadual,
        ])->values()]);
    }

    /** POST /nfe-brasil/tributacao/naturezas */
    public function store(UpsertNaturezaRequest $request): JsonResponse
    {
        $businessId = $this->liberado($request);
        $n = $request->validated();

        $alertas = $this->alertas($n, $businessId, null);
        if (ConferenciaNaturezaService::temErro($alertas)) {
            return response()->json(['alertas' => $alertas], 422);
        }

        $op = NfeOperacaoFiscal::create(array_merge($this->colunas($n), [
            'business_id' => $businessId,
            'slug'        => $this->slugLivre($businessId, (string) $n['nome']),
            'padrao'      => false,
        ]));

        $this->registrar($request, $businessId, $op, 'natureza.created');

        return response()->json(['id' => $op->id, 'alertas' => $alertas], 201);
    }

    /**
     * POST /nfe-brasil/tributacao/naturezas/conferir — a conferência sem gravar.
     *
     * A tela chama a cada alteração (como o protótipo faz): a regra fica num lugar só, no servidor,
     * em vez de uma cópia em TypeScript que divergiria. `id` opcional: a própria natureza não conta
     * como "outra ativa com o mesmo nome".
     */
    public function conferir(UpsertNaturezaRequest $request): JsonResponse
    {
        $businessId = $this->liberado($request);
        $id = $request->integer('id') ?: null;

        return response()->json(['alertas' => $this->alertas($request->validated(), $businessId, $id)]);
    }

    /** PUT /nfe-brasil/tributacao/naturezas/{id} */
    public function update(UpsertNaturezaRequest $request, int $id): JsonResponse
    {
        $businessId = $this->liberado($request);
        $op = $this->daEmpresa($businessId)->findOrFail($id);

        if (! $op->editavelPelaTela()) {
            return response()->json([
                'mensagem' => 'Esta natureza já é usada pelo cálculo da nota e só poderá ser editada aqui quando o cálculo passar a ler esta tela.',
            ], 409);
        }

        $n = $request->validated();
        $alertas = $this->alertas($n, $businessId, $op->id);
        if (ConferenciaNaturezaService::temErro($alertas)) {
            return response()->json(['alertas' => $alertas], 422);
        }

        $op->update($this->colunas($n));
        $this->registrar($request, $businessId, $op, 'natureza.updated');

        return response()->json(['id' => $op->id, 'alertas' => $alertas]);
    }

    /** 404 com a flag desligada para a empresa; senão, o business da sessão. */
    private function liberado(Request $request): int
    {
        $businessId = (int) $request->session()->get('business.id');
        abort_unless(app(FeatureFlagService::class)->isOn(self::FLAG, ['business_id' => $businessId]), 404);

        return $businessId;
    }

    private function daEmpresa(int $businessId)
    {
        return NfeOperacaoFiscal::query()->where('business_id', $businessId);
    }

    /**
     * Faixa de resumo do topo da lista (protótipo `Configuracao de Tributos.html`, `.dados`): quem
     * emite, de onde saem as notas e o IBS/CBS do ano. Só leitura, escopo explícito por
     * `business_id` (query builder não passa por global scope — ADR 0093). A UF segue a regra da
     * emissão (`ResolveUfEmitente`), para a tela mostrar a mesma origem que o motor usa.
     *
     * @return array{nome:?string,cnpj:?string,municipio:?string,uf:string,ano:int,ibs:?float,cbs:?float}
     */
    private function empresa(int $businessId): array
    {
        $business = DB::table('business')->where('id', $businessId)->first(['name', 'tax_number_1']);
        $municipio = DB::table('business_locations')->where('business_id', $businessId)->orderBy('id')->value('city');
        $ano = (int) now()->year;
        $ibsCbs = config('nfebrasil.ibs_cbs_por_ano', [])[$ano] ?? [];

        return [
            'nome'      => $business?->name,
            'cnpj'      => $business?->tax_number_1 ?: null,
            'municipio' => $municipio ?: null,
            'uf'        => $this->resolverUfEmitente($businessId),
            'ano'       => $ano,
            'ibs'       => isset($ibsCbs['ibs']) ? (float) $ibsCbs['ibs'] : null,
            'cbs'       => isset($ibsCbs['cbs']) ? (float) $ibsCbs['cbs'] : null,
        ];
    }

    private function regime(int $businessId): string
    {
        return ConferenciaNaturezaService::regimeDaConfig(
            NfeBusinessConfig::where('business_id', $businessId)->value('regime')
        );
    }

    /** @return list<array{nivel:string,secao:string,mensagem:string}> */
    private function alertas(array $n, int $businessId, ?int $proprioId): array
    {
        return $this->conferencia->conferir(
            $n,
            $this->regime($businessId),
            $this->outrasAtivas($this->daEmpresa($businessId)->get(), $proprioId),
        );
    }

    /** @return list<string> */
    private function outrasAtivas($todas, ?int $proprioId): array
    {
        return $todas->filter(fn (NfeOperacaoFiscal $o) => $o->id !== $proprioId && ($o->ativa ?? true))
            ->map(fn (NfeOperacaoFiscal $o) => (string) $o->nome)->values()->all();
    }

    /** Colunas gravadas a partir da natureza da tela. Nenhuma é lida pelo motor numa natureza editável. */
    private function colunas(array $n): array
    {
        return [
            'nome'                   => trim((string) $n['nome']),
            'tipo'                   => $n['tipo'] === 'E' ? 'entrada' : 'saida',
            'finalidade'             => (int) $n['fin'],
            'ativa'                  => (bool) $n['ativo'],
            'pede_nota_referenciada' => (bool) $n['ref'],
            'cfop'                   => ($n['cfop']['in'] ?? '') ?: null,
            'cfop_interestadual'     => ($n['cfop']['out'] ?? '') ?: null,
            'cfop_exterior'          => ($n['cfop']['ext'] ?? '') ?: null,
            'config_tela'            => array_intersect_key($n, array_flip(['icms', 'ipi', 'pis', 'cofins', 'ibs'])),
        ];
    }

    /** A operação no formato da tela. Operações sem `config_tela` (Venda, CSV) mostram só o que têm. */
    private function paraTela(NfeOperacaoFiscal $op): array
    {
        $cfop = (string) ($op->cfop ?? '');
        $tipo = $op->tipo ?? (in_array($cfop[0] ?? '', ['1', '2', '3'], true) ? 'entrada' : 'saida');

        return array_merge((array) ($op->config_tela ?? []), [
            'nome'  => (string) $op->nome,
            'tipo'  => $tipo === 'entrada' ? 'E' : 'S',
            'fin'   => (string) $op->finalidade,
            'ativo' => (bool) ($op->ativa ?? true),
            'ref'   => (bool) ($op->pede_nota_referenciada ?? false),
            'cfop'  => [
                'in'  => $cfop,
                'out' => (string) ($op->cfop_interestadual ?? ''),
                'ext' => (string) ($op->cfop_exterior ?? ''),
            ],
        ]);
    }

    private function slugLivre(int $businessId, string $nome): string
    {
        $base = Str::limit('tela-' . Str::slug($nome), 34, '');
        $slug = $base;
        for ($i = 2; NfeOperacaoFiscal::withTrashed()->where('business_id', $businessId)->where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    private function registrar(Request $request, int $businessId, NfeOperacaoFiscal $op, string $evento): void
    {
        activity('nfe.tributacao')
            ->causedBy($request->user())
            ->withProperties(['business_id' => $businessId, 'operacao_id' => $op->id, 'slug' => $op->slug])
            ->log($evento);
    }
}
