<!-- schema-allowlist: audit adversário DS read-only — findings ancorados em arquivo:linha, nenhuma task MCP criada (instrução explícita do solicitante) -->
---
date: 2026-06-13
hour: "09:30 BRT"
duration: "2h"
topic: "Auditoria adversária DS — tela de cadastro de Cliente vs Design System próprio"
authors: [W, C]
outcomes:
  - "Nota de conformidade DS Cliente: 58/100"
  - "423 hits ui:lint (cor crua) + 63 ds/no-adhoc-status-text (eslint) na superfície Cliente"
  - "Print 'Imprimir ficha' = stub window.print() sem @media print nem rota PDF — produz tela crua"
  - "4 primitivos canon (Badge/KpiCard/DataTable/EmptyState) re-implementados localmente"
prs: []
us:  []
related_adrs: ["0213-audit-creates-tasks-loop-fechado", "0094-constituicao-v2-7-camadas-8-principios"]
audit: true
---

# Auditoria 2026-06-13 — DS adversário: cadastro de Cliente

## TL;DR

Auditei a tela de Cliente (lista `Index.tsx` 2.693 linhas + drawer `ClienteSheet` + 10 tabs `_drawer/` + 13 sub-tabs `_show/` + 4 `_components/`) contra o DS do próprio sistema (tokens `inertia.css`/`foundations.css`, `ui:lint`, eslint `ds/*`, primitivos canon `Components/ui` + `shared`). **Veredito: a tela FUNCIONA e tem UX rica, mas é a campeã de débito DS do projeto — `ds-ledger.json` já marca `tokens: "no"` e a medição confirma: 423 hits de cor crua + 63 de status-text ad-hoc.** A maior parte do problema NÃO é a exceção `--cat-*` (avatares/tags de categoria): é **status semântico** (devedor, ativo/bloqueado, atrasado, frescor, danger) pintado com `rose/emerald/sky/amber/stone` cru quando já existe `Badge variant="success|danger|warning|info|neutral"` e `KpiCard tone=` canon prontos pra absorver. Soma-se um conjunto de **pontas soltas reais** (botão "Salvar" que não salva, "1 pendência" hard-coded, "Imprimir ficha" stub) e **duplicação de primitivos** (2 implementações de Avatar no mesmo arquivo, `Intl BRL` redeclarado em 9 arquivos, KPI strip re-inventando `KpiCard`).

**Nota de conformidade DS: 58/100.** Funcional e acessível na superfície, mas reprovado em tokenização semântica, reúso de primitivos e honestidade de features (3 stubs que se passam por prontos).

## Escopo auditado

- **Lista:** `resources/js/Pages/Cliente/Index.tsx` (2.693 l — lista densa + `ClienteSheet` drawer + paginação + filtros + busca + FABs)
- **Tabs drawer:** `_drawer/*.tsx` (10 arquivos: Identificacao 952l, Classificacao, Endereco, Contato, Comercial, Oss, IA, Auditoria, Placas, EnderecosEntrega)
- **Sub-tabs operações:** `_show/*.tsx` (13: Ledger, Sales, Payments, Documents, Activities, PessoasContato, Subscriptions, RewardPoints, Risco, Actions, Vehicles, ContactPicker, AddDiscount)
- **Components locais:** `_components/*.tsx` (Avatar, Pills, KpiStripClickable, ActiveChip)
- **Controllers:** `app/Http/Controllers/ContactController.php` (176 KB) + `Modules/Crm/Http/Controllers/Cliente*Controller.php` (6 arquivos)
- **Régua DS:** `inertia.css`, `foundations.css`, `ui:lint --detail`, `eslint ds/*`, `Components/ui/*`, `Components/shared/*`, `governance/ds-ledger.json`

**Fora de escopo / não re-reportado:** os 6 PRs recém-mergeados (KPIs/paginação/sort server-side, dedup subtítulo, badge drawer tokenizado) — confirmei que landaram. As ~80 cores de **categoria** (avatares oklch em `_components/Avatar.tsx`, `TagChip` 9 cores, `TipoPill` PF/PJ em `Pills.tsx`) são **exceção documentada** (ADR futuro `--cat-*`) — registradas mas NÃO contadas como bug.

## Medição automatizada (os números)

