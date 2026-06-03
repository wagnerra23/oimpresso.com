---
date: "2026-06-03"
time: "15:00"
type: handoff
estado_mcp: cycles-active
---

# Handoff — Tela Fila (Oficina/OS) do protótipo Cowork → staging + main · deploy prod Hostinger quebrado

## Estado MCP

- **Cycle:** CYCLE-08 "Receita — Onda A" (2026-05-31→06-28, 25d restantes). Relacionado: `US-OFICINA-026` p0 (outreach Martinho — a Fila é da `OficinaAuto/ServiceOrders` que serve a Martinho biz=164).
- **my-work:** `US-INFRA-011` (rotacionar senha MySQL Hostinger) em REVIEW — **possivelmente relacionado** ao incidente de deploy abaixo.
- Esta sessão NÃO tinha US trackada — foi implementação de handoff de design (Claude Design bundle) + manutenção de CI.

## O que aconteceu

1. **Tela "Fila" (Oficina)** — handoff Claude Design (`oimpresso.com.html` Cowork, Parte 4 do prompt OFICINA-DARK-STAGE). Descobri que 3 das 4 partes do handoff já estavam feitas/divergiam (dark theme já existe via `useTheme`; `--stage-*` diverge do repo Tailwind; board já portado). A peça nova real = **view Fila master-detail** na `ServiceOrders/Index`.
2. Construí `ServiceOrderFila.tsx` (lista agrupada Urgentes/Demais · detalhe inline read-only com pipeline+timeline ao vivo · rail "Apps vinculados") + toggle Lista↔Fila (`?view=` querystring). Fiel ao charter: sem edição inline, sem WhatsApp (US-OFICINA-006), sem cor crua/sessionStorage. Reusa `ServiceOrderSheet/StagePipeline/Timeline/StatusBadge`.
3. F2 screenshot aprovado por [W] (preview standalone light+dark). Mergeado **PR #2160 → main** (squash `8337605c8`) — CI 100% verde (build/Pest/visual-regression/UI-judge/lint). Também deployado no **CT 100 staging** (`staging.oimpresso.com`, branch feat/staging-ct100, build verde in-container).
4. **PR #1961** (phpstan baseline shrink Gov+Brief+Jana, stale 187 commits) — via CI-monitor: corrigi schema (frontmatter ProjectMgmt/SPEC + session log TL;DR), resolvi conflito do `phpstan-baseline.neon` (merge da main mantendo baseline da main — ratchet só barra regressão), CI verde, **mergeado** (squash `6c51106c2`) com aprovação [W].

## Artefatos gerados

- `resources/js/Pages/OficinaAuto/ServiceOrders/_components/ServiceOrderFila.tsx` (~393 linhas, novo)
- `resources/js/Pages/OficinaAuto/ServiceOrders/Index.tsx` (+61, toggle Lista↔Fila)
- PRs mergeados: **#2160** (Fila) e **#1961** (phpstan/schema)

## Persistência

- **git main:** ambos PRs mergeados (squash `8337605c8` + `6c51106c2`). Webhook→MCP propaga.
- **CT 100 staging:** Fila live (feat/staging-ct100 deployado via `docker/oimpresso-staging/deploy.sh`).
- **Produção Hostinger:** ❌ NÃO atualizada (ver próximos passos).

## Próximos passos pra retomar

1. 🔴 **INCIDENTE deploy prod:** workflow `Quick Sync` (auto push→main) **falha no step "Setup SSH"** (`ssh-keyscan` exit 1) — quebrado pra TODA a equipe (≥3 commits parados: financeiro, paymentgateway, Fila). Endpoint SSH Hostinger ESTÁ no ar (ssh-keyscan local OK) → causa provável = **IP do runner GitHub bloqueado no firewall Hostinger** OU secret `SSH_*` corrompido. Corrigir (allowlist runner / rotacionar secret — cruza com `US-INFRA-011`) e re-rodar Quick Sync → sobe os 3 commits.
2. Fila só chega em `oimpresso.com` quando (1) resolver.

## Lições catalogadas

- **L:** handoff de design pode ter premissas STALE vs repo (dark/tokens/board já feitos) — sempre Passo 0 verificar contra o `main` real antes de implementar (3 de 4 partes não-aplicáveis).
- **L:** `phpstan-baseline.neon` é **env-sensitive** (larastan Windows ≠ CI/Linux em resolução de tipo) — regenerar local quebra CI; resolver conflito mantendo o baseline da main (ratchet tolera over-list) é CI-safe.
- **L:** push em branch feature stale (187 commits atrás) NÃO re-dispara gates `pull_request` — precisa `gh pr update-branch`.

## Pointers detalhados

- PR Fila: github.com/wagnerra23/oimpresso.com/pull/2160 · PR phpstan: /pull/1961
- Deploy prod: `.github/workflows/quick-sync.yml` (auto) + `deploy.yml` (manual) · staging: `docker/oimpresso-staging/deploy.sh`
- Charter: `resources/js/Pages/OficinaAuto/ServiceOrders/Index.charter.md`
