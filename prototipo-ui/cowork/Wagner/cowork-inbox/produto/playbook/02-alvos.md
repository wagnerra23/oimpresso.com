---
sessao: "A1-A2"
titulo: Alvos de Produto
dono: "[CL]"
base: 4fa39eb8f007
---
# A1 · A2 · ALVO

Hoje @4fa39eb8f007 não há nenhum `produto--*` em `governance/design/targets/`. Sem alvo, `pedido.mjs` sai exit 2 (NÃO MEDI).

- **A1** `produto--unificado--index` — medir **depois** da 00 (o protótipo muda na 00).
- **A2** `produto--cadastros--index` · `--etiquetas--index` · `--importacao--index` · `--atualizar-preco--index` — fontes: `produto-cadastros.jsx`, `produto-acoes.jsx` (Etiquetas, Importação, Atualizar preço).

Medir no tema escuro, após `__oiLazyDone` e duas leituras iguais de `querySelectorAll('*').length`, com `getComputedStyle`. Rodar antes um caso de sanidade de valor conhecido.

## Prova
No JSON do índice. Recibo `_saida-A1.md` / `_saida-A2.md`.
