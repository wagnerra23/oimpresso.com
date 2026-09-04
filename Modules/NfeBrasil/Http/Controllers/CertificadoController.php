<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\NfeBrasil\Http\Requests\UploadCertificadoRequest;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;
use RuntimeException;
use Throwable;

/**
 * US-NFE-041 — gerenciamento do certificado A1 do business.
 *
 * Permissão: `nfe.configuracao.manage` (validada no FormRequest::authorize +
 * declarada em Modules\NfeBrasil\Http\Controllers\DataController::user_permissions).
 *
 * As duas MUTAÇÕES de risco — `upload` (substituir certificado) e `updateAmbiente`
 * (trocar ambiente SEFAZ) — exigem ADICIONALMENTE `fiscal.config.ambiente`, via
 * garantirGateAmbiente(). São as ações que param a emissão da empresa inteira.
 *
 * Pattern: Inertia (status() = render; upload() = redirect+flash). ADR 0029.
 */
class CertificadoController extends Controller
{
    public function __construct(private readonly CertificadoService $service) {}

    /**
     * GET /nfe-brasil/configuracao/certificado
     *
     * DEPRECATED 2026-05-27 — Wagner consolidou tela cert em /fiscal/config
     * (Fiscal/Config.tsx unificada). Page Pages/NfeBrasil/Configuracao/Certificado.tsx
     * removida. Mantém método pra compat retroativa com testes diretos
     * `$controller->status()` enquanto migração de testes não rola.
     *
     * Rota agora retorna redirect 302 → /fiscal/config (routes/web.php).
     * Painel fiscal vive em Modules\Fiscal\Http\Controllers\ConfigController::montarPainelFiscal.
     *
     * Em runtime, este método NÃO é mais chamado (rota é Closure redirect).
     * Se chamado diretamente (testes), retorna RedirectResponse pra /fiscal/config.
     */
    public function status(Request $request): RedirectResponse
    {
        return redirect('/fiscal/config', 302);
    }

    /**
     * POST /nfe-brasil/configuracao/certificado
     *
     * Upload + validação + storage encrypted-at-rest.
     * Senha NUNCA loga (FormRequest a separa do payload do audit).
     */
    public function upload(UploadCertificadoRequest $request): RedirectResponse
    {
        $this->garantirGateAmbiente($request);

        $businessId = (int) $request->session()->get('business.id');
        $cnpjBusiness = (string) $request->session()->get('business.tax_number_1', '');

        $pfxBase64 = base64_encode(file_get_contents($request->file('certificado')->getRealPath()));
        $senha = (string) $request->input('senha');

        try {
            $cert = $this->service->salvar(
                $businessId,
                $pfxBase64,
                $senha,
                array_filter(['cnpj_titular' => $cnpjBusiness ?: null]),
            );
        } catch (InvalidArgumentException $e) {
            return back()
                ->withErrors(['certificado' => $e->getMessage()])
                ->withInput($request->only('senha') ? [] : []); // nunca repõe senha
        }

        // Audit log SEM senha e SEM path do arquivo
        activity('nfe.certificado')
            ->causedBy($request->user())
            ->withProperties([
                'business_id'  => $businessId,
                'cnpj_titular' => $cert->cnpj_titular,
                'valido_ate'   => $cert->valido_ate->format('Y-m-d'),
            ])
            ->log('certificado.uploaded');

        return redirect()
            ->route('nfe-brasil.certificado.status')
            ->with('success', "Certificado A1 cadastrado. CNPJ {$cert->cnpj_titular} válido até {$cert->valido_ate->format('d/m/Y')}.");
    }

    /**
     * POST /nfe-brasil/configuracao/certificado/testar
     *
     * Testa cert + conexão SEFAZ via NFeStatusServico (cstat=107 esperado).
     * Não emite NF-e nenhuma — só ping de status. Idempotente, seguro pra
     * chamar sob demanda (botão UI).
     *
     * Resposta JSON pra polling/feedback rápido — Inertia partial reload
     * (`only:[]`) preserva o resto da página.
     */
    public function testar(Request $request, NfeService $nfeService): JsonResponse
    {
        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return response()->json(['ok' => false, 'error' => 'no_business_context'], 400);
        }

        // Cert obrigatório — sem ele, sequer chama o service
        $cert = NfeCertificado::where('business_id', $businessId)
            ->where('ativo', true)
            ->first();
        if (! $cert) {
            return response()->json([
                'ok'      => false,
                'error'   => 'sem_certificado',
                'xMotivo' => 'Cadastre um certificado A1 antes de testar a conexão.',
            ], 422);
        }

