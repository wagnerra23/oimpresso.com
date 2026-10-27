---
id: requisitos-backup-spec
module: Backup
status: ativo
version: "0.1.0"
owner: wagner
last_updated: "2026-10-26"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
---

# SPEC — Backup e exportação de dados

> `/backup` é o dump do banco inteiro, só para superadmin (decisão [W] de 2026-08-19, ver
> [DECISOES-ONDA-0.md](DECISOES-ONDA-0.md)). Este SPEC registra o que é **novo**: a exportação
> feita pela própria empresa.

## User Stories

### US-BKP-001 · A empresa exporta os próprios dados num arquivo

> owner: — · priority: p2 · estimate: 16h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — planejada (lacunas 2026-10-25); o único backup existente é o dump do banco inteiro em `/backup`, só superadmin

Origem: levantamento de lacunas 2026-10-25 (P2 Plataforma — backup e exportação de dados por empresa).
- O administrador da empresa pede a exportação; um job (com `business_id` no construtor) gera um zip com um CSV por tabela de negócio, só com as linhas da empresa.
- O arquivo fica disponível para baixar por tempo limitado e só para usuários da empresa com a permissão nova `backup.exportar_empresa`; o link expira.
- Nunca entra dado de outra empresa nem tabela global de plataforma; teste 98 × 99 abre o zip e confere.
- O pedido e o download ficam no registro de atividades.
- Antes do código: [W] decide a lista de tabelas da primeira versão e se o arquivo vai cifrado.
