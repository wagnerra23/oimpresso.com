---
sessao: "12"
titulo: Scorecard · Officeimpresso (Logs)
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 12

## Checklist
1. ✅ Pré-Flight (passo 0) nas 2 telas: charter + casos existem ao lado da `.tsx` — PARAR SE de charter **não** acionado
2. ✅ Nota (passo 1) nas 2 telas, 16 dimensões, cada uma com evidência `arquivo:linha`
3. ✅ Nome exato do slug da espec · `baseline_anterior` = nota · YAML parseia (`js-yaml`: 16 dimensões em cada)
4. ✅ Só o prefixo tocado — zero `.tsx`, zero charter, zero git
5. ⛔ E2E / axe / smoke (passos 2-4) fora, como a espec manda

## Notas

| Tela | Arquétipo | Nota | Nível | Dimensão mais fraca | Arquivo |
|---|---|---|---|---|---|
| `Officeimpresso/Logs/Index` | list (PT-01) | **75** | Advanced | mobile_fit 62 · a11y 70 · perf 70 · consistência 70 | `memory/governance/scorecards/screens/officeimpresso-logs-index.yaml` |
| `Officeimpresso/Logs/Timeline` | detail (PT-07) | **74** | Advanced | error_recovery 66 · 4× em 70 | `memory/governance/scorecards/screens/officeimpresso-logs-timeline.yaml` |

Gaps de maior impacto (ficam no YAML, **não** viraram task — isso é [W]):
- **Index** — `buildMaquinasPayload` faz `->get()` sem limite/paginação (`LicencaLogController.php:291`); tabela hand-roll de 10 colunas sem sort; chips de filtro com glifo `✕` sem aria-label.
- **Timeline** — coluna "Status HTTP" reusa o kind `licenca_no_acesso`: qualquer `>=400` (inclusive 500 de servidor) aparece com o tom de **bloqueada** (`Timeline.tsx:154-158`); Voltar descarta os filtros da lista; `duration_ms` 0 vira "—".

## Declarações honestas

- **A nota é de leitura de código** (`.tsx` + `_components/MaquinasTable.tsx` + charter + casos + `LicencaLogController.php`), **sem browser de prod**. As duas telas estão atrás da flag `useV2OfficeimpressoLogs` (`LicencaLogController.php:29`); se ela está ligada para algum business em prod **não foi medido**. O PARAR SE "tela não abre em prod" portanto **não foi verificado** — nem acionado, nem descartado.
- **Persona `wagner`** é aproximação: `Officeimpresso` não está em `memory/requisitos/_DesignSystem/personas-por-modulo.yml` e os charters não declaram `personas_alvo`; a missão fala em "suporte" (time interno WR2). Registrado em `persona_nota` de cada YAML.
- **Casos** todos 🧪 (sem veredito de CI) — pesou em `preflight_conformance`.

## Máquinas

`node scripts/qa/prototipo-readiness.mjs` (sem `--json`) → **RC=0**. As duas telas entraram em
**✅ PRONTAS pra aplicar HOJE** (61 no total):

```
       [Officeimpresso] Officeimpresso/Logs/Index
       [Officeimpresso] Officeimpresso/Logs/Timeline
```

`node scripts/qa/screen-grades-ratchet.mjs` → **RC=0**:

```
Catraca screen-grade · 194 telas · ✅ 192 ok/subiu · ✨ 2 novas · 🔻 0 regrediram · 🗑 0 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.
```

## Escopo
`memory/governance/scorecards/screens/officeimpresso-logs-index.yaml` ·
`memory/governance/scorecards/screens/officeimpresso-logs-timeline.yaml` · este recibo.
