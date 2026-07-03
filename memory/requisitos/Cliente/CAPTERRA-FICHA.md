# CAPTERRA-FICHA — Cliente (capacidade)

> Ficha canônica de benchmark de **capacidade** do módulo Cliente (cadastro de clientes/contatos PF+PJ — core UltimatePOS `App\Contact` + MWART Inertia, código em `Modules/Crm/`).
> **Gerada:** 2026-07-03 · agente `capterra-senior` · **Onda standalone Cliente** do programa de ondas ([template-onda-modulo.md](../_Governanca/programa-ondas/template-onda-modulo.md), fila pós-Sells: Compras→Produto→**Cliente**). OK [W] 2026-07-03.
> **Persona primária:** Larissa @ ROTA LIVRE (`business_id=4`), dona PME não-técnica, vestuário Termas do Gravatal/SC, monitor 1280×1024, balcão de venda. Secundária: **Eliana** [E] (financeiro/fechamento mensal — extrato/ledger).
> **Alvo de código:** `App\Contact` (`app/Contact.php`) + `App\ContactAddress` · `Modules/Crm/Http/Controllers/{Cliente*,ContactAddress,Ledger,ClienteLookup}Controller.php` + `app/Http/Controllers/ContactController.php` (legado UPOS, ~2800 LOC) · `resources/js/Pages/Cliente/{Index,Create,Edit,Show,Import,Ledger,Map}.tsx` (+ `_drawer`/`_form`/`_show`/`_components`).
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) (Capterra-driven) + [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0) + [0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) (tests biz=1) + [0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) (drawer 760px) + [0273](../../decisions/0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md) (âncoras).

> ⚠️ **Complementar, não substituto.** Já existe o **screen-grade** (módulo Cliente **77** de média — `Show` **86 Leader**, `Index` **84**, `Import` 78, `Create`/`Edit` 75, `Ledger` 72, `Map` 71 — [board](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md)) e o [`BRIEFING.md`](BRIEFING.md) + [`SPEC.md`](SPEC.md) (15 US). Esta ficha mede **CAPACIDADE** (riqueza do cadastro, LGPD/PII, crédito, extrato, histórico comercial, import) vs os líderes de cadastro+CRM BR — eixo que a nota de design **não mede**. Ver §8 "O que a nota alta esconde".

> 🪪 **Cliente ≠ CRM.** Este é o **cadastro de Cliente/contatos**, separado do *pipeline CRM* (leads/propostas/funil), que está em depreciação ([ADR 0301](../../decisions/0301-separar-cliente-deprecar-crm-pipeline.md)). Os concorrentes de CRM abaixo (RD Station/Pipedrive/Agendor) entram só como **teto dos eixos de consentimento e 360-timeline** — eles não têm registro fiscal BR (CPF/CNPJ/NF), logo não pontuam em paridade de cadastro/extrato.

---

## 1. Identidade do módulo

- **Nome interno:** `Cliente` (cadastro) — canon vive em `memory/requisitos/Cliente/`; **código backend ainda em `Modules/Crm/`** (rename de módulo não feito) + core `App\Contact`/`App\ContactAddress` (tabelas `contacts`/`contact_addresses`).
- **Domínio:** cadastro de clientes PF e PJ com canon fiscal BR (CPF/CNPJ mod-11, IE/RG, regime, endereço, contato) + visão de detalhe rica (drawer 760px, [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md)) + extrato financeiro (Ledger) + import + mapa geográfico.
- **Função:** manter o registro do cliente (dado PII-heavy), com múltiplos endereços, lookup CEP (ViaCEP) e CNPJ (BrasilAPI), 9 abas de 360° (vendas/pagamentos/extrato/documentos/atividades/pessoas/assinaturas/pontos/veículos) e mascaramento LGPD.
- **Estado lifecycle:** 7 Pages Inertia flag-gated (`config/mwart.php` `MWART_CLIENTE_*`, fallback Blade em `ContactController::shouldRenderInertiaCliente`); **Wagner confirmou biz=4 (ROTA LIVRE) rodando 5 telas em React em prod** ([ALINHAMENTO 2026-06-22](audits/ALINHAMENTO-cliente-2026-06-22.md)). Superfície de detalhe **viva** = drawer do Index; `Show.tsx` = dual-render legado.
- **Clientes diretos:** ROTA LIVRE biz=4 (Larissa, cadastro do dia-a-dia) + Wagner biz=1 (canary/smoke seguro) + Eliana (extrato/ledger).
- **Diferencial-chave:** **mascaramento PII server-side + activity-log que exclui CPF/CNPJ** (à frente dos ERPs BR, que são policy-only) + **colunas de consentimento** (`whatsapp_consent`/`email_consent`) com métodos de guarda + **command palette ⌘K** (lane vazia no mercado) + PiiRedactor/DsrService já existentes (máquina pronta pra o diferencial da §7).

