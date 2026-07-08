---
title: "Ativar o smoke visual pós-deploy (Playwright + OpenAI, runner GitHub)"
module: Governance
owner: W
status: rascunho
last_validated: "2026-07-08"
preconditions:
  - "Admin no repo GitHub wagnerra23/oimpresso.com (secrets + variable)"
  - "Workflow screen-smoke-after-merge.yml em main (mecanismo Playwright+OpenAI)"
  - "Conta biz=99 FAKE read-only no ERP (pra smoke de telas auth sem vazar PII/BRL)"
steps:
  - "1. Confirmar secret OPENAI_API_KEY (já existe — RAGAS)"
  - "2. (telas auth) gh secret set SMOKE_PROD_USER / SMOKE_PROD_PASS = conta biz=99 FAKE"
  - "3. POR ÚLTIMO: gh variable set SCREEN_SMOKE_ENABLED --body true"
  - "4. Smoke de fumaça via workflow_dispatch + validar review PR"
related_adrs: [0164-screen-review-pdca-tela-smoke-pos-merge, 0269-deploy-automatico-build-no-runner]
---

# RUNBOOK — Ativar o smoke visual pós-deploy (Playwright + OpenAI)

> **Status:** rascunho — pendente Wagner executar/validar. Criado 2026-07-08.
> **Substitui** o `RUNBOOK-screen-smoke-runner-ct100.md` (removido — a abordagem CT 100 + browser MCP foi trocada por Playwright + OpenAI num runner GitHub, sem infra nova; Wagner 2026-07-08 "use o openai").
> **Workflow:** [`.github/workflows/screen-smoke-after-merge.yml`](../../../.github/workflows/screen-smoke-after-merge.yml) · **motor:** [`scripts/screen-smoke/smoke.mjs`](../../../scripts/screen-smoke/smoke.mjs) · **rotas:** [`scripts/screen-smoke/routes.json`](../../../scripts/screen-smoke/routes.json)

---

## 1. O que roda (sem CT 100, sem secret novo além de login)

Num runner **ubuntu do GitHub**, após o `deploy.yml` concluir:
1. **Playwright headless** (já é dep do repo) navega prod, loga por form, screenshota **1440+1280**, coleta **console errors** + os **4 sinais de render** (título, sem erro JS, conteúdo não-vazio, shell montado).
2. **OpenAI vision** (`gpt-4o-mini`, o `OPENAI_API_KEY` que **já existe**) julga se a tela **renderizou ou está QUEBRADA**.
3. Escreve `<Tela>.review.md` (append round N) + smoke-log + screenshots no artifact.
4. Job `persist` abre um **PR `screen-smoke/review-<run>`** — o **merge pelo Wagner = decisão Act** (ADR 0164 §2.4).
5. Se qualquer tela vier **QUEBRADA**, o job fica **vermelho**.

---

## 2. Ativação (3 passos, o switch por último)

```bash
# 1. Confirmar que o OpenAI já está no repo (deve listar OPENAI_API_KEY):
gh secret list --repo wagnerra23/oimpresso.com | grep OPENAI_API_KEY

# 2. (SÓ pra telas auth) credenciais de uma conta biz=99 FAKE read-only — residual #8.
#    Sem isto, o smoke cobre só /login (público). NUNCA usar sua conta (biz=1) nem
#    cliente (biz=4): screenshots com dados reais/valores R$ vazariam no artifact E
#    na OpenAI (auto-mask não cobre razão social nem BRL — Tier 0 2026-06-08).
gh secret set SMOKE_PROD_USER --repo wagnerra23/oimpresso.com   # cole o usuário biz=99
gh secret set SMOKE_PROD_PASS --repo wagnerra23/oimpresso.com   # cole a senha

# 3. POR ÚLTIMO — o switch deliberado que liga o smoke:
gh variable set SCREEN_SMOKE_ENABLED --body true --repo wagnerra23/oimpresso.com

# (opcional) trocar o modelo de vision (default gpt-4o-mini):
# gh variable set SCREEN_SMOKE_MODEL --body gpt-4o --repo wagnerra23/oimpresso.com
```

---

## 3. Smoke de fumaça (validar end-to-end)

```bash
gh workflow run screen-smoke-after-merge.yml -f rota=Sells/Create --repo wagnerra23/oimpresso.com
gh run watch
```
Esperado: `detect` (should_smoke+infra_ready true) → `smoke` (screenshots + review.md, veredito no step summary) → `persist` abre o PR de review. Validar:
- O PR `screen-smoke/review-<run>` existe com o `review.md` round 1 (`pending-wagner`).
- O artifact `screen-smoke-<run_id>` tem os PNGs **sem PII/BRL** (conferir o escopo biz=99).
- **Act:** editar `decisão: approved` no round e mergear. Um próximo smoke da mesma tela apenda **round 2** (herança append-only).

---

## 4. Desligar / ajustar (sem deploy de código)

- **Pausar:** `gh variable set SCREEN_SMOKE_ENABLED --body false` (ou deletar). Volta a dormente; `detect` emite `::warning::`.
- **Ampliar cobertura:** editar [`scripts/screen-smoke/routes.json`](../../../scripts/screen-smoke/routes.json) (rota + `auth` + `source` do .tsx). É dado, não código.

---

## 5. Pegadinhas

- ⛔ **Credencial biz=1/biz=4** em SMOKE_PROD_USER/PASS → vazamento BRL/PII Tier 0 (artifact + OpenAI). Só **biz=99 fake**.
- 🟠 **tsx→rota não é 1:1** → o smoke cobre um conjunto CURADO (routes.json), priorizando rotas cujo `source` mudou. Expandir routes.json conforme telas críticas.
- 🟠 **#7 race** — o health gate confirma prod de pé, não que o `DEPLOY_SHA` já está LIVE (LSCache/OPcache). Pode fotografar o build anterior por alguns segundos.
- ⚠️ Ao validar de verdade, atualizar `last_validated` + `status: ativo` e registrar o que funcionou.

---

## 6. Cross-refs

- [ADR 0164](../../decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) — PDCA fase C (§3 browser-MCP-CT100 superseded por este mecanismo; emenda append-only é follow-up)
- [`.github/workflows/screen-smoke-after-merge.yml`](../../../.github/workflows/screen-smoke-after-merge.yml)
- [`scripts/screen-smoke/smoke.mjs`](../../../scripts/screen-smoke/smoke.mjs) · [`routes.json`](../../../scripts/screen-smoke/routes.json)
- [feedback-deploy-smoke-browser-obrigatorio §6](../../reference/feedback-deploy-smoke-browser-obrigatorio.md)
