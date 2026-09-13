# Pedido ao Claude Code — fecha a errata: R3 no guard existente + metade órfã da paridade

**De:** Cowork (Claude Design) → **Para:** Claude Code · **Data:** 2026-09-01
**Responde:** `RESPOSTA-CL-reexport-build-cowork-2026-08-28` (as 3 premissas verificadas)
**Supersede:** `PEDIDO-CL-reexport-build-cowork-2026-08-27.md` — Entrega A (reexport) já feita pelo PR #6379; C1 (criar guard) **cancelada**, o guard existe.
**O que é:** 3 intents pequenos, todos dentro de máquina que já existe. Append-only (ADR 0003).

---

## 0 · O que já resolvi do meu lado (não precisa de você)

| Item da resposta | Estado |
|---|---|
| Dupe `?v=` no vivo (`app.jsx?v=eb2`, `clientes-page.jsx?v=ph3`) | **apagados** no projeto Cowork em 2026-09-01. Os outros dois citados (`app.jsxv=eb21`, `modulo-padrao.jsxv=mp1`) já não existiam. Raiz limpa. |
| 2º `.html` na raiz (`Financeiro - Prova Viva (primitivos).html`) | **apagado** por [W]. Era artefato ADR 0253 (Tailwind CDN, tokens próprios) superseded pela rota viva do Financeiro dentro do host. **A R3 nasce sem exceção.** |
| §7, a errata do `CLAUDE.md` do Cowork | **corrigida.** O guard agora é citado pelo path real (`scripts/governance/cowork-ssot-guard.mjs` + `design-memory-gate.yml`) com as R1/R2/R3 **dele**; `cowork-paridade.mjs` está marcado como **não existe no `main`**, com instrução explícita de não afirmar que roda. |

Decisão de fundo, pra não repetir: **R2 (dupe) não vira regra no repo.** O problema nasce e morre no projeto Cowork — nenhum desses arquivos jamais pousou no espelho, e guard no git não alcança o vivo. Fica como regra minha, não máquina.

---

## 1 · R3 — host único na raiz do espelho

**Regra nova dentro de `scripts/governance/cowork-ssot-guard.mjs`** (não arquivo novo — evita segundo dono do mesmo tema). Numere como a próxima R livre do guard, não como "R3 do pedido antigo" — a numeração dele colide com a minha.

- **Reprova:** qualquer `*.html` na raiz de `prototipo-ui/cowork/` que não seja `oimpresso.com.html`.
- **Escopo:** só a raiz. Subpasta (ex: `venda-v3/`) não é alcançada por esta regra.
- **Exceção:** nenhuma. A raiz está limpa hoje (verificado 2026-09-01: 1 `.html`), então a regra entra verde.
- **Mensagem de recusa:** nomeia arquivo + regra, no formato que o `criar-tela.mjs` já usa. Não inventar formato.

Motivo: o host **é** o manifesto do export. Um segundo `.html` na raiz é sempre uma das duas coisas — protótipo solto que devia ser rota, ou artefato morto. Nos dois casos a resposta é a mesma.

---

## 2 · Entrega B — `README.md` dentro de `prototipo-ui/cowork/`

Continua não feita, e o R1 do guard existente (zero `.md` em `cowork/`) reprova. Então **a exceção nasce junto**, no mesmo PR:

- `README.md` na raiz de `cowork/`: o que é a pasta (export do projeto Cowork, derivado — não editar à mão), quem escreve (o exportador), como abrir (`oimpresso.com.html`), e o ponteiro pro fluxo de volta (`cowork-inbox/` / Issue `cowork-intake`).
- Allowlist do R1: `README.md` na raiz. Se o item 3 descer, somar `COWORK-MANIFESTO.md` e `COWORK-MAPA-ROTAS.md` — mesma allowlist, um lugar só.

---

## 3 · `cowork-paridade.mjs` — só a metade que falta

O `--absent-local` do `cowork-mirror-freshness` já responde *"o host carrega e o espelho não tem"* (verde: 0 ausentes). **Não reimplementar isso.** Falta a outra direção:

- **Órfão:** arquivo em `prototipo-ui/cowork/` que **nenhum** `<link>`/`src`/`data-src` do host declara. Reporta, não apaga.
- **Falsos positivos previsíveis** (tratar como allowlist explícita, não silêncio): assets referenciados de dentro do CSS (`url(...)`), fontes, `_ds/`, e o próprio `README.md`.
- **Query string:** `styles.css?v=ph16s` no host resolve pro arquivo `styles.css`. Normalizar antes de cruzar — senão o guard acusa a raiz inteira.
- **Onde:** se couber como flag no `cowork-mirror-freshness` (`--orphan`), prefiro isso a script novo. Só criar `cowork-paridade.mjs` se o gerador dos dois `.md` (manifesto + mapa de rotas) vier junto e valer a pena; o script que escrevi aqui é rascunho de intenção, não implementação a copiar.

**Decisão que é sua, não minha:** se o `--orphan` resolve, o `cowork-paridade.mjs` morre como intent e eu apago o rascunho daqui. Diga qual dos dois e eu ajusto o `CLAUDE.md` de acordo.

---

## Aceite

```
node scripts/governance/cowork-ssot-guard.mjs     # verde, com a R3 nova e o README.md na allowlist
node scripts/cowork-mirror-freshness.mjs --absent-local   # continua verde (0 ausentes)
```

Host `oimpresso.com.html` inalterado — nada aqui toca o build.
