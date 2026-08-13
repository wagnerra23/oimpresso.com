// @ts-check
/**
 * git-history.mjs — DONO ÚNICO do "dá pra confiar na history deste checkout?".
 *
 * ── POR QUE ESTE MÓDULO EXISTE ───────────────────────────────────────────────
 * `actions/checkout@v4` sem `fetch-depth` traz UM commit. Num clone assim,
 * `git log -1 --format=%cs -- <path>` só enxerga o commit da vez, então TODO
 * arquivo volta datado do dia da run — e o número sai sempre plausível, que é
 * exatamente o que torna a mentira invisível. Aconteceu duas vezes em prod:
 *
 *   · 2026-07-08→12  sdd-scorecard — o publish rodava com fetch-depth default.
 *   · 2026-08-12     service-scorecard.json publicou `last_commit: "2026-08-12"`
 *                    em bloco quando a verdade era 08-07 (Jana), 08-05
 *                    (Financeiro), 07-23… O sinal discriminante: em clone
 *                    completo são 13 datas DISTINTAS em 38 de 39 serviços;
 *                    raso daria UMA só. Consertado no PR #5727.
 *
 * A defesa nasceu no `sdd-scorecard` e ficou SÓ lá; o `service-scorecard`
 * passou a importar de lá (#5727) e o `anchor-lint` teve que ESPELHAR o
 * detector, porque o `sdd-scorecard` invoca o `anchor-lint` por `execSync` e o
 * import seria circular. Este módulo é FOLHA de propósito — não importa nada do
 * repo — para que todo mundo possa importar sem ciclo, e para que exista UM
 * detector em vez de seis. Segundo detector do mesmo fato drifta.
 *
 * ── A GRANULARIDADE IMPORTA (não use `--is-shallow-repository` cru) ──────────
 * Aquela flag é GROSSA DEMAIS: `git fetch origin governance/nightly-floor
 * --depth 1` — que o próprio ratchet manda rodar — marca o repo shallow SEM
 * truncar a history do HEAD. Quem usa a flag crua cega sozinho num repo que
 * está perfeitamente medível. Shallow só invalida a medição se algum boundary
 * do `.git/shallow` for ANCESTRAL do HEAD. Erro de git = não-confiável → true.
 *
 * ── O CONTRATO ──────────────────────────────────────────────────────────────
 * Em history truncada, todo derivador aqui devolve "não medido" — NUNCA um
 * valor inventado. Ausência de medição não é ausência de frescor, e dizer
 * "não sei" é honesto; dizer "hoje" é mentira com cara de dado.
 *
 * Refs: proibicoes §5 2026-07-24 (citar data de git log sem conferir se o clone
 *   é raso) · §5 2026-08-03 (consertar UM comprimento da família e não medir os
 *   irmãos) · LC-08 · PR #5727.
 */
import { execSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

/** @type {Map<string, boolean>} cwd → veredito (o detector custa 1-3 execSync) */
const _cache = new Map();

/**
 * Este checkout tem a history truncada a ponto de fabricar medidas?
 *
 * LAZY + memoizado de propósito: vários consumidores nunca chegam a derivar
 * data (o `knowledge-drift --check`, por exemplo, faz ZERO chamadas git — foi
 * medido). Rodar o detector no import cobraria git de quem não precisa.
 *
 * @param {{cwd?:string}} [opts]
 * @returns {boolean} `true` também quando o git falha (não-confiável ⇒ não mede)
 */
export function isShallowHistory({ cwd = process.cwd() } = {}) {
  const hit = _cache.get(cwd);
  if (hit !== undefined) return hit;

  const git = (cmd) => execSync(cmd, { cwd, stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
  let veredito;
  try {
    if (git('git rev-parse --is-shallow-repository') === 'false') {
      veredito = false;
    } else {
      const shallowFile = resolve(cwd, git('git rev-parse --git-path shallow'));
      // marcado shallow sem boundary legível → não dá pra provar que não trunca
      if (!existsSync(shallowFile)) {
        veredito = true;
      } else {
        veredito = false;
        const shas = readFileSync(shallowFile, 'utf8').split('\n').map((s) => s.trim()).filter(Boolean);
        for (const sha of shas) {
          try {
            execSync(`git merge-base --is-ancestor ${sha} HEAD`, { cwd, stdio: 'ignore' });
            veredito = true; // boundary corta a ancestry do HEAD → datas fabricáveis
            break;
          } catch { /* boundary fora da ancestry (órfã nightly-floor) — não trunca */ }
        }
      }
    }
  } catch {
    veredito = true; // git indisponível/erro → não-confiável
  }

  _cache.set(cwd, veredito);
  return veredito;
}

/**
 * Data do último commit que tocou um path (`%cs`, ISO curta) — frescor REAL.
 *
 * @param {string} relPath
 * @param {{raso?:boolean, cwd?:string, ausente?:any}} [opts]
 *   `raso` é injetável SÓ pro bite-test: guard que não pode ser exercitado é
 *   promessa, não defesa (LC-15). `ausente` é o valor de "não medido" — default
 *   `null`, mas o `memory-health` contrata `''`, e mudar o tipo de retorno dele
 *   por baixo seria trocar um bug por outro.
 * @returns {any} a data, ou `ausente` quando não deu pra medir
 */
export function gitLastDate(relPath, { raso, cwd = process.cwd(), ausente = null } = {}) {
  if (raso ?? isShallowHistory({ cwd })) return ausente;
  try {
    const out = execSync(`git log -1 --format=%cs -- "${relPath}"`, {
      cwd, stdio: ['ignore', 'pipe', 'ignore'],
    }).toString().trim();
    return out || ausente;
  } catch { return ausente; }
}

/**
 * Saída crua de um `git log` arbitrário, guardada pela mesma regra.
 *
 * Existe porque a classe não é só "data de 1 arquivo": o `doc-freshness-score`
 * deriva o corpus inteiro de UMA passada `--name-only`, e o
 * `briefing-code-staleness` CONTA commits desde a porta. Em clone raso os dois
 * enxergam um commit só — o primeiro data tudo de hoje, o segundo devolve
 * "0 commits à frente", que lê como "está em dia". Guardar só o `gitLastDate`
 * fecharia meia porta (§5 2026-08-03: medir os irmãos da família).
 *
 * @param {string} cmd comando `git log …` completo
 * @param {{raso?:boolean, cwd?:string, maxBuffer?:number}} [opts]
 * @returns {string|null} stdout, ou `null` quando não deu pra medir
 */
export function gitLogRaw(cmd, { raso, cwd = process.cwd(), maxBuffer = 256 * 1024 * 1024 } = {}) {
  if (raso ?? isShallowHistory({ cwd })) return null;
  try {
    return execSync(cmd, { cwd, stdio: ['ignore', 'pipe', 'ignore'], maxBuffer }).toString();
  } catch { return null; }
}

/** Limpa o memo — só pro self-test; produção mede uma vez por processo. */
export function _resetCacheParaTeste() { _cache.clear(); }
