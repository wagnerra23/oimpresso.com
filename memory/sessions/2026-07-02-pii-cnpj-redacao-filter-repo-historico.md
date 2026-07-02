---
date: "2026-07-02"
topic: "Redação de 3 CNPJs reais do histórico git inteiro via git filter-repo --replace-text + force-push autorizado Wagner — ADRs 0121 (ROTA LIVRE), 0194 (Tork), 0201 (Magalu teste); allowlist externa do pii-scan criada pra fakes de 0205/0207 (PR #3598)"
authors: [C]
type: incidente-pii-remediacao
autorizacao: "Wagner 2026-07-02 no chat: 'sim aprovo' (mecanismo A filter-repo) + 'merge'"
adrs_citados: [0121, 0194, 0201, 0205, 0207, 0257, 0297, 0093]
---

# Redação de CNPJs reais do histórico git (filter-repo) — 2026-07-02

## TL;DR

O PII scan (required) tinha 3 achados **dormentes** em ADRs canon de `main` (repo público): CNPJ real da cliente ROTA LIVRE em **0121** (linha 35), CNPJ real do prospect Tork em **0194** (linha 68) e CNPJ da Magazine Luiza usado como "CNPJ teste" em **0201** (linha 50) — violação Tier 0 "PII real NUNCA em PR/commit/log". Dormentes porque o scan é full-file só em arquivo tocado pelo diff. Com aprovação explícita do Wagner (mecanismo A, mesmo do incidente BRL 2026-06-08), os 3 números foram substituídos por `[REDACTED-CNPJ]` **em todo o histórico** (4.582 commits) via `git filter-repo --replace-text` + force-push com janela de branch protection.

## O que foi feito

1. **PR #3598** (mergeado antes do rewrite): allowlist **externa** no `pii-scan.sh` (`.github/pii-scan-allowlist.txt`, formato `path|literal`, só PII sintética) — porque o marker inline `pii-allowlist` exigiria editar corpo de ADR (append-only). Entradas pro CNPJ fake sintético dos contract tests (o `11.222.333...` clássico — literal completo catalogado na própria allowlist, 0205/0207) + migração frontmatter 0205/0207 `accepted/active→aceito/ativo` (corpo byte-idêntico, exceção ADR 0297).
2. **History rewrite**: clone bare fresco de `main` → `git filter-repo --replace-text` (3 CNPJs → `[REDACTED-CNPJ]`) → verificação `git log --all -S` = 0 ocorrências pros 3 números em TODOS os commits → janela: DELETE branch protection → `push --force-with-lease` → PUT protection restaurada (23 required checks + enforce_admins, verificado por GET).
   - 1ª tentativa rejeitada: main avançou durante a operação (sessão paralela, `adadc36c6→c11697d90`) — refeito com clone novo + lease amarrado no SHA; proteção restaurada intacta entre as tentativas.
   - Tip de main: `c11697d905` (velho) → `f57be9c992` (reescrito).
3. **Este PR**: frontmatter do 0121 `aceita→aceito` + `decided_at` quoted + `related` bare→slugs (corpo byte-idêntico — exceção 0297) + este session log.

## Efeitos colaterais / pendências

- **PRs abertos (5 no momento do rewrite)** referenciam a história antiga — precisam de rebase sobre o novo `main` antes de merge (merge sem rebase reintroduziria commits antigos com PII via merge-commit).
- **Checkouts locais** (D:/oimpresso.com e worktrees) ficaram com main antigo — `git fetch` + reset/rebase por sessão. O worktree `sells-caixa-link` tem `main` checked-out na história antiga.
- **Cache do GitHub**: commits antigos podem permanecer acessíveis por SHA direto até GC do GitHub; se necessário, abrir ticket no GitHub Support pedindo purge (precedente BRL aceitou o residual).
- Réplicas/espelhos (MCP server sync via webhook) re-sincronizam no próximo push.

## Regra reafirmada

PII real (CPF/CNPJ de cliente/prospect) NUNCA entra em arquivo versionado — nem "de exemplo". Fake sintético em ADR/handoff (append-only) vai pra `.github/pii-scan-allowlist.txt`; em qualquer outro arquivo, marker inline `pii-allowlist`. Real detectado = redação na fonte + avaliar rewrite de histórico (repo é público).
