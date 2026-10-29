---
page: /documentacao/casos-por-tela
component: resources/js/Pages/Documentacao/CasosPorTela.tsx
related_runbook: memory/requisitos/Documentacao/RUNBOOK-casos-por-tela.md
owner: wagner
status: draft
last_validated: "2026-10-28"
parent_module: Documentacao
related_prototype: n/a (fonte visual = layout aprovado por [W] e Maiara no artefato gerado por scripts/casos-backlog/template.html; herda PT-01 Lista)
related_us: [US-DOC-001]
alcance:
  rota: /documentacao/casos-por-tela
  rota_nome: documentacao.casos-por-tela
  permission: n/a (permissão de plataforma criada por migration, sem DataController — documentacao.casos_tela.ver, checada no CasosPorTelaController)
  menu_hook: n/a (a entrada é o botão "Casos de uso por tela" na capa /documentacao, visível só pra quem pode abrir)
  pacote: n/a (documentação da plataforma, não módulo vendido por pacote)
tier: B
charter_version: 1
---

# Page Charter — Documentacao/CasosPorTela

> Os pedidos de clientes por tela (itens `[BACKLOG]` com `Pedido: P-NNN`), as decisões [W] e o
> contrato de cada tela (casos de uso com id e veredito de teste), **lidos dos `*.casos.md` a cada
> acesso**. Substitui o artefato "Casos de uso por tela", que só mudava quando alguém rodava
> `npm run casos:pagina` e republicava. Casos: [CasosPorTela.casos.md](CasosPorTela.casos.md).

## Mission

A Maiara e o Felipe triam os pedidos dos clientes e conferem cada um contra as regras, e o [W]
responde as decisões, numa página que mostra sempre o que está no git (ADR 0256).

## Goals — Features (faz)

- Topo com os números (casos, clientes, valor/estoque, dependem do Wagner, sem regra) e o ciclo
  Pedido → Conferido → Virou caso de uso → Testado
- Cinco visões: Casos (por área e tela, busca, filtros que somam) · O que as telas garantem (UC por
  tela com veredito do manifesto de testes) · Regras de Fluxos · Decisões para o Wagner · Clientes
- Marcar "Conferido contra as regras" num pedido (`documentacao.casos_tela.conferir`)
- Responder uma decisão D-NN (`documentacao.casos_tela.decidir`; o [W] por `administrator_usernames`)
- Os números são os mesmos de `npm run casos:pagina -- --resumo` (teste de paridade dos leitores)
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

- ❌ **Não edita nem cria pedido** — um pedido entra no `casos.md` da tela, por PR
- ❌ **Não copia caso para o `.tsx` nem para markdown** — id, título, cliente ou número literal no TSX é defeito
- ❌ **Não é dado de empresa** — documentação da PLATAFORMA (ADR 0093): sem `business_id`, e o dono
  `Admin#{biz}` de uma empresa-cliente **não** abre a página (permissão checada sem o `Gate::before`)
- ❌ **Não escreve conferência nem resposta no casos.md** — moram em `doc_casos_marcas`

## Automation Anti-hooks

- ❌ Nenhum agente marca "Conferido" nem responde decisão em nome de alguém — a marca grava o usuário logado

## UX Targets

- Cabe em 1280px sem scroll horizontal; a coluna de áreas some abaixo de 768px e vira um select
- A lista chega depois do primeiro render (`Inertia::defer`), com esqueleto enquanto isso

## Refs

- Padrão de Tela: PT-01 Lista · Constituição UI v2: UI-0013
- Leitor: `app/Services/Documentacao/CasosPorTela.php` ⇔ `scripts/casos-backlog/leitor.mjs` (paridade em `paridade-php.test.mjs`)
- RUNBOOK: [RUNBOOK-casos-por-tela.md](../../../../memory/requisitos/Documentacao/RUNBOOK-casos-por-tela.md)
