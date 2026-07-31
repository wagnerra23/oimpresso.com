---
id: resources-js-pages-oficina-auto-vehicles-show-casos
casos: Ficha do veículo · /oficina-auto/veiculos/{id}
irmaos: Show.charter.md (lei) · ../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-017, US-AUTO-003]
related_cu: [CU-OFI-02, CU-OFI-15]
---

# Casos de Uso & Aceite — Ficha do veículo (Show)

> **Nasce neste PR** (chip Onda 2 do passo 5, [ADR 0351](../../../../../memory/decisions/0351-sdd-from-source.md)).
> Deriva do §6 [`CU-OFI-02`/`CU-OFI-15`](../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md)
> e do fluxo **F10** do §5.3 — **não** do `Show.tsx`.
>
> ⚠️ **Piloto LIVE.** Os casos **fotografam** o comportamento vivo. O `SPEC.md` declara
> `US-OFICINA-017` como `_parcial_` — o histórico existe, o "passaporte" completo não. Os casos
> abaixo contratam **o que existe**; o resto está no backlog, sem id.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.
>
> ⚖️ **Força:** lane `PHP / Pest (OficinaAuto · MySQL)` — **ADVISORY**: reprova visível, **não
> bloqueia merge**.

## Rastreabilidade

| UC | O que defende | Peso | Âncora (CU/US) | Teste |
|---|---|---|---|---|
| UC-OVS-01 | a ficha lista as OS daquele veículo | must | CU-OFI-02 · US-OFICINA-017 | `ServiceOrderCrudTest` |
| UC-OVS-02 | ficha de veículo de outro negócio não abre | must | CU-OFI-15 · US-AUTO-003 | `VehicleMultiTenantTest` |

---

## UC-OVS-01 · Ver tudo que já foi feito naquele veículo
- **Persona:** atendente/mecânico antes de orçar — *"esse caminhão já veio por causa disso?"*.
- **Como usa:** abre a ficha do veículo e olha a lista de OS anteriores.
- **Aceite:** Dado um veículo com OS no passado · Quando a ficha é aberta · Então **todas** as OS
  daquele veículo aparecem ligadas a ele.
- **Regressão que defende:** histórico partido (OS não aparece na ficha) — o orçamento é feito no
  escuro e o cliente é cobrado duas vezes pelo mesmo reparo.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php`
- **Status: 🧪**

## UC-OVS-02 · A ficha de um cliente não abre pro outro `[T0]`
- **Persona:** invariante multi-tenant ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Aceite:** Dado um veículo do negócio A · Quando a sessão é do negócio B · Então o veículo
  **não é alcançável** (não resolve).
- **Regressão que defende:** vazar placa, chassi e histórico de serviço de cliente de outra oficina.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Soma de quilometragem entre revisões (o "passaporte" que o `SPEC.md` US-OFICINA-017
  declara faltando).
- `[BACKLOG]` Foto antes/depois por OS na linha do histórico.
- `[BACKLOG]` Exportar o histórico do veículo em PDF.
- `[BACKLOG]` Filtro do histórico por período.
