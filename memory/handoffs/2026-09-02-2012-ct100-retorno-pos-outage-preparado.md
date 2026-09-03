---
date: "2026-09-02"
time: "20:12"
slug: "ct100-retorno-pos-outage-preparado"
tldr: "CT 100 segue fora (medido 4× com controle positivo). O runbook do RETORNO está escrito e commitado (passos 0-6, comando + recibo, no dono existente). A checklist NÃO foi executada e os evals NÃO rodaram — depende do [W] religar. Dois achados que mudam a ação física: o ARP do 192.168.0.50 responde com MAC de OUI Proxmox (mas isso NÃO prova host vivo), e o sintoma piorou de 502 às 08:04 para 000 às 19:59 — sem CDN na frente, o 502 só podia ter saído do próprio CT 100."
decided_by: []
cycle: null
prs: []
us: []
next_steps:
  - "[W]: religar/conferir o CT 100 — e conferir o CABO antes (1ª hipótese do runbook; os 3 nós Tailscale caíram juntos)"
  - "Assim que houver acesso: rodar `journalctl --list-boots | tail -3` — separa 'rede caiu' de 'host caiu' em um comando"
  - "Quem pegar o retorno: rodar os passos 0-6 da seção nova do RUNBOOK-acesso-ct100.md, na ordem"
  - "Preencher os 3 recibos ❓ da seção (meilisearch /health · mcp /api/mcp/health · vault) com o valor observado + data"
  - "Só depois dos passos 0-6: rodar /opt/oimpresso-ragas/ct100-jana-evals.sh e conferir se a semana entra em governance/ragas-real-trend"
  - "[W] decide: o trend RAGAS já vinha fail ANTES do outage (context_recall 0.40 → 0.03 em ago). Cadência restaurada ≠ qualidade restaurada"
related_adrs: ["0062-separacao-runtime-hostinger-ct100"]
---

# CT 100 — retorno pós-outage preparado (a máquina ainda não voltou)

## Estado no fechamento

**CT 100 fora.** Medido 4× entre 19:59 e 20:12 BRT: `tailscale ping ct100-mcp` sem resposta;
`mcp` / `langfuse` / `vault` / `staging` todos **HTTP 000**. **Controle positivo em toda
medição:** `https://oimpresso.com/login` → **200**, então os zeros são do alvo, não da minha
rede. Os três nós Tailscale (`ct100-mcp`, `pve-empresa`, `recorder`) estão offline **juntos**.

## O que ficou pronto

Seção **"Retorno pós-outage"** em
[`memory/requisitos/Infra/RUNBOOK-acesso-ct100.md`](../requisitos/Infra/RUNBOOK-acesso-ct100.md)
— commit `667df4a07b`, **+206 −0**, inserção pura com teste de identidade (remover a seção
devolve o arquivo byte-idêntico a `origin/main`). Estendi o **dono existente**: aquele runbook
já cobria o *diagnóstico da queda*; faltava o *retorno*.

Passos **0-6**, cada um com comando e recibo: camadas de rede → Docker/Traefik → stacks por
compose → 4 domínios → `mcp:sync-memory` → `cron-watchdog --entrega` → evals da Jana.

Recibo `📌` = **medido** (fonte citada). Recibo `❓` = **sem valor de referência no repo**, a
preencher na 1ª execução com a data — três ficaram assim, e ficaram de propósito: não achei
valor medido e **não inventei**.

## Os dois achados que o [W] precisa ver antes de ir até a máquina

### 1. A máquina responde em camada 2 — mas isso não prova que está viva

| Camada | Resultado |
|---|---|
| **L2 (ARP)** | **responde** — MAC `bc-24-11-47-c8-7d`, OUI `BC:24:11` = **Proxmox Server Solutions** |
| **L3 (ICMP)** | **8/8 falham** em 30s, sem intermitência observável |
| **L4 (TCP)** | 22 · 443 · 8006 → **timeout em todas**, nenhuma recusa |

Estou **na mesma LAN** (`192.168.0.101`), então as sondas são válidas. E a sonda discrimina —
controle positivo: gateway `192.168.0.1` porta 80 **ABERTA em 9ms**, 443 **RECUSADA**.

⚠️ **Não afirmo que a máquina está viva.** A entrada ARP pode ser cache do Windows, e o
desempate (`arp -d` + re-resolver) **exige terminal elevado** — tentei e devolveu *"requer
elevação"*, com a entrada intacta. O comando está no Passo 0 para quem tiver elevação.

### 2. O sintoma PIOROU hoje: `502` de manhã → `000` à noite

O [handoff das 08:04 de hoje](2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md) registrou
**`CT 100 em 502`**. Às 19:59 eu medi **`000`**. São estados diferentes: `502` é *proxy vivo,
backend morto*; `000` é *nada atende*.

E a topologia sustenta a leitura, porque **não há CDN na frente** — medido agora:

