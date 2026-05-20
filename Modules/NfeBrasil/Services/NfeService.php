<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Transaction;
use App\Util\OtelHelper;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\RecurringBilling\Models\Invoice;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Make;
use NFePHP\NFe\Tools;
use RuntimeException;

/**
 * US-NFE-042 · NfeService — emissão NF-e modelo 55 via sped-nfe.
 *
 * Responsabilidades:
 *   - Idempotência por (business_id, transaction_id)
 *   - Próximo número fiscal via nfe_emissoes.max(numero) + lock
 *   - Assinatura + transmissão SEFAZ (mockável via $toolsFactory)
 *   - Persiste XML em storage/app/nfe-brasil/{biz}/notas/{serie}-{numero}.xml
 *   - Grava NfeEmissao com status/cstat/chave_44/motivo
 *   - Atualiza business.ultimo_numero_nfe na autorização
 *
 * Entrada ($dadosNfe):
 *   transaction_id?: int|null   — idempotência
 *   modelo?:         '55'|'65'  — default '55'
 *   serie?:          string     — default business.numero_serie_nfe ?? '1'
 *   numero?:         int|null   — auto se null
 *   nat_op:          string     — natureza da operação (required)
 *   emit?:           array      — sobreposição de dados do emitente (business como default)
 *   dest:            array      — dados do destinatário (required)
 *   dets:            array[]    — itens da nota (required)
 *   total:           array      — totais ICMSTot pré-calculados (required)
 *   pag:             array[]    — pagamentos (required)
 *   valor_total:     float      — total líquido (required)
 *   inf_cpl?:        string     — informações complementares
 *
 * Multi-tenant: business_id sempre escopa. Ver skill multi-tenant-patterns.
 * ADR 0090: CertificadoService já aplica fallback legado — NfeService não duplica.
 */
class NfeService
{
    /**
     * @param CertificadoService $certificadoService
     * @param Closure|null $toolsFactory Override para testes. Assinatura:
     *   fn(string $configJson, array $certData): Tools
     *   $certData = {pfx_binary, senha, ...} — igual ao retorno de carregarParaSefaz()
     */
    public function __construct(
        private readonly CertificadoService $certificadoService,
        private readonly ?Closure $toolsFactory = null,
        private readonly ?MotorTributarioService $motor = null,
    ) {}

    /**
     * US-RB-044 fase 2 · Emite NF-e modelo 55 a partir de uma Invoice
     * de cobrança recorrente.
     *
     * Pré-requisitos no business (validados, falham fast):
     *   1. `nfe_certificados` ativo (CertificadoService)
     *   2. `nfe_business_configs.tributacao_default.ncm_default` configurado
     *      — sem isso o motor não sabe qual NCM usar pra Cobrança Recorrente
     *
     * Idempotência: usa `transaction_id = invoice.id` (UNIQUE em nfe_emissoes)
     * — segunda chamada com mesma invoice retorna emissão existente.
     *
     * Defensivo com dados ausentes do destinatário (UF/CEP/etc): fallback
     * pra UF do business (operação interna). Documento (CNPJ/CPF) é único
     * obrigatório no Contact — se ausente, lança RuntimeException.
     *
     * Usa MotorTributarioService (US-NFE-043 cascade ADR ARQ-0006) pra
     * resolver CFOP/CSOSN/CST/alíquotas. Sem motor = constructor injection
     * lazy resolve via container.
     *
     * @throws RuntimeException Quando ncm_default não configurado, contact
     *                          ausente, ou invoice sem valor positivo.
     */
    public function emitirParaInvoice(Invoice $invoice): NfeEmissao
    {
        $businessId = (int) $invoice->business_id;

        // Pré-validações — falham fast antes de tocar SEFAZ
        if ((float) $invoice->valor <= 0) {
            throw new RuntimeException(
                "Invoice {$invoice->numero_documento} sem valor positivo — não emite NF-e."
            );
        }

        $contact = $invoice->contact;
        if (! $contact) {
            throw new RuntimeException(
                "Invoice {$invoice->numero_documento} sem contact_id — não há destinatário."
            );
        }

        $documentoDest = preg_replace('/\D/', '', (string) ($contact->tax_number ?? ''));
        if (! in_array(strlen($documentoDest), [11, 14], true)) {
            throw new RuntimeException(
                "Contact {$contact->id} sem CPF/CNPJ válido (tax_number)."
            );
        }

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $config = NfeBusinessConfig::where('business_id', $businessId)->first();
        // Fallback: nfe_business_configs.tributacao_default.ncm_default → business.ncm_padrao
        $ncmDefault = (string) ($config?->tributacao_default['ncm_default']
            ?? $business->ncm_padrao
            ?? '');
        if (strlen($ncmDefault) !== 8) {
            throw new RuntimeException(
                "Business {$businessId} sem NCM padrão configurado. " .
                "Configure `ncm_padrao` no cadastro do business ou `nfe_business_configs.tributacao_default.ncm_default`."
            );
        }

        $ufOrigem = $this->resolverUF($business);
        $ufDestino = strtoupper((string) ($contact->state ?? '')) ?: $ufOrigem;
        if (! preg_match('/^[A-Z]{2}$/', $ufDestino)) {
            $ufDestino = $ufOrigem;
        }

        // MotorTributarioService cascade — ADR ARQ-0006
        $motor = $this->motor ?? app(MotorTributarioService::class);
        $tributo = $motor->calcular(
            new ProdutoFiscalContext(
                ncm:         $ncmDefault,
                valor:       (float) $invoice->valor,
                description: "Cobrança recorrente {$invoice->numero_documento}",
            ),
            businessId: $businessId,
            ufOrigem:   $ufOrigem,
            ufDestino:  $ufDestino,
        );

        $dadosNfe = [
            'transaction_id' => $invoice->id,
            'nat_op'         => 'COBRANCA RECORRENTE',
            'dest' => [
                'nome'         => substr((string) ($contact->supplier_business_name ?: $contact->name), 0, 60),
                strlen($documentoDest) === 14 ? 'cnpj' : 'cpf' => $documentoDest,
                'ind_ie_dest'  => '9', // 9 = não contribuinte (default seguro pra recorrência B2C/B2B sem IE)
                'logradouro'   => substr((string) ($contact->address_line_1 ?? 'NAO INFORMADO'), 0, 60),
                'numero'       => 'SN',
                'bairro'       => substr((string) ($contact->address_line_2 ?? 'CENTRO'), 0, 60),
                'municipio'    => substr((string) ($contact->city ?? 'NAO INFORMADO'), 0, 60),
                'cod_municipio' => '9999999', // UPos não tem código IBGE — placeholder; SEFAZ rejeita em prod, fix futuro
                'uf'           => $ufDestino,
                'cep'          => preg_replace('/\D/', '', (string) ($contact->zip_code ?? '00000000')),
                'email'        => $contact->email ?? null,
            ],
            'dets' => [[
                'cprod'   => 'INV-' . $invoice->id,
                'xprod'   => substr((string) "Cobranca recorrente {$invoice->numero_documento}", 0, 120),
                'ncm'     => $ncmDefault,
                'cfop'    => $tributo->cfop,
                'ucm'     => 'UN',
                'qcom'    => 1.0,
                'vuncom'  => (float) $invoice->valor,
                'vprod'   => (float) $invoice->valor,
                'utrib'   => 'UN',
                'qtrib'   => 1.0,
                'vuntrib' => (float) $invoice->valor,
                'ind_tot' => 1,
                'icms'    => [
                    'cst_csosn' => $tributo->csosn ?? $tributo->cst ?? '102',
                    'orig'      => 0,
                    'vbc'       => 0,
                    'picms'     => $tributo->aliquota_icms,
                    'vicms'     => $tributo->valor_icms,
                ],
                'pis'     => [
                    'cst'   => '07', // 07 = isenta — default seguro Simples Nacional
                    'vbc'   => 0,
                    'ppis'  => $tributo->aliquota_pis,
                    'vpis'  => $tributo->valor_pis,
                ],
                'cofins'  => [
                    'cst'      => '07',
                    'vbc'      => 0,
                    'pcofins'  => $tributo->aliquota_cofins,
                    'vcofins'  => $tributo->valor_cofins,
                ],
            ]],
            'total' => [
                'v_prod'    => (float) $invoice->valor,
                'v_bc_icms' => 0,
                'v_icms'    => $tributo->valor_icms,
                'v_pis'     => $tributo->valor_pis,
                'v_cofins'  => $tributo->valor_cofins,
                'v_nf'      => (float) $invoice->valor,
                'v_desc'    => 0,
                'v_frete'   => 0,
            ],
            'pag'         => [['tpag' => '99', 'vpag' => (float) $invoice->valor]], // 99 = "outros" — gateway info não fica no XML
            'valor_total' => (float) $invoice->valor,
            'inf_cpl'     => "Cobranca recorrente referente a {$invoice->numero_documento}.",
        ];

        return $this->emitir($businessId, $dadosNfe);
    }