## 2. Concorrentes-alvo

Pricing qualitativo (Tier 0: não commitar valores BRL — [proibicoes](../../proibicoes.md)). CRMs sem fiscal BR marcados — só teto de consentimento/360.

| # | Concorrente | Tipo | Faixa | Lacuna que o oimpresso pode preencher | Fonte |
|---|---|---|---|---|---|
| 1 | **Bling** | ERP PME BR (cadastro cli/forn) | entrada | **CNPJ→Receita não auto-preenche** (só "importar endereço da SEFAZ", ICMS-contrib); import **sem dedupe**; crédito bloqueia *soft* (marca pedido, boleto fura) | ajuda.bling.com.br |
| 2 | **Tiny (Olist)** | ERP PME BR | entrada | Cadastro parte de CNPJ+CEP; LGPD policy-only. **Referência forte:** medidor de limite de crédito no PDV/pedido/NFe | ajuda.olist.com |
| 3 | **Omie** | ERP mid BR | médio | **Cadastro BR mais rico** (Pesquisa Atômica Receita + multi-endereço + características + crédito **hard-block** + Curva ABC) — nossa referência-topo de cadastro | omie.com.br |
| 4 | **Conta Azul** | ERP/financeiro BR | médio | Receita auto-fill + **aviso de CPF/CNPJ duplicado ao salvar** + régua de cobrança (dunning WhatsApp/email) | ajuda.contaazul.com |
| 5 | **RD Station CRM** | CRM BR (sem fiscal) | free→pago | **SOTA de LGPD-como-feature**: base legal por lead (Art. 7º), opt-out em todo email, export com base legal — teto de **consentimento** | rdstation.com |
| 6 | **Pipedrive** | CRM global (sem fiscal) | pago US$ (risco câmbio) | Marketing-status por contato + **timeline cronológica** (deals/atividades/notas/emails) — teto de **360** | support.pipedrive.com |
| 7 | **Agendor** | CRM BR (sem fiscal) | free→pago | **Melhor import/dedupe BR**: regra de dedupe configurável + relatório de linhas rejeitadas + criar campo no mapeamento | ajuda.agendor.com.br |
| 8 | **Ploomes** | CRM BR (integra Omie) | pago | **Único com erasure de campo-a-campo**: flag "dado de pessoa física" auto-purga no delete + controle de acesso por perfil — referência de LGPD-by-design | suporte.ploomes.com |
| 9 | **HubSpot** (free) | CRM global | free | **Auto-dedupe no import** (email/domínio) + match por Record-ID (update não duplica) — referência de import | knowledge.hubspot.com |
| 10 | **Zoho CRM** | CRM global | pago | **Audit log de quem visualizou/editou** (por user/módulo, 60d) — referência de trilha de acesso a PII | zoho.com |

## 3. Capacidades em produção (validadas)

