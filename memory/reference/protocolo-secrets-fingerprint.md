---
name: Protocolo Secrets — SCP + SHA-256 fingerprint
description: Como inserir credenciais Tier 0 (Inter, certs, tokens) no DB Hostinger sem expor valores no chat Claude / logs / git.
type: reference
authority: canonical
status: live
created: 2026-05-15
related_adrs: [0030, 0093, 0101]
related_proposals: [secrets-handling-fingerprint-scp]
---

# Protocolo Secrets — SCP + SHA-256 fingerprint

> **Quando usar:** sempre que Wagner precisar cadastrar/atualizar credencial sensível (Inter PJ client_id/secret/cert, OPENAI_API_KEY, Inter webhook_secret, futuros bancos) num ambiente que tem Laravel + `Crypt::encryptString`. Substitui o caminho "Wagner abre tinker SSH manual e cola valores no terminal".

## Princípios duros

1. **Claude nunca vê o valor.** Apenas SHA-256[:12] fingerprint pra Wagner conferir visualmente com a fonte (Vaultwarden).
2. **Valor mora num arquivo local gitignored** durante o transporte. Nunca em commit, log, chat, env shell, argv visível em `ps`.
3. **Transporte criptografado.** SCP (SSH) move o JSON pro `/tmp` do alvo. Nunca HTTP/curl/email.
4. **Cleanup garantido.** Try/finally na linguagem orquestradora + try/finally na linguagem alvo. Sem cleanup, ferramenta não é aceitável.
5. **Idempotente.** Refuse sobrescrever credencial existente sem flag explícita de update.
6. **Tier 0 obrigatório.** `--business-id` é argumento required, valores validados contra allowlist.

## Pattern canônico (5 fases)

```
[1] WAGNER LOCAL             [2] WAGNER LOCAL          [3] CANAL SSH        [4] HOSTINGER          [5] AMBOS
preenche JSON gitignored  →  Python valida + SHA-256 → SCP JSON + PHP    → PHP boota Laravel    → cleanup /tmp + (opcional)
                            mostra fingerprints                            Crypt::encryptString    shred local
                                                                           INSERT DB
                                                                           DELETE /tmp/json
```

Detalhes em [`scripts/inter-credentials/README.md`](../../scripts/inter-credentials/README.md).

## Conformidade LGPD

Quando a credencial pertence a **titular** (cliente final pessoa física ou pessoa jurídica gerida por pessoa física), aplica-se LGPD:

- **Art. 6º** princípios — segurança (VII) + prevenção (VIII)
- **Art. 46** — agente de tratamento adota medidas técnicas. SCP + Crypt::encryptString cumpre o "técnicas adequadas".
- **Art. 48** — incidente de segurança comunica ANPD. Se credencial vazar (chat, git, log), é incidente reportável.

Exemplo no projeto:
- ✅ Credencial Inter biz=4 ROTA LIVRE → toca dado financeiro Larissa → **LGPD aplica**
- ⚪ Credencial Inter biz=1 Wagner WR2 → segredo corporativo Wagner → não-LGPD, mas mesmo protocolo

## Quando NÃO usar este protocolo

| Caso | Protocolo apropriado |
|---|---|
| Senha que Wagner usa em UI navegador (não vai pro DB) | Vaultwarden direto, sem Python |
| `.env` do projeto (DB_PASSWORD, APP_KEY) | SFTP do .env via [reference hostinger-mysql](hostinger-mysql-conexao.md) |
| Chave SSH (`id_ed25519_*`) | `~/.ssh/` direto, nunca commit |
| Token MCP server | Settings local do Claude Code (`~/.claude/settings.local.json`), [team-onboarding](../../.claude/skills/oimpresso-team-onboarding/SKILL.md) |
| Credencial transitória (1 uso, ex: token OAuth temporário) | Não persistir — usar em memória só |

## Implementação atual

- [`scripts/inter-credentials/install-biz.py`](../../scripts/inter-credentials/install-biz.py) — orquestrador genérico (atualmente specific Inter, mas pattern reusável)
- [`scripts/inter-credentials/_remote_install.php`](../../scripts/inter-credentials/_remote_install.php) — payload Laravel bootstrap + encrypt + insert
- [`scripts/inter-credentials/.gitignore`](../../scripts/inter-credentials/.gitignore) — bloqueia `.local.json`, `*.crt`, `*.key`, `*.pem`

Pra próximos bancos (Bradesco, Itaú, Santander) ou outros provedores que usam `BoletoCredential` ou modelo similar, o pattern é o mesmo — só muda o `_remote_install.php` (campos do `config_json`).

## Pra evoluir

1. **Generalizar Python** — argumento `--driver inter|bradesco|...` aponta pra `_remote_install_<driver>.php`
2. **HMAC do JSON antes do SCP** — Wagner gera HMAC local com chave compartilhada, PHP verifica antes de inserir (defesa contra MITM no canal SSH improvável mas zero-trust)
3. **Suporte rotação** — flag `--update` que verifica fingerprint diff vs DB atual e propõe update controlado
4. **Audit log** — toda operação grava em `secrets_audit_log` (sem valor, só `who+when+fingerprint[:12]+biz+banco`)

## Refs

- ADR 0030 — credenciais jamais em git (este protocolo é o **como** prático)
- ADR 0093 — multi-tenant Tier 0 (--business-id required)
- ADR 0101 — biz=4 nunca pra testes (sequence biz=1 smoke → canary → biz=4)
- [feedback nunca-publicar-credenciais.md](feedback-nunca-publicar-credenciais.md) — não publicar credenciais no chat
- RUNBOOK Inter PJ — `memory/requisitos/RecurringBilling/RUNBOOK-inter-pj.md`
