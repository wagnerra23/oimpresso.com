---
date: "2026-09-16"
topic: "Árvore completa do último bundle, poda transacional e espaço independente para Felipe"
authors: [C]
prs: []
outcomes:
  - "Importador gera manifesto tree autenticado e remove sobras somente na conta de destino"
  - "Wagner e Felipe podem ter bytes iguais em namespaces independentes"
  - "ZIP 20 aplicado: 695 fontes reais, zero sobras/ausências/divergências"
  - "24 arquivos de Felipe preservados pelo fingerprint antes/depois"
related_adrs:
  - 0404-ultimo-importado-e-autoridade-do-espelho
  - 0405-espelhos-cowork-independentes-por-conta
---

# Paridade do bundle por conta

## TL;DR

Pedido de [W]: corrigir sobras do import e preservar o espaço de Felipe. Implementação local, sem publicação/merge. O ZIP 20 resultou em 695 arquivos importáveis de Wagner exatamente conformes ao manifesto, preservando os 24 arquivos de Felipe.

## Processo e correção

O gerador anterior cobria só o grafo do shell. Podar toda a conta com esse manifesto apagaria playbooks legítimos. A recepção passou a incluir a árvore importável completa (`--full-tree`), reutilizando a classificação existente do importador e ignorando `sync/`, ruído e `.gitignore` local.

`mirrorScope: tree` participa do bundleId. O consumidor só poda sobras com esse escopo autenticado; depois de tree ativo recusa delta parcial. A poda acontece em staging e o gate de fonte única valida antes da promoção. Estado e espelho são trocados atomicamente; falha restaura a versão anterior.

A primeira aplicação real encontrou 23 colisões com Felipe. Foi revertida apenas nos paths desta sessão, após checks de escopo, contagem e hashes dos arquivos novos; a primeira tentativa de rollback amplo foi barrada pelo sandbox, e a versão restrita passou. Nenhuma mudança do Felipe foi feita. [W] então autorizou os dois espaços, registrados na ADR 0405. A R4 ficou por dono, sem permitir duplicata interna nem sombra do DS.

## Evidência real

- ZIP 20: 819 entradas, CRC32 conferido; pacote original conforme.
- Manifesto regenerado `6270479598680fc21409b6d6703b7dc62b0b41bf356108e2055ba2d572aba006`: 702 entradas, sendo 695 fontes e 7 caches DS reconciliados com o dono canônico.
- Reconciliação retirou 75 sobras e incluiu 95 arquivos antes ausentes; o índice de recepção voltou aos bytes da fonte. Conteúdo retirado ficou recuperável pelo Git.
- Wagner: `expected=695, actual=695, extra=0, missing=0, mismatch=0`.
- Felipe: 24 arquivos; fingerprint antes e depois `9ed48e5e396f44c12a514f6c94092a254448687afdd050d18592c9e6d57686d7`.

## Verificação e limites

Testes de transação, aplicador e guard passaram. Cobriram inclusão/alteração/remoção, snapshot inicial, delta regenerado, rollback, escopo autenticado, bloqueio de parcial após tree, duplicatas internas/DS e estados independentes das contas. Integridade estrutural passou; sem PHP/PHPStan local. Alteração preexistente de `.claude/launch.json` preservada. MCP do projeto indisponível.

Felipe tem opção explícita `--owner Felipe` no consumidor v2, com state próprio; nenhum export novo de Felipe foi fornecido. Não se afirmou paridade da conta com uma origem não auditada. Restam publicação/CI/merge das alterações locais.

## Publicação autorizada

Após pedido de merge, PR #7445 publicado. A primeira execução do lint visual confundiu 15 cópias de charters no espelho com Pages de produção. O scanner foi limitado pelo classificador canônico `screenSourceFromCharter`, com testes positivos para Pages raiz/módulo e negativos para cópias nas duas contas. A fonte importada não foi alterada para satisfazer o lint.
