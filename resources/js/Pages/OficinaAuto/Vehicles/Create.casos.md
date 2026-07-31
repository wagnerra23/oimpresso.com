---
id: resources-js-pages-oficina-auto-vehicles-create-casos
casos: Cadastrar veículo do cliente · /oficina-auto/veiculos/create
irmaos: Create.charter.md (lei) · ../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-001, US-AUTO-001]
related_cu: [CU-OFI-01, CU-OFI-15]
---

# Casos de Uso & Aceite — Cadastrar veículo do cliente (Create)

> **Nasce neste PR** (chip Onda 2 do passo 5, [ADR 0351](../../../../../memory/decisions/0351-sdd-from-source.md)).
> Deriva do §6 [`CU-OFI-01`](../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md)
> e do fluxo **F10** do §5.3 + o `Create.charter.md` v2 — **não** do `Create.tsx`.
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
| UC-OVC-01 | cadastrar pela placa funciona | must | CU-OFI-01 · US-OFICINA-001 | `VehicleCrudTest` |
| UC-OVC-02 | conjunto de duas placas (cavalo + reboque) | must | CU-OFI-01 · US-AUTO-001 | `VehicleCrudTest` |
| UC-OVC-03 | o dono do dado vem da sessão, não do formulário | must | CU-OFI-15 · US-OFICINA-001 | `VehicleMultiTenantTest` |

---

## UC-OVC-01 · Cadastrar o veículo que acabou de chegar
- **Persona:** atendente de balcão, veículo parado na frente.
- **Como usa:** digita a placa e os dados de identificação e salva antes de abrir a OS.
- **Aceite:** Dado os dados de identificação preenchidos · Quando salva · Então o veículo é
  persistido e passa a poder ser escolhido numa OS.
- **Regressão que defende:** cadastro que "salva" e não persiste — o atendente abre a OS sem
  veículo e a OS nasce órfã.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php`
- **Status: 🧪**

## UC-OVC-02 · Um conjunto, duas placas
- **Persona:** atendente de mecânica pesada — o que entra no pátio é **cavalo + reboque**, duas
  placas e dois chassis no mesmo conjunto.
- **Aceite:** Dado um conjunto com placa e chassi secundários · Quando é cadastrado · Então
  **as duas** identificações são preservadas no mesmo registro.
- **Regressão que defende:** perder a segunda placa e não conseguir identificar o que foi
  consertado — regressão direta no caso de uso do piloto.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php`
- **Status: 🧪**

## UC-OVC-03 · O dono do dado não é escolhido pelo formulário `[T0]`
- **Persona:** invariante multi-tenant ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Aceite:** Dado um cadastro de veículo · Quando é criado · Então o negócio dono vem da **sessão
  autenticada**, nunca de um campo enviado pelo cliente.
- **Regressão que defende:** um payload forjado plantar um veículo na base de outra oficina.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` **Consulta de placa auto-preenche os dados técnicos** (`CU-OFI-03` · US-OFICINA-012):
  a prova **já existe** — `tests/Feature/Modules/OficinaAuto/ConsultaPlacaEndpointTest.php` e
  `PlacaLookupServiceTest.php` — mas está **fora da área de escrita deste chip**
  (`Modules/OficinaAuto/Tests/**`), então o UC não foi declarado para não nascer órfão e travar
  o `casos-gate` G-2. Fica como próximo passo nomeado no §10 do SDD.
- `[BACKLOG]` A consulta de placa **não** traz nem guarda dado do proprietário (Non-Goal de
  produto fixado pelo charter v2) — sem teste que afirme a ausência.
- `[BACKLOG]` Erros de validação aparecem em todos os campos e o foco vai pro primeiro inválido.