| Ferramenta | Cliente | Detalhe |
|---|---|---|
| `ui:lint --detail` (R1 cor crua) | **423 hits** | maiores: `Pills.tsx` 117 (≈ exceção cat), `Index.tsx` 59, `Show.tsx` 39, `_show/SalesTab` 32, `_drawer/IATab` 24, `_show/RiscoClienteCard` 22 |
| `eslint` (total) | 127 msgs | — |
| `eslint ds/no-adhoc-status-text` | **63 hits** | 23 arquivos; top: `IdentificacaoTab`/`LedgerTab` 7, `EnderecoTab`/`Index` 6 |
| `eslint ds/no-native-*` (checkbox/radio/select) | **0** | ✅ migrados (só comentário legacy em `ContactPicker.tsx:3`) |
| `ds/no-rounded-xl` | **0** | ✅ radius ok |
| `text-[Npx]` arbitrário (fora ramp `--fs`) | **108 ocorrências / 23 arquivos** | `Index.tsx` 25, `_form/ClienteRail` 9, `_show/Subscriptions` 10 |
| `ds-ledger.json:38-46` Cliente | `tokens: "no"` | confirma débito de cor crua (vs Atendimento `ref`) |

> Nota metodológica: ui:lint R1 e eslint `ds/no-arbitrary-color` medem coisas diferentes — R1 pega classes da paleta Tailwind (`bg-rose-50`), o eslint só pega hex/arbitrário cru (`[#...]`). Por isso 423 vs ~0 no eslint de cor. Os 63 do eslint são todos `no-adhoc-status-text` (status pintado de `rose/emerald` cru).

---

## Dimensão 1 — NÃO-CONFORMIDADE DS

### 1.1 Status semântico pintado com cor crua em vez de `Badge`/token  — **P1**

O canon `Components/ui/badge.tsx:21-31` JÁ tem `variant="success|warning|danger|info|neutral"` (tons soft) criados explicitamente pra "espelhar o STATUS_STYLE hand-rolled (migração 1:1)". A tela ignora e re-pinta o mesmo status:

- `Index.tsx:287-291` — `const STATUS_STYLE` mapeia `late/active/idle` → `bg-rose-50 / bg-sky-50 / bg-stone-50` cru. **Fix:** `<Badge variant="danger|info|neutral">` ou `StatusBadge kind="..."`. **(S)**
- `_components/Pills.tsx:228-241` — `StatusPill` mapeia `ativo/inativo/bloqueado` → `emerald/stone/rose` cru. É status, não categoria. **Fix:** `<Badge variant="success|neutral|danger">`. **(S)**
- `_components/Pills.tsx:208-216` — `SaldoCell` devedor=`text-rose-700`, crédito=`text-emerald-700`. Devedor → `text-destructive`; crédito → `text-success`. **(S)**
- `_components/Pills.tsx:126-135` — `FRESCOR_STYLE` (fresc/recente/distante/frio) em `emerald/amber/rose/stone` cru — semântica de saúde de relacionamento, mapeável a `success/warning/destructive/muted`. (Zona cinza vs `--cat-*`; tratar como status). **(M)**
- `_components/ActiveChip.tsx:36-37` — chip de filtro ativo `default`=`blue`, `danger`=`rose` cru. É chrome de UI: `blue`→`primary`, `rose`→`destructive`. **(S)**
- `Index.tsx:1249-1254` — badge "VIP" inline `bg-yellow-100 text-yellow-800` ad-hoc. **Fix:** `<Badge variant="warning">VIP</Badge>`. **(S)**
- `Index.tsx:1672-1704` — stat card com prop `danger` pinta `border-rose-200/text-rose-700` cru. `danger` real → token `destructive` ou `KpiCard tone="danger"`. **(S)**
- `Index.tsx:1337-1339` — star favorito `text-amber-500/hover:bg-amber-50` cru. **(S)**
- Idem espalhado: `Show.tsx:153/166/355/490/497`, `_show/RiscoClienteCard.tsx` (22 hits), `_show/SalesTab.tsx` (32), `_show/LedgerTab.tsx` (12), `_drawer/IATab.tsx` (24).

### 1.2 63× `ds/no-adhoc-status-text` (eslint) — texto de erro/sucesso em `text-rose/emerald` cru — **P1**

