---
sessao: "11"
titulo: Scorecard · Forja (Aprovacoes, Trabalho, Cockpit)
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 11

## Checklist
1. ✅ Pré-Flight (passo 0): charter presente nas 3 telas — nenhum `PARAR SE` de charter acionado
2. ✅ Nota (passo 1): 3 YAMLs com o slug exato da espec, 16 dimensões, `baseline_anterior` = nota, gaps com `best_of_class` + `fix`
3. ✅ YAML parseia (PyYAML: 16 dims e 4 gaps em cada) · 4. ✅ `prototipo-readiness` rodado · 5. ✅ catraca rodada
6. ✅ só o prefixo tocado — zero `.tsx`, zero charter, zero git

## Notas

| tela | arquétipo | persona | nota | nível | pior dimensão |
|---|---|---|---|---|---|
| `Forja/Aprovacoes/Index` | list (mesa master-detail) | wagner | **81** | Advanced | error_recovery 72 · performance_perceived 72 |
| `Forja/Trabalho/Index` | list | wagner | **77** | Advanced | error_recovery 70 |
| `team-mcp/Forja/Cockpit` | other (shell de abas; pouso = Triagem) | wagner | **77** | Advanced | internal_consistency 70 |

Nota = média das 16 dimensões, peso uniforme (persona `wagner`, power user — `personas-por-modulo.yml` §Notas).

## ⚠️ O que esta nota É e o que NÃO é

**Nota de LEITURA DE CÓDIGO** — `.tsx` + charter + casos + controller. **Sem browser de prod, sem E2E, sem axe, sem smoke** (passos 2-4 fora desta thread). Nenhuma dimensão foi medida em runtime; cada uma cita `arquivo:linha` no bloco `evidencias` do YAML. O `PARAR SE` "a tela não abrir em prod" **não foi verificável** daqui: não abri prod, então ele não foi nem acionado nem descartado.

No Cockpit, a nota cobre o shell `Cockpit.tsx` + a aba de pouso `/forja` (Triagem). As outras 7 abas são componentes filhos não notados aqui.

## Achados que se repetem (e um que é hipótese)

- **Vazio que mente no 1º paint** — Aprovações e Trabalho: o backend defere a lista (`AprovacoesController.php:67`, `TrabalhoController.php:87`), a Page se protege só por default-destructure (allowlist do `InertiaDeferredFrontendGuardTest.php:56-57`) e **não** por `<Deferred>`. Não crasha, mas renderiza o empty state ("Fila zerada" / "Nenhum issue casa com o filtro") antes de a resposta chegar. O Cockpit faz certo (`<Deferred>` por aba).
- **HIPÓTESE, não medida:** `AprovacoesController::decidir` responde `response()->json` (404/409/422/200) a um `router.post` do Inertia. Pela leitura, o `onError` (Index.tsx:274) só recebe erro de validação Inertia — então a mensagem do servidor pode não chegar ao usuário. Precisa de smoke antes de virar achado.
- **Cabeçalho stale** — `Cockpit.tsx:8` ainda declara `copiloto.mcp.usage.all`; o charter (errata 2026-07-27) já registra `jana.mcp.usage.all`.

## Saída das máquinas

`node scripts/qa/prototipo-readiness.mjs` (rc=0):
```
✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): 62
     [Forja] Forja/Aprovacoes/Index
     [Forja] Forja/Roadmap/Gantt
     [Forja] Forja/Trabalho/Index
     [Forja] team-mcp/Forja/Cockpit
🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): 32
Total de telas com protótipo real: 94
```

`node scripts/qa/screen-grades-ratchet.mjs` (rc=0):
```
Catraca screen-grade · 195 telas · ✅ 192 ok/subiu · ✨ 3 novas · 🔻 0 regrediram · 🗑 0 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.
```

## Escopo
`memory/governance/scorecards/screens/{forja-aprovacoes-index,forja-trabalho-index,team-mcp-forja-cockpit}.yaml` + este recibo. Gaps ficam no YAML — nenhum virou task (sem [W]).
