---
page: /cliente (canon) · /contacts (legacy dual-render via config('mwart.cliente_index.enabled'))
component: resources/js/Pages/Cliente/Index.tsx
owner: wagner
status: live
last_validated: '2026-06-13'
charter_version: 10
parent_module: Cliente / Crm
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0094-constituicao-v2-7-camadas-8-principios'
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0107-emendation-0104-visual-comparison-gate-f3'
  - '0110-cockpit-pattern-v2-canon-list-detail'
  - '0114-prototipo-ui-cowork-loop-formalizado'
  - '0149-mwart-screen-pattern-reuse-cowork'
  - '0179-cliente-drawer-760px-substitui-show-fullpage'
  - '0187-constituicao-ui-v2-ponteiro-canon'
  - '0188-contacts-multi-type-flag-aditiva'
  - '0246-tipo-outros-default-migracoes-legacy'
supersedes: [Pages/Cliente/Show.charter.md v2]
tier: A

# ── ADR UI-0016 design contextualizado por persona ──────────────────────
# Skill personas-resolve (Tier A) carrega persona(s) automaticamente em edit.
personas_alvo:
  - depends_on_ramo                     # universal — varia por cliente final
  - daniela-martinho                    # secondary — frota Martinho pediu Veículos
  - kamila-martinho                     # secondary — admin/fiscal busca cliente
  - larissa-rota-livre                  # secondary — POS lookup cliente recorrente
job_principal: "buscar cliente recorrente em ≤3s; abrir drawer com info contextual em ≤1 clique"
fricoes_conhecidas:
  daniela: "veículos do cliente escondidos em sub-tab OSs (Fix #1694)"
  kamila: "saldo cliente sem drill-down por OS"
  larissa: "cliente fiado: precisa ir em Financeiro pra ver saldo"
metrica_sucesso:
  - "buscar cliente por nome parcial em ≤3 chars + ≤500ms"
  - "abrir drawer + ver KPIs primários (saldo, última OS, contato) em ≤1s"
  - "Daniela vê frota do cliente sem trocar de tab"
# ── /ADR UI-0016 ────────────────────────────────────────────────────────

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
  tabs: [identificacao, contato, endereco, comercial, classificacao, operacoes]
  header_chips: [placas, ia]   # auditoria saiu do chip → sub-aba de operacoes (2026-06-13)
  operacoes_subtabs: [ledger, sales, payments, documents, persons, subscriptions, rewards, auditoria]
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

## Goals (Drawer 760px — 6 tabs principais + chips Placas/IA)

- **Header drawer**: avatar grande, toggle PF/PJ, nome + "Pessoa jurídica · cadastrado há Xd", badge Ativo/Inativo/Bloqueado, botões "Imprimir ficha" + "Falar com Copiloto →" (= `/jana/chat?context=cliente:{id}`)
- **Tab Identificação**: Razão social/Nome, Fantasia (PJ), CNPJ + "Buscar CNPJ" (BrasilAPI proxy server-side), IE (PJ), Contato principal (PJ), Cargo (PJ), CPF/Nascimento/RG (PF) — máscaras + mod 11 + autosave on blur
- **Tab Contato**: tel/tel2 (máscara `(00) 0 0000-0000`), email (regex), site, canal preferido (radio: whatsapp/email/telefone/presencial) — autosave on blur
- **Tab Endereço**: CEP + ViaCEP proxy server-side ao blur autopreenche, endereço/número/complemento/bairro/cidade/UF — autosave on blur
- **Tab Comercial**: limite crédito, prazo padrão (dias), tabela preço (padrao/varejo/atacado/parceiro), pgto padrão (pix/boleto/cartão/dinheiro/transferência), obs comercial textarea — autosave on blur
- **Tab Classificação**: segmento (radio: varejo/atacado/agência/corporativo/evento/governo), tags multi-select (9 valores), status (ativo/inativo/bloqueado), VIP toggle — autosave on blur
- **Tab Operações** (`OssTab`): rail vertical com sub-abas `_show/LedgerTab`, `SalesTab`, `PaymentsTab`, `DocumentsTab`, `PessoasContatoTab`, `SubscriptionsTab`, `RewardPointsTab` + **Auditoria** (`_drawer/AuditoriaTab` — integrada 2026-06-13). `ActivitiesTab` removido (duplicava Auditoria — mesma fonte Spatie)
- **Chip IA**: 4 cards Copiloto (Resumo relacionamento / Reavaliar segmento+tags / Próxima ação / Score risco determinístico) — default ON pra todos (sem gate quota)
- **Sub-aba Auditoria** (em Operações): timeline Spatie ActivityLog v4.8 com 6+ tipos eventos — `forSubject(Contact $contact)` filtrado por business_id. Wagner 2026-06-13: saiu do chip (virou sub-aba de Operações) + **botão "Exportar log" removido** (acesso LGPD Art.18 pela própria timeline; rota `/auditoria/export` mantida no backend)

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
- `Pages/Cliente/_components/Pills.tsx` (StatusPill, TipoPill, TagChip, FrescorPill)
- `Pages/Cliente/_components/Avatar.tsx` (HSL hash determinístico)
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

