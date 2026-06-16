---
date: 2026-06-16
time: "2006 BRT"
slug: "financeiro-hero-gabarito-licao-copiar"
tldr: "Financeiro reconciliado com o gabarito Cowork: hero escuro→claro (#2844), sub-nav corrigido (pegava abas do Caixa, ADR 0180 split, #2847), realizado a tamanho de apoio (#2851), hero bate PIXEL com gabarito (#2856: --fs-2/--sh-2/spark/alarm). 4 PRs merged+deployados+verificados live. LICAO-MAE: parei de COPIAR o gabarito e ADIVINHEI valores (10.5px vs --fs-2; --sh-1 vs --sh-2) — Wagner perdeu a paciencia. Aberto: drawer acabamentos (lentes coloridas, ausente em prod)."
decided_by: [W]
cycle: "CYCLE-08"
prs: [2844, 2847, 2851, 2856]
us: []
next_steps:
  - "Drawer 'acabamentos': confirmar com Wagner se é as seções coloridas por domínio (.fin-lens-ic-pos/neg/warn/accent + .fin-lens-h4/.fin-lens-seg) — gabarito tem, prod NÃO (nem markup nem CSS); porte exige TSX + CSS. NÃO começar sem confirmar (README do handoff: 'ask if ambiguous')."
  - "Antes de qualquer outro fix visual no Financeiro: COPIAR do gabarito (bundle Claude Design), nunca adivinhar valores."
---

## Estado MCP no momento

Cycle CYCLE-08 (Receita — Onda A). 4 PRs desta sessão merged+deployados (alguns via re-dispatch pós-timeout SSH Hostinger). Drift cycle: os PRs são polish de Financeiro, off-cycle.

## O que aconteceu

Sessão começou com o handoff Cowork **CONSERTAR-ELO** (hero do Financeiro era "caixa preta"). Validei contra `main` e o diagnóstico estava **vencido** (os "2 bundles dup" já fundidos no #2127; o hero não vive no bundle e sim em `fin-cowork.css`). Conserto cirúrgico → cadeia de fixes:

1. **#2844** — hero `.fin-stat-hero` escuro (`oklch(0.22 0.01 80)`) → claro (gradiente accent 295). Aprovado por screenshot. Deploy travou ~21min em **timeout SSH Hostinger** (porta 65002 caiu, 443 seguia 200) — `gh api .../force-cancel` no run pendurado + re-disparei UMA vez (canon `hostinger.md`). Verificado live.
2. **#2847** — Wagner mostrou WR2 Sistemas com **abas erradas** ("Caixa/Conciliação/Contas Bancárias/Extrato" em vez do hub). Diagnosticado no browser-MCP: `FinanceiroSubNav.tsx` fazia `.find(group==='financas')` → pós-split ADR 0180 (26/mai, 4 entries flat) pegava SEMPRE a 1ª (Caixa). Fix: `pickFinanceiroEntry(menu, active)` (módulo puro `financeiroMenu.ts`) escolhe a entry DONA do `active`. ~11 telas afetadas. + gate `fin-subnav-gate`.
3. **#2851** — "realizado" do hero renderizava em 28px (igual o número-herói) → dois números gigantes. Fix: `.fin-stat-hint b { font-size: var(--fs-1) }`. **ERRO: chutei --fs-1.**
4. **#2856** — Wagner deu o **handoff Claude Design real** (`oimpresso.com.html`). Li README + segui imports (`financeiro.css`+`fin-boletos.css`). Comparei prod×gabarito e o gabarito dizia `--fs-2` (não --fs-1), `--sh-2` (não o --sh-1 que eu "achei" melhor), spark h34/op.9, alarm fs-1/14%. Copiei os valores exatos.

Wagner perdeu a paciência com o padrão "adivinhar em vez de copiar o gabarito".

## Artefatos gerados

- `resources/css/fin-cowork.css` — hero claro + valores do gabarito (#2844, #2851, #2856)
- `resources/js/Pages/Financeiro/_shared/FinanceiroSubNav.tsx` + `financeiroMenu.ts` (novo, puro) — #2847
- `tests/finHeroLight.spec.ts` + `tests/financeiroSubNav.spec.ts` — guards
- `.github/workflows/fin-hero-gate.yml` + `fin-subnav-gate.yml` — registrados em `gates-registry.json`

## Persistência

- **Git:** 4 PRs em `main` (#2844/#2847/#2851/#2856), deployados+verificados live.
- **Gabarito Cowork** extraído em `/tmp/oimp_extract/` (efêmero — re-baixar do handoff se precisar).
- **MCP:** webhook propaga ~2min pós-push deste handoff.

## Próximos passos pra retomar

Wagner precisa confirmar o "acabamento" do **drawer** (clicar num título). Candidato: seções coloridas por lente (`.fin-lens-*`). Se confirmado: porte markup (`Unificado/Index.tsx`) + CSS, copiando do gabarito `financeiro.css:305-513`.

## Lições catalogadas

- **L-MÃE (Wagner explícito, repetido): COPIAR do gabarito, não tirar da cabeça.** Quando existe o protótipo aprovado, adivinhar valor (10.5px vs --fs-2; --sh-1 vs --sh-2) é o pior erro — gera retrabalho peça-por-peça e queima a paciência. Método certo = `cowork-prototype-replication`: baixar o handoff Claude Design, difar prod×gabarito, copiar exato.
- **Handoff Claude Design** acessível via `api.anthropic.com/v1/design/h/<hash>?open_file=...` → **gzip'd tar** (curl + `gunzip` + `tar xf`). DesignSync tool precisa login claude.ai (indisponível headless). WebFetch falha >10MB.
- **Token mapping ds-v6→cockpit:** `--sunken`→`--bg-2`, `--text-2/3`→`--text-mute`, `--fin-line`→`--border`, `--accent-hi`→`color-mix`, `--t-1/--ease`→inline (não-cor). **NUNCA inline `oklch` cru** (conformance-gate cor-crua bloqueia).
- **Hostinger deploy:** SSH 65002 flaky; `gh api .../actions/runs/<id>/force-cancel` mata run pendurado (`always()` SSH não responde a cancel normal); re-disparar UMA vez (não martelar — `hostinger.md`).
- **module-grades-gate é ADVISORY** (não nos 16 required); TeamMcp 80↔79 é drift transiente do main, recuperou sozinho.

## Pointers detalhados

- Gabarito hero: `prototipo-ui/...` (26/mai, defasado) vs handoff Claude Design (atual). `fin-boletos.css` `.fin-stat-hero`.
- `hostinger.md` (`memory/reference/`) §SSH flaky + force-cancel.
- Drawer gabarito: `financeiro.css:305-513` + `financeiro-page.jsx`.
