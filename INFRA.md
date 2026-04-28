# INFRA.md — Acesso, deploy, fixes de produção

> **Para quem é este arquivo:** quem precisa subir código pra `oimpresso.com` (Hostinger), debugar produção via SSH, ou aplicar patches manuais que não cabem no fluxo git/CI.
>
> Pra fluxo de trabalho diário (ler memória, convenções, padrões de código), comece em [`CLAUDE.md`](CLAUDE.md). Pra qualquer coisa visual, em [`DESIGN.md`](DESIGN.md).

---

## 1. Servidor de produção (Hostinger Cloud Startup)

```
Host: 148.135.133.115
Port: 65002
User: u906587222
Key:  ~/.ssh/id_ed25519_oimpresso
Repo: ~/domains/oimpresso.com/public_html      (Laravel)
DB:   u906587222 / o51617061                    (MySQL, no mesmo host)
PHP:  /usr/bin/php          (8.4.19)
Composer: /usr/local/bin/composer
```

**IPv4 only** — sempre `-4`. Sem isso o handshake falha intermitentemente.

**Comando padrão:**
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 -o ConnectTimeout=60 u906587222@148.135.133.115 'CMD'
```

---

## 2. Cuidados com SSH

- SSH é **flaky**: 1ª tentativa frequentemente dá timeout. Receita: `curl -s4 https://oimpresso.com/ > /dev/null` pra warm e retry com `ConnectTimeout=120`.
- Multiplexing (`ControlMaster=auto`) **não funciona** no Hostinger — uma conexão por comando.
- **Nunca editar arquivo direto via SSH** — sempre `git pull` no repo. Bypass git = drift permanente (já queimou Eliana no 3.7→6.7).

---

## 3. Deploy

**Após push na branch principal (atualmente `main`) com mudança em `composer.json`/`composer.lock`:** rodar `composer install` (sem `--no-dev` — Faker é usado em prod). O workflow `quick-sync.yml` NÃO faz isso; sintoma de skip é tela branca Inertia. Ver `memory/sessions/` para incidente do upgrade Inertia v3.

**Receita de deploy manual** (quando `quick-sync.yml` falhar — ver auto-memória `reference_quick_sync_quebrada.md`):
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git pull origin main && \
   php artisan optimize:clear && \
   composer dump-autoload"