Os drawer/show tabs pintam mensagens de validação/estado com cor crua em vez de `<FieldError>/<FieldSuccess>` (`Components/ui/field-state.tsx`) ou `<Alert>`. `field-state` só é importado no `_form/` (Create/Edit) — os tabs do drawer (que são o cadastro de fato) não usam. Top ofensores: `_drawer/IdentificacaoTab.tsx` (7), `_show/LedgerTab.tsx` (7), `_drawer/EnderecoTab.tsx` (6), `Index.tsx` (6), `Import.tsx` (4), `Ledger.tsx` (4), `_show/SalesTab.tsx` (4). **Fix:** trocar por `FieldError`/`FieldSuccess`/`Alert`. **Esforço total (M).**

### 1.3 Tabela hand-rolled em vez de `<DataTable>` canon — **P2**

`Index.tsx:1222+` renderiza `<table>/<tr>/<td>` à mão (cabeçalho `SortHeader` próprio, `Th` próprio). `Components/shared/DataTable.tsx` existe e é usado em outras telas — aqui só `_show/SalesTab.tsx` o importa. O hand-roll multiplica `px-4 py-2.5` espalhado e duplica lógica de sort/sticky. **Fix:** avaliar migração pra `DataTable` (densidade custom é o trade-off — pode ficar como dívida consciente, mas registrar). **(L)**

### 1.4 `EmptyState` canon nunca importado — **P2**

`Components/shared/EmptyState.tsx` existe; `grep` em `Pages/Cliente/**` = 0 imports. Estados vazios (lista sem resultado, tab sem dados) são hand-rolled. **Fix:** adotar `EmptyState`. **(M)**

### 1.5 Inputs/textarea nativos hand-rolled (sem disparar eslint, mas fora do canon) — **P2**

eslint `ds/no-native-*` cobre checkbox/radio/select, não `<input type=text>`/`<textarea>`. Esses passam batido mas re-implementam borda/focus do `Input`/`Textarea` canon:
- `_show/DocumentsTab.tsx:247-255` — `<input type="text">` cru (`h-9 rounded-md border...`). **Fix:** `<Input>`. **(S)**
- `_show/DocumentsTab.tsx:256-263` — `<textarea>` cru. **Fix:** `<Textarea>`. **(S)**
- `_show/AddDiscountModal.tsx:166-167` — `<textarea>` cru idem. **(S)**

### 1.6 `text-[Npx]` arbitrário fora do ramp `--fs` — **P3**

108 ocorrências/23 arquivos. Ex.: `Index.tsx:853` `text-[22px]`, `:886/:997` `text-[12.5px]`, `:934/:957` `text-[10.5px]`, `:1250` `text-[9px]`, Pills `text-[10px]` repetido. `foundations.css` define `--fs-1..9`; esses valores fogem do ramp. **Fix:** mapear pros tokens `text-xs/sm` ou `--fs-N`. (Vários são micro-densidade Cowork — candidato a token novo `--fs-micro`.) **(M)**

---

## Dimensão 2 — PONTAS SOLTAS (stubs/placeholders/features meio-ligadas)

### 2.1 Botão "Salvar" do drawer NÃO salva — **P1 (honestidade de UI)**

`Index.tsx:2279-2289` — o botão primário "Salvar" do footer do drawer só executa `onOpenChange(false)` (fecha). Comentário explícito `:2282` "Placeholder Wave B -- Wave C dispara PATCH autosave on blur. TODO Wave C". O save real é autosave-on-blur — então o botão **mente**: implica uma ação de salvar que nunca dispara. Risco de o usuário clicar achando que confirma algo. **Fix:** remover o botão (autosave cobre) OU torná-lo um "Fechar" honesto, OU ligar um flush real do autosave. **(S)**

### 2.2 "1 pendência" hard-coded no footer — **P2**

`Index.tsx:2266-2270` — `{/* Placeholder pendencias -- Wave G calcula contagem real. */}` seguido de literal `1 pendência` pra TODO cliente. KPI falso exibido ao usuário. **Fix:** computar real ou esconder até ter dado. **(S)**

### 2.3 "Imprimir ficha" = `window.print()` stub — **P1** (ver Dimensão 4)

`Index.tsx:2061-2070`.

