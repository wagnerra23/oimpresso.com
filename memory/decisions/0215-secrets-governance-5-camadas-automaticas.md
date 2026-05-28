---
adr: 0215
title: Secrets governance — 5 camadas automáticas (auto-discovery + auto-validate + auto-alert + auto-PR + pre-commit gate)
status: accepted
date: 2026-05-28
deciders: [Wagner]
amends: []
references:
  - 0044-vaultwarden-self-hosted-cofre.md
  - 0045-hostinger-dns-api-endpoint-canonico.md
  - 0061-conhecimento-canonico-git-mcp-zero-automem.md
  - 0093-multi-tenant-isolation-tier-0.md
  - 0131-tiering-memoria-canonico-local-segredo.md
lifecycle: active
---

## Contexto

Wagner cobrou 2026-05-28 19:30 sessão incident WhatsApp mídia: *"isso não deveria ser automático essa estruturação? Como deveria funcionar? Como se aplica aqui?"*.

Contexto da cobrança: agente declarou Tier 0 gap falsamente sobre token Hostinger sem ter pesquisado memory canon (token estava em `memory/claude/reference_hostinger_hpanel.md:37` desde 2026-04-28, mas expirou). Wagner observou que toda a sequência (descobrir índice → validar → atualizar status → alertar Wagner) deveria ser automática, não reativa.

Histórico de fricções:
- **2026-05-28 17:55** — agente pediu Wagner criar A record no hPanel (skill `hostinger-dns-autonomy` criada)
- **2026-05-28 18:30** — agente declarou "Tier 0 gap token inacessível" sem ler memory canon (skill `memory-first-secret-search` criada + `_INDEX-SECRETS.md` criado)
- **2026-05-28 19:30** — agora: Wagner observa que toda essa estruturação deveria ser automática per design, não reativa

Antes desta ADR:
- `memory/_INDEX-SECRETS.md` é canônico mas **manual** — agente atualiza só quando lembra
- Validação de secret só acontece quando agente PRECISA usar (descobre 401 reativamente)
- Wagner não tem alerta proativo de expiração
- 20+ arquivos memory/ + .env têm secret patterns sem cross-check
- Drift entre fonte (git) e índice (canon) acumula silenciosamente

## Decisão

Sistema **5 camadas automáticas** de governance de secrets:

```
┌───────────────────────────────────────────────────────────────────┐
│  Camada 5: Auto-rotation (provider permite)                       │
│  Out-of-scope MVP (Hostinger NÃO suporta; AWS/Doppler futuro)     │
├───────────────────────────────────────────────────────────────────┤
│  Camada 4: Auto-alert (Wagner notificado ANTES de virar incident) │
│  Centrifugo channel `governance:secrets` + Brief Jana ingere log  │
├───────────────────────────────────────────────────────────────────┤
│  Camada 3: Auto-validate + auto-PR (cron daily 06h15 BRT)         │
│  `secrets:audit --auto-pr --notify` valida + commita drift        │
├───────────────────────────────────────────────────────────────────┤
│  Camada 2: Auto-validate (`secrets:audit` core)                   │
│  Curl/grep/ssh por entry tipo. Status active/expired/warning      │
├───────────────────────────────────────────────────────────────────┤
│  Camada 1: Auto-discovery (`secrets:scan`)                        │
│  Varre git canon procurando secret patterns sem entry no índice    │
└───────────────────────────────────────────────────────────────────┘

         ▲ Pre-commit hook + GH Action chamam camadas 1+2.
```

### Camada 1 — Auto-discovery (`php artisan secrets:scan`)

Comando que varre:
- `memory/` (todos `.md`)
- `config/*.php`
- `app/Console/Commands/*.php`
- `Modules/**/.env*` e `Modules/**/Config/*.php`

Procura padrões: `Bearer <token>`, `AKIA...`, `API_KEY=<value>`, `SECRET=<value>`, `PASSWORD=<value>`.

Cross-check com `memory/_INDEX-SECRETS.md`: se arquivo onde detectou padrão NÃO está referenciado em nenhuma `location` do índice, reporta **drift**.

Flags:
- `--diff-only` — apenas git diff staged (pre-commit hook)
- `--fail-on-drift` — exit 1 se drift detectado (CI gate)

### Camada 2 — Auto-validate (`php artisan secrets:audit`)

Parse markdown table de `_INDEX-SECRETS.md`, valida cada entry por tipo:

| Tipo | Validação |
|---|---|
| `hostinger_api` | `Http::withToken($t)->get(/api/dns/v1/zones/oimpresso.com)` retorna 200 |
| `ssh_key` | arquivo existe + chmod 600/400 |
| `hostinger_env` | SSH grep `^XXX=` `.env` retorna match |
| `ct100_env` | tailscale ssh + cat arquivo |
| `vaultwarden_item` | `bw get item <slug>` (se bw CLI configurado) |
| `minio_credentials` | `mc admin info` ou curl health |
| `docker_env` | docker inspect container env |

Atualiza coluna `Status` do índice in-place (rewrite markdown). Estados possíveis: ✅ active / 🟡 warning / 🔴 EXPIRED / 🔒 LOCKED / ⏸ pending.

### Camada 3 — Auto-PR + cron daily (`secrets:audit --auto-pr`)

Cron `app/Console/Kernel.php` daily 06h15 BRT (após brief 06h regenerar).

Se `secrets:audit` detecta drift:
1. Cria branch `chore/secrets-drift-YYYY-MM-DD-HHMMSS`
2. Commit "chore(secrets): drift detectado YYYY-MM-DD — <summary>"
3. Push + `gh pr create` automático
4. Wagner revisa + decide (rotacionar | aceitar | ignorar)

Cron secundário: `secrets:scan` weekly Mon 09h BRT — discovery semanal pra detectar secrets adicionados sem índice.

