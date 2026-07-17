---
slug: 0185-drawer-760-canon-entidades-cadastrais
number: 185
title: "Drawer 760 escala pra entidades cadastrais do projeto — substitui Edit.tsx/Create.tsx separados (amends ADR 0179)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-22"
accepted_at: "2026-05-22"
accepted_via: "Wagner aprovou em sessão `frosty-greider-83ab2f` 2026-05-22 — comando exato: 'eu gostei pode salvar'"
module: core
quarter: 2026-Q2
tags: [paradigma, drawer-760, cadastral, mwart, cowork-blueprint, persona-larissa, multi-tenant, autosave, ADR-0179-cliente-drawer, ADR-0182-pageheader, ADR-0093-tier0]
supersedes: []
supersedes_partially: []
amends:
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0149-mwart-screen-pattern-reuse-cowork
  - 0177-mwart-excecao-cliente-show-wave-paralela
  - 0180-sidebar-v3-5-grupos-ghosts-header
  - 0182-pageheadertabs-canon-pattern-telas
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

### Q5 — Largura do drawer pode variar pra encaixar informação?

**Não. 760px é fixo (canon imutável).** Quando tab tem muito conteúdo, usar 1 dos 3 mecanismos canônicos pra encaixar:

| Mecanismo | Quando aplicar | Exemplo de referência |
|---|---|---|
| **Sub-tabs aninhadas** (vertical pills 120px + content 640px) | Tab com 4+ visões operacionais distintas | Cliente tab "OSs" com Ledger/Sales/Payments/Docs/Activities/Pessoas/Subscriptions/Rewards |
| **Scroll horizontal interno** | Tabela com >5 colunas dentro de uma tab | Produto tab "Variações" com SKU/cor/tamanho/preço/estoque/foto |
| **Cards colapsáveis** (`<Accordion>` shadcn) | Seções long-form opcionais ou raramente usadas | Produto tab "Fiscal" com NCM/CFOP/CST/CSOSN/Regime colapsado por default |

**Razão pragmática:**
- Paridade visual entre 7 entidades quebra se largura varia — Larissa decora 1× e re-aprende em cada
- Toggle "expandir" adiciona estado UI persistente (cognitive load + edge cases de sync entre devices)
- **Se realmente não cabe em 760**, é sinal de **mismatch de pattern** → entidade é workflow, não cadastral → migra pra **FOCO V2** (matriz de elegibilidade já cobre)
- Sub-tabs aninhadas têm precedente validado (Cliente tab "OSs" comporta 8 sub-tabs em 640px content)

**Anti-pattern Tier 0 nesta ADR:**
- ❌ Drawer expansível 760→1040 on-demand
- ❌ Drawer largura per-entidade (Produto 900, Cliente 760)
- ❌ Largura responsiva por viewport (mobile 100% / desktop 760)
- ❌ Modal/Sheet centralizado em vez de drawer lateral

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

## Preocupações adicionais — armadilhas catalogadas pra sub-agents

Sub-agent que pegar qualquer Wave DEVE conferir estas armadilhas ANTES de codar:

### Técnicas

| Armadilha | Fix canon |
|---|---|
| **Conflito de concorrência autosave** — Maiara + Wagner editam mesmo registro simultâneo | Optimistic locking via `updated_at` check no PATCH; HTTP 409 retorna toast "Outro usuário editou — recarregue"; opcional merge UI |
| **Cache stale na lista após autosave** — usuário salva, fecha drawer, nome não atualiza | Inertia partial reload `only:['<modulo>s']` no `onBlur` save success |
| **Migration aditiva acumula colunas NULL na tabela core UPOS** — 7 entidades × 5-8 cols na mesma tabela `transactions`/`contacts` | Auditar tamanho tabela antes Wave; se >100 cols ou >1MB row size, considerar tabela paralela `<entidade>_meta` JSON |
| **N+1 queries Tab IA** — cada card chama endpoint Brain B; 4 cards × N entidades em lista = latência ruim | Tab IA carrega apenas quando drawer aberto + tab IA ativa (`Inertia::defer` por tab, lazy) |
| **Browser back button quebra drawer** — Inertia router default volta página inteira | `window.history.replaceState` + custom handler `popstate` fecha drawer antes de navegação |
| **Inertia::defer + autosave race** — defer carrega dados depois do mount; autosave dispara antes campos default | Bloquear `onBlur` save até deferred props resolverem (flag `isHydrated` em state) |
| **Mobile <1024px** — pattern não cobre | Fora de escopo desta ADR; futuro: drawer vira modal fullscreen em viewport <1024 |
| **Print/Export PDF** — drawer 760 não imprime bem | Rota `/print` separada por entidade (gera Blade legacy ou Browsershot PDF); botão no header drawer |

