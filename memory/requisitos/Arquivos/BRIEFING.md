---
id: requisitos-arquivos-briefing
module: Arquivos
status: shared-infra
status_nota: "DMS backbone consumido pelo trait `HasArquivos` (Cms, Financeiro, Fisio, NfeBrasil, OficinaAuto, Repair, Whatsapp) — mesma classificação do Repair, o outro backbone compartilhado. Tela própria desde 2026-08-24 (`/arquivos`) com as 4 vistas do charter: Acervo, Retenção, Cofre e Trilha."
updated_at: "2026-11-16"
owner: W
---

# BRIEFING — Modules/Arquivos

> **Estado consolidado** — 1 página executiva. Atualizado por PR mergeado (skill `brief-update` Tier B). Última revisão: 2026-11-16 (reconciliação com o código, auditoria de 2026-11-05, lote L3).

## Missão

Sistema canônico de arquivos do oimpresso — **cofre criptografado** + **pipeline curador 5-fase** (DISCOVER → CLASSIFY → REPORT → REVIEW → APPLY) + **deduplicação por MD5** + **audit log append-only** + **retenção e aviso ao titular (LGPD)**. Substitui storage ad-hoc espalhado em outros módulos por entidade única `Arquivo` referenciada via trait `HasArquivos`.

## Cliente piloto

**biz=1 Wagner** (uso interno) — cofre para a curadoria pessoal D:\Conhecimento (manuais Delphi WR Comercial, ADRs históricas, transcripts atendimento). Pré-piloto antes de expor pra ROTA LIVRE biz=4.

## Diferenciais vs mercado

| Capacidade | oimpresso | Bling/Tiny/Omie | Dropbox/GDrive |
|---|---|---|---|
| Cofre criptografado com troca de chave | ✅ `VaultEncryptionService` (AES-256-CBC, `APP_KEY`) + `arquivos:reencrypt-vault` | ❌ | 🟡 (encryption-at-rest, sem rotation) |
| Pipeline curador heurística-first | ✅ `CuradorEngine` 5-fase | ❌ | ❌ |
| Dedupe por MD5 dentro da empresa | ✅ `ArquivosService` + tabela `arquivos_dedupe` + `arquivos:dedupe-stats` | ❌ | 🟡 (block-level, opaco) |
| Audit log append-only LGPD | ✅ `arquivos_audit_log` + `arquivos:audit-log` + vista Trilha | ❌ | 🟡 (event log enterprise only) |
| Multi-tenant Tier 0 (`business_id` global scope) | ✅ Pest `MultiTenantTest.php` + `ArquivosTenantIsolationTest.php` | n/a | ❌ |
| Retenção LGPD configurável | ✅ `arquivos:retention-cleanup` + simulação na tela (dry-run) | ❌ | 🟡 (manual) |
| Aviso ao titular antes do vencimento | ✅ `arquivos:avisar-titulares` (e-mail e WhatsApp) | ❌ | ❌ |
| Backfill legado idempotente | ✅ migrations `backfill_nfe_xml`, `backfill_consumers` | n/a | n/a |

## Arquitetura

- **Entity:** `Modules/Arquivos/Entities/Arquivo.php` — Eloquent com `business_id` global scope + soft delete + hash MD5 (coluna `md5`) + metadata JSON
- **Services:**
  - `ArquivosService` — attach/classify/signedUrl/softDelete/restore, dedupe por MD5
  - `VaultEncryptionService` — `Crypt::encryptString` sobre o disk `vault` (AES-256-CBC, chave única `APP_KEY`; não há chave por empresa)
  - `Curador/CuradorEngine` — port PHP das regras do curador JS (`scripts/curador/`)
  - `ArquivosRetentionService`, `AvisoTitularService` + `AvisoTitularCanais`, `AnexosDoAppService`, leitores de stats (`CofreStatsReader`, `RetencaoStatsReader`, `Curador/CuradorStatsReader`)
- **Controllers:** `ArquivosAdminController` (tela `/arquivos`), `RetencaoSimulacaoController`, `DataController` (sidebar + permissões), `DownloadController` (signed URL), `InstallController`
- **Trait reuse:** `Concerns/HasArquivos` — `$model->attachArquivo($file)`, `$model->arquivos`. Quem usa é derivado: `git grep -l "use HasArquivos" -- '*.php'`
- **API do app:** anexos da OS em `GET/POST /api/app/os/{id}/arquivos` (`app/Http/Controllers/Api/App/OsArquivosController.php`, via contrato `AnexosDoApp`)
- **Comandos artisan** (`ArquivosServiceProvider`): audit-log, avisar-titulares, dedupe-stats, export-zip, health-check, recalcular-metadata, reencrypt-vault, retention-cleanup, revogar-permissoes-superadmin
- **Agendados** (`app/Console/Kernel.php`, só em `live`): `arquivos:health-check --alert` 06:30 e `arquivos:avisar-titulares --todos` 10:00 (BRT). Nenhum outro comando do módulo roda sozinho — o `retention-cleanup` é manual.

