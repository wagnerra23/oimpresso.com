# CAPTERRA-INVENTÁRIO — Cliente

> Gerado por skill `comparativo-do-modulo` (`/comparativo Cliente`) em **2026-07-03** — Passo 2 da onda standalone Cliente do programa de ondas.
> Fontes: [`CAPTERRA-FICHA.md`](CAPTERRA-FICHA.md) (19 capacidades, nota 65/100) + [`SPEC.md`](SPEC.md) (US-CRM-063..078) + `App\Contact`/`App\ContactAddress` + `Modules/Crm/` + `app/Http/Controllers/ContactController.php` + `resources/js/Pages/Cliente/`.
> ADR de governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) + [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) (cliente como sinal) + [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (Tier 0).

## Resumo

| Bucket | Quantidade | % |
|---|---|---|
| ✅ APROVADO | 7 | 37% |
| 🟡 PARCIAL | 11 | 58% |
| ❌ AUSENTE | 1 | 5% |
| **Total** | 19 | 100% |

**Por score (score = peso da capacidade na FICHA):**

| Score | ✅ | 🟡 | ❌ | Total |
|---|---|---|---|---|
| **P0** (bloqueador/define o domínio) | 3 | 2 | 1 | 6 |
| **P1** (mercado tem, cliente vai pedir) | 2 | 5 | 0 | 7 |
| **P2** | 2 | 3 | 0 | 5 |
| **P3** | 0 | 1 | 0 | 1 |

**Diagnóstico:** ao contrário de Compras (16% aprovado, "motor não fecha"), o Cliente tem **37% aprovado** — o cadastro **funciona e é maduro**: os 3 P0 ✅ (cadastro rico com mod-11, auto-fill Receita/CEP, **mascaramento PII server-side à frente dos ERPs BR**) são a espinha dorsal. O que falta é **cinturão de conformidade e prova**, não o motor: o **único ❌ é o mais grave** — C04, os direitos do titular (LGPD Art. 18, erasure/portabilidade) num módulo que **é** o repositório de PII do ERP — e os 2 P0 🟡 são débitos de garantia (multi-tenant `where` manual **sem teste no `Contact` pai**; extrato com render inline parcial). O módulo **está em prod** (biz=4 ROTA LIVRE roda 5 telas React), então o sinal ADR 0105 dos itens de conformidade/qualidade é **real**, não hipotético. Ver §8 da FICHA ("O que a nota 77/Show 86 esconde").

## Inventário detalhado

| # | Capacidade (FICHA) | Score | Status | Evidência | Falta |
|---|---|:-:|:-:|---|---|
| C01 | Riqueza do cadastro (PF/PJ, mod-11, grupos, tags, campos custom) | P0 | ✅ | `tipo` PF/PJ + `Rule\BR\CpfCnpj` mod-11 + `customer_group_id` + `tags` JSON + `vip` + `parent_contact_id` (matriz/filial) | Campos custom **dinâmicos** (1..10) ausentes (só `legacy_raw` arquivo) — gap menor P2/P3 |
| C02 | Auto-preenchimento CNPJ→Receita + CEP | P0 | ✅ | `Modules/Crm/Services/BrLookupService.php` (BrasilAPI CNPJ + ViaCEP, cache 30d/90d, retry 2x, timeout 4s) + `ClienteLookupController` (`ClienteLookupCnpjCepTest`) | — (supera Bling que só importa endereço SEFAZ) |
| C03 | PII masking + proteção de campo (LGPD) | P0 | ✅ | `ContactController.php:419-432` `maskTaxNumber()` server-side antes do Inertia + `Contact.php:29-42` `logOnly` **exclui** `tax_number` (`ContactPiiLogsActivityTest` + `ClienteDrawerRowsCanonBrPayloadTest`) | Access-control por perfil (máscara é uniforme, não graduada por role); masking no HTTP layer (auditar endpoints não-Inertia) |
| C04 | **Direitos do titular (erasure/anonimização + portabilidade)** — LGPD Art. 18 | P0 | ❌ | `LgpdEsquecerTitularTool`/`DsrService` existem mas `searchableEntityMap()` cobre **só Jana chat/memória, NÃO `contacts`**; export = só PDF do extrato | Estender DSR pra `contacts` (anonimização fiscal-aware) + export do registro completo (G-01/G-06) |
| C05 | Isolamento multi-tenant (Tier 0) | P0 | 🟡 | `where('business_id')` **manual** em cada query do controller; `ContactAddress` tem `HasBusinessScope`+`ContactAddressMultiTenantTest` | **`App\Contact` NÃO tem global scope** nem `booted()`; **sem teste cross-tenant no `Contact` pai** — doc afirma "global scope", código não tem (G-02) |
| C06 | Extrato financeiro do cliente (saldo/aging/export) | P0 | 🟡 | `LedgerController` + `getLedgerDetails` (faturas+pagamentos+saldo corrido+range) + aging por `pay_term` + export PDF (mPDF) | **Render inline 100% pendente** (filtra → abre Blade legacy, US-064 gap); sem dunning automático; email TODO; sem envio WhatsApp |
| C07 | Limite de crédito com bloqueio na venda | P1 | 🟡 | `contacts.credit_limit` (col 2018) + `TransactionUtil::isCustomerCreditLimitExeeded()` calcula | **Advisory** — o `store()` da venda **não bloqueia** nem avisa; Omie hard-block, Tiny medidor PDV (G-03, ⚠️ toca valor) |
| C08 | Import + dedupe/merge | P1 | 🟡 | `ContactController::postImportContacts()` (27 colunas, validação estrita) + `Pages/Cliente/Import.tsx` wizard | **Sem preview-before-commit**, **sem dedupe/merge**, erro por-linha raso (G-04) |
| C09 | Consentimento (opt-in/opt-out marketing) | P1 | 🟡 | `whatsapp_consent`/`email_consent`/`consent_updated_at` (migration 2026-05-12) + `Contact::canReceive*Notification()` — **à frente dos ERPs BR** | **Sem UI dashboard** de opt-in/opt-out nem base-legal-por-finalidade (Art. 7º) (G-05) |
| C10 | Histórico comercial / 360 (timeline) | P1 | ✅ | 9 abas **reais** server-side no drawer/Show (`_show/*Tab.tsx`: Ledger/Sales/Payments/Documents/Atividades/Pessoas/Assinaturas/Pontos/Veículos), defer, PII mascarada | Timeline **unificada** cronológica (hoje são abas separadas; Pipedrive/RD têm feed único) |
| C11 | Múltiplos endereços + escolha na entrega | P1 | 🟡 | `ContactAddress` 1:N + `is_default`/`is_shipping` + ViaCEP (`EnderecosEntregaList.tsx`) | **PR3 pendente** — seletor na venda (`Sells/Create` ainda usa `shipping_address` texto livre), US-078 PR3 |
| C12 | Segmentação (VIP/tags/RFM) | P1 | 🟡 | `contacts.vip` + `tags` JSON + `ClienteSegmentoAgent` (tags IA: vip/churn_risk/promotor/novo/fiel) | **RFM real ausente** — sem `last_purchase_at` no schema; FrescorPill computa como? (empty lane de mercado) |
| C13 | Matriz/filial + pessoas de contato | P1 | ✅ | `parent_contact_id` self-FK + `Contact::childContacts()` + `_show/PessoasContatoTab.tsx` | — |
| C14 | Command palette / teclado (⌘K/J/K/?) | P2 | ✅ | `Index.tsx` ⌘K + cheat-sheet `?` + J/K nav + `/` foco busca (`ClienteIndexDrawer760CharterTest`) — **lane vazia no mercado** | — |
| C15 | Mapa geográfico de clientes | P2 | 🟡 | `Map.tsx` split-screen lista+mapa + `ContactController::contactMap()` | iframe Google **hardcoded**, sem defer, lib deferida (screen-grade 71) |
| C16 | Trilha de auditoria (quem viu/editou PII) | P2 | 🟡 | activity-log de **edições** (PII excluída) + `AtividadesTab` (`ClienteAuditoriaTabTest`) | **"visualizou" NÃO logado** (anti-hook charter — privacidade); Zoho tem view-audit |
| C17 | Qualidade de design/UX (DS v4, densidade) | P2 | ✅ | screen-grade **77** média (Show **86 Leader**, Index **84**), 1280px-first, PT-BR, drawer 760 denso | Header não-canon roxo em Create/Edit; paleta inline no Index (polish DS) |
| C18 | Pontos/fidelidade (reward points) | P2 | 🟡 | `_show/RewardPointsTab.tsx` nativo (UPOS rp, condicional `enable_rp`) — à frente de Omie (terceiriza Fidelimax) | Básico — sem regras de acúmulo/resgate configuráveis |
| C19 | Detecção de duplicado ao salvar | P3 | 🟡 | `ClienteAutosaveDuplicateTaxTest` + `FullNameWithBusinessDedupTest` (tratamento de tax duplicado) | Sem fluxo de **merge** de duplicados; Conta Azul avisa CPF/CNPJ dup ao salvar |

## Tasks propostas (aguardando pick do Wagner)

> **Ordem por prioridade** (P0 primeiro). Cada task nasce com `module:Cliente priority:P{N}` + tags `["capterra-gap","onda-cliente"]` + `parent_plan:programa-ondas`.
> **Sinal ADR 0105:** o módulo **está em prod** (biz=4), então os itens de **conformidade/qualidade** têm sinal real. Itens marcados **⏸️ sinal pendente** são feature-wish (sem dor reportada) — NÃO vão pro backlog ativo sem sinal.
> **NÃO foram criadas no MCP ainda.** Aprove com "todas ✅ execute" / "só P0" / "1,2,4,5" / etc.

### P0 — bloqueador / define o domínio

1. **[P0] G-01 Anonimização fiscal-aware do titular** (US-CRM-079) — ✅ execute (obrigação LGPD Art. 18 §VI + diferencial de mercado) — _estender `DsrService::searchableEntityMap()` pra `contacts`: modo anonymize que redige PII (nome/CPF/contato) **preservando o registro fiscal** (NF, retenção legal) + trilha append-only + business_id Tier 0. Fecha C04 ❌. Nenhum concorrente faz erasure preservando NF — a lane de mercado mais valiosa; a máquina (`PiiRedactor`/`DsrService`/`LgpdEsquecerTitularTool`) já existe. Esforço M._
2. **[P0] G-02 Teste cross-tenant no `Contact` pai + avaliar global scope** (US-CRM-080) — ✅ execute (dever Tier 0) — _Pest que prova user@biz=1 não acessa contato@biz=99 (findOrFail→404) no `App\Contact` (hoje só o filho `ContactAddress` tem); avaliar promover `where` manual→global scope. Alinha o código ao claim da SPEC/BRIEFING. Fecha C05 🟡. Esforço S._

### P1 — mercado tem, cliente vai pedir

3. **[P1] G-03 Limite de crédito com bloqueio/aviso na venda** (US-CRM-081) — ✅ execute — ⚠️ **toca valor → Regra Mestre** (dupla confirmação + antes→depois + aprovação) — _wirar `isCustomerCreditLimitExeeded()` no `store()` da venda com toggle bloqueia/avisa (config per-business). Ativa um campo hoje decorativo; casa com o financeiro da Eliana. Fecha C07 🟡. Omie hard-block, Tiny medidor PDV. Esforço M._
4. **[P1] G-04 Import com preview + dedupe/merge** (US-CRM-082) — ✅ execute — _preview das linhas antes do commit + detecção de duplicado (CPF/CNPJ) + merge + relatório por-linha. Import ruim gera duplicata que suja o cadastro pra sempre. Fecha C08 🟡. Agendor (dedupe config), HubSpot (auto). Esforço M._
5. **[P1] G-05 UI de consentimento + base legal por finalidade** (US-CRM-083) — ✅ execute (🟡 sinal médio) — _expor `*_consent` numa aba/toggle de opt-in/opt-out + registrar base legal (Art. 7º). Comms já respeitam as colunas; falta a UI. Fecha C09 🟡. RD Station (base legal por lead). Esforço S-M._
6. **[P1] Ledger render inline 100%** (US-CRM-084) — ✅ execute — _parar de abrir Blade legacy ao filtrar o extrato (US-064 gap conhecido). Fecha metade de C06 🟡. Esforço M._
7. **[P1] US-078 PR3 — seletor de endereço salvo na venda** (`Sells/Create`) — ✅ execute — _dropdown lista endereços do cliente + "Outro (digitar)"; hoje `shipping_address` é texto livre. **Já no SPEC/backlog** (dor Wagner 2026-06-01). Fecha C11 🟡. Esforço S (~3h). ⚠️ toca `Sells/Create` (charter + gate visual)._

### P2 — LGPD / higiene / conveniência

8. **[P2] G-06 Export de portabilidade** (registro completo do cliente CSV/JSON, US-CRM-085) — ✅ execute (🟡 sinal baixo mas legal) — _dump do registro + transações + documentos (LGPD Art. 18 V). Fecha a 2ª metade de C04. Esforço S._
9. **[P2] Map: substituir iframe Google hardcoded por lib + defer** (US-CRM-086) — 🟡 sinal baixo — _`Map.tsx` usa iframe Google hardcoded sem defer (screen-grade 71). Fecha C15 🟡. Esforço S._
10. **[P2] RFM real** (`last_purchase_at` + recência) alimentando FrescorPill + `ClienteSegmentoAgent` (US-CRM-087) — ⏸️ sinal pendente — _empty lane de mercado (ninguém faz RFM nativo BR), mas é feature-wish sem dor reportada. Fecha C12 🟡. Segurar até sinal._
11. **[P2] Campos custom dinâmicos (1..10) no cadastro** (US-CRM-088) — ⏸️ sinal pendente — _Omie/Bling têm; Larissa pode não precisar. Fecha gap de C01. Segurar até sinal._

### P3 — polish

12. **[P3] Merge de duplicados (bulk actions Index)** (US-CRM-089) — 🟡 sinal médio — _detectar + mesclar contatos duplicados no Index. **Já no backlog SPEC** ("bulk merge duplicados"). Fecha C19 🟡. Esforço M._
13. **[P3] Header canon roxo em Create/Edit + limpar paleta inline Index** (US-CRM-090) — ✅ execute (polish DS) — _fecha o resíduo de C17 (screen-grade nota Create/Edit "header não-canon roxo"). Esforço S._

---

**Recomendação (não-vinculante):** aprovar as **10 tasks ✅ execute** (1-9 exceto as ⏸️, + 13) e **segurar as 2 ⏸️ sinal pendente** (10 RFM, 11 campos custom) como feature-wish ADR 0105 até haver dor de cliente. Prioridade máxima nos **2 P0** (1 erasure fiscal-aware = obrigação + diferencial; 2 teste cross-tenant = dever Tier 0) — os dois débitos que a nota de design 77/86 esconde.

**Próximo passo após pick:** `tasks-create` no MCP (1 por task aprovada, dedup vs `tasks-list` primeiro) + apêndice das US ao `SPEC.md` (seção "Backlog vindo do Capterra-Inventário") + commit. Depois, Passo 3 da onda (régua por tela: screen-grade + `casos_coverage` nas 7 telas) e Passo 4 (catraca + sentinela).