    /**
     * US-NFE-002 fase 2A · Emite NFC-e (modelo 65) a partir de uma Transaction
     * de venda finalizada no POS.
     *
     * **Diferença vs `emitirParaInvoice`:**
     *   - Modelo 65 (NFC-e) em vez de 55 (NFe B2B)
     *   - Gatilho: venda balcão (Listener `SellCreatedOrModified`)
     *   - Destinatário: pode ser anônimo (consumidor final) — CPF/CNPJ opcional
     *     no NFC-e, diferente de NFe55 que exige doc válido
     *   - `ind_pres` = 1 (presencial — venda física no balcão)
     *   - `ind_final` = 1 (consumidor final B2C)
     *
     * **Idempotência:** `transaction_id = $tx->id` UNIQUE em `nfe_emissoes`.
     * Re-chamada com mesma transaction = retorna emissão existente.
     *
     * **Pré-requisitos:**
     *   1. `nfe_certificados` ativo (CertificadoService)
     *   2. `nfe_business_configs.tributacao_default.ncm_default` configurado
     *   3. Transaction `final_total > 0`
     *
     * **Limitações fase 2A** (refinar em fase 2B):
     *   - Item da nota é **placeholder único** (nome="Venda PDV #X") — pra MVP
     *     não enumeramos `transaction_sell_lines` ainda. Cada linha vira 1 row
     *     do XML quando enumeração for adicionada (fase 2B).
     *   - CPF do consumidor não capturado da Transaction — fica anônimo (CNPJ
     *     '99999999999999' fictício; SEFAZ aceita NFC-e sem doc destinatário
     *     se valor < R$ 10.000).
     *   - Pagamento `tpag='99'` (outros) — fase 2B detecta forma real via
     *     `transaction_payments`.
     *
     * @throws RuntimeException Quando `final_total <= 0`, ncm_default ausente, ou business não encontrado.
     */
    public function emitirParaTransaction(Transaction $tx, string $modelo = '65'): NfeEmissao
    {
        $businessId = (int) $tx->business_id;

        if ((float) $tx->final_total <= 0) {
            throw new RuntimeException(
                "Transaction {$tx->id} sem valor positivo (final_total={$tx->final_total}) — não emite NFC-e."
            );
        }

        $config = NfeBusinessConfig::where('business_id', $businessId)->first();
        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $ncmDefault = (string) ($config?->tributacao_default['ncm_default']
            ?? $business->ncm_padrao
            ?? '');
        if (strlen($ncmDefault) !== 8) {
            throw new RuntimeException(
                "Business {$businessId} sem NCM padrão configurado. " .
                "Aplique um template em /nfe-brasil/tributacao OU configure `tributacao_default.ncm_default`."
            );
        }

        $ufOrigem = $this->resolverUF($business);

        // MotorTributarioService cascade — ADR ARQ-0006
        $motor = $this->motor ?? app(MotorTributarioService::class);
        $tributo = $motor->calcular(
            new ProdutoFiscalContext(
                ncm:         $ncmDefault,
                valor:       (float) $tx->final_total,
                description: "Venda PDV #{$tx->id}",
            ),
            businessId: $businessId,
            ufOrigem:   $ufOrigem,
            ufDestino:  $ufOrigem, // NFC-e é sempre intra-estadual (varejo balcão)
        );

        $valorTotal = (float) $tx->final_total;

        $dadosNfe = [
            'transaction_id' => $tx->id,
            'modelo'         => $modelo,
            'nat_op'         => 'VENDA AO CONSUMIDOR',
            'dest' => [
                // NFC-e B2C anônimo: doc é opcional. Se Transaction tiver contact_id
                // com CPF, fase 2B preenche. Por ora, default anônimo.
                'nome'         => 'CONSUMIDOR FINAL',
                'ind_ie_dest'  => '9', // 9 = não contribuinte (B2C balcão)
                'logradouro'   => 'NAO INFORMADO',
                'numero'       => 'SN',
                'bairro'       => 'CENTRO',
                'municipio'    => 'NAO INFORMADO',
                'cod_municipio' => '9999999', // placeholder; fase 2B usa cidade do business
                'uf'           => $ufOrigem,
                'cep'          => '00000000',
            ],
            'dets' => [[
                'cprod'   => 'PDV-' . $tx->id,
                'xprod'   => substr("Venda PDV #{$tx->id}", 0, 120),
                'ncm'     => $ncmDefault,
                'cfop'    => $tributo->cfop,
                'ucm'     => 'UN',
                'qcom'    => 1.0,
                'vuncom'  => $valorTotal,
                'vprod'   => $valorTotal,
                'utrib'   => 'UN',
                'qtrib'   => 1.0,
                'vuntrib' => $valorTotal,
                'ind_tot' => 1,
                'icms'    => [
                    'cst_csosn' => $tributo->csosn ?? $tributo->cst ?? '102',
                    'orig'      => 0,
                    'vbc'       => 0,
                    'picms'     => $tributo->aliquota_icms,
                    'vicms'     => $tributo->valor_icms,
                ],
                'pis'     => [
                    'cst'   => '07', // 07 = isenta — Simples Nacional default
                    'vbc'   => 0,
                    'ppis'  => $tributo->aliquota_pis,
                    'vpis'  => $tributo->valor_pis,
                ],
                'cofins'  => [
                    'cst'      => '07',
                    'vbc'      => 0,
                    'pcofins'  => $tributo->aliquota_cofins,
                    'vcofins'  => $tributo->valor_cofins,
                ],
            ]],
            'total' => [
                'v_prod'    => $valorTotal,
                'v_bc_icms' => 0,
                'v_icms'    => $tributo->valor_icms,
                'v_pis'     => $tributo->valor_pis,
                'v_cofins'  => $tributo->valor_cofins,
                'v_nf'      => $valorTotal,
                'v_desc'    => 0,
                'v_frete'   => 0,
            ],
            // tpag='01' = dinheiro (default conservador). Fase 2B detecta via transaction_payments.
            'pag'         => [['tpag' => '01', 'vpag' => $valorTotal]],
            'valor_total' => $valorTotal,
            'inf_cpl'     => "Venda PDV #{$tx->id}.",
        ];

        return $this->emitir($businessId, $dadosNfe);
    }

    /**
     * Emite NF-e via SEFAZ e persiste resultado em nfe_emissoes.
     *
     * @throws RuntimeException Se cert ausente, business não encontrado, ou falha de infra
     */
    public function emitir(int $businessId, array $dadosNfe): NfeEmissao
    {
        $modelo        = $dadosNfe['modelo'] ?? '55';
        $transactionId = isset($dadosNfe['transaction_id']) ? (int) $dadosNfe['transaction_id'] : null;

        // D9.a OTel — span envolve TUDO (validação, idempotência, reserva número,
        // HTTP SEFAZ sefazEnviaLote, processamento retorno). p99 crítico SEFAZ.
        return OtelHelper::spanBiz('nfe.emitir', function () use ($businessId, $dadosNfe, $modelo, $transactionId): NfeEmissao {
            return $this->emitirInterno($businessId, $dadosNfe, $modelo, $transactionId);
        }, [
            'module'         => 'NfeBrasil',
            'modelo'         => (string) $modelo,
            'transaction_id' => $transactionId,
        ]);
    }

