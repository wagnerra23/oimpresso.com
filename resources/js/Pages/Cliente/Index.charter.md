---
page: /cliente (canon) · /contacts (legacy dual-render via config('mwart.cliente_index.enabled'))
component: resources/js/Pages/Cliente/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-24
charter_version: 6
parent_module: Cliente / Crm
related_adrs: [0093, 0094, 0104, 0107, 0110, 0114, 0149, 0179, 0187]
supersedes: [Pages/Cliente/Show.charter.md v2]
tier: A
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/clientes/ (HTML + 13 .jsx)"
  blueprint_screenshot_approval: "Wagner 2026-05-21 aprovou opção A (drawer 760px)"
  derived_screens: [Index, Drawer]
  prototype_score: "KB-9.75 9,4/10 (Refinos #1+#2+#3 aplicados)"
drawer_pattern:
  width: 760px
  position: lateral-right
  trigger: "Click linha tabela OU deeplink /cliente/{id} → router.visit('/cliente?contact_id={id}&tab=identificacao')"
  close: "X · Esc · click backdrop"
  tabs: [identificacao, contato, endereco, comercial, classificacao, oss, ia, auditoria]
---

# Page Charter — /cliente (Index + Drawer 760px)

## Mission

Listagem densa de clientes com drawer lateral 760px abrindo ao clicar em qualquer linha. Drawer cobre 100% do ciclo cadastral (5 tabs com autosave on blur) + visão operacional ("OSs" wrapping das 8 sub-tabs Wave Final 2026-05-21: Extrato/Vendas/Pagamentos/Docs/Atividades/Pessoas/Assinaturas/Pontos) + IA (4 cards Copiloto via Modules/Jana) + Auditoria LGPD Art. 18 (Spatie ActivityLog v4.8). Paridade Cowork score 9,4/10.

## Goals (Index — listagem)

- Header rico: "Clientes" + count "X cadastrados · Y ativos" + busca + botões Importar/Exportar/Novo
- 6 dropdowns filtro: Tipo (PF/PJ) · Status (ativo/inativo/bloqueado) · UF (27) · Tags (multi) · Sem compra há (15d/30d/90d/180d/365d) · Com saldo (sim/não/devedor)
- Tabela densa: avatar colorido-hash HSL determinístico por id, Nome + sub-nome (fantasia/contato), Tipo pill, Documento mascarado, Cidade/UF, FrescorPill (fresc/recente/distante/frio com cor), Saldo destacado vermelho se devedor, Tags chips coloridas (9 valores semânticos), Star pessoal localStorage
- KB-9.75 Slice A atalhos (`⌘K` · `?` · `J/K` · Enter · `/`) — preservar PR #1309
- "32 clientes encontrados" count inline
- Inertia::defer em customers + kpis (skill `inertia-defer-default`)
- ~~**PTDP Onda 1 (v4 · 2026-05-24):**~~ ❌ **REVOGADA (v6 · 2026-05-24)** · Wagner reprovou BrunaGreeting + SavedViews em validação visual produção · removidos `Components/clientes/BrunaGreeting.tsx` e `SavedViews.tsx` · charter mantém histórico (append-only)
- **PTDP Onda 2 (v5 · 2026-05-24):** `<KpiStripClickable>` 5 cards-filtro (Clientes ativos · VIPs · Com saldo · Sem compra 90d · Novos este mês) substitui 4 KpiCard estáticos Wave G · clique aplica filtro substitutivo · toggle 2x desativa · counts client-side pros estimados (vips/sem90/novos · Onda 3 plug backend dedicado). **v6 nota:** mutex com SavedViews removido (não há mais SavedViews)

## Goals (Drawer 760px — 8 tabs)

