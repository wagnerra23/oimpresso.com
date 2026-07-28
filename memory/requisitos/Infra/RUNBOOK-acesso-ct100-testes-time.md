---
title: "RUNBOOK — Acesso da equipe (Maiara/Felipe) ao CT 100 pra rodar testes"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Acesso da equipe (Maiara/Felipe) ao CT 100 pra rodar testes

> **Tipo:** runbook reproduzível
> **Origem:** 2026-06-04 — Wagner: dar acesso à Maiara e ao Felipe pro CT 100
> rodarem a suíte Pest "sem perder segurança".
> **Refs:** [INFRA-ACESSO-CANON](../../reference/INFRA-ACESSO-CANON.md) ·
> [feedback-testes-no-ct100-nao-local](../../reference/feedback-testes-no-ct100-nao-local.md) ·
> [ADR 0235 staging](../../decisions/0235-staging-ct100-clone-anonimizado.md) ·
> [RUNBOOK-ssh-hardening-ct](RUNBOOK-ssh-hardening-ct.md) ·
> ACL versionado: [tailscale-acl.hujson](tailscale-acl.hujson)

## Objetivo

Maiara[M] e Felipe[F] (L2) precisam rodar `php artisan test` no container
`oimpresso-staging` (CT 100) — testes **não** rodam local nem no Hostinger
(feedback Wagner 2026-06-01). Liberar **sem** os 2 vazamentos clássicos:

1. **`-G docker` = root** — membro do grupo `docker` escala pra root do host
   (`docker run -v /:/host`). ❌ NÃO usar.
2. **ACL Tailscale catch-all `*→*`** — daria a TODO device acesso a TODA
   máquina/porta (Proxmox, Windows dev, etc.). ❌ Substituído por least-privilege.

## Modelo de segurança (defesa em profundidade)

| Camada | Mecanismo | Garante |
|---|---|---|
| Rede | ACL Tailscale por grupo+tag | suporte só alcança `tag:ct100:22` |
| Auth | Tailscale SSH `check` mode | re-autentica; revoga central (tira do tailnet) |
| OS | usuário próprio, **sem docker, sem sudo geral** | não vira root |
| Comando | 2 wrappers via sudo NOPASSWD | só roda teste/sync, não toca docker socket |
| Dado | staging anonimizado + isolado de prod (ADR 0235) | sem PII real, sem ação no mundo |

## Parte A — Lado servidor (CT 100) — ✅ FEITO 2026-06-04

Usuários `maiara` (uid 1000) e `felipe` (uid 1001), **sem** grupo docker/sudo.

Wrappers (root-owned `0755`, usuário não edita):

```bash
# /usr/local/bin/staging-test  — roda a suíte DENTRO do container (confinado)
#!/usr/bin/env bash
set -euo pipefail
exec docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test "$@"

# /usr/local/bin/staging-sync  — atualiza o código pra origin/main (idempotente)
#!/usr/bin/env bash
set -euo pipefail
cd /opt/oimpresso-staging/code
git fetch origin
git reset --hard origin/main
echo "staging sincronizado em: $(git rev-parse --short HEAD)"
```

sudoers `/etc/sudoers.d/staging-testers` (`0440`):

```
Cmnd_Alias STAGING_TEST = /usr/local/bin/staging-test, /usr/local/bin/staging-sync
maiara ALL=(root) NOPASSWD: STAGING_TEST
felipe ALL=(root) NOPASSWD: STAGING_TEST
```

Validado: `maiara` roda o wrapper; `maiara docker ps` → `permission denied` (✅).

## Parte B — Tailscale (painel `login.tailscale.com/admin`)

1. **Access Controls** → colar o conteúdo de [tailscale-acl.hujson](tailscale-acl.hujson) → Save.
2. **Machines** → `ct100-mcp` → menu `...` → **Edit ACL tags** → adicionar `tag:ct100`.
3. **Users** → **Invite external users** → `maiara@wr2.com.br` e `felipewr2@gmail.com`.
4. Validar (do PC do Wagner, que é admin):
   `tailscale ssh root@ct100-mcp "echo ok"` deve continuar funcionando.

## Parte C — O que Maiara/Felipe fazem (na máquina deles)

