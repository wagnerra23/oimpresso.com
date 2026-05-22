---
slug: 0185-drawer-760-canon-entidades-cadastrais
number: 185
title: "Drawer 760 escala pra entidades cadastrais do projeto — substitui Edit.tsx/Create.tsx separados (amends ADR 0179)"
type: adr
status: proposed
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-22"
module: core
quarter: 2026-Q2
tags: [paradigma, drawer-760, cadastral, mwart, cowork-blueprint, persona-larissa, multi-tenant, autosave, ADR-0179-cliente-drawer, ADR-0182-pageheader, ADR-0093-tier0]
supersedes: []
supersedes_partially: []
amends:
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0110-tipografia-canon-h1-subtitle"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0127-spatie-activitylog-lgpd-art-18"
  - "0149-mwart-screen-pattern-reuse-cowork"
  - "0177-mwart-excecao-cliente-show-wave-paralela"
  - "0180-sidebar-v3-5-grupos-ghosts-header"
  - "0182-pageheadertabs-canon-pattern-telas"
charter_impact:
  - "Pages/<Modulo>/Index.charter.md de todas as 7 entidades cadastrais elegíveis (OficinaAuto/ServiceOrders, OficinaAuto/Vehicles, Produto, RecurringBilling/Planos, Repair/DeviceModels, TransactionPayment, Essentials/Knowledge?) — bump pra `drawer_pattern: 760px-lateral`"
  - "Pages/<Modulo>/Edit.charter.md + Create.charter.md → status: superseded por Index.charter.md drawer"
pii: false
review_triggers:
  - "Larissa @ ROTA LIVRE biz=4 reportar drawer 760 não cabe em monitor 1280×1024 (regressão visual) → revisitar largura"
  - "Sub-agent aplicar pattern em entidade que NÃO é cadastral (workflow/POS) → ADR errou matriz, ajustar"
  - "Power-user (ADR 0105 sinal qualificado) pedir botão Salvar explícito em vez de autosave on blur → reabrir Q decisão autosave"
  - "Custo Brain B (Tab IA) escalar acima de baseline em N entidades → adicionar gate quota global"
  - "Forma híbrida emergir (drawer + side panel + modal) sem disciplina → CI gate `drawer:health` futuro alerta"
---

# ADR 0185 — Drawer 760 escala pra entidades cadastrais do projeto

## Contexto

[ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) (aceita 2026-05-21) decidiu que `Pages/Cliente/Show.tsx` full-page é substituído por **drawer lateral 760px** dentro de `Pages/Cliente/Index.tsx`, com **8 tabs cadastrais** (Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria) + **autosave on blur** (debounce 800ms) + redirect 302 das URLs `/cliente/{id}` legacy. PRs #1339-#1358 entregaram Waves A-G+Z-2 em ~3h elapsed, validado em prod biz=1 com nota 95% paridade Cowork score KB-9.75 9,4/10.

