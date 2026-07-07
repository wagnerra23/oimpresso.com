# Sessão 2026-07-07 — Comparação design×prod MEDIDA + freshness de deps + PageHeader roxo

> Arco: começou no "branco infinito" do Financeiro, virou uma **auditoria do processo de comparar design×prod** (o Wagner pegou 3 furos meus em sequência), e terminou com **3 lições de código mecanizadas** + o loop do PageHeader roxo fechado. **7 PRs MERGED** (#3912, #3915, #3917, #3918, #3919, #3920, #3921).

## Linha do tempo (o que o Wagner pediu → o que consertou)

1. **"branco infinito no fim de /financeiro/unificado"** → **[#3915](https://github.com/wagnerra23/oimpresso.com/pull/3915)**: causa raiz medida ao vivo — o header `sr-only` do CommandDialog (⌘K) é `position:absolute` e, sem ancestral positioned no scroller `.main-body`, ancorava no `<body>` com `top:~4000px` → esticava o `<html>` → a JANELA ganhava ~3000px de vazio. Fix = `.cockpit .main-body { position: relative }`. Conserta a classe inteira (toda tela do shell). Provado em prod: `html scrollHeight 3994→582`, `scrollTo(99999)→scrollY 0`.

2. **"testar se o protocolo aplicar-protótipo voltou"** → self-test `detectar-telas.mjs` 6/6, pré-voo OK, script == origin/main. **Descoberta:** não existe "git do design" — a fonte é o **projeto Cowork `019dcfd3` via `DesignSync.get_file`** (leitura livre, ADR 0315). O `financeiro-page.jsx` bate byte-a-byte com o espelho `prototipo-ui/cowork/`.

3. **"iguale a tela ao design"** → **[#3917](https://github.com/wagnerra23/oimpresso.com/pull/3917)** dark-mode: a prod roda `html.dark` e a tabela tinha **~99 classes de tema-claro fixas** (`text-stone-*`/`bg-stone-*`/`border-stone-*`/`bg-white`) → texto escuro-no-escuro invisível. Fix = tokens shadcn dark-aware (`text-foreground`/`text-muted-foreground`/`border-border`/`bg-muted`/`bg-card`). SÓ cor. Casos G-6 bump. Provado em prod (thead legível).

4. **"o processo é mais importante do que arrumar; documentar pra não errar nunca mais"** — o Wagner pegou que eu **comparei design×prod NO OLHO** e declarei "igual", perdendo center×left dos KPI + o roxinho. Essa classe já tinha acontecido em 06/07 (gerou o PROTOCOLO-COMPARACAO-RUNTIME) → **strike 2** pela regra two-strikes. Virou defesa mecânica:
   - **[#3918](https://github.com/wagnerra23/oimpresso.com/pull/3918)** `prototipo-ui/design-diff.mjs` — o `/design-diff` previsto na ADR 0299. Split igual ao `cowork-mirror-freshness`: `--probe` (sonda medida injetada IGUAL nos dois renders via Chrome MCP) → `--compare a.json b.json --check` (D2 layout · D4 tipografia · D6 cor · **D8 alinhamento** = o buraco). `--selftest` reproduz o incidente exato (8/8). **LC-06** no `LICOES_CODE` (classe `visual-compare-eyeball`, Ocorrências:2). +D8 no protocolo.
   - **Camada de ativação** (o "tinha que ter um gatilho, hook ou runbook" do Wagner): skill Tier B `comparar-design-prod` + hook `design-compare-protocol.mjs` (UserPromptSubmit, testado 11/11 incl. typo "desing"). Doc sem gatilho = o canal que o agente não lê (ADR 0315).

5. **"faltou baixar os CSS vinculados? ele não baixou tudo?"** → **[#3919](https://github.com/wagnerra23/oimpresso.com/pull/3919)** freshness manifest **v3**: media só as **3 âncoras de charter** e era cego pras **~100 deps de render** (`app.jsx`, `styles.css`, `tokens.css`, css por módulo). O drift real: o Wagner mudou o PageHeaderNav pra roxo no `app.jsx` do Cowork e a rodada "3 SYNC" ficou verde. Fix = `parseShellDeps(html)` deriva as deps dos `src/href` do shell (strip `?v=`, sem CDN). **LC-07** (`freshness-manifest-partial-coverage`). E2E: `⛔ STALE app.jsx`, exit 1.

6. **"pode ver a cor do PageHeader do financeiro"** → medido: design subnav ativo = `var(--accent)` **roxo** (0.72 dark), prod = **verde 145** (hue-do-grupo). É a mudança que o Wagner fez no `app.jsx` do Cowork (`onColor/onBorder` hue → `var(--accent)`), presa no vivo. Loop fechado:
   - **[#3920](https://github.com/wagnerra23/oimpresso.com/pull/3920)** re-export do `app.jsx` vivo pro espelho (mata o STALE) + ledger de frescor (ADR 0324 D1: 5 SYNC · 0 STALE).
   - **[#3921](https://github.com/wagnerra23/oimpresso.com/pull/3921)** aplica na prod: `PageHeaderTabs.tsx` tab ativo `text-primary + border-b-primary` (era hue-verde). **GLOBAL** (todo subnav ghost) — fiel ao design (1 componente pra todo módulo) + alinhado à ADR 0190 roxo universal.

## Meta-lições (perenes)

- **Comparação design×prod é MEDIDA (computed style/DOM), nunca no olho.** Screenshot é ilustração; a prova é a medida. Mesma sonda nos dois lados. (LC-06)
- **Frescor cobre as deps do render, não só a âncora da tela.** `app.jsx`/`styles.css`/`tokens.css` são o vetor de drift de design. (LC-07)
- **Fonte de design = Cowork `019dcfd3` via DesignSync** (não há git do design). `localhost:8765` = python server no staging fixo `~/Downloads/_cowork-handoff-staging/.../project/` (bundle 02/07; efêmero, morre no reboot).
- **Pegadinha do render local:** o disk-cache do XHR do Babel mascara design velho — `location.reload()` não revalida XHR. Fix: `fetch(arquivo, {cache:'reload'})` antes do reload. (mesma família LC-06/07)
- **`bg-white`/`text-white` em fundo colorido = ok; `bg-white` de card/dropdown = quebra no dark** → `bg-card`.

## Pendências pra próxima sessão

- **(ADR 0190 à parte) "roxinho" no dark:** o primary fica `0.55` no dark (design brilha pra `0.72`). Brightening app-wide do primary = **emenda ADR 0190** — decisão do Wagner (app inteiro vs só Financeiro). NÃO feito.
- **Drawers/sheets ainda com `stone`:** `FinOcrBoletoSheet` (21), `TituloEditSheet` (14), `FinAnexosPanel` (9) — dark-mode follow-up (só aparecem ao abrir).
- **design-diff rodada completa** do Financeiro (D1 rede + D3 ícones + D5 footer) — nesta sessão só rodei D2/D4/D6/D8.
- **Impacto GLOBAL do #3921** (subnav de TODO módulo vira roxo) — Wagner mergeou; smoke visual nos outros módulos (Cadastro/Comercial) vale conferir.

## Estado MCP no momento do fechamento

MCP **indisponível** nesta sessão (o brief veio do cache do hook SessionStart — Brief #317, off-cycle, sem cycle ativo). Não rodei `cycles-active`/`my-work` ao vivo (tools MCP não conectadas). Estado por git: 7 PRs MERGED, `origin/main` @ `602190ef59`. Ledger de frescor: 5 entradas (última 2026-07-07, 5 SYNC/0 STALE).
