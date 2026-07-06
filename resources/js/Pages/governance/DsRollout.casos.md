---
casos: DS Rollout · Ledger de Conformidade DS · /governance/ds-rollout
irmaos: DsRollout.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-06-12"
---

# Casos de uso — /governance/ds-rollout

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Tradução F3 do protótipo Cowork `DS Rollout - Ondas e Testes`. Persona: Wagner [W] (decide onda a onda). O placar prova "tudo aplicado" mecanicamente — o teste-mãe desta tela é o próprio `scripts/ds-ledger.mjs`.

## UC-DSR-01 — O placar só mostra número que veio de gate rodando
Status: ⬜ (manual/visual — abrir com e sem `governance/ds-ledger.json`)
Com o artefato presente, o Ledger renderiza as linhas medidas + o carimbo `● medido @sha · timestamp`.
Sem o artefato, o `DsRolloutController` cai no `staticFallback()` e a tela mostra o badge âmbar
`▲ snapshot estático · TODO ledger` com `0%` — nunca um número real não-medido.
**Pronto quando:** apagar `governance/ds-ledger.json` faz a tela exibir "TODO ledger"; recriar via
`npm run ds:ledger -- --write` faz aparecer o `%` real carimbado com o SHA.

## UC-DSR-02 — O censo reproduz a "Medição real" do design
Status: 🧪 (cobertura futura — assert no output de `ds-ledger.mjs --json`)
`scripts/ds-ledger.mjs` mede por Page: eslint `ds/*` (cor arbitrária + status-text + primitivos nativos)
+ paleta Tailwind crua + `conformance-gate` + `components-tree-guard`. O piloto `Produto/Create` cai em
**tokens** (`stone/rose` cru) **e primitivos** (`<input type=checkbox>` nativo, pego por `ds/no-native-checkbox`).
**Pronto quando:** `node scripts/ds-ledger.mjs --json` lista `Produto` com `cells.tokens="no"` e `cells.primitivos="no"`.

## UC-DSR-03 — probe e dark nunca fingem verde
Status: ⬜ (manual/visual — inspecionar células das colunas Probe/Dark)
Probe G1–13 e Dark exigem render de browser (Camada 2). O censo estático NÃO os mede e renderiza `–`
("não medido") com tooltip explicativo — jamais `✓`.
**Pronto quando:** toda linha não-referência mostra `–` em Probe e Dark, com `title` "não medido por censo estático".

## UC-DSR-04 — O Ledger renderiza as linhas reais do census
Status: 🧪 (cobertura futura — assert que a tabela tem N=counts.screens+references linhas)
A tabela do Ledger é populada da prop `census.ledger` (não hardcoded). Linha-referência (`Atendimento`)
aparece com `★` e destaque, fora da conta do `%`. A barra de progresso reflete `census.progressPct`.
**Pronto quando:** o nº de linhas = `census.counts.screens + census.counts.references` e a barra = `progressPct%`.

## UC-DSR-05 — Banner da árvore de componentes reflete o guard
Status: ⬜ (manual/visual — conferir badge "árvore Components/")
O census carrega `treeGuard` (de `components-tree-guard`). Pass → badge verde `✓ canônica`; fail → badge
vermelho com a contagem de violações.
**Pronto quando:** o badge bate com `node scripts/components-tree-guard.mjs` (exit 0 = ✓).

## UC-DSR-06 — A tela é canon (não recria o débito do protótipo)
Status: 🧪 (cobertura: eslint `ds/*` = 0 nesta tela · CI ESLint ratchet)
Usa `@/Components/PageHeader` v3.8 (não o `shared/` congelado), `KpiCard`, primitivos de layout
`<Inline>/<Grid>` e tokens semânticos (`text-success`/`text-destructive`/`text-warning`) — zero `<style>`
OKLCH cru e zero violação `ds/*`.
**Pronto quando:** `eslint resources/js/Pages/governance/DsRollout.tsx` = 0 warnings `ds/*`.

## UC-DSR-07 — As ondas seguem Tier-0 (a tela não executa nada)
Status: ⬜ (manual — a tela é read-only; nenhuma ação muta token/tela)
A tela é puramente informativa/medição. Tokenizar cor e portar telas (as ondas) permanecem Tier-0,
travadas no "vai" de Wagner onda a onda — fora desta tela.
**Pronto quando:** não há nenhuma ação na tela que edite token, CSS ou Page.

## UC-DSR-08 — Acesso (auth + permissão)
Status: ⬜ (manual — rota sob `auth` + `governance.dashboard.view`)
`/governance/ds-rollout` exige login e a permissão `governance.dashboard.view` (mesma família das demais
telas de governança). Item de nav só aparece com a permissão.
**Pronto quando:** usuário sem `governance.dashboard.view` não vê o link nem acessa a rota.

---

