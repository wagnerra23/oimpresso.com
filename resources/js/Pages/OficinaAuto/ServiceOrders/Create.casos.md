---
id: resources-js-pages-oficina-auto-service-orders-create-casos
casos: Abrir OS · /oficina-auto/service-orders/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado neste PR — os 4 UC já existiam; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-001, US-OFICINA-003, US-AUTO-005]
related_cu: [CU-OFI-04, CU-OFI-07, CU-OFI-15, CU-OFI-19]
---

# Casos de Uso & Aceite — Abrir OS (Create)

> **Contrato 🧪 (régua por tela · Onda 0b/ADR 0320).** Cada UC cita um teste que já existe (G-2).
> **Status 🧪** (em prova) porque o teste é Pest e ainda não está no manifesto de vereditos — subir
> pra ✅ exige um run coletado no CT100. Mesmo estado do golden `Sells/Create`.
>
> **Status:** ✅ passa (manifesto) · 🧪 em prova (teste cita o UC, sem veredito) · ⬜ não verificado · ❌ quebrou.

---

## UC-OCR-01 · Abrir OS com veículo existente redireciona pro Show
- **Persona:** atendente (veículo no balcão).
- **Aceite:** Dado um veículo cadastrado · Quando abre a OS (POST) · Então persiste e redireciona pro Show.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php`
- **Status: 🧪**

## UC-OCR-02 · order_type rejeita 'locacao' (erradicação ADR 0265)
- **Persona:** invariante de domínio (Oficina = reparo, não locação).
- **Aceite:** Dado uma tentativa de abrir OS com `order_type=locacao` · Quando submete · Então o backend rejeita (`in:manutencao,mecanica`, ADR 0265).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/OficinaFioUsavelAdr0265Test.php`
- **Status: 🧪**

## UC-OCR-03 · OS nasce em status 'aberta' (FSM canon)
- **Persona:** invariante de processo.
- **Aceite:** Dado uma OS recém-aberta · Quando é criada · Então nasce `aberta`; status não é campo do form (quem move é o FSM, ADR 0143/0265).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php`
- **Status: 🧪**

## UC-OCR-04 · vehicle_id/contact_id de outro business rejeitado (Tier 0)
- **Persona:** invariante de segurança.
- **Aceite:** Dado vehicle/contact do business B · Quando um usuário do business A tenta vincular na criação · Então rejeitado server-side (ADR 0093).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php`
- **Status: 🧪**

---

## Rastreabilidade (âncora no SDD §6 · [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md))

| UC | Peso | Âncora (CU/US) |
|---|---|---|
| UC-OCR-01 | must | CU-OFI-04 · US-OFICINA-001 · US-AUTO-005 |
| UC-OCR-02 | must `[T0]` | CU-OFI-19 · US-OFICINA-001 |
| UC-OCR-03 | must | CU-OFI-07 · US-OFICINA-003 |
| UC-OCR-04 | must `[T0]` | CU-OFI-15 · US-OFICINA-001 |

> `UC-OCR-02` é o caso que **prova a erradicação de domínio** da
> [ADR 0265](../../../../memory/decisions/0265-oficina-reparo-erradica-locacao.md): a oficina é
> reparo, e o backend recusa o tipo de ordem que não pertence a esse domínio. É o contrato
> executável do que o dicionário `memory/dominio/oficina-auto.md` declara.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (OficinaAuto · MySQL)`, criada em 2026-07-27 —
> **ADVISORY**: reprova visível, **não bloqueia merge**.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Autocomplete de veículo por placa (Mercosul + legacy)** — comportamento de UI/busca; contratar quando houver teste que cite o id.
- **[BACKLOG] Check-in de entrada (combustível/avarias)** — coberto parcialmente por `ServiceOrderCheckinTest`; contratar como UC quando a asserção citar o id.

## Como rodar a suíte
- **Pest (CT 100):** `docker exec oimpresso-staging php artisan test --filter=ServiceOrderCrud` (Tier 0: CT 100, nunca local).
- **Cadência:** rodar ao fim de toda mexida na tela. ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-03 · [CC] contrato inicial (4 UCs 🧪) a partir do charter + Pest existente (régua por tela Onda 0b). UC sobe pra ✅ quando um run CT100 alimentar o manifesto.