---

## v8 · 2026-06-03 · ADR 0246 — 5ª categoria "Outros" (append-only)

**Trigger:** Wagner 2026-06-03, conversa migração WR Comercial Delphi → oimpresso. Análise da tabela `PESSOAS` legacy revelou **12.233 de 13.703 cadastros com `TIPO='O'`** sem CPF/CNPJ obrigatório. Wagner aprovou usar a aba **Classificação existente** (não criar tela nova de conversão) — `ContactRoleType` ganha 5º valor `'other'`, `PAPEL_OPTIONS` em `ClassificacaoTab.tsx` ganha 5º chip clicável, `SLOT2_TABS` em `Index.tsx` ganha 6ª aba `Outros`. Conversão `Outros ↔ Cliente/Fornecedor/Equipe/Representante` é nativa via toggle dos chips — sem botão dedicado.

### Goals novos (append-only · v7 preservados)

- **6ª aba "Outros"** em `SLOT2_TABS` (após "Repr.") · ícone `Layers` · `href: /cliente?type=other`
- **5º chip "Outros"** em `PAPEL_OPTIONS` na aba Classificação · pattern toggle idêntico aos 4 existentes
- **Title H1 + CTA "Novo outros"** quando `?type=other` ativo · `ROLE_TITLE.other` adicionado
- **Counters tab subnav** estende pra 6 valores (`all/customer/supplier/employee/representative/other`)
- **CPF/CNPJ permanece nullable** em `StoreContactRequest.rules['cpf_cnpj']` (já era) → tipo Outros funciona sem documento sem mudar validation
- **Endpoint `/cliente/{id}/papeis`** estendido pra aceitar `is_other` no whitelist · invariante "≥1 papel ativo" passa de 4 pra 5 papéis

### Schema novo (ADR 0246)

Migration aditiva `2026_06_03_120000_add_is_other_flag_to_contacts.php`:

```sql
ALTER TABLE contacts ADD is_other TINYINT(1) NOT NULL DEFAULT 0 AFTER is_representative;
CREATE INDEX idx_contacts_biz_other ON contacts(business_id, is_other);
```

Sem backfill (nenhum cadastro existente é "Outros"). Migration Wave 30 (importer WR2) seta `is_other=1` pros legacy que não casam com customer/supplier/employee/representative.

### Invariantes (Tier 0 IRREVOGÁVEL — ADR 0246)

1. `type` enum **permanece** (UPOS legacy)
2. Flags **aditivas, nunca exclusivas** — `is_customer=1 AND is_other=1` permitido (prospect promovido a cliente mantém histórico)
3. Índice composto `(business_id, is_other)` — multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
4. Slot 2 `?type=other` é leitura agregada · CTA "Novo outros" cadastra com `is_other=1` default
5. Conversão `Outros → Cliente/Fornecedor/etc` é toggle chip (não tela dedicada)

### Non-Goals v8

- ❌ **Botão "Converter para..." dedicado** (Wagner 2026-06-03: chips Classificação já cobrem)
- ❌ **Service `ContactTypeConversionService`** novo (reusa endpoint `/papeis` existente)
- ❌ **Validação `required_unless`** pra CPF/CNPJ (regra já era nullable em StoreContactRequest)
- ❌ **Sub-tipos "Outros"** (prospect/lead/feira) — Onda futura se demanda aparecer

