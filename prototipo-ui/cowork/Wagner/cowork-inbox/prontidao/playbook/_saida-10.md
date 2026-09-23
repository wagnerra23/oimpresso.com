---
sessao: "10"
titulo: Scorecard · Sells/CreateV3
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 10

## Checklist
1. ✅ Pré-Flight: charter existe (`resources/js/Pages/Sells/CreateV3.charter.md`, `status: draft`) → não parou
2. ✅ Nota 16-dim gravada em `memory/governance/scorecards/screens/sells-createv3.yaml` (nome = slug da espec)
3. ✅ `screen: Sells/CreateV3` · `baseline_anterior` = a própria nota · 6 gaps com `best_of_class` + `fix`
4. ✅ YAML parseia (`yaml.safe_load` → 16 dimensões, 6 gaps)
5. ✅ Só o prefixo tocado: nenhuma `.tsx`, nenhum charter, zero git

## Tabela de notas

| tela | arquétipo | persona | nota | nível | medição |
|---|---|---|---|---|---|
| Sells/CreateV3 | form | larissa | **81** | Advanced | leitura de código |

Mais fracas: speed_to_task 72 · mobile_fit 72 · a11y_wcag 72 · internal_consistency 74 · preflight 78.
Mais fortes: i18n_ptbr 92 · microcopy 88 · information_hierarchy 86.

Ponderação: pesos Larissa de `framework-15-dimensoes.md` (total 34) + Pré-Flight com peso 1, que a tabela
não fixa — denominador 35. Declarado no cabeçalho do YAML.

## Divergência declarada com a espec

**PARAR SE "a tela não abrir em prod: não dar nota de olho no código".** Não abri prod: esta thread
rodou sem browser, e o despacho pediu explicitamente nota de leitura de código, declarada como tal.
Por isso o YAML carrega `medicao: leitura-de-codigo` e o cabeçalho diz que E2E/axe/smoke (passos 2-4)
ficaram fora. A rota existe no código (`routes/web.php:811`, `SellsV3Controller@create`, só GET);
**o status HTTP em prod não foi medido**.

## Provas

- `node scripts/qa/prototipo-readiness.mjs` → `Sells/CreateV3` consta em **✅ PRONTAS** (60) e não na
  lista 🟡 (34); total de telas com protótipo real: 94.
- `node scripts/qa/screen-grades-ratchet.mjs` →
  `Catraca screen-grade · 193 telas · ✅ 192 ok/subiu · ✨ 1 novas · 🔻 0 regrediram` · `rc=0`.

## Fora do escopo, visto no caminho
- Charter com conflito aberto (Non-Goal "Não calcula" × cálculo no porte) e Anti-hooks/Contrato
  visual pendentes — decisão de [L]/[W], registrado como gap, não tocado.
- `calculo-item.ts`/`numeros.ts` sem teste em `tests/js` — anotado em `d1_calculo`, não vira task sem [W].
