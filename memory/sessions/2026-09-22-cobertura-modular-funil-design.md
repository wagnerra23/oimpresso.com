---
date: "2026-09-22"
hour: "10:51 BRT"
topic: "Cobertura do funil de design em todos os módulos nWidart"
authors: [C]
outcomes:
  - "Suporte estrutural a Modules foi separado de cobertura real por módulo"
  - "Persistência de review modular passou a usar a lista exata copiada do artefato"
  - "Primeiro review não rastreado deixou de ser ignorado por git diff --quiet"
prs: [7648]
related_adrs:
  - 0410-ratificacao-zero-baseline-no-funil-design
---

# Cobertura modular do funil de design

## TL;DR

O funil reconhece a convenção `Modules/<Módulo>/Resources/js/Pages`, mas não funciona de ponta
a ponta para todos os módulos. Foram encontradas 80 Pages em sete módulos e apenas 37 charters;
o catálogo do smoke tem zero rota modular. A persistência ainda descartava reviews de módulos
e também podia ignorar o primeiro `review.md` não rastreado; ambos foram corrigidos na #7648.

## Medição

| módulo | Pages | charters |
|---|---:|---:|
| Cms | 4 | 4 |
| Forja | 31 | 13 |
| KB | 1 | 1 |
| Officeimpresso | 3 | 2 |
| PaymentGateway | 7 | 2 |
| Superadmin | 8 | 7 |
| Whatsapp | 26 | 8 |
| **total** | **80** | **37** |

O detector, o ledger, o bundle transaction, o gerador de diff e o consumidor de smoke têm
tratamento explícito para paths de módulo. O workflow pós-deploy passou a detectar esses paths
na rodada anterior. A cobertura operacional, porém, continua incompleta: nenhuma das 12 rotas
do `routes.json` aponta para uma Page de `Modules`.

## Defeito corrigido

O job `persist` copiava reviews de `Modules/...`, mas `git diff --quiet` e `git add` olhavam
somente `resources/js/Pages`. Além disso, `git diff --quiet` não enxerga arquivo novo não
rastreado, inclusive no core. O job agora acumula os caminhos exatos copiados, consulta
`git status --porcelain` e adiciona essa mesma lista.

Uma fixture Git real com `Modules/Forja/Resources/js/Pages/Forja/Index.review.md` comprovou que
o primeiro arquivo aparece no status e entra no índice.