1. Aceitar o convite Tailscale no e-mail → instalar Tailscale
   (`https://tailscale.com/download`) → logar com o **mesmo e-mail** convidado.
2. Token MCP chega via **Vaultwarden**, nunca email/WhatsApp.
3. Rodar os testes:

```bash
ssh maiara@ct100-mcp                          # Maiara loga como wr2backup@gmail.com; Felipe como felipewr2@gmail.com
sudo staging-sync                             # traz o código de origin/main
sudo staging-test tests/Feature/Sells/AlgumTest.php   # rodar por PATH
```

> ⚠️ **Rodar por PATH, não `--filter`** (por ora): `--filter` faz o Pest descobrir
> a suíte INTEIRA, que hoje quebra no bootstrap por helpers globais que se
> redeclaram (sweep pendente — `makeChannel` já resolvido #2251, falta `invokePrivate`
> et al). Passar o caminho do arquivo evita a descoberta global.
>
> Eles **não** editam código no servidor — código sempre via git (`staging-sync`).
> Só `oimpresso-staging`, **nunca** `oimpresso-mcp` (MCP server LIVE do time).

## Parte D — Session recording (tsrecorder) — ✅ FEITO 2026-06-04

As sessões SSH da suporte são **gravadas** por um container `tsrecorder` no CT 100.

1. ACL: `tag:session-recorder` em `tagOwners` + `"recorder": ["tag:session-recorder"]`
   e `"enforceRecorder": false` nas regras ssh de Maiara/Felipe (`false` = um hiccup
   do recorder **não** bloqueia o acesso; troca pra `true` se quiser gravação obrigatória).
2. Auth key tagueada `tag:session-recorder` (painel → Settings → Keys → Generate,
   Tags ON, single-use, não-ephemeral). **Segredo → Vaultwarden**, nunca git.
3. Container (CT 100):

```bash
mkdir -p /opt/tsrecorder
chown -R 65532:65532 /opt/tsrecorder   # PEGADINHA: imagem roda como uid nonroot 65532
docker run -d --name tsrecorder --restart unless-stopped \
  -e TS_AUTHKEY="$TS_AUTHKEY" \
  -v /opt/tsrecorder:/data \
  tailscale/tsrecorder:stable \
  /tsrecorder --dst=/data/recordings --statedir=/data/state
```

- **`--ui` ligado** (HTTPS do tailnet habilitado): playback web em
  **`https://recorder.tail38e4d9.ts.net`** (só admin alcança; cert Let's Encrypt
  provisiona no 1º acesso, leva ~1-2 min). O grant `tag:ct100 → tag:session-recorder:443`
  é **obrigatório** pra gravação funcionar (o nó SSH envia a gravação pro recorder).
- Gravações ficam em `/opt/tsrecorder/recordings` (arquivos `.cast`, pequenos).
- Node aparece no tailnet como `recorder` com tag `tag:session-recorder`.
- ⚠️ **Retenção:** tsrecorder não faz prune automático em disco local — CT 100 estava
  83% cheio. Adicionar limpeza periódica (cron) ou mover pra S3 (follow-up).

## Revogar acesso

- **Imediato:** painel Tailscale → Users → remover do tailnet (ou tirar de
  `group:suporte` no ACL). Acesso some sem mexer no servidor.
- **No host (opcional):** `userdel -r maiara` / remover de `/etc/sudoers.d/staging-testers`.

## Pendências / follow-up

- [x] **Session recording** (Tailscale SSH) — ✅ feito (Parte D). Container
  `tsrecorder` gravando em `/opt/tsrecorder/recordings`.
- [ ] **Retenção das gravações** — tsrecorder não prune sozinho; agendar limpeza
  (cron) ou mover storage pra S3. CT 100 a 83% de disco.
- [ ] **Playback web** — opcional: ligar HTTPS no tailnet + readicionar `--ui`.
- [ ] **Bug pré-existente na suíte:** `Cannot redeclare function makeChannel()`
  (colisão `Modules/Whatsapp/Tests/Feature/ChannelUserAccessTest.php` ×
  `LidCrossContactIncidentP0Test.php`) trava `php artisan test` cheio. Corrigir
  antes deles dependerem da suíte completa.
- [ ] **Auto-deploy da `main` no staging** (gap ADR 0235) — eliminaria o
  `staging-sync` manual.