### UX

| Armadilha | Fix canon |
|---|---|
| **Como abre drawer?** | Clique linha tabela = abre drawer; coluna "ação" final tem ícone `…`; atalhos `J/K` navegam linhas; `Enter` abre; `Esc` fecha |
| **Filtros lista + drawer aberto** | Filtros persistem (localStorage namespace `oimpresso.<modulo>.filtros.*`); drawer abre POR CIMA da lista, não substitui |
| **Notificação de mudança em background** | Polling 30s ou Centrifugo channel `<modulo>:<biz_id>:<entity_id>:updated` → alert "Outro usuário modificou X campo — atualizar?" |
| **WCAG accessibility** | Drawer foca 1º campo input ao abrir; `Esc` fecha; `Tab` move; trap focus dentro drawer; aria-labels nas tabs |
| **Modo dark/light** | Tabs usam tokens `oklch(--bg-card)` e `oklch(--fg-default)`; testar `data-theme="dark"` no smoke MCP |

### Negócio

| Armadilha | Fix canon |
|---|---|
| **Permissões granulares per-tab** — Maiara vê Auditoria mas não IA? | Permission Spatie por tab (ex `cliente.tab.ia.view` + `cliente.tab.auditoria.view`); `<{Modulo}Drawer>` renderiza tabs filtradas; Wagner decide gates per-business |
| **Auditoria autosave dispara N eventos** | Spatie `LogsActivity` config `logOnlyDirty=true` + `submitEmptyLogs=false`; batch por blur (1 evento por field group) |
| **LGPD esquecimento — registro anonimizado** | Drawer detecta `deleted_at` ou flag `anonymized_at` → renderiza modo readonly + tab Auditoria mostra eventos pré-anonimização com PII redacted |
| **Off-line — Larissa internet ruim Cabo Frio** | LocalStorage queue de PATCH não-confirmados; retry automático ao reconectar; toast "X mudanças pendentes" |

### Arquitetura

| Armadilha | Fix canon |
|---|---|
| **FSM no drawer (ServiceOrder/Order)** — botão "Avançar status" inline? | Header drawer mostra `<StatusBadge>` atual + dropdown "Avançar pra: [próximo stage ▼]" no canto direito; FSM transition disparada por endpoint separado (não autosave); recarrega drawer após transition |
| **Workflow-cadastral híbrido** (ServiceOrder, Order) — é cadastral OU workflow? | Critério: se entidade tem ≥3 tabs **identitárias** (Identif/Contato/Endereço/Comercial) → DRAWER 760. Se tem grid de itens >50 linhas OU upload >5MB → FOCO V2. ServiceOrder: ✅ cadastral. NF-e Emitir: ✅ workflow. |
| **Dependências entre tabs** — Tab Endereço afeta cálculo frete Tab Comercial | Autosave PATCH retorna `recalculated_fields[]` no JSON; frontend atualiza state local + toast informativo "Frete recalculado: R$ X" |
| **Validação multi-campo** — CPF inválido bloqueia save mas outro campo já salvou | Validação 2 camadas: blur-level (FormRequest single field) + commit-level (FormRequest multi-field no `<Drawer>.commit()` opcional); inválido marca campo + bloqueia transition FSM |

### Performance

