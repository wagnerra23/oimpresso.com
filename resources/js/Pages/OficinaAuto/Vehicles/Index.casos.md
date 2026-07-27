---
id: resources-js-pages-oficina-auto-vehicles-index-casos
casos: Frota dos clientes · /oficina-auto/veiculos
irmaos: Index.charter.md (lei) · ../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-001, US-AUTO-001]
related_cu: [CU-OFI-02, CU-OFI-15]
---

# Casos de Uso & Aceite — Frota dos clientes (Index)

> **Nasce neste PR** (chip Onda 2 do passo 5, [ADR 0351](../../../../../memory/decisions/0351-sdd-from-source.md)).
> Deriva do §6 [`CU-OFI-02`/`CU-OFI-15`](../../../../../memory/requisitos/OficinaAuto/SDD-tela-ordem-servico-v1.0.md)
> e do fluxo **F10** do §5.3 — **não** do `Index.tsx`.
>
> ⚠️ **Piloto LIVE** (Martinho, ~91 veículos reais). Os casos **fotografam** o comportamento vivo.
>
> 📌 **Escopo desta tela:** a frota é **dos clientes** da oficina (o caminhão que entra pra
> conserto), não frota própria — é o que o `Index.charter.md` fixa.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.
> **O veredito é da lane** (G-7).
>
> ⚖️ **Força:** lane `PHP / Pest (OficinaAuto · MySQL)` — **ADVISORY**: reprova fica visível e
> **não bloqueia merge**.

## Rastreabilidade

| UC | O que defende | Peso | Âncora (CU/US) | Teste |
|---|---|---|---|---|
| UC-OVI-01 | a lista mostra a frota do meu negócio | must | CU-OFI-02 · US-OFICINA-001 | `VehicleCrudTest` |
| UC-OVI-02 | veículo de outro negócio não aparece | must | CU-OFI-15 · US-AUTO-001 | `VehicleMultiTenantTest` |
| UC-OVI-03 | remover um veículo preserva o histórico | must | CU-OFI-02 · US-OFICINA-001 | `VehicleCrudTest` |

---

## UC-OVI-01 · Ver a frota que passa pela oficina
- **Persona:** atendente de balcão (antes de abrir a OS, confere se o veículo já está cadastrado).
- **Como usa:** abre a lista de veículos e procura pela placa.
- **Aceite:** Dado veículos cadastrados no negócio atual · Quando a lista é consultada · Então
  os veículos **daquele** negócio aparecem.
- **Regressão que defende:** lista vazia por scope mal aplicado — o atendente recadastra um
  veículo que já existe e o histórico de OS se parte em dois.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php`
- **Status: 🧪**

## UC-OVI-02 · A frota de um cliente não aparece pro outro `[T0]`
- **Persona:** invariante multi-tenant ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Aceite:** Dado veículos do negócio A · Quando a sessão é do negócio B · Então **nenhum** deles
  é listado.
- **Regressão que defende:** vazamento de placa/chassi entre oficinas — dado de cliente terceiro.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php`
- **Status: 🧪**

## UC-OVI-03 · Remover da lista não apaga a história
- **Persona:** atendente limpando cadastro antigo.
- **Aceite:** Dado um veículo com OS no passado · Quando é removido pela tela · Então ele sai da
  lista **mas o registro é preservado** (remoção reversível), de modo que as OS antigas continuam
  íntegras.
- **Regressão que defende:** apagar de verdade e deixar OS órfã — perda de histórico de serviço,
  que é o ativo do módulo.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Os filtros da lista (tipo, placa parcial, dono) devolvem o subconjunto certo — hoje
  sem teste que afirme o contrato dos filtros.
- `[BACKLOG]` A coluna de OS abertas por veículo chega por carga adiada (prop cara) e não trava a
  primeira pintura.
- `[BACKLOG]` A placa é apresentada no padrão visual brasileiro (diferencial de UX registrado pelo
  charter após feedback do cliente) — sem prova automatizada.
