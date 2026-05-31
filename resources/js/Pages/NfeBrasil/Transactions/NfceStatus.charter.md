---
page: /nfe-brasil/transactions/{tx}/status
component: resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx
owner: wagner
status: draft
last_validated: 2026-05-31
parent_module: NfeBrasil
related_adrs: [0029, 0058, 0062, 0093, 0094, 0143, "UI-0013"]
related_us: [US-NFE-002]
tier: A
charter_version: 2
---

# Page Charter -- /nfe-brasil/transactions/{tx}/status

> **Status:** draft. v2 (2026-05-31) reescreveu a tela 100% no Design System
> (Card/Badge/Button/PageHeader + tokens) e fechou os gaps do board SCREEN-GRADE
> (inline style + oklch hue 240 hardcoded). Non-Goals + Anti-hooks revisados.
> Promocao para status:live aguarda aprovacao Wagner.

---

## Mission

Acompanhar o **status fiscal pos-venda NFC-e** de uma Transaction individual --
tela onde a contadora (persona eliana) / operador POS consulta o resultado da
emissao SEFAZ depois de finalizar a venda. Polling 2s ate o cStat final
(100 = autorizada / >=200 = rejeitada), com fallback Centrifugo CT 100 quando o
broadcast vier (ADR 0058).

---

## Goals -- Features (faz)

- AppShellV2 (layout estatico) + Head "Status NFC-e -- Venda #{tx}" (PT-BR)
- 100% Design System: PageHeader + Card/CardHeader/CardContent + Badge + Button
  + tokens semanticos -- ZERO cor hardcoded
- Status renderizado via Badge com variant semantica (default = autorizada,
  destructive = rejeitada, secondary = processando/aguardando) e icone lucide
- Polling via hook useNfceStatus(transactionId) (HTTP 2s, para no cStat final)
- Estados de carregando e de erro tratados inline (tokens DS, sem oklch)
- Mostra cStat, situacao e chave de acesso (quando autorizada) -- info util pro fiscal
- Acoes contextuais como Button do DS:
  - "Verificar agora" -- refetch manual (complementa o polling silencioso)
  - "Voltar para vendas" -> /sells (rota existente, navegacao clara)
  - "Reemitir nota" -- aparece so quando rejeitada; acao MANUAL humana (confirm)
    que faz POST na rota existente /nfe-brasil/transactions/{tx}/emitir
  - "Baixar DANFE" -- aparece quando autorizada; hoje DESABILITADA (ver Anti-hooks)
- Multi-tenant Tier 0: o endpoint de status filtra por business.id na session (ADR 0093)
- Contrato de props inalterado: a tela recebe apenas transaction_id: number
  (NfeStatusController::showPage)

---

## Non-Goals -- Features (NAO faz)

> Anti-alucinacao. Cada item pode virar Pest GUARD test.

- Listagem de notas (a Index das vendas e outra tela)
- Edicao dos dados da venda (Transaction e read-only no contexto fiscal)
- Cancelamento direto desta tela (passa pelo FSM CancelarVendaCascade ADR 0143)
- Download da DANFE enquanto o id/URL da emissao nao chegar nas props (ver Anti-hooks)
- Polling cross-tenant (cada Transaction tem business_id proprio -- ADR 0093)
- Historico de status anteriores (audit via activity_log, nao na UI aqui)
- Broadcast Centrifugo no Hostinger (CT 100 only -- ADR 0062)

---

## UX Targets

- Persona eliana (contadora): linguagem fiscal correta (NFC-e, DANFE, SEFAZ,
  autorizada/rejeitada, chave de acesso, cStat)
- Polling 2000ms; para automaticamente no cStat final (100 ou >=200)
- 0 erros JS no console
- Cabe em monitor 1280px sem scroll horizontal; container max-w-3xl
- Tipografia/escala herdadas do DS (PageHeader + Card) -- sem px hardcoded
- Cor SOMENTE via variant do Badge/Button + tokens (text-foreground,
  text-muted-foreground, border-border, bg-muted, bg-primary, ...)