Wagner 2026-05-22 (continuação Wave Ponto PR #1381): a discussão sobre pattern Edit/Create dos demais módulos abriu a pergunta "o padrão seria o cliente?". Resposta Wagner: **sim — Drawer 760 escala pra projeto inteiro** quando a tela representa entidade cadastral (Cliente, Fornecedor, Produto, Funcionário, Veículo, Ordem de Serviço, Plano de Assinatura, Equipamento). Pages `Edit.tsx` e `Create.tsx` separadas (legacy MWART V1) deixam de ser pattern canônico pra essas entidades.

A skill [pageheader-canon](../../.claude/skills/pageheader-canon/SKILL.md) Fase 4-bis (atualizada PR #1381 commit `e62b8c2e9`) já documenta a **matriz de 3 padrões por tipo de tela** (DRAWER 760 / FOCO V2 / FOCO V1), mas indica explicitamente que **sub-agent NÃO INICIA refator sem ADR formal aceito**. Esta ADR formaliza a decisão.

Persona alvo continua **Larissa biz=4 ROTA LIVRE** (1280×1024, não-técnica). Validação geométrica em [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) §Consequências: `drawer 760 + sidebar AppShellV2 240 + main padding ≈ 1024px`. Cabe.

## Decisão

**Toda entidade cadastral do oimpresso DEVE migrar `Pages/<Modulo>/Edit.tsx` + `Create.tsx` (legacy MWART V1) pro padrão Drawer 760 lateral dentro de `Pages/<Modulo>/Index.tsx`, espelhando estrutura canônica do Cliente (ADR 0179).**

### Matriz de elegibilidade — 3 patterns canon por tipo de tela

| Tipo de tela | Pattern canon | Critério de elegibilidade |
|---|---|---|
| **Entidade cadastral** | **DRAWER 760** lateral + N tabs + autosave | ≥5 campos relacionados + alta freq edição + possível IA/Auditoria/categorização |
| **Workflow transacional** | **PAGE FOCO V2** (form fat, sem SubNav) | Form >20 campos OU layout >1024px OU upload+progress OU wizard multi-step |
| **Cadastro técnico simples** | **PAGE FOCO V1** (Voltar inline) | Cadastro ≤10 campos + baixa freq edição + sem IA/Auditoria |

### Backlog 7 entidades cadastrais elegíveis (escopo desta ADR)

| Módulo | Tela | Pattern atual | Pattern canon (esta ADR) |
|---|---|---|---|
| **Cliente** | Index + Show + Create + Edit | ✅ DRAWER 760 (Wave A-G PR #1339-#1358) | manter |
| **OficinaAuto/ServiceOrders** | Create + Edit | PageHeader simples | DRAWER 760 — 7 tabs (Identif/Veículo/Cliente/Itens/Status/IA/Auditoria) |
| **OficinaAuto/Vehicles** | Create + Edit | PageHeader simples | DRAWER 760 — 6 tabs (Identif/Proprietário/Técnico/Histórico/IA/Auditoria) |
| **Produto** | Create + Edit | sem header padrão | DRAWER 760 — 8 tabs (Identif/Estoque/Preços/Variações/Fiscal/Imagens/IA/Auditoria) |
| **RecurringBilling/Planos** | Create + Edit | sem header padrão | DRAWER 760 — 5 tabs (Identif/Itens/Preço/Ciclo/Auditoria) |
| **Repair/DeviceModels** | Create + Edit | PageHeader simples | DRAWER 760 — 4 tabs (Identif/Defeitos/Peças/Auditoria) |
| **TransactionPayment** | Edit | sem header padrão | DRAWER 760 — 4 tabs (Identif/Origem/Comprovante/Auditoria) |

**Fora de escopo desta ADR** (continuam pattern atual — não migrar pra drawer):
- Workflow transacional (Sells/Create POS · Purchase · NFSe/Emitir · Financeiro/Unificado/Novo · Repair/JobSheet · StockAdjustment · StockTransfer) → **FOCO V2** mantido
- Cadastro técnico simples (Ponto/Escalas/Form · Ponto/Importacoes/Create · Ponto/Intercorrencias/Create · Ponto/Colaboradores/Edit · ads/Skills · Essentials/Todo) → **FOCO V1** mantido (PR #1381 já entregue)
- Essentials/Knowledge — **decisão pendente Wagner** (KB é cadastral ou workflow?). Default: avaliar caso a caso na wave Pessoas.

### Estrutura obrigatória DRAWER 760 — replica ADR 0179

Sub-agent que aplica esta ADR num módulo DEVE seguir estrutura espelhada do Cliente:

```
Pages/<Modulo>/
├── Index.tsx                       # Listagem + <Drawer{Modulo}> lateral 760px (UMA Page só)
├── Index.charter.md                # status: live · drawer_pattern: 760px-lateral
├── _drawer/
│   ├── IdentificacaoTab.tsx        # Tab 1 — campos identidade
│   ├── {Tab2..N}.tsx               # Tabs específicas por entidade (5-8 tabs típico)
│   ├── IATab.tsx                   # Tab IA — Brain B Jana (resumo/segmento/próxima ação)
│   └── AuditoriaTab.tsx            # Tab Auditoria — Spatie ActivityLog forSubject(Entity)
└── _shared/
    ├── <Modulo>SubNav.tsx          # SubNav do Index header (modo NAV ADR 0182)
    └── <Modulo>PrimaryButton.tsx   # PrimaryButton "+ Novo X" (hue do grupo v3)
```

**Backend obrigatório por entidade:**
- N endpoints PATCH autosave (`/{modulo}/{id}/{tab}`) com debounce 800ms + optimistic UI + rollback 4xx/5xx
- 2 redirects 302: `/{modulo}/{id}` (Show legacy) e `/{modulo}/{id}/edit` → `/{modulo}?contact_id={id}&tab=identificacao`
- 2 endpoints lookup BR (CEP via ViaCEP + CNPJ via BrasilAPI) com cache Redis 90d/30d via `BrLookupService`
- 3-4 endpoints IA (`/ia/resumo`, `/ia/segmento`, `/ia/proxima-acao`) usando `LaravelAiSdkDriver` — Tab IA default ON sem gate inicial
- 1 endpoint Auditoria pull Spatie ActivityLog `forSubject(Entity)` — reuso ADR 0127, zero código novo de audit log
- Migration aditiva ALTER TABLE (NULL columns reversíveis) — **NÃO** criar tabela paralela; expande tabela core UPOS

**Frontend obrigatório:**
- AppShellV2 sidebar 240 + listagem 260 + drawer 760 = 1260px (cabe Larissa 1280×1024 sem scroll horizontal)
- Header `os-page-h` segue [ADR 0182](0182-pageheadertabs-canon-pattern-telas.md) (3 zonas: título / SubNav / PrimaryButton OU Voltar quando drawer aberto)
- Autosave on blur (sem botão "Salvar") + toast confirmação
- Deeplink `/{modulo}/{id}` → redirect 302 → `/{modulo}?contact_id={id}&tab=identificacao` (URL compartilhável)
- Pest charter test obrigatório em viewport 1280×1024 sem scroll horizontal

### Tabs canônicas reutilizáveis (template DRY)

Esta ADR oficializa **4 tabs reutilizáveis** que TODA entidade cadastral DEVE ter (template = Cliente):

| Tab canon | Conteúdo | Fonte | Reuso |
|---|---|---|---|
| **IdentificacaoTab** | CPF/CNPJ + Nome + Doc principal + Status | per-entidade | template Cliente/_drawer/IdentificacaoTab.tsx |
| **IATab** | 3-4 cards Brain B (Resumo / Segmento / Próxima Ação / determinístico) | `Modules/Jana` | template Cliente/_drawer/IATab.tsx (reusa endpoints adaptados) |
| **AuditoriaTab** | Timeline LGPD Art 18 — eventos Spatie ActivityLog | `Modules/Auditoria/Services/AuditEntryService` | template Cliente/_drawer/AuditoriaTab.tsx (zero código novo) |
| **EnderecoTab** | CEP + lookup ViaCEP + 5 campos endereço | `Modules/Crm/Services/BrLookupService` | template Cliente/_drawer/EnderecoTab.tsx (reusa Service) |

Tabs específicas por entidade (5-8 típico): cada sub-agent identifica conforme Cowork blueprint do módulo se houver, ou propõe estrutura no wagner-understand pré-implementação.

## Justificativa

### Por que escalar Drawer 760 em vez de manter FOCO mode pra todas Edit/Create

1. **1 paradigma único por persona** (Larissa decora 1×, transfere entre módulos): com 2 paradigmas (Cliente drawer + outros Page separada), Larissa re-aprende em cada módulo. Custo cognitivo alto, especialmente persona não-técnica.

2. **ROI proven empiricamente**: ADR 0179 Wave A-G entregou Cliente em ~3h elapsed (fator 10x IA-pair + 7 sub-agents paralelos). Score paridade Cowork 28→95% prod biz=1 validado. Pattern é executável em escala.

3. **Reuso ADR 0149 (pattern reuse Cowork)**: tabs `IdentificacaoTab`/`IATab`/`AuditoriaTab`/`EnderecoTab` de Cliente são templates copy-paste pra novas entidades. ROI de replicação ~90% (90h cliente → ~10h adaptação por nova entidade).

4. **Autosave on blur elimina UX-debt**: botão "Salvar" gera ansiedade (será que salvou?) e edge cases (form duplicado, conflito de concorrência). Autosave debounce 800ms + toast = padrão moderno Notion/Linear/Stripe 2026.

5. **LGPD Art 18 cumprido em 100% das entidades**: Tab Auditoria reusando Spatie ActivityLog ([ADR 0127](0127-spatie-activitylog-lgpd-art-18.md)) — sem código novo de audit, compliance automático.

6. **Tab IA democratizada**: cada entidade cadastral ganha 3-4 cards Brain B Jana automaticamente. Wagner pode regredir pra gate quota se custo escalar (review trigger registrado).

7. **Sub-tabs aninhadas têm precedente**: tab "OSs" do Cliente comporta 8 sub-tabs operacionais (Ledger/Sales/Payments/Documents/Activities/Pessoas/Subscriptions/Rewards) em 640px content. Provou que drawer 760 NÃO limita complexidade — só impõe disciplina de organização.

### Por que NÃO escalar pra workflow/POS/cadastro técnico

- **Sells/Create POS** (workflow): form fat com produtos+cliente+pagamento >30 campos + atalhos teclado + visão de processo. Não cabe em 760. Mantém pattern FOCO V2.
- **NFSe/Emitir** (workflow fiscal): wizard fiscal SEFAZ-SP com validação realtime de impostos. Drawer apertaria.
- **Ponto/Escalas/Form** (cadastro técnico ≤8 campos): drawer + 5 tabs reutilizáveis = overhead desnecessário pra 1 form simples. FOCO V1 é apropriado.

## Alternativas consideradas

- **(A) Drawer 760 pra TODAS as Edit/Create (32 telas)** — descartada. Sells/POS e NFSe/Emitir não cabem. Forçaria gambiarra (sub-tabs aninhadas excessivas) ou regressão UX (form apertado).

- **(B) Cliente exceção, demais 31 telas FOCO mode** — Wagner explicitamente rejeitou. Mantém 2 paradigmas competindo no projeto, perde ROI da consolidação. Larissa decora 2× por entidade.

- **(C) Híbrido por tipo de tela — Drawer 760 (entidades cadastrais) + FOCO V2 (workflow) + FOCO V1 (técnico)** ← **ESCOLHIDA**. 1 paradigma por *tipo* de tela. Matriz objetiva de elegibilidade. ROI máximo (90% reuso entre entidades cadastrais). Riscos contornados (workflow fat e técnico simples mantém pattern certo).

- **(D) Sidebar do drawer expansível 760→1200px on-demand** — descartada. Quebra paradigma "drawer lateral fixo" (ADR 0179 §Q1 sunset zero). Adiciona estado UI (expandido/colapsado) que aumenta cognitive load.

## Consequências

### Positivas

- **Paridade visual entre 7 entidades cadastrais** — Cliente, OficinaAuto/ServiceOrders+Vehicles, Produto, RecurringBilling/Planos, Repair/DeviceModels, TransactionPayment. Larissa decora 1× e transfere conhecimento.
- **LGPD Art 18 automático em 7 entidades** via Tab Auditoria reusando [ADR 0127](0127-spatie-activitylog-lgpd-art-18.md).
- **Tab IA democratizada** — 7 entidades ganham 3-4 cards Brain B Jana automaticamente. Maior cobertura uso IA Brain B → mais sinal pra Wagner calibrar custo.
- **ROI replicação ~90%** — templates `IdentificacaoTab`/`IATab`/`AuditoriaTab`/`EnderecoTab` + `BrLookupService` copy-paste entre módulos.
- **Multi-tenant Tier 0 [ADR 0093](0093-multi-tenant-isolation-tier-0.md) reforçado** — N endpoints novos por entidade × 7 entidades = ~50 endpoints validados via `business_id` global scope.
- **CI gate futuro viável** — gate pode validar presença de `<Drawer{Modulo}>` em todo `Pages/<Modulo>/Index.tsx` das entidades cadastrais.
- **Deeplink preservado** — redirect 302 `/{modulo}/{id}` mantém URL compartilhável (Maiara manda link no WhatsApp pra cliente abrir registro).

### Negativas / riscos

- **Esforço total 70-90h elapsed (7-9 dias úteis Wagner solo)** — ~10-15h por entidade × 6 entidades novas (Cliente já feito). Mitigável paralelizando 3 sub-agents (Waves OPERAR + VENDER + FINANÇAS).

- **Sub-tabs aninhadas em entidades com histórico fat (Produto/StockHistory, ServiceOrders/Atividades)** — pode apertar visual em 640px content. Mitigação Wave: agent escolhe dropdown "Ver: [tab ▼]" se layout pills não couber (ADR 0179 §Risco 1 já catalogou).

- **Custo Brain B Tab IA escala linearmente** — 7 entidades × 3-4 endpoints IA × N usuários/dia. Telemetria `Modules/Jana/Services/CustosService::log()` desde dia 1; Wagner regride pra gate se custo/dia exceder baseline.

- **ViaCEP/BrasilAPI rate limit risco em prod** — Larissa biz=4 + N entidades cadastrais aumenta hits. Cache Redis 90d/30d obrigatório no `BrLookupService` antes de cada Wave.

- **Edit.tsx/Create.tsx deleta zero-sunset por entidade** — rollback caro se bug aparecer pós-merge. Mitigação per-entidade: gating `MWART_<MODULO>_INDEX=true` canary biz=1 primeiro; rotas legacy `/contacts/{id}` Blade dual-render via `config('mwart.<modulo>_show.enabled')` permanecem fallback emergencial.

- **Charters explodem** — 7 entidades × 2 charters legacy (Edit + Create) precisam `status: superseded`. Wave precisa atualizar charter junto.

- **Sub-agent pode aplicar pattern em entidade ELEGÍVEL ERRADA** — ex tentar Drawer 760 em workflow transacional. Mitigação: matriz de elegibilidade explicita critérios objetivos (≥5 campos relacionados + alta freq + IA/Auditoria).

### Riscos mitigados pela ADR 0179 já validada

- Larissa 1280×1024 cabe — geometria validada em prod biz=1 (PR #1358 Z-2 smoke).
- Pattern visual aprovado por Wagner em sessão "aprovado merge" 2026-05-21 16:40.
- Spatie ActivityLog v4.8 instalado, zero código novo audit log.
- Spec já provada — Cliente Wave A-G entregou em 3h elapsed (fator 10x IA-pair).

## Plano de execução

### Wave VENDER (entidades cadastrais grupo vender hue 60)
- **Produto/Create + Edit** → Drawer 760 com 8 tabs (Identif/Estoque/Preços/Variações/Fiscal/Imagens/IA/Auditoria)
- Estimate: ~15h IA-pair (Produto tem mais complexidade — variações, fiscal NCM/CFOP, imagens)
- Owner: sub-agent paralelo

### Wave OPERAR (entidades cadastrais grupo operar hue 350)
- **OficinaAuto/ServiceOrders/Create + Edit** → Drawer 760 com 7 tabs
- **OficinaAuto/Vehicles/Create + Edit** → Drawer 760 com 6 tabs
- **Repair/DeviceModels/Create + Edit** → Drawer 760 com 4 tabs (cadastro técnico-cadastral híbrido)
- Estimate: ~25h IA-pair (3 entidades paralelas, base já em prod)
- Owner: sub-agent paralelo

### Wave FINANÇAS (entidades cadastrais grupo financas hue 145)
- **RecurringBilling/Planos/Create + Edit** → Drawer 760 com 5 tabs
- **TransactionPayment/Edit** → Drawer 760 com 4 tabs
- Estimate: ~12h IA-pair
- Owner: sub-agent paralelo

### Wave PESSOAS (avaliar elegibilidade caso a caso)
- **Essentials/Knowledge** — Wagner decide se KB é cadastral (drawer) ou workflow (FOCO)
- Estimate: ~8h IA-pair (depende decisão Wagner)

### Sequência sugerida

1. **F0 — Esta ADR aceita** (1h Wagner review + push status `accepted`)
2. **F1 — Wave piloto Produto** (single entity, agent paralelo, smoke MCP prod biz=1) — ~15h
3. **F2 — Wave OPERAR + FINANÇAS paralelas** após F1 validado — ~25h+12h = 37h em ~1.5 dia elapsed
4. **F3 — Wave PESSOAS decisão Wagner Knowledge** — ~8h
5. **F4 — CI gate `drawer:health`** — valida `<Drawer{Modulo}>` em `Pages/<Modulo>/Index.tsx` das entidades elegíveis — warn-only inicial, hard após backfill — ~3h

**Total estimado: 75-100h IA-pair / 4-6 dias elapsed paralelizado.**

## Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- N endpoints novos por entidade × 7 entidades ≈ **50+ endpoints PATCH/lookup/IA** — TODOS com `business_id` global scope obrigatório
- Pest cross-tenant biz=1 vs biz=99 por entidade — guard CI obrigatório
- ViaCEP/BrasilAPI cache Redis com namespace por `business_id` (evita vazamento Tier 0)
- Tab Auditoria filtra `Activity::where('business_id', auth()->user()->business_id)` — Spatie suporta nativamente
- Tab IA telemetria `Modules/Jana/Services/CustosService` agrega por `business_id`

## Métricas de sucesso (loop fechado — [Constituição v2 princípio 4](0094-constituicao-v2-7-camadas-8-principios.md))

| Métrica | Baseline (pré-ADR) | Meta pós-implementação |
|---|---|---|
| Entidades cadastrais com Drawer 760 | 1/7 (Cliente) | 7/7 (gate CI valida) |
| Tempo aprendizagem inter-entidade (smoke usuário novo) | medir baseline | -60% após Wave 3 |
| LGPD Art 18 cobertura (Tab Auditoria presente) | 14% (1/7) | 100% |
| Tab IA cobertura (Brain B endpoints expostos) | 14% (1/7) | 100% |
| Tickets suporte "como edito X" cross-entity | medir baseline 30d | -50% em 90d |
| Custo Brain B/dia/biz (Tab IA) | baseline pós-Cliente | acompanhar +N entidades — gate quota se >$5/dia/biz |
| Hick's Law score qualitative (audit) | 7/10 (Cliente isolado) | ≥9/10 (paridade entre 7) |

## Referências

- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Cliente Drawer 760 canon (esta ADR amends/escala)
- [ADR 0149](0149-mwart-screen-pattern-reuse-cowork.md) — Pattern reuse Cowork blueprint
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant IRREVOGÁVEL
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- [ADR 0110](0110-tipografia-canon-h1-subtitle.md) — Tipografia canon h1/subtitle
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — Loop Cowork ↔ Claude Code formalizado
- [ADR 0127](0127-spatie-activitylog-lgpd-art-18.md) — Spatie ActivityLog LGPD Art 18
- [ADR 0180](0180-sidebar-v3-5-grupos-ghosts-header.md) — Sidebar v3 5 grupos
- [ADR 0182](0182-pageheadertabs-canon-pattern-telas.md) — PageHeader 3 zonas canon
- Skill [`pageheader-canon`](../../.claude/skills/pageheader-canon/SKILL.md) Fase 4-bis — matriz 3 patterns por tipo de tela
- PRs Cliente Drawer 760 validados prod: #1339/#1342/#1344/#1347/#1348/#1349/#1351/#1352/#1353/#1355/#1356/#1357/#1358
- PR #1381 Wave Ponto (FOCO V1 — fora do escopo desta ADR, mantém pattern atual)
- Wagner decisão 2026-05-22 "Cliente Drawer 760 pra TUDO (escalar ADR 0179)" + "está sendo migrado para novo padrão mais bonito" — sessão `frosty-greider-83ab2f`

## Como aceitar esta ADR

1. **Wagner revisa** este draft completo
2. **Wagner aprova** OU edita matriz de elegibilidade (7 entidades) OU rejeita patterns específicos
3. **Wagner muda `status: proposed` → `status: accepted`** + adiciona `accepted_at: "YYYY-MM-DD"` + `accepted_via: "comando exato Wagner"`
4. **PR separado mergeia esta ADR** (commit-discipline ≤300 LOC já cumpre)
5. **Sub-agents subsequentes** podem iniciar Wave Produto (F1) seguindo template Cliente
