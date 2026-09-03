---
title: "RUNBOOK — Acesso ao CT 100 (oimpresso-mcp + Docker stack)"
owner: W
status: ativo
last_validated: "2026-07-17"
---

# RUNBOOK — Acesso ao CT 100 (oimpresso-mcp + Docker stack)

> 🔌 **CT 100 inacessível / "offline"? → O CABO DE REDE É RUIM (hardware conhecido, [W] 2026-07-16).**
> É a **1ª hipótese**, não a última. Máquina viva + rede morta se parecem **exatamente** com máquina
> desligada (sem ping, sem ARP, `rx 0`). Prove em 1 comando — `journalctl --list-boots` — antes de
> caçar energia/OOM/software. Receita completa: §"CT 100 sumiu da rede?" abaixo.

> **Tipo:** receita operacional "como executar comando hoje"
> **Validado:** 2026-05-06 (Claude Code @ wagner-pc) · **incidente de rede catalogado:** 2026-07-16
> **Hostinger paralelo:** ver CLAUDE.md §7 (warm-up + retry)
> **Hardening setup:** ver `RUNBOOK-ssh-hardening-ct.md` (receita inicial)

---

## TL;DR — comando que funciona hoje

```bash
tailscale ssh root@ct100-mcp 'CMD'
```

- **User:** `root` (não `dev` — `dev` é receita opcional pra ADICIONAR usuário per-dev, não o user padrão)
- **Hostname:** `ct100-mcp` (Tailscale magic DNS) ou IP `100.99.207.66`
- **Auth:** chave SSH + Tailscale ACL automático

⚠️ **Primeiro acesso da sessão:** Tailscale SSH pede re-autenticação via URL. Comando devolve algo como:

```
# Tailscale SSH requires an additional check.
# To authenticate, visit: https://login.tailscale.com/a/abc123
```

Wagner abre a URL no browser, aprova, e os próximos comandos da mesma sessão SSH (~12h, configurável no Tailscale console) passam direto. **Ação manual obrigatória pra Claude Code** — não dá pra contornar via headless.

---

## Estado da rede (verified 2026-05-06)

| Acesso | Hostname Tailscale | IP Tailscale | IP LAN (rede da empresa) | Auth |
|---|---|---|---|---|
| **CT 100 (Docker host)** | `ct100-mcp` | `100.99.207.66` | `192.168.0.50` | Tailscale SSH + chave |
| **Proxmox host empresa** | `pve-empresa` | `100.116.24.69` | **`192.168.0.2`** | Tailscale SSH + chave · web UI `:8006` |
| Wagner laptop | `claude-code-wagner-pc` | `100.92.78.86` | (DHCP) | — (origem) |

**LAN backup** (sem Tailscale):
- CT 100 → `ssh root@192.168.0.50`
- Proxmox → **`https://192.168.0.2:8006`** (web UI, realm Linux PAM)

> ⚠️ **O `192.168.0.2` faltava neste canon.** A tabela trazia só o IP *Tailscale* do `pve-empresa` — e numa queda em que o **Tailscale está morto** é justamente o LAN que salva. O endereço não existia em lugar nenhum do repo (`git grep` vazio); [W] teve que fornecê-lo à mão em 2026-09-02. Medido no mesmo dia: `HTTP 200`, `conn=0.002s`, tela de login do Proxmox VE.

---

## Containers rodando no CT 100 (snapshot 2026-05-06)

```bash
$ tailscale ssh root@ct100-mcp 'docker ps --format "table {{.Names}}\t{{.Image}}\t{{.Status}}"'
NAMES             IMAGE                              STATUS
meilisearch       getmeili/meilisearch:v1.43.0       Up 2 days
mysql-workers     mysql:8.0                          Up 6 days (healthy)
ollama-embedder   ollama/ollama:latest               Up 6 days
oimpresso-mcp     oimpresso/mcp:latest               Up 18 hours (healthy)
traefik           traefik:v3.6                       Up 7 days
portainer         portainer/portainer-ce:lts         Up 7 days
vaultwarden       vaultwarden/server:1.35.8-alpine   Up 7 days (healthy)
```

