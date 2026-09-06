# Estado durável do Design Sync

Este diretório guarda estado auditável do protocolo, nunca conteúdo de preview:

- `active-bundle.json`: manifesto completo do bundle promovido;
- `application-report.json`: arquivos modificados, telas, módulos, destinos e ações;
- `applications.json`: ledger v2 de recibos ligados aos hashes de fonte e alvo;
- `smokes/<slug-da-tela>.png`: screenshot durável do último smoke de cada tela (um por tela,
  sobrescrito; o hash no recibo é o que prova qual foto sustenta o estado — ADR 0390).

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
--record-smoke SOURCE --target TARGET --route /rota --deploy-sha SHA --screenshot FILE --tenant 1 [--host producao|staging-ct100|ci]
```

`--host` (ADR 0390) diz ONDE o smoke foi renderizado; default `producao`. O tenant é 1 em qualquer
host e `biz=4` segue recusado. Para `ci`, o caminho é automático: `design-smoke-ci.yml` fotografa
as telas `tested|validated` no app efêmero do CI e publica na órfã `governance/design-smokes`;
`node scripts/design-sync/smoke-consumir.mjs` (`--dry` só imprime) casa cada foto com o `.tsx`
atual por blob git e grava o recibo via `--record-smoke … --host ci`.

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
