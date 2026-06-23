# Token pipeline — D-06 (DTCG → Style Dictionary)

> **O que isto resolve.** Hoje o `tokens.css` é escrito à mão e copiado entre protótipo e produção — por isso o drift que o STATUS vive corrigindo (roxo vs azul, hex cru, `--accent` duplicado). Com o pipeline existe **uma fonte única** (`tokens/*.json` em formato **DTCG**) que **gera** o CSS e o TS. Mudou o token, mudou em todo lugar. É o passo que tira o DS de "folha de estilo bonita" e o torna **infraestrutura viva** (fecha o norte D-06).

## Camadas (não inverter)

```
tokens/primitive.json      escalas cruas (neutral, ink, purple, green…) · grupo `prim`, filtrado da saída
        ↓ (referência {prim.purple.500})
tokens/semantic.json       tema CLARO  — a UI consome SÓ esta camada
tokens/semantic.dark.json  tema ESCURO — mesmas chaves, primitivos escuros
        ↓ (Style Dictionary)
build/tokens.css           :root{…} + [data-theme="dark"]{…}
build/tokens.ts            objeto p/ React/TS
```

A UI **nunca** consome primitivo direto. Re-tematizar = mexer só na semântica. Trocar o **roxo canônico** = mudar 1 valor em `primitive.json` (`prim.purple.500`) — e isso é mudança de constituição (**só [W]**, ADR 0235).

## Rodar

```bash
cd pipeline
npm install
npm run build:tokens     # → build/tokens.css + build/tokens.ts
```

Node 18+. Saída determinística; o `build/` é gerado, **não se edita à mão** (cabeçalho avisa).

## Como entra no projeto real (proposta)

- `build/tokens.css` passa a ser a **fonte** importada tanto no protótipo (no lugar do `ds-v5/tokens.css` escrito à mão) quanto em produção (`resources/css/app.css` faz `@import`), garantindo D-06.
- `build/tokens.ts` cobre o consumo em React/Tailwind.
- CI roda `npm run build:tokens` e **falha se `build/` estiver fora de sync** com os fontes (anti-drift, casa com o Stylelint anti-hex/anti-redeclare já mergeado no #2054).

## Próximos passos (fora deste MVP)

- Adicionar elevação/sombra, z-stack, motion e densidade como tokens (já existem no `ds-v5` como CSS; portar pro DTCG).
- Plataforma **Figma** (Tokens Studio lê DTCG direto) — fecha design↔código.
- Origens por módulo (`--origin-*`) e paleta de avatar como grupos próprios.

> **Status:** proposta §10.4 · Cowork-local. Não está no git. Ver `prototipo-ui-patch/PROMPT_PARA_CODE_D-06.md` para a ponte zero-toque.