    /**
     * @internal Corpo real de emitir() — separado para wrap em OtelHelper::spanBiz().
     *           D9.a OTel instrumentação SEFAZ webservice p99 crítico.
     */
    private function emitirInterno(int $businessId, array $dadosNfe, string $modelo, ?int $transactionId): NfeEmissao
    {
        // ── 1. Idempotência ─────────────────────────────────────────────────
        // SEFAZ distingue 3 estados terminais (US-SELL-029):
        //   - autorizada → número usado oficialmente, imutável
        //   - cancelada (via evento SEFAZ) → número usado oficialmente, NÃO pode ser
        //     reaproveitado nem deletado (CONFAZ SINIEF 07/2005 Art. 14). Bloqueia
        //     retry com mensagem instrutiva — nova emissão exige nova transaction.
        //   - rejeitada/denegada/erro_envio → número NÃO foi declarado pra Receita.
        //     Permite retry, mas preserva registro como `inutilizado` (não hard
        //     delete) pra rastreabilidade fiscal. Inutilização SEFAZ formal via
        //     NfeInutilizacaoService (US-SELL-030).
        if ($transactionId !== null) {
            $existente = NfeEmissao::where('business_id', $businessId)
                ->where('transaction_id', $transactionId)
                ->first();

            if ($existente) {
                if (in_array($existente->status, ['autorizada', 'pendente'], true)) {
                    Log::info('NfeService: idempotência — emissão existente positiva', [
                        'business_id'    => $businessId,
                        'transaction_id' => $transactionId,
                        'emissao_id'     => $existente->id,
                        'status'         => $existente->status,
                    ]);
                    return $existente;
                }

                if ($existente->status === 'cancelada') {
                    Log::warning('NfeService: tentativa re-emitir transaction com NFe cancelada via SEFAZ', [
                        'business_id'    => $businessId,
                        'transaction_id' => $transactionId,
                        'emissao_id'     => $existente->id,
                        'numero'         => $existente->numero,
                    ]);
                    throw new RuntimeException(
                        "NFe {$existente->numero} foi cancelada via SEFAZ — número permanece usado oficialmente. " .
                        'Pra emitir nova NFe execute action FSM `emitir_nova_apos_cancelamento` (cria nova transaction).'
                    );
                }

                // rejeitada / denegada / erro_envio: número não foi declarado.
                // Marca como `inutilizado` preservando registro pra rastreabilidade.
                // Inutilização formal SEFAZ via NfeInutilizacaoService (US-SELL-030).
                Log::info('NfeService: emissão rejeitada — marcando inutilizado pra preservar sequencial', [
                    'business_id'      => $businessId,
                    'transaction_id'   => $transactionId,
                    'emissao_id'       => $existente->id,
                    'status_anterior'  => $existente->status,
                    'motivo_anterior'  => $existente->motivo,
                    'numero'           => $existente->numero,
                ]);
                $existente->update(['status' => 'inutilizada']);
            }
        }

        // ── 2. Cert + business ──────────────────────────────────────────────
        $certData = $this->certificadoService->carregarParaSefaz($businessId);

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $emitOverride = $dadosNfe['emit'] ?? [];
        $serie  = $dadosNfe['serie'] ?? ((string) ($business->numero_serie_nfe ?? '1'));
        $numero = isset($dadosNfe['numero']) ? (int) $dadosNfe['numero'] : null;

        // ── 3. Reserva número + cria emissao em transaction CURTA ───────────
        // BUG FIX P0 2026-05-10: SEFAZ HTTP call NUNCA pode rodar dentro de
        // DB::transaction — request pode travar 30s+ e segura o lock no
        // business inteiro, bloqueando outras emissões concorrentes. Refactor
        // em 3 fases (reserva → SEFAZ fora → processa retorno).
        /** @var NfeEmissao $emissao */
        $emissao = DB::transaction(function () use (
            $businessId, $transactionId, $modelo, $serie, &$numero, $dadosNfe
        ) {
            if ($numero === null) {
                $numero = $this->proximoNumeroLocked($businessId, $modelo, $serie);
            }

            return NfeEmissao::create([
                'business_id'    => $businessId,
                'transaction_id' => $transactionId,
                'modelo'         => $modelo,
                'serie'          => $serie,
                'numero'         => $numero,
                'status'         => 'enviando',
                'valor_total'    => (float) ($dadosNfe['valor_total'] ?? 0),
            ]);
        });

        // ── 4. SEFAZ HTTP call FORA da transaction ──────────────────────────
        // Idempotência: se exception aqui, NfeEmissao fica `erro_envio` (não
        // `enviando` órfão) — permite cron/job retry posterior identificar.
        try {
            $xml       = $this->buildXml($business, $emissao, $dadosNfe, $emitOverride);
            $tools     = $this->criarTools($business, $certData, $emitOverride, (string) $modelo);
            $xmlSigned = $tools->signNFe($xml);

            $idLote  = str_pad((string) $emissao->id, 15, '0', STR_PAD_LEFT);
            $response = $tools->sefazEnviaLote([$xmlSigned], $idLote, 1);
        } catch (\Throwable $e) {
            $emissao->update([
                'status' => 'erro_envio',
                'motivo' => 'Erro de transmissão SEFAZ: ' . substr($e->getMessage(), 0, 500),
            ]);
            Log::error('NfeService: falha na transmissão SEFAZ (FORA tx)', [
                'business_id' => $businessId,
                'emissao_id'  => $emissao->id,
                'error'       => $e->getMessage(),
            ]);
            throw $e;
        }

        // ── 5. Processa retorno em transaction CURTA 2 ──────────────────────
        DB::transaction(function () use ($emissao, $response, $xmlSigned, $businessId, $serie, $numero) {
            $this->processarRetorno($emissao, $response, $xmlSigned, $businessId, $serie, $numero);
        });

        return $emissao->refresh();
    }

    /**
     * Próximo número com SELECT FOR UPDATE no registro da série.
     * Garante unicidade em ambiente concorrente.
     */
    public function proximoNumeroLocked(int $businessId, string $modelo, string $serie): int
    {
        // Lock na row de business pra serializar emissões concorrentes
        DB::table('business')->where('id', $businessId)->lockForUpdate()->value('id');

        $ultimo = NfeEmissao::withTrashed()
            ->where('business_id', $businessId)
            ->where('modelo', $modelo)
            ->where('serie', $serie)
            ->max('numero') ?? 0;

        try {
            $legado = (int) (DB::table('business')
                ->where('id', $businessId)
                ->value('ultimo_numero_nfe') ?? 0);
        } catch (\Throwable) {
            $legado = 0; // coluna ausente no ambiente sem UltimatePOS 3.7
        }

        return max((int) $ultimo, $legado) + 1;
    }

    /**
     * Consulta NFeStatusServico — verifica se a SEFAZ-{UF} está respondendo
     * com o cert A1 do business.
     *
     * Útil pra:
     *   - Smoke check pré-emissão (cert válido + SEFAZ online)
     *   - Botão "Testar conexão SEFAZ" na tela do certificado (US-NFE-041)
     *   - Diagnóstico em caso de emissão travada (SEFAZ x cert x rede)
     *
     * **cstat esperado em sucesso:**
     *   - `107` — "Servico em Operacao" (homologação ou produção)
     *
     * **cstat de erro comum:**
     *   - `108` — "Servico Paralisado Momentaneamente"
     *   - `109` — "Servico Paralisado sem Previsao"
     *   - `280` / `281` — Certificado vencido / inválido
     *   - `283` — "Assinatura difere do cadastro"
     *
     * Não emite NFe nenhuma — só ping de status. Idempotente, seguro pra
     * chamar sob demanda (botão UI) sem efeito colateral.
     *
     * @return array{
     *   ok: bool,                  status==107 = ok
     *   cstat: string,             código de retorno SEFAZ
     *   xMotivo: string,           mensagem human-readable
     *   tempoResposta: float,      duração em segundos (medida client-side)
     *   ambiente: int,             1=produção, 2=homologação
     *   uf: string,                UF do emitente
     *   versao: ?string            versão do app SEFAZ (quando disponível)
     * }
     *
     * @throws RuntimeException Quando cert ausente ou business não encontrado
     */
    public function consultarStatusSefaz(int $businessId): array
    {
        // D9.a OTel — wrap HTTP SEFAZ status call (timeout cURL é hot-path crítico).
        return OtelHelper::spanBiz('nfe.status_sefaz', function () use ($businessId): array {
            return $this->consultarStatusSefazInterno($businessId);
        }, [
            'module' => 'NfeBrasil',
        ]);
    }

