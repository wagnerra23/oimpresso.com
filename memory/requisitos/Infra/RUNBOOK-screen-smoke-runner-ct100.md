---
title: "Provisionar o runner self-hosted CT 100 do screen-smoke pós-deploy"
module: Governance
owner: W
status: rascunho
last_validated: "2026-07-08"
preconditions:
  - "Acesso SSH Tailscale root@ct100-mcp (RUNBOOK-acesso-ct100.md)"
  - "Admin no repo GitHub wagnerra23/oimpresso.com (registrar runner + secret + variable)"
  - "Workflow screen-smoke-after-merge.yml em main (PRs #3949 + #3952 mergeados)"
  - "ADR 0062 lida — o runner é o CT 100, NUNCA o Hostinger"
steps:
  - "1. Registrar o runner self-hosted [self-hosted,ct100,browser-mcp] no CT 100"
  - "2. Wire do ambiente do runner: claude CLI + browser MCP headless + bw (Vaultwarden)"
  - "3. gh secret set ANTHROPIC_API_KEY"
  - "4. Provisionar Vaultwarden screen-smoke/wagner-prod-readonly como conta biz=99 FAKE"
  - "5. POR ÚLTIMO: gh variable set SCREEN_SMOKE_ENABLED --body true (o switch deliberado)"
  - "6. Smoke de fumaça: workflow_dispatch manual + validar review PR + mcp_alertas"
related_adrs: [0164-screen-review-pdca-tela-smoke-pos-merge, 0062-separacao-runtime-hostinger-ct100]
---

# RUNBOOK — Provisionar o runner self-hosted CT 100 do screen-smoke pós-deploy

> **Status:** rascunho — **NÃO validado** (pendente Wagner executar). Criado 2026-07-08.
> **Pré-leitura:** [ADR 0164](../../decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) · [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) · [`RUNBOOK-acesso-ct100.md`](RUNBOOK-acesso-ct100.md) · [`RUNBOOK-ct100-chrome-headless-browsershot.md`](RUNBOOK-ct100-chrome-headless-browsershot.md)
> **Ativa o workflow:** [`.github/workflows/screen-smoke-after-merge.yml`](../../../.github/workflows/screen-smoke-after-merge.yml)

---

## 1. Contexto

O workflow `screen-smoke-after-merge.yml` já está em `main` mas **dormente**: o job `smoke` só roda quando a variável de repo `SCREEN_SMOKE_ENABLED=true` **e** o secret `ANTHROPIC_API_KEY` existem, e ele roda em `runs-on: [self-hosted, ct100, browser-mcp]` — um runner que **ainda não existe**. Este RUNBOOK provisiona esse runner e liga o switch, **na ordem certa** (ligar o switch antes do runner online = job enfileira/pendura ~24h — CRÍTICO 1 da revisão adversarial).

**Por que no CT 100 e não em runner GitHub / Hostinger:** o browser MCP roda **isolado no CT 100** (ADR 0164 §3 + ADR 0062 IRREVOGÁVEL). O runner efêmero do GitHub não tem browser conectado a prod; o Hostinger é shared hosting (proibido daemon/browser). O CT 100 já tem Chromium headless (ver `RUNBOOK-ct100-chrome-headless-browsershot.md`).

### Estado antes → depois

| Aspecto | Antes (hoje) | Depois deste RUNBOOK |
|---|---|---|
| Job `smoke` no deploy de tela | `detect` emite `::warning:: DESLIGADO` | roda no CT 100, gera screenshots + review.md |
| `SCREEN_SMOKE_ENABLED` (var repo) | ausente/≠true | `true` (ligado por último) |
| Runner `[self-hosted,ct100,browser-mcp]` | não existe | online, service persistente |
| Loop Act (ADR 0164) | inexistente | job `persist` abre PR `screen-smoke/review-<run>` |

---

## 2. Ordem de provisionamento (NÃO pular a ordem)

> ⚠️ **O switch (passo 5) é SEMPRE o último.** Setar o secret ou registrar o runner isolado não liga nada; ligar o switch com o runner offline pendura jobs. Esta é a razão de existir o switch (fix do CRÍTICO 1).

### Passo 1 — Registrar o runner self-hosted no CT 100

Pegue o registration token (admin — expira em ~1h):

```bash
# Na sua máquina (gh autenticado como admin do repo):
gh api -X POST repos/wagnerra23/oimpresso.com/actions/runners/registration-token --jq .token
```

