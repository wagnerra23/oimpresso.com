---
patch: "00-INDICE.md"
modulo: Financeiro
autor: "[CC]"
criado: 2026-09-25
base_lida: 2c115a5ca250
---
# PATCH do índice do Financeiro — 2026-09-25 (delta, não substituição)

> **Threads 00 e 07 estão em execução.** IDs, fichas e recibos **não mudam**: o retorno continua sendo `_saida-00.md` e `_saida-07.md` nesta pasta. Não reabrir sessão — a sessão em curso lê este patch e ajusta **antes** de escrever o `_saida`.

## Por que existe
Conferência contra o `main` (`2c115a5ca250`) e contra o `scripts/qa/placar-indice.mjs` achou 3 problemas no que as sessões receberam:
1. **Provas que o placar não mede.** `medicao` · `a11y` · `diff` · `t7` · `execucao` · `runtime` saem **NÃO MEDIDA** e impedem `feito` para sempre (`TIPOS_ESTRUTURAIS` = arquivo · ausente · contem · nao_contem · json_com_chaves · um_de). Trocadas por provas estruturais no json do índice. As verificações humanas viram texto no `_saida` (§abaixo).
2. **Seletor morto.** A ficha 07 mandava escrever sob `.fin-cowork .fin-drawer-wide`. Em produção `fin-cowork` e `fin-drawer-wide` estão no **mesmo** elemento (`Index.tsx:2057`, `SheetContent` em portal no `<body>`, sem ancestral `.fin-cowork`) → a regra **não casa**. Mesmo defeito em `cowork-canon-financeiro-bundle.css:4033` e `fin-output.css:751/899`. **Seletor certo: `[role="dialog"].fin-cowork …`** (o que `fin-cowork.css` já usa nas Ondas 22b/23/25).
3. **Drawer forçado claro.** `fin-cowork.css` (bloco Onda 22b) fixa `[role="dialog"].fin-cowork { --surface:#ffffff; … background:#ffffff !important }`. O alvo foi medido no **dark**. Isso **não** entra na 07 (mudaria o escopo de uma thread em curso) — vira a **08**, atrás de decisão.

## §00 · ALVO — ✅ entregue (`_saida-00.md`, [CL] 2026-09-25)
- 11 seções `dw-*` em `governance/design/targets/financeiro--unificado.{alvo,secoes}.json`; valores = §3 da 07; 2 runs byte-idênticas; `secao-check` 11 conforme.
- As provas do índice foram **ajustadas ao que foi medido** (não o contrário): `arquivo` com `path` nos dois JSON + `json_com_chaves` `dw-painel · dw-hero · dw-abas · dw-rodape`. A chave `drawer`/`_ausentes` que este patch pedia antes **sai** — a thread já tinha decidido 11 seções em vez de 1.
- Dívida de dado apontada no recibo, **corrigida aqui**: (1) prova `arquivo` sem `path` → com path; (2) `variaveis.CSS`/`PROTOTIPO` eram listas → viraram strings (`CSS`, `CSS_IA`, `PROTOTIPO` com caminho completo).
- Fica com o [CL] (tarefa separada, não desta fila): `unificado.proto-baseline.json` STALE ("esperava 1 design system no shell, achei 3").

## §07 · Drawer — o que a sessão em curso ajusta
- **Se já escreveu regras sob `.fin-cowork .fin-drawer-wide`:** trocar para `[role="dialog"].fin-cowork` **no mesmo PR**. As provas cobram `[role="dialog"].fin-cowork .fin-dw-hero` e `[role="dialog"].fin-cowork .fin-drawer-footer` em `fin-cowork.css`.
- Remover (não sobrescrever) `.fin-cowork .fin-drawer-wide { padding-left: 22px; padding-right: 22px; }` de `fin-output.css:899` — regra morta.
- `fin-ia.css`: sem `background: white;`. Antes de editar, **medir** com `getComputedStyle` se `.fin-cowork .fin-curadoria …` casa dentro do drawer (depende de haver um 2º `.fin-curadoria` no corpo, `Index.tsx:2697-2701`).
- Guarda nova: não remover `[role="dialog"].fin-cowork .fin-drawer-tabs` (fix da Onda 25).
- **Não** mexer no bloco de vars claras — é a 08.
- Recibo `_saida-07.md` registra: A1–A12 no drawer vivo · zero botão sem nome · PR ≤300 linhas · T7 pendente (sem T7, "igual ao design" não se afirma).

## Thread nova · 08 — drawer segue o tema
- **Depende:** 07 feita + **D-FIN-DW-TEMA** ([W]).
- **Faz:** remover de `[role="dialog"].fin-cowork` as vars fixas claras e o `background: #ffffff !important`, herdando do `.cockpit[data-theme]`. Conferir que o drawer não fica transparente (motivo original da Onda 22b) — se ficar, usar `background: var(--surface)`.
- **Recomendação [CC]:** seguir o tema — mesmo defeito "CLARO-NO-DARK" que `fin-cowork.css` já corrigiu em 5 pontos da página (2026-07-10).
- Recibo: `_saida-08.md`.

## Decisão nova
`D-FIN-DW-TEMA` — o drawer passa a seguir o tema (recomendado) ou fica claro?
