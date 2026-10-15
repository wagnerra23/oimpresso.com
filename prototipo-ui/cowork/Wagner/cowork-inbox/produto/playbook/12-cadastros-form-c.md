---
sessao: "12"
titulo: Cadastros PR-c — Grupos de preço em drawer
dono: "[CL]"
base: 93ff8a960b74
---
# 12 · PR-c

Decisão [W] 2026-10-14 (`_ERRATA-INDICE-2026-10-14.md`): a aba **Grupos de preço** de `Produto/Cadastros` é a única das 6 que ainda abre o modal da Blade (`/selling-price-group`). Esta thread a leva para o mesmo drawer PT-02 das outras 5 (`_components/CadastroDrawer.tsx`, `sm:max-w-[760px]`).

- **Fonte de copy e campos:** `AbaGrupos` em `produto-cadastros.jsx` (:240 em diante) — título "Novo grupo de preço" / "Editar grupo de preço", campos Nome *, Descrição, chave "Grupo ativo" (sub "Inativo não aparece no PDV nem na exportação de preços."). O protótipo já desenha o drawer de 760px (2026-10-15).
- **Gravação:** mesmas rotas do modal clássico (`POST/PUT /selling-price-group`), com os nomes de campo dele.
- **Escopo:** o drawer grava **só o cadastro do grupo** (nome, descrição, ativo). Preço por grupo continua em Atualizar preço (thread 06). Se o form tocar qualquer valor, vale a regra mestre de valor (dupla prova + aprovação [W]).
- **Não toca:** `governance/design/contracts/produto-cadastros.contract.json`.
- **Prova:** `Index.tsx` sem link para `/selling-price-group/create`; comportamento por UC novo na lane `PHP / Pest (Estoque · MySQL)`.