| Armadilha | Fix canon |
|---|---|
| **Drawer carrega 8 tabs upfront** — bundle JS grande | Lazy load tabs via `React.lazy()` + `Suspense fallback={<TabSkeleton/>}` |
| **Stream IA cards** — Brain B leva 2-5s, bloqueia blur de outro campo | Cards IA usam React 19 `<Suspense>` + AsyncBoundary; falha em 1 card não derruba os outros 3 |
| **Lista re-render quando drawer fecha** | Inertia partial reload `only:['<modulo>s']` em vez de full reload; localStorage persiste posição scroll |

### Ecosistema

| Armadilha | Fix canon |
|---|---|
| **Skill `como-integrar` ativa** quando agent quer reusar tab IA em entidade nova | Wave plan inclui ativação manual da skill; copy `Cliente/_drawer/IATab.tsx` + adaptar prompts Brain B |
| **Cypress/Playwright smoke** — pattern testes pra drawer multi-tab | RUNBOOK `RUNBOOK-drawer-smoke-MCP.md` canon (template = `RUNBOOK-Cliente-drawer-760px.md`) por entidade |
| **Browser MCP smoke** — validar drawer abre/fecha/8 tabs/autosave | Script JS canon na skill `pageheader-canon` Fase 5 expandido pra incluir checks D1-D6 (drawer abre / 8 tabs / autosave / Auditoria / IA cards / Esc fecha) |
| **PR template** — checklist específico Drawer 760 | `.github/pull_request_template.md` ganha section "## Drawer 760 (ADR 0185)" com 10 checks obrigatórios |

## Plano de execução

### Estimates v2 — refinados conforme Wagner pergunta 2026-05-22 "e o trabalho que vai dar?"

Estimate v1 (15h Produto / 12h ServiceOrders) era otimista — não considerava armadilhas catalogadas (concorrência autosave, FSM no drawer, workflow-cadastral híbrido, validação multi-campo, sub-tabs aninhadas). Refinamento +15-30% por entidade:

| Wave | Entidade | Estimate v1 | **Estimate v2 realista** | Razão do ajuste |
|---|---|---|---|---|
| **VENDER** | Produto | 15h | **20-25h** | 8 tabs fiscais complexas (NCM/CFOP/CST/variações/imagens upload) |
| **OPERAR** | OficinaAuto/ServiceOrders | 12h | **22-28h** | FSM 13 stages + itens grid + workflow-cadastral híbrido |
| **OPERAR** | OficinaAuto/Vehicles | 8h | **10-12h** | 6 tabs simples (Identif/Proprietário/Técnico/Histórico/IA/Audit) |
| **OPERAR** | Repair/DeviceModels | 6h | **8-10h** | 4 tabs técnicas |
| **FINANÇAS** | RecurringBilling/Planos | 10h | **12-15h** | Ciclo cobrança + integração Inter |
| **FINANÇAS** | TransactionPayment | 6h | **8-10h** | 4 tabs simples |
| **F4 CI gate** | `drawer:health` | 3h | **4-6h** | Inclui PR template + Pest cobertura |
| **Total IA-pair** | 6 entidades + gate | 75-100h | **85-110h** | +15% safety |

**Plus relógio real:**
- Wagner aprovar cada wave (sleep, smoke prod, decisões): ~2-4h × 4 waves = **8-16h relógio**
- Smoke browser MCP por wave (skill `pageheader-canon` Fase 5 + RUNBOOK-drawer-smoke): 30min × 4 = **2h**
- Re-trabalho pós-smoke (bugs descobertos em prod biz=1 canary): **10-15% safety buffer**

**Estimate consolidado:** **85-110h IA-pair + ~4-6 dias relógio real paralelizado + ~1 dia Wagner aprovações cumulativo.**

### Wave VENDER (entidades cadastrais grupo vender hue 60)
- **Produto/Create + Edit** → Drawer 760 com 8 tabs (Identif/Estoque/Preços/Variações/Fiscal/Imagens/IA/Auditoria)
- Estimate v2: **20-25h IA-pair** (variações + fiscal NCM/CFOP/CST + imagens upload)
- Owner: sub-agent paralelo
- Armadilhas críticas: scroll horizontal interno na tab Variações; cards colapsáveis na tab Fiscal; upload Browsershot na tab Imagens