No CT 100 (host, NÃO dentro do container do app — separa concerns; o runner precisa de Docker/Chrome, não do Laravel):

```bash
tailscale ssh root@ct100-mcp
# (primeiro acesso da sessão pode pedir auth URL — você aprova)

# Usuário dedicado não-root pro runner (boa prática GitHub):
useradd -m -s /bin/bash ghrunner || true
su - ghrunner

mkdir -p ~/actions-runner && cd ~/actions-runner
# Baixe a versão atual do runner (confira a última em github.com/actions/runner/releases):
curl -o actions-runner-linux-x64.tar.gz -L \
  https://github.com/actions/runner/releases/download/v2.XXX.X/actions-runner-linux-x64-2.XXX.X.tar.gz
tar xzf actions-runner-linux-x64.tar.gz

# Configurar com os 3 labels EXATOS que o workflow espera:
./config.sh \
  --url https://github.com/wagnerra23/oimpresso.com \
  --token <REGISTRATION_TOKEN_DO_PASSO_ACIMA> \
  --name ct100-screen-smoke \
  --labels self-hosted,ct100,browser-mcp \
  --work _work \
  --unattended
```

Instalar como **service** (sobrevive reboot) — precisa de root:

```bash
exit   # volta pro root
cd /home/ghrunner/actions-runner
./svc.sh install ghrunner
./svc.sh start
./svc.sh status     # deve dizer "active (running)"
```

Confirme que aparece **online** em `github.com/wagnerra23/oimpresso.com/settings/actions/runners` (ou `gh api repos/wagnerra23/oimpresso.com/actions/runners --jq '.runners[].status'`).

### Passo 2 — Wire do ambiente do runner (o único ponto que exige mão)

O job `smoke` roda `claude -p "..." --allowedTools "mcp__claude-in-chrome__*,Read,Write,Edit"`. O ambiente do usuário `ghrunner` precisa de **3 coisas**:

**(a) `claude` CLI** no PATH do `ghrunner`:
```bash
su - ghrunner
# instalar o Claude Code CLI (método oficial atual) e confirmar:
command -v claude && claude --version
```

**(b) `bw` (Vaultwarden/Bitwarden CLI)** pra o login ler o item read-only (passo 4):
```bash
command -v bw || npm i -g @bitwarden/cli
# configurar server self-hosted + sessão (ver Vaultwarden do projeto):
bw config server https://vault.oimpresso.com
# a sessão/unlock do bw é parte do passo 4
```

**(c) 🟠 Browser MCP headless — DECISÃO TÉCNICA a resolver aqui (honesto):**

A skill referencia as tools `mcp__claude-in-chrome__*`. **`claude-in-chrome` é uma MCP de extensão/desktop** — pressupõe um Chrome com a extensão Claude e sessão de desktop. Num runner **headless** isso não sobe trivialmente. Duas saídas:

- **Opção A (recomendada) — Chrome headless + Xvfb + a MCP claude-in-chrome:** o CT 100 já tem Chromium (`RUNBOOK-ct100-chrome-headless-browsershot.md`). Subir `Xvfb :99` + Chrome com a extensão sob esse display virtual, e apontar a MCP pra ele. Mais fiel à skill (não muda tool names), porém mais setup.
- **Opção B — trocar por uma MCP de browser headless (Playwright/Puppeteer) via `--mcp-config`:** subir um MCP server headless e passar `claude -p ... --mcp-config <arquivo>`. **Custo:** as tools mudam de nome (`mcp__playwright__*`) → precisa ajustar o `--allowedTools` do workflow **e** a skill `tela-smoke-pos-merge` (os passos citam `mcp__claude-in-chrome__*`). Menos setup de SO, mais edição de canon.

> **Recomendação:** tentar **A** primeiro (mantém skill/workflow intactos). Se o claude-in-chrome não estabilizar headless em ~1h de tentativa, cair pra **B** e abrir um PR ajustando o `--allowedTools` + a skill (1 linha cada). **Este é o único item deste RUNBOOK sem receita fechada** — os demais são mecânicos. Registrar o que funcionou aqui ao validar.

### Passo 3 — Secret `ANTHROPIC_API_KEY`

```bash
gh secret set ANTHROPIC_API_KEY --repo wagnerra23/oimpresso.com
# cole a key quando pedir (ou use auth de assinatura no runner, se preferir)
```
> Sozinho isto **não liga** o smoke (o switch do passo 5 é o gate). Ordem proposital.

### Passo 4 — Vaultwarden `screen-smoke/wagner-prod-readonly` = conta biz=99 FAKE

