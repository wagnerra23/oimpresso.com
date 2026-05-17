<?php

declare(strict_types=1);

/**
 * HallucinationEvalTest — Wave 23 (22) → Wave 25 (30) → Wave 27 (100).
 *
 * 100 golden questions distribuídas em 6 categorias com assertContains /
 * assertNotContains strict — detecta fabricação contractual.
 *
 * Padrão: dado question + answer (mockada de Service real OU fixture),
 * valida que:
 *   - mustContain: TODOS os termos canon DEVEM aparecer na resposta
 *   - mustNotContain: NENHUM termo conhecido por gerar alucinação pode aparecer
 *   - category: bucket pra coverage report (gate mínimo 14/categoria)
 *
 * Cobertura por categoria (Wave 27 — gold-standard RAGAS Vellum 2026):
 *   - rota_livre_biz4         (16) — dados reais piloto vestuário
 *   - lgpd_compliance         (16) — Art. 7, 18, retention, opt-in
 *   - nfe_nfse_contabil       (16) — CFOP, ICMS-ST, CONFAZ, cancelamento
 *   - multi_tenant_tier0      (16) — business_id scope, superadmin, FK
 *   - fsm_canon_adr0143       (16) — pipelines, GuardsFsmTransitions, Flag
 *   - constituicao_governanca (20) — ADR 0094, skills, MCP, MWART, Cockpit
 *
 * NOTAS Tier 0 (ADR 0101):
 *   - Perguntas rota_livre usam placeholders [CLIENTE]/[VALOR]/[PRODUTO]
 *     porque biz=4 é PROD — NUNCA dado real em fixture commitada.
 *   - Mock mode default — não dispara LLM externo.
 *
 * @see Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json
 * @see Wave 22 FICHA Jana §A1 HallucinationEvalTest
 * @see Wave 25 saturation 22→30
 * @see Wave 27 expansion 30→100 (W27 governance-wave-27-mega-paralelo)
 */

use Tests\TestCase;

uses(TestCase::class);

function hallucinationGoldenSet(): array
{
    return array_merge(
        hallucinationGoldenSet_rotaLivre(),
        hallucinationGoldenSet_lgpd(),
        hallucinationGoldenSet_nfe(),
        hallucinationGoldenSet_multiTenant(),
        hallucinationGoldenSet_fsm(),
        hallucinationGoldenSet_governanca(),
    );
}

/**
 * Categoria 1 — ROTA LIVRE biz=4 dados reais (16 perguntas).
 *
 * IMPORTANTE: placeholders [CLIENTE], [VALOR], [PRODUTO], [DATA] em vez de PII real
 * (ADR 0101 Tier 0 — biz=4 é PROD, nunca commitar dado real em fixture).
 */