- **Header drawer**: avatar grande, toggle PF/PJ, nome + "Pessoa jurídica · cadastrado há Xd", badge Ativo/Inativo/Bloqueado, botões "Imprimir ficha" + "Falar com Copiloto →" (= `/jana/chat?context=cliente:{id}`)
- **Tab Identificação**: Razão social/Nome, Fantasia (PJ), CNPJ + "Buscar CNPJ" (BrasilAPI proxy server-side), IE (PJ), Contato principal (PJ), Cargo (PJ), CPF/Nascimento/RG (PF) — máscaras + mod 11 + autosave on blur
- **Tab Contato**: tel/tel2 (máscara `(00) 0 0000-0000`), email (regex), site, canal preferido (radio: whatsapp/email/telefone/presencial) — autosave on blur
- **Tab Endereço**: CEP + ViaCEP proxy server-side ao blur autopreenche, endereço/número/complemento/bairro/cidade/UF — autosave on blur
- **Tab Comercial**: limite crédito, prazo padrão (dias), tabela preço (padrao/varejo/atacado/parceiro), pgto padrão (pix/boleto/cartão/dinheiro/transferência), obs comercial textarea — autosave on blur
- **Tab Classificação**: segmento (radio: varejo/atacado/agência/corporativo/evento/governo), tags multi-select (9 valores), status (ativo/inativo/bloqueado), VIP toggle — autosave on blur
- **Tab OSs**: wrapper das 8 sub-tabs Wave Final (`_show/LedgerTab`, `SalesTab`, `PaymentsTab`, `DocumentsTab`, `ActivitiesTab`, `PessoasContatoTab`, `SubscriptionsTab`, `RewardPointsTab`) via sub-tabs aninhadas verticais (decisão final layout na Wave D)
- **Tab IA**: 4 cards Copiloto (Resumo relacionamento / Reavaliar segmento+tags / Próxima ação / Score risco determinístico) — default ON pra todos (sem gate quota)
- **Tab Auditoria**: timeline Spatie ActivityLog v4.8 com 6+ tipos eventos + botão Exportar log — `forSubject(Contact $contact)` filtrado por business_id

## Non-Goals

- ❌ Modal sobre modal (Falar com Copiloto abre nova rota, não nested)
- ❌ Show.tsx full-page (DELETADO no mesmo PR — Q1)
- ❌ Edição em batch (1 cliente por vez)
- ❌ Tab "Imprimir ficha" embutida — botão dispara `window.print` com CSS @media print
- ❌ ViaCEP/BrasilAPI client-side (proxy server-side obrigatório com cache Redis)
- ❌ Tabela paralela `clientes` (Q3: estende `contacts` UPOS aditivamente)
- ❌ Gate quota IA (Q4: Default ON; Wagner pode regredir depois)

## UX Targets

- p95 first-paint Index < 600ms (Inertia::defer customers/kpis)
- p95 drawer abrir < 200ms (sub-tabs Inertia partial reload only:[tab])
- p95 autosave round-trip < 400ms (POST PATCH + optimistic UI)
- p95 ViaCEP/BrasilAPI < 800ms (proxy cache Redis hit) · < 2.5s (cache miss + fallback)
- p95 IA card render < 6s (Brain B Sonnet/Haiku — graceful spinner)
- Viewport 1280×1024 (Larissa biz=4) — drawer 760 + AppShellV2 sidebar 240 + main padding cabe sem scroll horizontal (Pest charter test)

## Automation Anti-hooks

- ❌ Não dispara emails ao abrir drawer
- ❌ Não emite log "viewed" — Spatie ActivityLog SÓ em mutate (LGPD)
- ❌ Não acessa Contact de outro business_id (ADR 0093 Tier 0 IRREVOGÁVEL)
- ❌ CPF/CNPJ mascarado server-side (`tax_number_masked`); telefone idem
- ❌ Não chama LLM no score risco — determinístico (handoff §5.4)
- ❌ Não envia "Falar com Copiloto" sem confirmação humana — abre rota Jana, não dispara mensagem

## Sub-components

- `_drawer/IdentificacaoTab.tsx` (Wave C)
- `_drawer/ContatoTab.tsx`
- `_drawer/EnderecoTab.tsx`
- `_drawer/ComercialTab.tsx`
- `_drawer/ClassificacaoTab.tsx`
- `_drawer/OssTab.tsx` (wrapper Wave D)
- `_drawer/IATab.tsx` (Wave E)
- `_drawer/AuditoriaTab.tsx` (Wave F)
- `_show/*` (8 arquivos Wave Final 2026-05-21 — reusados via OssTab wrapper)
- `Components/clientes/Pills.tsx` (StatusPill, TipoPill, TagChip, FrescorPill)
- `Components/clientes/Avatar.tsx` (HSL hash determinístico)
- `Lib/br-mask.ts` · `Lib/br-validate.ts` · `Lib/avatar.ts` · `Lib/relDate.ts`

## Refs

- Dossiê wagner-understand: `memory/sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md`
- ADR 0179 paradigma drawer 760
- Charter superseded: `Pages/Cliente/Show.charter.md` v2
- Protótipo Cowork: `prototipo-ui/prototipos/clientes/`
- Backend: `app/Http/Controllers/ContactController.php` + `Modules/Crm/Http/Controllers/ClienteLookupController.php` (NOVO) + `ClienteIaController.php` (NOVO) + `ClienteAuditoriaController.php` (NOVO)
- Wave Final 2026-05-21: PRs #1298-1307 (paridade Blade — preservados via OssTab wrapper)
- KB-9.75 Slice A: PR #1309 (⌘K + cheat-sheet + J/K nav — preservado)

