# Sessão — protótipo mínimo por dono

## Pedido

[W] autorizou toda a limpeza e pediu que `prototipo-ui/` tivesse apenas Cowork e Design System;
Cowork deveria separar Wagner e Felipe, com handoffs próprios. Também pediu validação das
máquinas, arquivos paralelos, documentação e ADRs, seguida de PR e merge.

## Entrega

- topologia mínima implantada;
- cópias mortas e snapshots paralelos removidos;
- máquinas, governança, testes e documentação encaminhados aos donos corretos;
- importadores e mapas tornados owner-aware;
- shell ligado diretamente ao DS canônico;
- recriação automática de `_ds/` desativada;
- ADR 0397 aceita por autorização explícita de [W];
- testes de estrutura, âncora, contrato, transporte e frescor executados.

## Observação operacional

Os diretórios locais não relacionados `.claude/_salvage-*`, `.worktrees/` e
`scripts/dual-brain/` já existiam fora do escopo e não foram alterados por esta limpeza.