### Wave OPERAR (entidades cadastrais grupo operar hue 350)
- **OficinaAuto/ServiceOrders/Create + Edit** → Drawer 760 com 7 tabs (incl FSM dropdown no header)
- **OficinaAuto/Vehicles/Create + Edit** → Drawer 760 com 6 tabs
- **Repair/DeviceModels/Create + Edit** → Drawer 760 com 4 tabs
- Estimate v2: **40-50h IA-pair** (3 entidades, base já em prod)
- Owner: sub-agent paralelo
- Armadilhas críticas ServiceOrders: FSM dropdown header + workflow-cadastral matriz (validar entidade É cadastral antes de codar) + dependências Tab Veículo → Tab Itens

### Wave FINANÇAS (entidades cadastrais grupo financas hue 145)
- **RecurringBilling/Planos/Create + Edit** → Drawer 760 com 5 tabs
- **TransactionPayment/Edit** → Drawer 760 com 4 tabs
- Estimate v2: **20-25h IA-pair**
- Owner: sub-agent paralelo
- Armadilhas críticas: integração Inter na tab Ciclo (RecurringBilling); cache stale lista Pagamentos pós-autosave

### Wave PESSOAS (avaliar elegibilidade caso a caso)
- **Essentials/Knowledge** — Wagner decide se KB é cadastral (drawer) ou workflow (FOCO)
- Estimate v2: **8-12h IA-pair** (depende decisão Wagner)

### Sequência sugerida

1. **F0 — Esta ADR aceita** ✅ aceita 2026-05-22 por Wagner ("eu gostei pode salvar")
2. **F0.5 — Wave H técnica OBRIGATÓRIA** (15h IA-pair, ANTES das replicações) — gaps P0 da auditoria 2026-05-22 (nota 76,4/100 → 88) aplicados no template Cliente. Ver seção "Wave H — Consolidação técnica" abaixo. SEM Wave H, gap escala 7× (replica defeito em 6 entidades novas).
3. **F1 — Wave piloto Produto** (single entity, agent paralelo, smoke MCP prod biz=1) — **20-25h IA-pair**
4. **F2 — Wave OPERAR + FINANÇAS paralelas** após F1 validado — **60-75h IA-pair** em ~2 dias elapsed
5. **F3 — Wave PESSOAS decisão Wagner Knowledge** — **8-12h**
6. **F4 — Wave I evolução pós-replicação** (14h IA-pair) — gaps P1 CI gate + Centrifugo realtime + offline queue (eleva nota 88 → 92)

### Wave H — Consolidação técnica (15h IA-pair, PRÉ-replicação)

**Decisão Wagner 2026-05-22:** após auditoria estado-da-arte (nota ponderada 76,4/100, faixa "bom"), inserir Wave H técnica ANTES das Waves de replicação. Aplicar 4 gaps P0 **NO TEMPLATE CLIENTE** primeiro — replicação posterior herda nota baseline 88 (mundo-classe).

**Justificativa estratégica:** sem Wave H, cada gap se multiplica por 7 entidades. Custo de retrofit pós-replicação >> custo de Wave H hoje. Auditoria dossier em [memory/sessions/2026-05-22-arte-drawer-760-vs-mundo.md](../sessions/2026-05-22-arte-drawer-760-vs-mundo.md).

| Gap | Effort | Onde aplicar | Sistema-ref |
|---|---|---|---|
| **#1 Optimistic locking** | 6h | `Modules/Crm/Http/Controllers/ClienteAutosaveController.php` — `updated_at` check; HTTP 409 + toast frontend "outro usuário editou — recarregue"; opcional merge UI. Extrair middleware genérico pra reuso. | HubSpot, Linear |
| **#2 React.lazy + Inertia::defer per-tab** | 4h | `resources/js/Pages/Cliente/Index.tsx` — wrap 8 tabs em `React.lazy()` + `<Suspense fallback={<TabSkeleton/>}/>`; Inertia::defer per-tab payload. Bundle drawer 118KB → ~30KB initial. | Linear, Stripe |
| **#3 popstate handler** | 2h | `resources/js/Pages/Cliente/Index.tsx` — `useEffect` listen `popstate` → fecha drawer antes navegar pra outra rota; preserve filtros lista via `replaceState`. | GitHub, Linear |
| **#5 Focus trap + Esc + autofocus** | 3h | `resources/js/Pages/Cliente/Index.tsx` — Radix Dialog/Sheet primitives ou `react-focus-lock`; foca 1ª input do drawer ao abrir; `Esc` fecha; `Tab`/`Shift+Tab` trap dentro drawer. | Linear, Radix |