```yaml
capacidades_em_prod:
  - us: US-CRM-072
    nome: "Canon fiscal BR: 10 campos restaurados (cpf_cnpj/ie_rg/regime/consumidor_final/contribuinte) + Rule\\BR\\CpfCnpj mod-11"
    score: P0
    onde: "migration restore_br_fields + app/Rules/BR/CpfCnpj.php"
    evidencia: "ContactBrFieldsRestoredTest + tests/Unit/Rules/BR/CpfCnpjTest (mod-11 comportamental)"

  - us: US-CRM-075
    nome: "Lookup CNPJ (BrasilAPI) + CEP (ViaCEP) server-side cache-backed (TTL 30d/90d, retry 2x, timeout 4s)"
    score: P0
    onde: "Modules/Crm/Services/BrLookupService.php + ClienteLookupController.php"
    evidencia: "ClienteLookupCnpjCepTest"

  - us: US-CRM-076
    nome: "FormRequest server-side wira Rule\\BR\\CpfCnpj (defesa em profundidade — não confia no client)"
    score: P0
    onde: "app/Http/Requests/Cliente/{Store,Update}ContactRequest.php"
    evidencia: "StoreContactRequestTest (HTTP comportamental)"

  - us: US-LGPD
    nome: "Mascaramento PII server-side (maskTaxNumber CPF/CNPJ antes do Inertia) + activity-log logOnly SEM tax_number"
    score: P0
    onde: "app/Http/Controllers/ContactController.php:419-432 + app/Contact.php:29-42"
    evidencia: "ContactPiiLogsActivityTest + ClienteDrawerRowsCanonBrPayloadTest (regex maskTaxNumber)"

  - us: US-LGPD-CONSENT
    nome: "Colunas de consentimento (whatsapp_consent/email_consent/consent_updated_at) + guardas canReceive*Notification()"
    score: P1
    onde: "migration 2026_05_12_060001 + app/Contact.php:182-196"
    evidencia: "null-as-permit back-compat; UI dashboard de opt-in FUTURO"

  - us: US-CRM-078
    nome: "Múltiplos endereços por contato (1:N, is_default/is_shipping, espelho inline UPOS)"
    score: P1
    onde: "app/ContactAddress.php + contact_addresses (HasBusinessScope + softDeletes)"
    evidencia: "ContactAddressMultiTenantTest (cross-tenant biz=1 vs biz=99). GAP: PR3 seletor na venda pendente"

  - us: US-CRM-063..070
    nome: "Drawer/Show 360° — 9 abas reais (Extrato/Vendas/Pagamentos/Documentos/Atividades/Pessoas/Assinaturas/Pontos/Veículos), server-side, defer, PII mascarada"
    score: P1
    onde: "resources/js/Pages/Cliente/_show/*Tab.tsx + _drawer/*"
    evidencia: "LedgerTabTest/SalesTabTest/PaymentsTabTest/PessoasContatoTabTest (comportamentais)"

  - us: US-CRM-064
    nome: "Extrato financeiro (Ledger): faturas + pagamentos + saldo corrido + range de datas + export PDF (mPDF)"
    score: P0
    onde: "Modules/Crm/Http/Controllers/LedgerController.php + _show/LedgerTab.tsx + Pages/Cliente/Ledger.tsx"
    evidencia: "getLedgerDetails + aging por pay_term. GAP: render inline 100% parcial (filtra → abre Blade legacy); email TODO"

  - us: US-CRM-071
    nome: "Command palette ⌘K + cheat-sheet (?) + navegação J/K + foco busca (/) no Index"
    score: P2
    onde: "resources/js/Pages/Cliente/Index.tsx"
    evidencia: "ClienteIndexDrawer760CharterTest — 1º Page do oimpresso com palette nativo"

  - us: US-CRM-IMPORT
    nome: "Import CSV/XLSX (27 colunas, validação estrita de contagem)"
    score: P1
    onde: "ContactController::postImportContacts() + Pages/Cliente/Import.tsx"
    evidencia: "Wave1ImportInertiaTest. GAP: sem preview-before-commit, sem dedupe/merge, erro por-linha raso"

  - us: US-CRM-SEG
    nome: "Segmentação: VIP flag + tags JSON + ClienteSegmentoAgent (tags IA: vip/churn_risk/promotor/novo/fiel)"
    score: P1
    onde: "contacts.vip + contacts.tags + Modules/Crm/.../ClienteSegmentoAgent.php"
    evidencia: "guard estrutural — RFM real (last_purchase_at) NÃO existe no schema"

  - us: US-CRM-MAP
    nome: "Mapa geográfico de clientes (split-screen lista+mapa)"
    score: P2
    onde: "resources/js/Pages/Cliente/Map.tsx + ContactController::contactMap()"
    evidencia: "screen-grade 71. GAP: iframe Google hardcoded, sem defer"
```

## 4. Dimensões de capacidade P0-P3 — comparativa

Legenda: ✅ pareia/supera líder · 🟡 parcial · ❌ ausente. Nota /10 por mecanismo concreto (não por nome do concorrente).

