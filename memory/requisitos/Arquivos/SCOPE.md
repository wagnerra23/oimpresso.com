---
module: Arquivos
purpose: "Backbone de arquivos do ERP — toda entidade que anexa binário adota o trait HasArquivos e ganha storage multi-tenant, dedupe md5, signed URL e soft-delete. O anexo aparece na tela do módulo dono; o acervo inteiro da empresa tem tela própria em /arquivos (Arquivos/Index, 4 vistas: Acervo, Retenção, Cofre, Trilha). Carrega junto o aparato LGPD do acervo (exportação Art. 18, retenção, aviso ao titular, audit log de acesso, cofre criptografado com re-encrypt por rotação de chave) e o CuradorEngine de classificação."
migracao_ui: "n/a — 0 Blade; a única tela (Arquivos/Index) nasceu Inertia em 2026-08-24"
contains:
  - "ArquivosAdminController"
  - "DataController"
  - "DownloadController"
  - "InstallController"
  - "RetencaoSimulacaoController"
not_contains:
  - "Memoria RAG semântico Jana → Modules/Jana"
  - "OCR/transcrição/antivirus → fora de MVP"
trust_required: L2
owner: wagner
permission_prefix: arquivos.*
charter_adr: 0123
related_adrs:
  - 0123-modules-arquivos-backbone
  - 0093-multi-tenant-isolation-tier-0
url_prefixes:
  - /arquivos/*
drift_alerts: []
---

# Modules/Arquivos — DMS backbone

> ADR mãe: [0123](../../decisions/0123-modules-arquivos-backbone.md)
> SPEC: [memory/requisitos/Arquivos/SPEC.md](../../requisitos/Arquivos/SPEC.md)
> Princípio: **todo arquivo anexado deve cair lá**

## Superfície

- **Trait `HasArquivos`** (`Modules/Arquivos/Concerns/HasArquivos.php`) — o módulo dono
  anexa pelo trait e mostra o anexo na própria tela.
- **Tela `/arquivos`** (`resources/js/Pages/Arquivos/Index.tsx`, charter ao lado) — o acervo
  da empresa em 4 vistas: Acervo, Retenção, Cofre, Trilha. Ações na tela: classificar e excluir
  (`arquivos.access`), restaurar e simular a retenção (só superadmin). A tela nunca apaga de
  vez: o purge segue só no comando `arquivos:retention-cleanup`.
- **Download** por signed URL (`arquivos.download`).
- **Comandos** `arquivos:*` registrados no `ArquivosServiceProvider`. Agendados no
  `app/Console/Kernel.php`: `arquivos:health-check` e `arquivos:avisar-titulares`.
- **Storage** em `arquivos.disk_default`, que hoje cai em `local`. O disk `local-ct100` nunca
  foi criado; a troca para `arquivos-minio` ([ADR 0214](../../decisions/0214-arquivos-storage-s3-minio-ct100.md))
  está pendente da decisão B4 do [W].

Quem consome o trait é derivado, não listado aqui:

```bash
git grep -l "use HasArquivos" -- '*.php' | grep -v "^Modules/Arquivos/"
```

## Não-goals

❌ NÃO guarda anotação nem segredo: anotação é texto, não binário, e segredo vai no Vaultwarden
❌ NÃO replica a memória da Jana (RAG semântico ≠ DMS)
❌ NÃO indexa full-text
❌ NÃO faz OCR/transcrição
❌ NÃO faz antivirus scan
❌ NÃO substitui storage existente automaticamente — opt-in via trait

> **Histórico (removido em 2026-11-16).** Até esta data o arquivo carregava as seções
> "Status Sprint 1", "US-PRE pendentes", "Riscos transversais" e "Sprint 3+ (consumers
> planejados)", escritas em 2026-05-10, e um `not_contains` apontando para o `Modules/SRS`,
> removido em 2026-07-29 ([ADR 0357](../../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md)).
> O estado de cada US mora no [SPEC](SPEC.md); o texto antigo está no `git log` deste arquivo.
