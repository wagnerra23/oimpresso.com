---
date: "2026-07-17"
time: "21:46 BRT"
slug: visreg-item7-font-blindness
tldr: "ITEM 7 (Fidelidade da captura) fechado — os 3 sub-fases 3a/3b/3c em main (#4385/#4387/#4497). O gate visual parava de mentir sobre seed/engine, voltou a ver o <select> de Compras, e o force de Arial deu lugar a fonte self-hosted. 3c mergeado por decisão [W] SEM a contraprova de font-family validada — mecanismo em main, garantia ainda não provada."
prs: [4385, 4387, 4497]
decided_by: [W]
related_adrs: [0108-regressao-visual-pest-browser-tier-2, 0290-fidelity-lock-v0-recusado]
next_steps:
  - "Contraprova real do 3c: medir via getComputedStyle no browser do gate qual das 7 declaracoes de --font-sans e a efetiva nas 6 telas, trocar ELA, provar que visual-regression FICA VERMELHO. So isso prova que a cegueira de font-family morreu."
  - "KB 77->76 (advisory, drift de 10 commits de KB sem atualizar module-grades-baseline.json) — dono do KB atualiza o baseline ou aplica label."
---

## Estado MCP no momento

MCP oimpresso **desconectado** nesta sessão → checklist por fallback filesystem (git). `main` @ `1fe8f4b840` no início do handoff; os 3 merges do ITEM 7 já dentro (`cd5052d3aa` 3a, `d249a36dc9` 3b, `ed89a2959d` 3c). Off-cycle. Sessão longa (contexto perto do limite).

## O que aconteceu

Fechei o **ITEM 7 "Fidelidade da captura"** — último aberto do hardening do gate visual. A auditoria original deu 4,5🔴 com receita errada; um cético mediu 5 erros nela. Confirmei os 5 por varredura contada (não confiei na receita nem no cético cego):

- **3a COMMENT ROT** (#4385): 2 justificativas FALSAS no gate erradicadas. `"varia com o seed"` — **0** aleatoriedade nos 6 seeders Visreg *e* nos 4 do `DatabaseSeeder`; eram **2** sites, não 1. `"ratio bater com o engine nativo"` — moot nas 6 telas do `PixelBaselineTest` (passam `baselineFile` → nosso motor nas 2 pontas), load-bearing só nas outras 53.
- **3b desmascarar `select`** (#4387): a máscara escondia **1** controle real (Compras/Index:674). 12 baselines regeneradas; **recusei 5** não-relacionadas (Sells/Create + Financeiro) — o gate ENFORCING provou que eu estava certo (passaram contra baseline antiga = ruído).
- **3c self-host + remove Arial** (#4497): `@fontsource` IBM Plex (CDN fora), `VisregThreshold` não gera mais baseline pelo plugin (era **1 ramo**, não 4 suítes), guard `aguardarFontesReais()` (`document.fonts.check`), 59 baselines na fonte real. Re-cortado de main fresco (a v1 #4389 ficou 100 commits atrás; fechada+superseded).

## Decisão [W] registrada sem re-litigar

Mergear o 3c **antes** da contraprova validada. A contraprova (#4390, fechada) passou VERDE trocando `--font-sans` → Georgia, mas **inconclusiva**: mirei 1 das **7** declarações sem confirmar a efetiva. O 3c é estritamente melhor que o force (cego por construção) e o guard falha alto se o self-host quebrar — mas **não provei** que fecha a cegueira de `font-family`. [W] optou por mergear o mecanismo. Ok.

## Artefatos gerados

- 3 PRs MERGED: [#4385](https://github.com/wagnerra23/oimpresso.com/pull/4385) (+34/−7), [#4387](https://github.com/wagnerra23/oimpresso.com/pull/4387) (18 arq), [#4497](https://github.com/wagnerra23/oimpresso.com/pull/4497) (11 fontes + 59 baselines)
- 2 PRs CLOSED: #4389 (v1 do 3c, superseded) · #4390 (contraprova descartável)

## Persistência

git (3 squashes em `main`) · MCP (webhook ~2min) · sem BRIEFING (é infra de teste, não módulo).

## Lições catalogadas (§5-código candidatas)

1. **Gate vácuo em mim mesmo**: PR empilhado com base de BRANCH → `visual-regression.yml` (`branches:[main]`) **não dispara** → 25 verdes com **0 visual** = verde vazio. Só retarget pra `main` + close/reopen (o `edited` não está em `types:`) fez o gate rodar. (Ver `memory/reference/ci-nao-dispara-branch-conflita-main.md`.)
2. **Recusei 5 de 17 baselines regeneradas** por não terem relação com a mudança (0 `<select>` na árvore; assimetria compact-só). O gate ENFORCING confirmou. Re-baselinar as 17 seria assar ruído em silêncio.
3. **Dois quase-falsos-achados que só não viraram afirmação porque conferi**: "repo vazio" (era `git ls-files` escopando por cwd — pegadinha já catalogada) e "token morto: 0 Georgia no bundle" (o build rodou sem `node_modules` e gerou 0 CSS; li silêncio como evidência). Retirei o 2º no PR.
4. **Premissa "cross-process" do cético é FALSA**: o servidor HTTP do plugin roda no MESMO processo (`ServerManager:86-90` `new LaravelHttpServer`; `LaravelHttpServer:238/279` `app()->make(HttpKernel)`); `GlobalState::flush()` não toca Carbon; `Util.php:403` respeita `setTestNow`. Não virou comentário (não-provado sem CI = hipótese, não achado — §5 2026-07-15).

## Próximos passos pra retomar

Contraprova real do 3c (medir `getComputedStyle` no gate → trocar a declaração efetiva → provar vermelho). Branch de partida: `main` fresco. Sem isso, a cegueira de `font-family` segue **não-provada-morta**.

## Pointers detalhados

- Gate visual: `tests/Browser/Support/VisregThreshold.php` (motor + guard) · `tests/Browser/CoreScreens/*BaselineTest.php` (6 sites)
- Self-host: `resources/js/app.tsx` (6 imports `@fontsource`) · `resources/views/layouts/inertia.blade.php` (CDN removido)
- §5 código: `memory/proibicoes.md` (lápides 2026-07-15 "achado sem prova/varredura" + 2026-07-16 "presente não")
