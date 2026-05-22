# HANDOFF — Cliente (Drawer 760 canon)

> **Pós-Wave A-G prod biz=1.** Refinamento Claude Design 2026-05-22 + Wagner "registre o padrão eu adoro esse estilo".
>
> **Audiência:** Sub-agents Claude/Codex que vão aplicar Wave H técnica (15h) ou replicar drawer 760 nas 6 entidades cadastrais (Produto/ServiceOrders/Vehicles/DeviceModels/Planos/TransactionPayment).
>
> **Versão pre-implementação:** [`prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md`](../../../prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md) (380 linhas, mockup era spec; pós Wave A-G entregue, este HANDOFF assume o pattern como canon).

---

## 0. Antes de tocar em código — ler estes 4 docs (15 min)

| Doc | Linhas | Função |
|---|---|---|
| [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) | ~270 | Canon source Wave A-G (Q1-Q4 Wagner) |
| [ADR 0185](../../decisions/0185-drawer-760-canon-entidades-cadastrais.md) | 392 | Escala 7 entidades + Wave H 15h + Wave I + **21 armadilhas** |
| [memory/reference/drawer-760-pattern-canon.md](../../reference/drawer-760-pattern-canon.md) | ~180 | 1-pager executivo (mostrar pra time MCP) |
| [memory/reference/feedback-drawer-760-canon-style.md](../../reference/feedback-drawer-760-canon-style.md) | ~80 | Wagner Tier 0 — paradigma fechado |
| [RUNBOOK-Cliente-drawer-760px.md](RUNBOOK-Cliente-drawer-760px.md) | 399 | Receita executável Wave A-G+Z |

---

## 1. Estado atual (3 linhas)