## Aviso ao titular (LGPD Art. 18)

Decidido na [ADR 0421](../../decisions/0421-arquivos-aviso-ao-titular-registro-e-janela.md) (aceita) e estendido para e-mail e WhatsApp na [ADR 0422](../../decisions/0422-arquivos-aviso-titular-canal-email-whatsapp.md) (status `proposto`).

- **Quem recebe:** arquivo `sensitive` com titular identificado, nos 30 dias antes do vencimento. Vencido fica fora — o aviso é prévio.
- **Como sai:** `AvisarTitularJob`, só pelos canais que o negócio ligou e respeitando o opt-in do contato. Grava `titular_avisado_at` e uma linha `notice` na trilha só depois de algum canal ter saído.
- **Liga/desliga:** por negócio, em `business.common_settings['arquivos_aviso_titular']`. Default: os dois canais desligados. Hoje só pela linha de comando (`arquivos:avisar-titulares <biz> --email=on`); não há tela.
- **O aviso não apaga nada.** Apagar segue com o `arquivos:retention-cleanup`, manual.

## Frontend

**Tem tela desde 2026-08-24** — `resources/js/Pages/Arquivos/Index.tsx`, rota `GET /arquivos` (`can:arquivos.access`, permission que nasce `false`). _(Até 2026-08-24 o BRIEFING dizia "backend-only"; em 2026-08-25 dizia "2 das 4 vistas". As duas frases eram verdade na data.)_

| Vista | O que mostra |
|---|---|
| **Acervo** | o que está guardado, de quem é, e o prazo com a base legal ao lado |
| **Retenção** | o que vence, o que está no grace, o que passou do prazo |
| **Cofre** | saúde do armazenamento: espaço por disco, arquivo acima do cap de 50 MB do cofre, órfão sem dono, MD5 repetido |
| **Trilha** | `arquivos_audit_log` read-only — quem tocou em quê |

Ações: classificar e excluir (soft-delete, `arquivos.access`); restaurar e simular a retenção em dry-run (só superadmin). A tela nunca apaga de vez e não faz upload — o upload é da tela do módulo dono. O contrato vive no trio ao lado do `.tsx` (`Index.charter.md` + `Index.casos.md`); o passo a passo no [`RUNBOOK-index.md`](RUNBOOK-index.md). Charters de backend em `CHARTERS-vault-curador.md`.

## Métricas de saúde

- `php artisan arquivos:health-check` — orphan_files, dedupe_inconsistent, audit_log_lag, retention_overdue, vault_encryption_ratio (agendado 06:30)
- `php artisan arquivos:dedupe-stats` — taxa de dedupe
- Pest `MultiTenantTest.php` / `ArquivosTenantIsolationTest.php` — isolamento entre empresas (ADR 0093 Tier 0)

## Gaps conhecidos (backlog ADR feature-wish — ADR 0105)

- Chave do cofre por empresa (hoje é a `APP_KEY` única)
- Tela para ligar os canais do aviso ao titular
- Integração Curador com Jana IA (HyDE embeddings pra classificação semântica)
- OCR PDF scanned (Tesseract sidecar no CT 100)
- Versioning de arquivos (snapshot pre-overwrite)

## ADRs relacionadas

- [0123](../../decisions/0123-modules-arquivos-backbone.md) Modules/Arquivos backbone (ADR mãe)
- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [0124](../../decisions/0124-curador-conhecimento-pipeline.md) Curador — pipeline de conhecimento
- [0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) Tiering memória (LOCAL vs canon vs segredo)
- [0360](../../decisions/0360-deprecacao-admin-center-supersede-0122.md) Admin Center deprecado — a tela nasce no próprio módulo
- [0421](../../decisions/0421-arquivos-aviso-ao-titular-registro-e-janela.md) e [0422](../../decisions/0422-arquivos-aviso-titular-canal-email-whatsapp.md) Aviso ao titular

## Próximos passos sinal-qualificado

1. Wagner usar curador 30d em D:\Conhecimento → métricas reais
2. Se ROTA LIVRE precisar anexar docs em vendas → expor via trait em Sells
3. Ratificar ou ajustar a ADR 0422 (canais do aviso)

---

**Quem manteve:** Wave M agent 2026-05-16 · [CC] 2026-08-25 (seção Frontend) · [C] 2026-11-16 (reconciliação com o código: consumidores reais, MD5, `arquivos:reencrypt-vault`, link da ADR 0124, aviso ao titular). Próxima revisão: após próximo PR mergeado que toque `Modules/Arquivos/` ou `resources/js/Pages/Arquivos/`.
