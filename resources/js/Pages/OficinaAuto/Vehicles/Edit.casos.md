---
id: resources-js-pages-oficina-auto-vehicles-edit-casos
casos: Editar veículo do cliente · /oficina-auto/veiculos/{id}/edit
irmaos: Edit.charter.md (lei) · ../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-26"
last_run_ci: "2026-10-26 revalidado: a tela ganhou o campo opcional Próxima revisão (data) — nenhum UC existente muda; o campo é provado por UC-OVREV-02 (Modules/OficinaAuto/Tests/Feature/LembreteRevisaoContratoTest.php, 8 passed no CT 100, cópia isolada do branch). ---- 2026-10-06 revalidado: a tela ganhou o campo opcional Próxima revisão (km) — nenhum UC existente muda; o campo é provado por web cadastro e edição aceitam a próxima revisão (tests/Feature/Sells/AppOsApiContratoTest.php). Antes: 0 UC executado, trio nasceu em 2026-09-23"
related_us: [US-OFICINA-001, US-OFICINA-002]
related_cu: [CU-OFI-02]
---

# Casos de Uso & Aceite — Editar veículo do cliente (Edit)

> **Nasce neste PR** (chip Onda 2 do passo 5, [ADR 0351](../../../../../memory/decisions/0351-sdd-from-source.md)).
> Deriva do §6 [`CU-OFI-02`](../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md)
> e do fluxo **F10** do §5.3 — **não** do `Edit.tsx`. Gêmeo do `Create.casos.md` (mesmo conjunto
> de campos; a diferença é atualizar em vez de criar).
>
> ⚠️ **Piloto LIVE.** Os casos **fotografam** o comportamento vivo.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.
>
> ⚖️ **Força:** lane `PHP / Pest (OficinaAuto · MySQL)` — **ADVISORY**: reprova visível, **não
> bloqueia merge**.

## Rastreabilidade

| UC | O que defende | Peso | Âncora (CU/US) | Teste |
|---|---|---|---|---|
| UC-OVE-01 | corrigir o cadastro persiste | must | CU-OFI-02 · US-OFICINA-001 | `VehicleCrudTest` |
| UC-OVE-02 | editar não apaga a ponte com o sistema antigo | should | CU-OFI-02 · US-OFICINA-002 | `VehicleCrudTest` |
| UC-OVREV-01 | a meta de km avisa o cliente quando o km estimado chega perto | should | US-AUTO-014 · decisão [W] 2026-10-26 | `LembreteRevisaoContratoTest` |
| UC-OVREV-02 | a data da próxima revisão avisa 7 dias antes, só com opt-in, uma vez por meta | should | US-AUTO-014 · decisão [W] 2026-10-26 | `LembreteRevisaoContratoTest` |

---

## UC-OVE-01 · Corrigir o cadastro e o dado ficar corrigido
- **Persona:** atendente que digitou a placa errada, ou que recebeu o chassi depois.
- **Aceite:** Dado um veículo já cadastrado · Quando os campos de identificação são alterados e
  salvos · Então a alteração persiste.
- **Regressão que defende:** edição silenciosa que não grava — o atendente acha que corrigiu e
  a OS sai com o dado velho.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php`
- **Status: 🧪**

## UC-OVE-02 · Editar não apaga a ponte com o sistema antigo
- **Persona:** [W] auditando a migração do cliente legado.
- **Aceite:** Dado um veículo que veio da importação · Quando é editado pela tela · Então o
  identificador de origem **continua lá** — a rastreabilidade com a base legada sobrevive à edição.
- **Regressão que defende:** perder a reconciliação com o sistema antigo na primeira correção de
  cadastro (falha silenciosa, só descoberta meses depois).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php`
- **Status: 🧪**

## UC-OVREV-01 · A meta de km avisa o cliente quando o km estimado chega perto
- **Persona:** dono da oficina que quer o cliente de volta na revisão.
- **Aceite:** Dado um veículo com "Próxima revisão (km)" e duas OS com km em dias diferentes · Quando
  o km estimado de hoje (média diária entre as duas últimas OS × dias desde a última) fica a até 500 km
  da meta · Então o cliente entra na lista do dia. Com uma OS só, ou km que não cresce, não estima.
  Veículo, contato e OS de outra empresa não entram.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/LembreteRevisaoContratoTest.php`
- **Status: 🧪**

## UC-OVREV-02 · A data da próxima revisão avisa 7 dias antes, só com opt-in, uma vez por meta
- **Persona:** cliente que aceitou receber aviso pelo WhatsApp.
- **Aceite:** Dado um veículo com data de próxima revisão · Quando faltam 7 dias ou menos · Então o
  cliente entra na lista, desde que tenha consentido (`whatsapp_consent` sim; vazio ou não, fica de
  fora). Cada meta avisa uma vez; aviso recusado pelo envio não conta; meta nova avisa de novo. Sem o
  envio do WhatsApp ligado, nada sai e nada é registrado.
  A data se informa no campo "Próxima revisão (data)" do cadastro (criar e editar); data inválida
  volta com erro e não grava; apagar o campo tira a meta.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/LembreteRevisaoContratoTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Editar um veículo de outro negócio não é alcançável (o isolamento está provado no
  `Index.casos.md` UC-OVI-02 e no `Show.casos.md` UC-OVS-02; falta o caso específico da edição).
- `[BACKLOG]` A tela de edição tem **paridade exata de campos** com a de cadastro — hoje isso é
  afirmado pelo charter, não por teste.