- **Cliente prod biz=1 ao vivo** (PRs #1339→#1358 mergeados 2026-05-21, Wave Z-2 smoke ✓ paridade 95% Cowork)
- **Nota auditoria estado-da-arte 2026-05-22:** **76,4/100** vs Notion/Linear/HubSpot/Stripe/Salesforce — faixa "bom" (3,6 pts da mundo-classe ≥80). Dossier: [`memory/sessions/2026-05-22-arte-drawer-760-vs-mundo.md`](../../sessions/2026-05-22-arte-drawer-760-vs-mundo.md)
- **Wave H pendente (15h IA-pair) BLOQUEIA replicação** nas 6 entidades — gaps P0 escalam 7× se replicarmos primeiro

---

## 2. Bloqueador #1 — Wave H (fazer ANTES de qualquer outra coisa)

Aplicar 4 gaps P0 **no template Cliente** primeiro. Sub-agent que pegar Wave Produto/ServiceOrders/etc DEVE rodar este grep antes:

```bash
grep -l "updated_at.*check\|optimistic.*lock" Modules/Crm/Http/Controllers/ClienteAutosaveController.php
grep -l "React.lazy\|Inertia::defer" resources/js/Pages/Cliente/Index.tsx
grep -l "popstate\|usePopState" resources/js/Pages/Cliente/Index.tsx
grep -l "focus-lock\|focusTrap" resources/js/Pages/Cliente/Index.tsx
```

Se algum ausente → **STOP**. Aplicar Wave H primeiro.

| Gap | h | Como aplicar | Sistema-ref |
|---|---|---|---|
| #1 Optimistic locking | 6 | `updated_at` check em PATCH autosave (`ClienteAutosaveController`) + HTTP 409 + toast "outro user editou — recarregue" + opcional merge UI. Extrair middleware genérico pra reuso 6 entidades. | HubSpot, Linear |
| #2 Lazy load tabs | 4 | `React.lazy()` + `<Suspense fallback={<TabSkeleton/>}/>` nas 8 tabs do drawer. `Inertia::defer` per-tab payload. Bundle drawer **118KB → ~30KB initial**. | Linear, Stripe |
| #3 popstate | 2 | `useEffect` listen `popstate` no `Index.tsx` → fecha drawer antes de navegar pra outra rota; preserva filtros lista via `replaceState`. | GitHub, Linear |
| #5 Focus trap | 3 | Radix Dialog/Sheet primitives OU `react-focus-lock` no drawer wrapper. Foca 1ª input ao abrir; `Esc` fecha; `Tab`/`Shift+Tab` trap dentro drawer. WCAG 2.2 AA. | Linear, Radix |

**Output Wave H:** template Cliente sobe nota **76,4 → 88/100** (mundo-classe). Replicação herda baseline 88. Total: 15h IA-pair sequencial (não paralelo — gaps relacionados ao mesmo template).

Pest charter test obrigatório por gap (4 testes novos em `tests/Feature/Cliente/`). PR atômico Wave H (commit-discipline ≤300 LOC se cada gap = commit separado).

---

## 3. Schema canônico BR (8 tabs do drawer 760)

### 3.1 Identificação

| Campo | Tipo | Máscara/Validação | Obs |
|---|---|---|---|
| `tipo` | enum('PF','PJ') | toggle no header drawer | — |
| `nome` | string(255) | obrig., min 3 | "Razão social" se PJ |
| `fantasia` | string(255) | opcional | só PJ |
| `doc` | string(18) | **CPF/CNPJ mod 11** | máscara automática |
| `ie` | string(20) | opcional | só PJ — Inscrição estadual |
| `rg` | string(20) | opcional | só PF |
| `nascimento` | date | opcional | só PF |
| `contato` | string(120) | opcional | só PJ — responsável |
| `cargo` | string(80) | opcional | só PJ |

### 3.2 Contato

| Campo | Tipo | Máscara | Obs |
|---|---|---|---|
| `tel` | string(20) | `(00) 0 0000-0000` | principal |
| `tel2` | string(20) | idem | alternativo |
| `email` | string(120) | regex e-mail | inline error |
| `site` | string(120) | opcional | — |
| `canal` | enum | `whatsapp/email/telefone/presencial` | radio |

### 3.3 Endereço (ViaCEP via `BrLookupService`)

| Campo | Tipo | Validação | Obs |
|---|---|---|---|
| `cep` | string(9) | `00000-000`, 8 dígitos | dispara busca no blur |
| `endereco` | string(180) | obrig. | logradouro |
| `numero` | string(10) | obrig. | — |
| `complemento` | string(80) | opcional | apto, conjunto |
| `bairro` | string(80) | obrig. | — |
| `cidade` | string(120) | obrig. | — |
| `uf` | enum 27 UFs | obrig. | select |

`GET /clientes/lookup/cep/{cep}` proxy server-side → cache Redis 90d.

### 3.4 Comercial

| Campo | Tipo | Obs |
|---|---|---|
| `limite_centavos` | integer | limite crédito; vazio = sem limite |
| `prazo_dias` | integer | dias prazo padrão |
| `tabela_preco` | enum | `padrao/varejo/atacado/parceiro` |
| `pgto` | enum | `pix/boleto/cartao/dinheiro/transferencia` |
| `obs_comercial` | text | observações livres |

### 3.5 Classificação

| Campo | Tipo | Obs |
|---|---|---|
| `segmento` | enum | `varejo/atacado/agencia/corporativo/evento/governo` |
| `tags` | JSON string[] | multi-select 9 valores (TAG_OPTIONS) |
| `status` | enum | `ativo/inativo/bloqueado` |
| `vip` | boolean | flag global (≠ `favorito_pessoal`) |

### 3.6 OSs (read-only operacional)

Tab com sub-tabs aninhadas (vertical pills 120 + content 640): Ledger / Sales / Payments / Documents / Activities / Pessoas / Subscriptions / RewardPoints. Wave Final 2026-05-21 (PRs #1304-1307) entregou os 8 sub-componentes.

### 3.7 IA (Brain B Jana)

4 endpoints server-side (`LaravelAiSdkDriver` + cache):

```
POST /clientes/{cliente}/ia/resumo         → ResumoIAController
POST /clientes/{cliente}/ia/sugest-tags    → SugestaoTagsController
POST /clientes/{cliente}/ia/proxima-acao   → ProximaAcaoController
GET  /clientes/{cliente}/ia/risco          → RiscoController (determinístico, SEM LLM)
```

Quota `copiloto.admin.custos` (US-COPI-070). **NÃO chamar `window.claude.complete` no front.**

### 3.8 Auditoria (Spatie ActivityLog LGPD Art 18)

`AuditLog::where('subject_type', Contact::class)->where('subject_id', $id)` via `Modules/Auditoria/Services/AuditEntryService::forSubject($contact)`. **Zero código novo** — reusa ADR 0127.

---

## 4. 4 tabs DRY canônicas (copy do Cliente template)

Para cada nova entidade cadastral, sub-agent DEVE copiar e adaptar:

| Tab canon | Source template Cliente | O que adaptar | O que mantém |
|---|---|---|---|
| **IdentificacaoTab** | `resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx` (611L) | Campos identidade per-entidade (CPF/CNPJ vs SKU/Placa/CodigoBarras) | Pattern autosave debounce 800ms + optimistic + rollback + masks BR |
| **EnderecoTab** | `resources/js/Pages/Cliente/_drawer/EnderecoTab.tsx` | Nada (CEP+endereço universal) | Tudo — reusa `BrLookupService` cache Redis 90d |
| **IATab** | `resources/js/Pages/Cliente/_drawer/IATab.tsx` (~420L) | Prompts Brain B per-entidade | Pattern 3-4 cards + `LaravelAiSdkDriver` + Suspense |
| **AuditoriaTab** | `resources/js/Pages/Cliente/_drawer/AuditoriaTab.tsx` (~290L) | Nada (Spatie ActivityLog é polimórfico) | Tudo — `forSubject(Entity)` + LGPD Art 18 |

ROI replicação: **90% reuso** entre entidades cadastrais. Tabs específicas (5-8 típico) cada agent identifica conforme blueprint Cowork OU `wagner-understand` pré-implementação.

---

## 5. Anti-patterns Tier 0 (NÃO romper)

❌ Criar `Edit.tsx`/`Create.tsx` separado pra entidade cadastral
❌ Botão "Salvar" no drawer (autosave on blur)
❌ Modal/Sheet centralizado em vez de drawer lateral
❌ Drawer largura **≠ 760px** (não expansível, não responsivo, não per-entidade)
❌ Mais de ~10 tabs (use overflow `⋯ Mais`)
❌ `Show.tsx` full-page convivendo com drawer (sunset zero)
❌ Tab "Novo X" (drawer abre-fechado = create, abre populated = edit)
❌ Chamar BrasilAPI/ViaCEP de frontend (sempre via proxy `BrLookupService`)
❌ `window.claude.complete` direto em produção (sempre server-side IA endpoint)
❌ Persistir favoritos/anotações em `localStorage` no produto real (banco)
❌ Quebrar scope `business_id` global (ADR 0093 IRREVOGÁVEL)
❌ Aplicar pattern em entidade que **NÃO** é cadastral (workflow → FOCO V2; técnico simples → FOCO V1)

---

## 6. Critério Done (mensurável)

PR de cada entidade NÃO mergeia sem:

- [ ] **Wave H aplicada** no template Cliente (4 greps acima retornam path) — verificar PRE-flight do sub-agent
- [ ] **Score ≥88/100** na auditoria estado-da-arte (Wave H eleva 76,4 → 88; replicação herda)
- [ ] **Pest charter test** em viewport 1280×1024 sem scroll horizontal — verde
- [ ] **Smoke browser MCP** script JS canon Fase 5 da skill `pageheader-canon`:
  - Modo NAV: C1-C6 ✓ (tabs renderizam + active visible + labels curtos + primary hue correto + sem 500 + overflow funcional)
  - Modo FOCO (Edit/Create se entidade tiver): F1-F4 ✓
- [ ] **0 violações Tier 0** (multi-tenant `business_id` global + autosave on blur + drawer 760 fixo)
- [ ] **Charter `Index.charter.md`** com `drawer_pattern: 760px-lateral` + 8 tabs declaradas
- [ ] **RUNBOOK** seguindo template Wave A-G Cliente
- [ ] **Smoke prod biz=1 canary** ANTES de habilitar biz=4 Larissa (Tier 0 IRREVOGÁVEL)
- [ ] **Brief-update** após merge (skill `brief-update` Tier B auto)

---

## 7. Limites operacionais (zero-toque Wagner)

**NÃO fazer sem Wagner aprovar:**

- Habilitar `MWART_<MODULO>_INDEX=true` em **biz=4 Larissa** sem 7d canary biz=1 verde
- Mover Apps Vinculados pra Cliente — painel direito permanece exclusivo Copiloto/Inbox (PO decisão 2026-05-21)
- Introduzir 6ª cor de origin badge "CLI" — reusar **CRM** (azul) onde necessário (ADR UI-0008)
- Quebrar pattern reuse [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md) — todas entidades cadastrais herdam template Cliente
- Aplicar Drawer 760 fora da matriz de elegibilidade (workflow/POS/técnico simples seguem FOCO V2/V1)
- Custo Brain B Tab IA > $5/dia/biz → gate quota obrigatório (review trigger ADR 0185)

**Fazer SEMPRE:**

- Migration aditiva ALTER TABLE (NÃO criar tabela paralela; expande core UPOS)
- 5 endpoints PATCH autosave per entidade (`/identificacao` / `/contato` / `/endereco` / `/comercial` / `/classificacao`) com debounce 800ms + optimistic + rollback 4xx/5xx
- 2 redirects 302: `/{modulo}/{id}` (Show legacy) + `/{modulo}/{id}/edit` → `/{modulo}?contact_id={id}&tab=identificacao`
- Cache Redis 90d/30d em proxies BR (CEP/CNPJ via `BrLookupService`)
- LGPD Art 18 via Spatie ActivityLog (zero código novo, reusa ADR 0127)
- Pest cross-tenant biz=1 vs biz=99 por entidade

---

## 8. Refs

- [ADR 0179](../../decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) — Cliente Drawer 760 canon source
- [ADR 0185](../../decisions/0185-drawer-760-canon-entidades-cadastrais.md) — Escala 7 entidades + Wave H + Wave I
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0127](../../decisions/0127-spatie-activitylog-lgpd-art-18.md) — Spatie ActivityLog LGPD Art 18
- [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md) — Pattern reuse Cowork blueprint
- [ADR 0182](../../decisions/0182-pageheadertabs-canon-pattern-telas.md) — PageHeader 3 zonas canon
- [Skill pageheader-canon](../../../.claude/skills/pageheader-canon/SKILL.md) — Fase 4-bis + Wave H pré-requisito + Fase 5 validação visual
- [Reference 1-pager](../../reference/drawer-760-pattern-canon.md) — Para Felipe/Maiara/Eliana
- [Feedback canon Tier 0](../../reference/feedback-drawer-760-canon-style.md) — Wagner preferência forte
- [Auditoria estado-da-arte 2026-05-22](../../sessions/2026-05-22-arte-drawer-760-vs-mundo.md) — Nota 76,4/100 + 10 gaps + 14 URLs canon
- [RUNBOOK Wave A-G+Z Cliente](RUNBOOK-Cliente-drawer-760px.md) — Receita executável detalhada
- [HANDOFF_CLIENTES.md original](../../../prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md) — Versão pre-implementação (mockup era spec)

---

**Última atualização:** 2026-05-22 — Wagner sessão `frosty-greider-83ab2f` "registre o padrão eu adoro esse estilo. salve tudo formalize o melhor" + Claude Design refinou estrutura.
**Cliente prod biz=1:** ao vivo desde 2026-05-21 (Wave Z-2 smoke validated).
**Wave H:** pendente (15h IA-pair) — bloqueia replicação.
