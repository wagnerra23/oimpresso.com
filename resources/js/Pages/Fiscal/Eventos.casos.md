---
id: resources-js-pages-fiscal-eventos-casos
casos: Eventos Fiscais · /fiscal/eventos
irmaos: Eventos.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado nesta corrida — 3 UC herdam testes da lane required e 1 nasce com teste novo; veredito pendente das lanes"
related_us: [US-FISCAL-007]
---

# Casos de Uso & Aceite — Eventos Fiscais

> Persona: **Eliana [E] (contadora)** — auditoria. A timeline é o registro append-only do que foi feito com cada nota.
>
> **Âncora:** `CU-FISC-05`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `EventosCockpitMultiTenantTest` | `PHP / Pest (NfeBrasil · MySQL)` | ✅ **sim** — required no [baseline](../../../../governance/required-checks-baseline.json) e na allowlist do workflow |
| `GatesPermissaoFiscalTest` (novo) | `Pest Fiscal` (**pula** em SQLite) + suíte noturna CT 100 | ❌ **não** — advisory |

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FEVT-01 | isolamento da timeline | `[must]` `[T0]` | CU-FISC-12 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-02 | timeline é append-only | `[must]` `[reg]` | CU-FISC-05 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-03 | os 7 tipos SEFAZ rotulados | `[must]` | CU-FISC-05 | `EventosCockpitMultiTenantTest` | 🧪 |
| UC-FEVT-04 | gate de acesso à timeline | `[must]` `[T0]` | CU-FISC-13 | `GatesPermissaoFiscalTest` | 🧪 |

---

## UC-FEVT-01 — A timeline nunca mostra evento de outro business `[must]` `[T0]`

**Dado** eventos do business ativo e de outro business
**Quando** a timeline carrega
**Então** só os do business ativo aparecem.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) na superfície mais sensível — a trilha de auditoria de quem cancelou e corrigiu o quê.
- **Teste:** `Modules/Fiscal/Tests/Feature/EventosCockpitMultiTenantTest.php` — `it('UC-FEVT-01 · NfeEvento HasBusinessScope esconde cross-tenant — listagem timeline scoped')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FEVT-02 — Evento registrado não se edita `[must]` `[reg]`

**Dado** um evento SEFAZ já registrado
**Quando** a timeline renderiza
**Então** nenhuma linha oferece edição — o registro é append-only, sem carimbo de atualização.

- **Âncora legal:** CONFAZ SINIEF 07/2005 Art. 14 + LGPD Art. 37 (registro de operações). Corrigir um evento é **emitir outro**, nunca reescrever o anterior.
- **Regressão que defende:** transformar auditoria em rascunho editável.
- **Teste:** `EventosCockpitMultiTenantTest` — `it('UC-FEVT-02 · NfeEvento é append-only (UPDATED_AT = null) — eventos não devem ser editados')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FEVT-03 — Os sete eventos SEFAZ chegam rotulados em português `[must]`

**Dado** eventos de carta de correção, cancelamento, contingência e as quatro manifestações do destinatário
**Quando** a contadora filtra por categoria
**Então** cada tipo é reconhecido, agrupado na categoria certa e exibido com rótulo legível.

- **Regressão que defende:** tipo novo (ou renomeado) cair no rótulo genérico e sumir do filtro — a contadora deixa de ver a categoria inteira sem nenhum erro aparecer.
- **Teste:** `EventosCockpitMultiTenantTest` — `it('UC-FEVT-03 · mapa de TIPOS cobre os 7 códigos SEFAZ canônicos esperados pelo cockpit')`
- **Status:** 🧪 lane **required**; veredito pendente.
- **Fronteira declarada:** inutilização de faixa **não** é evento desta timeline — vive em registro próprio. O código documenta isso explicitamente; não é lacuna.

## UC-FEVT-04 — A timeline exige `fiscal.access` `[must]` `[T0]`

**Dado** um usuário sem `fiscal.access` e sem `superadmin`
**Quando** abre `/fiscal/eventos`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3 + guard em `EventosController@index`.
- **Teste:** `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php` — `it('UC-FEVT-04 · GET /fiscal/eventos aborta 403 sem fiscal.access nem superadmin')`
- **Status:** 🧪 teste nasce nesta corrida; veredito pendente.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] A justificativa exibida é truncada, para não vazar PII do XML** — Dado um evento com justificativa longa · Quando a linha renderiza · Então só um trecho inicial aparece. _Anti-hook do charter (o texto do motivo da SEFAZ pode conter dado pessoal); o corte existe no Controller, sem teste._
- **[BACKLOG · ⬜ sem teste] Filtro por categoria e por janela de tempo** — 7, 30 ou 90 dias, com 30 como padrão. _Existe no Controller; sem teste do resultado._
- **[BACKLOG · ⬜ sem teste] A nota de origem vem junto sem multiplicar consultas** — o charter limita a **um** relacionamento carregado; nenhum teste guarda esse limite.
- **[BACKLOG · 🧪 coberto em outra tela] As ações que GERAM evento** (cancelamento, carta de correção, inutilização, retransmissão, manifestação) têm contrato em [`Nfe.casos.md`](Nfe.casos.md) (`UC-FNFE-04..07`) e [`Dfe.casos.md`](Dfe.casos.md) (`UC-FDFE-03/04`) — o dispatch vive lá, esta tela só **lê** o resultado. Não se duplica UC entre telas irmãs.

## Como rodar a suíte

1. **Lane required:** `PHP / Pest (NfeBrasil · MySQL)` roda `EventosCockpitMultiTenantTest` em todo PR que toque `Modules/Fiscal/Tests/**`.
2. **Advisory + noturna:** `Pest Fiscal` (SQLite, pula) e a suíte noturna CT 100 (MySQL, roda).
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **4 UC** derivados do §6 do SDD. Os 8 itens de backlog que citavam ações de mutação foram **movidos** para as telas donas (`Nfe`/`Dfe`) em vez de duplicados — UC não se repete entre telas irmãs.
