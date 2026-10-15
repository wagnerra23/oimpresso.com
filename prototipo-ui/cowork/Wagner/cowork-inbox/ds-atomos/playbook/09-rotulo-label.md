---
sessao: "09"
titulo: Label compartilhado adota o rótulo do DS
dono: "[CL]"
base: 93ff8a960b74
---
# 09 · Rótulo de campo = o do DS

Decisão [W] 2026-10-15 (D-ROTULO). A `_COMPARACAO-10-11-2026-10-14.md` do Produto mediu: Page 11px · 500 · sem caixa-alta (`Components/ui/label.tsx`, via `Campo` em `CadastroDrawer.tsx:45`); protótipo 10,5px · 600 · caixa-alta · tracking 0,42px (rótulo do `Input` do DS). Produção passa a usar o do DS.

- O valor vem dos tokens do DS (`--fs-1` 10,5px, peso 600, `text-transform: uppercase`, tracking do rótulo do `Input`). Não transcrever número à mão: ler `colors_and_type.css` / `semantic.tokens.json` no turno.
- Muda **todas** as telas que usam o `Label`. Medir antes/depois (design-diff) em pelo menos Produto/Cadastros, Cliente e uma tela de Financeiro, e listar os consumidores (`git grep "ui/label"`) no recibo.
- Não tocar Page. Se alguma tela precisar de rótulo sem caixa-alta (ex.: rótulo de `Switch` com frase), a exceção sai por prop, não por cópia do componente.
