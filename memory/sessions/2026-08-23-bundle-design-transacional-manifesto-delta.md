---
date: "2026-08-23"
hour: "12:21 BRT"
duration: "2h"
topic: "Bundle Design transacional com manifesto, delta e inventário de aplicação"
authors: [W, C]
outcomes:
  - "Bundle v2 passou a validar sequência, base, SHA-256, chunks e estado-alvo antes da promoção"
  - "Snapshot inicial e delta incremental passaram a compartilhar manifesto durável"
  - "Superadmin e Officeimpresso ganharam inventário 1:N com evidência aplicada/testada ligada a hashes"
prs: [6150]
us: []
related_adrs:
  - "0379-bundle-design-transacao-manifesto-delta-staging"
  - "0374-cowork-mirror-pull-direto-sem-transcricao"
  - "0336-gates-design-promocao-por-mordida-provada-emenda-0314"
---

# Sessão — bundle Design transacional

## TL;DR

O transporte anterior conseguia aplicar payloads, mas não sabia provar qual era a base, se todas
as partes pertenciam ao mesmo lote nem se a mudança inteira podia ser promovida. Também confundia
fonte recebida no espelho com modificação efetivamente aplicada no React.

Foi criado o bundle v2: manifesto completo do estado-alvo, snapshot/delta, chunks SHA-256,
staging dos quatro destinos e promoção por swaps com rollback. O relatório durável lista os
arquivos modificados, cada fonte, Page React e módulo. Evidências de aplicação/teste ficam ligadas
aos hashes atuais e são invalidadas quando fonte ou alvo muda.

## Entregas

- contrato v2 e JSON Schema para manifesto, partes, relatório e ledger;
- produtor incremental: snapshot inicial, `--previous` para delta, parte 01 de controle e chunks
  de arquivo maior que o teto;
- consumidor fail-closed: sequência 1..N, bundle-base, digests, bytes, SHA-256 e grafo completo;
- transação em staging para Cowork, design-docs, runtime `_ds` e estado, com rollback provado;
- estado em `scripts/design-sync/state/`, fora do cache `_ds`;
- `status.mjs` com lista legível de alterações, módulos, destinos e ações;
- registro `--mark-applied` com evidência e testes, invalidado automaticamente por hash;
- detector reutilizável como módulo, preservando mapeamentos 1:N de Superadmin/Officeimpresso;
- documentação operacional, protocolo/config, CI e ADR 0379 proposta.

## Validação

- gerador: snapshot, delta exato, cap, arquivo grande, missing, glob e part01 control-only;
- transação: promoção, roteamento, módulos 1:N, órfão fail-closed, delta sem bytes unchanged/deleted,
  path traversal, corrupção, dry-run, base divergente, rollback e evidência stale;
- E2E real de CLI: gerador v2 → applier dry → promoção → ausência da part01 sem write;
- detector e teste anti-drift verdes;
- `protocolo.config --selftest` verde;
- `gate-selftest` 76/76;
- testes de workflows, inventário de máquinas e índice de ADR verdes;
- `selftest-registry-check`: zero arquivo `.test.mjs` órfão; permanece vermelho apenas pela dívida
  preexistente `.claude/hooks/block-sonda-que-mente.mjs --selftest`, fora deste diff.

## Estado

Implementação publicada na PR #6150 pela branch `codex/design-bundle-transaction`, em worktree
isolado e atualizado com `origin/main`. Nenhum bundle real foi recebido nesta sessão, portanto os
JSONs ativos ainda não foram gerados. MCP de memória não estava disponível; foi usado o canon
versionado do repositório.
