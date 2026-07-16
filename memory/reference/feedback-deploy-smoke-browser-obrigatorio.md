---
name: feedback-deploy-smoke-browser-obrigatorio
description: Após deploy/publicação de tela, smoke REAL no browser é OBRIGATÓRIO antes de declarar "funcionando" — curl/HTTP 302/200 NÃO é smoke, só prova roteamento, não render.
metadata:
  type: feedback
---

**Regra (Wagner, 2026-05-29, textual):** *"depois de publicar tem que testar e garantir estar funcionando. use browser e anote no seu procedimento padrão: testa."*

## Contexto (o erro que originou)

Declarei as telas Triage/Inbox "no ar / live" baseado em **curl retornando 302** (rota redireciona pro login = rota existe). Wagner cobrou, com razão: **302 só prova que a ROTA existe, não que a PÁGINA renderiza.** Uma tela Inertia/React pode deployar e dar **tela branca / erro JS / props quebradas** e ainda assim a rota responder 302/200. Violei **R1 (smoke real ANTES de declarar pronto)** do PROTOCOLO-WAGNER-SEMPRE.

## Why (por que dói)

**Deploy ≠ funcionando.** Precedente real: hotfix #1192 prod tela branca (`auth.can` era objeto não array) — deploy "OK", tela quebrada. Só o **render real no browser** (DOM montado, sem console error, dados visíveis) confirma que funciona. Curl/HTTP status é necessário mas **não suficiente**.

## How to apply (procedimento padrão — SEMPRE após deploy de tela)

1. **Smoke REAL no browser logado** após QUALQUER deploy de tela (Inertia/Blade): abrir a rota e confirmar os 4 sinais:
   - (a) `h1`/título renderiza (não tela branca)
   - (b) sem erro no console JS
   - (c) dados/KPIs aparecem (não skeleton infinito / 0 em tudo)
   - (d) sidebar/shell montou
2. **curl 302/200 NÃO conta como smoke** — só confirma roteamento. NUNCA declarar "funcionando" só com curl.
3. **Se Chrome MCP indisponível** (computer-use read-tier / sem extensão): smoke **colaborativo** — Wagner navega logado, Claude screenshota + verifica os 4 sinais. Login é sempre do Wagner (privacy: Claude não digita senha).
4. **Evidência anexada** ao handoff (screenshot ou os 4 sinais confirmados). Sem evidência de render = não está pronto.
5. Se deploy é **automático** (quick-sync.yml builda na Hostinger pós push main), o smoke continua obrigatório — auto-deploy não dispensa o teste do render.
6. **INCONDICIONAL — nunca perguntar "quer que eu faça o smoke?".** É **sempre-sim**, faz parte do processo, não é uma opção que o Wagner escolhe turno a turno. Perguntar sobre o já-decidido viola a **Lei de Uma Tela §3 MANDATO** (*"decidido → EXECUTO, não pergunto; 'quer que eu…?' sobre o já-decidido = desperdício do tempo do Wagner"*). Reincidência 2026-07-08 (PR #3945 sidebar Suporte): perguntei *"quer que eu agende o smoke ou me chama depois?"* — Wagner textual: *"nemme pergunte mais isso é sempre sim automatize isso"*. O smoke pós-deploy roda **sozinho**, sem HITL de decisão; o único HITL é o **merge** (R10) e, quando o Chrome MCP não estiver disponível, o **login** (passo 3).

## Liga com

- Reforça **R1** ([PROTOCOLO-WAGNER-SEMPRE](PROTOCOLO-WAGNER-SEMPRE.md)) + skills [[incident-done-checklist]] + [[smoke-prod-evidence]] + [[tela-smoke-pos-merge]].
- Pareado com [[feedback-auto-merge-quando-verde]] (merge) — este cobre o passo SEGUINTE (deploy → smoke render).
- Deploy canônico Hostinger: `quick-sync.yml` (git pull + build:inertia no servidor, auto no push main).