| ID | Capacidade | Peso | Líder do eixo (mecanismo SOTA) | oimpresso Cliente hoje | Nota /10 |
|---|---|:-:|---|---|:-:|
| **C01 (P0)** | Riqueza do cadastro (PF/PJ, mod-11, grupos, tags, campos custom) | 4 | Omie (multi-endereço + características + campos custom + config obrigatoriedade) | ✅ PF/PJ `tipo` + `CpfCnpj` mod-11 + `customer_group_id` + `tags` JSON + VIP + matriz/filial; **❌ campos custom dinâmicos** (só `legacy_raw` arquivo) | **8** |
| **C02 (P0)** | Auto-preenchimento CNPJ→Receita + CEP | 4 | Omie/Conta Azul (Receita auto-fill abre form pronto) | ✅ `BrLookupService` (BrasilAPI CNPJ + ViaCEP, cache, retry, timeout 4s) — supera Bling (só endereço SEFAZ) | **8** |
| **C03 (P0)** | **PII masking + proteção de campo (LGPD)** | 4 | Ploomes (flag campo-a-campo); ERPs BR = **policy-only** | ✅ `maskTaxNumber` server-side antes do Inertia + `logOnly` exclui CPF/CNPJ + testes de máscara — **à frente de TODO ERP BR**; 🟡 sem access-control por perfil (máscara uniforme, não graduada) | **8** |
| **C04 (P0)** | **Direitos do titular (erasure/anonimização + portabilidade export)** — LGPD Art. 18 | 4 | RD Station (base legal); Ploomes (auto-purge no delete) | ❌ DSR (`LgpdEsquecerTitularTool`) cobre **só Jana chat/memória, NÃO `contacts`**; export = só PDF do extrato, sem dump do registro completo. Obrigação legal **descoberta** (§8) | **3** |
| **C05 (P0)** | Isolamento multi-tenant (Tier 0) | 4 | — (concorrentes multi-empresa, não Tier-0 rígido) | 🟡 **`App\Contact` NÃO tem global scope** — filtro `where('business_id')` **manual** em cada query (footgun); `ContactAddress` tem trait+teste, mas o **Contact pai não tem teste cross-tenant** | **6** |
| **C06 (P0)** | Extrato financeiro do cliente (saldo/aging/export) | 4 | Conta Azul (régua de cobrança) + Bling (vencidos + WhatsApp/PDF) | ✅ `LedgerController` faturas+pagamentos+saldo corrido+range+aging+PDF; 🟡 **sem dunning automático**, email TODO, sem envio WhatsApp | **7** |
| **C07 (P1)** | Limite de crédito com bloqueio na venda | 2 | Omie (hard-block faturamento) + Tiny (medidor no PDV) | 🟡 campo `credit_limit` + `isCustomerCreditLimitExeeded()` **calcula mas é advisory** — a venda **não bloqueia** se estourar o limite | **5** |
| **C08 (P1)** | Import + dedupe/merge | 2 | Agendor (dedupe config + relatório) / HubSpot (auto-dedupe) | 🟡 import 27-col funciona, mas **sem preview**, **sem dedupe/merge**, erro por-linha raso | **4** |
| **C09 (P1)** | Consentimento (opt-in/opt-out marketing) | 2 | RD Station/Pipedrive (base legal por registro) | 🟡 colunas `*_consent` + `canReceive*` reais (**à frente dos ERPs BR**), mas **sem UI dashboard** nem base-legal-por-finalidade | **6** |
| **C10 (P1)** | Histórico comercial / 360 (timeline) | 2 | Pipedrive/RD (feed cronológico único) | ✅ 9 abas reais (vendas/pag/extrato/docs/atividades/pessoas/assinaturas/pontos/veículos); 🟡 **abas separadas, não uma timeline unificada** | **7** |
| **C11 (P1)** | Múltiplos endereços + escolha na entrega | 2 | Omie (multi-endereço) | ✅ `ContactAddress` 1:N + is_default/is_shipping + ViaCEP; 🟡 **PR3 pendente** (seletor na venda; `shipping_address` ainda texto livre) | **7** |
| **C12 (P1)** | Segmentação (VIP/tags/RFM) | 2 | — (ninguém faz RFM nativo; Omie só Curva ABC) | 🟡 VIP + tags JSON + **agente IA de segmento** (novel); **❌ RFM real** (sem `last_purchase_at` no schema) | **6** |
| **C13 (P1)** | Matriz/filial + pessoas de contato | 2 | Omie / CRMs | ✅ `parent_contact_id` self-FK + `childContacts` + `PessoasContatoTab` | **7** |
| **C14 (P2)** | Command palette / teclado (⌘K/J/K/?) | 1 | — (nenhum ERP/CRM BR tem) | ✅ ⌘K + cheat-sheet + J/K — **lane vazia**, 1º Page com palette nativo | **9** |
| **C15 (P2)** | Mapa geográfico de clientes | 1 | — (feature rara) | 🟡 `Map.tsx` split-screen, mas iframe Google hardcoded, sem defer | **6** |
| **C16 (P2)** | Trilha de auditoria (quem viu/editou PII) | 1 | Zoho CRM (audit log por user/módulo) | 🟡 activity-log de **edições** (PII excluída) + `AtividadesTab`; **"visualizou" NÃO logado** (anti-hook charter — privacidade) | **6** |
| **C17 (P2)** | Qualidade de design/UX (DS v4, densidade, PT-BR) | 1 | Shopify/Linear (referência global) | ✅ screen-grade 77 (Show 86 Leader, Index 84), 1280px-first, PT-BR, drawer denso | **8** |
| **C18 (P2)** | Pontos/fidelidade (reward points) | 1 | Omie (via integração Fidelimax) | 🟡 `RewardPointsTab` nativo (UPOS rp, condicional `enable_rp`) — **à frente** de Omie (que terceiriza), mas básico | **6** |
| **C19 (P3)** | Detecção de duplicado ao salvar | 0.5 | Conta Azul (aviso CPF/CNPJ duplicado ao salvar) | 🟡 há tratamento de tax duplicado (`ClienteAutosaveDuplicateTaxTest` + `FullNameWithBusinessDedupTest`), mas sem fluxo de merge | **5** |