### 2.4 Favorito (Star) — coluna DB existe mas nunca é escrita — **P3**

`Index.tsx:364-385` `useFavoritos` é localStorage-only. Doc `:367` admite "Coluna `favorito_users` JSON em Contact existe (Wave B migration) mas é reservada pra futura sync". Feature meio-ligada (schema morto). Decisão consciente p/ Larissa 1-user — registrar como dívida, não bug. **(—)**

### 2.5 Comentários "Wave G expandirá / TODO Wave C/D/G" — **P3 (limpeza)**

Espalhados: `Index.tsx:128` ("Wave G expandirá full coverage"), `:1859` ("TODO Wave G: HSL hash" — já feito, comentário stale), `:1894` ("Wave G troca por relDate util"), `:1946`, `:2008`. `Map.tsx:3` "placeholder até Wagner aprovar lib". São TODOs de waves já fechadas → ruído. **Fix:** varredura de comentários stale. **(S)**

---

## Dimensão 3 — DUPLICAÇÃO

### 3.1 DOIS componentes `Avatar` no mesmo arquivo — **P2**

`Index.tsx:92` importa `Avatar as ClienteAvatar` (o canon `_components/Avatar.tsx`, oklch) e o usa na linha da tabela (`:1239`). MAS `Index.tsx:1708-1714` **define outro** `function Avatar` inline com `bg-gradient-to-br from-stone-100` cru. Duas implementações de avatar coexistindo. **Fix:** deletar o inline, usar só `ClienteAvatar`. **(S)**

### 3.2 `avatarInitial` duplicado — **P3**

`Index.tsx:325-329` redefine `avatarInitial` que já existe exportado em `_components/Avatar.tsx:53`. **Fix:** importar do canon. **(S)**

### 3.3 `Intl.NumberFormat BRL` redeclarado em 9 arquivos — **P2**

`Index.tsx:293`, `Ledger.tsx`, `Show.tsx`, `Pills.tsx:190`, `_show/{LedgerTab,PaymentsTab,RewardPointsTab,RiscoClienteCard,SalesTab}`. Sem `formatBRL` util compartilhado. **Fix:** extrair pra `Lib/` (ou usar util existente). **(S)**

### 3.4 `relDate`/`relativeFromIso`/`relativeDate` duplicado — **P3**

`Pills.tsx:145` `relativeFromIso` + `Index.tsx` `relativeDate` (`:1894` admite "Wave G troca por relDate util `Lib/relDate.ts`"). Duas formatações relativas. **(S)**

### 3.5 `KpiStripClickable` re-inventa `KpiCard` canon — **P1 (reúso + lint-evasion)**

`_components/KpiStripClickable.tsx` re-implementa do zero um grid de cards-filtro clicáveis, enquanto `Components/shared/KpiCard.tsx` JÁ suporta `onClick` + `selected` + `tone` + `icon` + `value` + `label` (linhas 83-87, 144-157). Pior: `KpiStripClickable.tsx:67-71` declara que usa **oklch inline de propósito** "pra não disparar regra R1 do ui:lint" — ou seja, **evasão deliberada do linter** em vez de adoção do token/canon. Isso é anti-padrão de governança (burla a catraca em vez de conformar). **Fix:** migrar pra `KpiCard`/`KpiGrid` com `tone` semântico; eliminar os 5 `TONE_STYLES` oklch inline. **(M)**

### 3.6 Status duplicado entre lista, drawer e KPI strip — **P3**

"VIP" aparece 3×: badge inline na linha (`Index.tsx:1249`), `TagChip` `vip` (`:1311`) e card "VIPs" do KPI strip. "Status/Saldo" aparece na coluna da lista e de novo no header/footer do drawer. Redundância informacional — avaliar consolidação. **(M)**

---

## Dimensão 4 — IMPRESSÃO ("Imprimir ficha")

**Veredito: STUB não-funcional. Imprime a tela crua, não uma ficha profissional.**

