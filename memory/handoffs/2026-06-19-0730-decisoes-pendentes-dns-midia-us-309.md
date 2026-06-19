---
date: "2026-06-19"
time: "0730 BRT"
slug: "decisoes-pendentes-dns-midia-us-309"
tldr: "2 decisões abertas pós-sessão veredito-ledger, pra resolver FRIO. (1) DNS: o endpoint de download de mídia `whatsapp-whatsmeow.oimpresso.com` NÃO resolve (curl 6, no CT100) → ~48k mídias pending desde ~12/jun. Decidir: dobrar no proposal ingestao-perda-zero (já referencia US-WA-311) OU numerar US própria. (2) US-WA-309 (banner 'canal caiu' na Caixa) preservada no session log do #3009 — decidir se formaliza no SPEC (pode já estar feito via branches caixa-banner)."
decided_by: [W]
prs: [3009, 3013]
related_adrs:
  - 0286-channel-health-corroborado-por-mensagem-real
  - 0202-whatsapp-profissionalizacao-baileys-out
  - 0062-separacao-runtime-hostinger-ct100
next_steps:
  - "DECISÃO DNS-mídia: (a) adicionar o achado como seção no proposal whatsapp-ingestao-perda-zero.md, OU (b) numerar uma US própria pro download-de-mídia. Causa-raiz isolada — verificar endpoint correto do WuzAPI + count real."
  - "DECISÃO US-WA-309: o texto da US está no session log do #3009. Se for backlog aberto, formalizar no SPEC com número NÃO-colidente. Checar se as branches caixa-unif-health-banner/banner-businesswide-probe já entregaram."
  - "Já feito: #3013 (fix self-update.sh) mergeado; auto-ativa no próximo git-sync do CT100."
---

# Handoff 2026-06-19 07:30 BRT — 2 decisões pendentes (DNS de mídia + US-WA-309)

> **Pra quem pega isto frio:** veio do fim de uma sessão longa (programa "veredito-ledger" + deploy do MCP + caça à sentinela do self-update). Tudo o que era código foi resolvido e mergeado. Sobraram **2 decisões de produto/backlog** que dependem de contexto/julgamento do [W] — explicadas aqui pra não se perderem.

## Contexto em 1 parágrafo

Ao deployar o MCP no CT 100 (pra ativar o `recusado` consultável), encontrei (a) o **bug da sentinela do self-update** — já corrigido no **#3013** (mergeado) — e (b) **4 user stories de WhatsApp escritas direto no checkout de prod e nunca commitadas** (drift), que preservei no **session log do #3009** (`memory/sessions/2026-06-19-ct100-spec-drift-rescue.md`). Dessas 4: US-WA-308 já está feita (ADR 0286); US-WA-310 do drift COLIDE de número com o US-WA-310 real (Embedded Signup); sobram **309** e **311** como backlog que pode ser real. As 2 decisões abaixo são sobre essas duas.

---

## Decisão 1 — Onde colocar o achado do DNS de mídia (o "US-WA-311")

**O achado (causa-raiz, não suspeita):** rodei do CT 100:
```
$ curl -I https://whatsapp-whatsmeow.oimpresso.com/
curl: (6) Could not resolve host: whatsapp-whatsmeow.oimpresso.com
```
O endpoint de **download de mídia do daemon NÃO RESOLVE** (DNS sumiu/renomeou). É **por isso** que ~**48.514** mensagens estão com `media_download_status='pending'` desde ~12/jun (o drift reportou esse número; era atribuído a "cURL 28 timeout" — na real é DNS quebrado, `cURL 6`). Não é mistério: o host do daemon de download está morto ou foi renomeado.

**Por que NÃO criei uma US nova:** o número `US-WA-311` **já está tomado** — o proposal `memory/decisions/proposals/whatsapp-ingestao-perda-zero.md` o referencia ("backlog perdido da US-WA-311"). Criar `US-WA-311` no SPEC duplicaria (o mesmo erro de colisão do `US-WA-310` do drift). Por isso parei e trouxe pra cá.

**Opções:**
- **(a)** Adicionar o achado de DNS como uma seção "Root cause (2026-06-19)" no proposal `whatsapp-ingestao-perda-zero.md` (que já é dono do "backlog perdido"). *Posso fazer num PR de 1 arquivo se aprovado.* — **recomendado** (não duplica, enriquece o que já existe).
- **(b)** Numerar uma US própria só pro download-de-mídia (ex: o próximo US-WA livre — checar o maior no SPEC), separada da ingestão de webhook.

**Próxima ação concreta de quem pegar:** confirmar o **endpoint correto do WuzAPI** pra download (o `whatsapp-whatsmeow.oimpresso.com` morreu — qual é o vivo?), apontar o worker de download pra ele, e drenar o backlog. Re-confirmar o count via DB (a query direta `127.0.0.1:3306` recusou conexão — usar a connection configurada do app, não PDO cru).

---

## Decisão 2 — Formalizar (ou não) o US-WA-309 (banner "canal caiu")

**O que é:** banner persistente no topo da Caixa Unificada quando algum canal acessível está `channel_health != healthy` ("⚠ Canal X desconectado — religar" + link pra `/atendimento/canais/{id}`). Hoje a Caixa só renderiza banner pra `preview_only`; quando o canal cai (disconnected/banned/degraded) **não mostra nada**.

**Estado:** o texto completo da US está no **session log do #3009**. **Pode já estar feito** — há branches `fix/caixa-unif-health-banner-placement` e `fix/banner-businesswide-probe` que parecem entregar exatamente isso.

**Decisão:** checar se essas branches já mergearam o banner. Se SIM → o US-WA-309 está feito, nada a fazer (só não re-specar). Se NÃO → formalizar no SPEC com número **não-colidente** (não reusar 309 se colidir — checar o maior US-WA no SPEC primeiro).

---

## Ponteiros (tudo o que precisa)
- Session log com o texto verbatim das 4 USs + cruzamento por status: `memory/sessions/2026-06-19-ct100-spec-drift-rescue.md` (#3009).
- Proposal dono do US-WA-311: `memory/decisions/proposals/whatsapp-ingestao-perda-zero.md`.
- Fix da sentinela (já em main): `docker/oimpresso-mcp/scripts/self-update.sh` (#3013) — root cause documentado no commit.
- Acesso ao CT 100 pra re-diagnosticar: `tailscale ssh root@ct100-mcp` (1º comando da sessão pede aprovação por URL — [W] abre).