## Adendo MV batch 2026-07-06 — UCs de contrato do backend (Ledger/prop `census`)

> Auditoria QA-de-tela (agente `screen-qa-specialist`, batch MV aprovado por Wagner).
> **Achado:** os 8 UCs acima estavam ⬜/🧪 — **zero teste Pest** mordia o `DsRolloutController`.
> Os UCs abaixo são os testáveis por backend (prop `census`, fallback, roteamento) e
> agora têm teste que MORDE em `Modules/Governance/Tests/Feature/DsRolloutControllerTest.php`.
> UC-DSR-02/03/06/07 permanecem client-side/gate-side (não backend) — cobertos por
> `ds-ledger.mjs`/eslint/inspeção visual, fora do escopo deste teste Pest.
>
> **Gap real detectado (não corrigido aqui — QA não edita):** o UC-DSR-08 afirma que a rota
> exige `governance.dashboard.view`, mas o grupo em `Http/routes.php` só aplica `auth` (o
> Controller faz só `$this->middleware('auth')`). A permissão está apenas no nav (`topnav.php`),
> não no gate da rota. O teste UC-DSR-08 abaixo cobre o que É verdade hoje (auth bloqueia anônimo);
> alinhar rota↔nav (adicionar `can:governance.dashboard.view` na rota) é decisão de Wagner.

## UC-DSR-09 — Rota nomeada + throttle leve (render estático)
Status: 🧪 (Pest escrito — `DsRolloutControllerTest`: rota existe + `throttle:60,1`)
`/governance/ds-rollout` é `governance.ds-rollout.index`, render estático (lê 1 JSON local, zero query),
por isso throttle leve `60,1` (vs 20-30/min das telas com query pesada). Ancorado no `routes.php` e no
charter (§UX Targets: p95 < 500ms, zero I/O no controller).
**Pronto quando:** `Route::has('governance.ds-rollout.index')` = true e o middleware inclui `throttle:60,1`.

## UC-DSR-08b — Acesso: `auth` bloqueia anônimo (o que É verdade hoje)
Status: 🧪 (Pest escrito — GET anônimo ⇒ 302/401/403)
Refina UC-DSR-08 pro contrato real da rota: sem autenticar, `GET /governance/ds-rollout` retorna
302/401/403 (nunca 200). A verificação da permissão `governance.dashboard.view` no gate da rota é o
gap acima (hoje só no nav) — este UC cobre a barreira `auth` efetivamente presente.
**Pronto quando:** GET anônimo devolve status ∈ {302,401,403} e nunca 200.

## UC-DSR-01b — A TRAVA de governança sobrevive no fallback (número só de gate)
Status: 🧪 (Pest escrito — cenário `sem ds-ledger.json ⇒ measured=false + TODO ledger`)
Refina o UC-DSR-01 no nível do contrato do backend: **sem** `governance/ds-ledger.json`, o
`DsRolloutController::staticFallback()` DEVE devolver `measured=false`, `progressPct=0`,
`measuredAgainstSha=null`, `generatedAt=null` e `progressLabel` contendo `"TODO ledger"` — a tela nunca
pode exibir `%` real não-medido. **Com** o artefato presente, o inverso: `measured=true` + SHA + timestamp.
Teste NÃO-tautológico: a asserção vem do charter/ADR 0239 (git=SSOT · número só de gate), não do código.
**Pronto quando:** apagar o artefato faz `census.measured=false` + `progressLabel` com "TODO ledger";
o artefato presente carimba `census.measured=true` + `measuredAgainstSha`.

## UC-DSR-04b — Contrato canônico da prop `census` (Ledger não é hardcoded)
Status: 🧪 (Pest escrito — `assertInertia` sobre `census`)
O render Inertia de `governance/DsRollout` expõe `census` com as chaves canônicas que o `.tsx` consome:
`ledger[]` (linhas), `progressPct`, `progressLabel`, `measured`, `counts.{screens,done,references}`
(e `treeGuard`/`measuredAgainstSha`/`generatedAt` opcionais, fechando UC-DSR-05). Garante que a tabela
do Ledger vem da prop, não de array cravado no componente.
**Pronto quando:** `assertInertia` acha `census.ledger` + `census.counts.references` + `census.measured`.

## UC-DSR-10 — Render estático: sem `Inertia::defer` (eager por design)
Status: 🧪 (Pest escrito — guard sobre o source do Controller)
Ao contrário do `ModuleGradeController` (props caras → `defer`), o `DsRolloutController` lê 1 JSON local
(zero query) e renderiza eager — coerente com o charter (p95 < 500ms, zero I/O caro). Guard anti-regressão:
introduzir `Inertia::defer` aqui sem necessidade viola o espírito documentado.
**Pronto quando:** o source do Controller NÃO contém `Inertia::defer` e contém `Inertia::render('governance/DsRollout'`.
