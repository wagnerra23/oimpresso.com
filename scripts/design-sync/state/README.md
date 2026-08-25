# Estado durável do Design Sync

Este diretório guarda estado auditável do protocolo, nunca conteúdo de preview:

- `active-bundle.json`: manifesto completo do bundle promovido;
- `application-report.json`: arquivos modificados, telas, módulos, destinos e ações;
- `applications.json`: evidências ligadas aos hashes de fonte e alvo, com testes executados.

Os três arquivos são gerados/atualizados pelos comandos em `scripts/design-sync/`. Depois de
uma sincronização real, devem entrar no mesmo PR das aplicações React correspondentes.

`_ds` não substitui este estado. `_ds` é cache derivado para o preview e pode ser reconstruído;
histórico, base do próximo delta e prova de aplicação ficam aqui. Uma mudança em qualquer hash
invalida automaticamente a evidência anterior e recoloca a tela como pendente.

Contrato JSON: [`../bundle.schema.json`](../bundle.schema.json).