```
Se `composer.lock` mudou, trocar `dump-autoload` por `composer install`.

**Branch hardcoded em workflows CI:** `.github/workflows/deploy.yml` e `quick-sync.yml` ainda referenciam `6.7-bootstrap` em algumas linhas — PR de cleanup pendente (ver handoff).

---

## 4. Patches manuais ativos

- **WP `/ajuda/`** tem patch manual de PHP 8.4 (`create_function` → closures) — atualização via wp-admin reverte; ver auto-memória `reference_wp_ajuda_fix.md` se precisar repatchar.

---

## 5. Dev local (Herd / Laragon)

- Site: `https://oimpresso.test` (Herd SSL)
- MySQL: Laragon `127.0.0.1:3306` root sem senha, DB `oimpresso`
- PHP: 8.4 Herd
- Login: `WR23` / `Wscrct*2312`
- Meilisearch local: `http://127.0.0.1:7700` (PID auto, ver `D:\oimpresso.com\meilisearch\`)

---

## 6. Ativos da empresa

> Inventário completo de servidores, serviços externos e painéis usados pelo projeto e operação. Credenciais detalhadas em auto-memória (fora do git) — aqui só método de acesso e responsabilidade.

### 6.0 Resumo (de relance)

| # | Ativo | Onde / IP | Função | Acesso |
|---|---|---|---|---|
| 6.1 | **Proxmox empresa `sistema`** | LAN 192.168.0.2 / pública 177.74.67.30:8006 | Hypervisor — hospeda CT 100 (Docker: Traefik+Portainer+Vaultwarden+Reverb futuro) | root@pam SSH/web + Token API mcp2 |
| 6.2 | **Hostinger Cloud Startup** | 148.135.133.115 | App PHP-FPM principal (oimpresso.com) | SSH key + hPanel (Google OAuth) + API token |
| 6.3 | **Windows Server 2022** | LAN 192.168.0.3 + 192.168.0.4 (2 NICs) | Sistemas Delphi (WR Comercial) + FireBird DB + serviços legados | RDS 3389 (login pendente Wagner) |
| 6.4 | **Central VoIP** | LAN 192.168.0.21 | PBX Issabel/Asterisk/Elastix — telefonia interna | painel `https://192.168.0.21/` admin/wscrct.000465 |
| 6.5 | **KingHost / Uni5** | painel.kinghost.com.br | DNS de `wr2.com.br` + e-mails da empresa | login eliana@wr2.com.br (senha em auto-memória, 2 candidatas) |

**Onde estão as credenciais detalhadas (auto-memória local, fora do git):**

- Proxmox + CT 100: `reference_proxmox_credenciais.md`
- Hostinger SSH: `reference_hostinger_ssh_credenciais.md` + `reference_hostinger_server.md`
- Hostinger hPanel + API: `reference_hostinger_hpanel.md`
- Central VoIP: `reference_central_voip_issabel.md`
- KingHost/Uni5: `reference_painel_kinghost.md`
- Vaultwarden (cofre que vai centralizar tudo isso): `reference_vaultwarden_credenciais.md`

> Hostinger compartilhado **não roda daemons persistentes** (sem supervisord, sem controle do nginx pra WS proxy). Pra Reverb, Meilisearch como serviço, agentes Vizra ADK em background, Horizon supervised, etc., usar os ativos abaixo.

### 6.1 Servidor Proxmox da empresa (oimpresso, escritório)

```
Status:        ✅ ATIVO E DISPONÍVEL (a partir de 2026-04-28)
Localização:   Escritório oimpresso (Wagner)
Hardware:      Intel Xeon E5-2680v4 14C / 125.7 GB RAM / 2 TB HD
Hypervisor:    Proxmox VE 9.1.1 (kernel 6.17.2)
Node:          sistema
IP LAN:        192.168.0.2 (bridge vmbr0)
IP público:    177.74.67.30 (ISP ateky.net.br — IP fixo da empresa)
DNS planejado: reverb.oimpresso.com A 177.74.67.30 (Cloudflare laranja-OFF — pendente Wagner criar)
Port forward:  TCP 443 externo → 192.168.0.50:443 LAN (pendente Wagner config router)
Upload Mbps:   <preencher: importante pra dimensionar conexões WS>
Acesso SSH:    192.168.0.2:22 (root, mesma senha do painel)
Painel Proxmox: https://192.168.0.2:8006/ (login root@pam — senha em auto-memória, não commitar)
Token API:     root@pam!mcp2 criado 2026-04-28 via REST (privsep=0; secret em auto-memória)
MCP Proxmox:   pendente configurar em .mcp.json quando Wagner ativar
Disco SSD:     /dev/sda Kingston SA400S37 480 GB (boot + LVM)
Disco HDD 2TB: ❌ DECLARADO PELO WAGNER MAS NÃO DETECTADO (pendente investigar
                via Shell Proxmox: lsblk -a / fdisk -l / dmesg)
Storage:       local-lvm (lvmthin, 319.6 GB livres) → disks de VM/CT
                local (dir, 85 GB livres) → ISOs, templates LXC, backups
Templates LXC: debian-12-standard_12.12-1_amd64.tar.zst (118 MB, baixado 2026-04-28)
VMs/CTs:       (nenhuma — instalação fresca em 2026-04-28)
```

**Uso planejado (ordem de prioridade):**

1. **VM `reverb`** — Reverb daemon + cloudflared (ou direto se IP fixo expor 443) — ver [ADR 0042](memory/decisions/0042-reverb-substitui-pusher-cloud.md)
2. **VM `meilisearch`** — Meilisearch como serviço persistente (substitui o `~/meilisearch/` instalado mas não rodando do Hostinger) — ver task A4 de Felipe em [`CURRENT.md`](CURRENT.md) e [ADR 0036](memory/decisions/0036-replanejamento-meilisearch-first.md)
3. **VM `copiloto-workers`** — Horizon + queue workers + agentes Vizra ADK em background (Larissa, FaithCheck, etc.) — ver [ADR 0035](memory/decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)
4. **VM `staging`** — réplica Laravel pra teste pré-produção (evita os 3 incidentes de crash em prod do histórico — CLAUDE.md §4)

**Vantagens vs migrar tudo pra VPS pago:**

- 128 GB RAM acomoda todas as VMs acima com folga
- IP fixo na empresa = sem custo de cloud + sem latência cross-region (Larissa em SP, servidor em SP)
- Proxmox dá snapshot/backup nativo
- Wagner tem controle físico (CLT compliance, LGPD residency, etc.)

**Riscos a mitigar antes de produção:**

- 🟡 **Energia / link da empresa** — quedas afetam disponibilidade. Mitigação: UPS + 4G failover ou manter Hostinger como fallback HTTP-only.
- 🟡 **Backup off-site** — Proxmox snapshots não saem do hardware. Mitigação: replicação pra Hostinger ou S3 weekly.
- 🟡 **Acesso remoto** — SSH inbound pelo IP fixo precisa de firewall + chave forte. Sem senha, só chave pública.
- 🟡 **DNS** — apontar `reverb.oimpresso.com` / `meili.oimpresso.com` pro IP da empresa via Cloudflare (proxy laranja off pra WS funcionar nativo, ou laranja on com plano que suporta WS).

**Próximos passos pra ativar:**

1. [ ] Wagner preenche os campos `<preencher>` desta seção
2. [ ] Wagner configura DNS dos subdomínios pro IP fixo
3. [ ] Claude/Felipe instalam VM Debian 12 + provisionam Reverb (PR #64)
4. [ ] Claude/Felipe instalam VM Debian 12 + provisionam Meilisearch (A4 do Felipe)
5. [ ] Smoke test ponta-a-ponta: Larissa em browser → wss://reverb.oimpresso.com → daemon Proxmox → broadcast OK

### 6.2 Hostinger Cloud Startup (já em uso — §1)

Continua sendo o servidor PHP-FPM do app principal (`oimpresso.com`). Não vamos migrar app pra Proxmox por enquanto — só serviços que precisam de daemon.

### 6.3 Servidor Windows Server 2022 (Delphi / WR Comercial)

```
Status:        ✅ Em produção (sistemas Delphi do Wagner)
Localização:   Escritório oimpresso (mesma LAN do Proxmox)
Hardware:      2× Intel Xeon (multi-socket), 128 GB RAM, placa de vídeo dedicada
SO:            Windows Server 2022
NICs:          duas placas
                ├─ 192.168.0.4 — interface principal (RDS, Cpanel, FTP, FireBird, Horse, etc.)
                └─ 192.168.0.3 — interface secundária (sem port forward público)
Acesso RDS:    rdp://192.168.0.4:3389 (público via TP-Link regra #4)
DB FireBird:   192.168.0.4:3050 (público via TP-Link regra #8)
Login:         <pendente Wagner passar — guardar no Vaultwarden quando subir>
```

**Roda:**
- Sistema Delphi WR Comercial (`reference_delphi_wr_comercial.md`)
- DB FireBird (banco antigo do WR Comercial)
- Serviços diversos legados (Horse:19000, THorse:8050, socket_horce:55666, Rat:214)

### 6.4 Central VoIP (Issabel / Asterisk / Elastix)

```
Status:        ✅ Em produção (telefonia interna)
IP LAN:        192.168.0.21 (reserva DHCP por MAC 94:DE:80:F4:59:2D)
Plataforma:    Issabel/Asterisk/Elastix (família PBX)
Painel:        https://192.168.0.21/ (admin web)
SIP:           UDP 5060 (LAN, sem port forward visível)
Login:         <pendente Wagner passar — Vaultwarden>
```

Range UDP 4000-5999 do TP-Link regra #7 (`Telefone`) direciona pra **192.168.0.2** (Proxmox), o que é provavelmente regra obsoleta — a central real está em `.21` na LAN. Verificar e ajustar quando tiver tempo.

### 6.5 KingHost / Uni5 (DNS legado + e-mails)

```
Painel web:    https://painel.kinghost.com.br/index.php
Login:         eliana@wr2.com.br
Senha:         <em auto-memória — não commitar>
Plataforma:    Uni5 (https://api.uni5.net/) — fabricante por trás da KingHost
```

**Pra que serve:**
- Hospeda zona DNS de **`wr2.com.br`** (domínio da empresa Wagner — diferente de WR2 Sistemas/Eliana(WR2) que é cliente do PontoWr2)
- Serve e-mails @wr2.com.br
- **NÃO usar pra `oimpresso.com`** — esse fica na Hostinger (§6.2)

**API Uni5:** documentação aponta `https://api.uni5.net/<recurso>` (cliente/dominio/dns/etc.) com auth via header. Token API ainda não estabelecido (testado em 2026-04-28 = 401). Quando precisar automatizar DNS de `wr2.com.br`, gerar token específico no painel e atualizar auto-memória.

---

> **Última atualização:** 2026-04-28 (sessão Reverb install — adicionada §6 Ativos: Proxmox empresa disponível pra Reverb/Meilisearch/workers; Hostinger fica só com PHP-FPM do app)