| Container | Função | Skill/ADR relacionada |
|---|---|---|
| `oimpresso-mcp` | MCP server (Laravel 13 + `laravel/mcp` ^0.9) | ADR 0053 |
| `meilisearch` | Hybrid retrieval (embedder OpenAI text-embedding-3-small) | ADR 0036 |
| `ollama-embedder` | Embeddings local (futuro, não em uso prod ainda) | — |
| `mysql-workers` | DB local pros workers (separado do Hostinger) | — |
| `traefik` | Reverse proxy + Let's Encrypt cert auto | ADR 0042 |
| `portainer` | UI Docker (admin Wagner) | — |
| `vaultwarden` | Cofre de senhas (vault.oimpresso.com) | — |

---

## Atalhos comuns

### Entrar no shell do oimpresso-mcp
```bash
tailscale ssh root@ct100-mcp 'docker exec -it oimpresso-mcp bash'
```
Working dir do container: `/var/www/html` (Laravel root, mesmo layout do app Hostinger).

### Ver logs de container
```bash
tailscale ssh root@ct100-mcp 'docker logs --tail 50 oimpresso-mcp'
```

### Restart oimpresso-mcp (após mudança de config)
```bash
tailscale ssh root@ct100-mcp 'cd /opt/docker/oimpresso-mcp && docker compose restart app'
```

### Listar tools MCP registradas
```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-mcp php artisan mcp:list-tools 2>/dev/null || docker exec oimpresso-mcp grep -rE "tool.*name" config/ routes/'
```

### Conferir SSH tunnel pro MySQL Hostinger
```bash
tailscale ssh root@ct100-mcp 'systemctl status autossh-mysql 2>/dev/null || ss -tlnp | grep 3307'
```

---

## ⚠️ CT 100 "sumiu" da rede? Suspeite do CABO ANTES de qualquer outra coisa

> **[W] 2026-07-16:** *"acho que foi cabo de rede. anote para lembrar, o cabo está ruim"* — **o cabo do CT 100 é hardware sabidamente ruim.** É a **primeira hipótese**, não a última.

**Sintoma que engana** (medido no incidente 2026-07-16, ~23h de "queda"):

| O que se vê | O que parece | O que É |
|---|---|---|
| `tailscale status` → `offline, last seen 23h ago, **rx 0**` | máquina morta | rede morta, **máquina viva** |
| `ping 192.168.0.50` → sem resposta | máquina desligada | idem |
| **ausente do `arp -a`** (camada 2!) | desligada/sem cabo | cabo ruim = igual a sem cabo |
| `mcp.oimpresso.com` + `vault.oimpresso.com` → timeout | stack caiu | só o transporte caiu |

**O teste que MATA a dúvida em 1 comando** (assim que houver qualquer janela de acesso):

```bash
tailscale ssh root@ct100-mcp 'journalctl --list-boots | tail -3'
```

Se o boot anterior **terminou só quando você reiniciou** (e não 23h atrás), a máquina **esteve ligada o tempo todo** → **o problema foi REDE, não host**. No incidente 2026-07-16 o boot `-1` ia de `2026-06-22 13:53` até `2026-07-16 15:57` (o reboot do [W]) — 24 dias de uptime durante a "queda". Confirmação por descarte no mesmo comando: `free -h` (27Gi livres) + `dmesg | grep -i oom` (vazio) ⇒ não foi recurso.

**Assinatura do cabo ruim** (≠ host desligado):
- **Intermitência**: conecta, responde alguns segundos, cai com `Connection closed by UNKNOWN port 65535` / `502 Bad Gateway`.
- **Perde a rota direta**: alterna `direct 192.168.0.50:41641` → `relay "sao"`.
- `rx 0` com `tx` subindo = seu lado fala, o outro não volta (link físico morto). Já `rx > 0` + queda = link **intermitente** (cabo ruim), não ausente.
- **Nós vizinhos caem juntos** (`pve-empresa`, `recorder`) — se o cabo é do host/switch, leva todos.

**Ordem de diagnóstico (barato → caro):**
1. **Cabo/porta do switch** — trocar o cabo é o fix de 30s (hardware conhecido como ruim).
2. `journalctl --list-boots` — separa "rede caiu" de "host caiu".
3. Só então: energia · disco · OOM · software de rede (Tor/VPN/proxy mexem em rota+iptables e isolam a máquina igualzinho).