```
mcp / langfuse / vault / staging .oimpresso.com  ->  177.74.67.30    (IP público do CT 100)
oimpresso.com                                    ->  148.135.133.115 (Hostinger, o controle)
```

Com o DNS apontando direto ao CT 100, um `502` **só pode ter saído do próprio CT 100** (Traefik
de pé, serviço atrás dele fora). Ou seja: **hoje de manhã havia algo rodando lá.**

⚠️ **Isto é inferência, não medição minha:** o `502` eu li de um handoff de outra sessão, não
observei, e não sei de qual componente saiu. Mas se procede, contraria "desligado desde 28/08 e
estático" — e reforça a hipótese de **rede/cabo** sobre a de host desligado.

**Por que os dois achados importam juntos:** se o host estiver ligado e só a rede estiver morta,
"ir lá e ligar" é a ação errada. O runbook já cataloga o **cabo ruim** como 1ª hipótese
([W] 2026-07-16), e a assinatura *"nós vizinhos caem juntos"* bate com os três offline. O
`journalctl --list-boots | tail -3` resolve isso em **um comando** assim que houver acesso — se
o boot anterior só terminar quando o [W] reiniciar, a máquina esteve ligada o tempo todo.

⚠️ **Ambiguidade que quase virei fato:** `tailscale status` mostra `LastSeen 2026-09-02T19:20:00.1Z`
nos três nós — "40 min atrás", o que contradiria "fora desde 28/08". **Timestamp idêntico ao
centésimo em três nós não é 'caíram juntos'**; é provavelmente carimbo do netmap. Não consegui
separar as leituras (o daemon local está de pé desde 31/08, então não é artefato de start).
Fica **ambíguo**, não vai como fato.

## O que NÃO foi feito

| Item | Motivo |
|---|---|
| Executar a checklist | CT 100 não voltou |
| `ct100-jana-evals.sh` + semana no trend | idem — e o script exige o container de pé (sai `exit 1` sem ele) |
| **MCP-first do fechamento** (`cycles-active`, `my-work`, `sessions-recent`, `whats-active`) | **não medi**: o MCP **é** o serviço que está fora |

O fechamento MCP-first não foi esquecido — é impossível nesta sessão **por causa do próprio
incidente**. Registrar isso é mais honesto que omitir a seção.

## Achado lateral que é decisão [W], não conserto

A branch `governance/ragas-real-trend` (última semana: **2026-08-23**, 7 entradas) mostra o
`context_recall` caindo de **0.40** (julho) para **0.043 / 0.031** (09 e 16/ago), com três
`gate_status=fail` e dois `skipped` — **tudo anterior ao outage**. Restaurar a cadência dos
evals não restaura a qualidade. Deixei o aviso no Passo 6 para quem executar o retorno não
confundir "o cron voltou" com "a Jana está bem". Não investiguei: fora do escopo, e o
instrumento roda no CT 100.

## Estado MCP no momento do fechamento

**Não consultado — MCP inacessível.** `https://mcp.oimpresso.com` → **HTTP 000** nas 4
tentativas (19:59 · 20:04 · 20:10 · 20:12 BRT). `cycles-active`, `my-work`, `sessions-recent`,
`decisions-search` e `whats-active` rodam contra `mcp.oimpresso.com` (CT 100) e nenhuma
respondeu. O `brief-fetch` do SessionStart caiu em fallback por timeout, como o próprio hook
registrou.

**Substituto medido, do que deu para medir localmente:**

```
node scripts/governance/cron-watchdog.mjs --entrega   → exit 1
📦 17 artefato(s) de estado com data interna (de 267) · limite 60d · 2 🔴 parado(s)
🔴 governance/jana-ragas-baseline.json      — 63d (data interna 2026-07-01)
🔴 governance/jana-ragas-real-baseline.json — 63d (data interna 2026-07-01)
```

Esse baseline está na seção nova: se a lista **crescer** no retorno, o outage derrubou entrega
nova.

## Nota de worktree (para quem retomar aqui)

Trabalhei em branch nova `claude/ct100-retorno-pos-outage`, criada a partir de `origin/main`
**fresco** (0/0). A branch anterior deste worktree — `claude/ct100-pos-retorno-b4ac7d` — estava
**−147** e carrega **um commit não-mergeado de outra sessão** (`0543136e7f`, 28/ago,
*"feat(design): exigir recibos executáveis por tela"*). **Não a toquei**, e ela segue apontando
para aquele commit. Quem for limpar o worktree precisa saber que ele está lá.

## Refs

- [session log 2026-09-02](../sessions/2026-09-02-ct100-outage-retorno-runbook.md) — medições completas e o que ficou por fazer
- [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100
- [handoff 2026-08-31](2026-08-31-1054-jana-p0-tier0-faxina-e-d0-identidade.md) — registrou "CT 100 em 502 desde 28/ago"
