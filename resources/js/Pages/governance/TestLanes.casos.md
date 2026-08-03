---
casos: governance/TestLanes — carimbado do Padrão de Tela
irmaos: TestLanes.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-07-11"
---

# Casos de Uso & Aceite — governance/TestLanes

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. O stub `e2e/governance-test-lanes.spec.ts` já cita `UC-TESTLA-01`.

---

## UC-TESTLA-01 · TODO: o caminho feliz da tela
- **Persona:** Larissa (ROTA LIVRE) — TODO: o que ela quer fazer nesta tela.
- **Aceite:** Dado TODO · Quando TODO · Então TODO (resultado verificável).
- **Teste:** `e2e/governance-test-lanes.spec.ts` — stub `test.fixme` citando `UC-TESTLA-01` (troque por asserção real).
- **Regressão que defende:** TODO — o que não pode voltar a quebrar.
- **Status: ⬜** — stub; vira 🧪/✅ quando o teste executar e passar.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** TODO: próximo caso.

## Trilha do tempo
- 2026-07-11 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
