---
sessao: "01"
titulo: Sonda tema-escuro
dono: "[CL]"
base: e22d2f85bcf4
---
# 01 · Sonda de render no tema escuro

**Antes de escrever:** ler `scripts/design/` e `scripts/governance/` — se já houver sonda de render dark, estender, não criar.

`scripts/design/tema-escuro-probe.mjs` (Playwright, como o `design-diff`): abre o espelho servido de `prototipo-ui/cowork/Wagner/oimpresso.com.html`, seta `data-theme="dark"`, e para **cada rota de `MOCK.MENU_FLAT` + ghosts + `SUPERADMIN_MENU`** (194 em 05/10):

1. `window.__go(rota)` → espera `__oiLazyDone` + **duas leituras iguais** de `querySelectorAll('*').length`.
2. Para todo elemento visível de `main.main` (≥10×6 px, `display≠none`): `getComputedStyle(e).backgroundColor`. **Converter pra luminância considerando o alfa** (`oklch(L C H / α)` e `rgba`: compor sobre o fundo do pai, ou ignorar α < 0,5). Claro = L > 0,78.
3. **Fora** (allowlist declarada no script, nunca heurística escondida): papel/impressão (`.vr-paper`, `.vd-trans-page`, `[class*=print]`, `[class*=paper]`, `[class*=-a4]`, `[class*=termica]`, `[class*=recibo]`, `[class*=proof]`, `[class*=pdf]`), placa veicular (`[class*=plate]`), tag de vestuário (`[class*=tag]`).
4. **Também abrir os estados escondidos:** hover no 1º item com `[class*=tip]`, selecionar 1 linha onde houver checkbox de lote. A falha de Vendas só aparecia assim.
5. **Caso de sanidade antes do veredito** (regra do protocolo): injetar `<div style="background:#fff;width:40px;height:20px">` em `main.main`; se não for detectado, o run sai **exit 2 NÃO MEDI**.
6. **Rota que trava** (a manual travou depois de 111): timeout por rota de 15 s; rota estourada sai `timeout`, não derruba o run.

Saída: JSON `{rota, claros:[{seletor, bg, n}], estado}` + resumo no stdout. Teste: a sanidade e um falso positivo conhecido (`color-mix(var(--text) 10%, transparent)` **não** pode acusar).

## Required (D2)

[W] 05/10: o check **bloqueia** o PR. Por isso, antes de ligar:
- **Baseline no mesmo PR.** O run inicial vai achar o que existe hoje (inclusive os 3 não triados). Grave `scripts/design/tema-escuro-baseline.json` com esse estado; o check falha só em **achado novo** (rota×seletor fora do baseline). Sem baseline, o primeiro PR de qualquer pessoa trava por dívida antiga.
- **NÃO MEDI não é verde nem vermelho:** sanidade falhou ou ambiente quebrou → exit 2, o job sai como erro de infraestrutura, com mensagem dizendo isso — nunca como "tema ok".
- **Timeout de rota não reprova:** sai listado; 3+ timeouts no mesmo run → exit 2.
- Baseline só **encolhe**: entrada que some do run sai do arquivo no mesmo PR (o script avisa).

## Prova
No JSON do índice.