    /**
     * @internal Corpo real de consultarStatusSefaz() — separado para wrap OTel.
     */
    private function consultarStatusSefazInterno(int $businessId): array
    {
        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $start = microtime(true);

        // Try/catch envolve TUDO — cert load, criarTools (TypeError, etc), sefazStatus
        // (timeout cURL, etc). Qualquer falha vira RuntimeException padronizada
        // pra controller poder retornar payload com UF/ambiente já populados.
        try {
            $certData    = $this->certificadoService->carregarParaSefaz($businessId);
            $tools       = $this->criarTools($business, $certData, [], '55');
            $responseXml = $tools->sefazStatus();
        } catch (\Throwable $e) {
            Log::error('NfeService: consultarStatusSefaz falhou', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
                'classe'      => $e::class,
            ]);
            throw new RuntimeException(
                'Falha ao consultar SEFAZ: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        $tempoResposta = round(microtime(true) - $start, 3);

        $std     = (new Standardize($responseXml))->toStd();
        $cstat   = (string) ($std->cStat ?? '999');
        $xMotivo = (string) ($std->xMotivo ?? 'Resposta SEFAZ sem xMotivo');
        $versao  = isset($std->verAplic) ? (string) $std->verAplic : null;

        $ok = $cstat === '107';

        Log::info('NfeService: status SEFAZ consultado', [
            'business_id'    => $businessId,
            'cstat'          => $cstat,
            'xMotivo'        => $xMotivo,
            'ok'             => $ok,
            'tempo_resposta' => $tempoResposta,
        ]);

        return [
            'ok'            => $ok,
            'cstat'         => $cstat,
            'xMotivo'       => $xMotivo,
            'tempoResposta' => $tempoResposta,
            'ambiente'      => (int) ($business->ambiente ?? 2),
            'uf'            => $this->resolverUF($business),
            'versao'        => $versao,
        ];
    }

    /**
     * US-SELL-034 · Cancela NFe autorizada via evento SEFAZ (tpEvento=110111).
     *
     * Aciona Tools::sefazCancela, parseia cstat, persiste evento em nfe_eventos
     * e atualiza NfeEmissao.status='cancelada'. Idempotente: re-chamada com
     * NfeEmissao já cancelada retorna o evento autorizado existente sem chamar
     * SEFAZ de novo.
     *
     * Regras SEFAZ:
     *   - Justificativa: 15-255 chars obrigatórios
     *   - cstat=135 → "Evento registrado e vinculado a NF-e" (cancelamento OK)
     *   - cstat=136 → "Evento registrado, NF-e não localizada" (aceitamos como OK
     *     — SEFAZ confirmou o registro do evento; raríssimo em prod mas defensivo)
     *   - Demais cstat → RuntimeException com xMotivo
     *
     * Prazo legal de cancelamento (NFe55=168h, NFC-e=24h) NÃO validado aqui —
     * caller (CancelarVendaCascade após decisão gerente) é responsável. Caso
     * fora do prazo, SEFAZ devolve cstat de erro e RuntimeException sobe.
     *
     * Multi-tenant Tier 0 (ADR 0093): cross-tenant guard via $businessId
     * cruzando com emissao.business_id (defesa em profundidade — global scope
     * já filtra mas guard explícito previne bypass via withoutGlobalScopes).
     *
     * @throws InvalidArgumentException  Justificativa fora de 15-255 chars
     * @throws UnauthorizedActionException Cross-tenant
     * @throws RuntimeException          NfeEmissao não encontrada, não cancelável,
     *                                   SEFAZ retornou cstat de erro, ou falha
     *                                   de infra/cert
     */
    public function cancelar(
        int $businessId,
        int $nfeEmissaoId,
        string $justificativa,
    ): NfeEvento {
        // D9.a OTel — wrap evento SEFAZ cancela (tpEvento 110111). Prazo legal
        // NFe55=168h NFC-e=24h — p99 monitorado.
        return OtelHelper::spanBiz('nfe.cancelar', function () use ($businessId, $nfeEmissaoId, $justificativa): NfeEvento {
            return $this->cancelarInterno($businessId, $nfeEmissaoId, $justificativa);
        }, [
            'module'         => 'NfeBrasil',
            'nfe_emissao_id' => $nfeEmissaoId,
        ]);
    }

    /**
     * @internal Corpo real de cancelar() — separado para wrap OTel.
     */
    private function cancelarInterno(
        int $businessId,
        int $nfeEmissaoId,
        string $justificativa,
    ): NfeEvento {
        // ── 1. Validar justificativa ────────────────────────────────────────
        $len = mb_strlen($justificativa);
        if ($len < 15 || $len > 255) {
            throw new InvalidArgumentException(
                "Justificativa deve ter 15-255 caracteres (regra SEFAZ). " .
                "Recebido: {$len} chars."
            );
        }

        // ── 2. Carregar NfeEmissao (sem global scope p/ cross-tenant guard
        //      explícito — defesa em profundidade ADR 0093) ────────────────
        $emissao = NfeEmissao::withoutGlobalScopes()->find($nfeEmissaoId);
        if (! $emissao) {
            throw new RuntimeException("NfeEmissao {$nfeEmissaoId} não encontrada.");
        }

        // ── 3. Cross-tenant guard ───────────────────────────────────────────
        if ((int) $emissao->business_id !== $businessId) {
            throw new UnauthorizedActionException(
                "Cross-tenant attempt: business {$businessId} tentou cancelar NfeEmissao " .
                "{$nfeEmissaoId} de business {$emissao->business_id}."
            );
        }

        // ── 4. Idempotência: já cancelada? ──────────────────────────────────
        if ($emissao->status === 'cancelada') {
            $eventoExistente = NfeEvento::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('emissao_id', $emissao->id)
                ->where('tipo', '110111')
                ->where('status', 'autorizado')
                ->latest('id')
                ->first();

            if ($eventoExistente) {
                Log::info('NfeService.cancelar: idempotência — NFe já cancelada, retornando evento existente', [
                    'business_id'    => $businessId,
                    'nfe_emissao_id' => $emissao->id,
                    'evento_id'      => $eventoExistente->id,
                ]);
                return $eventoExistente;
            }

            // Edge case: status=cancelada mas evento autorizado sumiu (drift).
            // Loga e segue pra reemitir o evento — defensivo.
            Log::warning('NfeService.cancelar: status=cancelada sem evento 110111 autorizado — reemitindo evento', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $emissao->id,
            ]);
        }

        // ── 5. Carregar cert + business ─────────────────────────────────────
        $certData = $this->certificadoService->carregarParaSefaz($businessId);

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        // ── 6. Construir Tools + chamar sefazCancela ─────────────────────────
        // Protocolo de autorização vive em metadata.nProt (JSON). Sem ele,
        // SEFAZ rejeita o evento (cstat 215 "Falha schema XML").
        $nProtocolo = (string) ($emissao->metadata['nProt'] ?? '');
        if ($nProtocolo === '') {
            throw new RuntimeException(
                "NfeEmissao {$emissao->id} sem protocolo de autorização (metadata.nProt vazio). " .
                "Cancelamento SEFAZ exige nProt da autorização original."
            );
        }

        $chave = (string) ($emissao->chave_44 ?? '');
        if (strlen($chave) !== 44) {
            throw new RuntimeException(
                "NfeEmissao {$emissao->id} sem chave_44 válida (len=" . strlen($chave) . "). " .
                "Não é possível cancelar uma NFe sem chave de acesso."
            );
        }

        $modelo = (string) $emissao->modelo;
        $xmlResp = null;

        try {
            // criarTools já trata $toolsFactory pra testes — reusa código existente
            $tools = $this->criarTools($business, $certData, [], $modelo);
            $xmlResp = $tools->sefazCancela($chave, $justificativa, $nProtocolo);
        } catch (\Throwable $e) {
            Log::error('NfeService.cancelar: falha SEFAZ', [
                'business_id'    => $businessId,
                'nfe_emissao_id' => $emissao->id,
                'chave'          => $chave,
                'error'          => $e->getMessage(),
            ]);
            // Persiste tentativa rejeitada pra rastreabilidade + re-lança
            $this->persistirEventoCancelamento(
                $emissao, $justificativa, 'rejeitado', null,
                ['erro' => $e->getMessage()],
            );
            throw new RuntimeException(
                "Falha SEFAZ ao cancelar NFe chave={$chave}: {$e->getMessage()}",
                previous: $e,
            );
        }

        // ── 7. Parse resposta SEFAZ ─────────────────────────────────────────
        $std = (new Standardize((string) $xmlResp))->toStd();

        // Resposta vem em retEnvEvento → retEvento.infEvento (singular ou array)
        $retEvento = $std->retEvento ?? null;
        if (is_array($retEvento)) {
            $retEvento = $retEvento[0] ?? null;
        }
        $infEvento = $retEvento->infEvento ?? null;

        $cstat   = (string) ($infEvento->cStat ?? $std->cStat ?? '999');
        $xMotivo = (string) ($infEvento->xMotivo ?? $std->xMotivo ?? '');

        $aceito = in_array($cstat, ['135', '136'], true);

        // ── 8. Persistir evento + atualizar status ──────────────────────────
        if (! $aceito) {
            // Persiste rejeição pra rastreabilidade + lança
            $this->persistirEventoCancelamento(
                $emissao, $justificativa, 'rejeitado', $cstat,
                ['xml_ret' => $xmlResp, 'x_motivo' => $xMotivo],
            );
            throw new RuntimeException(
                "SEFAZ rejeitou cancelamento NFe chave={$chave}: cstat={$cstat} {$xMotivo}"
            );
        }

        // cstat 135 ou 136 → cancelamento aceito
        return DB::transaction(function () use ($emissao, $justificativa, $cstat, $xMotivo, $xmlResp) {
            $emissao->update(['status' => 'cancelada']);

            $evento = $this->persistirEventoCancelamento(
                $emissao, $justificativa, 'autorizado', $cstat,
                ['xml_ret' => $xmlResp, 'x_motivo' => $xMotivo],
            );

            Log::info('NfeService.cancelar: NFe cancelada via SEFAZ', [
                'business_id'    => $emissao->business_id,
                'nfe_emissao_id' => $emissao->id,
                'chave'          => $emissao->chave_44,
                'cstat'          => $cstat,
                'evento_id'      => $evento->id,
            ]);

            return $evento;
        });
    }