## 5. Cálculo da nota ponderada

Pesos canônicos: **P0=4 · P1=2 · P2=1 · P3=0.5**.

```
P0 (peso 4): (C01 8 + C02 8 + C03 8 + C04 3 + C05 6 + C06 7) = 40 × 4 = 160
P1 (peso 2): (C07 5 + C08 4 + C09 6 + C10 7 + C11 7 + C12 6 + C13 7) = 42 × 2 = 84
P2 (peso 1): (C14 9 + C15 6 + C16 6 + C17 8 + C18 6) = 35 × 1 = 35
P3 (peso 0.5):(C19 5) = 5 × 0.5 = 2.5

Σ ponderado = 160 + 84 + 35 + 2.5 = 281.5

Máximo possível:
  P0: 6×10×4 = 240 · P1: 7×10×2 = 140 · P2: 5×10×1 = 50 · P3: 1×10×0.5 = 5  → 435

nota_capacidade = 281.5 / 435 × 100 = 64.7 → 65/100
```

```
NOTA CAPACIDADE oimpresso Cliente: 65/100
Referência-topo BR (Omie, cadastro):    ~72/100  — Receita auto-fill + multi-endereço + crédito hard-block + Curva ABC
Referência BR direta (Conta Azul):      ~66/100  — Receita auto-fill + dedupe-warn + dunning; cadastro mais leve
Referência BR entrada (Bling):          ~60/100  — funcional, mas SEFAZ só-endereço, import sem dedupe, crédito soft
Teto de consentimento/360 (RD/Pipedrive): n/a p/ cadastro — sem fiscal BR (CPF/CNPJ/NF); só fixam o topo de C04/C09/C10

Gap pro topo BR (Omie): -7 pts. Causa: erasure/portabilidade LGPD (C04=3) + import sem dedupe (C08) + crédito não-bloqueante (C07).
Vantagem sobre Bling/Conta Azul em: PII masking server-side (C03), command palette (C14), consentimento por-registro (C09), 360 de 9 abas (C10), Tier-0 (C05).
```

**Leitura honesta:** a capacidade (65) fica **próxima** do design (77) — bem diferente de Sells (design 88-90 vs capacidade 60, um abismo). O cadastro é um módulo **maduro** cuja capacidade quase acompanha o polimento. O oimpresso **lidera os ERPs BR** em mascaramento de PII, palette de teclado e consentimento por-registro, e **empata** com o mid-tier (Conta Azul) — mas fica atrás do Omie nos eixos operacionais de cadastro (crédito bloqueante, dedupe de import) e **descoberto** no eixo que a própria natureza PII-heavy do módulo mais cobra: **os direitos do titular (erasure/portabilidade)**.

