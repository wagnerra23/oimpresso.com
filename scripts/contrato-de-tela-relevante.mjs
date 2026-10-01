#!/usr/bin/env node
// @ts-check
/**
 * contrato-de-tela-relevante.mjs — decide se o check "Contratos de tela" RODA num diff.
 *
 * Entrada: lista de paths mudados (um por linha) no stdin. Saída: `true` ou `false` no stdout.
 * Chamado por `.github/scripts/contrato-de-tela-detect.sh` (skip-as-pass · ADR 0261).
 *
 * POR QUE EXISTE (medido 2026-10-01): o filtro antigo era um regex escrito à mão com
 * `^resources/js/Pages/.+\.tsx?$`. Duas superfícies que o PRÓPRIO consumidor
 * (`scripts/contrato-de-tela.mjs --contract`) lê ficavam fora dele:
 *   1. telas no módulo dono — `Modules/<X>/Resources/js/Pages/**` (11 alvos de contrato:
 *      Superadmin ×4, Crm ×3, Whatsapp, Connector, Officeimpresso…);
 *   2. alvos fora de Pages — `resources/js/Layouts/AppShellV2.tsx` e
 *      `resources/js/Components/cockpit/*` (contrato `cockpit-sidebar`).
 * Nos últimos 100 PRs mergeados, 4 tocaram tela de módulo e o check pulou; um deles (#8378)
 * mexeu no alvo do contrato `officeimpresso-licencas` e o job saiu `success` com o step dos
 * contratos `skipped`. Gate verde sem ter medido (proibicoes §5, LC-11).
 *
 * O predicado agora é DERIVADO, não listado:
 *   - tela = `isUnderPagesRoot` de `scripts/qa/page-path.mjs` (a mesma fonte de
 *     `raizesDePages`, que já cobre núcleo + módulo e as duas grafias de Resources);
 *   - alvo = os `alvo[]` dos `*.contract.json` versionados, lidos na hora — contrato novo
 *     apontando para qualquer lugar passa a disparar o check sem ninguém editar este arquivo.
 * Os dois só contam arquivo `.ts`/`.tsx`, que é o que o consumidor coleta sob um alvo.
 *
 * Falha alta: erro de leitura/JSON quebra o processo (exit ≠ 0) e o step do detect cai
 * visível — nunca vira `false` em silêncio.
 */
import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join, resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isUnderPagesRoot, normalizeRepoPath } from './qa/page-path.mjs';

const CONTRACTS_DIR = 'governance/design/contracts';

/** Arquivos que mudam o próprio gate (o filtro antigo, preservado + as peças novas). */
const META = /^(?:scripts\/contrato-de-tela(?:-relevante)?(?:\.test)?\.mjs|scripts\/auditar-intencao-fluxo\.mjs|scripts\/adversario-intencao-fluxo\.mjs|scripts\/qa\/page-path\.mjs|governance\/design\/contracts\/.*|Modules\/Whatsapp\/Http\/Controllers\/Admin\/ChannelsController\.php|\.github\/workflows\/contrato-de-tela\.yml|\.github\/scripts\/contrato-de-tela-detect\.sh)$|\.contract\.json$/;

const FONTE = /\.tsx?$/;

/** @param {string} root @returns {string[]} alvos normalizados de todos os contratos versionados. */
export function alvosDosContratos(root) {
  const dir = join(root, CONTRACTS_DIR);
  if (!existsSync(dir)) return [];
  const alvos = [];
  for (const f of readdirSync(dir).filter((n) => n.endsWith('.contract.json')).sort()) {
    const c = JSON.parse(readFileSync(join(dir, f), 'utf8'));
    for (const a of [].concat(c.alvo ?? [])) alvos.push(normalizeRepoPath(a).replace(/\/+$/, ''));
  }
  return alvos;
}

/**
 * @param {string[]} changed paths mudados
 * @param {string[]} alvos alvos dos contratos
 * @returns {{relevante: boolean, motivo: string}}
 */
export function relevante(changed, alvos) {
  for (const raw of changed) {
    const p = normalizeRepoPath(raw.trim());
    if (!p) continue;
    if (META.test(p)) return { relevante: true, motivo: `peça do gate: ${p}` };
    if (!FONTE.test(p)) continue;
    if (isUnderPagesRoot(p)) return { relevante: true, motivo: `tela: ${p}` };
    const alvo = alvos.find((a) => p === a || p.startsWith(`${a}/`));
    if (alvo) return { relevante: true, motivo: `alvo de contrato (${alvo}): ${p}` };
  }
  return { relevante: false, motivo: 'nenhum arquivo de tela, alvo de contrato ou peça do gate' };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
  const changed = readFileSync(0, 'utf8').split('\n');
  const r = relevante(changed, alvosDosContratos(root));
  process.stderr.write(`${r.motivo}\n`);
  process.stdout.write(r.relevante ? 'true\n' : 'false\n');
}