Evidência:
1. `Index.tsx:2061-2070` — botão chama `if (typeof window !== 'undefined') window.print();` com comentário literal: `// Placeholder Wave B -- window.print() abre dialogo print do navegador. // Wave G implementa CSS @media print + layout dedicado.`
2. **Zero `@media print` pra Cliente.** `grep @media print` em `resources/css/**`: existe robusto pra Sells (`sells-kb975-print.css` recibo térmico 80mm + orçamento A4), Financeiro (`fin-output.css` A4) — **nenhum** stylesheet de print pra cliente.
3. **Zero rota/método de ficha.** `ContactController.php` (176 KB) não tem nenhum método `print/ficha/pdf` (`grep` = 0). `routes/web.php` tem `printInvoice` pra sells/purchases/stock-transfers — nada pra cliente.

Consequência: clicar "Imprimir ficha" abre o diálogo de print do navegador sobre o DOM vivo do drawer — sairá com sidebar, topnav, chrome do Sheet, scroll cortado. Não é ficha cadastral. **Fix:** ou (a) `@media print` dedicado isolando a ficha (padrão `sells-kb975-print.css`), ou (b) rota `GET /cliente/{id}/ficha` → Blade/PDF server-side (padrão `printInvoice`). **(L)** — humano-limitado (precisa decidir formato com Wagner).

---

## Achados acionáveis (convenção ADR 0213 — read-only, nenhuma task criada)

> Instrução do solicitante: NÃO criar task MCP, NÃO commitar. Linhas abaixo são o backlog proposto pra Wagner aprovar manualmente depois.

- [ ] TASK[claude](P1): "Imprimir ficha" real — `@media print` dedicado OU rota `/cliente/{id}/ficha` PDF (padrão `sells-kb975-print.css` / `printInvoice`)
  - Onde: `Index.tsx:2061-2070` · `ContactController.php` (sem método) · `resources/css/` (sem stylesheet)
  - Esforço: L · Impact: usuário imprime tela crua achando que é ficha
- [ ] TASK[claude](P1): botão "Salvar" do drawer que não salva — remover/honestizar/ligar flush
  - Onde: `Index.tsx:2279-2289`
  - Esforço: S · Impact: UI mente, risco de falsa confirmação
- [ ] TASK[claude](P1): tokenizar status semântico → `Badge variant` / `text-destructive|success` (STATUS_STYLE, StatusPill, SaldoCell, ActiveChip, VIP, stat danger)
  - Onde: `Index.tsx:287-291,1249-1254,1672-1704` · `Pills.tsx:208-241` · `ActiveChip.tsx:36-37`
  - Esforço: M · Impact: 60% dos 423 hits ui:lint são isso; some `tokens: "no"` → `partial`
- [ ] TASK[claude](P1): 63× `ds/no-adhoc-status-text` → `FieldError/FieldSuccess/Alert` nos drawer/show tabs
  - Onde: `IdentificacaoTab`, `LedgerTab`, `EnderecoTab`, `Index`, `Import`, `Ledger`, `SalesTab`…
  - Esforço: M · Impact: zera a catraca eslint do Cliente
- [ ] TASK[claude](P1): `KpiStripClickable` → `KpiCard`/`KpiGrid` canon (elimina 5 oklch inline + lint-evasion §3.5)
  - Onde: `_components/KpiStripClickable.tsx:67-238`
  - Esforço: M · Impact: remove evasão deliberada do linter; reúso canon
- [ ] TASK[claude](P2): deletar `Avatar` inline duplicado + `avatarInitial` dup; usar `_components/Avatar.tsx`
  - Onde: `Index.tsx:1708-1714,325-329`
  - Esforço: S · Impact: 1 fonte de verdade pro avatar
- [ ] TASK[claude](P2): extrair `formatBRL` util (redeclarado em 9 arquivos)
  - Onde: `Index.tsx:293` + 8 outros
  - Esforço: S
- [ ] TASK[claude](P2): "1 pendência" hard-coded → real ou esconder
  - Onde: `Index.tsx:2266-2270`
  - Esforço: S · Impact: KPI falso
- [ ] TASK[claude](P2): inputs/textarea nativos → `Input`/`Textarea` canon
  - Onde: `DocumentsTab.tsx:247-263` · `AddDiscountModal.tsx:166-167`
  - Esforço: S
- [ ] TASK[claude](P2): adotar `EmptyState` canon (0 imports hoje)
  - Onde: estados vazios de lista + tabs
  - Esforço: M
- [ ] TASK[claude](P3): varredura de comentários "Wave G/C/D" stale + `text-[Npx]` → ramp `--fs`
  - Onde: 23 arquivos (108 text-[N]) · TODOs `Index.tsx:128,1859,1894`
  - Esforço: M
