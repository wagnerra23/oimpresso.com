---
casos: Ponto/Conformidade — Conformidade CLT (/ponto/conformidade)
irmaos: Conformidade.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o escopo e as regras não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "2026-09-28"
---

# Casos de Uso & Aceite — Ponto/Conformidade

> Os UC-CONF-01..03 vêm do casos.md do protótipo (`prototipo-ui/cowork/Wagner/resources/js/Pages/Ponto/Conformidade.casos.md`);
> os demais, das Leis da thread 05 + ADR 0413 D0 + Tier 0 — **nunca do .tsx** (§5 2026-06-05).
> Teste: `Modules/Ponto/Tests/Feature/ConformidadeContratoTest.php` (lane `ponto-pest.yml`).
> **Status:** ✅ passa · 🧪 teste cita o UC, veredito pendente · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-CONF-01 | Cada apontamento cita a lei e os números | must | ConformidadeContratoTest | 🧪 |
| UC-CONF-02 | Limite vem do config | must | ConformidadeContratoTest | 🧪 |
| UC-CONF-04 | Tenant de teste não enxerga o tenant 99 | must | ConformidadeContratoTest | 🧪 |
| UC-CONF-05 | Ativo sem PIS listado; desligado e com PIS não | must | ConformidadeContratoTest | 🧪 |
| UC-CONF-06 | NSR sai "não medido", nunca zero | must | ConformidadeContratoTest | 🧪 |
| UC-CONF-07 | Somente leitura | must | ConformidadeContratoTest | 🧪 |
| UC-CONF-08 | A tela abre pela rota com o painel deferido | must | ConformidadeContratoTest | 🧪 |
| UC-CONF-09 | Sem dado, a nota explica por quê | must | ConformidadeContratoTest | 🧪 |

---

## UC-CONF-01 · Cada apontamento cita a lei e os números · `must`
- **Aceite:** Dado intrajornada de 35 min · Quando o painel abre · Então mostra Art. 71, apurado 0h35, limite 1h00, colaborador e dia.
- **Status: 🧪** — 6 passed no CT 100 em 2026-09-25; veredito da lane pendente.

## UC-CONF-02 · Limite vem do config · `must`
- **Aceite:** Dado `intrajornada_minima_minutos` = 45 · Quando o painel abre · Então o limite exibido é 0h45.
- **Status: 🧪**

## UC-CONF-04 · Tenant de teste não enxerga o tenant 99 · `must`
- **Aceite:** Dado violação no tenant 99 · Quando o tenant de teste abre o painel · Então o caso do 99 não aparece e o próprio aparece.
- **Status: 🧪** — bite-test: só cai quando as **duas** defesas (`where` na apuração e no colaborador) são removidas.

## UC-CONF-05 · Colaborador ativo sem PIS · `must`
- **Aceite:** Dado ativo sem PIS, desligado sem PIS e ativo com PIS · Então só o primeiro é listado.
- **Status: 🧪**

## UC-CONF-06 · NSR sai "não medido" · `must`
- **Aceite:** Quando o painel abre · Então `nsr.medido = false` e `total = null` (a tela mostra "—").
- **Status: 🧪**

## UC-CONF-07 · Somente leitura · `must`
- **Aceite:** Quando o painel é calculado · Então nenhuma linha é gravada (ADR 0413 D0).
- **Status: 🧪**

## UC-CONF-08 · A tela abre pela rota com o painel deferido · `must`
- **Aceite:** Dado usuário com `ponto.access` · Quando GET `/ponto/conformidade?mes=AAAA-MM` · Então renderiza `Ponto/Conformidade` com `mes` e, no partial reload, `painel` com as 6 verificações.
- **Status: 🧪**

## UC-CONF-09 · Sem dado, a nota explica por quê · `must`
- **Fonte:** charter do protótipo (`prototipo-ui/cowork/Wagner/resources/js/Pages/Ponto/Conformidade.charter.md`) — *"Nenhum número inventado: sem dado, empty state que explica por quê"*. Achado do smoke em prod biz=1 de 2026-09-26: com 0 colaboradores controlados a nota dizia "Nenhuma violação apurada", lida como competência limpa.
- **Aceite:** Dado 0 colaboradores com ponto controlado · Quando o painel abre · Então `cobertura.estado = sem_colaboradores` e a nota diz "Nenhum colaborador com controle de ponto". Dado colaborador controlado e nenhum dia apurado na competência · Então `sem_apuracao`, a nota diz "Competência sem apuração" e os KPIs da apuração saem "—" — o cabeçalho do card também diz "sem apuração", nunca "0 casos". Dado ≥1 dia apurado sem violação · Então `apurado` e a nota "Nenhuma violação apurada" volta a valer. Desligado antes da competência e colaborador sem controle de ponto não contam.
- **Status: 🧪**

## Backlog de casos (sem id)

- **[BACKLOG]** Contagem casa com o Fechamento (UC-CONF-03 do protótipo): dado N violações duras, a pré-checagem do Fechamento usa o mesmo N — chamando `ConformidadeService`, não recontando. Entra com id quando a thread 04 existir.
- **[BACKLOG]** Item "Conformidade CLT" no menu do Ponto leva à tela (alcance).

## Trilha do tempo
- 2026-09-28 · contrato visual (`tests/Browser/visreg-screens.json`, foto do estado `sem_apuracao`) + cabeçalho do card "sem apuração" no lugar de "0 casos". Casos revalidados contra a tela. Refs: ADR 0411.
- 2026-09-28 · UC-CONF-09 — cobertura da competência (colaboradores controlados · dias apurados) distingue os 3 estados da nota. Refs: US-PONTO-016.
- 2026-09-25 · [CL] thread 05 — trio a partir do `criar-tela.mjs` + casos do protótipo. Refs: ADR 0413 D0 · US-PONTO-016.
