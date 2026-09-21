---
date: "2026-09-22"
hour: "10:15 BRT"
topic: "Auditoria dos buracos atuais entre protótipo e produção"
authors: [C]
outcomes:
  - "Inventário separou prova estrutural, smoke de CI e validação real de produção"
  - "Falso verde de Page sem rota passou a falhar como NÃO MEDIDO"
  - "Páginas Inertia de Modules passaram a disparar o smoke pós-merge"
prs: [7648]
related_adrs:
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0410-ratificacao-zero-baseline-no-funil-design
---

# Buracos atuais entre protótipo e produção

## TL;DR

O funil agora diz a verdade sobre seu alcance, mas ainda não prova produção: são 164 pares,
3 com smoke apenas em CI, 0 validados em produção e 133 anteriores ao smoke. A PR #7648
também fecha um falso verde concreto: arquivo de Page sem rota cadastrada podia disparar um
smoke que visitava somente as rotas críticas e terminava verde sem abrir a tela alterada.

## Buracos medidos

- **Estado do funil:** 63 `compared`, 70 `anchored`, 28 `to-create`, 3 `smoked-ci`,
  0 `smoked-staging` e 0 `validated`. Portanto, 133 pares ainda não chegaram ao smoke e
  nenhum tem a cadeia exigida de produção.
- **Paridade visual:** `compared` prova mapa/hash consistente; não prova comparação renderizada
  protótipo × aplicação. Essa comparação continua dependente de execução local/manual.
- **Mapas frágeis:** 67 mapas cobrem 67 de 223 charters; 580 partes estão mapeadas, mas apenas
  84 usam âncora estável `data-contract`. As outras 496 dependem de linha, e há 66 TODOs de
  âncora pendentes.
- **Recibo de teste:** o comando registrado é texto livre e não está ligado mecanicamente à
  tela. As três telas Fiscal usam o mesmo job como recibo; ele prova a lane, não o comportamento
  individual de cada tela.
- **Produção:** o catálogo do smoke contém 12 rotas, 11 autenticadas e 3 críticas. O run real
  35720880986 falhou porque o login permaneceu em `/login`; os verdes recentes eram no-op sem
  Page elegível alterada.
- **Sem produtor de estado:** o workflow pós-merge grava review/artifact, mas não promove o
  ledger por tela. Não há produtor encontrado para `smoked-staging` ou `validated`.
- **Identidade de tenant:** o ledger exige tenant 1 e a credencial de smoke observada usa
  business 99; falta uma ponte explícita para o recibo ser atribuível ao mesmo alvo.
- **Impacto indireto:** cinco arquivos do bundle ativo afetam transporte/estilo/playbook sem
  atribuição a um estado de tela, então a cadeia por tela não demonstra seu alcance.

## Correção desta rodada

- `select-routes.mjs` separa rotas selecionadas de fontes sem correspondência.
- Uma Page/componente alterado sem `source` exato em `routes.json` gera resumo `NÃO MEDIDO` e
  falha antes de abrir o navegador; as rotas críticas deixam de servir como prova substituta.
- O detector do workflow inclui `Modules/*/Resources/js/Pages` e a variante minúscula.
- O self-test cobre rota comum, rota de módulo, modo manual, modo navegação e mistura com fonte
  desconhecida.

## Verificação

- `select-routes.test.mjs`: passou;
- `node --check scripts/screen-smoke/smoke.mjs`: passou;
- `design-memory-gate.test.mjs`: passou;
- `gate-selftest.mjs`: 82/82;
- `memory-health.mjs`: 0 falhas, 10 avisos preexistentes;
- `git diff --check`: passou.

PHP/Pest/PHPStan não rodaram localmente, conforme ADR 0062. A mudança executável desta rodada
é Node/workflow e será novamente verificada pelo CI da PR.