- Acoes acessiveis por teclado (Button/Link nativos)

---

## UX Anti-patterns

- Loop de polling infinito sem cap (canon = para no cStat final)
- Cor crua / inline (style com color/background, oklch(...), #hex,
  bg-(blue|sky|zinc|...)-N) -- canon = variant do Badge + tokens
- Modal so pra mostrar status (canon = inline no Card)
- Reemissao SEM confirmacao humana (canon = window.confirm antes do POST)
- Auto-redirect pra /sells apos autorizar (canon = usuario decide)
- Spinner permanente sem feedback de erro (canon = estado de erro inline)

---

## Automation Hooks

- GET /nfe-brasil/transactions/{tx}/status -> NfeStatusController::showPage
  (Inertia render so com transaction_id)
- GET /nfe-brasil/api/transactions/{tx}/nfe-status -> JSON polling
  (consumido por useNfceStatus; retorna cStat, xMotivo, chaveAcesso, ...)
- POST /nfe-brasil/transactions/{tx}/emitir -> reemissao MANUAL (botao "Reemitir
  nota", so quando rejeitada, atras de confirm; throttle 30/min ja no backend)
- Multi-tenant: status filtrado por business.id da session (ADR 0093)
- Transport futuro: Centrifugo channel nfce.business.{biz}.tx.{tx}
  (ADR 0058 -- CT 100 only; Page/Hook nao mudam, so o transport interno)

---

## Automation Anti-hooks

> O que essa tela NUNCA faz.

- NAO dispara emissao/SEFAZ no render (so o EmitirNfceJob background faz);
  o POST de reemissao so ocorre por clique humano + confirm
- NAO dispara emails ao abrir nem ao mudar de status (read-only no render)
- NAO escreve no banco no render (consulta pura via useNfceStatus)
- NAO acessa Transaction de outro business_id (Tier 0 + global scope)
- NAO constroi link de DANFE com dado inexistente: a rota
  GET /nfe-brasil/emissoes/{id}/danfe-pdf e chaveada pelo **id da emissao**,
  que NAO chega nas props (so vem transaction_id). Por isso o botao "Baixar
  DANFE" fica DESABILITADO (com TODO no codigo) ate o backend expor o id/URL da
  emissao -- nada de endpoint inventado.
- NAO loga PII (chave de acesso pode aparecer na UI, mas CPF/razao social do
  cliente nunca em log)
- NAO roda daemon de polling no Hostinger (Centrifugo broadcast = CT 100 ADR 0062)

---

## Refs

- US-NFE-002 -- memory/requisitos/NfeBrasil/SPEC.md -- Emissao NFC-e + status pos-venda
- ADR UI-0013 -- Constituicao UI v2 (4 camadas, tokens)
- ADR 0058 -- Centrifugo CT 100 (transport futuro)
- ADR 0062 -- Hostinger sem daemons
- ADR 0093 -- Multi-tenant Tier 0
- ADR 0143 -- FSM Pipeline (cancel cascade)
- BRIEFING.md -- memory/requisitos/NfeBrasil/BRIEFING.md -- estado consolidado do modulo

---

## Historico

| Data | Autor | Mudanca |
|---|---|---|
| 2026-05-16 | [CC] Wave M boost | Draft criado (auditoria NfeBrasil 71->82, gap D3.c charters 30%). |
| 2026-05-31 | [CC] SCREEN-GRADE boost | v2: tela reescrita 100% DS (Card/Badge/Button/PageHeader + tokens), removidos inline style + oklch hue 240. Adicionadas acoes contextuais (Verificar agora, Reemitir manual via rota existente, DANFE desabilitada pendente backend). Non-Goals/Anti-hooks revisados. Contrato de props (transaction_id) preservado. |