**⛔ Não repita meu erro (Claude, 2026-07-16):** conclui *"host desligado ou desconectado"* a partir de ping+ARP negativos. A metade "desligado" estava **errada** — e o `--list-boots` provava em 1 comando. **Ausência de rede não distingue máquina morta de cabo morto**; o log de boot distingue.

### Achado lateral do mesmo incidente: disco em 87% — ✅ caducou (re-medido 2026-09-02)

**Em 2026-07-16 (fato datado, preservado):** `/dev/mapper/pve-vm--100--disk--0` → **81G de 99G (87%)**. Não causou a queda (era rede), mas estava apertado: 13G livres num host que roda Langfuse + Postgres + MinIO + staging + Jaeger. O parágrafo original pedia `docker system prune` + rotação de log/trace.

**Re-medido em 2026-09-02:** o volume foi **expandido para 197G** — agora **91G de 197G (49%)**, 98G livres. A urgência do `prune` **caducou**. O número de julho segue verdadeiro na data dele; quem apodreceu foi o ponteiro "vale um prune antes que vire incidente".

```bash
tailscale ssh root@ct100-mcp 'df -h /'
```

---

## 🔁 Retorno pós-outage — ordem de verificação (CT 100 voltou: e agora?)

> **Quando usar:** o CT 100 ficou fora (energia, cabo, host desligado) e voltou. A seção acima
> (`CT 100 "sumiu" da rede?`) é o **diagnóstico da queda**; esta é o **retorno**. Ligar o Proxmox
> fisicamente é ato do [W] — tudo abaixo é o que se faz **depois** disso.
>
> **Regra da seção:** cada passo tem **comando** e **recibo**. Recibo `📌` foi **medido** (fonte ao
> lado); recibo `❓` **ainda não tem valor de referência no repo** — quem rodar primeiro anota o
> valor observado, com a data. Não invente recibo: número plausível escrito à mão é pior que campo
> vazio, porque para de ser conferido.

### Passo 0 — o host voltou mesmo? (separe as camadas antes de culpar serviço)

Da máquina do [W], que está na mesma LAN (`192.168.0.x`) do CT 100 (`192.168.0.50`):

```bash
arp -a | grep 192.168.0.50                     # L2 — MAC bc:24:11:* (OUI Proxmox)
ping -n 1 -w 2000 192.168.0.50                 # L3
tailscale ping --timeout=4s --c=1 ct100-mcp    # overlay
```

| O que você vê | O que É | Próximo passo |
|---|---|---|
| ARP **ausente** + ping falha | nada na rede naquele IP | host desligado **ou** cabo (§ acima — cabo é a 1ª hipótese) |
| ARP **presente** + ping falha + TCP **timeout** | ambíguo: L2 vivo **ou** cache ARP velho | desempatar com o `arp -d` abaixo **antes** de concluir |
| ARP presente + ping OK + `tailscale ping` falha | host vivo, **overlay** caído | `systemctl status tailscaled` no host |
| tudo OK | rede voltou | Passo 1 |

⚠️ **A entrada ARP sozinha NÃO prova que a máquina está viva** — pode ser cache do seu Windows. Só
o flush desempata, e ele **exige terminal elevado** (medido 2026-09-02: `arp -d` sem elevação
devolve *"A operação solicitada requer elevação"* e a entrada **continua lá** — quem ler o `arp -a`
depois disso acha que mediu e não mediu):

```bash
arp -d 192.168.0.50 && ping -n 1 -w 2000 192.168.0.50 ; arp -a | grep 192.168.0.50
# reapareceu => respondeu AGORA (L2 vivo) · não reapareceu => o de antes era cache
```

**Sonda TCP** — `timeout` e `recusada` são respostas **diferentes** (filtrado/morto × serviço
parado). Portas: `8006` Proxmox web · `22` SSH · `443` Traefik:

```bash
for p in 22 443 8006; do
  (timeout 3 bash -c "</dev/tcp/192.168.0.50/$p" 2>/dev/null && echo "$p: ABERTA") || echo "$p: sem resposta"
done
```

**Rode o controle positivo junto** (o gateway) — sem ele, *"tudo em timeout"* não distingue host
morto de sonda quebrada. 📌 Medido 2026-09-02: gateway `192.168.0.1` porta 80 **ABERTA em 9ms** e
443 **RECUSADA em 2s**, enquanto o CT 100 deu **4/4 timeout**. Recusa rápida prova que a sonda anda.

