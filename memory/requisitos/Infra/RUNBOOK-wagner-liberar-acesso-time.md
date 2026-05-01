# RUNBOOK — Wagner libera acesso de infra pro time (Eliana, Felipe, Maíra, Luiz)

**Status:** 🔴 Aberto · **Owner:** Wagner [W] · **Bloqueia:** Eliana [E], Felipe [F], Maíra [M], Luiz [L]
**Criado:** 2026-04-30 · **Disparador:** Eliana ficou bloqueada na sessão 30-abr noite (não conseguiu SSH Hostinger pra inspecionar `business` table do 3.7 — sem key, sem senha de password auth)
**Tags:** infra · onboarding · ssh · vaultwarden · proxmox · tailscale · acesso

---

## Por que isso virou bloqueante

**Sintoma:** Eliana [E] precisou inspecionar `business` table do schema 3.7 (campos PT + cert A1 que ela mesma criou em 2024) pra programar módulo NFSe standalone. Não tinha:

1. ❌ SSH key Hostinger (`~/.ssh/id_ed25519_oimpresso` só existe na máquina do Wagner)
2. ❌ Credencial CT Proxmox / Tailscale (não compartilhada)
3. ❌ Conta Vaultwarden ativa (signups OFF)
4. ❌ Acesso phpMyAdmin (se existe — não documentado)

**Impacto:** Claude (com créditos limitados da Eliana) gastou ~30% de uma sessão pesquisando MCP/auto-mem e tentando SSH com senha (Hostinger só aceita key) — **sem produzir código de NFSe**.

**Bug real:** `oimpresso-team-onboarding` skill cobre só MCP token. Não cobre SSH/CT/Vault. Equipe de 5 não pode trabalhar nos módulos fiscais sem isso.

---

## Definição de pronto (DoD)

Cada dev (Eliana / Felipe / Maíra / Luiz) consegue rodar **localmente** sem pedir nada pro Wagner:

```bash
# Hostinger
ssh hostinger 'cd domains/oimpresso.com/public_html && php artisan tinker --execute="echo json_encode(DB::select(\"DESCRIBE business\"));"'

# CT Proxmox via Tailscale
ssh ct100 'docker ps'

# Vaultwarden
# abrir https://vault.oimpresso.com → login com email/master próprio → ver collection "Infra"
```

E confirma cada um acima dando output esperado. Sem 3 ✅, dev fica bloqueado em tasks de infra/fiscal/deploy.

---

## Wagner — checklist (4 frentes em paralelo)

### 1. SSH Hostinger (~15 min por dev)

**Pra cada dev:**

```bash
# Cada dev gera a própria key NA MÁQUINA DELA e te manda só a pública
# Eliana → no PowerShell dela:
ssh-keygen -t ed25519 -C "eliana@oimpresso" -f ~/.ssh/id_ed25519_oimpresso
cat ~/.ssh/id_ed25519_oimpresso.pub   # ela copia e te manda
```

Você (Wagner) faz UMA VEZ no Hostinger:

```bash
ssh -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
echo "ssh-ed25519 AAAA... eliana@oimpresso" >> ~/.ssh/authorized_keys
echo "ssh-ed25519 AAAA... felipe@oimpresso" >> ~/.ssh/authorized_keys
echo "ssh-ed25519 AAAA... maira@oimpresso" >> ~/.ssh/authorized_keys
echo "ssh-ed25519 AAAA... luiz@oimpresso" >> ~/.ssh/authorized_keys
exit
```

Confirma cada dev:

```bash
# Eliana testa
ssh -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'whoami'
# Esperado: u906587222
```

**~/.ssh/config sugerido pro dev** (atalho `ssh hostinger`):

```
Host hostinger
  HostName 148.135.133.115
  Port 65002
  User u906587222
  IdentityFile ~/.ssh/id_ed25519_oimpresso
  ServerAliveInterval 3
  ServerAliveCountMax 200
  AddressFamily inet
```

**Risco:** Hostinger é shared hosting — todos compartilham mesmo user `u906587222`. Não há isolamento entre devs. Mitigação: trabalhar via worktree git, não editar arquivo no servidor direto (ADR 0061).

---

### 2. Vaultwarden — convite + organization "Infra" (~30 min total)

**Pré-requisito (1×, se não fez):**

- Confirmar SMTP configurado em [`infra/proxmox/docker-host/.env`](infra/proxmox/docker-host/.env) — ADR 0044 diz que estava pendente. Sem SMTP, invite-link tem que ir manual.

**Convidar cada dev:**

```bash
# CT 100
ssh ct100
# Habilita signups temporariamente
docker compose exec docker-host vaultwarden /bin/sh -c "echo 'SIGNUPS_ALLOWED=true' >> /data/.env"
docker compose restart vaultwarden
```

Cada dev abre `https://vault.oimpresso.com/#/register` → cria conta com email próprio + master password forte (escreve em papel guardado fisicamente — recuperação IMPOSSÍVEL).

```bash
# Após todos cadastrados — fechar signups
docker compose exec docker-host vaultwarden /bin/sh -c "sed -i 's/SIGNUPS_ALLOWED=true/SIGNUPS_ALLOWED=false/' /data/.env"
docker compose restart vaultwarden
```

**Criar organization "Infra"** (Wagner faz no painel `https://vault.oimpresso.com/#/admin`):

- Nome: `oimpresso Infra`
- Convidar membros: eliana@..., felipe@..., maira@..., luiz@...
- Roles: `User` (não `Admin` — manter Wagner único admin)

**Collections dentro da org "Infra":**