    /**
     * US-FISCAL-014 — Retransmite NFe rejeitada/denegada/erro_envio via SEFAZ.
     *
     * Contexto: quando SEFAZ recusa emissão (cstat ≠ 100 — ex 539 duplicidade,
     * 778 CSTUF inválido, 691 NCM divergente) ou o request falha (erro_envio
     * por timeout/rede), a NfeEmissao fica com número "perdido" (não declarado
     * oficialmente). Retransmitir = pegar próximo número novo + re-enviar pra
     * SEFAZ com payload re-derivado da Transaction associada.
     *
     * Estratégia:
     *  1. Valida emissao status in [rejeitada, denegada, erro_envio]
     *  2. Cross-tenant guard
     *  3. Verifica transaction_id != null (emissões manuais sem TX exigem
     *     scope dedicado — fora deste PR)
     *  4. `forceDelete()` na NfeEmissao antiga (libera UNIQUE constraint
     *     business_id+transaction_id). Audit preservado via Spatie LogsActivity
     *     (Wave 18 D7 — log captura status/cstat/motivo/numero/chave_44).
     *  5. Chama `emitirParaTransaction($tx, $modelo)` que cria NfeEmissao NOVA
     *     com próximo número (proximoNumeroLocked withTrashed = sequencial
     *     fiscal monotônico, sem reuso).
     *
     * Side-effect crítico: o número fiscal da NfeEmissao antiga NÃO é
     * inutilizado SEFAZ aqui — fica como "buraco" no sequencial. Cliente deve
     * rodar inutilização SEFAZ (US-SELL-030 / PR #5 inutilizar faixa) pra
     * fechar buracos anualmente. Documentado em SPEC US-FISCAL-014 Non-Goals.
     *
     * Limitações conhecidas:
     *  - Só funciona pra NfeEmissao com transaction_id != null (NFC-e PDV
     *    + NF-e B2B Sells canon)
     *  - Não corrige causa raiz da rejeição — se cstat foi 691 (NCM divergente),
     *    é responsabilidade do usuário corrigir cadastro produto ANTES de
     *    retransmitir (mapa "Jana sugere" no NotaDrawer guia a receita).
     *
     * Multi-tenant Tier 0 (ADR 0093): businessId session via cross-tenant guard.
     *
     * @throws InvalidArgumentException   Status não-retransmissível
     * @throws UnauthorizedActionException Cross-tenant
     * @throws RuntimeException           Sem transaction_id / TX não encontrada
     */
    public function retransmitir(
        int $businessId,
        int $nfeEmissaoId,
    ): NfeEmissao {
        return OtelHelper::spanBiz('nfe.retransmitir', function () use ($businessId, $nfeEmissaoId): NfeEmissao {
            return $this->retransmitirInterno($businessId, $nfeEmissaoId);
        }, [
            'module'         => 'NfeBrasil',
            'nfe_emissao_id' => $nfeEmissaoId,
        ]);
    }

