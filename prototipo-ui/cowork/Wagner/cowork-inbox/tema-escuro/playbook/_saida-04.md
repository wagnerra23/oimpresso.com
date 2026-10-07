---
sessao: "04"
titulo: "Triagem no protótipo — saída da thread"
autor: "[CL]"
criado: 2026-10-07
base: d2b61b44e1
thread: 04-triagem-prototipo.md
veredito: "39 dos 40 achados da 01 corrigidos no build; 1 é falso positivo (amostra de cor). Sonda nas 196 rotas: 0 achado novo. Tema claro idêntico ao main nos 40 seletores."
---

# _saída 04 · Triagem no protótipo

## 1 · Triagem dos 40 achados do baseline

| achado | rotas | causa | o que virou |
|---|---|---|---|
| `cms-pill.on` / `.warn` | site, cms-blog, cms-depoimentos, cms-leads, cms-modulo | pastel fixo `0.95 0.04` | dark tintado (`cms-page.css`) |
| `cms-card-av` | cms-depoimentos, cms-leads | cor por `style` inline | cor por `--av-bg/--av-fg` + dark (`cms-page.css`, `cms-page.jsx`, `cms-extras.jsx`) |
| `usr-avatar` | usuarios, comissionados, comissoes | idem | idem (`usuarios-page.css` + 3 jsx) |
| `usr-role` | usuarios, funcoes | `style` inline + `filter: brightness` que **clareava** o pastel | `--rl-*` + dark; o filter vira `none` no escuro |
| `fnc-card-risco` | funcoes | fixo | dark (`acessos-page.css`) |
| `cnx-m.get/.post` | conn-docs | fixo | dark (`connector-page.css`) |
| `mod-badge.active`, `mod-stat-ic.ok/.danger` | modulos, conn-saude, conn-modulo | fixo | dark (`modulos-page.css`) |
| `kb-cat-pill` | kb | `style` inline com `oklch(0.94 …)` | matiz por `--kb-hue` + dark (`kb-page.css/.jsx`) |
| `kb-seg-btn.active` | kb | **invertido** (fundo `--text`, texto branco — ilegível no escuro) | tintado de accent (D1) |
| `tk-filter.active` | tarefas | invertido | tintado de accent (D1) (`styles.css`) |
| `btn.primary` | auditoria, portalos | invertido dentro de `.mockup-page` | tintado de accent (D1) (`mockup-pages.css`) |
| `div.dot` (passo atual) | portalos | fundo `--text` | accent (`mockup-pages.css`) |
| `ap-av`, `ap-nivel`, `ap-tipo`, `ap-handoff-alert`, `fj-hj-team-alert` | projects | fixos (`--ah`) | dark com o mesmo `--ah` (`forja-page.css`) |
| Tailwind `bg-stone-200`, `bg-sky-50` | cobranca | sem tradução escura no `pg-styles.css` | dark para stone-100/200/300 e sky-50 (`pg-styles.css`) |
| `inline-flex w-9` (switch desligado) | payment-gateways | `bg-stone-300` fixo | idem |
| `gmi-swatch` | cmp-grade | **falso positivo**: é a amostra da cor do produto | fica; vai para a allowlist (ver §3) |

O padrão do escuro é o que o próprio espelho já usava (`styles.css` `.os-stage.*`): fundo `oklch(0.30 0.07 H)`, texto `oklch(0.85 0.10 H)`, borda `oklch(0.42 0.07 H)`. Todo bloco novo está sob `[data-theme="dark"]`; o claro não muda.

## 2 · Prova (sonda da 01, local, Chromium do Playwright)

- **Antes** (21 rotas do baseline): 21 com superfície clara, 40 achados — bate com o baseline.
- **Depois**, 21 rotas: 1 rota com achado (`cmp-grade :: span.gmi-swatch`), 39 `↓ sumiu`, exit 0.
- **Depois**, todas as rotas: `196 rota(s) · 196 medida(s) · 0 sem medida · 1 com superfície clara`, exit 0. (A 01 mediu 194; o menu ganhou 2 rotas desde então.)
- **Controle de que "sumiu" não é "parou de renderizar"**: para cada um dos 40 seletores, medi contagem e cor em `main` × branch, nos dois temas. Claro **idêntico em 40 de 40**; contagem no escuro **igual em 40 de 40**; o fundo no escuro passou de L 0,92–0,97 para L 0,27–0,36.
- O controle pegou um defeito meu antes do commit: um comentário com `--av-*/--rl-*` tinha `*/` dentro, fechava o comentário cedo e derrubava a regra `.usr-avatar` no tema claro (3 rotas sem fundo). Corrigido e re-medido (o 40/40 acima é depois do conserto).

## 3 · Não feito, e por quê

- **`gmi-swatch` na allowlist.** A allowlist mora em `scripts/design/tema-escuro-probe.mjs`, e esta thread tem `nao_toca: scripts/`. Pedido: acrescentar `[class*=swatch]` (amostra de cor de produto) à `ALLOWLIST` no próximo PR que tocar a sonda; aí a última linha do baseline sai.
- **Baseline encolhido mesmo assim.** Tirei as 39 linhas de `scripts/design/tema-escuro-baseline.json` neste PR, fora do prefixo, porque o `_saida-01` manda "cada correção tira a linha do baseline no mesmo PR" — sem isso, a volta de um desses seletores não seria acusada como novo.
- **Cowork.** As correções estão no espelho; o build do Cowork só fica igual quando estes arquivos subirem (ver o corpo do PR).

## 4 · Prefixo tocado

`prototipo-ui/cowork/Wagner/`: 10 CSS (`acessos-page`, `cms-page`, `connector-page`, `forja-page`, `kb-page`, `mockup-pages`, `modulos-page`, `pg-styles`, `styles`, `usuarios-page`), 7 JSX (`cms-extras`, `cms-page`, `comissionados-page`, `comissoes-page`, `funcoes-page`, `kb-page`, `usuarios-page`) e este recibo. Fora do prefixo: `scripts/design/tema-escuro-baseline.json` (§3).