**Output Wave H:**
- Template Cliente fica nota **76,4 → 88/100** (mundo-classe)
- Pattern reuso garantido nas 6 entidades subsequentes (Produto/ServiceOrders/Vehicles/DeviceModels/RecBilling-Planos/TransactionPayment)
- Pest charter test obrigatório por gap (4 testes novos no `tests/Feature/Cliente/`)
- 1 PR atômico Wave H (commit-discipline ≤300 LOC respeitado se cada gap = commit separado)
- Smoke MCP biz=1 valida cada gap individualmente antes Wave VENDER iniciar

**Owner Wave H:** sub-agent foreground (não paralelo — sequencial pra agent garantir gap-a-gap). Estimate IA-pair: 15h.

### Wave I — Evolução pós-replicação (14h IA-pair, OPCIONAL)

Aplicar **após Wave PESSOAS** entregar. Eleva nota 88 → 92.

| Gap | Effort | Razão pós-replicação |
|---|---|---|
| **#4 CI gate `drawer:health`** | 5h | Precisa entidades migradas pra ter o que validar |
| **#6 Centrifugo realtime channel** | 6h | Precisa subscriber pattern provado em produção |
| **#7 Offline queue localStorage** | 8h | Sinal qualificado Larissa Cabo Frio (ADR 0105) |

### Adiados (P2/P3) — esperar sinal qualificado

Gaps #8 (permissões per-tab Spatie), #9 (rota /print PDF), #10 (mobile <1024 modal fullscreen) — esperar requisição explícita Wagner ou cliente piloto antes de implementar.

### Estimate total atualizado

| Fase | Effort IA-pair | Wagner relógio |
|---|---|---|
| F0.5 Wave H (pré-replicação) | **15h** | 1h aprovar |
| F1 Produto (VENDER) | 20-25h | 2-4h aprovar |
| F2 OPERAR + FINANÇAS paralelas | 60-75h | 4-8h aprovar |
| F3 PESSOAS Knowledge | 8-12h | 1-2h decidir |
| F4 Wave I evolução | 14h | 1-2h aprovar |
| **Total** | **117-141h IA-pair** | **9-17h relógio** |

**Estimate consolidado:** **100-140h IA-pair + ~6-8 dias relógio real paralelizado + ~1.5 dia Wagner aprovações cumulativo.**

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

## Aceitação

✅ **Aceita 2026-05-22 por Wagner** em sessão `frosty-greider-83ab2f` — comando exato: *"eu gostei pode salvar"*.

Sub-agents podem agora iniciar **Wave Produto (F1)** seguindo template Cliente + matriz de armadilhas catalogadas nesta ADR.

### Antes de cada Wave começar — checklist obrigatório do sub-agent

1. Ler esta ADR completa + skill [`pageheader-canon`](../../.claude/skills/pageheader-canon/SKILL.md) Fase 4-bis
2. Confirmar entidade É cadastral conforme matriz (≥5 campos identitários + IA/Auditoria fazem sentido)
3. Listar tabs propostas + estrutura `_drawer/` antes de codar (apresentar pra Wagner aprovar)
4. Verificar armadilhas críticas da seção "Preocupações adicionais" que se aplicam à entidade
5. Estimate realista por entidade conforme tabela v2 (NÃO usar estimate v1 otimista)
6. Skill `wagner-understand` pré-implementação OBRIGATÓRIA pra Waves >15h IA-pair
7. PR atômico por entidade (commit-discipline; encadear PRs como Wave A-G Cliente fez)