### Refs v8

- [ADR 0246 · Tipo "Outros" como categoria default em migrações legacy](../../../memory/decisions/0246-tipo-outros-default-migracoes-legacy.md)
- [Migration `2026_06_03_120000_add_is_other_flag_to_contacts.php`](../../../database/migrations/2026_06_03_120000_add_is_other_flag_to_contacts.php)
- [memory/research/clientes-legacy-officeimpresso/01-wr-sistemas/02-schema-real-2026-06-03.md](../../../memory/research/clientes-legacy-officeimpresso/01-wr-sistemas/02-schema-real-2026-06-03.md) — profile WR2 motivador
- Conversa Wagner 2026-06-03: insight "aba Classificação já tem isso pronto, só falta o chip Outros"

---

## v9 · 2026-06-08 · Excluir contato pela tela (menu ⋮) + consolidação no drawer (append-only)

**Trigger:** Wagner 2026-06-08. Primeiro pediu "arrumar os botões da contacts" — o menu ⋮ da linha tinha 5 itens, 3 apontando pra Blade legacy (`/contacts/{id}`, `/contacts/{id}/edit`, `/contacts/ledger`), ícone `Eye` duplicado e um "Excluir" `disabled` permanente (botão morto). Consolidado no drawer 760 (PR #2420): **Ver detalhes · Editar · Extrato** abrem o drawer na aba certa. Depois Wagner: "exclusão de contato pela tela, precisa" → "Excluir" volta **funcional**.

### Goals novos (append-only · v8 preservados)

- **Menu ⋮ da linha consolidado no drawer** (ADR 0179): "Editar" → drawer aba Identificação · "Extrato" → drawer aba Operações › Extrato (sub-aba `ledger`). Removidos os links Blade legacy "Página completa" e o "Excluir" morto.
- **Excluir contato (soft delete)** via `DELETE /contacts/{id}` (`ContactController::destroy`, AJAX JSON `{success,msg}`):
  - Gated por `permissions.delete` (`customer.delete || supplier.delete`) — backend revalida o mesmo `can()`.
  - **Escondido pro `is_default`** (consumidor/fornecedor walk-in) no front; `destroy()` também protege server-side.
  - **AlertDialog de confirmação** (ação destrutiva nunca em 1 clique) + toast sonner de sucesso/erro.
  - Pós-sucesso: fecha o drawer do excluído (se aberto) + `router.reload(['customers','kpis','tab_counts'])`.

### Invariantes (Tier 0 IRREVOGÁVEL)

1. Exclusão é **soft delete** (Contact `use SoftDeletes`) — reversível, LGPD-friendly.
2. `destroy()` **bloqueia** se houver qualquer `Transaction` do contato (venda/compra/OS) — devolve `success:false` + msg; nada é apagado.
3. Escopo `business_id` em todas as queries de exclusão — multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
4. `is_default` (walk-in) **nunca** é excluído.
5. Toda exclusão grava `ActivityLog` `contact_deleted` (LGPD Art. 18) + desabilita login de usuários associados (`allow_login=0`).

### Non-Goals v9

- ❌ **Merge/mesclar contatos duplicados** pela tela — segue na rota legacy `/contacts/duplicates` (Non-Goal v7 mantido; esforço maior, futuro).
- ❌ **Exclusão em batch** (mantém Non-Goal "1 por vez").
- ❌ **Hard delete / forceDelete** pela UI — soft delete only.
- ❌ **Restaurar excluído** pela tela (Onda futura se demanda aparecer).

### Refs v9

- PR #2420 (consolidação do menu ⋮ no drawer · mergeado 2026-06-08)
- `ContactController::destroy($id)` (trava transação + `is_default` + ActivityLog + business_id scope)
- `Route::resource('contacts')` → `DELETE /contacts/{id}` (`contacts.destroy`)
- Conversa Wagner 2026-06-08: "arrumar os botões" → "exclusão de contato pela tela, precisa"
