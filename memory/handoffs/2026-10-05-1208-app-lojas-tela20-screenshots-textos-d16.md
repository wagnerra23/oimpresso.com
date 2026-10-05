---
date: "2026-10-05"
time: "12:08 BRT"
slug: app-lojas-tela20-screenshots-textos-d16
tldr: "Tela 20 (Novo produto, sem preço) ligada no app de loja depois de medir a API em produção (401/401, controle 404). Screenshots de loja D16 refeitos do main com a Venda rápida (Android 8, iPhone 9) e textos de loja reescritos para a D16. Os 5 PRs entraram. Falta o que é só do [W]: Vaultwarden, Demo lojas, login real, envio e a D9."
decided_by: [W]
prs: [8638, 8641]
next_steps:
  - "[W]: senhas das contas demo no Vaultwarden, pacote Demo lojas para o gestor.demo, login real, envio às lojas e tipo de conta do Play Console."
  - "Sessão Coordenar app das lojas + [W]: atualizar a D9 de docs/lojas-app/DECISOES.md, que ainda cita as 7 áreas (D13)."
  - "Refazer screenshots e a tabela Conferir no build quando entrarem as telas que faltam da D16."
---

# App das lojas: tela 20 ligada, screenshots e textos D16

## O que foi feito
| PR | Repo | Estado | O quê |
|---|---|---|---|
| #51 | oimpresso-app | mergeado (`45196035`) | Liga a tela 20 (Novo produto, sem preço). Branch atualizado com `main`; tsc limpo, 131 testes, build de loja sem dado de demo (controle: build demo tem) |
| #63 | oimpresso-app | mergeado | 3º lote de screenshots D16 (8 telas), anterior ao #39 |
| #64 | oimpresso-app | mergeado | 4º lote, do `main` `f107dcf`: Android 8 (máx. Play), iPhone 9 (+ Tarefas), com Venda rápida |
| #8638 | oimpresso.com | mergeado | `docs/lojas-app/textos/listagem-pt-BR-completa.md` reescrito para o escopo D16 |
| #8641 | oimpresso.com | mergeado | Venda rápida nos textos; tabela "Conferir no build" |

API da tela 20 medida em produção em 2026-10-05 11:21Z, sem token: `GET /api/app/produtos/opcoes` 401,
`POST /api/app/produtos` 401, rota inexistente de controle 404.

## Onde estão as coisas
- Screenshots: `store-assets/screenshots/{android-1080x1920,iphone69-1320x2868}/` no repo do app. Para refazer:
  `npm run build:demo` e depois `node store-assets/screenshots/capturar.mjs www store-assets/screenshots --sem-faixa`.
- Textos: `docs/lojas-app/textos/listagem-pt-BR-completa.md`, com contagem medida por campo e tabela do que cada função
  exige no build.

## Pendente
- **Só [W]:** senhas das contas demo no Vaultwarden; pacote "Demo lojas" para o `gestor.demo`; login real; envio às
  lojas; tipo de conta do Play Console.
- **D9 em `docs/lojas-app/DECISOES.md`** ainda cita "as 7 áreas (D13)". Não mexido (registro de decisão); a sessão
  "Coordenar app das lojas" leva ao [W].
- Refazer screenshots e a tabela "Conferir no build" quando entrarem as telas que faltam da D16.
- CI do app recusado por cobrança desde 2026-10-02 18:51Z; verificação local segue substituindo.

## Estado MCP no momento do fechamento
- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas para @wr23.