### Camada 4 — Auto-alert (Centrifugo + Jana brief)

`secrets:audit --notify` publica em canal Centrifugo `governance:secrets`:
```json
{
  "type": "secrets.drift_detected",
  "changes": [{"name": "...", "old": "...", "new": "..."}],
  "count": N,
  "detected_at": "ISO8601"
}
```

Brief Diário Jana (cron 06h BRT) ingere log estruturado `secrets.drift_detected` → seção "🔴 Atenção" do brief de manhã.

Wagner vê drift no Brief antes de virar incident reativo.

### Camada 5 — Pre-commit hook + GH Action (gate)

`.githooks/pre-commit` ganha bloco novo que chama `secrets:scan --diff-only --fail-on-drift`. Bloqueia commit que adicione secret sem entry no índice.

`.github/workflows/secrets-governance.yml`:
- `pull_request` paths memory/config/Modules → `secrets:scan --fail-on-drift`
- `schedule` cron 09h UTC (06h BRT) → `secrets:audit --auto-pr`
- `workflow_dispatch` manual trigger

## Não-goals

- ❌ Não substitui Vaultwarden — secrets reais continuam lá; índice é só ponteiro
- ❌ Não roda decryption de Vault items — agente não tem master pass user
- ❌ Não rotaciona secrets automaticamente Camada 5 nesta ADR (Hostinger não suporta; futuro AWS/Doppler/Vault dinâmico)
- ❌ Não escaneia arquivos binários, vendor, node_modules, storage
- ❌ Não tenta "guess" valores de secret faltantes — pendente sempre Wagner setup

## Plano implementação (este PR + follow-ups)

### Este PR (Sprint S-0215-1 — 5 camadas mínimas viáveis)

- US-SEC-001 Camada 1: `app/Console/Commands/SecretsScanCommand.php`
- US-SEC-002 Camada 2: `app/Console/Commands/SecretsAuditCommand.php`
- US-SEC-003 Camada 3: cron schedule em `app/Console/Kernel.php`
- US-SEC-004 Camada 4: Centrifugo publish em `SecretsAuditCommand::publishCentrifugoAlert()`
- US-SEC-005 Camada 5: `.githooks/pre-commit` block novo + `.github/workflows/secrets-governance.yml`
- US-SEC-006 Pest: `tests/Feature/SecretsAuditCommandTest.php` + `SecretsScanCommandTest.php`

### Follow-ups (PRs separados)

- US-SEC-007 Brief Jana ingere `secrets.drift_detected` log estruturado
- US-SEC-008 `bw` (Bitwarden CLI) instalado em CT 100 + service account `claude-agent` Vault user-level + `~/.bw-session` cache pra agente
- US-SEC-009 Skill `secret-vaultwarden` Tier B — wrapper bw CLI pra outras secrets futuras
- US-SEC-010 Validação tipos faltantes: meta_cloud, asaas, sicoob, mailgun (handlers no command)
- US-SEC-011 Métricas: tempo médio até drift detected, MTBR (mean time between rotations), secrets ativos por tipo

## Consequências

✅ **Boas:**
- Drift detectado proativamente — Wagner avisado ANTES de incident reativo
- Índice canon sempre fresh — auto-validação daily mantém status atual
- Pre-commit gate impede secrets sem catalogação subindo
- CI redundante: PR levanta também antes de merge
- Centrifugo alert + Brief Jana = Wagner vê tudo num lugar
- Discovery semanal cobre drift fonte → índice (alguém adicionou secret sem atualizar índice)
- ROI: 1× setup (~4h IA-pair) → economiza N incidents reativos × 1h cada

⚠️ **Tradeoffs:**
- `secrets:audit` precisa rodar com SSH key + tailscale acesso (CT 100 + Hostinger) → não funciona em CI free runner padrão. Mitigação: GH Action usa secret runner OR roda Camada 2 só em CT 100 via SSH self-hosted runner futuro.
- Pre-commit hook adiciona latência ~500ms ao `git commit` — aceitável.
- Falso positivos em pattern detection (test fixtures contendo "Bearer fake-token...") — mitigação: SCAN_PATHS conservador + EXCLUDE_PATHS pra `tests/`.
- Cron pode gerar PR ruidoso se secret válido detectado como expired por bug em handler — mitigação: status só muda na markdown se handler retorna != current; Wagner sempre revisa PR antes merge.
- Camada 5 GH Action precisa `GH_TOKEN` com permissão pra criar PR — usar `secrets.GITHUB_TOKEN` default workflow scope OK.

## Validação

- ✅ `secrets:scan` em CI verde no PR (sem novo drift)
- ✅ `secrets:audit --filter=hostinger` muda status do Hostinger token de ✅ → 🔴 EXPIRED (token validado, retorna 401)
- ✅ Pre-commit hook bloqueia commit de arquivo PHP com `BEARER_TEST_TOKEN=g8JeEn9GsgBlVh...` (token real value sem entry no índice)
- ✅ Cron agendado em `php artisan schedule:list` mostra `secrets:audit --auto-pr --notify` daily 06h15
- ✅ Centrifugo publish em smoke local com `--notify`
- ✅ Pest tests 6/6 verde

## Notas

- ADR 0215 amenda implicitamente fluxo `memory/_INDEX-SECRETS.md` (criado mesma sessão) — índice agora auto-mantido
- Skill `memory-first-secret-search` Tier A continua bloqueante — agente humano-controlado checks índice antes de busca; este sistema é defesa em profundidade pra quando agente esquecer
- Wagner cobrou "como deveria funcionar / aplica aqui" — resposta canônica nesta ADR
- Roadmap Camadas pós-MVP: rotation auto (Doppler/Vault), métricas avançadas, integração SOC2 audit log
