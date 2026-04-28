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

## 6. Ativos disponíveis (homologação / serviços que precisam de daemon)

> Hostinger compartilhado **não roda daemons persistentes** (sem supervisord, sem controle do nginx pra WS proxy). Pra Reverb, Meilisearch como serviço, agentes Vizra ADK em background, Horizon supervised, etc., usar os ativos abaixo.

### 6.1 Servidor Proxmox da empresa (oimpresso, escritório)

```
Status:        ✅ ATIVO E DISPONÍVEL (a partir de 2026-04-28)
Localização:   Escritório oimpresso (Wagner)
Hardware:      128 GB RAM, 2 TB HD
Hypervisor:    Proxmox VE
IP fixo:       <preencher: IP público da empresa>
Hostname:      <preencher>
Upload Mbps:   <preencher: importante pra dimensionar conexões WS>
Acesso SSH:    <preencher: porta + chave a configurar>
Painel Proxmox: <preencher: URL https://...:8006>
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

---

> **Última atualização:** 2026-04-28 (sessão Reverb install — adicionada §6 Ativos: Proxmox empresa disponível pra Reverb/Meilisearch/workers; Hostinger fica só com PHP-FPM do app)
