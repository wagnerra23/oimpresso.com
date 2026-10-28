---
id: requisitos-backup-spec
module: Backup
status: ativo
version: "0.1.0"
owner: wagner
last_updated: "2026-10-27"
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

**Implementado em:** _pendente_ — fatia 1 (o pacote: `app/Backup/ExportacaoEmpresa/` gera o zip com um CSV por tabela, atrás de `BACKUP_EXPORTACAO_EMPRESA`; teste 98 × 99 em `tests/Feature/Backup/ExportacaoEmpresaTest.php`); falta o job, a permissão, a tela, o link que expira e o registro do download

Origem: levantamento de lacunas 2026-10-25 (P2 Plataforma — backup e exportação de dados por empresa).
- O administrador da empresa pede a exportação; um job (com `business_id` no construtor) gera um zip com um CSV por tabela de negócio, só com as linhas da empresa.
- O arquivo fica disponível para baixar por tempo limitado e só para usuários da empresa com a permissão nova `backup.exportar_empresa`; o link expira.
- Nunca entra dado de outra empresa nem tabela global de plataforma; teste 98 × 99 abre o zip e confere.
- O pedido e o download ficam no registro de atividades.
- Decisão [W] 2026-10-27: entram as tabelas com `business_id` e as filhas pelo pai; ficam fora credenciais (certificado NF-e, chaves de gateway e boleto, NFS-e, WhatsApp, Jana), senhas, tabelas `mcp_*`/`_bkp_*`/`_tmp_*`/`docs_*`/`licenca_*`, caches e logs de webhook. Zip sem cifra; a proteção é a permissão e o link que expira. A lista vive em `TabelasDaEmpresa`.