## 6. Top gaps P0/P1 (pra subir a nota)

| # | Gap | Cap | Esforço | ROI (persona) | Sinal ADR 0105 | Concorrente / lane |
|---|---|---|---|---|---|---|
| **G-01** | **Anonimização fiscal-aware do titular** — estender `DsrService` pra `contacts`: anonimiza PII (nome/CPF/contato) **preservando o registro fiscal** (NF, retenção) + trilha append-only — fecha C04 | C04 | M (~12-16h) | **P0 — obrigação legal** (LGPD Art. 18 §VI) + **lane de mercado vazia** (CRMs têm consentimento sem NF; ERPs têm NF sem erasure) | ✅ execute (é lei) | **ninguém faz bem** — Ploomes chega perto sem fiscal |
| **G-02** | **Teste cross-tenant no `Contact` pai + avaliar global scope** — hoje é `where('business_id')` manual sem teste no Contact (só no filho `ContactAddress`); fecha o gap entre o que a doc diz ("global scope") e o que o código faz — fecha C05 | C05 | S (~4h) | **P0 — dever Tier 0**; o isolamento hoje repousa em disciplina manual | ✅ execute (Tier 0) | — |
| **G-03** | **Limite de crédito com bloqueio/aviso na venda** — wirar `isCustomerCreditLimitExeeded()` no `store()` da venda com toggle bloqueia/avisa (Wagner aprova) — fecha C07 | C07 | M (~10h) | alto (Eliana/financeiro evita inadimplência) — ⚠️ **toca valor → Regra Mestre** (dupla confirmação + antes→depois) | 🟡 medir se há vendas a prazo estourando limite | Omie (hard), Tiny (medidor PDV) |
| **G-04** | **Import com preview + dedupe/merge** — preview das linhas antes do commit + detecção de duplicado (CPF/CNPJ) + merge + relatório por-linha — fecha C08 | C08 | M (~12h) | alto (import ruim gera duplicata que suja o cadastro pra sempre) | ✅ execute (dor de migração real) | Agendor (config dedupe), HubSpot (auto) |
| **G-05** | **UI de consentimento + base legal por finalidade** — expor as colunas `*_consent` numa aba/toggle de opt-in/opt-out + registrar base legal (Art. 7º) — fecha C09 | C09 | S-M (~8h) | médio-alto (marketing/cobrança precisa de opt-in provável) | 🟡 medir necessidade (comms hoje já respeitam colunas) | RD Station (base legal por lead) |
| **G-06** | **Export de portabilidade** (registro completo do cliente CSV/JSON, LGPD Art. 18 V) | C04 | S (~4h) | médio (raro mas legal) | 🟡 sob demanda | — |

## 7. Diferenciais oimpresso vs concorrentes

1. **Mascaramento PII server-side + activity-log sem CPF/CNPJ** (`maskTaxNumber` antes do Inertia + `logOnly` whitelist + testes de máscara) — **à frente de TODOS os ERPs BR**, que tratam LGPD como página de política, não como mecânica no registro.
2. **Consentimento por-registro** (`whatsapp_consent`/`email_consent`/`consent_updated_at` + guardas `canReceive*Notification()`) — Bling/Omie/Conta Azul **não têm** consentimento no cadastro; só os CRMs (RD/Pipedrive) têm.
3. **Command palette ⌘K + J/K + cheat-sheet** no Index — **lane de mercado vazia**: nenhum ERP/CRM BR oferece navegação por teclado nesse nível.
4. **Agente IA de segmento** (`ClienteSegmentoAgent` — vip/churn_risk/promotor/novo/fiel) — categorização assistida por IA, além da Curva ABC monetária do Omie.
5. **360° de 9 abas** incluindo **veículos** (integração vertical Oficina) — PDV/CRM horizontal não tem essa aba.
6. **Máquina de LGPD já existe** (`PiiRedactor` + `DsrService` + `LgpdEsquecerTitularTool` modo anonymize/hard + trilha append-only) — falta **apontá-la pro `contacts`** (G-01). O diferencial mais forte do mercado (anonimização fiscal-aware) está a um passo, não a um projeto.

