---
module: Arquivos
purpose: "DMS backbone — todo arquivo anexado do oimpresso deve cair aqui. Tabelas arquivos + arquivos_audit_log + arquivos_dedupe. Trait HasArquivos morphMany pra opt-in por entidade. Curador engine PHP + parity com JS scripts/curador. Signed URLs + soft-delete + dedupe sha256."
contains:
  - "DataController"
  - "DownloadController"
  - "InstallController"
not_contains:
  - "MemCofre senhas/segredos → Modules/SRS"
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

> ADR mãe: [0123](../../memory/decisions/0123-modules-arquivos-backbone.md)
> SPEC: [memory/requisitos/Arquivos/SPEC.md](../../memory/requisitos/Arquivos/SPEC.md)
> Princípio: **todo arquivo anexado deve cair lá**

## Status Sprint 1

| US | Status | Notas |
|---|---|---|
| US-ARQ-001 scaffold | ✅ Sprint 1 (este PR) | módulo nWidart 8 peças |
| US-ARQ-002 migrations | ✅ Sprint 1 (este PR) | arquivos + arquivos_audit_log + arquivos_dedupe |
| US-ARQ-003 trait HasArquivos | ✅ Sprint 1 (este PR) | morphMany + attachArquivo + arquivosClassificados |
| US-ARQ-004 ArquivosService | ✅ Sprint 1 (este PR) | attach/classify/signedUrl/softDelete/restore/dedupe |
| US-ARQ-005 CuradorEngine PHP | 🟡 parcial (este PR) | 6 regras críticas portadas; demais Sprint 1 dia 3 |
| US-ARQ-006 Storage disks config | ⏳ Sprint 1 dia 4 | precisa US-PRE-ARQ mount CT 100 |
| US-ARQ-007 ParityTest | 🟡 placeholder | precisa fixtures comuns Sprint 1 dia 3 |
| US-ARQ-008 Signed URL controller | ⏳ Sprint 1 dia 4 | route placeholder em web.php |
| US-ARQ-009 Pest multi-tenant | 🟡 placeholder | scaffold + 2 smoke; matriz completa Sprint 1 dia 5 |
| US-ARQ-010 backfill smoke | ⏳ Sprint 3 (NFe XML primeiro consumer) | depende US-PRE-ARQ |

## US-PRE pendentes (Wagner faz)

1. **Mount `/var/lib/oimpresso-arquivos`** no CT 100 (volume Docker bind)
2. **Mount `/var/lib/oimpresso-vault`** separado pra disk encrypted-at-rest
3. **Decisão TLS encryption-at-rest**: Agent C 2026-05-10 sinalizou que Laravel Filesystem **NÃO suporta nativamente**. Decidir entre:
   - `league/flysystem-encrypted` middleware (compatível com qualquer disk)
   - `Crypt::encrypt($contents)` antes de `Storage::put()` (mais explícito, mais código)
4. **Pinar commit JS de referência** pro ParityTest (sem isso, paridade fica circular)
5. **Gerar `tests/Fixtures/CuradorParity/*.jsonl`** com 100 paths sintéticos via `discover.mjs --output fixtures-jsonl` (Sprint 1 dia 3)

## Riscos transversais (Agent C 2026-05-10)

- Storage `local-ct100` mount + `.env` ARQUIVOS_DISK_DEFAULT precisam ser configurados em ambos Hostinger e CT 100 — testes integration passam local mas quebram em prod sem isso
- Encryption-at-rest do disk `vault` exige decisão tech (middleware vs explicit encrypt)
- Drift JS↔PHP timezone — Date.now() vs time(). Forçar UTC em ambos
- ParityTest sem fixtures = paridade circular

## Não-goals

❌ NÃO substitui MemCofre (anotações ≠ binary)
❌ NÃO replica Copiloto/Memoria (RAG semântico ≠ DMS)
❌ NÃO indexa full-text MVP (Meilisearch entra Sprint 4+)
❌ NÃO faz OCR/transcrição MVP
❌ NÃO antivirus scan MVP
❌ NÃO substitui storage existente automaticamente — opt-in via trait

## Sprint 3+ (consumers planejados)

Agent F 2026-05-10 mapeou 10 consumers em Modules/*. Ordem prioridade:
1. **NfeBrasil** — xml_path + danfe_path + certificados encrypted (Sprint 3 ✅ já planejado)
2. **Financeiro Boleto PDFs**
3. **Ponto Importação eSocial**
4. **Jana TaskAttachment** (consolida sha256 dedup)
5. **SRS DocSource**
