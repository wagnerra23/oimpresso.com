---
id: requisitos-oficina-auto-telas-importer-frota-legada-casos
casos: Importar a frota do cliente migrado (fluxo SEM tela React) · comando artisan
irmaos: ../SDD-tela-ordem-servico-v1.0.md (§5.3 F12 · §6 CU-OFI-18) · ../RUNBOOK-migracao-cliente-legacy.md
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-002]
related_cu: [CU-OFI-18]
---

# Casos de Uso & Aceite — Importar a frota do cliente migrado

> **2ª casa do contrato** (`memory/requisitos/<Mod>/_telas/`): este fluxo **não tem tela React**
> — é comando artisan. O `casos-coverage-guard` varre só `Pages/**`, então um fluxo sem tela não
> teria onde ancorar contrato; a porta `requisitos-status.mjs` lê esta casa também.
>
> Deriva do §6 [`CU-OFI-18`](../SDD-tela-ordem-servico-v1.0.md) e do fluxo **F12** do §5.3.
>
> ⚠️ **Piloto LIVE** — o comando escreve na base de um cliente real. Os casos abaixo **fotografam**
> as guardas que já existem; nenhum pede mudança.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (OficinaAuto · MySQL)` — **ADVISORY** (não está em
> `governance/required-checks-baseline.json`): reprova fica visível e **não bloqueia merge**.

## Rastreabilidade

| UC | O que defende | Peso | Âncora (CU/US) | Teste |
|---|---|---|---|---|
| UC-OIM-01 | importar exige dizer **para qual cliente** | must | CU-OFI-18 · US-OFICINA-002 | `ImportFirebirdMartinhoCommandTest` |
| UC-OIM-02 | ensaio não escreve nada | must | CU-OFI-18 · US-OFICINA-002 | `ImportFirebirdMartinhoCommandTest` |
| UC-OIM-03 | o identificador legado sobrevive à importação | should | CU-OFI-18 · US-OFICINA-002 | `VehicleCrudTest` |

---

## UC-OIM-01 · Importar exige dizer para qual cliente `[T0]`
- **Persona:** [W] / operador de migração.
- **Como usa:** roda o importador apontando o arquivo exportado do sistema antigo do cliente.
- **Aceite:** Dado o comando de importação · Quando é executado **sem** informar o business alvo ·
  Então falha e **nada** é gravado.
- **Regressão que defende:** frota de um cliente cair na base de outro — a pior falha possível
  num importador multi-tenant ([ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ImportFirebirdMartinhoCommandTest.php`
- **Status: 🧪**

## UC-OIM-02 · O ensaio não escreve nada
- **Persona:** operador de migração conferindo antes de valer.
- **Aceite:** Dado um arquivo de importação válido · Quando roda em modo de ensaio · Então o
  relatório sai, mas **nenhum** registro é criado ou alterado.
- **Regressão que defende:** "ensaio" que grava — o operador perde a chance de conferir antes.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ImportFirebirdMartinhoCommandTest.php`
- **Status: 🧪**

## UC-OIM-03 · O identificador legado sobrevive
- **Persona:** [W] auditando a migração meses depois.
- **Aceite:** Dado um veículo criado a partir do sistema antigo · Quando é consultado depois ·
  Então continua carregando o identificador de origem, permitindo reconciliar com a base legada.
- **Regressão que defende:** perder a ponte com o sistema antigo e não conseguir mais provar
  de onde veio cada placa.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleCrudTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Reimportar o mesmo arquivo não duplica veículos (idempotência do importador) —
  comportamento não afirmado por teste hoje.
- `[BACKLOG]` Conciliação de vendas e financeiro do cliente migrado (a parte que o `SPEC.md`
  US-OFICINA-005 declara `_parcial_`).