## 8. O que a nota "77 / Show 86 Leader" esconde (leitura adversarial)

O pedido da onda: procurar o que a nota alta **esconde**. Quatro achados:

1. **A nota 77/86 é de DESIGN, não de capacidade.** O screen-grade mede UX/DS (density, tokens, defer, PT-BR). Ele **não mede** direitos do titular, enforcement de crédito, ou qualidade do import. A nota de capacidade (esta ficha) é **65** — e, diferente de Sells, o gap é pequeno (o cadastro é maduro). O que a tela bonita esconde não é "a conta não fecha" (Sells), é **"o titular não tem como ser esquecido"**.

2. **Um módulo PII-heavy sem direito ao esquecimento do titular (C04=3).** O `LgpdEsquecerTitularTool` existe e é bom — mas o `DsrService::searchableEntityMap()` **não lista a tabela `contacts`**. Ou seja: o cliente pode pedir apagamento (LGPD Art. 18 §VI) e **não há caminho** pra anonimizar/apagar o registro do cliente — só o chat da Jana está coberto. A portabilidade (Art. 18 V) também é parcial (só PDF do extrato, sem dump do registro). Para o módulo que **é** o repositório de PII do ERP, essa é a lacuna de maior exposição legal — e a mais irônica, porque a máquina (`PiiRedactor`/`DsrService`) já existe pra outros dados.

3. **O "Tier 0 global scope" do cadastro é, no código, filtro manual.** A [SPEC §1](SPEC.md) e o [BRIEFING](BRIEFING.md) afirmam *"`App\Contact` usa global scope `business_id`"*. **No código, `app/Contact.php` não tem `addGlobalScope` nem `HasBusinessScope` nem `booted()`** — o isolamento é `where('business_id', $business_id)` **repetido à mão** em cada query do controller (padrão UPOS legado). Funciona em prod há anos, mas repousa em **disciplina manual**, e **não há teste cross-tenant no `Contact` pai** (só no filho `ContactAddress`, via `ContactAddressMultiTenantTest`). O claim da doc está **à frente do código**: o dia em que alguém esquecer o `where`, vaza tenant e nenhum teste pega. (G-02 fecha isso.)

4. **O limite de crédito é calculado mas não morde (C07=5).** `isCustomerCreditLimitExeeded()` (`TransactionUtil.php:4089`) computa se o cliente estourou o limite — mas é **advisory**: o `store()` da venda **não bloqueia** nem avisa por padrão. Omie faz hard-block no faturamento; Tiny mostra medidor no PDV antes de vender. No oimpresso, a venda a prazo passa por cima do limite em silêncio — o campo existe pra nada operacional.

**Síntese adversarial:** o `Show 86 Leader` diz "cadastro lindo e completo"; a capacidade diz "**o titular do dado não tem porta de saída, o isolamento é manual sem rede, e o limite de crédito é decorativo**". Os dois primeiros são **obrigação** (LGPD Art. 18 + Tier 0); o G-01 (anonimização fiscal-aware) é, de quebra, **o diferencial de mercado mais forte disponível** — barato perto do custo de um pedido de titular sem resposta.

## 9. Anti-padrões / pegadinhas Tier 0 (Cliente)

- ⛔ **`App\Contact` NÃO tem global scope** — toda query DEVE `where('business_id', $business_id)` explícito (footgun). Só `ContactAddress` tem `HasBusinessScope`. Ao criar query/endpoint novo de contato, **nunca** confiar em scope automático ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- ⛔ **Logar CPF/CNPJ** — o activity-log usa `logOnly` whitelist que **exclui** `tax_number_1`; nunca adicionar PII fiscal ao log (`ContactPiiLogsActivityTest` quebra). Mascarar via `maskTaxNumber` **antes** do Inertia props.
- ⛔ **Mexer em `credit_limit`/extrato/saldo sem Regra Mestre** — qualquer alteração que toque valor (limite, saldo devedor, aging, baixa) exige **dupla confirmação + tabela antes→depois + aprovação** ([proibicoes](../../proibicoes.md) §Regra Mestre de valor/estoque).
- ⛔ **Commitar valores BRL** (saldo, limite, extrato) em `memory/`/PR/commit — redact `[redacted Tier 0]` (só Wagner/Eliana veem valor).
- ⛔ **Smoke em `business_id=4`** (ROTA LIVRE prod) — usar biz=1 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)).
- ⛔ **Re-adicionar counter numérico dentro da tab** do Index — removido de propósito 2026-05-25 (duplicava o KPI strip); anti-regressão catalogada em [clientes-gap.md](clientes-gap.md).
- ⛔ **Alterar `format_date`** de biz=4 — shift +3h preservado intencionalmente ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)).
- ⛔ **Absorver funil CRM (deals/kanban) na tela Cliente** — é território de `Modules/Crm` em depreciação ([ADR 0301](../../decisions/0301-separar-cliente-deprecar-crm-pipeline.md)); Cliente é cadastro, não pipeline.
- ⛔ **`Inertia::render` com prop cara sem `defer`** — ver skill `inertia-defer-default` (drawer/abas usam defer; manter).

