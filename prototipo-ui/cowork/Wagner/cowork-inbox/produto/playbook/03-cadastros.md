---
sessao: "02-03"
titulo: Produto/Cadastros
dono: "[CL]"
base: 4fa39eb8f007
---
> Ficha da thread **03** (o mesmo texto serve 02, cada uma com o seu arquivo).

# 02 · 03 · Cadastros de apoio (6 abas)

Fonte F1: `produto-cadastros.jsx`. Trio proposto: `cowork-inbox/produto-telas-novas/Cadastros.charter.md` + `.casos.md` (16 UC) — copiar para `resources/js/Pages/Produto/Cadastros/Index.*`.

- **02** Unidades · Marcas · Categorias — permissões já existem (`unit.*`, `brand.*`, `category.*` só quando `category_type == 'product'`). Depende de D3.
- **03** Variações · Grupos de preço · Garantias — só depois da 01.

Regras: props `can` por aba; aba sem `view` → `EmptyState variant="no-perm"`; recusa de exclusão em uso com contagem do backend (`withCount`); contagem clicável → índice filtrado.

## Prova
No JSON do índice. Recibo `_saida-02.md` / `_saida-03.md`.