- [ ] TASK[wagner](P3): decidir `--cat-*` ADR (avatares oklch + TagChip 9 cores) — formaliza a exceção das ~80 cores de categoria
  - Onde: `_components/Avatar.tsx:32-45` · `Pills.tsx:55-77`
  - Esforço: S (ADR) · Impact: separa "exceção legítima" de "débito" no ds-ledger

<!-- TASK_IGNORED: §2.4 favorito localStorage-only — decisão consciente Larissa 1-user (ADR 0105 cliente-como-sinal); coluna favorito_users dorme até sinal qualificado -->
<!-- TASK_IGNORED: §1.3 DataTable hand-rolled — densidade custom Cowork é trade-off intencional; só registrar, não forçar migração sem sinal -->

## Top-10 priorizado — "O QUE PRECISA SER FEITO" (impacto × esforço)

| # | Ação | Sev | Esf | Por quê primeiro |
|---|---|---|---|---|
| 1 | Honestizar/remover botão "Salvar" do drawer (`Index.tsx:2279`) | P1 | S | UI que mente é o pior tipo de bug; fix de 1 linha |
| 2 | "1 pendência" hard-coded → real/esconder (`Index.tsx:2266`) | P2 | S | KPI falso visível, fix trivial |
| 3 | Deletar Avatar inline dup + avatarInitial dup (`Index.tsx:1708,325`) | P2 | S | 1 fonte de verdade, remove cor crua stone, quick win |
| 4 | Tokenizar status semântico → `Badge`/token (STATUS_STYLE, StatusPill, SaldoCell, VIP, ActiveChip) | P1 | M | derruba ~60% dos 423 hits ui:lint; move `tokens:"no"`→`partial` |
| 5 | 63× ad-hoc-status-text → `FieldError/Alert` nos tabs | P1 | M | zera a catraca eslint do Cliente |
| 6 | `KpiStripClickable` → `KpiCard` canon (mata lint-evasion §3.5) | P1 | M | reúso + acaba com evasão deliberada do linter |
| 7 | "Imprimir ficha" real (@media print OU rota PDF) | P1 | L | feature anunciada que entrega lixo; decidir formato c/ Wagner |
| 8 | `formatBRL` util único (9 arquivos) + inputs/textarea canon | P2 | S | reúso barato, remove hand-roll |
| 9 | Adotar `EmptyState` + decidir migração `DataTable` | P2 | M/L | consistência de vazios; DataTable é dívida consciente |
| 10 | ADR `--cat-*` (Wagner) + limpeza TODO/text-[N] stale | P3 | S/M | formaliza exceção de categoria e separa débito real do legítimo |

**Ordem de ataque sugerida:** quick wins 1-3 (1 PR, ~S total, mata 3 mentiras de UI e 1 dup) → tokenização 4-6 (o grosso do número DS, 2-3 PRs por ds-ledger) → print 7 (precisa decisão de formato) → reúso 8-9 → governança 10. Itens 4+5+6 juntos derrubam Cliente de "campeão de débito" pra `partial` no `ds-ledger.json`.

## Refs

- `governance/ds-ledger.json:38-46` — Cliente `tokens: "no"` (a régua confirma o débito)
- `Components/ui/badge.tsx:21-31` — variants `success/warning/danger/info/neutral` já existem (criadas pra absorver os pills hand-rolled)
- `Components/shared/{StatusBadge,KpiCard,KpiGrid,DataTable,EmptyState}.tsx` — primitivos canon sub-adotados no Cliente
- `eslint.config.js:137-166` — rules `ds/no-native-*` + `ds/no-rounded-xl` + `ds/no-arbitrary-color` + `ds/no-adhoc-status-text`
- `resources/css/sells-kb975-print.css` / `fin-output.css` — padrão de `@media print` que o Cliente NÃO tem
- ADR 0213 — audit docs criam MCP tasks (loop fechado) · ADR 0094 — Constituição v2

---

**Arquivo:** `memory/sessions/2026-06-13-audit-adversario-ds-cliente-cadastro.md`
**Status:** audit-only (nenhum código alterado, nenhuma task MCP criada, nenhum git — conforme instrução)
