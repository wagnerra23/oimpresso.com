---
date: "2026-09-02"
hour: "20:12"
topic: "CT 100 fora — preparo do retorno pós-outage (runbook estendido) + medição do estado real"
authors: ["C"]
outcomes:
  - "Seção 'Retorno pós-outage' (passos 0-6, comando + recibo) escrita no RUNBOOK-acesso-ct100.md — dono existente estendido, sem doc novo"
  - "CT 100 medido 4× ao longo da sessão: fora nas 4, com controle positivo provando que a sonda funciona"
  - "Achado que altera a ação do [W]: ARP do 192.168.0.50 responde com MAC de OUI Proxmox — mas NÃO é prova de host vivo (arp -d exige elevação)"
  - "Checklist NÃO executada e evals NÃO rodados: o CT 100 não voltou nesta sessão"
prs: []
us: []
related_adrs: ["0062-separacao-runtime-hostinger-ct100"]
---

# CT 100 fora — preparo do retorno pós-outage

> **O que esta sessão entregou:** o runbook do retorno, escrito e commitado.
> **O que ela NÃO entregou, e por quê:** a execução da checklist e os evals da Jana — o CT 100
> não voltou. Ligar o Proxmox é ato do [W], e nada aqui tenta contornar isso.

---

## 1. Estado medido (4 tentativas, 20:00 → 20:12 BRT)

| Hora BRT | `tailscale ping ct100-mcp` | mcp | langfuse | vault | staging |
|---|---|---|---|---|---|
| 19:59 | sem resposta | 000 | 000 | 000 | 000 |
| 20:04 | sem resposta | 000 | 000 | 000 | 000 |
| 20:10 | sem resposta | 000 | 000 | 000 | 000 |
| 20:12 | sem resposta | 000 | — | — | — |

**Controle positivo em toda medição:** `https://oimpresso.com/login` → **HTTP 200**. Os `000`
são do alvo, não da minha rede — sem isso, quatro zeros não distinguiriam CT 100 fora de curl
quebrado (§5 2026-08-01).

**Horário de retorno: não houve.** O item 2 da tarefa pedia registrar o horário quando
acontecesse; não aconteceu dentro desta sessão.

## 2. O achado que muda a ação física do [W]

A premissa de trabalho era "Proxmox desligado desde 28/08". A medição não confirma nem
desmente isso — **mas mostra um estado mais específico do que "desligado"**:

| Camada | Sonda | Resultado |
|---|---|---|
| **L2 (ARP)** | `arp -a \| grep 192.168.0.50` | **responde**: MAC `bc-24-11-47-c8-7d` — OUI `BC:24:11` é **Proxmox Server Solutions** |
| **L3 (ICMP)** | `ping` × 8 amostras em 30s | **8/8 falham**, sem intermitência observável |
| **L4 (TCP)** | portas 22 · 443 · 8006 | **timeout em todas**, nenhuma recusa |

Estou **na mesma LAN** (meu IPv4: `192.168.0.101`), então essas sondas são válidas — não estão
atravessando a internet.

⚠️ **NÃO afirmo que a máquina está viva.** A entrada ARP pode ser cache do meu Windows, e o
desempate (`arp -d` + re-resolver) **exige terminal elevado** — tentei, e devolveu *"A operação
solicitada requer elevação"* com a entrada **intacta**. Quem lê o `arp -a` depois disso acha que
mediu e não mediu. O comando de desempate ficou registrado no runbook para quem tiver elevação.

**Sinal circunstancial, não prova:** às 19:59 o ARP estava **ausente mesmo depois de um ping**;
às 20:04 apareceu. Se a resolução aconteceu ali, algo respondeu em L2. Não consigo fechar isso
sem o flush.

**Por que isto importa para o [W]:** se o host estiver ligado e só a rede/serviço estiverem
mortos, "ir até lá e ligar" é a ação errada — o runbook já cataloga o **cabo de rede ruim** como
1ª hipótese ([W] 2026-07-16), e a assinatura *"nós vizinhos caem juntos"* bate: `ct100-mcp`,
`pve-empresa` e `recorder` estão os três offline.

### ⚠️ ERRATA (2026-09-03) — não era uma queda contínua: foram DUAS, e a inferência abaixo ficou pequena

