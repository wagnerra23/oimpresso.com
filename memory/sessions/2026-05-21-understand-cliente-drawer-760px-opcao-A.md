---
slug: 2026-05-21-understand-cliente-drawer-760px-opcao-A
title: "wagner-understand — refazer paradigma Cliente: drawer 760px lateral substitui Show.tsx (opção A)"
type: understand-decode
date: 2026-05-21
session: frosty-greider-83ab2f
spawned_by: claude-pai
status: ready-for-execution
related_adrs: [0093, 0094, 0104, 0107, 0110, 0114, 0149, 0167]
related_pages: [Cliente/Index, Cliente/Show]
related_charters: [Pages/Cliente/Index.charter.md, Pages/Cliente/Show.charter.md]
prototype_score: KB-9.75 9,4/10 (Refinos #1 + #2 + #3)
---

# Decodificação

## Pedido cru de Wagner (texto exato)

> Wagner aprovou opção (A) — refazer paradigma da tela Cliente: o `Show.tsx` full-page atual (Extrato/Vendas/Pagamentos/Docs/Atividades/Pessoas/Assinaturas/Pontos) será SUBSTITUÍDO por um **drawer 760px lateral** abrindo a partir do `Index.tsx`, com **8 tabs cadastrais**: Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria. Conforme protótipo Cowork em `prototipo-ui/prototipos/clientes/` (HTML + 13 .jsx, score KB-9.75 9,4/10).

---

## Decodificação refinada

- **Objetivo principal:** Inverter o paradigma de detalhe de cliente — de **página full-page** (`/cliente/{id}` renderiza `Show.tsx`) para **drawer lateral 760px** sobre `Index.tsx`, sem regredir as 5 waves de paridade legacy mergeadas em 2026-05-21.

- **Sub-objetivos atômicos:**
  1. Definir destino da rota `/cliente/{id}` (deeplink → abrir Index + drawer pré-aberto pelo `?contact_id={id}`, OU manter Show como fallback até sunset).
  2. Expandir `ClienteSheet` (480 → 760 px) com header rico (toggle PF/PJ + badge Ativo + 2 botões) + 8 tabs.
  3. **5 tabs cadastrais novas** (Identificação · Contato · Endereço · Comercial · Classificação) com máscaras/validadores BR + proxies ViaCEP + BrasilAPI server-side com cache.
  4. **Tab OSs** = wrapper que **encaixa as 5 subtabs já feitas Wave 5/Final** (PaymentsTab, LedgerTab, SalesTab, DocumentsTab, ActivitiesTab, PessoasContatoTab, SubscriptionsTab, RewardPointsTab) sem regredir.
  5. **Tab IA** com 4 cards Copiloto (Resumo / Reavaliar segmento+tags / Próxima ação / Score) — endpoints server-side via `Modules/Jana` (NÃO `window.claude.complete` cliente).
  6. **Tab Auditoria** = timeline LGPD via `spatie/laravel-activitylog` (composer já tem v4.8) + `Modules/Auditoria` (já existe controller `AuditoriaController`).
  7. **Listagem turbinada** — avatar colorido-hash, 6 dropdowns (Tipo/Status/UF/Tags/Sem compra há/Com saldo), tags coloridas semânticas, FrescorPill, star favorito pessoal, Saldo vermelho, Exportar.
  8. Charter `Show.charter.md` v2 → `superseded_by` Charter `Index.charter.md` v3 (com `drawer_pattern: 760px-lateral` + 8 tabs).
  9. ADR canon nova de **mudança de paradigma** (porque Charter Show v2 lista 4 das 8 tabs como Non-Goals explícitos — vide §Pegadinhas).

- **Critério de pronto pra Wagner aprovar a Wave inteira:**
  - Screenshot drawer aberto em **prod** com `MWART_CLIENTE_INDEX=true` no biz=1 (Wagner WR2 SC, **não** biz=4) renderizando as 8 tabs.
  - Pest cobertura: 8 fluxos cadastrais + IA + Auditoria + cross-tenant (`business_id`) + permission gate.
  - `php artisan jana:health-check` verde (5 checks).
  - Brave smoke salvo em `prototipo-ui/SYNC_LOG.md` (R1 do PROTOCOLO).
  - Charter v3 + ADR de paradigma com `status: accepted`.
  - Rota legacy `/contacts/{id}` permanece intacta (dual-render via `config('mwart.cliente_show.enabled')`).

- **Persona alvo:** Wagner (dev/PO) implementa; Larissa (biz=4 ROTA LIVRE, monitor 1280×1024, não-técnica) consome — drawer **DEVE caber** sem scroll horizontal em 1280px (760 + sidebar AppShellV2 ≈ 240 + main → confere 1000px largura útil, OK).

- **Implícitos detectados:**
  - Wagner quer **DENSIDADE Linear/Attio** na listagem (não verbosa). Avatar colorido-hash é HSL determinístico por `contact.id`.
  - **Pattern reuse ADR 0149** — derivar 90% dos componentes do protótipo Cowork (13 .jsx já existem em `prototipo-ui/prototipos/clientes/`), não inventar do zero.
  - **R2 (cópia literal)** aplica — Wagner aprovou o protótipo Cowork (score 9,4/10) em sessão `s` no Cowork ↔ Claude Code loop ADR 0114. Cópia integral em PR único, não slice.
  - Inertia partial reload (`only:['tabIdentificacao']`) por tab no abrir — defer caro só do que está visível.
  - A nova rota `/cliente/{id}` deve fazer redirect/deeplink pro `Index.tsx?contact_id={id}` que abre drawer automaticamente — preserva URL compartilhável.

- **Ambiguidades a confirmar com Wagner ANTES de codar Wave B:**
  - Q1: `Show.tsx` será **DELETADO** após sunset 30d, ou vira **modo "ficha completa"** acessível via botão "Imprimir ficha" + `target=_blank`?
  - Q2: Quando Wagner clica em "Editar" no header do drawer — abre `Edit.tsx` em rota nova, ou as 5 tabs cadastrais **já são editáveis inline** (autosave por field blur)? O protótipo Cowork sugere INLINE (handoff §2 lista os campos como form).
  - Q3: **Migration nova `clientes`** (handoff §3) ou **reusar `contacts` UPOS** estendido? Handoff propõe tabela paralela; mas legacy UPOS biz=1/biz=4 já tem 10+ campos BR restaurados no PR #1313. Recomendação: **estender `contacts`** (não duplicar), criar campos missing (`tipo`, `fantasia`, `ie`, `rg`, `nascimento`, `cargo`, `tel2`, `canal`, `tabela_preco`, `pgto`, `obs_comercial`, `segmento`, `tags` JSON, `vip`) via migration aditiva idempotente.
  - Q4: Tab IA fica **default** ou só pra plano pago `copiloto.admin.custos`? US-COPI-070 menciona quota; preciso decidir se quota bloqueia render ou só execução dos 4 cards.

---

## Regras protocolo aplicáveis (R1-R12 — PROTOCOLO-WAGNER-SEMPRE.md)

| Regra | Aplica? | O que exige neste pedido |
|---|---|---|
| **R1 Smoke real** | ✅ obrigatório | Brave smoke pós-merge em prod `oimpresso.com/cliente` logado biz=1; screenshot do drawer com 8 tabs renderizando salvo em `prototipo-ui/SYNC_LOG.md`. Também shell-shared check (Index toca AppShellV2 layout): 3 rotas Inertia distintas. |
| **R2 Cópia literal aprovada** | ✅ aplica | Wagner aprovou protótipo Cowork (9,4/10). PR pode ultrapassar 300 LOC com label `design-literal-copy` + link prototype. NÃO propor slice. |
| **R3 Mexeu→Registra** | ✅ obrigatório | Edits em `Modules/Crm/` (LedgerController), `app/Http/Controllers/ContactController.php` (core UPOS), `Modules/Jana/Http/Controllers/` (IA), `Modules/Auditoria/` (timeline). PRÉ-FLIGHT: ler `memory/requisitos/Crm/SPEC.md` + RUNBOOK-cliente-show.md + Show.charter.md + Index.charter.md + ADR 0149 ANTES. |
| **R4 Multi-tenant Tier 0** | ✅ IRREVOGÁVEL | Todo endpoint novo (lookup/cnpj, lookup/cep, ia/resumo, ia/sugest-tags, ia/proxima-acao, ia/risco, auditoria/{id}) filtra `business_id`. Migrations aditivas com `business_id` indexado se for tabela nova. Pest cross-tenant biz=1 vs biz=99 obrigatório. |
| **R5 PT-BR + economia** | ✅ aplica | Escopo grande (>2500 LOC est., 12+ arquivos, refator estrutural Show↔Index). **Confirmar escopo ANTES** das 4 ambiguidades Q1-Q4 acima via `AskUserQuestion`. PT-BR em tudo. |
| **R6 biz=1 não biz=4** | ✅ aplica | Pest `business_id=1`. Smoke prod `Wagner@oimpresso.com` biz=1 (WR2 SC), **JAMAIS** biz=4 Larissa (cliques disparam OS/WhatsApp pra clientes reais). |
| **R7 Charter+visual-comparison** | ✅ obrigatório | Edit em `Pages/Cliente/Index.tsx` e `Pages/Cliente/Show.tsx` → ler `Index.charter.md` (status: draft → publish v3 NESTA wave) + `Show.charter.md` v2 (marcar `superseded_by`). Atualizar `memory/requisitos/Crm/cliente-show-visual-comparison.md` + criar `cliente-drawer-760-visual-comparison.md`. |
| **R8 Branch/worktree disciplina** | ✅ aplica | Estamos no worktree `D:\oimpresso.com\.claude\worktrees\frosty-greider-83ab2f`. TODOS Edits via path absoluto do worktree (não main repo). Pre-flight `pwd` antes de cada Edit. |
| **R9 Zero auto-mem** | ✅ aplica | Esta devolutiva vai em `memory/sessions/` git canon (não auto-mem). Charter v3 + ADR de paradigma → `memory/decisions/`. RUNBOOK atualizado → `memory/requisitos/Crm/`. |
| **R10 Aprovação humana** | ✅ aplica | Wagner aprovou opção (A) — caminho. PR criar/push/merge precisa "sim pode" explícito por PR (a wave provavelmente vira 4-6 PRs sequenciais). |
| **R11 Continuar até desfecho** | ✅ aplica | Uma vez Wagner aprovar charter v3 + ADR, Claude implementa Wave B→G sem parar pra re-aprovação por commit; pausa só em CI red / gap visual smoke / ambiguidade nova. |
| **R12 Fechamento sessão** | ✅ aplica | Handoff append-only em `memory/handoffs/` + índice `08-handoff.md` quando Wagner fechar a sessão. |

**Especiais ativados:**
- [`smoke-prod-evidence`](../../.claude/skills/smoke-prod-evidence/SKILL.md) Tier B
- [`charter-first`](../../.claude/skills/charter-first/SKILL.md) Tier A
- [`mwart-comparative` V4](memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — 15 dimensões + Claude Design plugin
- [`inertia-defer-default`](../../.claude/skills/inertia-defer-default/SKILL.md) Tier B
- [`multi-tenant-patterns`](../../.claude/skills/multi-tenant-patterns/SKILL.md) Tier A
- [`RUNBOOK-onda-cowork`](../requisitos/_DesignSystem/RUNBOOK-onda-cowork.md) — 12 fases canon

---

## Inventário no projeto

| O que procurei | Onde achei | Status |
|---|---|---|
| **Show.tsx atual com 8 tabs** | `resources/js/Pages/Cliente/Show.tsx` linhas 80-112 | LIVE — Wave Final mergeada: ledger/sales/payments/documents + activities/persons/subscriptions/rewards |
| **Subtabs já buildadas** | `Pages/Cliente/_show/` (10 arquivos) | LIVE — PaymentsTab, LedgerTab, SalesTab, DocumentsTab, ActionsMenu, AddDiscountModal, ContactPicker, ActivitiesTab, PessoasContatoTab, SubscriptionsTab, RewardPointsTab |
| **ClienteSheet drawer atual** | `Pages/Cliente/Index.tsx` linhas 744-799 (480px, 2 KPI + contato + 2 botões) | EXPANDIR pra 760px + 8 tabs |
| **KB-9.75 Slice A** | `Pages/Cliente/Index.tsx` linhas 174-279, 892-… | LIVE PR #1309 — ⌘K, ?, J/K, Enter, / focus search — REUSAR 100% |
| **Charter `Show.charter.md` v2** | `Pages/Cliente/Show.charter.md` | Non-Goals listam 4 das 8 tabs novas como ❌ — paradigma muda → supersede obrigatório |
| **Charter `Index.charter.md`** | `Pages/Cliente/Index.charter.md` | status: **draft** desde 2026-05-09 — esta Wave publica v3 com `drawer_pattern: 760px-lateral` |
| **Protótipo Cowork** | `prototipo-ui/prototipos/clientes/` (HTML + 16 .jsx/.css) | Score 9,4/10 — base de cópia literal (R2). Arquivos-chave: `clientes-drawer.jsx` (540px shell — vai pra 760), `clientes-tabs.jsx` (Audit+OSs+IA), `clientes-975.jsx` (CmdK/Cheat/Fresc/Fav/Print) |
| **HANDOFF_CLIENTES.md** | `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` (381 linhas) | Spec completa: schema BR · migrations sketch · rotas · 4 endpoints IA · checklist pronto. **LER ANTES** de cada Wave. |
| **Rota `/cliente/{id}` canon** | `routes/web.php:203-213` | Wrapper thin chama `ContactController::show($id)` — guard `whereIn type [customer, both]` + business_id check |
| **`ContactController::show()`** | `app/Http/Controllers/ContactController.php:1004-1100+` | Inertia branch (1050) com `Inertia::defer` em stats/transactions/sales/contact_dropdown |
| **Config flag** | `config/mwart.php:130-133` `cliente_show.enabled` | LIVE — canary biz=1 |
| **Spatie ActivityLog** | `composer.json:47` v4.8 | INSTALADO — usar pra Auditoria tab (não inventar mcp_audit_log paralelo) |
| **Module Auditoria** | `Modules/Auditoria/Http/Controllers/AuditoriaController.php` + `DataController` + `AuditEntryService` + LGPD retention.php | EXISTE — reusar service `AuditEntryService::forSubject(Contact)` |
| **Module Copiloto/Jana** | `Modules/Jana/Http/Controllers/ChatController.php` + `Services/Ai/LaravelAiSdkDriver.php` + `Services/CustosService.php` | EXISTE — usar `LaravelAiSdkDriver` em 4 endpoints novos, quota `copiloto.admin.custos` via `CustosService` |
| **Modules/Copiloto/** | nenhum diretório | ❌ NÃO existe módulo separado; tudo é `Modules/Jana`. "Falar com Copiloto →" do header do drawer aponta pra `/jana/chat?context=cliente:{id}` (NÃO `/copiloto`) — confirmar com Wagner |
| **`ContextSnapshotService`** | NÃO existe nesse nome | Verificar — pode ser `Modules/Brief/` ou `Modules/Jana/Services/BriefDiarioService.php` (existe) |
| **BrasilAPI / ViaCEP service-side** | nenhum match em `app/`/`Modules/` | ❌ NÃO existe — criar `Modules/Crm/Services/BrLookupService.php` com cache Redis (CNPJ 30d, CEP 90d) |
| **ADR 0149 pattern reuse** | citado em Show/Index charters | LIVE — derive não-copy |
| **SPEC.md Crm** | `memory/requisitos/Crm/SPEC.md` (31KB) | LER ANTES — pré-flight R3 |
| **RUNBOOK-cliente-show.md** | `memory/requisitos/Crm/RUNBOOK-cliente-show.md` (35 linhas) | §8 menciona "sunset legacy tabs migram pra Tabs futuras" — esta wave É essa migração |
| **Visual comparison** | `memory/requisitos/Crm/cliente-show-visual-comparison.md` | Atualizar + criar `cliente-drawer-760-visual-comparison.md` |
| **Coord sessão 5 waves** | `memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md` | Contexto Wave 5/Final paralelas que mergearam |
| **Tasks MCP** | (não consultei `tasks-list`) | Recomendar Wagner criar `US-CRM-068` (drawer 760) e `US-CRM-069` (listagem turbinada) — separar |

**Bandeira amarela:** sub-tabs Wave Final (`ActivitiesTab`, `PessoasContatoTab`, `SubscriptionsTab`, `RewardPointsTab`) foram catalogadas como **Non-Goals explícitos** no Charter v2 mas implementadas mesmo assim entre 2026-05-21 (PRs #1304-1307). Indica que o Charter v2 já estava obsoleto. Esta Wave **formaliza** isso via supersede.

---

## Pegadinhas conhecidas

- **[Tier 0]** `business_id` global scope obrigatório em TODA query nova (lookup CNPJ/CEP cache, IA endpoints, Auditoria, autosave field). `withoutGlobalScopes()` proibido sem comentário `// SUPERADMIN: <razão>`.

- **[Charter supersede obrigatório]** `Show.charter.md` v2 linhas 42-44 listam Atividades/Pessoas/Assinaturas/Reward Points como **Non-Goals explícitos**. Mesmo já implementadas via PRs #1304-1307, o charter NÃO foi atualizado — bandeira amarela. Esta Wave **DEVE** publicar Charter Index v3 com `supersedes: [Pages/Cliente/Show.charter.md v2]` + reverter status Show.charter.md pra `status: superseded`.

- **[ADR de mudança de paradigma]** Mudança de "página de detalhe full-page" para "drawer lateral 760px" é Tier A (toca pattern compartilhado com outras telas — Fornecedor pode seguir igual). Exige ADR canon nova (próxima numeração — ver `decisions/`) com `accepted` por Wagner.

- **[Sub-tab "OSs" complexidade]** A tab "OSs" do drawer 760 não é trivial — ela **encapsula 8 sub-tabs** (Extrato, Vendas, Pagamentos, Docs, Atividades, Pessoas, Assinaturas, Pontos). Opções: (i) sub-tabs internas verticais à esquerda + conteúdo direita (drawer 760 + sub-tab 120 + content 640 — apertado), (ii) renderizar SalesTab por default e oferecer dropdown "ver outras seções" expandindo para "ficha completa" full-page residual. **Recomendar (ii)** — Show.tsx vira "Ver ficha completa" via botão "Imprimir ficha → tela cheia". Reduz risco de regressão das 5 PRs já mergeadas.

- **[PII Tier 0]** CPF/CNPJ NUNCA plain. `maskTaxNumber()` existe em ContactController; usar `tax_number_masked` em todo defer payload. Telefone também mascarado se for público (cliente Larissa atende cliente final do cliente — chain leak).

- **[Rota legacy `/contacts/{id}` intacta]** Não destruir — dual-render via `config('mwart.cliente_show.enabled')`. Sunset SÓ depois canary 30d biz=1 + biz=4.

- **[ViaCEP / BrasilAPI rate limit]** Cliente Larissa biz=4 ROTA LIVRE faz ~30 cadastros/dia — sem cache server-side, dispara rate limit ViaCEP (consulta/IP) em pico. **OBRIGATÓRIO** proxy `/cliente/lookup/cep/{cep}` + `/cliente/lookup/cnpj/{cnpj}` com cache Redis (CEP 90d, CNPJ 30d) — NUNCA chamar do client.

- **[Inertia defer + partial reload por tab]** Cada tab carrega dados via `Inertia::defer` + `<Deferred data="tabIdentificacao">`. Trocar de tab faz `router.reload({ only: ['tabContato'] })` — não fetch full page. Pattern já usado em Sells/Index e Show.tsx hoje.

- **[Spatie ActivityLog não duplicar]** Composer já tem `spatie/laravel-activitylog ^4.8`. Tab Auditoria usa `Activity::forSubject($contact)` (já usado em ContactController:1044). NÃO criar tabela `audit_log` paralela. `Modules/Auditoria/Services/AuditEntryService` já oferece scope multi-tenant.

- **[Quota IA Copiloto]** 4 endpoints IA (`/cliente/{id}/ia/resumo`, `/sugest-tags`, `/proxima-acao`, `/risco`) consomem quota — `Modules/Jana/Services/CustosService.php`. Score risco (`/risco`) **NÃO chama LLM** (handoff §5.4 — determinístico). Não contar quota nele.

- **["Falar com Copiloto →" rota]** Não existe `/copiloto` — Jana é o módulo. Botão aponta `/jana/chat?context=cliente:{id}` — `Modules/Jana/Http/Controllers/ChatController.php` precisa aceitar query `context` e pré-carregar fact sobre cliente.

- **["Imprimir ficha" PDF]** `window.print` client-side funciona pro MVP. Wagner mencionou brand Oimpresso — precisa CSS `@media print` específico no drawer. PDF real via DomPDF/Browsershot só Wave G+.

- **[LICOES_F3_FINANCEIRO_REJEITADO]** Wave Financeiro 2026-05-09 rejeitada por 6 meta-anti-padrões + 15 técnicos. PRÉ-FLIGHT obrigatório antes de Wave C-F: ler `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`. Padrão: NÃO inventar fluxos não pedidos; copiar PROTÓTIPO COWORK pixel a pixel.

- **[1280px breakpoint Larissa]** Drawer 760 + AppShellV2 sidebar 240 + main padding ≈ 1024px. **Deve testar em viewport 1280×1024** sem scroll horizontal — Pest charter test obrigatório.

- **[Worktree]** Estamos em `D:\oimpresso.com\.claude\worktrees\frosty-greider-83ab2f`. Edits via path absoluto desse worktree (lição R8 — PR #1032 quase perdeu 4h).

- **[Tasks atômicas + commits ≤300 LOC]** Override `commit-discipline` PERMITIDO com label `design-literal-copy` (R2). Cada Wave A-G ~1 PR. PRs sequenciais com `Depends-on:` no body.

- **[Spec backlog 7 PRs]** A wave inteira é >2500 LOC. **R5 economia:** confirmar escopo das 4 ambiguidades antes de codar — fator 10x ADR 0106 inclui margem 2x pra retrabalho de spec.

---

## Plug-points (arquivo:linha — mudança)

### Frontend (resources/js/)

| Camada | Arquivo:linha | Mudança |
|---|---|---|
| Index | `Pages/Cliente/Index.tsx:744` (ClienteSheet) | Expandir 480→760px, header com toggle PF/PJ + badge + 2 botões "Imprimir ficha" + "Falar com Copiloto →", 8 tabs |
| Index | `Pages/Cliente/Index.tsx:307` (h1+subtitle) | Adicionar 6 dropdowns (Tipo · Status · UF · Tags · Sem compra há · Com saldo) + botão Exportar |
| Index | `Pages/Cliente/Index.tsx:412` (`<Deferred data="customers">`) | Tabela: avatar colorido-hash HSL determinístico (substituir `<Avatar initial>` mono atual), Tag chips coloridas semânticas, FrescorPill (fresc/recente/distante/frio), Star pessoal, Saldo destacado vermelho |
| Index | `Pages/Cliente/Index.tsx:174` (KB-9.75 Slice A keymap) | Adicionar 1-8 trocar tab quando drawer aberto |
| Components novos | `Components/clientes/Pills.tsx` | StatusPill · TipoPill · TagChip · FrescorPill — derivar de `clientes-listagem.jsx` |
| Components novos | `Components/clientes/Avatar.tsx` | `avatarFor(id)` HSL hash determinístico derivar de `clientes-icons.jsx::avatarFor` |
| Components novos | `Pages/Cliente/_drawer/IdentificacaoTab.tsx` (NOVO) | Form PF/PJ toggle + máscaras BR (mod 11 CPF/CNPJ) |
| Components novos | `Pages/Cliente/_drawer/ContatoTab.tsx` | tel/tel2 máscara + email regex + canal radio |
| Components novos | `Pages/Cliente/_drawer/EnderecoTab.tsx` | CEP máscara + blur dispara ViaCEP proxy + autopreenche |
| Components novos | `Pages/Cliente/_drawer/ComercialTab.tsx` | limite/prazo/tabelaPreco/pgto/obsComercial |
| Components novos | `Pages/Cliente/_drawer/ClassificacaoTab.tsx` | segmento/tags/status/vip |
| Components novos | `Pages/Cliente/_drawer/OssTab.tsx` | **wrapper** que renderiza sub-tab default (SalesTab) + dropdown "Ver outras seções" → expandir Show.tsx full-page como modal/iframe (preserva 5 PRs Wave 5/Final) |
| Components novos | `Pages/Cliente/_drawer/IATab.tsx` | 4 cards Copiloto + endpoints `/cliente/{id}/ia/*` |
| Components novos | `Pages/Cliente/_drawer/AuditoriaTab.tsx` | timeline 6+ tipos eventos + Exportar log (`spatie/activitylog`) |
| Helpers BR | `Lib/br-mask.ts` (NOVO) | cpf/cnpj/tel/cep — derivar de `clientes-icons.jsx::BRMask` |
| Helpers BR | `Lib/br-validate.ts` (NOVO) | mod 11 — derivar de `clientes-icons.jsx::BRValidate` |
| Helpers BR | `Lib/avatar.ts` (NOVO) | initialsFor + avatarFor HSL — derivar de `clientes-icons.jsx::avatarFor` |
| Helpers BR | `Lib/relDate.ts` (NOVO) | "há X dias" formatter |
| Charter | `Pages/Cliente/Index.charter.md` | draft → live v3 com `drawer_pattern: 760px-lateral` + 8 tabs + supersede ref |
| Charter | `Pages/Cliente/Show.charter.md` | v2 → status: superseded + `superseded_by: [Index.charter.md v3]` |
| Show.tsx | `Pages/Cliente/Show.tsx` | MANTER intacto pro Q1 (modo "ficha completa") — só atualizar header pra incluir banner "Versão completa — atalho ⌘O" |

### Backend (app/, Modules/)

| Camada | Arquivo:linha | Mudança |
|---|---|---|
| Routes | `routes/web.php:198` (cliente.index/show) | Adicionar wrappers GET `/cliente/{id}` redirect 302 → `/cliente?contact_id={id}` (deeplink drawer) — OPÇÃO Q1 |
| Routes (Cliente novo grupo) | `routes/web.php` (após `cliente.show`) | `POST /cliente/{id}/identificacao` + `/contato` + `/endereco` + `/comercial` + `/classificacao` (autosave); `GET /cliente/lookup/cep/{cep}`; `GET /cliente/lookup/cnpj/{cnpj}`; `POST /cliente/{id}/ia/resumo`; `POST /cliente/{id}/ia/sugest-tags`; `POST /cliente/{id}/ia/proxima-acao`; `GET /cliente/{id}/ia/risco`; `GET /cliente/{id}/auditoria` (Spatie); `POST /cliente/{id}/favorito-pessoal` |
| Controller | `app/Http/Controllers/ContactController.php:198` (index) | Adicionar payload novo `customers[].avatar_hash`, `tags[]`, `segmento`, `vip`, `frescor`, `saldo`, `is_favorite` — propagar pra Index.tsx |
| Controller | `app/Http/Controllers/ContactController.php:1004` (show) | Adicionar deeplink redirect quando `request()->wantsJson()=false && config('mwart.cliente_index.enabled')` → `redirect()->to("/cliente?contact_id={$id}&tab=identificacao")` |
| Service novo | `Modules/Crm/Services/BrLookupService.php` (NOVO) | Proxy BrasilAPI/ViaCEP com cache Redis |
| Controller novo | `Modules/Crm/Http/Controllers/ClienteLookupController.php` (NOVO) | Endpoints `lookup/cep` + `lookup/cnpj` |
| Controller novo | `Modules/Crm/Http/Controllers/ClienteIaController.php` (NOVO) | 4 endpoints IA — usa `Modules/Jana/Services/Ai/LaravelAiSdkDriver` + `CustosService` quota check |
| Controller novo | `Modules/Crm/Http/Controllers/ClienteAuditoriaController.php` (NOVO) | `forSubject(Contact)` via `spatie/laravel-activitylog` |
| Migration | `database/migrations/2026_05_22_000000_extend_contacts_for_cliente_drawer.php` (NOVO) | **Aditiva idempotente** — adiciona `tipo`, `fantasia`, `ie`, `rg`, `nascimento`, `cargo`, `tel2`, `canal`, `tabela_preco`, `pgto`, `obs_comercial`, `segmento`, `tags` JSON, `vip`, `favorito_users` JSON em `contacts`. Garante `business_id` indexado. |
| Migration | `2026_05_22_000001_create_anotacoes_table.php` (NOVO) | Tabela polimórfica `anotacoes` (handoff §3) com `business_id` + `morphs(subject)` |
| Spatie config | `config/activitylog.php` (existe) | Garantir `Contact` model logged — `use LogsActivity;` no Eloquent |
| Permission | seeders Spatie roles | `cliente.lookup`, `cliente.audit_view`, `cliente.ia_consume`, `cliente.export_log` (4 novas) |

### Pest tests

| Arquivo | Cobertura |
|---|---|
| `tests/Feature/Cliente/ClienteIndexDrawer760CharterTest.php` (NOVO) | 11 Pest GUARDs do Index charter v3 + Non-Goal violations + cross-tenant |
| `tests/Feature/Cliente/ClienteDrawerCadastroAutosaveTest.php` (NOVO) | 5 tabs cadastrais — autosave por blur + validação mod 11 + multi-tenant |
| `tests/Feature/Cliente/ClienteLookupCnpjCepTest.php` (NOVO) | BrLookupService cache + proxy + erro 404 + rate limit graceful |
| `tests/Feature/Cliente/ClienteIaQuotaTest.php` (NOVO) | 4 endpoints IA respeitam `CustosService::checkQuota($user, 'cliente_ia')` |
| `tests/Feature/Cliente/ClienteAuditoriaSpatieTest.php` (NOVO) | timeline 6 tipos eventos + exportar log + LGPD Art. 18 |
| `tests/Feature/Cliente/ClienteShowSupersededTest.php` (NOVO) | `/cliente/{id}` redirect → drawer; `/contacts/{id}` legacy permanece |

---

## Tasks atômicas + estimate (fator 10x ADR 0106 + margem 2x)

| Wave | Task | Estimate | Bloqueia? | Dependências |
|---|---|---|---|---|
| **A** | Charter supersede `Show.charter.md` v2 + publish `Index.charter.md` v3 | 30min | — | Wagner aprova Q1-Q4 |
| **A** | ADR canon paradigma drawer 760 + 8 tabs (numeração próxima) | 45min | Wave B | A.1 |
| **A** | RUNBOOK-cliente-drawer-760.md + visual-comparison.md | 30min | — | A.1 |
| **A** | Wagner aprova `accepted` no ADR + Charter v3 | (humano) | Wave B-G | A.1+A.2 |
| **B** | Migration aditiva idempotente `contacts` + `anotacoes` table | 1h | C-F | A approved |
| **B** | Expandir ClienteSheet 480→760, header rico + esqueleto 8 tabs (skeleton vazio) | 1.5h | C-F | B.1 |
| **B** | Wire deeplink `/cliente/{id}` → Index `?contact_id={id}` (`router.visit` na load) | 30min | — | B.2 |
| **B** | Pest charter v3 (`ClienteIndexDrawer760CharterTest`) | 45min | C-G | B.2 |
| **C** | `IdentificacaoTab.tsx` + máscaras CPF/CNPJ + mod 11 + toggle PF/PJ | 1.5h | — | B done |
| **C** | `ContatoTab.tsx` + máscara tel/tel2 + email regex | 45min | — | B done |
| **C** | `EnderecoTab.tsx` + máscara CEP + ViaCEP proxy + autopreenche | 1.5h | — | B done + Wave-proxy |
| **C** | `ComercialTab.tsx` + autosave on blur | 1h | — | B done |
| **C** | `ClassificacaoTab.tsx` + tags multi-select + vip | 1h | — | B done |
| **C** | `BrLookupService.php` + `ClienteLookupController.php` + cache Redis | 1.5h | C.endereco | B done |
| **C** | Endpoints POST autosave 5 tabs em ContactController novo método ou Module Crm | 2h | — | B done |
| **C** | Pest `ClienteDrawerCadastroAutosaveTest` + `ClienteLookupCnpjCepTest` | 1.5h | — | C done |
| **D** | `OssTab.tsx` wrapper renderiza SalesTab por default + dropdown "Ver outras seções" | 1.5h | — | B done |
| **D** | "Ver ficha completa" botão expande Show.tsx em new tab `target=_blank` | 30min | — | D.1 |
| **D** | Pest `ClienteShowSupersededTest` — `/cliente/{id}` redirect + `/contacts/{id}` legacy intacto | 45min | — | D done |
| **E** | `IATab.tsx` 4 cards Copiloto + spinners + erro graceful | 2h | — | B done |
| **E** | `ClienteIaController.php` 4 endpoints via `LaravelAiSdkDriver` + quota | 2.5h | — | B done |
| **E** | RiscoController determinístico (não LLM) — port `clientes-tabs.jsx::RiscoCliente` | 1h | — | B done |
| **E** | Pest `ClienteIaQuotaTest` (mock LLM) | 1h | — | E done |
| **F** | `AuditoriaTab.tsx` timeline 6 tipos eventos + Exportar log | 1.5h | — | B done |
| **F** | `ClienteAuditoriaController.php` + `Contact` `LogsActivity` trait wire | 1h | — | F.1 |
| **F** | Pest `ClienteAuditoriaSpatieTest` LGPD Art. 18 | 1h | — | F done |
| **G** | Listagem turbinada Index: avatar HSL, tag chips coloridas, FrescorPill, Star pessoal, Saldo destacado | 2h | — | B done |
| **G** | 6 dropdowns filtro (Tipo/Status/UF/Tags/Sem compra/Com saldo) + sync URL | 2h | — | G.1 |
| **G** | Exportar CSV/XLSX | 1.5h | — | G done |
| **G** | Pest filtros + Star + Export | 1.5h | — | G done |
| **Z** | Wave-Final smoke Brave prod biz=1 + screenshot 8 tabs + SYNC_LOG.md update | 1h | — | A-G merged |
| **Z** | jana:health-check + brief-update + handoff | 45min | — | Z.1 |

**Total estimate:** ~35h IA-pair + margem 2x = **~70h elapsed** ≈ **2 semanas pra Wagner solo** ou **~1 semana** com Felipe paralelizando Wave G enquanto Wagner faz C/E.

---

## Recomendação pro Claude pai

**Caminho recomendado pra execução:**

1. **NÃO codar nada** até Wagner responder Q1-Q4 (Show.tsx destino + edição inline + migration estratégia + IA quota gating).
2. **Wave A primeiro** — Charter supersede + ADR de paradigma + RUNBOOK — atomic (~2.5h). Wagner aprova `accepted`. Sem essa base não dá pra justificar invasão dos Non-Goals do Show.charter.md v2.
3. **Wave B+C em paralelo** depois — esqueleto drawer 760 + 5 tabs cadastrais + BrLookupService — atomic (~10h elapsed).
4. **Wave D crucial** — definir o pattern do "OSs tab" sem regredir as 5 PRs Wave 5/Final mergeadas. Recomendação: **wrapper minimal** (SalesTab + dropdown "ver outras seções"). Não mover Activities/Persons/Subscriptions/Rewards pra dentro do drawer 760 nessa primeira iteração — fica em "ficha completa" via Show.tsx (modo expandido).
5. **Wave E+F em paralelo** depois — IA e Auditoria são desacopladas (~7h).
6. **Wave G last** — listagem turbinada visual; isolada das tabs do drawer, pode ir em PR separado mesmo (~7h).
7. **Wave Z fechamento** — smoke Brave + handoff + brief-update.

**O que confirmar com Wagner ANTES de codar Wave B:**

- **Q1** Show.tsx fica como modo "ficha completa" via botão "Imprimir ficha → tela cheia", ou DELETA após canary 30d biz=1 + biz=4?
- **Q2** 5 tabs cadastrais são **editáveis inline com autosave por blur**, ou são read-only + botão "Editar" → `/cliente/{id}/edit` Edit.tsx existente?
- **Q3** Migration estratégia: estender `contacts` (UPOS legacy) aditivamente, OU criar tabela paralela `clientes` (handoff §3)?
- **Q4** Tab IA: render default pra todos, ou gate por plano pago / quota `copiloto.admin.custos`?

**Skills que DEVEM ativar (Tier A always-on + Tier B contextuais):**

- Tier A: `brief-first` · `mcp-first` · `multi-tenant-patterns` · `commit-discipline` · `mwart-process` · `mwart-comparative V4` · `charter-first` · `wagner-protocol-enforce`
- Tier B contextuais: `inertia-defer-default` · `smoke-prod-evidence` · `tela-smoke-pos-merge` · `brief-update` · `RUNBOOK-onda-cowork` (12 fases) · `publication-policy` · `como-integrar` (sub-tabs Wave Final)

**ADRs canon relacionadas obrigatórias de ler ANTES de codar:**

- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 Constituição v2
- ADR 0104 processo MWART canônico
- ADR 0107 visual gate F1.5
- ADR 0110 Cockpit Pattern V2
- ADR 0114 Cowork loop formalizado
- ADR 0149 pattern reuse blueprint Cowork
- ADR 0167 errata 0130 handoff
- ADR NOVA Wave A (paradigma drawer 760)
- LICOES_F3_FINANCEIRO_REJEITADO.md (Wave Financeiro rejeitada 2026-05-09 — 6 meta-anti-padrões + 15 técnicos)

**Próximo passo recomendado:**

Wagner aprovar Q1-Q4 → Claude executa **Wave A** (Charter v3 + ADR paradigma + RUNBOOK + visual-comparison) → Wagner confirma `accepted` no ADR → Claude segue Wave B-G sob R11 (continuar até desfecho).
