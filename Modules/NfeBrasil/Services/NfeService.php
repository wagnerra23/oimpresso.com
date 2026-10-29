<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services;

use App\Domain\Fsm\Exceptions\UnauthorizedActionException;
use App\Support\BR\Cnpj;
use App\Transaction;
use App\Util\OtelHelper;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Modules\NfeBrasil\Exceptions\AliquotaInternaNaoCadastradaException;
use Modules\NfeBrasil\Exceptions\NcmPadraoAusenteException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeIcmsUf;
use Modules\NfeBrasil\Models\NfeLocalEmissor;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;
use Modules\NfeBrasil\Services\Tributacao\TributoCalculado;
use Modules\RecurringBilling\Models\Invoice;
use NFePHP\Common\Certificate;
use NFePHP\NFe\Common\Standardize;
use NFePHP\NFe\Factories\Contingency;
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

        // Mantém as letras do CNPJ alfanumérico (IN RFB 2.229/2024); CPF e CNPJ numérico seguem iguais.
        $documentoDest = Cnpj::documentoFiscal((string) ($contact->tax_number ?? ''));
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
            // R-NFE-022: recusa com a saída (tela + campo), nunca emite sem NCM.
            throw new NcmPadraoAusenteException($businessId);
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
                // Thread 25 · R-NFE-030: com benefício, o item leva o cBenef, a base reduzida e o
                // percentual de redução. Sem benefício, as chaves ficam ausentes/0 como antes.
                'cbenef'  => $tributo->c_benef,
                'icms'    => [
                    'cst_csosn' => $tributo->csosn ?? $tributo->cst ?? '102',
                    'orig'      => 0,
                    'vbc'       => $this->baseIcmsDoItem($tributo, (float) $invoice->valor),
                    // R-NFE-040: a régua guarda fração (0,18); o XML leva percentual (18,00).
                    'picms'     => round($tributo->aliquota_icms * 100, 4),
                    'vicms'     => $tributo->valor_icms,
                    'pred_bc'   => round($tributo->reducao_base * 100, 4),
                ],
                'pis'     => [
                    'cst'   => '07', // 07 = isenta — default seguro Simples Nacional
                    'vbc'   => 0,
                    'ppis'  => round($tributo->aliquota_pis * 100, 4),
                    'vpis'  => $tributo->valor_pis,
                ],
                'cofins'  => [
                    'cst'      => '07',
                    'vbc'      => 0,
                    'pcofins'  => round($tributo->aliquota_cofins * 100, 4),
                    'vcofins'  => $tributo->valor_cofins,
                ],
                // IBS/CBS (Reforma · US-FISCAL-021 PR-D). Serializado só quando o business
                // está em modo full/hybrid (schema PL_010); Simples/legado → alíquota 0 +
                // cst nulo → grupo omitido. vbc = base do item (mesmo valor que o motor usou).
                'ibscbs'  => [
                    'cst'          => $tributo->cst_ibs,
                    'cst_cbs'      => $tributo->cst_cbs,
                    'c_class_trib' => $tributo->c_class_trib,
                    'vbc'          => (float) $invoice->valor,
                    'aliquota_ibs' => $tributo->aliquota_ibs,
                    'aliquota_cbs' => $tributo->aliquota_cbs,
                    'valor_ibs'    => $tributo->valor_ibs,
                    'valor_cbs'    => $tributo->valor_cbs,
                ],
            ]],
            'total' => [
                'v_prod'    => (float) $invoice->valor,
                'v_bc_icms' => $this->baseIcmsDoItem($tributo, (float) $invoice->valor), // R-NFE-040
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
     * **Fase 2B (thread 17):** um `det` por `transaction_sell_lines`, com o NCM
     *   do produto e o motor chamado por item (`montarItensNfe`). Venda sem
     *   linha legível volta ao item genérico "Venda PDV #X" de antes, com log.
     *
     * **Limitações que seguem** (cada uma é uma thread):
     *   - CPF do consumidor não capturado da Transaction — fica anônimo (CNPJ
     *     '99999999999999' fictício; SEFAZ aceita NFC-e sem doc destinatário
     *     se valor < R$ [redacted Tier 0]).
     *   - Pagamento `tpag='99'` (outros) — fase 2B detecta forma real via
     *     `transaction_payments`.
     *
     * **Venda separada (US-NFE-069 · UC-FLX-27):** com `$separada = true`, a nota sai só com a parte
     *   MERCADORIA de {@see SeparacaoNotasDaVenda}: as linhas de mercadoria, o total dessa parte como
     *   vNF e o frete inteiro (regra de rateio decidida por [W] em 2026-10-21). A parte serviço vai
     *   em NFS-e. A recusa de item de serviço continua valendo sobre as linhas que sobram.
     *
     * @throws RuntimeException Quando `final_total <= 0`, ncm_default ausente, ou business não encontrado.
     */
    /**
     * @param array<string,mixed>|null $destinatarioExterno R-NFE-046 · destinatário vindo de fora do cadastro (ex.
     *        pedido de marketplace), com as chaves de `contacts`; vence o cliente da venda. Só NF-e (55).
     * @param string|null $cfop R-NFE-046 · CFOP de 4 dígitos que vence o da regra em todos os itens (ex. o do pedido
     *        de marketplace, configurável lá). Null = o CFOP da regra, como antes.
     */
    public function emitirParaTransaction(
        Transaction $tx,
        string $modelo = '65',
        bool $separada = false,
        ?array $destinatarioExterno = null,
        ?string $cfop = null,
    ): NfeEmissao
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
            // R-NFE-022: recusa com a saída (tela + campo), nunca emite sem NCM.
            throw new NcmPadraoAusenteException($businessId);
        }

        // Thread 26 · R-NFE-031/032: a UF, o emitente e a série vêm do local da venda quando ele
        // é um estabelecimento emissor próprio; senão, do business, como antes.
        $emitente = $this->resolverEmitente($business, $tx->location_id ? (int) $tx->location_id : null, (string) $modelo);
        $ufOrigem = $emitente['uf'];
        // US-SELL-078 · o `final_total` da venda já inclui o ICMS-ST cobrado no fechamento
        // ([W] 2026-10-26). A nota parte da base SEM o ST e o soma de volta no vNF (R-NFE-043):
        // vNF = (final_total − icms_st_valor) + vST = final_total. Sem a coluna, ST 0 como antes.
        $valorTotal = $this->baseDaNota($tx);
        $frete = (float) ($tx->shipping_charges ?? 0);

        // Fase 2B (thread 17 · R-NFE-023..025c): um `det` por linha da venda, com o
        // NCM do produto, e o motor chamado POR ITEM. Venda sem linha legível cai no
        // item genérico de antes (fase 2A) — mesmo XML de hoje, com log.
        $motor  = $this->motor ?? app(MotorTributarioService::class);
        $linhas = $this->linhasDaVenda($businessId, (int) $tx->id);
        if ($linhas === []) {
            Log::warning('nfe_itens_ausentes', ['business_id' => $businessId, 'transaction_id' => $tx->id]);
            $linhas = [[
                'sell_line_id' => null,
                'product_id'   => null,
                'cprod'        => 'PDV-' . $tx->id,
                'xprod'        => "Venda PDV #{$tx->id}",
                'ncm'          => $ncmDefault,
                'unidade'      => 'UN',
                'quantidade'   => 1.0,
                'valor_unitario' => $valorTotal,
            ]];
        }

        // US-NFE-069: venda separada emite só a parte mercadoria (total, frete e linhas da parte).
        $separacao = null;
        if ($separada) {
            $separacao = app(SeparacaoNotasDaVenda::class)->separar($businessId, (int) $tx->id);
            if ($separacao['pendentes'] !== []) {
                // D-NATUREZA: pendente bloqueia o faturamento separado; o contador decide antes.
                throw new RuntimeException('Produto(s) ' . implode(', ', $separacao['pendentes']) . ' com natureza fiscal a decidir.');
            }
            $parte = $separacao[SeparacaoNotasDaVenda::MERCADORIA];
            if ($parte['total'] <= 0) {
                throw new RuntimeException("Venda {$tx->id} não tem parte mercadoria — emita só a NFS-e.");
            }
            $ids = array_column($parte['linhas'], 'sell_line_id');
            $linhas = array_values(array_filter($linhas, fn (array $l) => in_array($l['sell_line_id'], $ids, true)));
            $valorTotal = (float) $parte['total'];
            $frete = (float) $parte['frete'];
        }

        // Thread 29 · R-NFE-034: item de serviço (decidido pelo contador) não sai em NF-e/NFC-e.
        $this->recusarItensDeServico($businessId, $linhas);

        // R-NFE-036: NF-e (55) de venda com cliente identificado sai para ele — documento, IE,
        // endereço e UF. Sem cliente com CPF/CNPJ, e sempre na NFC-e, segue o consumidor anônimo
        // da própria UF, como antes. A UF e o contribuinte do destino chegam ao motor (CFOP 6xxx,
        // regra por UF, DIFAL); a interna do destino vem da tabela por UF quando cadastrada.
        $destReal = (string) $modelo === '55'
            ? $this->destinatarioDaEmissao($tx, $ufOrigem, $destinatarioExterno)
            : null;
        $ufDestino = $destReal['uf'] ?? $ufOrigem;
        $contribuinte = $destReal === null ? null : $destReal['ind_ie_dest'] === '1';
        $interna = $destReal === null || $ufDestino === $ufOrigem
            ? null
            : $this->internaDoDestino($businessId, $ufOrigem, $ufDestino);
        // R-NFE-037: venda a não contribuinte de outra UF leva o grupo ICMSUFDest, que exige a
        // alíquota interna do destino (a SEFAZ rejeita a nota sem o grupo). Sem a interna
        // cadastrada a nota é recusada com a saída — nunca sai com DIFAL zero por falta de dado.
        $this->garantirInternaDoDifal($destReal !== null, $ufOrigem, $ufDestino, $contribuinte, $interna);

        $itens = $this->montarItensNfe(
            linhas:         $linhas,
            valorNota:      $valorTotal,
            frete:          $frete,
            ncmDefault:     $ncmDefault,
            motor:          $motor,
            businessId:     $businessId,
            ufOrigem:       $ufOrigem,
            ufDestino:      $ufDestino,
            internaDestino: $interna,
            destinatarioContribuinte: $contribuinte,
            crtEmitente:    $emitente['crt'] ?? null,
        );
        if ($cfop !== null) {
            $itens['dets'] = self::comCfop($itens['dets'], $cfop);
        }

        $dadosNfe = [
            'transaction_id' => $tx->id,
            'modelo'         => $modelo,
            'nat_op'         => $destReal === null ? 'VENDA AO CONSUMIDOR' : 'VENDA DE MERCADORIA',
            'dest' => $destReal ?? [
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
            'dets'     => $itens['dets'],
            'total'    => $itens['total'],
            'metadata' => $separacao === null ? $itens['metadata'] : array_merge((array) $itens['metadata'], [
                'separacao' => [
                    'regra'         => $separacao['regra'],
                    'final_total'   => $separacao['final_total'],
                    'servico_total' => $separacao[SeparacaoNotasDaVenda::SERVICO]['total'],
                ],
            ]),
            // tpag='01' = dinheiro (default conservador). Fase 2B detecta via transaction_payments.
            // R-NFE-043: com ICMS-ST o vNF passa do total da venda; o pagamento acompanha o vNF
            // (sem ST, v_nf == $valorTotal, como antes).
            'pag'         => [['tpag' => '01', 'vpag' => $itens['total']['v_nf']]],
            'valor_total' => $itens['total']['v_nf'],
            'inf_cpl'     => $separacao === null
                ? "Venda PDV #{$tx->id}."
                : "Venda PDV #{$tx->id}. Parte mercadoria; os servicos da venda saem em NFS-e.",
        ];
        if ($emitente['location_id'] !== null) {
            $dadosNfe['emit']        = $emitente['emit'];
            $dadosNfe['serie']       = $emitente['serie'];
            $dadosNfe['location_id'] = $emitente['location_id'];
        }

        return $this->emitir($businessId, $dadosNfe);
    }

    /**
     * US-SELL-078 · base da nota: o `final_total` da venda sem o ICMS-ST que ela já cobrou. A nota
     * soma o vST de volta (R-NFE-043), então vNF = final_total. Venda sem ST: o próprio total.
     */
    public function baseDaNota(Transaction $tx): float
    {
        return (float) $tx->final_total - (float) ($tx->icms_st_valor ?? 0);
    }

    /**
     * R-NFE-046 · destinatário da NF-e: o externo (ex. pedido de marketplace) quando vier, senão o
     * cliente da venda (R-NFE-036). Externo sem CPF/CNPJ recusa — não cai no cliente da venda nem no
     * consumidor anônimo, porque quem o mandou disse quem é o destinatário.
     *
     * @param array<string,mixed>|null $externo
     * @return array<string,mixed>|null
     */
    public function destinatarioDaEmissao(Transaction $tx, string $ufOrigem, ?array $externo): ?array
    {
        if ($externo === null) {
            return $this->destinatarioDaVenda($tx, $ufOrigem);
        }

        return $this->destinatarioDoContato((object) $externo, $ufOrigem)
            ?? throw new RuntimeException('Destinatário informado sem CPF/CNPJ válido — a NF-e não sai sem documento do destinatário.');
    }

    /**
     * R-NFE-046 · o CFOP informado vence o da regra em todos os itens. 4 dígitos começando por 1–7
     * (entrada/saída × estado/fora/exterior); outro formato recusa. O valor é de quem chama.
     *
     * @param list<array<string,mixed>> $dets
     * @return list<array<string,mixed>>
     */
    public static function comCfop(array $dets, string $cfop): array
    {
        if (! preg_match('/^[1-7]\d{3}$/', $cfop)) {
            throw new RuntimeException("CFOP inválido: {$cfop} (4 dígitos, de 1xxx a 7xxx).");
        }

        return array_map(fn (array $d) => ['cfop' => $cfop] + $d, $dets);
    }

    /**
     * R-NFE-036 · destinatário da NF-e (55) de venda: o cliente da venda, lido no mesmo tenant.
     * Null = sem cliente identificado (sem contato, ou contato sem CPF/CNPJ) → consumidor anônimo.
     */
    protected function destinatarioDaVenda(Transaction $tx, string $ufOrigem): ?array
    {
        if (empty($tx->contact_id)) {
            return null;
        }
        $contato = DB::table('contacts')
            ->where('business_id', (int) $tx->business_id)
            ->where('id', (int) $tx->contact_id)
            ->first();

        return $contato === null ? null : $this->destinatarioDoContato($contato, $ufOrigem);
    }

    /**
     * R-NFE-036 · monta o `dest` a partir do cadastro do cliente. Sem DB (testável).
     *
     * IE: `inscricao_estadual` (canon NF-e) ou o espelho `ie` do drawer. `ind_ie_dest` vem do
     * `indicador_ie` cadastrado (1 contribuinte · 2 isento · 9 não contribuinte); sem ele, IE
     * preenchida = 1, vazia = 9. UF inválida cai na UF do emitente (operação interna).
     *
     * @return array<string,mixed>|null
     */
    public function destinatarioDoContato(object $c, string $ufOrigem): ?array
    {
        $doc = Cnpj::documentoFiscal((string) (($c->tax_number ?? '') ?: ($c->cpf_cnpj ?? '')));
        if (! in_array(strlen($doc), [11, 14], true)) {
            return null;
        }

        $ie  = preg_replace('/\D/', '', (string) (($c->inscricao_estadual ?? '') ?: ($c->ie ?? '')));
        $ind = (string) ($c->indicador_ie ?? '');
        if (! in_array($ind, ['1', '2', '9'], true)) {
            $ind = $ie !== '' ? '1' : '9';
        }
        $uf = strtoupper(trim((string) ($c->state ?? '')));
        if (! preg_match('/^[A-Z]{2}$/', $uf)) {
            $uf = $ufOrigem;
        }
        $codMun = preg_replace('/\D/', '', (string) ($c->city_code ?? ''));
        $nome = (string) (($c->supplier_business_name ?? '') ?: ($c->name ?? ''));

        return array_filter([
            strlen($doc) === 14 ? 'cnpj' : 'cpf' => $doc,
            'nome'          => substr($nome !== '' ? $nome : 'CLIENTE', 0, 60),
            'ind_ie_dest'   => $ind,
            'ie'            => $ind === '1' && $ie !== '' ? $ie : null,
            'logradouro'    => substr((string) (($c->address_line_1 ?? '') ?: 'NAO INFORMADO'), 0, 60),
            'numero'        => substr((string) (($c->numero ?? '') ?: 'SN'), 0, 60),
            'bairro'        => substr((string) (($c->neighborhood ?? '') ?: (($c->bairro ?? '') ?: 'CENTRO')), 0, 60),
            'municipio'     => substr((string) (($c->city ?? '') ?: 'NAO INFORMADO'), 0, 60),
            // 9999999 = o buildXml procura o código pela UF + nome da cidade, como já fazia.
            'cod_municipio' => strlen($codMun) === 7 ? $codMun : '9999999',
            'uf'            => $uf,
            'cep'           => preg_replace('/\D/', '', (string) ($c->zip_code ?? '')),
            'email'         => ($c->email ?? '') ?: null,
        ], fn ($v) => $v !== null);
    }

    /** R-NFE-043 · grava no grupo ICMS do item os campos do ST (modBCST, pMVAST, vBCST, pICMSST, vICMSST). */
    private function preencherSt(\stdClass $std, ?array $st): void
    {
        if ($st === null) {
            return;
        }
        $std->modBCST = (int) $st['modbcst'];
        $std->pMVAST  = $this->fmt((float) $st['pmvast'], 4);
        $std->vBCST   = $this->fmt((float) $st['vbcst']);
        $std->pICMSST = $this->fmt((float) $st['picmsst'], 4);
        $std->vICMSST = $this->fmt((float) $st['vicmsst']);
    }

    /** CST (regime normal) e CSOSN (Simples) que levam ICMS-ST retido pelo emitente. */
    private const CST_COM_ST = ['10', '30', '70', '90', '201', '202', '203', '900'];

    /**
     * R-NFE-043 · campos do ICMS-ST do item, a partir do motor (R-NFE-015: base pela MVA, ST =
     * base × interna − ICMS próprio). Null sem ST. Regra com MVA e ST calculado, mas CST/CSOSN que
     * não leva ST, é recusada — sairia nota com imposto que o código da situação diz não existir.
     *
     * @return array<string,float|int>|null
     */
    private function stDoItem(TributoCalculado $tributo, ?float $internaDestino, ?string $codigoIcms = null): ?array
    {
        if ($tributo->valor_st <= 0) {
            return null;
        }
        // P-155: confere o código que o item leva (o do regime do local), não o primeiro da regra.
        $cst = $codigoIcms ?? (string) ($tributo->csosn ?? $tributo->cst ?? '');
        if (! in_array($cst, self::CST_COM_ST, true)) {
            throw new RuntimeException("A regra fiscal calcula ICMS-ST (MVA) mas o CST/CSOSN {$cst} não leva ST — ajuste a regra em Tributação (ex.: CST 10 ou CSOSN 202).");
        }

        return [
            'modbcst' => 4, // 4 = margem de valor agregado (%)
            'pmvast'  => round((float) ($tributo->mva ?? 0) * 100, 4),
            'vbcst'   => $tributo->base_st,
            'picmsst' => round((float) $internaDestino * 100, 4),
            'vicmsst' => $tributo->valor_st,
        ];
    }

    /** R-NFE-037 · interestadual a não contribuinte sem interna do destino: recusa com a saída. */
    private function garantirInternaDoDifal(bool $temDestinatario, string $ufOrigem, string $ufDestino, ?bool $contribuinte, ?float $interna): void
    {
        if ($temDestinatario && $ufDestino !== $ufOrigem && $contribuinte === false && $interna === null) {
            throw AliquotaInternaNaoCadastradaException::para($ufDestino);
        }
    }

    /** Interna do destino (decimal) da tabela por UF; null se o contador ainda não cadastrou. */
    public function internaDoDestino(int $businessId, string $ufOrigem, string $ufDestino): ?float
    {
        try {
            return NfeIcmsUf::aliquotaInterna($businessId, $ufOrigem, $ufDestino);
        } catch (AliquotaInternaNaoCadastradaException) {
            return null;
        }
    }

    /**
     * Thread 29 · R-NFE-034 — produto que o contador decidiu como SERVIÇO não sai em NF-e/NFC-e
     * (é NFS-e). Recusa a nota inteira em vez de tirar o item, porque o total da nota é o
     * `final_total` da venda (REGRA MESTRE). Pendente e mercadoria emitem como antes.
     *
     * @param list<array<string,mixed>> $linhas saída de linhasDaVenda()
     */
    public function recusarItensDeServico(int $businessId, array $linhas): void
    {
        $servicos = array_keys(array_filter(
            app(\Modules\NfeBrasil\Services\Tributacao\NaturezaFiscalService::class)->naturezas($businessId, array_values(array_filter(array_map(
                fn (array $l) => $l['product_id'] !== null ? (int) $l['product_id'] : null, $linhas,
            )))),
            fn (string $n) => $n === 'servico',
        ));
        if ($servicos !== []) {
            throw new RuntimeException(
                'Produto(s) ' . implode(', ', $servicos) . ' decidido(s) pelo contador como serviço (ISS): '
                . 'emita NFS-e para eles. A NF-e/NFC-e não sai com item de serviço.'
            );
        }
    }

    /**
     * Thread 17 · linhas da venda prontas pro `det` (R-NFE-023).
     *
     * Tier 0: o JOIN com `products` filtra `business_id` — linha de produto de
     * outro tenant não entra na nota, mesmo que a FK aponte pra ela.
     * Quantidade e preço vêm da linha (`unit_price_inc_tax` = o que o cliente
     * pagou por unidade, já com desconto de linha e imposto UPos).
     *
     * `origem` = origem da mercadoria do produto (tabela A, 0–8 · DEFAULT 0 na coluna).
     *
     * @return list<array{sell_line_id:int|null,product_id:int|null,cprod:string,xprod:string,ncm:string,origem:int,unidade:string,quantidade:float,valor_unitario:float}>
     */
    public function linhasDaVenda(int $businessId, int $transactionId): array
    {
        $rows = DB::table('transaction_sell_lines as tsl')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('tsl.transaction_id', $transactionId)
            ->where('p.business_id', $businessId)
            ->orderBy('tsl.id')
            ->get([
                'tsl.id as sell_line_id', 'tsl.product_id', 'tsl.quantity', 'tsl.unit_price_inc_tax',
                'p.name', 'p.sku', 'p.ncm', 'p.origem_mercadoria', 'v.sub_sku', 'u.short_name',
            ]);

        $linhas = [];
        foreach ($rows as $r) {
            $linhas[] = [
                'sell_line_id'   => (int) $r->sell_line_id,
                'product_id'     => (int) $r->product_id,
                'cprod'          => (string) ($r->sub_sku ?: $r->sku ?: $r->product_id),
                'xprod'          => (string) $r->name,
                'ncm'            => (string) ($r->ncm ?? ''),
                'origem'         => (int) ($r->origem_mercadoria ?? 0),
                'unidade'        => (string) ($r->short_name ?: 'UN'),
                'quantidade'     => (float) $r->quantity,
                'valor_unitario' => (float) $r->unit_price_inc_tax,
            ];
        }

        return $linhas;
    }

    /**
     * Thread 17 · monta `dets` + `total` + `metadata` a partir das linhas (R-NFE-023..025b).
     *
     * Sem DB: tudo que precisa entra por parâmetro (testável com motor falso).
     *
     * REGRA MESTRE VALOR — o total da nota NÃO muda: `vNF` = `valorNota` (o
     * `final_total` da venda, como na fase 2A). O que muda é a decomposição:
     *   vProd_i = round(qCom × vUnCom, 2)
     *   vFrete = `frete` da venda, inteiro ([W] 2026-10-21 · R-NFE-025)
     *   Δ = valorNota − Σ vProd_i − vFrete
     *   Δ < 0 → desconto, rateado (vDesc por item)
     *   Δ > 0 → acréscimo em vOutro, rateado
     * Antes o frete só entrava até o acréscimo e o desconto o consumia: frete 20,00 com desconto
     * 7,00 saía como vFrete 13,00 e vDesc 0. Se o desconto passaria do vProd (o `final_total`
     * não contém o frete declarado), vale a regra antiga, com log.
     *   base_i = vProd_i − vDesc_i + vFrete_i + vOutro_i   (Σ base_i = vNF exato)
     * Rateio em centavos pelo método do maior resto: cada item recebe no máximo
     * 1 centavo acima da parte proporcional, e o desconto nunca passa o vProd do item.
     * O motor é chamado por item sobre base_i; os totais de imposto são a soma
     * dos itens já arredondados (arredondamento por item, como a SEFAZ valida).
     *
     * Linha de valor 0 (componente de combo) não vira `det` — NF-e não aceita item zerado.
     *
     * @param list<array<string,mixed>> $linhas saída de linhasDaVenda()
     * @return array{dets:list<array<string,mixed>>,total:array<string,float>,metadata:array<string,mixed>|null}
     */
    public function montarItensNfe(
        array $linhas,
        float $valorNota,
        float $frete,
        string $ncmDefault,
        MotorTributarioService $motor,
        int $businessId,
        string $ufOrigem,
        string $ufDestino,
        ?float $internaDestino = null,
        ?bool $destinatarioContribuinte = null,
        ?int $crtEmitente = null,
    ): array {
        // R-NFE-024 controle: sem NCM padrão válido não há saída — mesma recusa do
        // R-NFE-022 em emitirParaTransaction (defesa em profundidade, sem DB).
        if (strlen($ncmDefault) !== 8) {
            throw new NcmPadraoAusenteException($businessId);
        }

        // 1. vProd por item, em centavos
        $itens = [];
        foreach ($linhas as $l) {
            $qtd  = (float) $l['quantidade'];
            $vun  = round((float) $l['valor_unitario'], 10);
            $cent = (int) round($qtd * $vun * 100);
            if ($qtd <= 0 || $cent <= 0) {
                continue;
            }
            $itens[] = $l + ['vuncom' => $vun, 'vprod_cent' => $cent];
        }
        if ($itens === []) {
            throw new RuntimeException('Venda sem item de valor positivo — não emite nota.');
        }

        $totalCent = (int) round($valorNota * 100);
        $somaCent  = array_sum(array_column($itens, 'vprod_cent'));
        $pesos     = array_column($itens, 'vprod_cent');
        $freteCent = (int) round(max($frete, 0) * 100);
        $delta     = $totalCent - $somaCent - $freteCent;
        if (-$delta > $somaCent) {
            // O total não comporta o frete declarado (o desconto passaria do vProd): regra antiga.
            Log::warning('nfe_frete_fora_do_total', ['business_id' => $businessId, 'v_nf' => $totalCent / 100, 'frete' => $freteCent / 100]);
            $delta     = $totalCent - $somaCent;
            $freteCent = $delta > 0 ? min($freteCent, $delta) : 0;
            $delta    -= $freteCent;
        }

        $descontos = $delta < 0 ? $this->ratearCentavos(-$delta, $pesos) : array_fill(0, count($itens), 0);
        $fretes    = $this->ratearCentavos($freteCent, $pesos);
        $outros    = $this->ratearCentavos(max($delta, 0), $pesos);

        // 2. motor por item
        $dets = [];
        $ncmPadraoUsado = [];
        $tot = ['v_bc_icms' => 0.0, 'v_icms' => 0.0, 'v_pis' => 0.0, 'v_cofins' => 0.0, 'v_ibs' => 0.0, 'v_cbs' => 0.0,
            'v_icms_uf_dest' => 0.0, 'v_fcp_uf_dest' => 0.0, 'v_bc_st' => 0.0, 'v_st' => 0.0];
        $temDifal = $internaDestino !== null && $destinatarioContribuinte === false && $ufOrigem !== $ufDestino;
        foreach ($itens as $i => $it) {
            $ncm = preg_replace('/\D/', '', (string) $it['ncm']);
            if (strlen($ncm) !== 8 || $ncm === '00000000') {
                $ncm = $ncmDefault;
                $ncmPadraoUsado[] = ['sell_line_id' => $it['sell_line_id'], 'product_id' => $it['product_id']];
            }

            $base = ($it['vprod_cent'] - $descontos[$i] + $fretes[$i] + $outros[$i]) / 100.0;
            $contexto = new ProdutoFiscalContext(
                ncm:         $ncm,
                valor:       $base,
                // R-NFE-025 (N1): `products` não tem `fiscal_rule_override_id` no schema
                // hoje — a coluna é migration de outra lane 🔴. Até lá, null.
                fiscal_rule_override_id: null,
                description: (string) $it['xprod'],
            );
            // R-NFE-036: sem dado de destino (NFC-e, consumidor anônimo) a chamada é a de sempre.
            // P-155: local com regime próprio usa o cálculo com o CRT dele; sem CRT, como antes.
            $tributo = match (true) {
                $crtEmitente !== null => $motor->calcularComRegimeDoLocal(
                    $contexto,
                    businessId: $businessId,
                    ufOrigem: $ufOrigem,
                    ufDestino: $ufDestino,
                    crtEmitente: $crtEmitente,
                    aliquotaInternaDestino: $internaDestino,
                    destinatarioContribuinte: $destinatarioContribuinte,
                ),
                $internaDestino === null && $destinatarioContribuinte === null
                    => $motor->calcular($contexto, businessId: $businessId, ufOrigem: $ufOrigem, ufDestino: $ufDestino),
                default => $motor->calcularComDestino(
                    $contexto,
                    businessId: $businessId,
                    ufOrigem: $ufOrigem,
                    ufDestino: $ufDestino,
                    aliquotaInternaDestino: $internaDestino,
                    destinatarioContribuinte: $destinatarioContribuinte,
                ),
            };

            $vprod = $it['vprod_cent'] / 100.0;
            // P-155: no local do regime normal (CRT 3) vale o CST da regra; sem CRT, como antes.
            $codigoIcms = $crtEmitente === 3
                ? ($tributo->cst ?? $tributo->csosn ?? '102')
                : ($tributo->csosn ?? $tributo->cst ?? '102');
            $dets[] = [
                'cprod'   => substr((string) $it['cprod'], 0, 60),
                'xprod'   => substr((string) $it['xprod'], 0, 120),
                'ncm'     => $ncm,
                'cfop'    => $tributo->cfop,
                'ucm'     => substr((string) $it['unidade'], 0, 6),
                'qcom'    => (float) $it['quantidade'],
                'vuncom'  => $it['vuncom'],
                'vprod'   => $vprod,
                'utrib'   => substr((string) $it['unidade'], 0, 6),
                'qtrib'   => (float) $it['quantidade'],
                'vuntrib' => $it['vuncom'],
                'dec_vun' => 10,
                'vdesc'   => $descontos[$i] / 100.0,
                'vfrete'  => $fretes[$i] / 100.0,
                'voutro'  => $outros[$i] / 100.0,
                'ind_tot' => 1,
                // Thread 25 · R-NFE-030: com benefício, o item leva o cBenef, a base reduzida e o
                // percentual de redução. Sem benefício, as chaves ficam ausentes/0 como antes.
                'cbenef'  => $tributo->c_benef,
                'icms'    => [
                    'cst_csosn' => $codigoIcms,
                    // Origem do produto (tabela A). Linha montada sem a chave (teste, chamada
                    // antiga) segue com 0 — a origem que toda nota usava.
                    'orig'      => (int) ($it['origem'] ?? 0),
                    'vbc'       => $this->baseIcmsDoItem($tributo, $base, $crtEmitente),
                    // R-NFE-040: a régua guarda fração (0,18); o XML leva percentual (18,00).
                    'picms'     => round($tributo->aliquota_icms * 100, 4),
                    'vicms'     => $tributo->valor_icms,
                    'pred_bc'   => round($tributo->reducao_base * 100, 4),
                    // R-NFE-043: ICMS-ST do motor (R-NFE-015) no grupo do CST/CSOSN com ST.
                    'st'        => $this->stDoItem($tributo, $internaDestino, $codigoIcms),
                ],
                // R-NFE-025b: `nfe_fiscal_rules` não guarda CST de PIS/COFINS hoje — o
                // motor não devolve. '07' segue como fallback, logado abaixo.
                'pis'     => [
                    'cst'   => '07',
                    'vbc'   => 0,
                    'ppis'  => round($tributo->aliquota_pis * 100, 4),
                    'vpis'  => $tributo->valor_pis,
                ],
                'cofins'  => [
                    'cst'      => '07',
                    'vbc'      => 0,
                    'pcofins'  => round($tributo->aliquota_cofins * 100, 4),
                    'vcofins'  => $tributo->valor_cofins,
                ],
                'ibscbs'  => [
                    'cst'          => $tributo->cst_ibs,
                    'cst_cbs'      => $tributo->cst_cbs,
                    'c_class_trib' => $tributo->c_class_trib,
                    'vbc'          => $base,
                    'aliquota_ibs' => $tributo->aliquota_ibs,
                    'aliquota_cbs' => $tributo->aliquota_cbs,
                    'valor_ibs'    => $tributo->valor_ibs,
                    'valor_cbs'    => $tributo->valor_cbs,
                ],
                'nivel_tributacao' => $tributo->nivel_usado,
                // R-NFE-037: DIFAL da venda a não contribuinte de outra UF (grupo ICMSUFDest).
                // Os valores são os do motor (R-NFE-017/035); o vNF não muda.
                'difal' => $temDifal ? [
                    'vbc'       => $base,
                    'p_interna' => $internaDestino,
                    'p_inter'   => $tributo->aliquota_icms,
                    'p_fcp'     => (float) ($tributo->fcp ?? 0),
                    'v_fcp'     => $tributo->valor_fcp,
                    'v_difal'   => $tributo->valor_difal,
                ] : null,
            ];
            if ($temDifal) {
                $tot['v_icms_uf_dest'] += $tributo->valor_difal;
                $tot['v_fcp_uf_dest']  += $tributo->valor_fcp;
            }
            if ($tributo->valor_st > 0) {
                $tot['v_bc_st'] += $tributo->base_st;
                $tot['v_st']    += $tributo->valor_st;
            }

            $tot['v_bc_icms'] += $this->baseIcmsDoItem($tributo, $base, $crtEmitente); // R-NFE-040 (thread 25: benefício)
            $tot['v_icms']   += $tributo->valor_icms;
            $tot['v_pis']    += $tributo->valor_pis;
            $tot['v_cofins'] += $tributo->valor_cofins;
            $tot['v_ibs']    += $tributo->valor_ibs;
            $tot['v_cbs']    += $tributo->valor_cbs;
        }

        Log::info('pis_cofins_cst_fallback', [
            'business_id' => $businessId,
            'itens'       => count($dets),
            'cst'         => '07',
        ]);

        return [
            'dets'  => $dets,
            'total' => [
                'v_prod'    => $somaCent / 100.0,
                'v_bc_icms' => round($tot['v_bc_icms'], 2),
                'v_icms'    => round($tot['v_icms'], 2),
                'v_pis'     => round($tot['v_pis'], 2),
                'v_cofins'  => round($tot['v_cofins'], 2),
                'v_ibs'     => round($tot['v_ibs'], 2),
                'v_cbs'     => round($tot['v_cbs'], 2),
                'v_icms_uf_dest' => round($tot['v_icms_uf_dest'], 2),
                'v_fcp_uf_dest'  => round($tot['v_fcp_uf_dest'], 2),
                'v_bc_st'   => round($tot['v_bc_st'], 2),
                'v_st'      => round($tot['v_st'], 2),
                // R-NFE-043 · leiaute W16: vNF = vProd − vDesc + vST + vFrete + vSeg + vOutro + vII + vIPI.
                // O ST entra por cima do total da venda (o comprador paga o imposto retido).
                'v_nf'      => ($totalCent + (int) round($tot['v_st'] * 100)) / 100.0,
                'v_desc'    => array_sum($descontos) / 100.0,
                'v_frete'   => array_sum($fretes) / 100.0,
                'v_outro'   => array_sum($outros) / 100.0,
            ],
            'metadata' => $ncmPadraoUsado === [] ? null : ['itens_ncm_padrao' => $ncmPadraoUsado],
        ];
    }

    /**
     * R-NFE-040 · base do ICMS que vai ao item. Com benefício (base reduzida, thread 25) é a do
     * motor. Sem ela, item do regime normal com CST tributado e alíquota leva a base do item —
     * o vICMS é base × alíquota e a SEFAZ confere a conta. Simples e CST sem ICMS seguem 0.
     * P-155: com o CRT do local, o regime é o dele, não a falta de CSOSN na regra.
     */
    private function baseIcmsDoItem(TributoCalculado $tributo, float $base, ?int $crtEmitente = null): float
    {
        if ($tributo->base_icms !== null) {
            return $tributo->base_icms;
        }
        $regimeNormal = $crtEmitente === null ? $tributo->csosn === null : $crtEmitente === 3;
        $regimeNormalTributado = $regimeNormal
            && in_array((string) $tributo->cst, ['00', '10', '51', '90'], true)
            && $tributo->aliquota_icms > 0;

        return $regimeNormalTributado ? round($base, 2) : 0.0;
    }

    /**
     * Rateia `$centavos` proporcional a `$pesos` (maior resto). Soma exata;
     * cada parte ≤ floor(proporcional) + 1.
     *
     * @param list<int> $pesos
     * @return list<int>
     */
    private function ratearCentavos(int $centavos, array $pesos): array
    {
        $n = count($pesos);
        if ($centavos <= 0 || $n === 0) {
            return array_fill(0, $n, 0);
        }
        $soma   = array_sum($pesos);
        $partes = [];
        $restos = [];
        foreach ($pesos as $i => $p) {
            $exato      = intdiv($centavos * $p, $soma);
            $partes[$i] = $exato;
            $restos[$i] = ($centavos * $p) % $soma;
        }
        $falta = $centavos - array_sum($partes);
        arsort($restos);
        foreach (array_keys($restos) as $i) {
            if ($falta <= 0) {
                break;
            }
            $partes[$i]++;
            $falta--;
        }
        ksort($partes);

        return array_values($partes);
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
                // BUG FIX 2026-06-02: liberar a UNIQUE (business_id, transaction_id)
                // setando transaction_id=null — MySQL trata NULL como distinto em
                // UNIQUE. Sem isso, o NfeEmissao::create() abaixo (mesma transaction)
                // viola `nfe_emissoes_biz_tx_unique` no retry pós-falha. Mesma técnica
                // canônica do retransmitirInterno() (CONFAZ Art. 14 — nunca hard-delete;
                // metadata.original_transaction_id preserva o vínculo pra audit).
                $existente->update([
                    'status' => 'inutilizada',
                    'transaction_id' => null,
                    'metadata' => array_merge((array) ($existente->metadata ?? []), [
                        'original_transaction_id'   => $transactionId,
                        'inutilizada_em'            => now()->toIso8601String(),
                        'status_antes_inutilizacao' => $existente->status,
                    ]),
                ]);
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

        // ── 2b. Modo de emissão (US-NFE-006 / ADR TECH-0002) ────────────────
        // Lido ANTES da transaction (é SELECT curto e não precisa do lock).
        // Fora de contingência isto devolve tpEmis=1 e o fluxo abaixo é idêntico
        // ao de antes deste PR — a contingência é opt-in por tenant e nasce false.
        $tpEmis = $this->resolverTpEmis($businessId, (string) $modelo);
        // US-NFE-071: SVC (6/7) também é contingência, mas TRANSMITE na hora — para a SEFAZ
        // Virtual. Só EPEC (4) e off-line (9) guardam a nota e param antes da SEFAZ.
        $emContingencia = in_array($tpEmis, [NfeEmissao::TP_EMIS_EPEC, NfeEmissao::TP_EMIS_OFFLINE_NFCE], true);

        // ── 3. Reserva número + cria emissao em transaction CURTA ───────────
        // BUG FIX P0 2026-05-10: SEFAZ HTTP call NUNCA pode rodar dentro de
        // DB::transaction — request pode travar 30s+ e segura o lock no
        // business inteiro, bloqueando outras emissões concorrentes. Refactor
        // em 3 fases (reserva → SEFAZ fora → processa retorno).
        /** @var NfeEmissao $emissao */
        $emissao = DB::transaction(function () use (
            $businessId, $transactionId, $modelo, $serie, &$numero, $dadosNfe, $tpEmis, $emContingencia
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
                // 'contingencia' NÃO é erro: é nota válida ainda não transmitida.
                'status'         => $emContingencia ? 'contingencia' : 'enviando',
                'tp_emis'        => $tpEmis,
                'valor_total'    => (float) ($dadosNfe['valor_total'] ?? 0),
                // Thread 17 · R-NFE-024: itens que saíram com o NCM padrão (saúde fiscal, 14).
                'metadata'       => $dadosNfe['metadata'] ?? null,
            ] + (isset($dadosNfe['location_id']) ? ['location_id' => (int) $dadosNfe['location_id']] : []));
        });

        // ── 4. SEFAZ HTTP call FORA da transaction ──────────────────────────
        // Idempotência: se exception aqui, NfeEmissao fica `erro_envio` (não
        // `enviando` órfão) — permite cron/job retry posterior identificar.
        try {
            $xml       = $this->buildXml($business, $emissao, $dadosNfe, $emitOverride);
            $tools     = $this->criarTools($business, $certData, $emitOverride, (string) $modelo);
            $xmlSigned = $tools->signNFe($xml);

            // US-NFE-071: com SVC/EPEC a lib precisa saber o modo — é ele que manda o lote
            // para a SEFAZ Virtual (SVC) ou permite o evento EPEC. Armado DEPOIS de assinar:
            // o XML já sai do buildXml com tpEmis/dhCont/xJust, e a nota normal não passa aqui.
            $tipoSped = ModoContingencia::tipoSpedNfe($tpEmis);
            if ($tipoSped !== '') {
                $tools->contingency = $this->contingenciaSped($businessId, $emissao, $tpEmis, $tipoSped);
            }

            // ── 4b. CONTINGÊNCIA: persiste e PARA aqui (US-NFE-006 / ADR TECH-0002) ──
            // A ADR é explícita: "XML em contingência é gravado ANTES de qualquer call
            // SEFAZ". A nota está emitida e é VÁLIDA — o DANFE sai com o indicador de
            // contingência e o cliente leva o documento. O que falta é só transmitir,
            // e quem faz isso é o RetentarContingenciaJob quando a SEFAZ voltar.
            //
            // Por que persistir é inegociável: o número fiscal JÁ foi consumido pelo
            // proximoNumeroLocked acima. Perder o XML aqui deixaria um buraco na
            // sequência sem documento — problema fiscal, não bug de software.
            if ($emContingencia) {
                $this->persistirXmlContingencia($emissao, $xmlSigned, $businessId, $serie, (int) $numero);

                // US-NFE-071: EPEC só vale com o evento 110140 registrado no Ambiente Nacional.
                // Vai DEPOIS de persistir: se o AN também falhar, a nota segue guardada.
                if ($tpEmis === NfeEmissao::TP_EMIS_EPEC) {
                    $this->registrarEpec($emissao->refresh(), $tools, $xmlSigned);
                }

                return $emissao->refresh();
            }

            $idLote  = str_pad((string) $emissao->id, 15, '0', STR_PAD_LEFT);

            // Retry-with-backoff em erro de TRANSPORTE transitório (connection reset,
            // recv failure, timeout, SSL) — SEFAZ (SVRS homolog/prod) às vezes fecha o
            // socket sem processar. Reenvio é idempotente: idLote/chave são
            // determinísticos → SEFAZ deduplica (cStat 204/539 duplicidade tratado em
            // processarRetorno). NÃO retenta rejeição por cStat — essa volta em
            // $response (não vira exception), seguindo direto pro processamento.
            $maxTentativas = 3;
            for ($tentativa = 1; ; $tentativa++) {
                try {
                    $response = $tools->sefazEnviaLote([$xmlSigned], $idLote, 1);
                    break;
                } catch (\Throwable $envioErro) {
                    $transitorio = (bool) preg_match(
                        '/connection reset|recv failure|broken pipe|timed?\s*out|timeout|could not resolve|ssl|curl|comunica/i',
                        $envioErro->getMessage()
                    );
                    if (! $transitorio || $tentativa >= $maxTentativas) {
                        throw $envioErro;
                    }
                    Log::warning('NfeService: erro transitório SEFAZ — retry de envio', [
                        'business_id' => $businessId,
                        'emissao_id'  => $emissao->id,
                        'tentativa'   => $tentativa,
                        'error'       => substr($envioErro->getMessage(), 0, 200),
                    ]);
                    usleep(1_500_000 * $tentativa); // backoff progressivo: 1.5s, 3s
                }
            }
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

        // Thread 26 · R-NFE-032: `business.ultimo_numero_nfe` é o contador da série padrão do
        // business. Série de filial (outra série) não herda esse número — senão a filial começaria
        // do número da matriz e, ao autorizar, empurraria o contador dela.
        $legado = 0;
        if ($serie === $this->seriePadrao($businessId)) {
            try {
                $legado = (int) (DB::table('business')
                    ->where('id', $businessId)
                    ->value('ultimo_numero_nfe') ?? 0);
            } catch (\Throwable) {
                $legado = 0; // coluna ausente no ambiente sem UltimatePOS 3.7
            }
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
        // SUPERADMIN: carrega cross-tenant de propósito pra validar business_id no passo 3
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
            // SUPERADMIN: idempotência pós-guard — business_id já validado, filtrado explícito abaixo
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
     * Estratégia (CONFAZ SINIEF 07/2005 Art. 14 preservation IRREVOGÁVEL —
     * Wave 27/28 saturation D6/D2 enforce ZERO hard-delete em NfeEmissao):
     *  1. Valida emissao status in [rejeitada, denegada, erro_envio]
     *  2. Cross-tenant guard
     *  3. Verifica transaction_id != null (emissões manuais sem TX exigem
     *     scope dedicado — fora deste PR)
     *  4. **UPDATE** na NfeEmissao antiga (NUNCA hard-delete — documento fiscal
     *     imutável): `status='inutilizada'` + `transaction_id=null` (libera UNIQUE
     *     biz+tx pra a nova emissão) + `metadata.original_transaction_id` (audit
     *     trail) + `metadata.retransmitido_em`. Spatie LogsActivity (Wave 18 D7)
     *     captura o `updated` event automaticamente.
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
        // SUPERADMIN: carrega cross-tenant de propósito pra validar business_id no passo 2
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
        // US-NFE-069: a NF-e da parte mercadoria re-emite também separada (senão a recusa de serviço barra).
        $separada = isset(((array) ($emissao->metadata ?? []))['separacao']);

        Log::info('NfeService.retransmitir: iniciando retransmissão', [
            'business_id'    => $businessId,
            'nfe_emissao_id' => $emissao->id,
            'numero_antigo'  => $numeroOriginal,
            'serie_antigo'   => $serieOriginal,
            'status_antigo'  => $statusOriginal,
            'cstat_antigo'   => $cstatOriginal,
            'transaction_id' => $tx->id,
        ]);

        // ── 5. UPDATE antigo (preserva audit CONFAZ Art. 14 — NUNCA hard-delete) ──
        // Wave 27/28 saturation D6/D2 IRREVOGÁVEL: remoção física de NfeEmissao
        // viola CONFAZ SINIEF 07/2005 Art. 14 (documento fiscal imutável). Em
        // vez disso: status='inutilizada' + transaction_id=null libera a UNIQUE
        // biz+tx (MySQL trata NULL como distinto em UNIQUE), preservando o row
        // na tabela com audit completo. Spatie LogsActivity captura o 'updated'
        // automaticamente; metadata.original_transaction_id mantém referência
        // pra recuperação caso preciso futuramente.
        $emissao->update([
            'status' => 'inutilizada',
            'transaction_id' => null,
            'metadata' => array_merge((array) ($emissao->metadata ?? []), [
                'original_transaction_id' => $tx->id,
                'retransmitido_em' => now()->toIso8601String(),
                'retransmitido_por_business' => $businessId,
                'status_antes_retransmissao' => $statusOriginal,
                'cstat_antes_retransmissao' => $cstatOriginal,
            ]),
        ]);

        // ── 6. Re-emite via fluxo canon emitirParaTransaction ──────────────
        // Cria NfeEmissao NOVA com próximo número (proximoNumeroLocked
        // withTrashed = sequencial monotônico — não reusa numero antigo).
        $nova = $this->emitirParaTransaction($tx, $modelo, $separada);

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

    /**
     * US-NFE-006 / ADR TECH-0002 — TRANSMITE uma nota emitida em contingência.
     *
     * ⚠️ NÃO CONFUNDIR COM `retransmitir()` LOGO ACIMA. São operações fiscalmente
     * OPOSTAS, e trocar uma pela outra corrompe documento fiscal:
     *
     *   retransmitir()          → a SEFAZ REJEITOU. O número queimou. Inutiliza a
     *                             emissão e cria uma NOVA, com número NOVO.
     *   transmitirContingencia() → a nota é VÁLIDA e já foi impressa e entregue ao
     *                             cliente. Transmite AQUELE MESMO documento, com o
     *                             MESMO número e o MESMO XML assinado. Nada de novo
     *                             número — isso quebraria a correspondência entre o
     *                             DANFE que está na mão do cliente e o XML na SEFAZ.
     *
     * Por isso reenvia o XML PERSISTIDO em vez de reconstruí-lo: reconstruir geraria
     * `cNF`/`dhEmi` diferentes e, portanto, outra chave de acesso.
     *
     * Backoff (ADR TECH-0002): cada falha incrementa `retry_count`; ao atingir
     * MAX_RETRIES_CONTINGENCIA o status vira `rejeitada` e o gestor é alertado —
     * porque nota em contingência que nunca transmite é pendência fiscal, não ruído.
     *
     * @throws UnauthorizedActionException Cross-tenant
     * @throws InvalidArgumentException    Status não é 'contingencia'
     * @throws RuntimeException            XML persistido ausente
     */
    public function transmitirContingencia(int $businessId, int $emissaoId): NfeEmissao
    {
        return OtelHelper::spanBiz('nfe.transmitir_contingencia', function () use ($businessId, $emissaoId): NfeEmissao {
            return $this->transmitirContingenciaInterno($businessId, $emissaoId);
        }, [
            'module' => 'NfeBrasil',
            'nfe_emissao_id' => $emissaoId,
        ]);
    }

    private function transmitirContingenciaInterno(int $businessId, int $emissaoId): NfeEmissao
    {
        // SUPERADMIN: carrega cross-tenant de propósito pra validar business_id abaixo.
        $emissao = NfeEmissao::withoutGlobalScopes()->find($emissaoId);
        if (! $emissao) {
            throw new RuntimeException("NfeEmissao {$emissaoId} não encontrada.");
        }

        if ((int) $emissao->business_id !== $businessId) {
            throw new UnauthorizedActionException(
                "Cross-tenant attempt: business {$businessId} tentou transmitir contingência da "
                . "NfeEmissao {$emissaoId} de business {$emissao->business_id}."
            );
        }

        if ($emissao->status !== 'contingencia') {
            throw new InvalidArgumentException(
                "Transmissão de contingência só aplica em status 'contingencia'. "
                . "Status atual: {$emissao->status}."
            );
        }

        // O XML assinado é a ÚNICA fonte: reconstruir mudaria a chave de acesso.
        if (! $emissao->xml_path || ! Storage::exists($emissao->xml_path)) {
            throw new RuntimeException(
                "NfeEmissao {$emissaoId} em contingência sem XML persistido ({$emissao->xml_path}). "
                . 'Não é possível transmitir sem reconstruir o documento — o que geraria outra chave.'
            );
        }

        $xmlSigned = (string) Storage::get($emissao->xml_path);

        $business = DB::table('business')->where('id', $businessId)->first();
        if (! $business) {
            throw new RuntimeException("Business {$businessId} não encontrado.");
        }

        $certData = $this->certificadoService->carregarParaSefaz($businessId);
        $tools = $this->criarTools($business, $certData, [], (string) $emissao->modelo);
        $idLote = str_pad((string) $emissao->id, 15, '0', STR_PAD_LEFT);

        try {
            $response = $tools->sefazEnviaLote([$xmlSigned], $idLote, 1);
        } catch (\Throwable $e) {
            return $this->registrarFalhaContingencia($emissao, $e->getMessage());
        }

        DB::transaction(function () use ($emissao, $response, $xmlSigned, $businessId) {
            $this->processarRetorno(
                $emissao,
                $response,
                $xmlSigned,
                $businessId,
                (string) $emissao->serie,
                (int) $emissao->numero,
            );
        });

        Log::info('NfeService: contingência transmitida', [
            'business_id' => $businessId,
            'emissao_id' => $emissao->id,
            'numero' => $emissao->numero,
            'status_final' => $emissao->refresh()->status,
        ]);

        return $emissao->refresh();
    }

    /**
     * Falha de transmissão de contingência: conta a tentativa e, no teto, escala.
     *
     * NÃO volta pra 'contingencia' silenciosamente no teto: vira `rejeitada` com
     * motivo, porque nota que não transmite depois de N tentativas é pendência que
     * alguém precisa OLHAR — deixá-la na fila pra sempre é o alarme que nunca toca.
     */
    private function registrarFalhaContingencia(NfeEmissao $emissao, string $erro): NfeEmissao
    {
        $tentativas = (int) $emissao->retry_count + 1;
        $estourou = $tentativas >= NfeEmissao::MAX_RETRIES_CONTINGENCIA;

        $emissao->update([
            'retry_count' => $tentativas,
            'last_retry_at' => now(),
            'status' => $estourou ? 'rejeitada' : 'contingencia',
            'motivo' => $estourou
                ? sprintf(
                    'Contingência não transmitida após %d tentativas. Última falha: %s',
                    $tentativas,
                    substr($erro, 0, 300),
                )
                : sprintf('Aguardando transmissão (tentativa %d). Última falha: %s', $tentativas, substr($erro, 0, 300)),
        ]);

        // if/else em vez de `Log::{$log}(...)`: chamada de método VARIÁVEL num facade
        // é opaca pra análise estática (Larastan reprova) e não economiza nada aqui.
        $contexto = [
            'business_id' => $emissao->business_id,
            'emissao_id' => $emissao->id,
            'numero' => $emissao->numero,
            'tentativa' => $tentativas,
            'esgotou' => $estourou,
            'erro' => substr($erro, 0, 300),
        ];

        if ($estourou) {
            Log::error('NfeService: contingência esgotou as tentativas', $contexto);
        } else {
            Log::warning('NfeService: falha ao transmitir contingência', $contexto);
        }

        return $emissao->refresh();
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
            // R-NFE-045: a sped-nfe lê `CSC`/`CSCid` (Common\Tools::addQRCode) para o QR Code da
            // NFC-e anterior à versão 3; até 2026-10-26 iam `tokenCSC`/`idCSC` vazios, que ela não
            // lê. O CSC é o cadastrado na empresa (`business.csc`/`csc_id`, o mesmo que a
            // consulta de cadastro já usa). No QR v3 a lib não exige o CSC.
            'CSC'         => (string) ($business->csc ?? ''),
            'CSCid'       => (string) ($business->csc_id ?? ''),
        ]);
    }

    /**
     * US-NFE-006 / ADR TECH-0002 — modo de emissão (tpEmis) do business AGORA.
     *
     * Devolve 1 (normal) para quem não ligou contingência, que é o default do schema
     * e o caso de 100% dos tenants hoje — por isso a emissão comum sai byte-idêntica.
     *
     * O modo depende do MODELO (Manual SEFAZ / ADR TECH-0002):
     *   modelo 55 (NF-e)  → o modo da ativação (US-NFE-071): 4 = EPEC (padrão),
     *                       6 = SVC-AN, 7 = SVC-RS
     *   modelo 65 (NFC-e) → 9 = contingência off-line (não existe SEFAZ Virtual de NFC-e)
     * Modelo 67 (CT-e) não tem contingência definida aqui: cai em normal de propósito,
     * porque inventar um tpEmis pra ele seria adivinhar lei fiscal.
     */
    private function resolverTpEmis(int $businessId, string $modelo): int
    {
        $config = NfeBusinessConfig::where('business_id', $businessId)->first();

        if (! $config || ! $config->em_contingencia) {
            return NfeEmissao::TP_EMIS_NORMAL;
        }

        return match ($modelo) {
            // US-NFE-071: o modo escolhido na ativação (EPEC / SVC-AN / SVC-RS).
            '55' => ModoContingencia::tpEmisNfe($config->contingencia_modo),
            '65' => NfeEmissao::TP_EMIS_OFFLINE_NFCE,
            default => NfeEmissao::TP_EMIS_NORMAL,
        };
    }

    /**
     * US-NFE-070 · UC-FLX-32 — `dhCont` (quando entrou em contingência) e `xJust` (por quê).
     *
     * A fonte é a ativação do tenant (`ContingenciaService::ativar`): o relógio original e o
     * motivo declarado. O schema da SEFAZ pede xJust de 15 a 256 caracteres; o formulário
     * aceita motivo a partir de 10, então motivo curto ganha o prefixo "Contingencia: " em vez
     * de ser recusado na hora de emitir. Sem config (não deveria acontecer com tpEmis != 1),
     * cai no instante da criação da emissão — ainda é a entrada real na contingência.
     *
     * @return array{0: string, 1: string}
     */
    private function dadosContingencia(int $businessId, NfeEmissao $emissao): array
    {
        $config = NfeBusinessConfig::withoutGlobalScopes() // SUPERADMIN: emissão roda em job sem sessão; filtro explícito abaixo.
            ->where('business_id', $businessId)
            ->first();

        $quando = $config?->contingencia_ativada_em ?? $emissao->created_at ?? now();
        $motivo = trim((string) ($config?->contingencia_motivo ?? ''));
        if ($motivo === '') {
            $motivo = 'SEFAZ indisponivel - emissao em contingencia';
        } elseif (mb_strlen($motivo) < 15) {
            $motivo = 'Contingencia: ' . $motivo;
        }

        return [$quando->format('Y-m-d\TH:i:sP'), mb_substr($motivo, 0, 256)];
    }

    /**
     * US-NFE-071 — o modo de contingência no formato que a sped-nfe lê (`Tools::$contingency`).
     *
     * Montado com `load()` e não com `activate()`: o `activate()` não aceita EPEC e pega o
     * relógio de AGORA; aqui o relógio e o motivo são os da ativação, os mesmos que já estão
     * no `dhCont`/`xJust` do XML — senão evento e nota contariam horas diferentes.
     */
    private function contingenciaSped(int $businessId, NfeEmissao $emissao, int $tpEmis, string $tipo): Contingency
    {
        [$dhCont, $xJust] = $this->dadosContingencia($businessId, $emissao);

        $contingencia = new Contingency();
        $contingencia->load((string) json_encode([
            'type' => $tipo,
            'motive' => $xJust,
            'timestamp' => (int) strtotime($dhCont),
            'tpEmis' => $tpEmis,
        ]));

        return $contingencia;
    }

    /**
     * US-NFE-071 · UC-FLX-32 — evento EPEC (110140) no Ambiente Nacional.
     *
     * O DANFE em EPEC só vale depois de o AN registrar o evento; a nota em si vai à SEFAZ da
     * UF depois, pelo RetentarContingenciaJob, como qualquer nota em contingência. Sucesso é
     * cStat 136 ("registrado, mas não vinculado a NF-e" — a NF-e ainda não chegou) ou 135.
     *
     * Falha NÃO derruba a emissão: o XML já está guardado e o número já foi consumido. Fica
     * um evento `rejeitado` com o erro e o motivo da nota diz que o DANFE ainda não vale —
     * quem lê a nota vê a pendência, em vez de achar que o EPEC saiu.
     */
    private function registrarEpec(NfeEmissao $emissao, Tools $tools, string $xmlSigned): NfeEvento
    {
        $xml = $xmlSigned;
        $justificativa = 'EPEC — emissão em contingência';

        try {
            $resposta = (string) $tools->sefazEPEC($xml);
        } catch (\Throwable $e) {
            return $this->falhaEpec($emissao, $justificativa, null, substr($e->getMessage(), 0, 500), []);
        }

        $std = (new Standardize($resposta))->toStd();
        $retEvento = $std->retEvento ?? null;
        if (is_array($retEvento)) {
            $retEvento = $retEvento[0] ?? null;
        }
        $inf = $retEvento->infEvento ?? null;
        $cstat = (string) ($inf->cStat ?? $std->cStat ?? '999');
        $xMotivo = (string) ($inf->xMotivo ?? $std->xMotivo ?? '');
        $payload = [
            'cStat' => $cstat,
            'xMotivo' => $xMotivo,
            'nProt' => isset($inf->nProt) ? (string) $inf->nProt : null,
            'dhRegEvento' => isset($inf->dhRegEvento) ? (string) $inf->dhRegEvento : null,
            'chNFe' => isset($inf->chNFe) ? (string) $inf->chNFe : null,
        ];

        if (! in_array($cstat, ['135', '136'], true)) {
            return $this->falhaEpec($emissao, $justificativa, $cstat, "cStat {$cstat}: {$xMotivo}", $payload);
        }

        $emissao->update([
            'motivo' => 'Emitida em contingência EPEC — evento registrado no Ambiente Nacional; '
                . 'aguardando transmissão à SEFAZ da UF.',
            'metadata' => array_merge((array) ($emissao->metadata ?? []), [
                'epec_cstat' => $cstat,
                'epec_nprot' => $payload['nProt'],
                'epec_registrado_em' => $payload['dhRegEvento'],
            ]),
        ]);

        Log::info('NfeService: EPEC registrado', [
            'business_id' => $emissao->business_id,
            'emissao_id' => $emissao->id,
            'cstat' => $cstat,
        ]);

        return NfeEvento::create([
            'business_id' => $emissao->business_id,
            'emissao_id' => $emissao->id,
            'tipo' => '110140',
            'justificativa' => $justificativa,
            'status' => 'autorizado',
            'cstat_evento' => $cstat,
            'payload_json' => $payload,
        ]);
    }

    private function falhaEpec(
        NfeEmissao $emissao,
        string $justificativa,
        ?string $cstat,
        string $erro,
        array $payload,
    ): NfeEvento {
        $emissao->update([
            'motivo' => 'Emitida em contingência EPEC, mas o evento não foi registrado no Ambiente Nacional ('
                . substr($erro, 0, 200) . '). O DANFE só vale com o EPEC registrado.',
        ]);

        Log::warning('NfeService: EPEC não registrado', [
            'business_id' => $emissao->business_id,
            'emissao_id' => $emissao->id,
            'cstat' => $cstat,
            'erro' => substr($erro, 0, 300),
        ]);

        return NfeEvento::create([
            'business_id' => $emissao->business_id,
            'emissao_id' => $emissao->id,
            'tipo' => '110140',
            'justificativa' => $justificativa,
            'status' => 'rejeitado',
            'cstat_evento' => $cstat,
            'payload_json' => $payload + ['erro' => substr($erro, 0, 500)],
        ]);
    }

    /**
     * US-NFE-006 / ADR TECH-0002 — persiste o XML assinado da nota em contingência.
     *
     * Mesmo path de `processarRetorno` (`nfe-brasil/{biz}/notas/{serie}-{numero}.xml`)
     * de propósito: o DANFE e o `DanfeService` já procuram ali, e o path é derivado de
     * (serie, numero), que não mudam quando a nota for transmitida depois.
     *
     * NÃO grava `chave_44`, `cstat` nem `emitido_em`: nada disso existe ainda — quem
     * emite esses campos é a SEFAZ, e ela não foi chamada. Preencher com placeholder
     * seria fabricar dado fiscal.
     */
    private function persistirXmlContingencia(
        NfeEmissao $emissao,
        string $xmlSigned,
        int $businessId,
        string $serie,
        int $numero,
    ): void {
        $xmlPath = sprintf('nfe-brasil/%d/notas/%s-%s.xml', $businessId, $serie, $numero);
        Storage::put($xmlPath, $xmlSigned);

        $emissao->update([
            'xml_path' => $xmlPath,
            'motivo' => 'Emitida em contingência — aguardando transmissão à SEFAZ.',
            'metadata' => array_merge((array) ($emissao->metadata ?? []), [
                'contingencia_emitida_em' => now()->toIso8601String(),
                'contingencia_tp_emis' => (int) $emissao->tp_emis,
            ]),
        ]);

        // Mesmo double-write do fluxo autorizado (ADR 0123) — o XML em contingência é
        // documento fiscal e merece o mesmo backbone de arquivos.
        $this->writeArquivoXml($emissao, $xmlPath, $xmlSigned);

        Log::info('NfeService: nota emitida em CONTINGÊNCIA (não transmitida)', [
            'business_id' => $businessId,
            'emissao_id' => $emissao->id,
            'numero' => $numero,
            'serie' => $serie,
            'tp_emis' => (int) $emissao->tp_emis,
            'xml_path' => $xmlPath,
        ]);
    }

    /**
     * Série padrão do business (`business.numero_serie_nfe`, default "1") — a mesma que
     * `emitirInterno` usa quando a emissão não informa série.
     */
    private function seriePadrao(int $businessId): string
    {
        try {
            return (string) (DB::table('business')->where('id', $businessId)->value('numero_serie_nfe') ?? '1');
        } catch (\Throwable) {
            return '1';
        }
    }

    /**
     * Thread 26 · R-NFE-031/032 — quem emite: o business ou o local (filial) da venda.
     *
     * Local com linha em `nfe_locais_emissores` emite como estabelecimento próprio: UF, CNPJ, IE e
     * razão social do `business_locations`; endereço, CEP e código IBGE da tabela-ponte (o
     * `business_locations` não tem rua nem bairro, e o `zip_code` dele é char(7)); série própria. Local sem linha (ou venda sem local) → o business, exatamente como antes.
     *
     * Recusa em vez de completar com dado da matriz: filial sem CNPJ, UF, endereço ou série, ou
     * com a mesma série do business, sairia com o emitente ou o número errados.
     *
     * @return array{uf:string,emit:array<string,mixed>,serie:?string,location_id:?int}
     */
    public function resolverEmitente(object $business, ?int $locationId, string $modelo): array
    {
        $padrao = ['uf' => $this->resolverUF($business), 'emit' => [], 'serie' => null, 'location_id' => null, 'crt' => null];
        if ($locationId === null || ! \Illuminate\Support\Facades\Schema::hasTable('nfe_locais_emissores')) {
            return $padrao;
        }

        $config = NfeLocalEmissor::withoutGlobalScopes() // SUPERADMIN: emissão roda em fila; o escopo é o where explícito
            ->where('business_id', $business->id)
            ->where('location_id', $locationId)
            ->first();
        if ($config === null) {
            return $padrao;
        }

        $loc = DB::table('business_locations')
            ->where('business_id', $business->id) // Tier 0: o local tem de ser da empresa
            ->where('id', $locationId)
            ->first();
        $uf     = strtoupper(trim((string) ($loc->state ?? '')));
        $cnpj   = preg_replace('/\D/', '', (string) ($loc->cnpj ?? ''));
        $serie  = $config->seriePara($modelo);
        $falta  = array_keys(array_filter([
            'local'          => $loc === null,
            'UF'             => ! preg_match('/^[A-Z]{2}$/', $uf),
            'CNPJ'           => strlen($cnpj) !== 14,
            'IE'             => trim((string) ($loc->inscricao_estadual ?? '')) === '',
            'logradouro'     => trim((string) $config->logradouro) === '',
            'bairro'         => trim((string) $config->bairro) === '',
            'código IBGE'    => ! preg_match('/^\d{7}$/', (string) $config->cod_municipio),
            'CEP'            => ! preg_match('/^\d{8}$/', (string) $config->cep),
            "série do modelo {$modelo}" => $serie === null,
        ]));
        if ($falta !== []) {
            throw new RuntimeException(
                "Local {$locationId} está cadastrado como emissor mas falta: " . implode(', ', $falta) . '.'
            );
        }
        // P-155: regime próprio do local (CRT 1, 2, 3 ou 4). Vazio = o regime da empresa.
        $crt = $config->crt !== null ? (int) $config->crt : null;
        if ($crt !== null && ! in_array($crt, NfeLocalEmissor::CRTS, true)) {
            throw new RuntimeException("Local {$locationId} tem regime (CRT) {$crt}, que não existe — use 1, 2, 3 ou 4.");
        }
        if ($serie === $this->seriePadrao((int) $business->id)) {
            throw new RuntimeException(
                "Local {$locationId} usa a série {$serie}, que é a série padrão da empresa — cada estabelecimento precisa de série própria."
            );
        }

        return [
            'uf'          => $uf,
            'emit'        => [
                'uf'            => $uf,
                'cnpj'          => $cnpj,
                'ie'            => (string) ($loc->inscricao_estadual ?? ''),
                'razao_social'  => (string) ($loc->razao_social ?: $loc->name),
                'nome_fantasia' => (string) ($loc->nome_fantasia ?: $loc->name),
                'logradouro'    => (string) $config->logradouro,
                'numero_end'    => trim((string) $config->numero) !== '' ? (string) $config->numero : 'SN',
                'bairro'        => (string) $config->bairro,
                'cod_municipio' => (string) $config->cod_municipio,
                'municipio'     => (string) ($loc->city ?? ''),
                'cep'           => (string) $config->cep,
            ] + ($crt !== null ? ['crt' => $crt] : []),
            'serie'       => $serie,
            'location_id' => $locationId,
            'crt'         => $crt,
        ];
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
     * Seleciona o schema do Make por business (US-FISCAL-021 / ADR 0321 · PR-C).
     *
     * `legacy` (default de TODOS os business hoje) → null → `new Make(null)` ≡ `new Make()`
     * atual → schema PL_009_V4, XML byte-idêntico. Só `full`/`hybrid_2026` (opt-in
     * explícito pós homologação) → `PL_010_V1`, habilitando o grupo UB (IBS/CBS, PR-D).
     *
     * ADR 0442 (emenda a 0321): emitente do regime normal (CRT 3) usa `PL_010_V1` sempre, sem
     * opt-in — o grupo IBS/CBS é obrigatório para ele desde 03/08/2026 (Ato Conjunto RFB/CGIBS
     * nº 4/2026). CRT 1 e 2 (Simples) seguem a flag como antes, XML byte-idêntico.
     */
    private function schemaReforma(int $businessId, int $crt = 1): ?string
    {
        if ($crt === 3) {
            return 'PL_010_V1';
        }

        $modo = NfeBusinessConfig::where('business_id', $businessId)
            ->value('reforma_tributaria_modo') ?: 'legacy';

        return in_array($modo, ['full', 'hybrid_2026'], true) ? 'PL_010_V1' : null;
    }

    /**
     * Monta o XML da NF-e via NFePHP\NFe\Make.
     */
    private function buildXml(object $business, NfeEmissao $emissao, array $dadosNfe, array $emitOverride): string
    {
        // Legacy → new Make(null) ≡ new Make() (byte-idêntico); full → new Make('PL_010_V1').
        // O mesmo modo governa a serialização do grupo UB (IBS/CBS) nos itens (PR-D).
        // ADR 0442: o CRT decide antes do Make (mesma conta do bloco emit, abaixo).
        $schemaReforma = $this->schemaReforma($business->id, (int) ($emitOverride['crt'] ?? $business->regime ?? 1));
        $nfe = new Make($schemaReforma);

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
        // Thread 20 · devolução de venda é nota de ENTRADA (tpNF=0). Sem a chave = 1, XML de sempre.
        $stdIde->tpNF      = (int) ($dadosNfe['tp_nf'] ?? 1);
        $stdIde->idDest    = $this->resolverIdDest($emitOverride, $dadosNfe['dest'] ?? [], $business);
        $stdIde->cMunFG    = $codMunEmit;
        // tpImp depende do modelo (rejeição "DANFE invalido" comum quando NFC-e usa tpImp=1):
        //   modelo 55 NFe: 1=Retrato, 2=Paisagem
        //   modelo 65 NFC-e: 4=DANFE NFC-e (bobina), 5=DANFE NFC-e em mensagem eletrônica
        $stdIde->tpImp     = (int) $emissao->modelo === 65 ? 4 : 1;
        // US-NFE-006 / ADR TECH-0002 — o modo de emissão vem da EMISSÃO, não de um literal.
        // `tp_emis` tem default 1 no schema, então emissão fora de contingência produz XML
        // byte-idêntico ao de antes deste PR. 4=EPEC (NF-e 55) · 9=off-line (NFC-e 65).
        $stdIde->tpEmis    = (int) ($emissao->tp_emis ?: NfeEmissao::TP_EMIS_NORMAL);
        // US-NFE-070 · UC-FLX-32 — com tpEmis != 1 a SEFAZ exige dhCont + xJust (rejeita sem eles).
        // Vêm da ATIVAÇÃO da contingência do tenant; com tpEmis = 1 nada muda no XML.
        if ($stdIde->tpEmis !== NfeEmissao::TP_EMIS_NORMAL) {
            [$stdIde->dhCont, $stdIde->xJust] = $this->dadosContingencia((int) $emissao->business_id, $emissao);
        }
        $stdIde->cDV       = 0;
        $stdIde->tpAmb     = (int) ($emitOverride['ambiente'] ?? $business->ambiente ?? 2);
        // Thread 20 · finNFe=4 na devolução (R-NFE-026). Sem a chave = 1 (normal), XML de sempre.
        $stdIde->finNFe    = (int) ($dadosNfe['fin_nfe'] ?? 1);
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

        // Thread 20 · R-NFE-026: a devolução referencia a chave da nota de origem (NFref/refNFe).
        if (! empty($dadosNfe['ref_nfe'])) {
            $stdRef         = new \stdClass();
            $stdRef->refNFe = (string) $dadosNfe['ref_nfe'];
            $nfe->tagrefNFe($stdRef);
        }

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
        //     (consumidor não identificado é válido pra venda <R$ [redacted Tier 0]k).
        //   - NFe (modelo 55) sem doc → erro fica claro upstream (NFe exige).
        $dest    = $dadosNfe['dest'];
        // CNPJ alfanumérico (IN RFB 2.229/2024) sai como está; antes as letras eram arrancadas.
        $doc     = Cnpj::documentoFiscal((string) ($dest['cnpj'] ?? $dest['cpf'] ?? ''));
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
        // Reforma Tributária (IBS/CBS · grupo UB): só serializa quando o business
        // está opt-in (schema PL_010). Legacy → false → nenhum item chama tagIBSCBS
        // → XML byte-idêntico ao de hoje (US-FISCAL-021 PR-D / REGRA MESTRE).
        $reformaAtiva = $schemaReforma !== null;
        $temIbsCbs    = false;
        foreach ($dadosNfe['dets'] as $idx => $det) {
            $item = $idx + 1;
            if ($this->adicionarItem($nfe, $item, $det, $crt, $reformaAtiva)) {
                $temIbsCbs = true;
            }
        }

        // ── total ────────────────────────────────────────────────────────────
        $total = $dadosNfe['total'];

        $stdICMSTot              = new \stdClass();
        $stdICMSTot->vBC         = $this->fmt($total['v_bc_icms'] ?? 0);
        $stdICMSTot->vICMS       = $this->fmt($total['v_icms'] ?? 0);
        $stdICMSTot->vICMSDeson  = 0.00;
        // R-NFE-037: soma do grupo ICMSUFDest dos itens (0 quando a nota não tem DIFAL, como antes).
        $stdICMSTot->vFCPUFDest  = (float) ($total['v_fcp_uf_dest'] ?? 0) > 0 ? $this->fmt((float) $total['v_fcp_uf_dest']) : 0.00;
        $stdICMSTot->vICMSUFDest = (float) ($total['v_icms_uf_dest'] ?? 0) > 0 ? $this->fmt((float) $total['v_icms_uf_dest']) : 0.00;
        $stdICMSTot->vICMSUFRemet = 0.00;
        $stdICMSTot->vFCP        = 0.00;
        // R-NFE-043: soma do ST dos itens (0 sem ST, como antes).
        $stdICMSTot->vBCST       = (float) ($total['v_bc_st'] ?? 0) > 0 ? $this->fmt((float) $total['v_bc_st']) : 0.00;
        $stdICMSTot->vST         = (float) ($total['v_st'] ?? 0) > 0 ? $this->fmt((float) $total['v_st']) : 0.00;
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
        $stdICMSTot->vOutro      = $this->fmt($total['v_outro'] ?? 0);
        $stdICMSTot->vNF         = $this->fmt($total['v_nf'] ?? 0);
        $nfe->tagICMSTot($stdICMSTot);

        // ── IBSCBSTot (Reforma · grupo W/IBSCBSTot) ────────────────────────────
        // Só quando ≥1 item emitiu grupo UB. Passamos stdClass vazio de propósito:
        // a lib auto-deriva vBCIBSCBS/vIBS/vCBS do acumulado por-item (uma fonte de
        // verdade → total == soma dos itens, sem soma manual). Legacy → nunca entra.
        if ($temIbsCbs) {
            $nfe->tagIBSCBSTot(new \stdClass());
        }

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
     * Adiciona item (det + imposto + ICMS + PIS + COFINS + IBS/CBS) ao Make.
     *
     * Retorna true se emitiu o grupo UB (IBS/CBS) pro item — usado pra decidir
     * a emissão do IBSCBSTot no total. Legacy/Simples → false (nada emitido).
     */
    private function adicionarItem(Make $nfe, int $item, array $det, int $crt, bool $reformaAtiva = false): bool
    {
        $stdProd         = new \stdClass();
        $stdProd->item   = $item;
        $stdProd->cProd  = (string) ($det['cprod'] ?? $item);
        $stdProd->cEAN   = 'SEM GTIN';
        $stdProd->xProd  = (string) ($det['xprod'] ?? '');
        $stdProd->NCM    = preg_replace('/\D/', '', (string) ($det['ncm'] ?? '00000000'));
        // Thread 25 · R-NFE-030: código do benefício fiscal da UF. Sem a chave = sem a tag.
        if (! empty($det['cbenef'])) {
            $stdProd->cBenef = (string) $det['cbenef'];
        }
        $stdProd->CFOP   = (string) ($det['cfop'] ?? '5102');
        $stdProd->uCom   = (string) ($det['ucm'] ?? 'UN');
        $stdProd->qCom   = $this->fmt((float) ($det['qcom'] ?? 1), 4);
        // `dec_vun` (thread 17): preço unitário real pode ter >2 casas; o leiaute aceita até 10.
        // Sem a chave = 2 casas, igual a antes (cobrança recorrente inalterada).
        $decVun          = (int) ($det['dec_vun'] ?? 2);
        $stdProd->vUnCom = $this->fmt((float) ($det['vuncom'] ?? 0), $decVun);
        $stdProd->vProd  = $this->fmt((float) ($det['vprod'] ?? 0));
        $stdProd->cEANTrib = 'SEM GTIN';
        $stdProd->uTrib  = (string) ($det['utrib'] ?? 'UN');
        $stdProd->qTrib  = $this->fmt((float) ($det['qtrib'] ?? 1), 4);
        $stdProd->vUnTrib = $this->fmt((float) ($det['vuntrib'] ?? 0), $decVun);
        $stdProd->indTot = (int) ($det['ind_tot'] ?? 1);
        if (($det['vdesc'] ?? 0) > 0) {
            $stdProd->vDesc = $this->fmt((float) $det['vdesc']);
        }
        if (($det['vfrete'] ?? 0) > 0) {
            $stdProd->vFrete = $this->fmt((float) $det['vfrete']);
        }
        if (($det['voutro'] ?? 0) > 0) {
            $stdProd->vOutro = $this->fmt((float) $det['voutro']);
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
            // Thread 25 · R-NFE-030: percentual de redução da base (CST 20/70 o exigem).
            if (($icms['pred_bc'] ?? 0) > 0) {
                $stdICMS->pRedBC = $this->fmt((float) $icms['pred_bc'], 4);
            }
            $stdICMS->vBC    = $this->fmt((float) ($icms['vbc'] ?? 0));
            $stdICMS->pICMS  = $this->fmt((float) ($icms['picms'] ?? 0));
            $stdICMS->vICMS  = $this->fmt((float) ($icms['vicms'] ?? 0));
            $this->preencherSt($stdICMS, $icms['st'] ?? null);
            $nfe->tagICMS($stdICMS);
        } else {
            // Simples Nacional — CSOSN
            $stdICMSSN        = new \stdClass();
            $stdICMSSN->item  = $item;
            $stdICMSSN->orig  = $orig;
            $stdICMSSN->CSOSN = $cstCsosn;
            if (in_array($cstCsosn, ['500', '400', '900'], true)) {
                $stdICMSSN->modBC  = (int) ($icms['modbc'] ?? 3);
                if (($icms['pred_bc'] ?? 0) > 0) {
                    $stdICMSSN->pRedBC = $this->fmt((float) $icms['pred_bc'], 4);
                }
                $stdICMSSN->vBC    = $this->fmt((float) ($icms['vbc'] ?? 0));
                $stdICMSSN->pICMS  = $this->fmt((float) ($icms['picms'] ?? 0));
                $stdICMSSN->vICMS  = $this->fmt((float) ($icms['vicms'] ?? 0));
            }
            $this->preencherSt($stdICMSSN, $icms['st'] ?? null);
            if (! empty($icms['st']) && $cstCsosn === '201') {
                // CSOSN 201 exige o crédito do Simples; sem cadastro dele, 0 (a regra não guarda).
                $stdICMSSN->pCredSN     = $this->fmt(0, 4);
                $stdICMSSN->vCredICMSSN = $this->fmt(0);
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

        // R-NFE-037: grupo NA (ICMSUFDest) — alíquotas em percentual, partilha 100% para o destino
        // desde 2019 (EC 87/2015, art. 99 do ADCT). Sem a chave, o item sai como antes.
        if (! empty($det['difal'])) {
            $d = $det['difal'];
            $stdDifal                 = new \stdClass();
            $stdDifal->item           = $item;
            $stdDifal->vBCUFDest      = $this->fmt((float) $d['vbc']);
            if ((float) $d['p_fcp'] > 0) {
                $stdDifal->vBCFCPUFDest = $this->fmt((float) $d['vbc']);
                $stdDifal->pFCPUFDest   = $this->fmt((float) $d['p_fcp'] * 100, 4);
                $stdDifal->vFCPUFDest   = $this->fmt((float) $d['v_fcp']);
            }
            $stdDifal->pICMSUFDest    = $this->fmt((float) $d['p_interna'] * 100, 4);
            $stdDifal->pICMSInter     = $this->fmt((float) $d['p_inter'] * 100, 2);
            $stdDifal->pICMSInterPart = 100;
            $stdDifal->vICMSUFDest    = $this->fmt((float) $d['v_difal']);
            $stdDifal->vICMSUFRemet   = $this->fmt(0);
            $nfe->tagICMSUFDest($stdDifal);
        }

        // ── IBS/CBS (Reforma Tributária · grupo UB · NT 2025.002 · PL_010_V1) ──
        // Serializa SÓ quando (a) o business está opt-in (schema PL_010 ativo) E
        // (b) há CST IBS/CBS configurado pro item. Simples/legado (sem regra IBS/CBS)
        // → cst nulo → grupo omitido → XML idêntico ao de hoje. Ref ADR 0321 + REGRA
        // MESTRE (memory/proibicoes.md — cálculo de valor exige dupla confirmação).
        $ibscbs = $det['ibscbs'] ?? [];
        if (! $reformaAtiva || empty($ibscbs['cst'])) {
            return false;
        }

        // MODELAGEM v1 (documentada — decisão pra Wagner na REGRA MESTRE):
        //  1. Split UF/Município: a régua fiscal (nfe_fiscal_rules) guarda UMA alíquota
        //     IBS combinada, mas o grupo UB exige separar entre gIBSUF (UF) e gIBSMun
        //     (Município). Sem coluna de split, lançamos o IBS combinado 100% em gIBSUF
        //     e zeramos gIBSMun — o vIBS do item (=vIBSUF+vIBSMun) segue == valor_ibs do
        //     motor. Refinar quando um business `full` precisar de split real (schema).
        //  2. CST único: o grupo usa um só CST; adotamos cst_ibs (o cst_cbs guardado à
        //     parte deve coincidir). Pilotos são Simples (alíquota 0) → caminho inerte.
        // Alíquotas são frações (0.001 = 0,1%); o XML espera percentual → × 100 (4 casas).
        $aliqIbs = (float) ($ibscbs['aliquota_ibs'] ?? 0);
        $aliqCbs = (float) ($ibscbs['aliquota_cbs'] ?? 0);

        $stdIBSCBS                  = new \stdClass();
        $stdIBSCBS->item            = $item;
        $stdIBSCBS->CST             = (string) $ibscbs['cst'];
        $stdIBSCBS->cClassTrib      = (string) ($ibscbs['c_class_trib'] ?? '');
        $stdIBSCBS->vBC             = $this->fmt((float) ($ibscbs['vbc'] ?? 0));
        // gIBSUF — recebe o IBS combinado (pIBSUF = fração × 100, 4 casas)
        $stdIBSCBS->gIBSUF_pIBSUF   = $this->fmt($aliqIbs * 100, 4);
        $stdIBSCBS->gIBSUF_vIBSUF   = $this->fmt((float) ($ibscbs['valor_ibs'] ?? 0));
        // gIBSMun — zerado (sem split; ver MODELAGEM v1 acima)
        $stdIBSCBS->gIBSMun_pIBSMun = $this->fmt(0, 4);
        $stdIBSCBS->gIBSMun_vIBSMun = $this->fmt(0);
        // gCBS
        $stdIBSCBS->gCBS_pCBS       = $this->fmt($aliqCbs * 100, 4);
        $stdIBSCBS->gCBS_vCBS       = $this->fmt((float) ($ibscbs['valor_cbs'] ?? 0));
        $nfe->tagIBSCBS($stdIBSCBS);

        return true;
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
                // Thread 26 · R-NFE-032: só a série padrão escreve no contador do business.
                if ((string) $serie === $this->seriePadrao($businessId)) {
                    DB::table('business')
                        ->where('id', $businessId)
                        ->update(['ultimo_numero_nfe' => $numero]);
                }
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