Ao preparar o PR encontrei o [PR #6587](https://github.com/wagnerra23/oimpresso.com/pull/6587),
mergeado às **17:35 BRT de 02/09** — antes das minhas medições. Ele declara: *"queda do CT 100 de
**27/08→02/09 (6 dias)**. Tudo medido em 2026-09-02"*, e as provas são de **acesso real e
repetido**: `df -h /` (volume expandido, 91G de 197G), `docker exec` dentro dos containers,
`tools/list` do MCP paginando 15 de 44.

Portanto o quadro correto é:

| Janela | Estado |
|---|---|
| 27/08 → 02/09 | queda (6 dias) — a que o handoff de 31/08 registrou como "502 desde 28/ago" |
| 02/09, até ~17:35 BRT | **de pé e plenamente acessível** — SSH, Docker e MCP respondendo |
| 02/09, 19:59 → 03/09 11:27 | **fora de novo** (minhas 6 medições) |

**Duas quedas separadas por uma janela de horas**, não uma contínua desde 28/08. A premissa com
que abri a sessão estava desatualizada, e eu não tinha como saber: o PR irmão foi mergeado no
intervalo, e o MCP — que responderia isso — era o próprio serviço fora.

O que isso faz com o raciocínio abaixo: a inferência do `502 → 000` **apontava na direção certa e
ficou pequena**. Não é só que "havia algo rodando de manhã" — a máquina estava **inteira**. E o
reforço à hipótese de **cabo/link intermitente** fica muito mais forte: um host que volta e recai
em poucas horas não se parece com host desligado.

O raciocínio original fica preservado abaixo, como foi feito na hora e com o que se sabia.

### O sintoma piorou hoje: `502` (08:04) → `000` (19:59)

O [handoff das 08:04 de hoje](../handoffs/2026-09-02-0804-fiscal-onda0-e-consertos-de-gates.md)
registrou **`CT 100 em 502`**. Eu medi **`000`**. Não é a mesma coisa: `502` é *proxy vivo,
backend morto*; `000` é *nada atende*.

E não há CDN na frente para explicar o `502` de outro jeito — medido:

```
mcp / langfuse / vault / staging .oimpresso.com  ->  177.74.67.30    (IP público do CT 100)
oimpresso.com                                    ->  148.135.133.115 (Hostinger, o controle)
```

Com DNS direto ao CT 100, o `502` **só pode ter saído do próprio CT 100** — Traefik de pé, com o
serviço atrás dele fora. Se procede, **hoje de manhã havia algo rodando lá**, o que contraria
"desligado desde 28/08 e estático".

⚠️ **Inferência, não medição minha:** o `502` eu li do handoff de outra sessão; não observei, e
não sei de qual componente saiu. Registro com a premissa à vista para não virar fato por
repetição — foi assim que a lápide [§5 2026-08-11](../proibicoes.md) nasceu (canon negando canon,
e a sessão seguinte herdando a negação).

⚠️ **Um dado do `tailscale status` que eu quase reportei errado:** os três nós mostram
`LastSeen: 2026-09-02T19:20:00.1Z` — "40 min atrás", o que contradiria "fora desde 28/08".
**Timestamp idêntico ao centésimo em três nós distintos não é 'caíram juntos'** — é mais
provável um carimbo do netmap. Não consegui separar as duas leituras (o daemon local está de pé
desde 2026-08-31T14:26Z, então **não** é artefato de start recente). Fica registrado como
**ambíguo**, não como fato.

## 3. O que foi escrito

Uma seção nova no **dono existente** — `memory/requisitos/Infra/RUNBOOK-acesso-ct100.md`. O
arquivo já cobria o *diagnóstico da queda* (§ *CT 100 "sumiu" da rede?*); faltava o *retorno*.
Doc novo teria sido máquina paralela ao dono (LC-19).

Passos **0-6**: camadas de rede → Docker/Traefik → stacks por compose → 4 domínios → 
`mcp:sync-memory` → `cron-watchdog --entrega` → evals da Jana.

**Convenção de honestidade adotada na seção:** recibo `📌` = **medido**, com a fonte citada;
recibo `❓` = **sem valor de referência no repo**, a preencher na 1ª execução com a data. Três
recibos ficaram `❓` (meilisearch `/health`, `mcp/api/mcp/health`, `vault`) porque **não achei
valor medido no repo e não estava disposto a inventar** — número plausível escrito à mão para
de ser conferido (§5 2026-07-17).

**`last_validated` não foi tocado** (segue `2026-07-17`): não rodei o runbook contra o CT 100
vivo. Carimbar a data de hoje seria exatamente o recibo falso que a seção proíbe.

### As quatro pegadinhas que a seção ancora (nenhuma é minha memória — todas têm fonte)

1. **Curl na raiz do MCP é falso-negativo declarado.** Timeout de 10s não cobre o middleware
   respondendo rota inexistente em ~9s — já se concluiu "fora do ar" com o serviço no ar
   ([handoff 2026-05-15](../handoffs/2026-05-15-2300-ct100-arruma-disco-reindex-baileys-purge.md)).
   O endpoint é `/api/mcp/health`.
2. **`mcp:sync-memory` roda no HOSTINGER, não no CT 100** — é `->environments(['live'])`
   invocado pelo scheduler de produção. Procurá-lo no crontab do CT 100 não acha nada, e
   *"não achei"* viraria diagnóstico errado (§5 2026-07-17: quem roda é pergunta do runtime).
3. **`ct100-jana-evals.sh` sai `exit 1`** se o container `oimpresso-staging` não existir. Ele
   não é o lugar de descobrir que a stack não subiu — o Passo 1 vem antes.
4. **Traefik antes de tudo:** com ele fora, os 4 `curl` dão `000` mesmo com cada serviço
   saudável por dentro.

## 4. Observação fora do escopo — o trend RAGAS já vinha ruim ANTES do outage

Fui conferir a branch órfã `governance/ragas-real-trend` para saber qual semana faltava. Faltam
as do outage (última entrada: **2026-08-23**, 7 semanas no total). Mas o histórico mostra outra
coisa:

| Semana | gate | `context_recall` |
|---|---|---|
| 2026-06-28 | pass | 0.3951 |
| 2026-07-19 | pass | 0.4016 |
| 2026-07-26 | **fail** | 0.3461 |
| 2026-08-02 | skipped | — |
| 2026-08-09 | **fail** | **0.043** |
| 2026-08-16 | **fail** | **0.0314** |
| 2026-08-23 | skipped | — |

**Queda de ~10× em `context_recall`** entre julho e agosto, com três `fail` e dois `skipped` —
tudo isso **anterior** ao outage. Restaurar a cadência dos evals **não** restaura a qualidade:
se a semana nova voltar `fail`, o achado é anterior e é **decisão [W]**, não conserto silencioso
no meio de um retorno. Deixei isso escrito no Passo 6 para que quem executar não confunda
"o cron voltou" com "a Jana está bem".

Não investiguei a causa — está fora do que foi pedido, e o instrumento de investigação
(`jana:ragas-real-eval`) roda no CT 100.

## 5. Também medido, e vale registrar

- **`cron-watchdog.mjs --entrega`** roda **local, sem rede** — funcionou mesmo com o CT 100
  fora. `exit=1`, **2 artefatos parados há 63 dias** (`governance/jana-ragas-baseline.json` e
  `jana-ragas-real-baseline.json`, ambos com data interna `2026-07-01`). O baseline está na
  seção nova: se a lista **crescer** no retorno, o outage derrubou entrega nova.
- **Caí no pipe e me corrigi na hora:** rodei `--entrega | tail` e li `rc=0` — o código era do
  `tail`; o script saía **1**. Está anotado como aviso no Passo 5. É o §5 2026-08-13, e o valor
  aqui é que ele apareceu **dentro do próprio trabalho de medição**.

## 6. O que NÃO fiz, e por quê

| Item da tarefa | Estado | Motivo |
|---|---|---|
| Executar a checklist no retorno | **não feito** | CT 100 não voltou |
| Rodar `ct100-jana-evals.sh` | **não feito** | idem — e o script exige o container de pé |
| Conferir a semana faltante no trend | **parcial** | li o estado atual (última = 08-23); a semana nova só existe depois do eval rodar |
| MCP-first do fechamento (`cycles-active`, `my-work`, `sessions-recent`, `whats-active`) | **não medi** | o MCP **é** o serviço que está fora; nenhuma das tools respondeu |

O fechamento MCP-first não é "esqueci" — é **impossível nesta sessão por causa do próprio
incidente**, e registrar isso é mais honesto que omitir a seção.

---

## Refs

- [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100
- [RUNBOOK-acesso-ct100.md](../requisitos/Infra/RUNBOOK-acesso-ct100.md) — o dono estendido
- [handoff 2026-08-31](../handoffs/2026-08-31-1054-jana-p0-tier0-faxina-e-d0-identidade.md) — registrou "CT 100 em 502 desde 28/ago"