    private function retransmitirInterno(int $businessId, int $nfeEmissaoId): NfeEmissao
    {
        // ── 1. Carrega NfeEmissao sem global scope (cross-tenant guard explícito) ──
        $emissao = NfeEmissao::withoutGlobalScopes()->find($nfeEmissaoId);
        if (! $emissao) {
            throw new RuntimeException("NfeEmissao {$nfeEmissaoId} não encontrada.");
        }

        // ── 2. Cross-tenant guard ──────────────────────────────────────────
        if ((int) $emissao->business_id !== $businessId) {
            throw new UnauthorizedActionException(
                "Cross-tenant attempt: business {$businessId} tentou retransmitir NfeEmissao "
                . "{$nfeEmissaoId} de business {$emissao->business_id}."
            );
        }

        // ── 3. Valida status retransmissível ───────────────────────────────
        $statusValidos = ['rejeitada', 'denegada', 'erro_envio'];
        if (! in_array($emissao->status, $statusValidos, true)) {
            throw new InvalidArgumentException(
                "Retransmissão só aplica em NFe rejeitada/denegada/erro_envio. "
                . "Status atual: {$emissao->status}."
            );
        }

        // ── 4. Verifica transaction_id (manual emissions exigem scope dedicado) ──
        if ($emissao->transaction_id === null) {
            throw new RuntimeException(
                "NfeEmissao {$emissao->id} sem transaction_id (emissão manual). "
                . 'Retransmissão de emissões manuais sem TX exige scope dedicado.'
            );
        }

        $tx = Transaction::find($emissao->transaction_id);
        if (! $tx) {
            throw new RuntimeException(
                "Transaction {$emissao->transaction_id} (vinculada NfeEmissao {$emissao->id}) "
                . 'não encontrada. Não é possível re-derivar payload.'
            );
        }

        $modelo = (string) $emissao->modelo;
        $numeroOriginal = $emissao->numero;
        $serieOriginal = $emissao->serie;
        $statusOriginal = $emissao->status;
        $cstatOriginal = $emissao->cstat;

        Log::info('NfeService.retransmitir: iniciando retransmissão', [
            'business_id'    => $businessId,
            'nfe_emissao_id' => $emissao->id,
            'numero_antigo'  => $numeroOriginal,
            'serie_antigo'   => $serieOriginal,
            'status_antigo'  => $statusOriginal,
            'cstat_antigo'   => $cstatOriginal,
            'transaction_id' => $tx->id,
        ]);

        // ── 5. forceDelete antigo (libera UNIQUE biz+tx; audit via Spatie) ──
        // Spatie LogsActivity (Wave 18 D7) já registrou 'updated' nas mudanças
        // status anteriores. O 'deleted' event aqui não loga payload completo
        // (logOnly em getActivitylogOptions captura apenas chave_44/numero/etc).
        // Pra audit fiscal trail: activity_log + NfeEvento (eventos SEFAZ associados).
        $emissao->forceDelete();

        // ── 6. Re-emite via fluxo canon emitirParaTransaction ──────────────
        // Cria NfeEmissao NOVA com próximo número (proximoNumeroLocked
        // withTrashed = sequencial monotônico — não reusa numero antigo).
        $nova = $this->emitirParaTransaction($tx, $modelo);

        Log::info('NfeService.retransmitir: retransmissão concluída', [
            'business_id'    => $businessId,
            'transaction_id' => $tx->id,
            'numero_antigo'  => $numeroOriginal,
            'numero_novo'    => $nova->numero,
            'status_novo'    => $nova->status,
            'cstat_novo'     => $nova->cstat,
        ]);

        return $nova;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Privados
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Cria registro em nfe_eventos pro evento de cancelamento (tipo=110111).
     *
     * @internal Usado SOMENTE por cancelar() — não exponha externamente.
     */
    private function persistirEventoCancelamento(
        NfeEmissao $emissao,
        string $justificativa,
        string $status,
        ?string $cstat,
        array $payload,
    ): NfeEvento {
        return NfeEvento::create([
            'business_id'   => $emissao->business_id,
            'emissao_id'    => $emissao->id,
            'tipo'          => '110111',
            'justificativa' => $justificativa,
            'status'        => $status,
            'cstat_evento'  => $cstat,
            'payload_json'  => $payload,
        ]);
    }

    private function criarTools(object $business, array $certData, array $emitOverride, string $modelo = '55'): Tools
    {
        $configJson = $this->montarConfigSefaz($business, $emitOverride);

        if ($this->toolsFactory !== null) {
            // Testes passam certData bruto — factory decide se cria Certificate ou não
            return ($this->toolsFactory)($configJson, $certData);
        }

        $cert  = Certificate::readPfx($certData['pfx_binary'], $certData['senha']);
        $tools = new Tools($configJson, $cert);
        // Modelo dinâmico: '55' (NFe B2B) | '65' (NFC-e B2C/POS) | '67' (CT-e — futuro)
        // Default '55' preserva backwards compat com `emitirParaInvoice` (US-RB-044).
        // Tools::model() espera ?int (sped-nfe v5+) — cast obrigatório, string causa
        // TypeError em runtime real (não pego pelos tests Pest que mockam Tools).
        $tools->model((int) $modelo);
        return $tools;
    }

    private function montarConfigSefaz(object $business, array $emitOverride): string
    {
        $cnpj       = preg_replace('/\D/', '', (string) ($emitOverride['cnpj'] ?? $business->cnpj ?? ''));
        $razao      = $emitOverride['razao_social'] ?? $business->razao_social ?? $business->name ?? '';
        $uf         = $emitOverride['uf'] ?? $this->resolverUF($business);
        $tpAmb      = (int) ($emitOverride['ambiente'] ?? $business->ambiente ?? 2);

        return (string) json_encode([
            'atualizacao' => now()->format('Y-m-d\TH:i:s'),
            'tpAmb'       => $tpAmb,
            'razaosocial' => $razao,
            'cnpj'        => $cnpj,
            'siglaUF'     => $uf,
            'schemes'     => 'PL_009_V4',
            'versao'      => '4.00',
            'tokenCSC'    => '',
            'idCSC'       => '',
        ]);
    }

    private function resolverUF(object $business): string
    {
        $loc = DB::table('business_locations')
            ->where('business_id', $business->id)
            ->orderBy('id')
            ->first();

        $state = $loc?->state ?? '';
        // UF brasileira: 2 letras maiúsculas
        if (preg_match('/^[A-Z]{2}$/', $state)) {
            return $state;
        }
        return 'SP';
    }

    /**
     * Monta o XML da NF-e via NFePHP\NFe\Make.
     */
    private function buildXml(object $business, NfeEmissao $emissao, array $dadosNfe, array $emitOverride): string
    {
        $nfe = new Make();

        $std         = new \stdClass();
        $std->versao = '4.00';
        $std->Id     = null;
        $nfe->taginfNFe($std);

        // ── ide ─────────────────────────────────────────────────────────────
        $ufEmit = $emitOverride['uf'] ?? $this->resolverUF($business);
        $ufCode = \NFePHP\Common\UFList::getCodeByUF($ufEmit);

        // Carrega IBGE do emitente via cidades.officeimpresso_codigo (business.cidade_id FK)
        $cidadeEmit   = DB::table('cidades')->where('id', $business->cidade_id ?? 0)->first();
        $codMunEmit   = (string) ($emitOverride['cod_municipio'] ?? $cidadeEmit?->officeimpresso_codigo ?? '9999999');
        $xMunEmit     = strtoupper((string) ($emitOverride['municipio'] ?? $cidadeEmit?->descricao ?? ''));

        $stdIde            = new \stdClass();
        $stdIde->cUF       = $ufCode;
        $stdIde->cNF       = rand(10000000, 99999999);
        $stdIde->natOp     = $dadosNfe['nat_op'];
        $stdIde->mod       = (int) $emissao->modelo;
        $stdIde->serie     = $emissao->serie;
        $stdIde->nNF       = $emissao->numero;
        $stdIde->dhEmi     = now()->format('Y-m-d\TH:i:sP');
        $stdIde->dhSaiEnt  = now()->format('Y-m-d\TH:i:sP');
        $stdIde->tpNF      = 1;
        $stdIde->idDest    = $this->resolverIdDest($emitOverride, $dadosNfe['dest'] ?? [], $business);
        $stdIde->cMunFG    = $codMunEmit;
        // tpImp depende do modelo (rejeição "DANFE invalido" comum quando NFC-e usa tpImp=1):
        //   modelo 55 NFe: 1=Retrato, 2=Paisagem
        //   modelo 65 NFC-e: 4=DANFE NFC-e (bobina), 5=DANFE NFC-e em mensagem eletrônica
        $stdIde->tpImp     = (int) $emissao->modelo === 65 ? 4 : 1;
        $stdIde->tpEmis    = 1;
        $stdIde->cDV       = 0;
        $stdIde->tpAmb     = (int) ($emitOverride['ambiente'] ?? $business->ambiente ?? 2);
        $stdIde->finNFe    = 1;
        // indFinal: 1 = consumidor final, 0 = não consumidor final.
        //   - NFC-e (modelo 65): SEMPRE 1 (rejeição cstat 717)
        //   - NFe (modelo 55) + ind_ie_dest=9 (não contribuinte): 1 (rejeição cstat 696
        //     "Operacao com nao contribuinte deve indicar operacao com consumidor final")
        //   - NFe (modelo 55) + dest com CPF: 1 (consumidor final pessoa física)
        //   - NFe (modelo 55) + dest contribuinte (ind_ie_dest=1 ou 2): pode ser 0
        $isNfceManual = (int) $emissao->modelo === 65;
        $destNaoContribuinte = ($dadosNfe['dest']['ind_ie_dest'] ?? '') === '9';
        $destTemCpf = isset($dadosNfe['dest']['cpf']) && $dadosNfe['dest']['cpf'] !== '';
        $stdIde->indFinal  = ($isNfceManual || $destNaoContribuinte || $destTemCpf) ? 1 : 0;
        $stdIde->indPres   = 1;
        $stdIde->procEmi   = '0';
        $stdIde->verProc   = '1.0';
        $nfe->tagide($stdIde);

        // ── emit ─────────────────────────────────────────────────────────────
        $crt        = (int) ($emitOverride['crt'] ?? $business->regime ?? 1);
        $stdEmit    = new \stdClass();
        $stdEmit->CNPJ  = preg_replace('/\D/', '', (string) ($emitOverride['cnpj'] ?? $business->cnpj ?? ''));
        $stdEmit->xNome = $emitOverride['razao_social'] ?? $business->razao_social ?? $business->name ?? '';
        $stdEmit->xFant = $emitOverride['nome_fantasia'] ?? $business->name ?? '';
        $stdEmit->IE    = preg_replace('/\D/', '', (string) ($emitOverride['ie'] ?? $business->ie ?? ''));
        $stdEmit->CRT   = $crt;
        $nfe->tagemit($stdEmit);

        $stdEnderEmit          = new \stdClass();
        $stdEnderEmit->xLgr   = $emitOverride['logradouro'] ?? $business->rua ?? '';
        $stdEnderEmit->nro    = $emitOverride['numero_end'] ?? $business->numero ?? 'SN';
        $stdEnderEmit->xBairro = $emitOverride['bairro'] ?? $business->bairro ?? '';
        $stdEnderEmit->cMun   = $codMunEmit;
        $stdEnderEmit->xMun   = $xMunEmit;
        $stdEnderEmit->UF     = $emitOverride['uf'] ?? $this->resolverUF($business);
        $stdEnderEmit->CEP    = preg_replace('/\D/', '', (string) ($emitOverride['cep'] ?? $business->cep ?? ''));
        $stdEnderEmit->cPais  = '1058';
        $stdEnderEmit->xPais  = 'BRASIL';
        $nfe->tagenderEmit($stdEnderEmit);

        // ── dest ─────────────────────────────────────────────────────────────
        // BUG FIX 2026-05-08: 2 problemas antigos resolvidos:
        //   1. xNome era setado ANTES de CPF/CNPJ → XSD SEFAZ exige
        //      CNPJ|CPF|idEstrangeiro PRIMEIRO. Reordenado.
        //   2. Quando doc vazio (consumidor NFC-e anônimo) caía no else
        //      e setava CPF='' (string vazia inválida XSD).
        // Solução:
        //   - NFC-e (modelo 65) sem CPF/CNPJ → omite <dest> inteiro
        //     (consumidor não identificado é válido pra venda <R$10k).
        //   - NFe (modelo 55) sem doc → erro fica claro upstream (NFe exige).
        $dest    = $dadosNfe['dest'];
        $doc     = preg_replace('/\D/', '', (string) ($dest['cnpj'] ?? $dest['cpf'] ?? ''));
        $isNfce  = (int) $emissao->modelo === 65;
        $hasDoc  = strlen($doc) === 11 || strlen($doc) === 14;

        if ($isNfce && !$hasDoc) {
            Log::info('NfeService: NFC-e consumidor anônimo (sem CPF/CNPJ) — omitindo <dest>', [
                'business_id'    => $emissao->business_id,
                'transaction_id' => $emissao->transaction_id,
                'emissao_id'     => $emissao->id,
            ]);
        } else {
            $stdDest = new \stdClass();
            // XSD SEFAZ ORDEM: CNPJ|CPF|idEstrangeiro ANTES de xNome.
            if (strlen($doc) === 14) {
                $stdDest->CNPJ = $doc;
            } elseif (strlen($doc) === 11) {
                $stdDest->CPF = $doc;
            } elseif (!empty($dest['id_estrangeiro'])) {
                $stdDest->idEstrangeiro = (string) $dest['id_estrangeiro'];
            }
            // xNome depois do documento (canon XSD).
            $stdDest->xNome = $dest['nome'] ?? 'CONSUMIDOR FINAL';
            $stdDest->indIEDest = $dest['ind_ie_dest'] ?? '9';
            if (! empty($dest['ie'])) {
                $stdDest->IE = preg_replace('/\D/', '', (string) $dest['ie']);
            }
            if (! empty($dest['email'])) {
                $stdDest->email = $dest['email'];
            }
            $nfe->tagdest($stdDest);

            // cod_municipio destinatário: tenta lookup cidades por UF+nome, fallback emitente
            $codMunDest = (string) ($dest['cod_municipio'] ?? null);
            if ($codMunDest === '' || $codMunDest === '9999999' || $codMunDest === null) {
                $xMunDestRaw = strtoupper(substr((string) ($dest['municipio'] ?? ''), 0, 40));
                $codMunDest  = (string) (DB::table('cidades')
                    ->where('uf', strtoupper($dest['uf'] ?? $ufEmit))
                    ->where('descricao', 'like', $xMunDestRaw . '%')
                    ->whereNull('deleted_at')
                    ->value('officeimpresso_codigo') ?? $codMunEmit);
            }

            $stdEnderDest          = new \stdClass();
            $stdEnderDest->xLgr   = $dest['logradouro'] ?? '';
            $stdEnderDest->nro    = $dest['numero'] ?? 'SN';
            $stdEnderDest->xBairro = $dest['bairro'] ?? '';
            $stdEnderDest->cMun   = $codMunDest;
            $stdEnderDest->xMun   = strtoupper((string) ($dest['municipio'] ?? ''));
            $stdEnderDest->UF     = $dest['uf'] ?? 'SP';
            $stdEnderDest->CEP    = preg_replace('/\D/', '', (string) ($dest['cep'] ?? ''));
            $stdEnderDest->cPais  = '1058';
            $stdEnderDest->xPais  = 'BRASIL';
            $nfe->tagenderDest($stdEnderDest);
        }

        // ── dets (itens) ─────────────────────────────────────────────────────
        foreach ($dadosNfe['dets'] as $idx => $det) {
            $item = $idx + 1;
            $this->adicionarItem($nfe, $item, $det, $crt);
        }

        // ── total ────────────────────────────────────────────────────────────
        $total = $dadosNfe['total'];

        $stdICMSTot              = new \stdClass();
        $stdICMSTot->vBC         = $this->fmt($total['v_bc_icms'] ?? 0);
        $stdICMSTot->vICMS       = $this->fmt($total['v_icms'] ?? 0);
        $stdICMSTot->vICMSDeson  = 0.00;
        $stdICMSTot->vFCPUFDest  = 0.00;
        $stdICMSTot->vICMSUFDest = 0.00;
        $stdICMSTot->vICMSUFRemet = 0.00;
        $stdICMSTot->vFCP        = 0.00;
        $stdICMSTot->vBCST       = 0.00;
        $stdICMSTot->vST         = 0.00;
        $stdICMSTot->vFCPST      = 0.00;
        $stdICMSTot->vFCPSTRet   = 0.00;
        $stdICMSTot->vProd       = $this->fmt($total['v_prod'] ?? 0);
        $stdICMSTot->vFrete      = $this->fmt($total['v_frete'] ?? 0);
        $stdICMSTot->vSeg        = 0.00;
        $stdICMSTot->vDesc       = $this->fmt($total['v_desc'] ?? 0);
        $stdICMSTot->vII         = 0.00;
        $stdICMSTot->vIPI        = 0.00;
        $stdICMSTot->vIPIDevol   = 0.00;
        $stdICMSTot->vPIS        = $this->fmt($total['v_pis'] ?? 0);
        $stdICMSTot->vCOFINS     = $this->fmt($total['v_cofins'] ?? 0);
        $stdICMSTot->vOutro      = 0.00;
        $stdICMSTot->vNF         = $this->fmt($total['v_nf'] ?? 0);
        $nfe->tagICMSTot($stdICMSTot);

        // ── transp ───────────────────────────────────────────────────────────
        $stdTransp       = new \stdClass();
        $stdTransp->modFrete = 9; // 9 = sem frete
        $nfe->tagtransp($stdTransp);

        // ── pag ──────────────────────────────────────────────────────────────
        $stdPag = new \stdClass();
        $nfe->tagpag($stdPag);

        foreach ($dadosNfe['pag'] as $pagamento) {
            $stdDetPag        = new \stdClass();
            $stdDetPag->tPag  = (string) ($pagamento['tpag'] ?? '01');
            $stdDetPag->vPag  = $this->fmt($pagamento['vpag'] ?? 0);
            $nfe->tagdetPag($stdDetPag);
        }

        // ── infAdic ──────────────────────────────────────────────────────────
        if (! empty($dadosNfe['inf_cpl'])) {
            $stdInfo         = new \stdClass();
            $stdInfo->infCpl = (string) $dadosNfe['inf_cpl'];
            $nfe->taginfAdic($stdInfo);
        }

        // ── infRespTec ───────────────────────────────────────────────────────
        // SEFAZ exige (cstat 972) tag <infRespTec> com dados do desenvolvedor do sistema.
        // Convenção oimpresso: WR2 Sistemas (Wagner) é o responsável técnico.
        // Configurável via config('nfebrasil.resp_tec.*') ou env NFEBRASIL_RESPTEC_*.
        $respTecCnpj = (string) config('nfebrasil.resp_tec.cnpj', env('NFEBRASIL_RESPTEC_CNPJ', ''));
        if ($respTecCnpj !== '') {
            $stdRespTec           = new \stdClass();
            $stdRespTec->CNPJ     = preg_replace('/\D/', '', $respTecCnpj);
            $stdRespTec->xContato = (string) config('nfebrasil.resp_tec.contato', env('NFEBRASIL_RESPTEC_CONTATO', 'WR2 Sistemas'));
            $stdRespTec->email    = (string) config('nfebrasil.resp_tec.email', env('NFEBRASIL_RESPTEC_EMAIL', ''));
            $stdRespTec->fone     = preg_replace('/\D/', '', (string) config('nfebrasil.resp_tec.fone', env('NFEBRASIL_RESPTEC_FONE', '')));
            $nfe->taginfRespTec($stdRespTec);
        }

        $nfe->montaNFe();
        $errors = $nfe->getErrors();
        if (! empty($errors)) {
            throw new RuntimeException(
                'Erro ao montar NF-e: ' . implode('; ', array_column($errors, 'msg'))
            );
        }

        return $nfe->getXML();
    }

    /**
     * Adiciona item (det + imposto + ICMS + PIS + COFINS) ao Make.
     */
    private function adicionarItem(Make $nfe, int $item, array $det, int $crt): void
    {
        $stdProd         = new \stdClass();
        $stdProd->item   = $item;
        $stdProd->cProd  = (string) ($det['cprod'] ?? $item);
        $stdProd->cEAN   = 'SEM GTIN';
        $stdProd->xProd  = (string) ($det['xprod'] ?? '');
        $stdProd->NCM    = preg_replace('/\D/', '', (string) ($det['ncm'] ?? '00000000'));
        $stdProd->CFOP   = (string) ($det['cfop'] ?? '5102');
        $stdProd->uCom   = (string) ($det['ucm'] ?? 'UN');
        $stdProd->qCom   = $this->fmt((float) ($det['qcom'] ?? 1), 4);
        $stdProd->vUnCom = $this->fmt((float) ($det['vuncom'] ?? 0));
        $stdProd->vProd  = $this->fmt((float) ($det['vprod'] ?? 0));
        $stdProd->cEANTrib = 'SEM GTIN';
        $stdProd->uTrib  = (string) ($det['utrib'] ?? 'UN');
        $stdProd->qTrib  = $this->fmt((float) ($det['qtrib'] ?? 1), 4);
        $stdProd->vUnTrib = $this->fmt((float) ($det['vuntrib'] ?? 0));
        $stdProd->indTot = (int) ($det['ind_tot'] ?? 1);
        if (($det['vdesc'] ?? 0) > 0) {
            $stdProd->vDesc = $this->fmt((float) $det['vdesc']);
        }
        if (($det['vfrete'] ?? 0) > 0) {
            $stdProd->vFrete = $this->fmt((float) $det['vfrete']);
        }
        $nfe->tagprod($stdProd);

        // imposto container
        $stdImp        = new \stdClass();
        $stdImp->item  = $item;
        $nfe->tagimposto($stdImp);

        // ICMS
        $icms = $det['icms'] ?? [];
        $cstCsosn = (string) ($icms['cst_csosn'] ?? '102');
        $orig     = (int) ($icms['orig'] ?? 0);

        if ($crt === 3) {
            // Regime Normal — CST
            $stdICMS         = new \stdClass();
            $stdICMS->item   = $item;
            $stdICMS->orig   = $orig;
            $stdICMS->CST    = $cstCsosn;
            $stdICMS->modBC  = (int) ($icms['modbc'] ?? 3);
            $stdICMS->vBC    = $this->fmt((float) ($icms['vbc'] ?? 0));
            $stdICMS->pICMS  = $this->fmt((float) ($icms['picms'] ?? 0));
            $stdICMS->vICMS  = $this->fmt((float) ($icms['vicms'] ?? 0));
            $nfe->tagICMS($stdICMS);
        } else {
            // Simples Nacional — CSOSN
            $stdICMSSN        = new \stdClass();
            $stdICMSSN->item  = $item;
            $stdICMSSN->orig  = $orig;
            $stdICMSSN->CSOSN = $cstCsosn;
            if (in_array($cstCsosn, ['500', '400', '900'], true)) {
                $stdICMSSN->modBC  = (int) ($icms['modbc'] ?? 3);
                $stdICMSSN->vBC    = $this->fmt((float) ($icms['vbc'] ?? 0));
                $stdICMSSN->pICMS  = $this->fmt((float) ($icms['picms'] ?? 0));
                $stdICMSSN->vICMS  = $this->fmt((float) ($icms['vicms'] ?? 0));
            }
            $nfe->tagICMSSN($stdICMSSN);
        }

        // PIS
        $pis         = $det['pis'] ?? [];
        $stdPIS      = new \stdClass();
        $stdPIS->item = $item;
        $stdPIS->CST  = (string) ($pis['cst'] ?? '07');
        $stdPIS->vBC  = $this->fmt((float) ($pis['vbc'] ?? 0));
        $stdPIS->pPIS = $this->fmt((float) ($pis['ppis'] ?? 0));
        $stdPIS->vPIS = $this->fmt((float) ($pis['vpis'] ?? 0));
        $nfe->tagPIS($stdPIS);

        // COFINS
        $cofins         = $det['cofins'] ?? [];
        $stdCOFINS      = new \stdClass();
        $stdCOFINS->item = $item;
        $stdCOFINS->CST  = (string) ($cofins['cst'] ?? '07');
        $stdCOFINS->vBC  = $this->fmt((float) ($cofins['vbc'] ?? 0));
        $stdCOFINS->pCOFINS = $this->fmt((float) ($cofins['pcofins'] ?? 0));
        $stdCOFINS->vCOFINS = $this->fmt((float) ($cofins['vcofins'] ?? 0));
        $nfe->tagCOFINS($stdCOFINS);
    }

    /**
     * Processa retorno SEFAZ: atualiza NfeEmissao + armazena XML autorizado.
     */
    private function processarRetorno(
        NfeEmissao $emissao,
        string $responseXml,
        string $xmlSigned,
        int $businessId,
        string $serie,
        int $numero
    ): void {
        $std = (new Standardize($responseXml))->toStd();

        // cStat nível lote
        $loteStatus = (string) ($std->cStat ?? '999');

        // Para indSinc=1, o status individual fica em protNFe.infProt
        $infProt  = $std->protNFe->infProt ?? null;
        $cstat    = (string) ($infProt?->cStat ?? $loteStatus);
        $xMotivo  = (string) ($infProt?->xMotivo ?? $std->xMotivo ?? '');
        $chNFe    = (string) ($infProt?->chNFe ?? '');
        $nProt    = (string) ($infProt?->nProt ?? '');
        $dhRecbto = (string) ($infProt?->dhRecbto ?? '');

        // cStat 100 = Autorizado NF-e; 150 = Autorizado fora do prazo
        if (in_array($cstat, ['100', '150'], true)) {
            $xmlPath = sprintf('nfe-brasil/%d/notas/%s-%s.xml', $businessId, $serie, $numero);
            Storage::put($xmlPath, $xmlSigned);

            $emissao->update([
                'status'     => 'autorizada',
                'cstat'      => $cstat,
                'motivo'     => $xMotivo,
                'chave_44'   => $chNFe,
                'xml_path'   => $xmlPath,
                'emitido_em' => $dhRecbto ? \Carbon\Carbon::parse($dhRecbto) : now(),
                'metadata'   => ['nProt' => $nProt, 'cstat_lote' => $loteStatus],
            ]);

            // US-ARQ-021 (ADR 0123) — double-write XML pra Modules/Arquivos backbone.
            // Mantém Storage::put + xml_path coluna legacy (fallback). Cria row em
            // arquivos table polimórfica pra futuro NfeService::xmlArquivo() consumir.
            // Try/catch graceful — falha aqui NÃO bloqueia fluxo emit fiscal.
            $this->writeArquivoXml($emissao, $xmlPath, $xmlSigned);

            // Atualiza contador fiscal no business — CRÍTICO pra evitar duplicidade.
            // BUG FIX P0 2026-05-10: catch silencioso (Log::warning) deixava contador
            // stale → próxima emissão recebia mesmo número → SEFAZ rejeita por chave
            // duplicada (ou pior: aceita 2 NFes com mesmo nNF). report() + throw
            // garantem que o job retentar visivelmente.
            try {
                DB::table('business')
                    ->where('id', $businessId)
                    ->update(['ultimo_numero_nfe' => $numero]);
            } catch (\Throwable $e) {
                report($e);
                Log::error('NfeService: FALHA ao atualizar ultimo_numero_nfe — abortando pra evitar duplicidade', [
                    'business_id' => $businessId,
                    'emissao_id'  => $emissao->id,
                    'numero'      => $numero,
                    'error'       => $e->getMessage(),
                ]);
                throw new RuntimeException(
                    "Numero NFe não atualizado (business_id={$businessId}, numero={$numero}) — emissão ABORTADA pra evitar duplicidade",
                    0,
                    $e
                );
            }

            // D9.a Log estruturado padrão `nfe.retorno_sefaz` — biz/chave/cstat
            // canônico pra dashboards Grafana/Loki + correlação com OTel span.
            Log::info('nfe.retorno_sefaz', [
                'biz'        => $businessId,
                'chave'      => $chNFe,
                'cstat'      => $cstat,
                'motivo'     => $xMotivo,
                'status'     => 'autorizada',
                'emissao_id' => $emissao->id,
                'nProt'      => $nProt,
                'modelo'     => (string) $emissao->modelo,
            ]);

            // US-NFE-044 — gera DANFE PDF (defensivo: falha não derruba a emissão)
            app(DanfeService::class)->salvar($emissao->refresh());

            return;
        }

        // cStat 301, 302 = Denegado (emitente irregular)
        if (in_array($cstat, ['301', '302', '110', '205'], true)) {
            $emissao->update([
                'status' => 'denegada',
                'cstat'  => $cstat,
                'motivo' => $xMotivo,
            ]);
            Log::warning('nfe.retorno_sefaz', [
                'biz'        => $businessId,
                'chave'      => $chNFe,
                'cstat'      => $cstat,
                'motivo'     => $xMotivo,
                'status'     => 'denegada',
                'emissao_id' => $emissao->id,
            ]);
            return;
        }

        // Demais = rejeitada
        $emissao->update([
            'status' => 'rejeitada',
            'cstat'  => $cstat,
            'motivo' => $xMotivo,
        ]);
        Log::warning('nfe.retorno_sefaz', [
            'biz'        => $businessId,
            'chave'      => $chNFe,
            'cstat'      => $cstat,
            'motivo'     => $xMotivo,
            'status'     => 'rejeitada',
            'emissao_id' => $emissao->id,
        ]);
    }

    private function resolverIdDest(array $emitOverride, array $dest, object $business): int
    {
        $ufEmit = $emitOverride['uf'] ?? $this->resolverUF($business);
        $ufDest = $dest['uf'] ?? $ufEmit;
        return $ufEmit !== $ufDest ? 2 : 1;
    }

    private function fmt(float $value, int $dec = 2): string
    {
        return number_format($value, $dec, '.', '');
    }

    /**
     * US-ARQ-021 — double-write XML autorizado pra Modules/Arquivos backbone.
     *
     * Cria row em `arquivos` polimórfica apontando pro mesmo storage_path
     * que xml_path coluna legacy. md5 calculado do conteúdo real (Sprint 6+
     * job recalcula size_bytes ler do disk).
     *
     * Idempotente: skip se Arquivo já existir pro emissao+sub_destination.
     * Try/catch graceful — falha aqui NUNCA bloqueia fluxo fiscal emit.
     *
     * Mantém xml_path coluna legacy (fallback durante transição até US-ARQ-022
     * Officeimpresso UI usar $emissao->xml_arquivo).
     *
     * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 4
     */
    private function writeArquivoXml(\Modules\NfeBrasil\Models\NfeEmissao $emissao, string $xmlPath, string $xmlSigned): void
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('arquivos')) {
                return; // Modules/Arquivos não migrado ainda — skip silencioso
            }

