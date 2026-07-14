---
title: "RUNBOOK — Offboarding de integrante do time (revogação + rotação)"
module: Infra
owner: W
status: rascunho
last_validated: "2026-07-14"
preconditions:
  - "Acesso admin: GitHub org/repo, Tailscale tailnet, Hostinger hPanel/SSH, CT 100, Vaultwarden"
  - "Saber quais repos/segredos/servidores a pessoa acessou (cruzar com a matriz do PLANO §3.1)"
steps:
  - "GitHub — remover collaborator/membro + repassar code ownership"
  - "MCP token — revogar em /copiloto/admin/team"
  - "Identity Mesh — marcar mcp_actors.revoked_at"
  - "Tailscale — tirar do group:suporte + revogar device"
  - "SSH — remover chave de authorized_keys (Hostinger + CT 100)"
  - "Vaultwarden — revogar acesso + ROTACIONAR segredos compartilhados que a pessoa conhecia"
  - "hPanel/gateways/2FA — revogar acessos administrativos"
  - "Registrar o offboarding (data, quem, o que foi revogado/rotacionado)"
related_adrs:
  - 0044-vaultwarden-self-hosted-cofre
  - 0030-credenciais-jamais-em-git
  - 0081-identity-mesh-mcp-actors
  - 0057-tela-team-admin-regras-governanca-tokens-mcp
---

# RUNBOOK — Offboarding de integrante do time

> **Por que existe:** o [PLANO-profissionalizar-acesso-time §3.5](PLANO-profissionalizar-acesso-time.md) apontou que **não havia** checklist canônico de revogação quando alguém sai. Este é o gap fechado. Aplica a qualquer saída — funcionário CLT, contratado PJ, ou fim de acesso temporário.
>
> **Regra de ouro:** *revogar acesso é metade; a outra metade é **rotacionar** todo segredo compartilhado que a pessoa pôde ver.* Chave revogada **não desfaz** uma chave copiada. E **desabilitar a conta NÃO revoga tokens/PATs/deploy keys** — são credenciais de máquina que seguem vivas até serem revogadas uma a uma.

## Quando rodar

No mesmo dia em que a saída é decidida — antes ou junto do último acesso da pessoa. Não deixar "pra depois": o intervalo entre decisão e revogação é a janela de risco.

## Passos

### 1. GitHub (código)
- Settings → Collaborators/Teams → **remover** a pessoa do repo/org.
- Se ela era **code owner** (ver [`.github/CODEOWNERS`](../../../.github/CODEOWNERS)): reatribuir os paths dela pra outro dono e abrir PR.
- Revogar **PATs** que ela tenha criado com escopo do repo/org (org admins veem em Settings → Personal access tokens da org, quando SSO/policy aplica).
- Conferir se ela tinha **deploy keys** próprias em algum repo → remover.

### 2. Token MCP
- `/copiloto/admin/team` → localizar tokens da pessoa → **revogar** (`copiloto:mcp-token:revogar` → `mcp_tokens.revoked_at = now()`, [ADR 0057](../../decisions/0057-tela-team-admin-regras-governanca-tokens-mcp.md)).
- 1 pessoa pode ter N tokens (laptop + desktop) — revogar **todos**.

### 3. Identity Mesh
- Marcar `mcp_actors.revoked_at` + `revoked_by_actor_id` do actor humano ([ADR 0081](../../decisions/0081-identity-mesh-mcp-actors.md)).
- Se havia **IA pareada** (`parent_actor_id` = a pessoa), revogar o actor-IA filho também.

### 4. Tailscale (infra)
- Editar [`tailscale-acl.hujson`](tailscale-acl.hujson) → **remover** o e-mail da pessoa de `group:suporte` (ou do grupo em que estava) + aplicar no painel.
- Painel Tailscale → **revogar/expirar** os devices da pessoa.

### 5. SSH
- **Hostinger:** remover a chave pública da pessoa de `~/.ssh/authorized_keys` (se tinha — em regra só Wagner/Felipe têm prod).
- **CT 100:** idem em cada container/host que ela acessava.

### 6. Vaultwarden — revogar + **ROTACIONAR** (o passo mais esquecido)
- Vaultwarden → remover a pessoa da organização + revogar collections compartilhadas com ela.
- Para **cada segredo compartilhado** que ela pôde ver (senhas de serviço, tokens de staging, credenciais que ela usava): **rotacionar na origem** via [RUNBOOK-rotacao-segredos](RUNBOOK-rotacao-segredos.md) + atualizar o consumidor + [`_INDEX-SECRETS.md`](../../_INDEX-SECRETS.md).
- Segredos pessoais dela (que só ela usava) não precisam rotação — só revogação.

### 7. hPanel / gateways / 2FA
- Revogar qualquer acesso administrativo: hPanel Hostinger, painéis de gateway (Asaas/Sicoob/Inter), Langfuse, Meilisearch, e-mail/Slack do time.
- Remover da política de 2FA da org (a saída aparece no audit log).

### 8. Registrar
- Anotar em um session log `memory/sessions/YYYY-MM-DD-offboarding-<pessoa-anon>.md`: data, quem saiu (sem PII sensível), o que foi revogado e o que foi rotacionado. É a trilha (prova, não promessa).

## Checklist rápido (copiar/colar)

```
[ ] 1. GitHub — collaborator removido + code owner repassado + PAT/deploy keys revogados
[ ] 2. MCP token(s) revogado(s)
[ ] 3. mcp_actors.revoked_at marcado (+ IA filha)
[ ] 4. Tailscale — fora do group + devices revogados
[ ] 5. SSH — chave removida (Hostinger + CT 100)
[ ] 6. Vaultwarden — acesso revogado + segredos compartilhados ROTACIONADOS
[ ] 7. hPanel/gateways/2FA revogados
[ ] 8. Offboarding registrado (session log)
```

## Pegadinhas

- **Não pare no passo 1.** Remover do GitHub não toca em Tailscale, SSH, tokens MCP nem Vaultwarden — cada superfície é independente ([PLANO §2.5](PLANO-profissionalizar-acesso-time.md)).
- **Rotação > revogação** para segredo compartilhado. Se em dúvida se a pessoa viu o segredo, rotacione.
- **PII:** não colocar CPF/dados sensíveis da pessoa no session log — só o handle/sigla ([regras-time](../../regras-time.md)).