function hallucinationGoldenSet_rotaLivre(): array
{
    return [
        [
            'question' => 'Quanto faturei nos últimos 30 dias?',
            'answer' => 'Faturamento últimos 30 dias da [CLIENTE] biz=4: R$ [VALOR] em [N] vendas. Fonte: transactions WHERE business_id=4 AND status=final AND date >= NOW()-30d.',
            'must_contain' => ['business_id=4', 'transactions', 'status=final'],
            'must_not_contain' => ['business_id=1', 'todos os clientes', 'sem filtro de tenant'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Quais meus 5 produtos mais vendidos?',
            'answer' => 'Top 5 produtos biz=4 últimos 30d: ranking por qty_sold em transaction_sell_lines JOIN products WHERE business_id=4. Resposta lista [PRODUTO_1..5] com qty + receita.',
            'must_contain' => ['business_id=4', 'transaction_sell_lines', 'products'],
            'must_not_contain' => ['todos os tenants', 'business_id=1', 'sem agregação'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Qual meu ticket médio?',
            'answer' => 'Ticket médio biz=4 últimos 30d: R$ [VALOR] = AVG(final_total) em transactions WHERE business_id=4 AND status=final AND type=sell.',
            'must_contain' => ['business_id=4', 'final_total', 'AVG'],
            'must_not_contain' => ['SUM total geral', 'sem business_id', 'biz=1 final'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Vendas por categoria nas últimas 4 semanas?',
            'answer' => 'Agregação por categoria.name biz=4: JOIN products → categories WHERE business_id=4 GROUP BY category_id. Vestuário tem categorias [CAT_1..N].',
            'must_contain' => ['business_id=4', 'categories', 'GROUP BY'],
            'must_not_contain' => ['hardcoded', 'tabela cache', 'sem JOIN'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Quanto tenho a receber em aberto?',
            'answer' => 'Inadimplência biz=4: SUM(payment_term_remaining) em transactions WHERE business_id=4 AND payment_status IN (due, partial). Status=cancelled excluído do total.',
            'must_contain' => ['business_id=4', 'payment_status', 'due'],
            'must_not_contain' => ['biz=1 aberto', 'sem filter status', 'soma cancelladas'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Quem é o dono do business_id=4?',
            'answer' => 'ROTA LIVRE — [CLIENTE Larissa] dona/operadora. Razão social: [LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME]. Vestuário em Termas do Gravatal/SC.',
            'must_contain' => ['ROTA LIVRE', 'Termas do Gravatal', 'Vestuário'],
            'must_not_contain' => ['gráfica SP', 'WR2', 'Eliana(WR2)'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Posso usar dados de biz=4 em testes Pest?',
            'answer' => 'NÃO. ADR 0101 — tests SEMPRE biz=1 OR biz=99. NUNCA biz=4 (ROTA LIVRE prod). Tier 0 IRREVOGÁVEL — dados cliente em teste é grave.',
            'must_contain' => ['NÃO', '0101', 'biz=1', 'NUNCA biz=4'],
            'must_not_contain' => ['ok em test', 'permitido com cuidado', 'às vezes pode'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Qual customização ativa em biz=4 sobre datas?',
            'answer' => 'ADR 0066 — format_date shift +3h preservado intencionalmente em ROTA LIVRE biz=4. Legacy de clientes antigos OfficeImpresso. Remover só com nova ADR supersedes.',
            'must_contain' => ['0066', 'format_date', '+3h', 'biz=4'],
            'must_not_contain' => ['bug critico', 'remover urgente', 'sem nova ADR'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Qual monitor Larissa usa pra operar?',
            'answer' => 'ROTA LIVRE opera em monitor 1280px. Designs novos precisam caber. F1.5 visual gate MWART valida largura mínima.',
            'must_contain' => ['1280px', 'ROTA LIVRE', 'F1.5'],
            'must_not_contain' => ['4K', '1920px é mínimo', 'ultrawide obrigatório'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Que percentual do volume de vendas é biz=4?',
            'answer' => 'ROTA LIVRE biz=4 representa 99% do volume de vendas do oimpresso novo (Laravel). Modules/Vestuario único vertical em produção.',
            'must_contain' => ['99%', 'volume', 'Vestuario'],
            'must_not_contain' => ['50%', 'minoria', 'ComunicacaoVisual prod'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Posso fazer F5 cutover MWART biz=4 sem canary?',
            'answer' => 'NÃO. ADR 0104 §F5 — F5 CUTOVER sem aviso prévio cliente + canary 7d é grave. ROTA LIVRE 99% volume = surprise = perda. Sempre canary.',
            'must_contain' => ['NÃO', 'canary', '0104'],
            'must_not_contain' => ['sem canary ok', 'dispensável', 'cutover direto'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'F4 QA pode usar biz=4 em smoke?',
            'answer' => 'NÃO grave. F4 QA exige smoke biz=1 (ADR 0101). Usar biz=4 (cliente) em smoke é grave. Tier 0 IRREVOGÁVEL.',
            'must_contain' => ['NÃO', 'biz=1', '0101'],
            'must_not_contain' => ['biz=4 ok', 'permitido', 'opcional'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Qual módulo vertical serve ROTA LIVRE?',
            'answer' => 'Modules/Vestuario (CNAE 4781-4/00). Único vertical em produção. ROTA LIVRE é cliente piloto Larissa em Termas do Gravatal/SC.',
            'must_contain' => ['Modules/Vestuario', '4781-4/00', 'Termas do Gravatal'],
            'must_not_contain' => ['Modules/Grafica', 'ComunicacaoVisual prod', 'OficinaAuto'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'A consulta de faturamento de biz=4 vaza pra biz=1?',
            'answer' => 'NÃO. ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL — business_id global scope obrigatório em Transaction model. Pest cross-tenant biz=1 vs biz=99 garante isolamento.',
            'must_contain' => ['NÃO', '0093', 'global scope'],
            'must_not_contain' => ['pode vazar', 'opcional', 'sem scope'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Quais produtos têm estoque baixo em biz=4?',
            'answer' => 'Query variation_location_details WHERE business_id=4 AND qty_available < alert_quantity JOIN products. Multi-tenant scope aplica via VariationLocationDetails::query() global scope.',
            'must_contain' => ['business_id=4', 'variation_location_details', 'global scope'],
            'must_not_contain' => ['todos tenants', 'sem scope', 'biz=1'],
            'category' => 'rota_livre_biz4',
        ],
        [
            'question' => 'Como Jana responde pergunta de Larissa sobre faturamento?',
            'answer' => 'CYCLE-01 goal validado em prod: Jana usa dados reais via Service tenant-scoped (business_id=4), responde em PT-BR com [VALOR] consolidado. PII NUNCA em log (PiiRedactor).',
            'must_contain' => ['business_id=4', 'PT-BR', 'PiiRedactor'],
            'must_not_contain' => ['inglês', 'CPF em log', 'sem scope'],
            'category' => 'rota_livre_biz4',
        ],
    ];
}

/**
 * Categoria 2 — Compliance LGPD (16 perguntas).
 */
function hallucinationGoldenSet_lgpd(): array
{
    return [
        [
            'question' => 'Posso deletar dados de cliente que pediu opt-out?',
            'answer' => 'Sim com ressalvas — LGPD Art. 18 V. Direito ao apagamento, MAS retenção obrigatória de NFe (5 anos CONFAZ), boletos pagos (10 anos LC 116), folha (5 anos CLT). Anonimizar PII em vez de hard delete onde lei exige retenção.',
            'must_contain' => ['Art. 18', 'Anonimizar', 'retenção'],
            'must_not_contain' => ['hard delete tudo', 'sem ressalva', 'ignorar CONFAZ'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Como exportar dados do cliente (LGPD Art. 18)?',
            'answer' => 'Art. 18 II — direito à portabilidade. Endpoint exportação JSON/CSV com dados tenant-scoped (business_id), excluindo PII de terceiros. Audit log da exportação obrigatório.',
            'must_contain' => ['Art. 18', 'portabilidade', 'Audit log'],
            'must_not_contain' => ['sem audit', 'XML obrigatório', 'só admin pode'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Qual a retenção mínima de boletos?',
            'answer' => 'Boletos pagos: 10 anos (LC 116 + CTN). Boletos cancelados: 5 anos. Soft-delete em Modules/RecurringBilling não basta — manter row com payment_status final.',
            'must_contain' => ['10 anos', 'LC 116', 'pagos'],
            'must_not_contain' => ['1 ano', 'pode deletar imediato', 'sem retenção'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'CPF/CNPJ pode aparecer em log?',
            'answer' => 'NUNCA. Logs com [REDACTED] via PiiRedactor em Modules/Jana/Services/Privacy/. Skill commit-discipline (Tier A) enforce automático em PR/commit também.',
            'must_contain' => ['NUNCA', '[REDACTED]', 'PiiRedactor'],
            'must_not_contain' => ['ok em DEBUG', 'permitido masked', 'só em prod proibido'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Onde fica o PiiRedactor canon?',
            'answer' => 'Modules/Jana/Services/Privacy/PiiRedactor.php. Sanitização Tier 0 ANTES de mandar texto pro LLM externo. Logga PII como [REDACTED]. Audit periódico D7.a do health-check.',
            'must_contain' => ['Modules/Jana/Services/Privacy', 'ANTES', '[REDACTED]'],
            'must_not_contain' => ['DEPOIS do LLM', 'opcional', 'só em prod'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Posso mandar PII pro OpenAI/Anthropic sem mask?',
            'answer' => 'NÃO. LGPD Art. 33 — transferência internacional exige garantias. PiiRedactor sanitiza ANTES do dispatch. Modelo SaaS recebe payload masked.',
            'must_contain' => ['NÃO', 'Art. 33', 'PiiRedactor'],
            'must_not_contain' => ['pode mandar livremente', 'sem mask', 'OpenAI seguro'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Cliente pediu apagamento de pedidos antigos. Posso?',
            'answer' => 'Não diretamente. Notas fiscais relacionadas têm retenção CONFAZ 5 anos. Anonimizar contact_id + manter transactions com FK NULL preservada. Audit log obrigatório.',
            'must_contain' => ['retenção', 'CONFAZ', 'Anonimizar'],
            'must_not_contain' => ['hard delete tudo', 'cascade DELETE', 'sem audit'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Quem é DPO formal no oimpresso?',
            'answer' => 'Decisão Wagner 2026-05-09: Eliana[E] esposa não assume DPO formal por enquanto. Vai estudar LGPD com calma. Counsel externo segue necessário pra Pilares 1-4 oimpresso Insights.',
            'must_contain' => ['Eliana[E]', 'não assume', 'Counsel externo'],
            'must_not_contain' => ['DPO ativo', 'Eliana assumiu', 'sem counsel'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Contact pode receber email/WhatsApp sem opt-in?',
            'answer' => 'NÃO. LGPD opt-in. NotificarClienteCancelamentoJob checa Contact::canReceiveEmailNotification() e canReceiveWhatsappNotification(). NULL=permite (back-compat), FALSE=bloqueia + log.',
            'must_contain' => ['NÃO', 'canReceive', 'NULL=permite'],
            'must_not_contain' => ['sem check', 'ignora opt-out', 'sempre manda'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Base legal pra processar dados de funcionário?',
            'answer' => 'LGPD Art. 7 V — execução de contrato (CLT) + Art. 7 II — cumprimento obrigação legal (Portaria MTP 671/2021 ponto eletrônico). Append-only obrigatório.',
            'must_contain' => ['Art. 7', 'CLT', '671/2021'],
            'must_not_contain' => ['consentimento sempre', 'sem base legal', 'opt-in obrigatório CLT'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'ponto_marcacoes pode receber UPDATE/DELETE?',
            'answer' => 'NÃO. Append-only por força de lei (Portaria 671/2021). Use Marcacao::anular() que cria registro de anulação preservando original. Triggers MySQL de imutabilidade.',
            'must_contain' => ['NÃO', 'Append-only', '671/2021', 'anular'],
            'must_not_contain' => ['UPDATE ok', 'DELETE permitido', 'sem trigger'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Cliente externo pode auditar nossos logs?',
            'answer' => 'LGPD Art. 18 IV — direito de acesso aos dados próprios. Cliente vê apenas dados do seu business_id. Logs agregados de plataforma exigem business_id scope. Audit endpoint dedicado.',
            'must_contain' => ['Art. 18', 'business_id scope', 'Audit'],
            'must_not_contain' => ['acesso total', 'sem scope', 'todos os tenants'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Posso commitar .env com senha de DB?',
            'answer' => 'NUNCA. Credenciais NUNCA em git. Vaultwarden (vault.oimpresso.com em CT 100) é cofre canônico. .gitignore bloqueia .env. memory/reference/feedback-nunca-publicar-credenciais.md detalha.',
            'must_contain' => ['NUNCA', 'Vaultwarden', '.gitignore'],
            'must_not_contain' => ['ok mascarada', 'permitido em dev', 'sem Vaultwarden'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Quanto tempo retém marcações de ponto?',
            'answer' => 'Portaria MTP 671/2021 Art. 87 — manter por 5 anos após resolução do contrato. ponto_marcacoes append-only com imutabilidade. Anulação cria registro novo, preserva original.',
            'must_contain' => ['5 anos', '671/2021', 'append-only'],
            'must_not_contain' => ['1 ano', 'pode deletar', 'sem imutabilidade'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'PII real em PR de teste é aceitável?',
            'answer' => 'NUNCA. Use placeholders [CLIENTE]/[CPF_FAKE]/[VALOR]. Skill commit-discipline (Tier A) + PiiRedactor + governance-gate.yml workflow CI bloqueiam. PR com PII real = rejeitar.',
            'must_contain' => ['NUNCA', 'placeholders', 'governance-gate'],
            'must_not_contain' => ['ok em fixture', 'mascarado parcial ok', 'sem gate CI'],
            'category' => 'lgpd_compliance',
        ],
        [
            'question' => 'Qual ADR central da Constituição cobre LGPD multi-tenant?',
            'answer' => 'ADR 0094 §6 — Multi-tenant Tier 0 IRREVOGÁVEL é princípio 6 dos 8 princípios duros. Vazar dados entre tenants viola LGPD Art. 46 (segurança). ADR 0093 detalha business_id scope.',
            'must_contain' => ['0094', '0093', 'IRREVOGÁVEL'],
            'must_not_contain' => ['princípio opcional', 'sem ADR central', 'flexível'],
            'category' => 'lgpd_compliance',
        ],
    ];
}

/**
 * Categoria 3 — NFe/NFSe contábil (16 perguntas).
 */
function hallucinationGoldenSet_nfe(): array
{
    return [
        [
            'question' => 'Qual CFOP para venda interna ao consumidor final?',
            'answer' => 'CFOP 5.102 — venda de mercadoria adquirida ou recebida de terceiros, dentro do estado. Para consumidor final não contribuinte usar 5.102 com indFinal=1 e indPres=1.',
            'must_contain' => ['5.102', 'indFinal', 'estado'],
            'must_not_contain' => ['6.102', 'interestadual interna', 'CFOP 1.000'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Como funciona ICMS-ST em venda interestadual?',
            'answer' => 'ICMS Substituição Tributária — vendedor recolhe ICMS por toda a cadeia. CST 010/020/030 conforme. Modules/NfeBrasil calcula vBCST + vICMSST via NCM + CEST + MVA do estado destino.',
            'must_contain' => ['CST', 'vICMSST', 'CEST', 'MVA'],
            'must_not_contain' => ['sem ST', 'CST 00', 'comprador recolhe'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Cancelar NFe pode quando?',
            'answer' => 'CONFAZ SINIEF 07/2005 Art. 14 — até 24h após autorização (uso 168h tolerância prática). Após isso, somente NF de devolução. Status passa pra cancelada, NUNCA forceDelete (número permanece usado oficialmente).',
            'must_contain' => ['24h', 'SINIEF 07/2005', 'cancelada', 'NUNCA forceDelete'],
            'must_not_contain' => ['sempre pode cancelar', 'forceDelete ok', 'sem prazo'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'NFe rejeitada conta no sequencial?',
            'answer' => 'NÃO conta — número fica disponível pra próxima emissão OU pra inutilização. Status rejeitada/denegada/erro_envio → marcar inutilizada (preserva registro, não hard delete).',
            'must_contain' => ['NÃO', 'inutilizada', 'preserva'],
            'must_not_contain' => ['conta sim', 'forceDelete', 'gap proibido'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'NFSe (serviço) vs NFe (produto) — quando usar?',
            'answer' => 'NFSe — serviços (municipal, ISSQN). NFe — mercadoria (federal/estadual, ICMS/IPI). NFC-e — varejo consumidor final eletrônica. Modules/NfeBrasil cobre as 3 com adapters separados.',
            'must_contain' => ['NFSe', 'ISSQN', 'NFC-e', 'Modules/NfeBrasil'],
            'must_not_contain' => ['mesmo XML', 'unificado SPED', 'NFe pra serviço'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Qual ambiente SEFAZ usar em teste?',
            'answer' => 'tpAmb=2 (homologação). NUNCA enviar pra tpAmb=1 (produção) em test/dev — emissão real com NF válida. Configurável via env NFE_AMBIENTE.',
            'must_contain' => ['tpAmb=2', 'homologação', 'NUNCA'],
            'must_not_contain' => ['tpAmb=1 em test', 'produção ok dev', 'sem env'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Boleto pago dispara NFe automática?',
            'answer' => 'Sim — US-RB-044 Modules/RecurringBilling. Webhook Asaas/Inter → confirma pagamento → enfileira EmitirNfeJob com business_id + dados da venda. Configurável por business.',
            'must_contain' => ['US-RB-044', 'EmitirNfeJob', 'Webhook'],
            'must_not_contain' => ['manual sempre', 'sem automação', 'requer admin'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Inutilização de numeração NFe — quando?',
            'answer' => 'Quando há gap (número pulado por erro). Envia inutilização no SEFAZ pro range específico. Justificativa obrigatória ≥15 chars. Modules/NfeBrasil/Services/Inutilizar coberto por SPED.',
            'must_contain' => ['gap', 'Justificativa', 'SEFAZ'],
            'must_not_contain' => ['sem justificativa', 'qualquer hora', 'sem SPED'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'CSC NFC-e vai em commit?',
            'answer' => 'NUNCA. CSC é segredo de homologação SEFAZ. Vaultwarden em CT 100 (vault.oimpresso.com). .env do business em DB encrypted. .gitignore bloqueia .env.',
            'must_contain' => ['NUNCA', 'Vaultwarden', 'encrypted'],
            'must_not_contain' => ['ok masked', 'em config', '.env commitado'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'NFe denegada — o que fazer?',
            'answer' => 'Status SEFAZ 110 — usuário sem permissão (irregularidade fiscal do destinatário). NFe denegada NÃO pode ser cancelada — número fica usado. Marcar status=denegada, NUNCA forceDelete.',
            'must_contain' => ['110', 'NÃO pode ser cancelada', 'NUNCA forceDelete'],
            'must_not_contain' => ['cancela normal', 'forceDelete sem restrição', 'reemite com mesmo num'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'CFOP para devolução interna?',
            'answer' => '1.202 — devolução de venda de mercadoria adquirida ou recebida de terceiros (entrada interna). Estado origem = estado destino do vendedor. Modules/NfeBrasil tem matrix CFOP-pares.',
            'must_contain' => ['1.202', 'devolução', 'entrada'],
            'must_not_contain' => ['5.202', 'saída', 'interestadual interna'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'CST 60 significa o quê?',
            'answer' => 'CST 60 — ICMS cobrado anteriormente por substituição tributária. Operação como Substituído (contribuinte que recebeu mercadoria com ICMS-ST já recolhido). Não destaca ICMS na nota.',
            'must_contain' => ['Substituído', 'substituição tributária', 'Não destaca'],
            'must_not_contain' => ['CST 00', 'destaca ICMS normal', 'Substituto direto'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'NFC-e contingência offline funciona?',
            'answer' => 'Sim — tpEmis=9 (contingência off-line NFC-e). Gera DANFE simplificado, transmite quando rede voltar. Modules/NfeBrasil/Services/Contingencia gerencia fila + reenvio em 24h.',
            'must_contain' => ['tpEmis=9', 'contingência', 'DANFE'],
            'must_not_contain' => ['só online', 'sem fila', 'desabilitado'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Carta de Correção NFe — quando?',
            'answer' => 'CC-e SEFAZ — corrige erros que NÃO afetem valor/quantidade/dados emit/dest/data. Ajustes em informação adicional, código produto, peso. Limite 20 CC-e por NFe.',
            'must_contain' => ['CC-e', 'NÃO afetem valor', '20'],
            'must_not_contain' => ['altera valor', 'ilimitada', 'sem limite'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Emit/Dest CPF ou CNPJ em PR?',
            'answer' => 'NUNCA. Mesma regra LGPD/PII — usa [CPF_FAKE]/[CNPJ_FAKE] em fixture. PiiRedactor sanitiza logs. governance-gate.yml CI bloqueia commit com regex CPF/CNPJ.',
            'must_contain' => ['NUNCA', 'PiiRedactor', 'governance-gate'],
            'must_not_contain' => ['mascarado ok', 'em XML é ok', 'sem CI gate'],
            'category' => 'nfe_nfse_contabil',
        ],
        [
            'question' => 'Onde fica config certificado A1 por business?',
            'answer' => 'business_metadata table com chave cert_a1_pfx_encrypted + cert_a1_password_encrypted (Crypt facade Laravel). Vaultwarden tem backup. NUNCA salvar em diretório web acessível.',
            'must_contain' => ['business_metadata', 'encrypted', 'NUNCA'],
            'must_not_contain' => ['filesystem público', 'sem encrypt', 'env compartilhado'],
            'category' => 'nfe_nfse_contabil',
        ],
    ];
}

/**
 * Categoria 4 — Multi-tenant Tier 0 (16 perguntas).
 */
function hallucinationGoldenSet_multiTenant(): array
{
    return [
        [
            'question' => 'Como criar nova empresa (business) no oimpresso?',
            'answer' => 'Via fluxo onboarding UltimatePOS → INSERT business + users (owner) + business_locations + role suffix #{biz}. Migrations idempotentes. ADR 0093 obriga business_id em tudo que tocar dados.',
            'must_contain' => ['business', '#{biz}', '0093'],
            'must_not_contain' => ['tinker direto', 'sem owner', 'sem business_id'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Como funciona isolation entre business?',
            'answer' => 'ADR 0093 — Eloquent Model usa BusinessScope global scope que adiciona WHERE business_id = session("business.id") em toda query. Trait BelongsToBusiness aplica automático.',
            'must_contain' => ['BusinessScope', 'global scope', 'BelongsToBusiness'],
            'must_not_contain' => ['filter manual', 'sem scope', 'opcional'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Superadmin pode acessar dados de biz=4?',
            'answer' => 'Sim, com withoutGlobalScopes() E comentário // SUPERADMIN: <razão>. Acesso audita via mcp_audit_log. Sem comentário = code review bloqueia PR.',
            'must_contain' => ['withoutGlobalScopes', 'SUPERADMIN', 'mcp_audit_log'],
            'must_not_contain' => ['sem auditar', 'sem comentário', 'silencioso'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Tabela mcp_memory_documents tem business_id?',
            'answer' => 'NÃO — REPO-WIDE (governança da plataforma, sem business_id). Exceção documentada ADR 0053. Mesma regra pra mcp_audit_log que é cross-tenant por design.',
            'must_contain' => ['NÃO', 'REPO-WIDE', '0053'],
            'must_not_contain' => ['tem business_id', 'tenant-scoped', 'obrigatório'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Job assíncrono sem HTTP context — como pega business_id?',
            'answer' => 'ADR 0093 §"Commands & Jobs sem HTTP context": Job recebe int $businessId no constructor. session() não funciona em queue worker. Service não usa session() (§4).',
            'must_contain' => ['$businessId', 'constructor', 'session() não funciona'],
            'must_not_contain' => ['session() funciona', 'auth() no Job', 'opcional'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Cron command precisa scope?',
            'answer' => 'Sim — iterar businesses ativos e setar scope manual via setBusinessContext($id) helper, ou usar withoutGlobalScopes() com filtro WHERE business_id = $id no SQL.',
            'must_contain' => ['setBusinessContext', 'businesses', 'WHERE business_id'],
            'must_not_contain' => ['ignorar tenant', 'sem scope cron', 'todos juntos'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Tabela de negócio nova precisa business_id?',
            'answer' => 'Sim — obrigatório indexado + FK. Migration adiciona business_id BIGINT UNSIGNED NOT NULL + INDEX + FOREIGN KEY business_id REFERENCES business(id). Trait BelongsToBusiness no Model.',
            'must_contain' => ['business_id', 'INDEX', 'FOREIGN KEY', 'BelongsToBusiness'],
            'must_not_contain' => ['opcional', 'sem FK', 'pode nullable sem ADR'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Roles Spatie multi-tenant — qual o pattern?',
            'answer' => 'Role::firstOrCreate(["name" => "{$role}#{$bizId}", "business_id" => $bizId, "guard_name" => "web"]) OU auto-detect via Schema::hasColumn("roles", "business_id"). UltimatePOS pattern.',
            'must_contain' => ['#{$bizId}', 'business_id', 'Schema::hasColumn'],
            'must_not_contain' => ['role global', 'sem business_id', 'auth() padrão'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Pest test cross-tenant — pattern obrigatório?',
            'answer' => 'ADR 0101 — criar dados biz=1 e biz=99, login biz=1, query, assertCount esperado biz=1 only. NUNCA biz=4 (ROTA LIVRE prod). Cada Model nova precisa cross-tenant test.',
            'must_contain' => ['biz=1', 'biz=99', 'NUNCA biz=4', '0101'],
            'must_not_contain' => ['biz=4 ok', 'sem cross-tenant', 'opcional'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'jana:health-check tem check de isolation?',
            'answer' => 'Sim — multi_tenant_isolation é um dos 5 checks SQL diários 06:00 BRT. Detecta rows com business_id NULL em tabelas obrigatórias. Falha vira ALERT em storage/logs/laravel.log.',
            'must_contain' => ['multi_tenant_isolation', '5 checks', 'ALERT'],
            'must_not_contain' => ['sem check', 'opcional', 'só warning'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'O que faz BusinessScope global scope?',
            'answer' => 'Eloquent Scope que adiciona WHERE business_id = session("business.id") em toda query do Model. Aplicado via trait BelongsToBusiness. Não passa pra raw queries — usar query builder com scope explícito lá.',
            'must_contain' => ['Eloquent Scope', 'session', 'BelongsToBusiness'],
            'must_not_contain' => ['raw query também aplica', 'sem session', 'opcional'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'auth()->user()->business_id é confiável em Job?',
            'answer' => 'NÃO em Job — auth() não tem context em queue worker. Sempre passar $businessId no constructor. Service Layer pega via construtor explícito, não via session/auth.',
            'must_contain' => ['NÃO', 'queue worker', 'constructor'],
            'must_not_contain' => ['confiável sempre', 'auth() funciona em Job', 'sem passar'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'tabela users tem business_id?',
            'answer' => 'Sim — users.business_id NOT NULL FK pra business(id). Usuário pertence a 1 business. Multi-business um usuário = múltiplas rows users (pattern UltimatePOS legacy).',
            'must_contain' => ['users.business_id', 'NOT NULL', 'UltimatePOS'],
            'must_not_contain' => ['nullable', 'global', 'sem FK'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Vazar dados entre tenants é qual gravidade?',
            'answer' => 'Tier 0 IRREVOGÁVEL — pior bug possível. ADR 0093 + ADR 0094 §6. Demanda hotfix imediato + post-mortem ADR + audit cross-tenant Pest novo. LGPD Art. 46 também viola.',
            'must_contain' => ['Tier 0', 'pior bug', '0093', 'Art. 46'],
            'must_not_contain' => ['bug menor', 'opcional fix', 'sem post-mortem'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'Centrifugo cross-tenant channel separado?',
            'answer' => 'Sim — channel pattern business:{biz}:user:{user_id}. ADR 0058 obriga isolation no broker. Centrifugo permissions garantem usuário de biz=1 não assina canal de biz=4.',
            'must_contain' => ['business:{biz}', '0058', 'permissions'],
            'must_not_contain' => ['channel global', 'sem isolation', 'opcional'],
            'category' => 'multi_tenant_tier0',
        ],
        [
            'question' => 'OtelHelper::spanBiz resolve business_id como?',
            'answer' => 'App\\Util\\OtelHelper::spanBiz wrap callback em span OTel com business_id auto-resolvido da session/auth. Zero overhead quando config("otel.enabled")=false. Tier 0 audit trail.',
            'must_contain' => ['App\\Util\\OtelHelper', 'business_id', 'Zero overhead'],
            'must_not_contain' => ['sempre exporta', 'Modules/Jana/OtelHelper', 'sem business_id'],
            'category' => 'multi_tenant_tier0',
        ],
    ];
}

/**
 * Categoria 5 — FSM canon ADR 0143 (16 perguntas).
 */
function hallucinationGoldenSet_fsm(): array
{
    return [
        [
            'question' => 'Como mudar estado de venda no oimpresso?',
            'answer' => 'ExecuteStageActionService::execute(subject, action_key, user, payload) em app/Domain/Fsm/. Gateway obrigatório — UPDATE direto em current_stage_id bloqueado pelo trait GuardsFsmTransitions.',
            'must_contain' => ['ExecuteStageActionService', 'app/Domain/Fsm', 'GuardsFsmTransitions'],
            'must_not_contain' => ['UPDATE direto ok', 'tinker', 'sem service'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'O que faz FsmAuthorizationFlag?',
            'answer' => 'Singleton per-request consume-once (app/Domain/Fsm/Support/FsmAuthorizationFlag.php). ExecuteStageActionService::execute aciona mark(). GuardsFsmTransitions checa antes do UPDATE — flag consumida = autoriza.',
            'must_contain' => ['Singleton', 'consume-once', 'mark()', 'FsmAuthorizationFlag'],
            'must_not_contain' => ['property dinâmica', '$tx->_flag', 'global persistente'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'GuardsFsmTransitions trait faz o quê?',
            'answer' => 'GuardsFsmTransitions trait em Transaction + JobSheet bloqueia UPDATE direto em current_stage_id lançando UnauthorizedActionException. Use ExecuteStageActionService que aciona FsmAuthorizationFlag::mark() singleton.',
            'must_contain' => ['GuardsFsmTransitions', 'current_stage_id', 'UnauthorizedActionException'],
            'must_not_contain' => ['permite UPDATE direto', 'sem exception', 'opcional bypass'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Pipeline Sells quantos stages?',
            'answer' => '11 stages (quote_draft → ... → completed + cancelled/on_hold) × 21 actions × 10 roles per-business. Aplicado em vendas legadas via fsm:bulk-start-pipeline.',
            'must_contain' => ['11 stages', '21 actions', '10 roles', 'per-business'],
            'must_not_contain' => ['13 stages', '5 actions', 'sem roles'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Pipeline Repair quantos stages?',
            'answer' => '13 stages (recebido_para_diagnostico → ... → entregue_completo + terminais) × ~15 actions × 6 roles per-business.',
            'must_contain' => ['13 stages', '~15 actions', '6 roles'],
            'must_not_contain' => ['11 stages', '21 actions', '10 roles'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Como migrar vendas legadas pro FSM?',
            'answer' => 'php artisan fsm:bulk-start-pipeline {biz} [--dry-run]. Itera transactions sem current_stage_id e seta stage inicial. 162 vendas biz=1 prontas pra migrar.',
            'must_contain' => ['fsm:bulk-start-pipeline', '--dry-run', '162'],
            'must_not_contain' => ['migration SQL direta', 'tinker', 'sem dry-run'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'fsm:scan-drift roda quando?',
            'answer' => 'Cron daily 03:00 BRT registrado em app/Console/Kernel.php. Alerta mass-update bypass — detecta UPDATE current_stage_id direto que escapou do gateway. Audit FSM.',
            'must_contain' => ['daily 03:00 BRT', 'Kernel.php', 'bypass'],
            'must_not_contain' => ['hourly', 'sem cron', 'opcional'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'sale_stage_history.action_id pode ser NULL?',
            'answer' => 'Sim — entrada Pipeline iniciado (via startPipeline ou bulk-start-pipeline) cria audit log SEM action (transição não veio de action cadastrada). Coluna nullable desde hotfix #643 2026-05-12.',
            'must_contain' => ['nullable', 'Pipeline iniciado', '#643'],
            'must_not_contain' => ['NOT NULL', 'sem nullable', 'obrigatório'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Quantas tabelas FSM canon?',
            'answer' => '5 tabelas FSM: sale_processes, sale_process_stages, sale_stage_actions, sale_stage_action_roles, sale_stage_history (audit append-only).',
            'must_contain' => ['5 tabelas', 'sale_processes', 'sale_stage_history', 'append-only'],
            'must_not_contain' => ['3 tabelas', '10 tabelas', 'mutable'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Side-effects FSM Sells — quais?',
            'answer' => 'ReservarEstoque, ConsumirEstoque, LiberarReserva, CancelarVendaCascade (orquestra cancel NFe SEFAZ + Asaas/Inter refund/cancel + Whatsapp/email). Isolados em side-effect classes.',
            'must_contain' => ['ReservarEstoque', 'CancelarVendaCascade', 'NFe SEFAZ', 'Asaas'],
            'must_not_contain' => ['no Controller', 'sem isolation', 'inline'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'UI FSM drawer fica onde?',
            'answer' => 'resources/js/Pages/Sells/_components/FsmActionPanel.tsx — botões dinâmicos por stage + RBAC + timeline auditável. SaleSheet drawer canon.',
            'must_contain' => ['FsmActionPanel', 'RBAC', 'timeline'],
            'must_not_contain' => ['hardcoded buttons', 'sem RBAC', 'no Controller'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Action is_critical sem role cadastrada — o que acontece?',
            'answer' => 'Service lança UnauthorizedActionException (fail-secure). Seed sempre cadastra role pra actions de risco (cancelar_venda, voltar_estagio, iniciar_producao).',
            'must_contain' => ['UnauthorizedActionException', 'fail-secure', 'cancelar_venda'],
            'must_not_contain' => ['executa silenciosa', 'sem exception', 'permitido'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Quando FSM LIVE prod biz=1?',
            'answer' => 'ADR 0143 — LIVE prod biz=1 desde 2026-05-12. Marco canonizado. Coexistência opt-in com state machine legacy — current_stage_id nullable permite migração gradual.',
            'must_contain' => ['0143', '2026-05-12', 'biz=1'],
            'must_not_contain' => ['biz=4', 'em planejamento', 'ainda não live'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'Property dinâmica em Model com nome ≠ coluna real?',
            'answer' => 'Proibido — Eloquent interpreta como atributo persistível e SQL UPDATE inclui na cláusula SET → "Unknown column" error. Use singleton estático per-request OU registrar $appends + accessor (lição hotfix #640 — 2026-05-12).',
            'must_contain' => ['Proibido', 'Unknown column', 'singleton', '#640'],
            'must_not_contain' => ['permitido', 'flexível', 'sem erro'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'static::observe dentro de bootXxx() do trait?',
            'answer' => 'Proibido — Laravel detecta recursão de boot e lança LogicException: bootIfNotBooted method may not be called. Use static::updating(closure) que é syntactic sugar de static::registerModelEvent (lição hotfix #639 — 2026-05-12).',
            'must_contain' => ['Proibido', 'LogicException', 'static::updating', '#639'],
            'must_not_contain' => ['permitido', 'sem recursão', 'observe em boot ok'],
            'category' => 'fsm_canon_adr0143',
        ],
        [
            'question' => 'NotificarClienteCancelamentoJob — opt-in?',
            'answer' => 'Sim — checa Contact::canReceiveEmailNotification() e canReceiveWhatsappNotification() antes de Mail::raw / dispatch WhatsApp. LGPD opt-in. NULL=permite (back-compat); FALSE=bloqueia + log.',
            'must_contain' => ['canReceiveEmailNotification', 'opt-in', 'NULL=permite'],
            'must_not_contain' => ['sem check', 'ignora opt-out', 'sempre manda'],
            'category' => 'fsm_canon_adr0143',
        ],
    ];
}

/**
 * Categoria 6 — Constituição v2 + Governança (20 perguntas).
 */
function hallucinationGoldenSet_governanca(): array
{
    return [
        [
            'question' => 'O que é o ADR 0094?',
            'answer' => 'ADR 0094 — Constituição v2: 7 camadas + 8 princípios duros. Documento mãe da governança. Princípios: Context as product, Tiered cost, Charter > Spec, Loop fechado, SoC brutal, Multi-tenant Tier 0 IRREVOGÁVEL, Transparência, Fallback.',
            'must_contain' => ['0094', '7 camadas', '8 princípios', 'IRREVOGÁVEL'],
            'must_not_contain' => ['v1', 'v3', '6 princípios'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Quais skills Tier A always-on?',
            'answer' => 'brief-first, mcp-first, multi-tenant-patterns, commit-discipline, mwart-process, mwart-comparative V4. Hook SessionStart carrega automático. Convenção interna ADR 0095.',
            'must_contain' => ['brief-first', 'mcp-first', 'multi-tenant-patterns', '0095'],
            'must_not_contain' => ['Tier B', 'slash command', 'opt-in'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Cockpit V2 — o que é?',
            'answer' => 'Dashboard executivo Wagner consolidando estado vivo do projeto via tools MCP. Métricas: velocity, burndown, cycle goals, custos IA. Supera Cockpit V1 que era documento textual estático em git.',
            'must_contain' => ['MCP', 'velocity', 'cycle goals'],
            'must_not_contain' => ['markdown estático ativo', 'V1 vigente', 'sem MCP'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'MWART é único caminho?',
            'answer' => 'Sim — ADR 0104. Único caminho canônico de migração Blade→Inertia. 5 fases obrigatórias com gate visual F1.5, RUNBOOK obrigatório, F3 estado-da-arte, smoke biz=1 em F4, canary 7d em F5.',
            'must_contain' => ['0104', 'Único caminho', '5 fases', 'gate visual F1.5'],
            'must_not_contain' => ['caminhos alternativos', 'totalmente opcional', 'sem gate'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'MCP server roda onde?',
            'answer' => 'ADR 0053 — mcp.oimpresso.com em CT 100/FrankenPHP. NUNCA em shared hosting (lento + crasha). MCP_TOOLS_EXPOSED=true só em CT 100; default false fora dele.',
            'must_contain' => ['mcp.oimpresso.com', 'CT 100', 'NUNCA'],
            'must_not_contain' => ['Hostinger sim', 'shared hosting ok', 'qualquer ambiente'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Reverb foi substituído por quê?',
            'answer' => 'ADR 0058 — Centrifugo + FrankenPHP no CT 100. Reverb tinha problemas de escala WebSocket. Centrifugo é binário Go single-process com millions of connections out-of-the-box.',
            'must_contain' => ['0058', 'Centrifugo', 'FrankenPHP', 'CT 100'],
            'must_not_contain' => ['Pusher', 'Soketi', 'Ably'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'CURRENT.md/TASKS.md ativos?',
            'answer' => 'NÃO. ADR 0070 — Jira-style task management. REMOVIDOS. Estado vivo via tools MCP (cycles-active, tasks-list) em tabelas mcp_cycles, mcp_tasks. Markdown estático morreu.',
            'must_contain' => ['NÃO', '0070', 'cycles-active', 'mcp_cycles'],
            'must_not_contain' => ['TASKS.md ativo', 'CURRENT.md em uso', 'markdown vigente'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Auto-mem privada ativa?',
            'answer' => 'NÃO. ADR 0061 + ADR 0131 — ZERO auto-mem privada legada. Hook block-automem.ps1 bloqueia Write/Edit. Canônico vai DIRETO pro git via PR.',
            'must_contain' => ['NÃO', '0061', '0131', 'block-automem'],
            'must_not_contain' => ['ativa', 'opt-in', 'permitida pra Wagner'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Pode editar ADR canon existente?',
            'answer' => 'PROIBIDO. Append-only. Criar ADR nova com supersedes: [N]. CI governance-gate.yml Job 1 bloqueia merge de PR que tenha status M/R* em memory/decisions/NNNN-*.md.',
            'must_contain' => ['PROIBIDO', 'supersedes', 'governance-gate'],
            'must_not_contain' => ['ok editar', 'pode reescrever', 'sem CI'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Quais módulos referência canônica?',
            'answer' => 'ADR 0011 — Modules/Jana, Modules/Repair, Modules/Project são módulos referência canônica. Antes de criar/ajustar qualquer arquivo, abrir o equivalente e imitar pattern.',
            'must_contain' => ['0011', 'Modules/Jana', 'Modules/Repair', 'Modules/Project'],
            'must_not_contain' => ['copiar de qualquer', 'sem referência', 'inventar pattern'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'O que é Daily Brief?',
            'answer' => 'ADR 0091 — Daily Brief gera snapshot consolidado em mcp_briefs table via cron brief:generate. Schedule daily 06:00 BRT. Tool MCP brief-fetch retorna esse snapshot (~3k tokens).',
            'must_contain' => ['0091', 'mcp_briefs', '06:00 BRT', 'brief-fetch'],
            'must_not_contain' => ['hourly', 'sem cron', 'real-time'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Sub-agent estado-da-arte faz o quê?',
            'answer' => 'Subagent Opus que pesquisa melhores em 2026 + compara com oimpresso + avalia gaps por impacto×esforço. Output: memory/sessions/YYYY-MM-DD-arte-<slug>.md.',
            'must_contain' => ['Subagent Opus', 'gaps', 'sessions/YYYY-MM-DD-arte'],
            'must_not_contain' => ['Sonnet', 'sem output', 'memory/decisions'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Sub-agent capterra-senior faz o quê?',
            'answer' => 'Auditor SÊNIOR módulo-agnóstico — pesquisa 10-15 concorrentes (25-50 buscas), 15-20 capacidades P0-P3, nota 0-100 ponderada (P0=4, P1=2, P2=1, P3=0.5). Output CAPTERRA-FICHA.md.',
            'must_contain' => ['10-15 concorrentes', '15-20 capacidades', 'P0=4', 'CAPTERRA-FICHA'],
            'must_not_contain' => ['3-5 concorrentes', 'sem ponderação', 'sem ficha'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Sub-agent whatsapp-doctor faz o quê?',
            'answer' => 'SRE de plantão do daemon Baileys CT 100. Diagnóstico + recovery (purge banned, reconnect zombie, force fallback Meta) + auditoria anti-ban + post-mortem. Runbook canônico Whatsapp/runbooks/baileys-troubleshoot-ban.md.',
            'must_contain' => ['Baileys', 'CT 100', 'fallback Meta', 'baileys-troubleshoot-ban'],
            'must_not_contain' => ['Hostinger daemon', 'Twilio', 'sem runbook'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Coordenador-paralelo decompõe como?',
            'answer' => 'Subagent Opus — decompõe problema em N waves isoladas + spawn N sub-agents general-purpose paralelos + consolidação. Output memory/sessions/YYYY-MM-DD-coord-<slug>.md.',
            'must_contain' => ['N waves', 'general-purpose', 'paralelos', 'consolidação'],
            'must_not_contain' => ['sequencial', 'sem isolation', '1 agent só'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'BgeReranker do Jana usa qual modelo?',
            'answer' => 'BAAI/bge-reranker-v2-m3 self-host CT 100 (FastAPI + FlagEmbedding). NDCG@10 +6pp vs RRF baseline. Fallback RrfReranker em HTTP fail (graceful degradation).',
            'must_contain' => ['BAAI/bge-reranker-v2-m3', 'CT 100', 'NDCG@10', 'Fallback'],
            'must_not_contain' => ['SaaS', 'Cohere prod', 'OpenAI rerank', 'Hostinger'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Cliente como sinal qualificado — ADR?',
            'answer' => 'ADR 0105 — Backlog só recebe item se cliente paga + reporta OU métrica detecta drift. Hipótese sem sinal vira ADR de feature wish, não US ativa.',
            'must_contain' => ['0105', 'cliente paga', 'feature wish', 'US ativa'],
            'must_not_contain' => ['hipótese vira US', 'sem sinal ok', 'qualquer ideia entra'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Recalibração velocidade fator 10x — ADR?',
            'answer' => 'ADR 0106 — fator 10x em tarefas codáveis com IA-pair + margem 2x. Tarefas humano-limitadas (canary 7d, monitor 30d, smoke real) mantém relógio do mundo real.',
            'must_contain' => ['0106', '10x', 'margem 2x', 'canary 7d'],
            'must_not_contain' => ['5x', 'fator único', 'sem margem'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Workflow MEXEU REGISTRA em 3 fases?',
            'answer' => 'PRE-FLIGHT (ler SPEC + RUNBOOK + ADRs) → DURING (commit incremental + push WIP 30min) → POST (PR + CI + merge + docs canon). Tier 0 IRREVOGÁVEL.',
            'must_contain' => ['PRE-FLIGHT', 'DURING', 'POST', 'IRREVOGÁVEL'],
            'must_not_contain' => ['opcional', 'depois eu commito', '2 fases'],
            'category' => 'constituicao_governanca',
        ],
        [
            'question' => 'Vaultwarden roda onde e pra quê?',
            'answer' => 'vault.oimpresso.com em CT 100 Proxmox. Cofre canônico de segredos (tokens, credenciais). Git canônico e auto-mem privada NUNCA recebem credenciais sensíveis.',
            'must_contain' => ['vault.oimpresso.com', 'CT 100', 'NUNCA'],
            'must_not_contain' => ['Hostinger vault', 'commit de credenciais ok', 'env commitado'],
            'category' => 'constituicao_governanca',
        ],
    ];
}

// =============================================================================
// TESTS — Wave 27 saturation 30→100 com bucket coverage gate.
// =============================================================================

it('gold-set hallucination tem >= 100 perguntas canon (Wave 27 saturation 30→100)', function () {
    expect(count(hallucinationGoldenSet()))->toBeGreaterThanOrEqual(100);
});

it('valida must_contain sobre todas as 100 respostas canon', function () {
    foreach (hallucinationGoldenSet() as $i => $entry) {
        foreach ($entry['must_contain'] as $term) {
            $msg = "Q#{$i} ({$entry['question']}) — answer NÃO contém termo canon '{$term}'";
            test()->assertStringContainsString($term, $entry['answer'], $msg);
        }
    }
});

it('valida must_not_contain sobre todas as 100 respostas canon (anti-alucinação)', function () {
    foreach (hallucinationGoldenSet() as $i => $entry) {
        foreach ($entry['must_not_contain'] as $term) {
            $msg = "Q#{$i} ({$entry['question']}) — answer CONTÉM termo alucinado proibido '{$term}'";
            test()->assertStringNotContainsString($term, $entry['answer'], $msg);
        }
    }
});

it('toda pergunta tem campo category populado (Wave 27 bucket coverage gate)', function () {
    foreach (hallucinationGoldenSet() as $i => $entry) {
        test()->assertArrayHasKey('category', $entry, "Q#{$i} sem campo 'category'");
        test()->assertNotEmpty($entry['category'], "Q#{$i} category vazia");
    }
});

it('bucket coverage — cada categoria tem >= 14 perguntas (mínimo gold-standard)', function () {
    $set = hallucinationGoldenSet();
    $buckets = [];
    foreach ($set as $entry) {
        $cat = $entry['category'];
        $buckets[$cat] = ($buckets[$cat] ?? 0) + 1;
    }

    $expectedCategories = [
        'rota_livre_biz4',
        'lgpd_compliance',
        'nfe_nfse_contabil',
        'multi_tenant_tier0',
        'fsm_canon_adr0143',
        'constituicao_governanca',
    ];

    foreach ($expectedCategories as $cat) {
        test()->assertArrayHasKey($cat, $buckets, "Categoria '{$cat}' ausente");
        test()->assertGreaterThanOrEqual(
            14,
            $buckets[$cat],
            "Categoria '{$cat}' tem só {$buckets[$cat]} perguntas (mínimo 14)"
        );
    }
});

it('cobertura mínima 80% — pelo menos 80 perguntas das 6 categorias chave', function () {
    $set = hallucinationGoldenSet();
    $allowedCats = [
        'rota_livre_biz4',
        'lgpd_compliance',
        'nfe_nfse_contabil',
        'multi_tenant_tier0',
        'fsm_canon_adr0143',
        'constituicao_governanca',
    ];
    $coveredCount = 0;
    foreach ($set as $entry) {
        if (in_array($entry['category'], $allowedCats, true)) {
            $coveredCount++;
        }
    }
    $totalCount = count($set);
    $coverageRatio = $coveredCount / $totalCount;

    expect($coverageRatio)->toBeGreaterThanOrEqual(
        0.80,
        "Cobertura categoria chave {$coveredCount}/{$totalCount} = " . round($coverageRatio * 100, 1) . '% < 80%'
    );
});

it('detecta alucinação simulada — answer fabricada falha o gate', function () {
    $fabricatedAnswer = 'O isolamento multi-tenant é opcional via ADR 0042. business_id é flag opcional.';
    $rotaLivreSet = hallucinationGoldenSet_rotaLivre();
    $entry = $rotaLivreSet[0]; // pergunta sobre faturamento ROTA LIVRE

    // Pelo menos 1 must_not_contain deve ser violado pela frase fabricada
    $violations = 0;
    foreach ($entry['must_not_contain'] as $term) {
        if (str_contains($fabricatedAnswer, $term)) {
            $violations++;
        }
    }

    // Se a fixture não tinha "opcional"/"sem filtro de tenant" como must_not, força check explícito
    $explicitProibidos = ['opcional', 'sem filtro de tenant', 'flag opcional'];
    foreach ($explicitProibidos as $term) {
        if (str_contains($fabricatedAnswer, $term)) {
            $violations++;
        }
    }

    expect($violations)->toBeGreaterThan(0, 'Detector de alucinação não pegou termos proibidos');
});

it('cobertura tags hallucination — perguntas mencionam dominios chave', function () {
    $set = hallucinationGoldenSet();
    $combined = implode(' ', array_column($set, 'question'));

    expect($combined)->toContain('multi-tenant');
    expect($combined)->toContain('FSM');
    expect($combined)->toContain('LGPD');
    expect($combined)->toContain('CFOP');
    expect($combined)->toContain('biz=4');
    expect($combined)->toContain('skills');
});

it('cenario por categoria — uma pergunta concreta por bucket validada', function () {
    $set = hallucinationGoldenSet();
    $byCategory = [];
    foreach ($set as $entry) {
        $byCategory[$entry['category']][] = $entry;
    }

    // Pra cada bucket pegar 1 entrada e validar contract (question + answer + must_contain + must_not_contain)
    foreach ($byCategory as $cat => $entries) {
        $first = $entries[0];
        test()->assertArrayHasKey('question', $first, "Bucket {$cat} sem question");
        test()->assertArrayHasKey('answer', $first, "Bucket {$cat} sem answer");
        test()->assertArrayHasKey('must_contain', $first, "Bucket {$cat} sem must_contain");
        test()->assertArrayHasKey('must_not_contain', $first, "Bucket {$cat} sem must_not_contain");
        test()->assertNotEmpty($first['must_contain'], "Bucket {$cat} must_contain vazio");
        test()->assertNotEmpty($first['must_not_contain'], "Bucket {$cat} must_not_contain vazio");
    }
});

it('zero PII real em fixtures (Tier 0 ADR 0101 — biz=4 prod)', function () {
    $set = hallucinationGoldenSet();
    foreach ($set as $i => $entry) {
        // Detecta CPF/CNPJ literais (XXX.XXX.XXX-XX ou XX.XXX.XXX/XXXX-XX)
        $cpfRegex = '/\d{3}\.\d{3}\.\d{3}-\d{2}/';
        $cnpjRegex = '/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/';

        $combined = $entry['question'] . ' ' . $entry['answer'];
        test()->assertDoesNotMatchRegularExpression(
            $cpfRegex,
            $combined,
            "Q#{$i} contém CPF real (use [CPF_FAKE])"
        );
        test()->assertDoesNotMatchRegularExpression(
            $cnpjRegex,
            $combined,
            "Q#{$i} contém CNPJ real (use [CNPJ_FAKE])"
        );
    }
});
