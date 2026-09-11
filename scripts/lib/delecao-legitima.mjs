// @ts-check
/**
 * delecao-legitima.mjs — "o item sumiu: foi REMOÇÃO legítima ou FUGA da catraca?"
 *
 * Fonte ÚNICA da regra, consumida por catracas cujo universo vem do LADO DO PR:
 *   - `scripts/qa/screen-grades-ratchet.mjs`  (nota de tela · scorecard sumiu)
 *   - `scripts/qa/screen-coverage-map.mjs`    (cobertura de tela · charter/e2e/a11y/scorecard sumiu)
 *
 * POR QUE VIVE AQUI e não no ratchet que a criou (movida em 2026-09-09, corpo intacto): o
 * ratchet EXECUTA a catraca no top-level, então importar a função dele rodaria um gate inteiro
 * — inclusive o `process.exit(1)` dele — dentro do outro. Copiar seria a 2ª cópia da mesma
 * regra, que é a doença que `uc-regex.mjs` documenta com 4 drifts. Mesma escolha que já
 * produziu `charter-signal.mjs` e `uc-regex.mjs`.
 *
 * O FP foi medido no ratchet antes de armar (regra "LIGUE A MÁQUINA" item 4): 258 deleções de
 * scorecard, 258 com o `.tsx` morto junto ⇒ 0 falso-positivo.
 */

/**
 * Vetor 2 — DELEÇÃO (o buraco medido em 2026-08-10, §5 "Catraca que itera o LADO DO PR").
 *
 * O laço principal itera `readdirSync` do PR: arquivo deletado NUNCA entra nele,
 * então apagar um scorecard passava por baixo da catraca. A promessa "robusto
 * contra burla" do cabeçalho cobria só o vetor de BAIXAR `baseline_anterior`.
 *
 * A distinção NÃO é heurística — o scorecard declara `path:`:
 *   - sumiu o YAML **e** o `.tsx` daquele path também  → tela removida, LEGÍTIMO (cala)
 *   - sumiu o YAML **e** o `.tsx` continua vivo         → vetor de fuga (acusa)
 *
 * FP medido no histórico completo antes de armar (regra "LIGUE A MÁQUINA" item 4):
 * 258 deleções de scorecard, 258 com o `.tsx` morto junto ⇒ **0 falso-positivo**.
 * (E 0 verdadeiro-positivo: o vetor nunca foi usado — isto é defesa preventiva.)
 *
 * NÚCLEO PURO + injeção, pra o selftest poder exercitar sem git. Mas assert sobre
 * helper puro NÃO prova o pipeline (§5 2026-07-30) — por isso o `--selftest`
 * também roda um bite-test E2E contra um repo git de verdade.
 */
export function classificarDelecoes({ naBase, noPr, pathDe, tsxVivo }) {
  const noPrSet = new Set(noPr);
  const fuga = [];
  let legitimas = 0;
  let semPath = 0;
  for (const f of naBase) {
    if (noPrSet.has(f)) continue; // não foi deletado
    const p = pathDe(f);
    if (!p) { semPath++; continue; } // sem `path:` declarado → não dá pra decidir; não acusa
    if (tsxVivo(p)) fuga.push({ file: f, path: p });
    else legitimas++;
  }
  return { fuga, legitimas, semPath };
}
