---
id: requisitos-backup-briefing
module: Backup
status: parcial
status_nota: "backend das Ondas 1-2 no main; a tela ainda renderiza Blade — F3 pendente"
updated_at: "2026-08-20"
owner: W
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0062-separacao-runtime-hostinger-ct100
---

# BRIEFING — `Backup`

> **Função única:** resumo executivo e índice. Aponta para os donos; não recopia o que eles dizem.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

A tela `/backup` do UltimatePOS legado: listar, gerar, baixar e excluir os dumps do banco, sem
abrir terminal. **Não** é um `Modules/<X>` — vive no núcleo (`app/Http/Controllers/BackUpController.php`),
e por isso não tem `SCOPE.md`/`SUPERFICIE.md`/`topicos/`.

## Estado atual

Migração Blade → Inertia em ondas. O que já está no `main` é backend; a tela em si segue Blade.

| Onda | Entrega | Estado |
|---|---|---|
| 0 | decisões de [W] + o que a leitura do `main` mediu | mergeada |
| 1 | segurança do legado: travessia de caminho, `catch` morto, exclusão do último backup, `store()` ausente | mergeada |
| 2 | `backup:run` sai da requisição — job na fila `backups` + worker próprio no `Kernel` | PR aberto |
| 3 | render Inertia atrás de flag + trio da tela | F1 feita (o RUNBOOK); F3 pendente |
| 4-5 | contrato de tela no CI · decommission do Blade | não iniciadas |

Estado por PR e verdade do momento: `gh pr list --search "backup in:title"`.

## Portas canônicas

- **Receita da tela:** [`RUNBOOK-index.md`](RUNBOOK-index.md) — fluxo, estados, rotas, smoke, diagnóstico
- **Decisões + medições:** [`DECISOES-ONDA-0.md`](DECISOES-ONDA-0.md)
- **Código:** `app/Http/Controllers/BackUpController.php` · `app/Jobs/RunBackupJob.php` ·
  `app/Console/Kernel.php` (worker) · `config/backup.php` · `app/Backup/Cleanup/KeepLatestBackups.php`
- **Legado a remover na Onda 5:** `resources/views/backup/index.blade.php`
- **Testes:** `tests/Feature/Backup/` — rodam no CT 100, nunca local

## Decisões e riscos que exigem atenção

- **O zip é dump do banco INTEIRO**, todos os tenants. Por isso a permissão foi restrita a
  superadmin — decisão [W] registrada em [`DECISOES-ONDA-0.md`](DECISOES-ONDA-0.md), Tier 0
  ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **O disco de backup é o `local`, com raiz em `public_path('uploads')`** — a mesma pasta de
  logos, imagens e documentos de todos os tenants. É o que torna validação de nome de arquivo
  uma questão de isolamento, não de higiene.
- **Sem worker, não há backup.** A fila é `backups`, com cron próprio; a `default` não serve
  (quem a drena está atrás de um gate desligado por padrão). Detalhe no RUNBOOK §3.

## Próxima ação verificável

- **F3 da Onda 3** — gerar `resources/js/Pages/Backup/Index.tsx` + charter + casos.
  Evidência de conclusão: o trio existe e o `casos-gate` passa para a tela.
  ⚠️ Bloqueio atual: o protótipo existe no Cowork mas não desceu para o espelho — o payload
  (~3,5 MB) excede o teto de 256 KiB do transporte. Diagnóstico e guarda em `aplicar-payload.mjs`.

## Regra de manutenção

1. Mudou requisito de tela: altere o charter/casos ao lado da Page, não este arquivo.
2. Mudou o fluxo operacional: altere [`RUNBOOK-index.md`](RUNBOOK-index.md).
3. Nova decisão de [W]: acrescente em [`DECISOES-ONDA-0.md`](DECISOES-ONDA-0.md) com a data.
4. Este arquivo só muda quando o **estado das ondas** muda — não recopie o que os donos acima dizem.