### Passo 1 — Docker e Traefik, antes de qualquer serviço

**Traefik primeiro, sempre.** Ele termina o TLS e roteia **todos** os domínios: com ele fora, os 4
`curl` do Passo 3 dão `000` mesmo com cada serviço saudável por dentro. Perseguir Langfuse com o
Traefik morto é caçar o sintoma errado.

```bash
tailscale ssh root@ct100-mcp 'docker ps --format "table {{.Names}}\t{{.Status}}" | sort'
tailscale ssh root@ct100-mcp 'docker ps | grep traefik'
```

📌 **Recibo:** ~20 containers `Up`. A lista canônica de nomes está em
[INFRA-ACESSO-CANON §CT 100](../../reference/INFRA-ACESSO-CANON.md): `meilisearch` ·
`ollama-embedder` · `oimpresso-mcp` · `bge-reranker` · `centrifugo` ·
`langfuse-web`/`worker`/`postgres-langfuse`/`redis-langfuse`/`clickhouse-langfuse` ·
`minio-langfuse` · `growthbook`(+`mongo`) · `whatsapp-whatsmeow` · `jaeger` · `mysql-workers` ·
`traefik` · `portainer` · `vaultwarden`. **Conte e compare contra a lista** — container que não
subiu no boot não aparece como erro, aparece como **ausência**, e ausência é fácil de não ver.

**Disco:** confira, mas **sem a urgência de julho** — o volume foi expandido e em **2026-09-02**
estava em **49%** (91G de 197G), não nos 87% de 2026-07-16 (§ *Achado lateral* acima tem os dois
números datados). Pós-outage continua sendo a hora barata de olhar, porque Postgres/ClickHouse/MinIO
do Langfuse crescem calados — mas **não saia fazendo `prune` por reflexo**: aquele ponteiro caducou.

```bash
tailscale ssh root@ct100-mcp 'df -h / ; docker system df'
```

### Passo 2 — stacks por compose

O Langfuse tem compose **próprio**, fora do diretório dos outros:

```bash
tailscale ssh root@ct100-mcp 'cd /opt/langfuse/code/docker/langfuse && docker compose ps'
```

📌 **Recibo:** **TODOS** `healthy` ([RUNBOOK-langfuse-ct100 §1.1](RUNBOOK-langfuse-ct100.md) —
*"esperar TODOS 'healthy'"*). `Up` **não basta**: ClickHouse e Postgres sobem antes de aceitar
conexão, e o `langfuse-web` só serve depois deles.

```bash
tailscale ssh root@ct100-mcp 'docker exec meilisearch sh -c "curl -s http://localhost:7700/health"'
tailscale ssh root@ct100-mcp 'curl -sf http://localhost:8080/health'     # bge-reranker
tailscale ssh root@ct100-mcp 'docker exec ollama-embedder ollama list'
```

- 📌 bge-reranker → **HTTP 200** ([RUNBOOK-bge-reranker-ct100](RUNBOOK-bge-reranker-ct100.md) §DoD).
- ❓ meilisearch `/health` → anote a saída no 1º retorno.
- ❓ ollama-embedder → deve listar **`qwen3-embedding:0.6b`** (embedder canônico do índice —
  INFRA-ACESSO-CANON §Meilisearch). Lista **vazia** = modelo não carregado, e aí o recall da Jana
  degrada **sem erro**: a busca responde, só responde pior.

### Passo 3 — os 4 domínios (HTTP, de fora)

```bash
curl -sS https://langfuse.oimpresso.com/api/public/health                                              # 📌 200
curl -s -o /dev/null -w '%{http_code} ssl:%{ssl_verify_result}\n' https://staging.oimpresso.com/login  # 📌 200 ssl:0
curl -s -o /dev/null -w '%{http_code}\n' --max-time 20 https://mcp.oimpresso.com/api/mcp/health        # ❓ anote
curl -s -o /dev/null -w '%{http_code}\n' https://vault.oimpresso.com                                   # ❓ anote
```

Fontes dos 📌: [RUNBOOK-langfuse-operacional](RUNBOOK-langfuse-operacional.md) ·
[RUNBOOK-staging-ct100 §9](RUNBOOK-staging-ct100.md).