            $arquivableType = 'Modules\\NfeBrasil\\Models\\NfeEmissao';

            $exists = DB::table('arquivos')
                ->where('arquivable_type', $arquivableType)
                ->where('arquivable_id', $emissao->id)
                ->where('sub_destination', 'nfe-xml')
                ->where('storage_path', $xmlPath)
                ->exists();

            if ($exists) {
                return; // já existe — idempotente
            }

            DB::table('arquivos')->insert([
                'business_id'         => $emissao->business_id,
                'arquivable_type'     => $arquivableType,
                'arquivable_id'       => $emissao->id,
                'disk'                => 'local',
                'storage_path'        => $xmlPath,
                'original_name'       => basename($xmlPath),
                'mime_type'           => 'application/xml',
                'size_bytes'          => strlen($xmlSigned),
                'md5'                 => md5($xmlSigned),
                'bucket'              => 'active',
                'sub_destination'     => 'nfe-xml',
                'sensitive_flags'     => null,
                'classified_by'       => 'nfe-service-double-write',
                'classified_at'       => now(),
                'uploaded_by_user_id' => null,
                'visibility'          => 'private',
                'encrypted'           => false,
                'retention_days'      => null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            Log::info('NfeService.double_write.xml.ok', [
                'emissao_id'  => $emissao->id,
                'business_id' => $emissao->business_id,
                'xml_path'    => $xmlPath,
            ]);
        } catch (\Throwable $e) {
            Log::warning('NfeService.double_write.xml.fail', [
                'emissao_id' => $emissao->id ?? null,
                'error'      => substr($e->getMessage(), 0, 200),
            ]);
            // NUNCA propaga — fluxo fiscal NÃO pode quebrar por falha em arquivos table
        }
    }
}