| Collection | Quem tem acesso | Conteúdo |
|---|---|---|
| `Hostinger SSH` | todos | passphrase da key (se houver), recovery codes |
| `Proxmox + CT` | todos | `root@pam` Proxmox, IP/user/senha CT 100, token API |
| `Tailscale` | todos | invite link, magic-DNS, hostnames |
| `MCP tokens` | todos | tokens oimpresso `/copiloto/admin/team` |
| `App secrets` | só Wagner + Felipe | `OPENAI_API_KEY`, `MEILI_MASTER_KEY`, `REVERB_APP_SECRET` |
| `Wagner pessoal` | só Wagner | senhas pessoais (não-projeto) |

---

### 3. Tailscale (rede interna Proxmox) (~10 min por dev)

**Pré-requisito:** Wagner tem conta Tailscale empresa (`oimpresso.tailnet`).

```bash
# Wagner — gerar invite link no painel Tailscale
# https://login.tailscale.com/admin/users → Invite users
# colar email Eliana / Felipe / Maíra / Luiz → enviar
```

Cada dev:

1. `winget install tailscale` (Windows) ou `brew install tailscale` (Mac)
2. `tailscale up` → autentica via browser
3. Ver hostnames: `tailscale status`
4. Esperado ver: `ct100.tailnet` ou similar com IP `100.x.y.z`

**Adicionar ao `~/.ssh/config`:**

```
Host ct100
  HostName ct100.<tailnet>.ts.net   # Wagner confirma exato
  User root                          # ou outro user criado
  IdentityFile ~/.ssh/id_ed25519_oimpresso
```

**Confirma:**

```bash
ssh ct100 'docker ps'
# Esperado: lista de containers (traefik, portainer, vaultwarden, meilisearch, mcp-oimpresso)
```

---

### 4. MCP token oimpresso (~5 min por dev — se ainda não tem)

Skill `oimpresso-team-onboarding` cobre. Resumo:

```bash
# Wagner — gerar token pro dev
# https://oimpresso.com/copiloto/admin/team
# clicar "+ Token" no nome do dev → copia raw → entrega via Vaultwarden collection "MCP tokens"
```

Dev cola em `.claude/settings.local.json` (gitignored) substituindo `mcp_COLE_SEU_TOKEN_AQUI`.

Confirma:

```bash
# No Claude Code, qualquer dev:
# tools-list deve mostrar 7 tools mcp__Oimpresso_MCP___<NOME>__*
```

---

## Tempo total Wagner

| Frente | Tempo |
|---|---|
| 1. SSH Hostinger × 4 devs | 60 min |
| 2. Vaultwarden | 30 min |
| 3. Tailscale × 4 devs | 40 min |
| 4. MCP token × 4 devs | 20 min |
| **Total** | **~2h30** |

Pode quebrar em 2 sessões de 1h cada se preferir.

---

## Como instruir Claude pra melhorar (skill upgrade)

Após executar este RUNBOOK, atualizar a skill [`.claude/skills/oimpresso-team-onboarding/SKILL.md`](.claude/skills/oimpresso-team-onboarding/SKILL.md) pra cobrir SSH + Tailscale + Vaultwarden (hoje só cobre MCP token).

**Trigger esperado da skill expandida:** ativar quando dev novo abre Claude Code 1ª vez OU pede *"setup completo"*, *"todos os acessos"*, *"infra dev"*.

**Deliverable da skill:** dev roda 1 comando único (`oimpresso-onboard.ps1`?) que:
1. Gera SSH key local
2. Mostra a public key pra copiar e enviar pro Wagner via Slack/Vaultwarden
3. Configura `~/.ssh/config` com aliases `hostinger` + `ct100`
4. Instala Tailscale via winget/brew
5. Mostra link Vaultwarden + cria conta
6. Pede MCP token + escreve em `.claude/settings.local.json`
7. Roda smoke test 4×: `ssh hostinger 'whoami'`, `ssh ct100 'docker ps'`, abrir vault.oimpresso.com, MCP `tasks-current`

---

## Checklist Wagner (copia pra concluir)

- [ ] Frente 1: SSH Hostinger
  - [ ] Eliana — key recebida + adicionada + `whoami` OK
  - [ ] Felipe — idem
  - [ ] Maíra — idem
  - [ ] Luiz — idem
- [ ] Frente 2: Vaultwarden
  - [ ] SMTP confirmado
  - [ ] Org "Infra" criada
  - [ ] 4 devs convidados + cadastrados + master password em papel
  - [ ] Signups fechado novamente
  - [ ] Collections populadas
- [ ] Frente 3: Tailscale
  - [ ] 4 devs convidados
  - [ ] hostnames `ct100.tailnet` testados de cada máquina
- [ ] Frente 4: MCP token
  - [ ] 4 tokens gerados + entregues via Vaultwarden
  - [ ] Cada dev confirma `tasks-current` funciona

Quando todos os ✅ marcados:

- Atualiza `CURRENT.md` removendo "blocked Eliana"
- Atualiza skill `oimpresso-team-onboarding` (frente 5 acima)
- Cria session log `memory/sessions/YYYY-MM-DD-acesso-time-liberado.md`
- Commit + push (webhook GitHub→MCP sincroniza em <60s)

---

## Referências

- ADR 0044 — Vaultwarden self-hosted como cofre
- ADR 0043 — Docker host CT 100 (192.168.0.50 / 177.74.67.30)
- ADR 0060 — Migração rede interna Proxmox
- ADR 0061 — Conhecimento canônico em git/MCP
- ADR 0063 — Hierarquia de fontes (MCP > SSH > filesystem)
- Skill `oimpresso-team-onboarding` (atual — só cobre MCP)
- INFRA.md §SSH (não acessível com hook MCP-first ativo — usar `decisions-search query:"INFRA SSH"`)