⛔ **NÃO teste o MCP pela raiz `/`.** Já produziu **falso-negativo declarado**: um `curl` externo com
timeout de 10s não cobre o middleware completo respondendo rota inexistente em ~9s, e conclui-se
"fora do ar" com o serviço no ar
([handoff 2026-05-15](../../handoffs/2026-05-15-2300-ct100-arruma-disco-reindex-baileys-purge.md)).
Use `/api/mcp/health` ([mcp-endpoints.md](../../reference/mcp-endpoints.md)) e dê folga no timeout.

⚠️ **`ssl_verify_result` ≠ 0 logo após o retorno** costuma ser Traefik em *backoff* do Let's Encrypt,
não certificado inválido. Espere e repita antes de mexer
([RUNBOOK-staging-ct100 §Pegadinhas #7](RUNBOOK-staging-ct100.md)).

⚠️ **`docker restart` não relê o `env_file`** — o container fica com o `.env` velho **em memória**.
Se você mexeu em env durante o outage: `docker compose up -d --force-recreate`
([RUNBOOK-staging-ct100 §Pegadinhas #3](RUNBOOK-staging-ct100.md)).

### Passo 4 — `mcp:sync-memory` volta a sair 0

⚠️ **Este cron roda no HOSTINGER, não no CT 100.** Ele é `->environments(['live'])` e quem o invoca é
o scheduler de produção ([`app/Console/Kernel.php`](../../../app/Console/Kernel.php),
`everyFiveMinutes()`). Procurá-lo no crontab do CT 100 não acha nada — e "não achei" viraria
diagnóstico errado.

```bash
# no HOSTINGER (warm-up + retry: CLAUDE.md §SSH Hostinger)
php artisan mcp:sync-memory --reason=pos-outage-check ; echo "exit=$?"
tail -20 storage/logs/mcp-cron.log
```

📌 **Recibo:** `exit=0`. Enquanto o CT 100 esteve fora, este é o cron que falhou a cada 5 min.
Confira o `mcp-cron.log` **até ver uma execução limpa depois do horário do retorno** — a primeira
pode pegar a stack ainda subindo.

### Passo 5 — watchdog de entrega (roda local, sem rede)

```bash
node scripts/governance/cron-watchdog.mjs --entrega ; echo "exit=$?"
```

Mede **idade de artefato de estado**, não heartbeat — por isso funciona mesmo com o CT 100 fora, e é
a leitura honesta de *"o que parou de ENTREGAR durante o outage"*.

📌 **Baseline medido 2026-09-02, com o CT 100 ainda fora** (compare no retorno — se a lista
**cresceu**, o outage derrubou entrega nova):

```
📦 entrega — 17 artefato(s) de estado com data interna (de 267) · limite 60d · 2 🔴 parado(s)
🔴 governance/jana-ragas-baseline.json      — parado há 63d (última data interna: 2026-07-01)
🔴 governance/jana-ragas-real-baseline.json — parado há 63d (última data interna: 2026-07-01)
exit=1
```

⚠️ **Não leia o `exit` através de um pipe.** `... --entrega | tail` devolve o código do `tail`
(medido nesta sessão: `rc=0` com o script saindo **1**). Rode sem pipe, ou use `PIPESTATUS`.

### Passo 6 — os evals semanais da Jana (o que o outage comeu)

Os 2 evals de staging **não têm scheduler** — quem invoca é um cron do host (`0 6 * * 0`, domingo
06:00 BRT). Com o container fora, eles não aconteceram:

```bash
tailscale ssh root@ct100-mcp '/opt/oimpresso-ragas/ct100-jana-evals.sh' ; echo "exit=$?"
```

📌 **Pré-condição do próprio script:** se o container `oimpresso-staging` não existir, ele sai
**`exit 1`** com `FATAL: container ... não existe — nada invocado (gap honesto no trend)`. Ou seja:
**rode o Passo 1 antes** — este script não é o lugar de descobrir que a stack não subiu.

Depois, conferir se a semana entrou no trend:

```bash
git fetch origin governance/ragas-real-trend
git show FETCH_HEAD:governance/ragas-real-trend.json | grep -o '"week": *"[^"]*"' | tail -3
```

📌 **Estado em 2026-09-02:** a última semana no trend é **`2026-08-23`** (7 entradas). As semanas do
outage estão **ausentes** — e a ausência é honesta por construção: o script nunca inventa run.

⚠️ **Semana no trend ≠ semana boa.** O trend já vinha ruim **antes** do outage: `2026-08-09` e
`2026-08-16` deram `gate_status=fail` com `context_recall` em **0.043 / 0.031**, contra **0.40** em
julho (≈10× de queda), e `2026-08-02`/`2026-08-23` saíram `skipped`. Restaurar a *cadência* não
restaura a *qualidade*: se a semana nova voltar `fail`, o achado é **anterior** ao outage e é
decisão [W] — não conserto silencioso no meio do retorno.

### Fechamento

Só depois dos passos 0-6 é honesto dizer que o CT 100 "voltou". Antes disso o que existe é **ping
verde**, que não é a mesma frase.

⚠️ **E "voltou" não é "estável".** Precedente medido em **2026-09-02**: a queda de 27/08 foi
resolvida e o CT 100 passou o dia acessível — outra sessão rodou `df -h`, `docker exec` e
`tools/list` nele até ~17:35 BRT. Às **19:59 do mesmo dia** ele estava fora de novo (`tailscale
ping` sem resposta, os 4 domínios em `000`), e seguia fora em **03/09 11:27**. Ou seja: **duas
quedas separadas por uma janela de horas**, não uma contínua. Ao fechar um retorno, **registre a
hora da última verificação verde** — sem ela, a próxima sessão herda "está no ar" como se fosse
permanente e vai diagnosticar o incidente errado. E queda que volta sozinha e recai em horas é a
assinatura de **cabo/link intermitente** (§ *CT 100 sumiu da rede?*), não de host desligado. E vale para a seção inteira: passo que não pôde ser medido (sem
`gh`, sem elevação, sem token) se registra como **"não medi"** — nunca como verde inferido.
Instrumento que afirma saúde sem ter medido é o defeito que o
[§5 2026-07-29](../../proibicoes.md) cataloga.

---

## Pegadinhas conhecidas

### 1. `tailscale: failed to look up local user "dev"`
- User `dev` não é o padrão. Use `root`.
- Receita pra ADICIONAR user `dev` (ou outro per-dev) está em `RUNBOOK-ssh-hardening-ct.md` §4.

### 2. `tailscale: failed to look up local user "BOOK-XXXX\\wagne"`
- Aconteceu em comando sem user explícito. Tailscale SSH tenta passar user do Windows local.
- **Sempre prefixar:** `tailscale ssh root@ct100-mcp` (não `tailscale ssh ct100-mcp`).

### 3. URL de auth check no primeiro comando
- Comportamento normal do Tailscale SSH server (modo `check`).
- Não é erro — Wagner abre URL e aprova, depois passa direto por algumas horas.
- Se Claude Code está autônomo (sem Wagner ao lado), agendar comando pra horário em que Wagner esteja disponível pra clicar.

### 4. Comando muito grande via aspas
- `tailscale ssh root@ct100-mcp 'cmd'` engole aspas internas — pra SQL/PHP complexo, usar heredoc:
```bash
tailscale ssh root@ct100-mcp 'bash -s' <<'EOF'
docker exec oimpresso-mcp php artisan tinker --execute="echo 'oi';"
EOF
```

### 5. `ssh root@100.99.207.66` direto (não via tailscale ssh)
- **Funciona** se você tem chave SSH instalada no CT 100.
- Mas Tailscale SSH é preferível: ACL granular, audit em Tailscale console, sem precisar gerenciar chaves manualmente.

### 6. `grep --include` NÃO existe dentro dos containers (BusyBox) — o vazio é ERRO, não ausência

Os containers do CT 100 são Alpine, e o `grep` é BusyBox:

```
grep -rn Foo --include=*.php .   →   rc=2   "grep: unrecognized option: include=*.php"
```

Saída vazia, erro no stderr. Se você canalizar (`| head`), o `rc` passa a ser o do `head` (**0**) e o vazio se disfarça de "não achei". Foi assim que em 2026-09-02 concluí que `MyWorkTool` não era referenciado em lugar nenhum — o registro estava, o tempo todo, em `Modules/Jana/Mcp/OimpressoMcpServer.php`.

**Forma que funciona dentro do container:**
```bash
find . -name "*.php" -not -path "./vendor/*" | xargs grep -ln Foo
```

Regra geral: rode um **controle positivo** (um padrão que você SABE que casa, com as mesmas flags) antes de confiar em qualquer resultado vazio.

### 7. `tools/list` do MCP é PAGINADO de 15 em 15 — uma página não é o inventário

O servidor devolve `nextCursor` (base64 de `{"offset":N}`). Ler só a 1ª página entrega **15 de 44** tools e sugere, falsamente, que `decisions-fetch`, `sessions-recent`, `memoria-search`, `cc-search` e `claude-code-usage-self` "sumiram" — as cinco estão registradas e funcionando. Caí nisso em 2026-09-02, e o corte limpo em ordem de array é justamente o que denuncia paginação (filtro de escopo seria espalhado).

Para inventariar de verdade: **exaurir as páginas** seguindo o `nextCursor`, ou ler a fonte, que é o oráculo:

```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-mcp sh -c "grep -c Tool::class Modules/Jana/Mcp/OimpressoMcpServer.php"'
```

---

## Fluxo: registrar nova tool MCP no oimpresso-mcp

Caso de uso: implementar Sprint que adiciona tool nova (ex: Sprint 1 `brief-fetch`).

```bash
# 1. Entrar no shell
tailscale ssh root@ct100-mcp 'docker exec -it oimpresso-mcp bash'

# 2. Pull da branch nova (CT 100 tem deploy próprio, separado do Hostinger)
cd /var/www/html
git pull origin main

# 3. Editar config/mcp.php (laravel/mcp ^0.9) — ver runbook específico do Sprint
# Ex: memory/requisitos/Infra/RUNBOOK-mcp-tool-brief-fetch.md

# 4. Restart container pra recarregar config
exit  # sair do exec
cd /opt/docker/oimpresso-mcp
docker compose restart app

# 5. Validar tool listada
docker exec oimpresso-mcp php artisan mcp:list-tools | grep <nome-tool>
```

---

## Rodar Pest de uma BRANCH sem tocar a árvore do `oimpresso-staging`

O container `oimpresso-staging` pode ter trabalho não-commitado de **outra sessão** —
`git checkout <branch>` lá dentro é destrutivo. Pra rodar teste de uma branch isolado,
use um worktree em `/tmp` (whitelist do hook block-destructive). **3 pegadinhas:**

```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-staging sh -c "
  cd /var/www/html
  git fetch origin <branch> -q
  git worktree add --detach /tmp/wt-X origin/<branch>
  cp -a /var/www/html/vendor /tmp/wt-X/vendor          # 1. COPIAR, não symlink (ver abaixo)
  cp /var/www/html/.env /tmp/wt-X/.env                 # 3. env do staging
  mkdir -p /tmp/wt-X/storage/framework/{views,cache/data,sessions} \
           /tmp/wt-X/storage/logs /tmp/wt-X/bootstrap/cache   # 2. storage não vem no checkout
  chmod -R 777 /tmp/wt-X/storage /tmp/wt-X/bootstrap/cache
  cd /tmp/wt-X && php artisan test <path> "
'
# limpeza: rm -rf /tmp/wt-X ; git -C /var/www/html worktree prune
```

1. **`vendor` COPIADO (`cp -a`), NUNCA symlink pro `/var/www/html/vendor`.** O
   `autoload_psr4.php` calcula `$baseDir` em runtime via `__DIR__` — com symlink, `__DIR__`
   resolve pro path REAL do staging, então as classes/rotas carregam o código do **staging**,
   não o da branch. Sintoma traiçoeiro: classe/rota nova "não existe"
   (`RouteNotFoundException`), as velhas do staging aparecem, e o teste passa/falha **medindo
   o repo errado**. `composer` nem existe no container — a cópia dispensa `dump-autoload`.
2. **`storage/` + `bootstrap/cache` não vêm no checkout** → boot morre com
   `InvalidArgumentException: Please provide a valid cache path`. Criar + `chmod 777`.
3. **Copiar o `.env`** do staging.
4. **Se a tela for Inertia, criar o stub do manifest do Vite** — senão a *root view* do Inertia
   não renderiza, a resposta vira página de erro e `assertInertia` reprova com
   `Not a valid Inertia response`. **Falso-negativo perfeito:** o código está certo e o teste
   acusa. É o mesmo passo que a lane do CI faz ("Stub Vite manifest — root view sem build do front"):

   ```bash
   mkdir -p /tmp/wt-X/public/build-inertia
   cat > /tmp/wt-X/public/build-inertia/manifest.json <<'EOF'
   {"resources/css/inertia.css":{"file":"assets/inertia.css","src":"resources/css/inertia.css","isEntry":true},
    "resources/js/app.tsx":{"file":"assets/app.js","src":"resources/js/app.tsx","isEntry":true}}
   EOF
   ```

5. **Escolher o BANCO — o `oimpresso_staging` não serve por default.** Medido 2026-08-19: ele
   estava com **13 tabelas e sem `business`** (zerado por outra sessão — é o incidente que a
   [proibicoes §Ambiente](../../proibicoes.md) já registra). Com ele, `seededTenant()` faz
   `markTestSkipped` e a suíte sai **exit 0 sem provar nada** (LC-13). Conferir antes:

   ```bash
   docker exec oimpresso-staging-db mariadb -u<user> -p<pass> -N -e \
     "SELECT table_schema, COUNT(*) FROM information_schema.tables
      WHERE table_schema NOT IN ('mysql','information_schema','performance_schema','sys')
      GROUP BY table_schema;"
   ```

   O cliente do container é **`mariadb`**, não `mysql` (o binário `mysql` não existe no PATH).
   Em 2026-08-19 o schema provisionado e com o tenant canônico era **`oimpresso_qa`**
   (379 tabelas · business 1, 2 e **98** · 141 currencies · 65 permissions) — aponte o
   `DB_DATABASE` pra ele. **Não decore este nome:** rode a consulta acima e escolha o que
   tiver `business` com o 98.

⚠️ Outra sessão rodando `git worktree prune`/`add` no mesmo container pode **despejar o
registro do seu worktree** (o `.git` do worktree vira "not a git repository"). Pra
re-sincronizar 1 arquivo sem depender do git do worktree, mexendo só em refs (não na árvore
alheia): `git -C /var/www/html show origin/<branch>:<path> > /tmp/wt-X/<path>`.

Provado 2026-07-17 (US-INFRA-002): o controle-negativo do teste Tier 0 só ficou honesto
depois de trocar o symlink pela cópia — com symlink, 6/6 "passava" contra o staging.

Reincidido 2026-08-19 (Officeimpresso Onda 1), e vale como recibo do custo: usei symlink sem
ler esta seção. O veredito veio **plausível e errado** — `5 failed, 23 passed`, com as 2 falhas
de Inertia apontando pra um controller que, no código da branch, tinha os 3 `Inertia::render`
no lugar. Estava medindo o `LicencaLogController` do staging (main de #5728, sem render nenhum).
Com `cp -a` + as pegadinhas 4 e 5 acima: **28 passed (81 assertions)**.
A prova barata de que você está medindo a branch certa, antes de rodar qualquer coisa:

```bash
grep -c "<simbolo que SÓ existe na sua branch>" <arquivo>   # 0 = você está no repo errado
```

---

## Refs

- **CLAUDE.md §1** — Stack-alvo IA (mcp.oimpresso.com canônico)
- **INFRA.md §6.2** — CT 100 Proxmox empresa estado
- **ADR 0042** — Infra empresa padrão (Proxmox + Docker + Traefik)
- **ADR 0053** — MCP server canônico (CT 100 + SSH tunnel pro MySQL Hostinger)
- **ADR 0058** — Centrifugo + FrankenPHP (CT 100, Reverb abandonado)
- **ADR 0061** — Zero auto-mem privada
- `RUNBOOK-ssh-hardening-ct.md` — receita hardening inicial (zero)

---

**Última atualização:** 2026-07-17 — adicionada seção "Rodar Pest de uma BRANCH sem tocar a árvore do staging" (worktree em /tmp + vendor copiado, não symlink). Descoberto ao rodar o Tier 0 da US-INFRA-002.

**2026-05-06** — incluído fluxo Tailscale SSH auth check via URL após Sprint 1 ativação real (descoberta: user é `root`, não `dev`; hostname é `ct100-mcp` magic DNS).
