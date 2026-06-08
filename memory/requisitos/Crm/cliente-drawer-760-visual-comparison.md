---
slug: cliente-drawer-760-visual-comparison
title: "Cliente — Comparativo visual drawer 760px lateral vs protótipo Cowork KB-9.75"
type: visual-comparison
module: Crm
visual_comparison: cliente-drawer-760px
adr: 0179
charter: resources/js/Pages/Cliente/Index.charter.md v3 (draft → publish nesta Wave A)
prototype_source: prototipo-ui/prototipos/clientes/ (KB-9.75 9,4/10 — Refinos #1 + #2 + #3)
inertia_target: resources/js/Pages/Cliente/Index.tsx (ClienteSheet expandido 480→760)
controller: app/Http/Controllers/ContactController.php::index/show + Modules/Crm/Http/Controllers/{ClienteLookup,ClienteIa,ClienteAuditoria}Controller (NOVO)
stories: [US-CRM-068, US-CRM-069, US-CRM-070, US-CRM-071, US-CRM-072]
status: validated-prod
approved_by: "Wagner@WR2 Sistemas biz=1 2026-05-21 17:12 BRT via Chrome MCP smoke ao vivo"
date: 2026-05-21
last_validated: 2026-05-21
methodology: mwart-comparative V4 + precisao-literal V1 (6 fases canon)
current_state_score_pre_wave: ~28/100 (Wagner avaliação 2026-05-21 13:20 BRT — pré-Wave A)
current_state_score_post_wave_subjective: ~88/100 (subjetivo pós-Wave G merge 16:50 BRT)
current_state_score_post_wave_literal: 95% (precisao-literal rigorosa pós Z-2.1 17:30 BRT)
final_score: 95% (paridade Cowork KB-9.75 9,4/10 ATINGIDA)
target_score: ">= 90/100 (paridade Cowork KB-9.75)"
plug_in_design_critique: smoke prod biz=1 OK — sem regressão CI
prs_entregues: [1339, 1347, 1348, 1349, 1351, 1355, 1356]
smoke_screenshots:
  - ss_7608ncv1k (listagem turbinada 35 clientes)
  - ss_3235a993m (drawer Tab Identificação)
  - ss_26803ikz8 (drawer Tab Endereço)
  - ss_0362npp0t (drawer Tab IA — 4 cards)
  - ss_1563qg36u (drawer Tab Auditoria — LGPD)
related_adrs: [0093, 0094, 0104, 0107, 0110, 0114, 0149, 0167, 0179]
session_ref: memory/sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md
---

# Visual Comparison — Cliente drawer 760px lateral vs protótipo Cowork

> **Gate F1.5 obrigatório** (ADR 0107 visual gate + ADR 0114 Cowork loop formalizado). Wagner aprova **SCREENSHOT do drawer aberto em prod biz=1** (NÃO tabela markdown). Substitui paradigma "Show.tsx full-page" por "drawer 760 sobre Index.tsx".

## ⚠️ Update 2026-05-21 16:23 BRT — Wave A-G entregue (3 PRs encadeados)

| Wave | PR | Dimensões fechadas |
|---|---|---|
| **A** | [#1339](https://github.com/wagnerra23/oimpresso.com/pull/1339) | docs canon (ADR 0179 + charters + RUNBOOK + este arquivo) |
| **B+C** | [#1342](https://github.com/wagnerra23/oimpresso.com/pull/1342) | 6 Drawer estrutura · 7 Tabs do drawer · 8 Form fields BR · 10 HISTÓRICO strip · 11 Header drawer (parcial) |
| **D+E+F+G** | [#1344](https://github.com/wagnerra23/oimpresso.com/pull/1344) | 1 Densidade · 2 Paleta semântica · 3 Tipografia · 4 Filtros 6 dropdowns · 5 Tabela colunas · 9 IA cards · 10 Auditoria timeline · 14 Performance · 15 Brand |

**Nota estimada pós-código:** ~90/100 (subjeitо a screenshot real Wagner pós-merge)
**Pendência única:** Wave Z-2 — Wagner aprovar SCREENSHOT prod biz=1 com `MWART_CLIENTE_INDEX=true`

## 0. Resumo executivo

Migração de paradigma: a tela `/cliente` deixa de abrir `Show.tsx` em rota dedicada (`/cliente/{id}` full-page com 8 tabs operacionais) e passa a abrir um **drawer lateral 760px** sobre `Index.tsx` com **8 tabs cadastrais** (Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria). Origem: protótipo Cowork `prototipo-ui/prototipos/clientes/` aprovado por Wagner com score KB-9.75 9,4/10.

| # | Dimensão (15 V4 + 3 extras) | Atual | Target | Gap | Wave fix |
|---|---|:-:|:-:|:-:|:-:|
| 1 | Densidade & hierarquia visual (listagem) | 60/100 | 90 | 30 | G |
| 2 | Paleta + cor semântica (avatar/tag/frescor/saldo) | 15/100 | 95 | 80 | G |
| 3 | Tipografia + microcopy | 70/100 | 90 | 20 | G |
| 4 | Filtros + busca (6 dropdowns + ⌘K) | 50/100 | 95 | 45 | G |
| 5 | Tabela colunas (cadastral+frescor vs operacional) | 40/100 | 95 | 55 | G |
| 6 | Drawer estrutura + header rico | 25/100 | 95 | 70 | B |
| 7 | Tabs do drawer (8 cadastrais) | 10/100 | 95 | 85 | B+C+D+E+F |
| 8 | Form fields + inputs BR (máscaras + ViaCEP) | 5/100 | 90 | 85 | C |
| 9 | IA cards Copiloto (4 cards) | 0/100 | 90 | 90 | E |
| 10 | Auditoria timeline LGPD | 5/100 | 85 | 80 | F |
| 11 | HISTÓRICO strip / KPIs operacionais | 50/100 | 90 | 40 | B |
| 12 | Acessibilidade + atalhos (KB-9.75 + 1-8) | 85/100 | 95 | 10 | (PR #1309 + G) |
| 13 | Empty + loading + error states (6 estados) | 50/100 | 90 | 40 | B+G |
| 14 | Performance percepção (defer + partial reload) | 70/100 | 90 | 20 | B |
| 15 | Brand + identidade Oimpresso (print + voice) | 75/100 | 95 | 20 | B+F |

**Pontuação atual (média ponderada simples 15 dim):** ~42/100 ponderado · **28/100** quando aplicado o peso de Wagner (~28/100, paradigma errado domina a nota).
**Target Wave A-G:** ≥ 90/100 (paridade Cowork KB-9.75).

> Gap dominante: dimensões 7 + 8 + 9 (tabs cadastrais + form BR + IA) representam **260 pontos de gap** dos ~520 totais. Sem elas o paradigma inteiro está errado — daí Wagner avaliar o conjunto em ~28/100 e não na média 42 simples.

---

## 1. Densidade & hierarquia visual (listagem) — 60/100 → 90

**Cowork:** row height ~52px IBM Plex Sans · `text-sm` body + `text-xs` sub-nome + `text-[10px] uppercase tracking-widest` labels · vazio negativo padding 24px laterais · hierarquia clara nome→doc→cidade.
**Atual:** row height ~64px (sobra), `px-4 py-3` ok mas KPIs cards `mt-6 gap-4` mais espaçados que Cowork; tipografia padrão shadcn sem o IBM Plex; nome sem sub-nome (cidade/UF inline).
**Gap:** -10 em padding (>32px atual vs 24px alvo), -10 em ausência de sub-nome cidade/UF, -10 em line-height generoso demais.
**Wave fix:** G — apertar `py-3` → `py-2.5`, adicionar `<div className="text-[11px] text-muted-foreground/70 leading-tight">{cidade}/{uf}</div>` na coluna Cliente.

## 2. Paleta + cor semântica — 15/100 → 95

**Cowork (Avatar HSL hash determinístico):** `avatarFor(id)` deriva hue de `id % 360` com `hsl(hue, 60%, 55%)` — ~12 cores distinguíveis garantem reconhecimento visual instantâneo por cliente.
**Cowork (Tag chips):** 9 cores semânticas (`varejo` amarelo, `atacado` roxo, `corporativo` azul, `evento` rosa, `parceiro` verde, `agência` índigo, `governo` vermelho, `vip` dourado, `reincidente` laranja).
**Cowork (FrescorPill):** 4 estados (`fresco` verde 0-30d, `recente` azul 31-90d, `distante` âmbar 91-180d, `frio` cinza 180d+).
**Cowork (Saldo devedor):** `text-red-700 tabular-nums font-semibold` quando >0.
**Atual:** Avatar único gradient stone monocromático (`Avatar initial` na linha 457 do Index.tsx); zero tag chips; nenhum FrescorPill (apenas status pill `late/active/idle`); saldo `valor_aberto` em `text-foreground` neutro sem semântica.
**Gap dominante:** -80. Avatar = 0/95, Tag = 0/95, Frescor = 30/95 (proxy via status), Saldo = 35/95 (formatBRL ok mas sem cor).
**Wave fix:** G — criar `Lib/avatar.ts::avatarFor(id)` + `Components/clientes/TagChip.tsx` + `Components/clientes/FrescorPill.tsx` + condicional `text-red-700` no saldo.
**Evidência:** `prototipo-ui/prototipos/clientes/clientes-icons.jsx::avatarFor`, `clientes-listagem.jsx::TagChip`, `clientes-975.jsx::FrescorPill`.

## 3. Tipografia + microcopy — 70/100 → 90

**Cowork:** font-family IBM Plex Sans 400/500/600/700; header `text-2xl/font-semibold tracking-tight`; subtítulo inline "32 cadastrados · 29 ativos" `text-sm text-muted-foreground`; labels uppercase `text-[10px] tracking-widest`; microcopy curto e operacional ("Falar com Copiloto →", "Imprimir ficha", "Revalidar cadastro").
**Atual:** font padrão shadcn (Geist/Inter); header `text-2xl/font-semibold` ✅ idêntico; subtítulo presente mas longo "Lista de clientes com KPIs de relacionamento e drawer de detalhes ao clicar" (linha 308-310) — deveria virar contador inline; labels uppercase `text-[10px]` ✅ no SheetKpi.
**Gap:** -20. Falta IBM Plex (mas tem proxy via Geist); subtítulo verbose; microcopy operacional 90% pendente (apenas "Importar" + "Novo cliente" hoje).
**Wave fix:** G — encurtar subtítulo pra "{total} cadastrados · {ativos} ativos · {com_saldo} com saldo"; preservar font shadcn (não trocar pra IBM Plex agora — Tier 2 estético).

## 4. Filtros + busca — 50/100 → 95

**Cowork:** 6 dropdowns FilterDropdown (Tipo · Status · UF · Tags · Sem compra há · Com saldo) + busca text + ⌘K palette + ActiveChip que remove filtro individual; sincroniza URL via querystring.
**Atual:** 4 pílulas radio mutuamente exclusivas (Todos/Ativos/Atrasados/Sem OS, linhas 294-299) + busca text + ⌘K Slice A (PR #1309) ✅; **sem** UF/Tags/Sem compra/Com saldo; **sem** ActiveChip removível individual; querystring básico.
**Gap:** -45. Pílulas atual cobrem ~50% do "Status" dropdown Cowork; falta 5 dropdowns adicionais; falta ActiveChip; KB-9.75 ⌘K já completo.
**Wave fix:** G — substituir as 4 pílulas por 6 FilterDropdown + ActiveChip horizontal scrollable + sync URL via `router.get('/cliente', { ...filters })` debounced.
**Evidência:** `clientes-listagem.jsx::FilterDropdown` + `ActiveChip`.

## 5. Tabela colunas — 40/100 → 95

**Cowork colunas (cadastral+frescor):** [Avatar HSL · Nome+sub-nome · TipoPill · Documento mascarado · Cidade/UF · ÚltimaCompra + FrescorPill · Saldo devedor · Tags + Star pessoal].
**Atual colunas (operacional):** [Avatar mono · Cliente (nome+doc) · Contato (mobile) · OS · Abertas · ValorAberto · Status · ÚltimaOS · Ações ⋯] (linhas 417-426).
**Mismatch crítico:** as 4 últimas colunas da atual (OS/Abertas/ValorAberto/UltimaOS) refletem paradigma operacional WR2 SC (oficina), o Cowork reflete paradigma cadastral CRM puro. **A wave G NÃO deve apagar** os agregados operacionais — Larissa biz=4 ROTA LIVRE depende deles. Solução: ADICIONAR avatar HSL + tag chips + star + FrescorPill + saldo colorido, MANTER OS/Abertas/UltimaOS como colunas secundárias toggleable.
**Gap:** -55. Avatar mono → HSL (-15), falta Tags+Star (-15), falta FrescorPill (-15), saldo sem cor semântica (-10).
**Wave fix:** G — colunas finais [Avatar HSL · Nome+sub · TipoPill · Documento · Cidade/UF · FrescorPill+ÚltimaOS · Saldo colorido · OS · Tags+Star · ⋯]. Largura útil 1024px (Larissa) confere.

## 6. Drawer estrutura + header rico — 25/100 → 95

**Cowork:** Sheet `w-[760px] sm:max-w-[760px]` lateral direito · header com avatar grande 56px HSL + toggle PF/PJ + badge `Ativo`/`Inativo`/`Bloqueado` + 2 CTAs ("Imprimir ficha" outline · "Falar com Copiloto →" primary) + `text-2xl` nome + sub-nome documento + cidade/UF.
**Atual:** Sheet `w-[480px] sm:max-w-[480px]` (linha 758) · header só `<SheetTitle>{contact?.name}` + `<SheetDescription>{tax_number_masked}` + 4 KPI cards 2x2 (Total OS / Em aberto / Atrasadas / Valor) + section "Contato" `<dt>/<dd>` + 2 botões ("Página completa" outline · "Editar" primary).
**Gap:** -70. Largura 480 → 760 (impacto cascata em todas as tabs novas), sem toggle PF/PJ, sem badge status, CTAs apontam pra rotas legacy `/contacts/{id}` (Página completa) e `/contacts/{id}/edit` (Editar) — Cowork remove edit page (5 tabs cadastrais inline editáveis) e adiciona "Falar com Copiloto →" + "Imprimir ficha".
**Wave fix:** B — expandir `w-[760px]`; refazer header com avatar 56px HSL + toggle PF/PJ + badge + 2 CTAs novas. Botão "Página completa" vira "Imprimir ficha" (preserva Show.tsx via target=_blank).
**Evidência:** `clientes-drawer.jsx::ClienteDrawer` shell.

## 7. Tabs do drawer (8 cadastrais) — 10/100 → 95

**Cowork (8 cadastrais):** Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria.
**Atual (Show.tsx 8 operacionais):** Extrato · Vendas · Pagamentos · Documentos & Notas · Atividades · Pessoas · Assinaturas · Pontos (linhas 80-112 Show.tsx).
**Atual (ClienteSheet drawer):** apenas 4 KPIs + dl Contato + 2 botões — **ZERO tabs**.
**Mismatch de paradigma:** o conjunto 8 do Cowork é **cadastro CRM** (editar dados do cliente); o conjunto 8 do Show.tsx é **histórico transacional** (consultar movimentação). Wagner aprovou opção (A): **Cowork ganha** — as 8 cadastrais entram, mas a tab "OSs" do Cowork vira wrapper que encaixa as 5 sub-tabs Wave Final (`SalesTab` default + dropdown "Ver outras seções" expandindo Activities/Persons/Subscriptions/Rewards).
**Gap:** -85. ZERO das 8 tabs cadastrais existe no drawer hoje; 5 já existem como componentes Wave 5/Final mas em Show.tsx (a serem reposicionadas dentro de OSsTab via dropdown).
**Wave fix:** B (skeleton 8 tabs vazias) + C (5 tabs cadastrais com forms) + D (OSsTab wrapper) + E (IATab 4 cards) + F (AuditoriaTab timeline).
**Evidência:** `clientes-drawer.jsx` shell + `clientes-tabs.jsx::AuditTab/OssTab/IATab`.

## 8. Form fields + inputs BR (máscaras + ViaCEP/BrasilAPI) — 5/100 → 90

**Cowork:** máscaras inline CPF `000.000.000-00`, CNPJ `00.000.000/0000-00`, tel `(00) 0 0000-0000`, CEP `00000-000`; validação mod 11 (CPF/CNPJ) inline error; ViaCEP no blur do CEP autopreenche logradouro/bairro/cidade/UF; BrasilAPI no blur do CNPJ autopreenche razão social; autosave on blur por field; radio canal (whatsapp/email/telefone/presencial); multi-select tags (9 valores).
**Atual:** ZERO no drawer; existe em `/contacts/{id}/edit` Blade legacy (não medido aqui).
**Gap:** -85. Tudo novo. **Bloqueador crítico:** `Modules/Crm/Services/BrLookupService.php` + `ClienteLookupController` proxies precisam existir ANTES (Wave C) pra evitar rate limit ViaCEP/BrasilAPI quando Larissa biz=4 cadastra 30/dia.
**Wave fix:** C (5 tabs cadastrais + Lib/br-mask.ts + Lib/br-validate.ts + BrLookupService + endpoints POST autosave 5 tabs).
**Evidência:** `clientes-icons.jsx::BRMask`/`BRValidate`; `clientes-drawer.jsx::SectionIdentificacao` etc.

## 9. IA cards Copiloto (4 cards) — 0/100 → 90

**Cowork:** 4 cards na tab IA — (a) **Resumo de relacionamento** (LLM) com spinner + erro graceful + editável antes de aplicar; (b) **Reavaliar segmento + tags** (LLM) sugere mudanças aplicáveis em 1-click; (c) **Próxima ação sugerida** (LLM) ex: "Cliente sem compra há 187 dias — sugerir reativação WhatsApp"; (d) **Score de risco** (determinístico, NÃO LLM — port `clientes-tabs.jsx::RiscoCliente`).
**Atual:** Show.tsx tem tab "Atividades" mas é placeholder Activity feed (PR #1305 Wave Final) — não tem nada de IA Copiloto.
**Gap:** -90. Tudo novo. Endpoints servidor `Modules/Crm/Http/Controllers/ClienteIaController.php` reusando `Modules/Jana/Services/Ai/LaravelAiSdkDriver` + quota `CustosService::checkQuota($user, 'cliente_ia')`; **NÃO** usar `window.claude.complete` cliente (Tier 0 segurança).
**Wave fix:** E (IATab.tsx + 4 endpoints IA + RiscoController determinístico + Pest mock LLM).
**Pegadinha:** US-COPI-070 quota — decidir Q4 (render default ou gate plano pago?). Recomendação: render default + endpoint bloqueia execução se quota exceeded.
**Evidência:** `clientes-tabs.jsx::IATab` + `RiscoCliente`.

## 10. Auditoria timeline LGPD — 5/100 → 85

**Cowork:** timeline vertical com 6 tipos de eventos (created · field_changed · status_changed · view · os_created · note_added) + avatar+nome user + timestamp absoluto + "há Xm/h/d" relativo + botão "Exportar log" CSV/PDF (LGPD Art. 18 direito de acesso a dados pessoais).
**Atual:** zero no drawer. Backend `composer.json:47` tem `spatie/laravel-activitylog ^4.8` instalado mas Contact model não tem `LogsActivity` trait. `Modules/Auditoria/Services/AuditEntryService` existe e oferece scope multi-tenant.
**Gap:** -80. UI novo (timeline) + wire backend (`use LogsActivity;` no Contact + `ClienteAuditoriaController::forSubject($contact)` + endpoint `GET /cliente/{id}/auditoria` + endpoint `GET /cliente/{id}/auditoria/export.csv`).
**Wave fix:** F (AuditoriaTab.tsx + ClienteAuditoriaController + LogsActivity trait wire + permission `cliente.audit_view` + Pest LGPD Art. 18).
**Evidência:** `clientes-tabs.jsx::AuditTab` + `fakeAudit` mock data.

## 11. HISTÓRICO strip / KPIs operacionais — 50/100 → 90

**Cowork:** card "Resumo do relacionamento" com 4 KPIs strip horizontal: (a) **OSs no total** · (b) **Ticket médio** · (c) **Saldo aberto** · (d) **Última compra** com FrescorPill inline. Posicionado abaixo do header do drawer, antes das tabs.
**Atual:** Show.tsx tem 4 StatCards no header (Total vendido · A receber · Total comprado · Saldo abertura) — métricas próximas mas **falta ticket médio**; nomes diferentes ("vendido" vs "OSs total"); FrescorPill ausente; posicionamento ok (header).
**Gap:** -40. Renomear pra vocabulário Cowork; adicionar ticket_medio computed accessor; adicionar FrescorPill inline na ÚltimaCompra; mover do header pro acima-das-tabs (mais próximo do contexto da tab ativa).
**Wave fix:** B (drawer header refactor) — HistoricoStrip component com 4 KPIs cadastrais.
**Evidência:** `clientes-drawer.jsx::HistoricoStrip`.

## 12. Acessibilidade + atalhos (KB-9.75 + 1-8) — 85/100 → 95

**Cowork:** ⌘K command palette · `?` cheat-sheet · J/K nav linha · Enter abre drawer · `/` foca busca · `1-8` troca tab quando drawer aberto · aria-roles (`role=tab`/`role=tabpanel`/`role=dialog`) · aria-selected · foco anel visível (`ring-2 ring-blue-400`) · ESC fecha drawer.
**Atual (PR #1309 KB-9.75 Slice A):** ⌘K ✅ · `?` ✅ · J/K ✅ · Enter ✅ · `/` ✅ · ESC ✅ (linhas 174-279 Index.tsx). Falta **`1-8` troca tab** (drawer atual não tem tabs ainda).
**Gap:** -10. Falta atalho 1-8 (dependência: tabs existirem — Wave B).
**Wave fix:** B+G — adicionar handler 1-8 no keymap quando `openContactId !== null && activeTab`.
**Evidência:** PR #1309 + `clientes-975.jsx::CommandPalette/CheatSheet/KBScore`.

## 13. Empty + loading + error states (6 estados) — 50/100 → 90

**Cowork (6 estados):** (a) **cheia** listagem normal · (b) **vazio** sem clientes "Cadastre o primeiro cliente" CTA · (c) **busca** ativa com chips removíveis · (d) **sem resultado** busca vazia com Empty-state IA "Não encontrei. Quer que eu pesquise no Brasil.io?" · (e) **loading skeleton** rows shimmer · (f) **linha selecionada** anel azul + bg-blue-50.
**Atual:** (a) ✅ cheia, (b) ✅ vazio simples (linha 432 "Nenhum cliente encontrado nesse filtro"), (e) ✅ TableSkeleton+KpiSkeleton via `<Deferred fallback>`, (f) ✅ ring-2 ring-blue-300 (linha 448). Faltam (c) chips busca + (d) Empty-state IA + (e) **drawer** skeleton (loading do drawer tab por tab).
**Gap:** -40. Adicionar ActiveChip (parte da dim 4); adicionar NoResultsIA card; adicionar `DrawerSkeleton` por tab.
**Wave fix:** B (DrawerSkeleton) + G (NoResultsIA + ActiveChip).
**Evidência:** `clientes-listagem.jsx::EmptyState` + `NoResultsIA`.

## 14. Performance percepção (defer + partial reload) — 70/100 → 90

**Cowork:** client-side puro 60fps (mockup HTML); transições suaves shadcn Sheet `slide-in-right` 250ms; tabs change instantânea (in-memory).
**Atual:** Inertia::defer já em `customers` + `kpis` (linhas 332 + 412) ✅; drawer abre via `setOpenContactId` instantâneo (in-memory) ✅; budget Charter v3: p95 first-paint <1200ms (50 customers), KPIs defer <800ms, Customers defer <1500ms.
**Gap:** -20. Quando tabs entrarem (Wave B+), cada tab nova precisa `router.reload({ only: ['tabIdentificacao'] })` lazy (não eager) pra SPA-feel. Sem partial reload, drawer fica blocking 8 queries SQL no abrir.
**Wave fix:** B — wire `<Deferred data="tabIdentificacao">` em cada tab + `useEffect` chama `router.reload({ only: ['tabContato'] })` no `onValueChange` do Tabs root.
**Evidência:** `inertia-defer-default` skill Tier B + ADR 0110 Cockpit V2.

## 15. Brand + identidade Oimpresso (print + voice) — 75/100 → 95

**Cowork:** logo Oimpresso no header (laranja `#FF6A00`); cor primária consistente; brand voice "Falar com Copiloto →" como microcopy diferenciador; LGPD Art. 18 visível na tab Auditoria como brand promise ("Você pode exportar todos os seus dados"); `@media print` específico no drawer pra "Imprimir ficha" gera 1 página A4 com brand.
**Atual:** header AppShellV2 com logo Oimpresso ✅; cor primária consistente ✅ (`bg-primary text-primary-foreground`); microcopy operacional mas sem "Falar com Copiloto" (ainda); LGPD ausente; `@media print` ausente (Show.tsx full-page acaba sendo usada pra print informal hoje).
**Gap:** -20. Adicionar "Falar com Copiloto →" no header drawer (Wave B) + LGPD banner na Auditoria (Wave F) + CSS `@media print` no drawer (Wave B).
**Wave fix:** B (header + @media print) + F (LGPD banner).
**Pegadinha:** "Falar com Copiloto →" aponta pra `/jana/chat?context=cliente:{id}` (NÃO `/copiloto` — não existe). `Modules/Jana/Http/Controllers/ChatController.php` precisa aceitar query `context` e pré-carregar fact sobre cliente.

---

## (extra A) Tab IA 4 cards detalhada — 0/100 → 90

Sobreposto com dim 9; reforça que **4 endpoints** server-side (`/cliente/{id}/ia/resumo`, `/sugest-tags`, `/proxima-acao`, `/risco`) + quota `CustosService` + RiscoController determinístico. Total ~5h dev IA-pair (fator 10x ADR 0106).

## (extra B) Tab Auditoria detalhada — 5/100 → 85

Sobreposto com dim 10; reforça **NÃO duplicar** tabela `audit_log` paralela — usar `spatie/laravel-activitylog` v4.8 já instalado + `Modules/Auditoria/Services/AuditEntryService::forSubject(Contact)`. Total ~3.5h dev.

## (extra C) Header drawer rico (toggle PF/PJ + badge + 2 CTAs) — 30/100 → 90

Sobreposto com dim 6; reforça que mudança não é só layout — é semântica (toggle PF/PJ muda quais campos das 5 tabs cadastrais aparecem: PF mostra `rg/nascimento`, PJ mostra `fantasia/ie/contato/cargo`).

---

## A11y (consolidado)

- `role=dialog` no Sheet drawer + `aria-labelledby=drawer-title` + `aria-describedby=drawer-doc`
- `role=tablist` + `role=tab` + `role=tabpanel` + `aria-selected` + `aria-controls` em todas as 8 tabs
- `aria-live=polite` em IA cards (anuncia "Carregando resumo…" → "Resumo gerado")
- Foco visível `ring-2 ring-blue-400` em row focada, tab ativa, e form fields
- Atalhos teclado: ⌘K · ? · J/K · Enter · / · ESC · 1-8 (drawer aberto) · Tab/Shift-Tab dentro do drawer
- Contraste WCAG AA: tag chips coloridas devem ter `text-{color}-900` sobre `bg-{color}-100` (testar com axe-core)
- Empty-state IA tem `<button>` semântico (não `<div onClick>`)

## Performance targets (p95)

- First-paint Index `/cliente`: < 1200ms (50 customers)
- KPIs defer: < 800ms
- Customers defer: < 1500ms
- Drawer abre (in-memory): < 100ms
- Tab change (router.reload only): < 500ms
- IA card resposta LLM: < 4000ms (com spinner + erro 8s timeout graceful)
- ViaCEP/BrasilAPI proxy hit cache Redis: < 200ms
- Print ficha render: < 1500ms

## Próximo passo gate F1.5 (humano-limitado)

- [ ] Wagner roda `design:design-critique` no protótipo `prototipo-ui/prototipos/clientes/Oimpresso ERP - Clientes.html`
- [ ] Wagner roda `design:accessibility-review` no atual `resources/js/Pages/Cliente/Index.tsx` (pós-Wave B skeleton drawer 760)
- [ ] Wagner roda `design:ux-copy` nos microcopy do drawer (8 tabs + 2 CTAs header + 4 IA cards + Auditoria LGPD banner)
- [ ] Wagner aprova **SCREENSHOT** do drawer 760px aberto em prod biz=1 com as 8 tabs renderizando (gate ADR 0107 — NÃO tabela markdown)
- [ ] Brave smoke prod biz=1 — `oimpresso.com/cliente` clica linha → drawer abre → troca cada uma das 8 tabs → screenshot salvo em `prototipo-ui/SYNC_LOG.md` (R1 do PROTOCOLO)
- [ ] Atualizar este arquivo trocando `status: draft` → `status: approved` + `approved_by: wagner` + notas REAIS pós-merge Wave G+Z (substituir estimativas calibradas por medições)

## Telas derivadas que herdam aprovação

- (nenhuma — drawer 760 é canon NOVO; Fornecedor (`/contacts?type=supplier`) pode adotar o mesmo pattern em Wave futura mas exige próprio visual-comparison)

## Telas com divergência declarada (NÃO usam este blueprint)

- `Cliente/Show.tsx` full-page — **superseded** por este drawer; mantido como modo "ficha completa" via botão "Imprimir ficha" `target=_blank` (Q1 a confirmar Wagner)
- `Cliente/Edit.tsx` (Blade legacy `/contacts/{id}/edit`) — pode ser substituído por edit-inline autosave nas 5 tabs cadastrais (Q2 a confirmar Wagner)
- `Cliente/Create.tsx`, `Cliente/Import.tsx`, `Cliente/Ledger.tsx`, `Cliente/Map.tsx` — fluxos paralelos, não tocados nesta Wave

## Refs

- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 Constituição v2 (7 camadas + 8 princípios)
- ADR 0104 processo MWART canônico (5 fases obrigatórias)
- ADR 0107 visual gate F1.5 (Wagner aprova SCREENSHOT, não tabela)
- ADR 0110 Cockpit Pattern V2
- ADR 0114 prototipo-ui Cowork loop formalizado
- ADR 0149 pattern reuse blueprint Cowork
- ADR 0167 errata 0130 handoff
- ADR 0179 (Wave A NOVA — paradigma drawer 760 substitui Show.tsx full-page)
- Charter `Pages/Cliente/Index.charter.md` v3 (draft → publish Wave A com `drawer_pattern: 760px-lateral`)
- Charter `Pages/Cliente/Show.charter.md` v2 (Wave A marca `status: superseded` + `superseded_by: Index.charter.md v3`)
- RUNBOOK `memory/requisitos/Crm/RUNBOOK-cliente-drawer-760px.md` (Wave A cria)
- HANDOFF `prototipo-ui/prototipos/clientes/HANDOFF_CLIENTES.md` (spec protótipo 9,4/10 KB-9.75 — schema BR completo)
- PROTOCOL `prototipo-ui/PROTOCOL.md` (Cowork loop)
- LICOES `prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md` (6 meta-anti-padrões + 15 técnicos — PRÉ-FLIGHT Wave C-F)
- Dossiê `memory/sessions/2026-05-21-understand-cliente-drawer-760px-opcao-A.md` (decodificação completa wagner-understand)
- Sessão coord `memory/sessions/2026-05-21-coord-cliente-show-paridade-5waves.md` (contexto 5 PRs Wave 5/Final mergeadas)
- Pointer-redirect `memory/requisitos/Cliente/show-visual-comparison.md` (apontará pra este após sunset Show)
- Visual comparisons irmãs: `cliente-index-visual-comparison.md`, `cliente-show-visual-comparison.md`, `cliente-create-visual-comparison.md`, `cliente-edit-visual-comparison.md`, `cliente-import-visual-comparison.md`, `cliente-ledger-visual-comparison.md`, `cliente-map-visual-comparison.md`

---

_Visual-comparison criado 2026-05-21 (Wave A) — methodology mwart-comparative V4 (15 dimensões + 3 extras). Notas calibradas pela auditoria de Wagner (~28/100 conjunto, distribuído ±5 por dimensão). Gate F1.5 pendente: Wagner aprovará SCREENSHOT pós-Wave B+G em prod biz=1 com 8 tabs renderizando — NÃO esta tabela. Substituição de paradigma (Show.tsx full-page → drawer 760 lateral) requer ADR 0179 `accepted` antes de Wave B. Próxima atualização: pós-merge Wave Z, substituir estimativas por medições reais._