---

## v7 · 2026-05-24 · Onda 3 PTDP — Slot 2 PT-01 multi-type contatos (append-only)

**Trigger:** Wagner 2026-05-24 ROTA LIVRE biz=4 validação visual `/cliente`:
> "no Delphi (WR Comercial legacy) tenho cadastro de tipo de contato, mesmo contato pode ser classificado como cliente, fornecedor, funcionário — evita ter o mesmo cadastro em tabelas separadas"

3 caminhos arquiteturais (UPOS single-type vs Delphi multi-flag) analisados. Wagner aprovou **Opção B** ("pode fazer") — flags aditivas backward-compat.

### Goals novos (append-only · v6 preservados)

- **Slot 2 PT-01 ModuleTopNav (sub-tabs ghost)** entre PageHeader e KpiStrip — 5 tabs:
  - Clientes (`?type=customer`) · ícone Users
  - Fornecedores (`?type=supplier`) · ícone Truck
  - Funcionários (`?type=employee`) · ícone Briefcase
  - Representantes (`?type=representative`) · ícone UserCheck
  - Todos (`?type=all`) · ícone List (leitura agregada · sem CTA "Novo")
- **Title H1 + subtítulo + CTA "Novo X"** mudam por papel ativo (`ROLE_TITLE` map)
- **Backend filter** `is_X=1` (com fallback `type='X'` durante migration roll-out)
- **Backward-compat UPOS** total — `contacts.type` enum permanece authoritative pra Sells/Compras/Folha legacy
- **Pre-fill CTA "Novo X"** abre `/contacts/create?type=customer|supplier|employee|representative` (rota UPOS Blade legacy preservada)

### Schema novo (ADR 0188)

Migration aditiva `2026_05_24_200000_add_role_flags_to_contacts.php`:

```sql
ALTER TABLE contacts ADD is_customer       TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE contacts ADD is_supplier       TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE contacts ADD is_employee       TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE contacts ADD is_representative TINYINT(1) NOT NULL DEFAULT 0;

-- Backfill papel principal
UPDATE contacts SET is_customer=1 WHERE type='customer'; -- (idem outros)

-- Índices compostos Tier 0 (ADR 0093 IRREVOGÁVEL)
CREATE INDEX idx_contacts_biz_customer ON contacts(business_id, is_customer);
-- (idem supplier/employee/representative)
```

### Invariantes (Tier 0 IRREVOGÁVEL — ADR 0188)

1. `type` enum **permanece** (UPOS legacy 200+ telas Blade)
2. Backfill **one-way** `type=X` → `is_X=1` (idempotente)
3. Flags **aditivas, nunca exclusivas** — Wagner Rocha cliente+representante = `is_customer=1 AND is_representative=1` (mesma row)
4. Índices compostos `(business_id, is_X)` — multi-tenant Tier 0 IRREVOGÁVEL [ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
5. Slot 2 `?type=all` é **leitura agregada** — CTA "Novo" não renderiza (Wagner escolhe papel explícito)

### Non-Goals v7

- ❌ **Pivot `contact_types` table** (Opção C rejeitada · ROI baixo em PME 100 cadastros/mês)
- ❌ **Drawer 760 seção "Papéis" com 4 checkboxes** — Onda 4 futura (scope-only · ADR 0188 §Plano-8)
- ❌ **Merge automático de cadastros duplicados** (Wagner Rocha id=42 cliente + id=99 repr) — script manual futuro
- ❌ **Reescrita queries UPOS legacy** (Sells/Compras/Folha continuam `WHERE type='X'`)

### Refs v7

- [ADR 0188 · Contatos multi-type · flags aditivas](../../../memory/decisions/0188-contacts-multi-type-flag-aditiva.md) (canonical, aceita Wagner 2026-05-24)
- [Migration `2026_05_24_200000_add_role_flags_to_contacts.php`](../../../database/migrations/2026_05_24_200000_add_role_flags_to_contacts.php)
- [ADR UI-0013 · Constituição UI v2 · 4 camadas](../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) (Slot 2 PT-01 canônico)
- [PT-01 · Lista canônica](../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
- [ADR 0040 · ModuleTopNav sub-tabs ghost](../../../memory/decisions/0040-moduletopnav-subtabs-ghost.md)
- Delphi WR Comercial · flags bool por papel (pattern legacy 15 anos)
- HANDOFF_CLIENTES.md (Cowork chat1 + validação produção Wagner 2026-05-24)
