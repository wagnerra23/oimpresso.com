#!/usr/bin/env node
// ds-domains-companion.mjs — emite o companion `cockpit_domains.css` do espelho Cowork.
//
// POR QUE EXISTE (decisão 2026-07-10 · CODE_NOTES · PR da Opção A):
//   O mirror `colors_and_type.css` cura de propósito uma superfície LEGÍVEL (fundações + tipo +
//   status + os 4 --kind base). Os tokens de DOMÍNIO (origin/stage/sla/canal/kpi-feature/kind-soft/
//   vip) vivem na camada shell canônica `_generated-cockpit-{light,dark}.css` (gerada do SSOT
//   semantic.tokens.json · ADR 0310/0311). O ds-v6/tokens.css do Cowork só sobrevive porque
//   guarda esses domínios como LITERAIS. Este companion expõe os MESMOS domínios, verbatim do
//   canon, num arquivo que o Cowork faz <link> ao lado do colors_and_type.css → o Cowork consome
//   domínio AO VIVO e os literais do ds-v6 viram deletáveis. Respeita a camada UI-0013:
//   colors_and_type = fundações legíveis; ESTE = camada shell/domínio.
//
// DIFERENTE do ds-mirror-build.mjs: aquele RECONCILIA valores no scaffold curado (não adiciona);
// este EMITE só o subconjunto de domínio, aditivo, num arquivo separado. Nada sobrescreve as
// fundações do colors_and_type.css (filtro exclui accent/bg/border/sb-*/text/radius/shadow/etc).
//
// Determinístico: valores verbatim do git (_generated-cockpit-*, que já são gerados do SSOT);
// tokens ordenados; sem Date/random. Fonte 1:1 com o canon — não reprocessa OKLCH.
//
// Uso:
//   node scripts/design-sync/ds-domains-companion.mjs [tokensDir] > cockpit_domains.css
//   node scripts/design-sync/ds-domains-companion.mjs --write   # grava no mirror-snapshot
//   node scripts/design-sync/ds-domains-companion.mjs --check    # falha se o arquivo gravado difere (CI)
//   tokensDir default = resources/css/tokens

import { readFileSync, writeFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = join(HERE, '..', '..');
const OUT = join(HERE, 'mirror-snapshot', 'cockpit_domains.css');

// Grupos de DOMÍNIO (o que o colors_and_type.css OMITE de propósito). Prefixo-match.
// Inclui `kind-` inteiro (base + -soft): a base repete o valor canon do colors_and_type (dup inócua),
// as -soft preenchem a lacuna. `vip` pega --vip e --vip-soft.
const DOMAIN = ['origin-', 'stage-', 'sla-', 'canal-', 'kpi-feature-', 'kind-', 'vip'];
const isDomain = (name) => DOMAIN.some((p) => name.slice(2).startsWith(p));

const norm = (v) => v.trim().replace(/\s+/g, ' ').replace(/;+$/, '').trim();

// Extrai os --vars de domínio de um arquivo gerado, na ordem de declaração, deduplicado.
function domainVars(file, tokensDir) {
  const css = readFileSync(join(tokensDir, file), 'utf8');
  const out = new Map();
  const re = /(--[a-z0-9-]+)\s*:\s*([^;]+);/gi;
  let m;
  while ((m = re.exec(css)) !== null) {
    if (isDomain(m[1]) && !out.has(m[1])) out.set(m[1], norm(m[2]));
  }
  return out;
}

function build(tokensDir) {
  const light = domainVars('_generated-cockpit-light.css', tokensDir);
  const dark = domainVars('_generated-cockpit-dark.css', tokensDir);
  const line = (map) => [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]))
    .map(([k, v]) => `  ${k}: ${v};`).join('\n');
  return `/* cockpit_domains.css — CAMADA DE DOMÍNIO do DS pro espelho Cowork.
 * GERADO por scripts/design-sync/ds-domains-companion.mjs — NÃO EDITAR À MÃO.
 * Fonte: resources/css/tokens/_generated-cockpit-{light,dark}.css (gerado do SSOT
 * semantic.tokens.json · ADR 0310/0311). Valores VERBATIM do canon.
 *
 * O Cowork faz <link> deste arquivo AO LADO do colors_and_type.css: aquele traz as
 * fundações legíveis, este traz os domínios (origin/stage/sla/canal/kpi-feature/kind-soft/vip)
 * ao vivo do canon — daí o ds-v6/tokens.css do Cowork perde a razão de existir (literais
 * redundantes) e pode ser deletado. Camada UI-0013 respeitada: domínio = shell/.cockpit.
 *
 * ${light.size} tokens light + ${dark.size} dark.
 */
.cockpit {
${line(light)}
}
.cockpit[data-theme="dark"] {
${line(dark)}
}
`;
}

const args = process.argv.slice(2);
const tokensDir = args.find((a) => !a.startsWith('--')) || join(REPO, 'resources', 'css', 'tokens');
const css = build(tokensDir);

if (args.includes('--check')) {
  let cur = '';
  try { cur = readFileSync(OUT, 'utf8'); } catch { /* ausente */ }
  if (cur !== css) {
    process.stderr.write('ds-domains-companion: cockpit_domains.css DESATUALIZADO vs canon — rode com --write e commite.\n');
    process.exit(1);
  }
  process.stderr.write('ds-domains-companion: companion em sincronia com o canon.\n');
} else if (args.includes('--write')) {
  writeFileSync(OUT, css);
  process.stderr.write(`ds-domains-companion: escrito ${OUT}\n`);
} else {
  process.stdout.write(css);
}
