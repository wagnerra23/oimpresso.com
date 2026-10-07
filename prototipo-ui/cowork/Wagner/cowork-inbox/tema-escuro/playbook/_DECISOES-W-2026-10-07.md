# _DECISOES-W-2026-10-07 — Tema escuro (Cowork → Code)

> **Fonte:** [W] 2026-10-07, textual: *"tema escuro: pode decidir"* — delegação explícita ao [CC].
> **Origem:** `_saida-02.md` §4 (baseline de produção e gate).

## D-BASELINE · baseline de produção

**Resposta: sim, criar baseline — em modo catraca.**

- A baseline congela as **19 Pages** da `_saida-02` §3 como dívida conhecida (Page + seletor + contagem).
- O step deixa de ser só advisory **para o que é novo**: Page fora da baseline com superfície clara, ou contagem que **sobe** numa Page da baseline → falha. Contagem que desce → o PR atualiza a baseline pra baixo (nunca pra cima).
- O step passa a rodar em todo PR que toca `resources/js/Pages/**` ou `resources/css/**`, não só quando toca a sonda. Se estourar o teto de 25 min do job, separar em job próprio — não reduzir universo.
- `swatch` entra na allowlist (já pedido na `_saida-04`).

## Ordem da thread 03 (zerar a dívida)
1. `footer.fx-shell-foot` do Fiscal → 7 Pages de uma vez.
2. `fin-filter-cb` → PlanoContas + Unificado.
3. `Sells/CreateV3` placa "Total da venda" (D1 do índice).
4. O resto, uma Page por PR, menor primeiro.

## Edição pedida no json
```json
[{ "id": "D-BASELINE", "respondida": true, "resposta": "baseline catraca com as 19; novo ou aumento falha; roda em PR que toca Pages/css" }]
```