        // Carrega contexto do business pra payload de erro ter UF/ambiente
        // mesmo quando o service explode antes de chegar a SEFAZ.
        $businessRow = \DB::table('business')->where('id', $businessId)->first();
        $ufFallback  = $this->resolveUfBusinessLocation($businessId);
        $ambiente    = (int) ($businessRow->ambiente ?? 2);

        try {
            $resultado = $nfeService->consultarStatusSefaz($businessId);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok'            => false,
                'error'         => 'sefaz_failure',
                'cstat'         => '—',
                'xMotivo'       => $e->getMessage(),
                'tempoResposta' => 0,
                'ambiente'      => $ambiente,
                'uf'            => $ufFallback,
                'versao'        => null,
            ], 502);
        } catch (Throwable $e) {
            return response()->json([
                'ok'            => false,
                'error'         => 'unexpected',
                'cstat'         => '—',
                'xMotivo'       => 'Erro inesperado: ' . $e->getMessage(),
                'tempoResposta' => 0,
                'ambiente'      => $ambiente,
                'uf'            => $ufFallback,
                'versao'        => null,
            ], 500);
        }

        // Audit log (sem dados sensíveis)
        activity('nfe.certificado')
            ->causedBy($request->user())
            ->withProperties([
                'business_id' => $businessId,
                'cstat'       => $resultado['cstat'],
                'ok'          => $resultado['ok'],
                'tempo'       => $resultado['tempoResposta'],
            ])
            ->log('certificado.status_sefaz_consultado');

        return response()->json($resultado);
    }

    /**
     * POST /nfe-brasil/configuracao/certificado/ambiente
     *
     * Atualiza `business.ambiente` (1=produção, 2=homologação). Inertia redirect
     * de volta pra status() com flash success — preserva contexto da página
     * sem reload total.
     *
     * Audit log captura mudança (sem dados fiscais sensíveis) — inclui o MOTIVO
     * digitado, porque sem ele a trilha diz "quem e quando" mas não "por quê".
     *
     * Gate: `fiscal.config.ambiente` (ver garantirGateAmbiente). Até 2026-09-04
     * este método não tinha gate NENHUM — nem FormRequest, nem middleware na rota.
     *
     * Cerimônia (só quando há TROCA de verdade): o nome do destino escrito à mão
     * + motivo de 15+ caracteres. Confirmação que não bate deixa o ambiente
     * INALTERADO e devolve o erro dizendo isso.
     */
    public function updateAmbiente(Request $request): RedirectResponse
    {
        $this->garantirGateAmbiente($request);

        $businessId = (int) $request->session()->get('business.id', 0);
        if ($businessId === 0) {
            return back()->withErrors(['ambiente' => 'Sessão sem business.']);
        }

        $validated = $request->validate([
            'ambiente' => 'required|integer|in:1,2',
        ]);

        $ambienteAntes = (int) (\DB::table('business')
            ->where('id', $businessId)
            ->value('ambiente') ?? 2);
        $ambienteNovo = (int) $validated['ambiente'];

        // Sem TROCA não há cerimônia: postar o valor que já está gravado não muda
        // nada, então não faz sentido exigir motivo nem confirmação. Este early
        // return vem ANTES da validação da cerimônia de propósito.
        if ($ambienteAntes === $ambienteNovo) {
            return back()->with('success', 'Ambiente já estava configurado nesse valor.');
        }

        $label = $ambienteNovo === 1 ? 'PRODUÇÃO' : 'HOMOLOGAÇÃO';

        // ── Cerimônia da troca ────────────────────────────────────────────────
        // Confirmação de uma palavra genérica ("sim", "ok") não segura uma ação
        // que muda o valor fiscal de TODA nota emitida depois dela. Por isso são
        // duas provas independentes: o nome do destino escrito à mão E um motivo
        // que o fisco possa ler depois.
        $cerimonia = $request->validate([
            'motivo'      => 'required|string|min:15|max:255',
            'confirmacao' => 'required|string|max:40',
        ], [
            'motivo.required'      => 'Escreva o motivo da troca — ele fica registrado na auditoria.',
            'motivo.min'           => 'O motivo precisa de pelo menos 15 caracteres.',
            'confirmacao.required' => "Digite {$label} para confirmar.",
        ]);

        // Case- e acento-insensível DE PROPÓSITO: a fricção que importa é ter de
        // escrever a palavra (contra a troca por reflexo), não acertar o cedilha.
        // Exigir o acento puniria teclado, não desatenção.
        if ($this->normalizarConfirmacao($cerimonia['confirmacao']) !== $this->normalizarConfirmacao($label)) {
            return back()
                ->withInput()
                ->withErrors([
                    'confirmacao' => "A confirmação não bate: digite {$label} para trocar. O ambiente NÃO foi alterado.",
                ]);
        }

        \DB::table('business')
            ->where('id', $businessId)
            ->update(['ambiente' => $ambienteNovo]);

        activity('nfe.certificado')
            ->causedBy($request->user())
            ->withProperties([
                'business_id'   => $businessId,
                'ambiente_de'   => $ambienteAntes,
                'ambiente_para' => $ambienteNovo,
                // O motivo entra no evento porque sem ele a trilha responde "quem e
                // quando" mas não "por quê" — e é o "por quê" que alguém precisa
                // quando for explicar por que a nota de terça não tem valor fiscal.
                'motivo'        => $cerimonia['motivo'],
            ])
            ->log('certificado.ambiente_alterado');

        return redirect()
            ->route('nfe-brasil.certificado.status')
            ->with('success', "Ambiente SEFAZ alterado para {$label}.");
    }

    /**
     * Gate das DUAS ações que param a emissão da empresa inteira: trocar o
     * ambiente SEFAZ e substituir o certificado A1.
     *
     * POR QUE SEPARADO de `fiscal.config.edit`: editar o e-mail do contador e
     * trocar o ambiente de emissão não são o mesmo risco — a segunda muda o valor
     * fiscal de TODA nota emitida depois dela. Charter `Fiscal/Config`, decisão
     * [W] 2026-08-24 (`prototipo-ui/design-docs/cowork-inbox/fiscal/`).
     *
     * FAIL-SECURE por desenho: sem a permissão, 403. Isto FECHA um buraco real —
     * até 2026-09-04 o `updateAmbiente` não tinha gate nenhum (a rota só carrega
     * `web/auth/SetSessionData/language/timezone/AdminSidebarMenu`, e o método
     * recebia `Request` puro, sem FormRequest). Qualquer usuário autenticado com
     * business em sessão conseguia inverter produção↔homologação.
     *
     * A mensagem repete o texto que a tela mostra no campo travado, pra quem
     * levar o 403 ao suporte encontrar a mesma frase dos dois lados.
     */
    /**
     * Normaliza a palavra de confirmação: caixa alta + sem acento.
     *
     * O mapa é EXPLÍCITO em vez de `iconv('ASCII//TRANSLIT')` porque aquele
     * depende de locale do servidor e devolve resultado diferente conforme o
     * ambiente — inaceitável num predicado que decide se a emissão fiscal muda.
     * Cobre o que as duas palavras usam (Ç, Ã em PRODUÇÃO e HOMOLOGAÇÃO) mais os
     * acentos vizinhos, pra quem digitar variação não ser recusado por engano.
     */
    private function normalizarConfirmacao(string $valor): string
    {
        $semAcento = strtr(trim($valor), [
            'Á' => 'A', 'á' => 'a', 'À' => 'A', 'à' => 'a', 'Â' => 'A', 'â' => 'a',
            'Ã' => 'A', 'ã' => 'a', 'É' => 'E', 'é' => 'e', 'Ê' => 'E', 'ê' => 'e',
            'Í' => 'I', 'í' => 'i', 'Ó' => 'O', 'ó' => 'o', 'Ô' => 'O', 'ô' => 'o',
            'Õ' => 'O', 'õ' => 'o', 'Ú' => 'U', 'ú' => 'u', 'Ç' => 'C', 'ç' => 'c',
        ]);

        return mb_strtoupper($semAcento, 'UTF-8');
    }

    private function garantirGateAmbiente(Request $request): void
    {
        $user = $request->user();

        if ($user && ($user->can('superadmin') || $user->can('fiscal.config.ambiente'))) {
            return;
        }

        abort(403, 'Exige fiscal.config.ambiente');
    }

    /**
     * Resolve UF do business via business_locations (mesmo critério do
     * NfeService). Garante payload de erro contextualizado mesmo quando
     * o service explode antes de chegar a SEFAZ. Fallback 'SP'.
     */
    private function resolveUfBusinessLocation(int $businessId): string
    {
        $loc = \DB::table('business_locations')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->first();

        $state = $loc?->state ?? '';
        return preg_match('/^[A-Z]{2}$/', $state) ? $state : 'SP';
    }
}
