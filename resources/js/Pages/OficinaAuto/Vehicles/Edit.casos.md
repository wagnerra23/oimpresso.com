---
id: resources-js-pages-oficina-auto-vehicles-edit-casos
casos: Editar veículo do cliente · /oficina-auto/veiculos/{id}/edit
irmaos: Edit.charter.md (lei) · ../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
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

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Editar um veículo de outro negócio não é alcançável (o isolamento está provado no
  `Index.casos.md` UC-OVI-02 e no `Show.casos.md` UC-OVS-02; falta o caso específico da edição).
- `[BACKLOG]` A tela de edição tem **paridade exata de campos** com a de cadastro — hoje isso é
  afirmado pelo charter, não por teste.