## 10. Decisão / Nota / Recomendação

### Nota de capacidade
**65/100** — entre Bling (~60) e Conta Azul (~66), abaixo de Omie (~72). Honesto: o cadastro é **melhor que o mercado BR nos eixos de PII/consentimento/teclado** (C03/C09/C14) e **empata no mid-tier**, mas fica atrás nos eixos operacionais de cadastro (crédito bloqueante, dedupe de import) e **descoberto** nos direitos do titular — a lacuna mais grave para um módulo cuja razão de existir é guardar PII.

### Causa principal do gap (1 frase)
**O cadastro é maduro em coleta e exibição de PII, mas carece de (a) direito ao esquecimento/portabilidade do titular, (b) isolamento multi-tenant provado (hoje manual, sem teste no Contact) e (c) enforcement de crédito na venda — sendo que a máquina de LGPD pra fechar (a) já existe e só falta apontar pro `contacts`.**

### Top 3 P0 pra fechar (executável)
1. **G-01 — Anonimização fiscal-aware do titular** (estender `DsrService` pra `contacts`): fecha a obrigação LGPD Art. 18 **e** abre o diferencial de mercado mais forte disponível (nenhum concorrente faz erasure preservando NF). Esforço M, ROI P0. **Comece por aqui.**
2. **G-02 — Teste cross-tenant no `Contact` pai + avaliar global scope**: alinha o código ao claim da doc; a barata rede de segurança Tier 0 que hoje não existe no cadastro. Esforço S.
3. **G-03 — Limite de crédito com bloqueio/aviso na venda**: ativa um campo hoje decorativo; casa com o financeiro da Eliana. Esforço M — ⚠️ toca valor (Regra Mestre).

### Próximos passos da onda (T6 — encaixar, não duplicar)
- **Passo 2:** `/comparativo Cliente` → `CAPTERRA-INVENTARIO.md` (buckets ✅🟡❌) + batch `tasks-create` (aguarda OK [W]) apendendo US-CRM-079+ ao [SPEC.md](SPEC.md).
- **Passo 3:** régua por tela (screen-grade **+ `casos_coverage`**) nas 7 telas Cliente.
- **Passo 4:** catraca + sentinela (baseline screen-grade + casos + exposição Tier-0).

### Referências
- [BRIEFING.md](BRIEFING.md) · [SPEC.md](SPEC.md) (US-CRM-063..078) · [ALINHAMENTO-cliente-2026-06-22.md](audits/ALINHAMENTO-cliente-2026-06-22.md) · [clientes-gap.md](clientes-gap.md) (mockups Cowork)
- Screen-grade board: [SCREEN-GRADE-BOARD-2026-05-30.md](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md) (Cliente 77 média · Show 86 · Index 84)
- Ficha-modelo (calibração): [Sells/CAPTERRA-FICHA.md](../Sells/CAPTERRA-FICHA.md) (nota 60)
- Session log: [2026-07-03-capterra-cliente.md](../../sessions/2026-07-03-capterra-cliente.md)
- Plano da onda: [template-onda-modulo.md](../_Governanca/programa-ondas/template-onda-modulo.md) · [PLANO-MESTRE.md](../_Governanca/programa-ondas/PLANO-MESTRE.md)

---

**Próxima revisão:** 2026-10-03 (trimestre) ou quando G-01 (anonimização fiscal-aware do titular) fechar.
**Onda:** standalone Cliente (adversário concorrente — programa de ondas), Passo 1.
