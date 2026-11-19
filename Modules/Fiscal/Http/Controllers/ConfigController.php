<?php

namespace Modules\Fiscal\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\ModoContingencia;
use Modules\NfeBrasil\Services\Tributacao\ContadorService;
use Modules\NFSe\Models\NfseProviderConfig;

/**
 * Cert/Cfg fiscal — UNIFICADO (sub-página 6 do design KB-9.75).
 *
 * Tela ÚNICA pra gestão de certificado A1 + ambiente SEFAZ + tributação.
 * Embute funcionalidade do Modules/NfeBrasil/Configuracao/Certificado (upload,
 * testar conexão, trocar ambiente) — forms apontam pros endpoints existentes
 * `/nfe-brasil/configuracao/certificado/*` (FormRequest valida permissão +
 * HasBusinessScope ADR 0093, ZERO duplicação de lógica).
 *
 * Permissão: superadmin OU fiscal.config.edit (+ nfe.configuracao.manage no
 * endpoint NfeBrasil pra mutações).
 */
class ConfigController extends Controller
{
    public function index(Request $request): Response
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.config.edit')) {
            abort(403, 'Sem permissão fiscal.config.edit');
        }

        // Aba ativa via rota (?tab=) — barra canônica PageHeaderTabs navega por href
        // (padronização DS Onda 3). Whitelist server-side; default 'cert'.
        $activeTab = (string) $request->query('tab', 'cert');
        if (! in_array($activeTab, ['cert', 'series', 'ambiente', 'sped'], true)) {
            $activeTab = 'cert';
        }

        $businessId = (int) $request->session()->get('business.id');
        $cnpjBusinessSession = (string) $request->session()->get('business.tax_number_1', '');

        // Cert ativo do business (escopo via HasBusinessScope global)
        $cert = NfeCertificado::query()
            ->where('ativo', true)
            ->orderByDesc('valido_ate')
            ->first();

        $config = NfeBusinessConfig::query()->first();

        $painel = $this->montarPainelFiscal($businessId, $cnpjBusinessSession);

        $certPayload = null;
        if ($cert) {
            $dias = $cert->valido_ate
                ? (int) now()->startOfDay()->diffInDays($cert->valido_ate, false)
                : null;

            $alerta = $dias === null
                ? null
                : ($dias < 0 ? 'vencido' : ($dias <= 30 ? 'proximo_vencimento' : 'ok'));

            // Fallback CNPJ titular (cert antigo pode ter vazio). A chave do painel é
            // `cnpjBusiness` — até 2026-11-05 isto lia `cnpj_business`, que não existe:
            // o fallback nunca chegava à tela, e o acesso à chave inexistente vira
            // ErrorException no Laravel quando o cert não tem CNPJ (UC-FCFG-11).
            $cnpjTitularRaw = $cert->cnpj_titular ?: '';
            $cnpjTitularFallback = $cnpjTitularRaw === '' ? ($painel['cnpjBusiness'] ?? null) : null;

            $certPayload = [
                'uuid'                  => $cert->uuid,
                'cnpjTitular'           => $cert->cnpj_titular,
                'cnpjTitularFallback'   => $cnpjTitularFallback,
                'validoAteIso'          => $cert->valido_ate?->toIso8601String(),
                'validoAteBr'           => $cert->valido_ate?->format('d/m/Y'),
                'diasRestantes'         => $dias,
                'alerta'                => $alerta,
                'ativo'                 => (bool) $cert->ativo,
            ];
        }

        return Inertia::render('Fiscal/Config', [
            'activeTab' => $activeTab,
            'certificado' => $certPayload,
            'config' => $config ? [
                'regime'              => $config->regime ?? 'lucro_presumido',
                'autoEmissionEnabled' => (bool) ($config->auto_emission_enabled ?? false),
                'tributacaoDefault'   => $config->tributacao_default ?? [],
                // US-NFE-006 / ADR TECH-0002 — estado da contingência SEFAZ deste tenant.
                // `diasAtiva` vem CALCULADO NO SERVIDOR de propósito: a ADR mitiga o risco
                // "tenant esquece ligado" com um aviso de DURAÇÃO, e duração calculada no
                // browser dependeria do relógio da máquina do operador.
                'contingencia'        => [
                    'ativa'        => (bool) ($config->em_contingencia ?? false),
                    'ativadaEmIso' => $config->contingencia_ativada_em?->toIso8601String(),
                    'ativadaEmBr'  => $config->contingencia_ativada_em?->format('d/m/Y H:i'),
                    'diasAtiva'    => $config->contingencia_ativada_em
                        ? (int) $config->contingencia_ativada_em->diffInDays(now())
                        : null,
                    'motivo'       => $config->contingencia_motivo,
                    // US-NFE-071 — modo escolhido (nulo = epec, o de antes da escolha) e a
                    // SEFAZ Virtual da UF do emitente, para a tela indicar qual SVC vale.
                    // A recusa da SVC errada é do ContingenciaService, não da tela.
                    'modo'         => $config->contingencia_modo ?: ModoContingencia::EPEC,
                    'svcDaUf'      => ModoContingencia::svcDaUf($painel['uf'] ?? null),
                    'uf'           => $painel['uf'] ?? null,
                ],
            ] : null,
            // Painel fiscal — espelha CertificadoController::status() do NfeBrasil
            'painel' => $painel,
            // Gate das duas ações de risco (trocar ambiente · substituir certificado).
            // Isto é ESPELHO, pra tela dizer o motivo em texto em vez de mostrar botão
            // cinza sem explicação. Quem RECUSA de verdade é o servidor, em
            // CertificadoController::garantirGateAmbiente — a tela não é a barreira.
            'podeTrocarAmbiente' => (bool) (
                auth()->user()->can('superadmin')
                || auth()->user()->can('fiscal.config.ambiente')
            ),
            // Card "Envio de documentos" (4º card da região `fiscal-config-cert-regime`).
            // LEITURA REAL das flags que governam os listeners que mandam o DANFE:
            // `EnviarDanfePorEmail` (NFe 55) e `EnviarDanfeNFCePorEmail` (NFC-e 65).
            // Ambas vivem em Modules/NfeBrasil/Config/config.php — são de DEPLOY, não
            // por business; a tela DIZ isso em texto, porque um selo por-empresa aqui
            // mentiria sobre o escopo.
            // Desde 2026-11-19 cada empresa pode escolher (`email_danfe_nfe/nfce`); sem
            // escolha vale a chave do servidor. `*Ativo` é o que de fato acontece.
            'envioDocumentos' => [
                'nfeAtivo'     => NfeBusinessConfig::envioDanfeAtivo($businessId, '55'),
                'nfceAtivo'    => NfeBusinessConfig::envioDanfeAtivo($businessId, '65'),
                'nfeEscolha'   => $config?->email_danfe_nfe,
                'nfceEscolha'  => $config?->email_danfe_nfce,
                'nfePadrao'    => (bool) config('nfebrasil.email_danfe_on_autorizada', true),
                'nfcePadrao'   => (bool) config('nfebrasil.email_danfe_nfce_on_autorizada', false),
                'temConfig'    => (bool) $config,
            ],
            // Contador da empresa (playbook Fiscal thread 15c). Null = não cadastrado.
            'contador' => $this->contadorPayload($businessId),
            // NFS-e: ambiente + prestador (nfse_provider_configs), editados em
            // POST /nfse/configuracao. Null = módulo NFSe ausente neste deploy.
            'nfse' => $this->nfsePayload($businessId),
            // Onda 2 I — séries fiscais (tab "Séries" do ModuleTopNav).
            // TODO[CL]: substituir por query real (business.numero_serie_nfe +
            // possíveis tabelas de séries auxiliares).
            'seriesMock' => $this->mockSeriesFiscais(
                $painel['serieNfe'] ?? '1',
                (int) ($painel['proximoNumero'] ?? 1),
            ),
        ]);
    }

    /**
     * POST /fiscal/config/contador — cadastro do contador (playbook Fiscal thread 15c · D-CONTADOR).
     * Mesmo gate da tela. Salvar também cria o papel `Contador#{business}`, se ainda não existir.
     */
    public function salvarContador(Request $request, ContadorService $service): RedirectResponse
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.config.edit')) {
            abort(403, 'Sem permissão fiscal.config.edit');
        }
        $dados = $request->validate([
            'nome'  => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'crc'   => ['nullable', 'string', 'max:40'],
        ]);

        $service->salvar((int) $request->session()->get('business.id'), $dados, (int) auth()->id());

        return back()->with('status', ['success' => 1, 'msg' => 'Contador salvo.']);
    }

    /**
     * POST /fiscal/config/envio — liga, desliga ou devolve ao padrão do servidor o envio
     * automático do DANFE desta empresa. Mesmo gate da tela. Sem configuração fiscal
     * (regime) a empresa não tem onde guardar a escolha: recusa em vez de criar uma
     * configuração com regime padrão, que mudaria a emissão.
     */
    public function salvarEnvio(Request $request): RedirectResponse
    {
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.config.edit')) {
            abort(403, 'Sem permissão fiscal.config.edit');
        }
        $dados = $request->validate([
            'nfe'  => ['required', 'in:padrao,ligado,desligado'],
            'nfce' => ['required', 'in:padrao,ligado,desligado'],
        ]);

        $businessId = (int) $request->session()->get('business.id');
        // SUPERADMIN: business_id explícito da sessão; nunca do request.
        $config = NfeBusinessConfig::withoutGlobalScopes()->where('business_id', $businessId)->first();
        if (! $config) {
            return back()->withErrors(['nfe' => 'Configure o regime fiscal da empresa antes de escolher o envio.']);
        }

        $valor = fn (string $v) => $v === 'padrao' ? null : $v === 'ligado';
        $config->email_danfe_nfe = $valor($dados['nfe']);
        $config->email_danfe_nfce = $valor($dados['nfce']);
        $config->save();

        return back()->with('status', ['success' => 1, 'msg' => 'Envio de documentos salvo.']);
    }

    /** @return array{nome: string, email: string, crc: string|null, papel: string}|null */
    /**
     * Ambiente e prestador da NFS-e desta empresa — campo separado de `business.ambiente`,
     * que é o da NF-e. `existe=false` = empresa ainda sem configuração (a emissão recusa).
     */
    private function nfsePayload(int $businessId): ?array
    {
        if (! class_exists(NfseProviderConfig::class) || ! Schema::hasTable('nfse_provider_configs')) {
            return null;
        }

        // SUPERADMIN: business_id explícito da sessão, mesmo critério do NfseEmissaoService.
        $c = NfseProviderConfig::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->whereNull('deleted_at')
            ->first();

        return [
            'existe'        => (bool) $c,
            'ambiente'      => $c?->ambiente ?? 'homologacao',
            'municipioIbge' => $c?->municipio_codigo_ibge,
            'prestadorCnpj' => $c?->prestador_cnpj,
            'prestadorIm'   => $c?->prestador_im,
            'serie'         => $c?->serie_default,
            'cnae'          => $c?->cnae,
            'temCertificado' => (bool) $c?->cert_id,
        ];
    }

    private function contadorPayload(int $businessId): ?array
    {
        $c = app(ContadorService::class)->doBusiness($businessId);

        return $c === null ? null : [
            'nome' => $c->nome, 'email' => $c->email, 'crc' => $c->crc,
            'papel' => ContadorService::nomePapel($businessId),
        ];
    }

    /**
     * Mock séries fiscais. Onda 2 I — tab Séries do Config.

     *
     * @return array<int, array<string, mixed>>
     */
    protected function mockSeriesFiscais(string $serieNfePrincipal, int $proximoNfe): array
    {
        return [
            ['modelo' => 55, 'serie' => $serieNfePrincipal, 'proximo' => $proximoNfe,
             'filial' => 'Matriz', 'ativo' => true, 'obs' => null],
            ['modelo' => 65, 'serie' => '9', 'proximo' => 9013,
             'filial' => 'Matriz · balcão', 'ativo' => true, 'obs' => null],
            ['modelo' => 55, 'serie' => '2', 'proximo' => 1,
             'filial' => 'Filial 02 (futuro)', 'ativo' => false, 'obs' => 'Reservada — sem operação ainda'],
        ];
    }

    /**
     * Coleta dados consolidados pra painel fiscal — espelha
     * Modules\NfeBrasil\Http\Controllers\CertificadoController::montarPainelFiscal.
     *
     * Inclui: CNPJ + razão social + regime + numeração NFe + tributação default
     * + UF/cidade + ambiente SEFAZ (1=produção, 2=homologação).
     *
     * Defensivo — colunas opcionais não quebram se ausentes.
     *
     * @return array<string, mixed>
     */
    private function montarPainelFiscal(int $businessId, string $cnpjBusinessSession): array
    {
        $business = DB::table('business')->where('id', $businessId)->first();
        $config   = DB::table('nfe_business_configs')->where('business_id', $businessId)->first();
        $location = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->first();

        $tributacao = $config?->tributacao_default
            ? json_decode($config->tributacao_default, true)
            : null;

        $serie  = (string) ($business->numero_serie_nfe ?? '1');
        $ultimo = (int) ($business->ultimo_numero_nfe ?? 0);

        return [
            'cnpjBusiness'   => $cnpjBusinessSession ?: ($business->cnpj ?? null),
            'razaoSocial'    => $business->name ?? null,
            'regime'         => $config?->regime,
            'ncmPadrao'      => $business->ncm_padrao ?? null,
            'serieNfe'       => $serie,
            'ultimoNumero'   => $ultimo,
            'proximoNumero'  => $ultimo + 1,
            'cfopDefault'    => $tributacao['cfop'] ?? null,
            'csosnDefault'   => $tributacao['csosn'] ?? null,
            'cstDefault'     => $tributacao['cst'] ?? null,
            'uf'             => $location?->state ?? null,
            'cidade'         => $location?->city ?? null,
            'ambiente'       => (int) ($business->ambiente ?? 2),
        ];
    }
}
