---
title: "RUNBOOK — Rotação de segredos do repo público (US-INFRA-042)"
module: Infra
owner: W
status: ativo
last_validated: "2026-06-21"
related_adrs:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0062-separacao-runtime-hostinger-ct100
---

# RUNBOOK — Rotação de segredos do repo público

> **Origem:** auditoria de saúde/integridade 2026-06-21 — risco #1 (segredos vivos num repo PÚBLICO sem rotação). US-INFRA-042 (p0, CYCLE-SAUDE).
> **Quem executa:** Wagner (W) — envolve credenciais/host; um agente NÃO executa esta receita.
> **Princípio duro:** editar o HEAD **NÃO resolve** — git é append-only, o que está no histórico está comprometido pra sempre. **Rotacionar é a única correção real**; tornar o repo privado é o que estanca a exposição contínua.

## Objetivo
Rotacionar todo segredo que vazou pro git público (`wagnerra23/oimpresso.com`), atualizar os consumidores, e fechar a porta (repo privado + pre-commit ativo) — de forma que o histórico exposto deixe de ser uma chave viva.

## Pré-condições
- Acesso admin ao host do Meilisearch (`meilisearch.oimpresso.com`), ao painel/API Hostinger, ao CT 100 e ao Vaultwarden (`vault.oimpresso.com`).
- Relatório do scan **gitleaks (PR #3148, full-history)** em mãos — é a fonte da lista COMPLETA de segredos vivos (não confiar só na memória).

## Passos

### 0. Inventário (antes de tudo)
- Pegue o artifact/relatório do **gitleaks #3148** → lista exata dos segredos no histórico.
- Cruze com `memory/reference/_INDEX-SECRETS.md` (os 12 do incidente 2026-05-15) e o doc do incidente.

### 1. Meilisearch master key (a mais crítica — controle admin total do search multi-tenant)
- Gere `MEILI_MASTER_KEY` nova no host do Meilisearch + **restart** do serviço.
- Atualize `MEILI_MASTER_KEY` no `.env` da app (Hostinger) **e** no CT 100, se usado lá.
- Re-emita as *search keys* derivadas (mudam quando a master muda).
- Valide: a app indexa e busca normalmente.

### 2. Token DNS Hostinger
- Revogue o token antigo no painel/API DNS Hostinger.
- Gere novo, atualize onde é usado (automação de DNS / deploy).

### 3. Os 12 segredos do incidente 2026-05-15
- Lista em `_INDEX-SECRETS.md`. Para cada um: rotacione **na origem** (cada serviço) e atualize o consumidor.

### 4. Repo público → decisão (estanca a exposição)
- **Recomendado:** GitHub → Settings → General → Change visibility → **Private**. Enquanto for público, qualquer um clona o histórico e lê tudo.
- Se mantiver público: **tudo** que passou pelo histórico tem que ser tratado como comprometido (rotação 100%).

## Pós-rotação (fecha o loop)
- Atualize o status **por item** no `_INDEX-SECRETS.md` (rotacionado em / por quem / data).
- Guarde as **novas** keys no **Vaultwarden** (`vault.oimpresso.com`) — nunca no git.
- Ative o pre-commit de segredos em cada máquina de dev: `git config core.hooksPath .githooks`.
- (Opcional, defesa em profundidade) promova o `secret-scan` (gitleaks) a required no CI após calibrar falso-positivo.

## Validação (DoD da US-INFRA-042)
- [ ] `MEILI_MASTER_KEY` + token DNS + os 12 rotacionados (cada um confirmado na origem).
- [ ] App e MCP funcionando com as novas keys (smoke de busca + deploy).
- [ ] `_INDEX-SECRETS.md` atualizado por item.
- [ ] Decisão de visibilidade do repo registrada (privado OU rotação-100% aceita).
- [ ] `core.hooksPath .githooks` ativo nas máquinas de dev.
- [ ] Marcar `tasks-update US-INFRA-042 status:done acceptance_ref:<commit do _INDEX-SECRETS atualizado>`.

## Notas
- Multi-tenant Tier 0: a Meilisearch master expõe TODOS os índices de TODOS os tenants — por isso é a #1.
- Hostinger ≠ CT 100 ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)): rotacione nas duas pontas onde a key é usada.
- Segredo nunca volta pro git ([ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md)) — canônico = Vaultwarden.
