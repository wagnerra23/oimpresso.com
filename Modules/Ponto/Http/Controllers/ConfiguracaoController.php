<?php

namespace Modules\Ponto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ponto\Entities\Rep;

class ConfiguracaoController extends Controller
{
    /**
     * Painel de LEITURA dos parâmetros do módulo.
     *
     * ⚠️ O payload é montado por ALLOWLIST, e isso não é preciosismo: prop do Inertia viaja
     * inteira no HTML servido ao browser, e `config('pontowr2')` carrega
     * `rep.certificado_icp_pass` — a senha do certificado ICP-Brasil usado pra assinar
     * marcação (PKCS#7 A1). Antes desta mudança o config INTEIRO ia pra tela: 14 blocos,
     * incluindo o caminho e a senha do certificado, `esocial.*` e `ultimatepos.*`.
     *
     * Medido antes de mexer (sonda no CT 100, não leitura): com
     * `pontowr2.rep.certificado_icp_pass` definido, a string aparecia no corpo da resposta de
     * `/ponto/configuracoes` — status 200, para qualquer usuário com `ponto.access`.
     *
     * Os 4 blocos abaixo são exatamente os que a tela renderiza (`Configuracoes/Index.tsx`:
     * `config.clt`, `config.banco_horas`, `config.rep`, `config.afd`). O bloco `esocial`
     * existe na interface TypeScript mas não é lido em lugar nenhum do render — por isso
     * fica de fora. Comportamento visível da tela: inalterado.
     *
     * Defendido por `UC-CFGIDX-01` (`Configuracoes/Index.casos.md`).
     */
    public function index(): Response
    {
        $cfg = config('pontowr2');

        return Inertia::render('Ponto/Configuracoes/Index', [
            'config' => [
                'clt'         => $cfg['clt'] ?? [],
                'banco_horas' => $cfg['banco_horas'] ?? [],
                // `Arr::except` e não uma lista de chaves permitidas: o bloco `rep` ganha
                // parâmetro novo com alguma frequência, e uma allowlist aqui faria o
                // parâmetro novo sumir da tela em silêncio. O que não pode passar é o
                // segredo, e ele é nomeado.
                'rep'         => Arr::except($cfg['rep'] ?? [], ['certificado_icp_path', 'certificado_icp_pass']),
                'afd'         => $cfg['afd'] ?? [],
            ],
        ]);
    }

    public function reps(Request $request): Response
    {
        $businessId = session('business.id') ?? $request->user()->business_id;
        $paginated = Rep::where('business_id', $businessId)
            ->orderBy('identificador')
            ->paginate(20)
            ->withQueryString();

        $paginated->getCollection()->transform(fn ($r) => [
            'id'            => $r->id,
            'tipo'          => $r->tipo,
            'identificador' => $r->identificador,
            'descricao'     => $r->descricao,
            'local'         => $r->local,
            'cnpj'          => $r->cnpj,
            'ativo'         => (bool) ($r->ativo ?? true),
        ]);

        return Inertia::render('Ponto/Configuracoes/Reps', [
            'reps' => $paginated,
        ]);
    }

    public function storeRep(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tipo'          => 'required|in:REP_P,REP_C,REP_A',
            'identificador' => 'required|string|size:17|unique:ponto_reps,identificador',
            'descricao'     => 'required|string|max:120',
            'local'         => 'nullable|string|max:120',
            'cnpj'          => 'nullable|string|size:14',
        ]);

        $validated['business_id'] = session('business.id') ?? $request->user()->business_id;
        Rep::create($validated);

        return back()->with('success', 'REP cadastrado.');
    }
}