> 🔴 **Tier 0 (residual #8 da revisão adversarial).** A conta **tem que ser biz=99 fake read-only**, NUNCA a sua (biz=1) nem o cliente (biz=4). Senão os screenshots capturam dados reais + **valores R$** — o auto-mask cobre CPF/CNPJ/email/telefone, **não** razão social nem BRL — e o artifact (30d, baixável por quem tem Actions) vira vetor de vazamento BRL Tier 0 (regra 2026-06-08).

1. Criar/confirmar um usuário do ERP **em biz=99** com perfil **read-only** (sem permissão de escrita; só visualização das telas).
2. Guardar credenciais no Vaultwarden como item `screen-smoke/wagner-prod-readonly`.
3. Deixar o `bw` do `ghrunner` capaz de ler esse item (sessão/unlock non-interativo — via `BW_SESSION` exportado no ambiente do service, ou API key de service account). **Não** colar senha no workflow.

### Passo 5 — POR ÚLTIMO: ligar o switch

Só depois de 1–4 OK e o runner **online**:

```bash
gh variable set SCREEN_SMOKE_ENABLED --body true --repo wagnerra23/oimpresso.com
```

---

## 3. Smoke de fumaça (validar o fluxo end-to-end)

1. Disparo manual (não precisa esperar um deploy real):
   ```bash
   gh workflow run screen-smoke-after-merge.yml -f rota=Sells/Create --repo wagnerra23/oimpresso.com
   ```
2. Acompanhar: `gh run watch` (ou a aba Actions). Esperado:
   - `detect` → `should_smoke=true`, `infra_ready=true`.
   - `smoke` roda no CT 100, gera screenshots 1440+1280 + review.md, notifica `mcp_alertas`.
   - `persist` abre um PR `screen-smoke/review-<run>` com o `review.md`.
3. Validar:
   - O PR de review existe e tem o `review.md` round 1 (status `pending-wagner`).
   - O artifact `screen-smoke-<run_id>` tem os PNGs **sem PII/BRL visível** (conferir o mask + o escopo biz=99).
   - `my-inbox` / `mcp_alertas` tem o alerta `screen-review-pending`.
4. Fazer o **Act**: editar o round no `review.md` do PR com `decisão: approved` e mergear. Confirmar que um próximo smoke da mesma tela apenda **round 2** (herança append-only).

---

## 4. Desligar / rollback (sem deploy de código)

- **Pausar o smoke:** `gh variable set SCREEN_SMOKE_ENABLED --body false` (ou deletar a variable). O job volta a ficar dormente; `detect` emite o `::warning::`. Nenhum job pendura.
- **Tirar o runner:** `./svc.sh stop && ./svc.sh uninstall` no CT 100 + remover em Settings→Actions→Runners. **Desligue o switch ANTES** de remover o runner (senão o próximo deploy de tela pendura esperando runner).

---

## 5. Pegadinhas catalogadas

- ⛔ **Ligar o switch com o runner offline** → job `smoke` fica *queued* amarelo até ~24h (`timeout-minutes` só conta execução). Ordem: runner online → … → switch por último.
- ⛔ **Runner dentro do container do app** (`oimpresso-mcp`) → mistura Laravel com CI + Chrome. Use host/usuário dedicado `ghrunner`.
- ⛔ **Credencial biz=1/biz=4 no item Vaultwarden** → vazamento BRL/PII Tier 0 (passo 4).
- ⛔ **Rodar isto no Hostinger** → viola ADR 0062. O runner é o CT 100.
- 🟠 **Browser MCP headless** (passo 2c) é o único ponto sem receita fechada — resolver A vs B na execução e **anotar aqui** o que funcionou (atualizar `last_validated` + `status: ativo`).

---

## 6. Cross-refs

- [ADR 0164](../../decisions/0164-screen-review-pdca-tela-smoke-pos-merge.md) §9 — pendências de ativação (este RUNBOOK cumpre 9.1–9.4)
- [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100
- [`.github/workflows/screen-smoke-after-merge.yml`](../../../.github/workflows/screen-smoke-after-merge.yml) — o workflow (header tem a ordem de ativação)
- [skill `tela-smoke-pos-merge`](../../../.claude/skills/tela-smoke-pos-merge/SKILL.md) — os 8 passos que o agente executa
- [`RUNBOOK-ct100-chrome-headless-browsershot.md`](RUNBOOK-ct100-chrome-headless-browsershot.md) — base do Chromium headless no CT 100
