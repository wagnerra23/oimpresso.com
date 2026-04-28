# ADR 0044 — Vaultwarden self-hosted como cofre de credenciais

**Status:** ✅ Aceita
**Data:** 2026-04-28
**Escopo:** Plataforma — gestão de credenciais (humanas e de aplicação) do projeto e da equipe
**Decisor:** Wagner [W] (declarou em 2026-04-28: "onde posso colocar a senhas em segurança" → ok pra Vaultwarden)
**Implementador:** Claude (sessão 2026-04-28, mesmo PR #64)
**Branch:** `claude/reverb-install`

---

## Contexto

Acumulamos ao longo da sessão de provisão do Proxmox/Reverb várias credenciais sem cofre formal:

- `root@pam` Proxmox + senha + token API mcp2
- CT 100 root password (`docker-host`)
- BasicAuth do dashboard Traefik (`admin / <gerada>`)
- Login Hostinger SSH (já em `~/.ssh/id_ed25519_oimpresso`)
- Login KingHost web
- Cliente Cursor + Code config keys
- Futuras: `OPENAI_API_KEY`, `MEILI_MASTER_KEY`, `REVERB_APP_SECRET`, etc.

Hoje essas senhas estão:

- Em **auto-memória local** do Claude (`C:\Users\wagne\.claude\projects\.../memory/reference_*.md`) — texto claro, fora do git, mas qualquer um com acesso à máquina lê
- Em **`.env`** no CT 100 (gitignored, permissão 600)
- **No chat** Claude (alguns valores apareceram em mensagens) — ver ADR de boas práticas

**Problema:** sem cofre formal, equipe (Wagner + Felipe + Maíra + Luiz + Eliana) compartilha credenciais por chat, screenshot ou texto plano. Risco LGPD + risco operacional + sem auditoria de quem acessou o quê.

## Decisão

**Adotar Vaultwarden 1.35+ self-hosted no `docker-host` (CT 100, Proxmox empresa)** como cofre canônico de credenciais humanas. Compatível 100% com clients oficiais Bitwarden (extensão Chrome/Edge/Brave/Firefox, app Android/iOS, desktop Windows/Mac/Linux, CLI).

**Acesso:** `https://vault.oimpresso.com` (TLS Let's Encrypt automático via Traefik label, quando DNS+port forwards prontos).

**Tier:** OSS, gratuito, ilimitado de usuários e itens.

## Alternativas consideradas

| Opção | Por que não |
|---|---|
| **Bitwarden Cloud (Free / Teams)** | Free funciona pra individual mas Teams ($3/usuário/mês) tem custo recorrente. Dados ficam em servidores Bitwarden (US/EU) — preocupação LGPD residency. Vaultwarden self-hosted é gratuito e dados ficam na empresa. |
| **1Password Teams** | UX excelente mas ~R$100/mês p/ 5 usuários. Não justifica pra esse porte. |
| **KeePass / KeePassXC** | Offline-only, sem sync nativo. Compartilhar com equipe vira manual (DropBox/SyncThing) — frágil. |
| **LastPass** | Histórico de incidentes de segurança (2022 master password breach). Não recomendado. |
| **Passbolt** | OSS self-hosted, foco em equipes, mas instalação mais complexa que Vaultwarden e UX inferior. |
| **Hashicorp Vault** | Overkill pra 5 pessoas. Excelente pra secrets de aplicação automatizados, péssimo pra UX humana. |

## Consequências

**Positivas:**

- **$0/mês** de custo recorrente (vs $30+/mês Teams plans)
- **Dados na empresa** (LGPD residency garantida — backup junto com Proxmox snapshot)
- **UX familiar** — clients Bitwarden oficiais funcionam direto
- **Compartilhamento por organization/collections** — pode separar "Wagner pessoal" / "oimpresso ERP" / "PontoWr2 cliente Eliana(WR2)" / "Tokens de aplicação"
- **WebSocket sync** entre dispositivos (mudou senha no celular → extensão Chrome atualiza em segundos)
- **Container leve** — ~20 MB RAM, ~50 MB disk inicial

**Negativas / dívidas técnicas assumidas:**

- **Backup é responsabilidade do operador.** Snapshot Proxmox cobre, mas vale ter export adicional (Vaultwarden tem export Bitwarden-format por usuário). Se o CT 100 cair antes do backup, perde-se senhas adicionadas no intervalo.
- **Recuperação de master password é IMPOSSÍVEL** — perdeu a master, perdeu o vault desse user. Mitigação: cada usuário cria "emergency contact" + escreve master em papel guardado fisicamente.
- **`ADMIN_TOKEN` em texto claro** no `.env` — versão 1.35.8 ainda aceita mas avisa. Migrar pra Argon2 PHC (`vaultwarden hash`) numa segunda iteração.
- **Sem MFA via TOTP por hardware** no plano free do Vaultwarden — TOTP via Google Authenticator funciona; YubiKey requer build com feature flag.
- **Single point of failure**: se `docker-host` cai, equipe não acessa nenhum painel/site que dependa de senha do vault. Mitigação: extensão Bitwarden mantém cache local criptografado por X horas (config).

**Neutro:**

- DOMAIN está hardcoded (`https://vault.oimpresso.com`) — clients vão exigir esse hostname. Acesso via IP direto + porta funciona pra setup inicial mas extensões Bitwarden recusam.

## Plano de implementação

**Feito (sessão 2026-04-28):**

1. ✅ Adicionar bloco `vaultwarden` ao [`compose.yml`](../../infra/proxmox/docker-host/compose.yml) com label Traefik `Host(\`vault.oimpresso.com\`)` + entrypoint websecure + cert resolver `le`
2. ✅ Variáveis em [`.env.example`](../../infra/proxmox/docker-host/.env.example): `VAULTWARDEN_SIGNUPS_ALLOWED`, `VAULTWARDEN_ADMIN_TOKEN`
3. ✅ `docker compose up -d vaultwarden` no CT 100 — container `healthy`, smoke test LAN HTTP/2 200 com headers Bitwarden
4. ✅ Porta 8200 exposta temporariamente pra acesso pré-DNS (`http://192.168.0.50:8200/`)
5. ✅ Auto-memória `reference_vaultwarden_credenciais.md` (fora do git) com `ADMIN_TOKEN` e instruções 1ª subida

**Pendências (Wagner — fora do código):**

- 🟡 Criar A record DNS `vault.oimpresso.com → 177.74.67.30` (junto com reverb/portainer/traefik) no painel Hostinger
- 🟡 Acessar `http://192.168.0.50:8200/` agora e **criar conta master** (master password forte, anotar em papel)
- 🟡 Após criar conta: trocar `VAULTWARDEN_SIGNUPS_ALLOWED=true` → `false` no `.env` do CT, `docker compose up -d vaultwarden` pra recriar com cadastro fechado
- 🟡 Convidar equipe via email-invite do painel admin `/admin` (precisa SMTP — config futura) ou via convite manual

**Pendências (Claude — futuro):**

- 🟢 Migrar `ADMIN_TOKEN` plain → Argon2 PHC (`vaultwarden hash`)
- 🟢 Configurar SMTP (Hostinger Email ou Gmail App Password) pra invites/reset funcionarem
- 🟢 Migrar credenciais de auto-memória (`reference_*.md` com senhas) pra organizations no Vaultwarden:
  - "Infra" — Proxmox, CT, Traefik, KingHost, Hostinger
  - "App credentials" — `OPENAI_API_KEY`, `REVERB_APP_SECRET`, `MEILI_MASTER_KEY`
  - "Pessoal Wagner" — não-projeto
- 🟢 Avaliar Infisical/Doppler pra secrets de aplicação automáticos no Laravel (Vaultwarden é foco humano, não machine-to-machine)

## Rollback

- **Container down:** `docker compose stop vaultwarden`
- **Container destroy + dados preservados:** `docker compose rm -f vaultwarden` (volume `vaultwarden-data` fica)
- **Wipe total:** `docker compose down vaultwarden && docker volume rm vaultwarden-data` (perde TUDO — refazer cadastro)
- **Migrar pra Bitwarden Cloud:** export Bitwarden-format do vault, criar conta cloud, importar. Compatível 100%.

## Relacionadas

- [ADR 0040](0040-policy-publicacao-claude-supervisiona.md) — Policy de publicação (não vazar credenciais em PR/commit/Slack)
- [ADR 0043](0043-docker-host-traefik-vs-lxc-nativo.md) — Stack Docker que hospeda Vaultwarden
- [INFRA.md §6.1](../../INFRA.md) — Servidor Proxmox empresa

---

**Resumo executivo (1 linha):** Vaultwarden self-hosted no `docker-host` (1 container, ~20 MB RAM, $0/mês) — clients Bitwarden oficiais, dados na empresa (LGPD), backup junto com snapshots Proxmox, organizations pra equipe (Infra / App / Pessoal).
