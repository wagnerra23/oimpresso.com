# catalog/ — Índice de localização (para o Claude Code)

Estes catálogos existem para o Code **nunca adivinhar** onde algo está. Leia nesta ordem:

1. **`components.md`** — onde mora cada componente/global (símbolo → namespace → arquivo → props → consumidores).
2. **`classes.md`** — o que cada classe CSS `.oi-*` faz (definição, linha, grupo, modificadores).
3. **`screens/<rota>.md`** — a **build sheet** de cada tela: fonte, componentes usados, classes usadas, estados, dados/contrato, referência visual.
4. **`assets.md`** — fontes, ícones, imagens.

Regra de camadas (dependência só aponta para baixo):
`base → tokens → ui-kit (OIUi) → domain-ui (OIManut) → dados/estado → telas → shell`.
Nenhuma tela exporta componente de outra; primitivos compartilhados vivem em `oi-ui.jsx`/`oi-manutencao.jsx`.

> Os catálogos apontam o **caminho da fonte** de cada tela. A árvore de layout exata é lida direto no `.jsx` — os catálogos dizem *onde ler* e *o que usar*; a fonte diz *como está montado*; a `reference/` diz *como deve parecer*.
