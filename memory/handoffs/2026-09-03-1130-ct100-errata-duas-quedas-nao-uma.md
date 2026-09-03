---
date: "2026-09-03"
time: "11:30"
slug: "ct100-errata-duas-quedas-nao-uma"
tldr: "ERRATA do handoff de ontem 20:12: não foi uma queda contínua desde 28/08 — foram DUAS. O PR #6587 (mergeado 02/09 17:35) prova acesso pleno ao CT 100 naquele dia (df -h, docker exec, tools/list). Às 19:59 do mesmo dia ele caiu de novo, e seguia fora em 03/09 11:27. Host que volta e recai em horas é assinatura de cabo/link intermitente, não de host desligado. A seção do runbook foi corrigida em 2 pontos antes de virar PR."
decided_by: []
cycle: null
prs: []
us: []
next_steps:
  - "[W]: a hipótese do CABO ganhou força — a máquina voltou sozinha em 02/09 e recaiu em horas, o que host desligado não faz"
  - "Assim que houver acesso: `journalctl --list-boots | tail -3` fecha a questão (rede × host) em um comando"
  - "Ao fechar QUALQUER retorno do CT 100: registrar a hora da última verificação verde — sem ela a próxima sessão herda 'está no ar' e diagnostica o incidente errado"
related_adrs: ["0062-separacao-runtime-hostinger-ct100", "0130-handoff-append-only-mcp-first"]
---

# ERRATA — foram duas quedas do CT 100, não uma

> Handoff **novo** em vez de edição, porque
> [o de ontem 20:12](2026-09-02-2012-ct100-retorno-pos-outage-preparado.md) é append-only
> ([ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)). O que ele diz continua sendo
> o que se sabia às 20:12; o que mudou é o que se pode saber hoje.

## O que estava errado

O handoff de ontem trabalhou com a premissa herdada de **"CT 100 fora desde 28/08"**, contínuo.
Ao preparar o PR, encontrei o [PR #6587](https://github.com/wagnerra23/oimpresso.com/pull/6587),
mergeado às **17:35 BRT de 02/09** — **antes** das minhas medições, e por uma sessão irmã que eu
não tinha como ver (o MCP, que responderia isso, era o próprio serviço fora).

Ele declara: *"queda do CT 100 de **27/08→02/09 (6 dias)**. Tudo medido em 2026-09-02"*. E as
provas são de **acesso real e repetido**, não de leitura: `df -h /` (volume expandido para 197G,
91G usados), `docker exec` dentro dos containers Alpine, e `tools/list` do MCP paginando **15 de
44** tools.

## O quadro correto

| Janela | Estado |
|---|---|
| 27/08 → 02/09 | queda (6 dias) — o que o handoff de 31/08 registrou como "502 desde 28/ago" |
| 02/09, até ~17:35 BRT | **de pé e plenamente acessível** — SSH, Docker e MCP respondendo |
| 02/09 19:59 → 03/09 11:27 | **fora de novo** — 6 medições minhas, todas com controle positivo |

**Duas quedas separadas por uma janela de horas.**

## O que isso faz com o diagnóstico — e é o ponto para o [W]

A inferência de ontem (`502` às 08:04 → `000` às 19:59, sem CDN na frente, logo *"havia algo
rodando de manhã"*) **apontava na direção certa e ficou pequena**: a máquina não estava
"parcialmente viva", estava **inteira**.

E isso **fortalece muito a hipótese de cabo/link intermitente** sobre a de host desligado: um
host desligado não volta sozinho e recai em poucas horas. É a assinatura que o próprio runbook
já cataloga (§ *CT 100 "sumiu" da rede?* — *"Intermitência: conecta, responde alguns segundos,
cai"*, e *"nós vizinhos caem juntos"*, com `ct100-mcp`, `pve-empresa` e `recorder` os três
offline).

O `journalctl --list-boots | tail -3` continua fechando a questão em **um comando** assim que
houver acesso: se o boot anterior só terminar quando o [W] reiniciar, a máquina esteve ligada o
tempo todo e o problema é rede.

## O que corrigi antes de virar PR

Dois pontos na seção nova do
[RUNBOOK-acesso-ct100.md](../requisitos/Infra/RUNBOOK-acesso-ct100.md):

1. **Passo 1 — disco.** Meu texto mandava conferir citando os **87%** de 2026-07-16. O #6587
   re-mediu no mesmo dia: o volume foi expandido e está em **49%** (91G de 197G). Deixar como
   estava **reintroduziria o ponteiro podre que a sessão irmã acabou de matar** — o número de
   julho segue verdadeiro na data dele, mas a urgência do `prune` não existe mais. Reescrito para
   "confira, sem a urgência de julho".
2. **Fechamento — "voltou" ≠ "estável".** Adicionado o precedente das duas quedas, com a regra
   que ele ensina: **ao fechar um retorno, registrar a hora da última verificação verde**. Sem
   ela, a próxima sessão herda "está no ar" como permanente — que é exatamente como eu herdei
   "fora desde 28/08".

## Lição de método (minha, não do [W])

O erro não foi de medição — minhas 6 sondas estavam certas, com controle positivo em todas. Foi
de **fonte**: tratei a premissa do enunciado como estado verificado, e o estado real estava num
PR mergeado no meio da minha sessão. A defesa canônica (`whats-active`, `sessions-recent`) roda
contra o MCP, que **era o serviço fora** — então ela não estava disponível justamente no
incidente em que ela mais valia.

O que teria pego, e é barato: **`git log origin/main --since` nos paths do meu escopo antes de
escrever**, que não depende de MCP nenhum. Foi o `git log` que pegou, só que na hora do PR em vez
de na hora de escrever.

## Estado MCP no momento do fechamento

**Não consultado — MCP inacessível**, pelo segundo dia. `https://mcp.oimpresso.com` → **HTTP
000** em 03/09 11:27 BRT (controle positivo `oimpresso.com` → **200**). `cycles-active`,
`my-work`, `sessions-recent`, `decisions-search` e `whats-active` rodam contra o CT 100 e nenhuma
responde.

## Refs

- [handoff 2026-09-02 20:12](2026-09-02-2012-ct100-retorno-pos-outage-preparado.md) — o que esta errata corrige
- [session log 2026-09-02](../sessions/2026-09-02-ct100-outage-retorno-runbook.md) — errata também registrada lá, com o raciocínio original preservado
- [PR #6587](https://github.com/wagnerra23/oimpresso.com/pull/6587) — a evidência de acesso em 02/09
- [ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md) · [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md)
