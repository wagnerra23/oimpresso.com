# PEDIDO [CL] — Onda 11: pré-compilar o JSX do Cowork (parar de transpilar no navegador)

> Ponte, não memória. Escrito por [CC] 2026-08-24. Não commitado — as tools de GitHub daqui são read-only.
> Contexto: ondas 9/10/12 já foram aplicadas **no protótipo** (carregador do `oimpresso.com.html`) e resolvem rede. Sobra CPU, e isso só se resolve com passo de build no repo.

## O problema, medido no host
`oimpresso.com.html` carrega ~150 arquivos `.jsx` e **transpila todos com `Babel.transform` no cliente, em toda carga**. O `@babel/standalone` (≈2,8 MB) é baixado só pra isso. O carregador diferido já:
- baixa numa **janela deslizante de 8** à frente do compilador (onda 9 — antes eram ~150 `fetch` no primeiro frame);
- **prioriza a família da rota** atual por partição estável (onda 10);
- devolve a thread a cada 6 módulos.

Nada disso reduz o custo de compilar. É o último gargalo de carga do protótipo.

## O que peço
Um passo de build que gere `.js` pré-compilado ao lado de cada `.jsx` do export, e um host que carregue o `.js` quando existir.

1. **Script** `scripts/cowork-precompile.mjs` — varre `prototipo-ui/cowork/**/*.jsx`, roda `@babel/core` com o preset `react`, escreve `<nome>.jsx.js` mantendo `//# sourceURL`. `--check` no CI falha se algum `.js` estiver mais velho que o `.jsx` (mesma linhagem do `cowork-paridade.mjs`).
2. **Carregador** — no `start()` do host, tentar `data-src + ".js"` primeiro; cair no `.jsx` + `Babel.transform` só no 404. Assim o protótipo continua funcionando sem o build (esteira não pode depender de CI).
3. **Babel standalone condicional** — se todos os módulos resolveram em `.js`, o `<script>` do `@babel/standalone` não precisa entrar. Vale medir antes de decidir: `app.jsx`, `sidebar.jsx` e os outros `type="text/babel"` eager também precisariam do mesmo tratamento pra a tag sair de vez.
4. **Guard** — `cowork-ssot-guard.mjs` (R1) precisa de exceção pros `.jsx.js` gerados dentro de `cowork/`, do mesmo jeito que os 2 `.md` gerados do `cowork-paridade`.

## Decisões que são de [W], não minhas
- **D1** Os `.jsx.js` são **artefato commitado** ou gerado no CI? Commitado = protótipo sempre rápido, diff sujo. Gerado = diff limpo, esteira depende do CI.
- **D2** Vale minificar? Ganha bytes, perde a leitura do fonte no navegador (que hoje é como [W] confere a tela).

## Ordem
D1 → PR-1 script + `--check` → PR-2 carregador com fallback → PR-3 exceção no guard → medir → PR-4 (só então) tirar o Babel standalone.
