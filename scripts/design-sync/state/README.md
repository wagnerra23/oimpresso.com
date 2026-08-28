# Estado durável do Design Sync

Este diretório guarda estado auditável do protocolo, nunca conteúdo de preview:

- `active-bundle.json`: manifesto completo do bundle promovido;
- `application-report.json`: arquivos modificados, telas, módulos, destinos e ações;
- `applications.json`: ledger v2 de recibos ligados aos hashes de fonte e alvo.

Os três arquivos são gerados/atualizados pelos comandos em `scripts/design-sync/`. Depois de
uma sincronização real, devem entrar no mesmo PR das aplicações React correspondentes.

`_ds` não substitui este estado. `_ds` é cache derivado para o preview e pode ser reconstruído;
histórico, base do próximo delta e prova de aplicação ficam aqui. Uma mudança em qualquer hash
invalida automaticamente os recibos dependentes e recua a tela ao último estado ainda provado.

## Ciclo executável por tela

O `status.mjs` é a única porta de escrita e leitura do ciclo. Use o mesmo par
`SOURCE` + `TARGET` em todas as etapas:

```text
--mark-compared SOURCE --target TARGET --map MAP
--mark-applied SOURCE --target TARGET --evidence FILE
--run-test SOURCE --target TARGET --runner local|ct100|ci --command-json '["programa","argumento"]'
--record-smoke SOURCE --target TARGET --route /rota --deploy-sha SHA --screenshot FILE --tenant 1
```

O resultado progride por `anchored → compared → applied → tested → validated`. Os verbos são
literais: mapa válido prova comparação; arquivo versionado prova aplicação; somente comando
executado com saída zero prova teste; e somente smoke posterior, no tenant manual permitido,
com SHA de deploy e screenshot versionado prova validação. Texto livre e nome de teste não
viram recibo.

Para fechar apenas o escopo novo, sem transformar dívida legada em anistia ou bloqueio global:

```text
node scripts/design-sync/status.mjs --check-lifecycle --source SOURCE --minimum STATE
node scripts/design-sync/status.mjs --check-lifecycle --module MODULO --minimum STATE
```

As regras e a motivação arquitetural estão na ADR 0384. Este arquivo só documenta a interface
executável; `status.mjs` e o schema continuam donos do contrato de máquina.

Contrato JSON: [`../bundle.schema.json`](../bundle.schema.json).
