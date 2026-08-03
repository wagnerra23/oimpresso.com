#!/usr/bin/env node
// @ts-check
/**
 * system-map-deadlinks.test.mjs — bite-test do validador de path morto do system-map.
 *
 * POR QUE EXISTE: a regra "o gerador NUNCA emite path que não resolve" (Wagner
 * 2026-07-12) é boa e continua valendo — ela matou o ponteiro inventado
 * `Modules/Project`. Mas ela não distinguia PONTEIRO ("vá aqui") de TRANSCRIÇÃO
 * (título de lápide §5 copiado de proibicoes.md). Uma lápide cita `Modules/SRS`
 * PORQUE o módulo foi deletado — e o fail-closed nisso significa que toda deleção
 * de módulo mata o gerador.
 *
 * Foi o que aconteceu, com recibo: 4 runs agendadas seguidas do `system-map.yml`
 * falharam (30/07, 31/07, 01/08, 02/08 de 2026) com "PATH MORTO ... (inline)
 * Modules/SRS" — deletado por design em #5036 —, depois somando `Modules/ADS`.
 * O PAINEL-SISTEMA.md congelou no regen de 28/07 (#4938).
 *
 * Este teste prova as DUAS pernas, senão a exceção vira buraco:
 *   · LIBERA — path morto DENTRO do bloco transcrito (é o caso legítimo).
 *   · MORDE  — o MESMO path morto FORA do bloco (a regra original segue viva).
 *   · MORDE  — link markdown morto mesmo DENTRO do bloco (escopo mínimo: a
 *              exceção cobre só path inline; link markdown nunca foi o caso).
 *   · MORDE  — marcador aberto e não fechado NÃO engole o resto do doc.
 *
 * Determinístico: strings inline; o único I/O é existsSync sobre paths reais do
 * repo (um que existe, um que foi deletado de propósito).
 * Uso: node scripts/governance/system-map-deadlinks.test.mjs
 */
import { deadLinks } from './system-map.mjs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { existsSync } from 'node:fs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const OUT = join(ROOT, 'memory', 'reference', 'PAINEL-SISTEMA.md');

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };
const temInline = (md, alvo) => deadLinks(md, OUT).some((d) => d === `(inline) ${alvo}`);

console.log('\n  system-map · path morto — transcrição ≠ ponteiro\n');

// Pré-condição: sem ela o teste mede não-execução (verde por vácuo, §5 2026-07-24).
const VIVO = 'scripts/governance/system-map.mjs';
const MORTO = 'Modules/SRS'; // deletado de propósito em #5036 (deprecação concluída)
ok(existsSync(join(ROOT, VIVO)), `pré-condição: \`${VIVO}\` existe no repo`);
ok(!existsSync(join(ROOT, MORTO)), `pré-condição: \`${MORTO}\` NÃO existe (deletado por design)`);

// ── A) LIBERA: o caso real — lápide §5 citando módulo deletado ────────────────
{
  const md = [
    '## Ideias avaliadas e ABANDONADAS (§5 — não re-propor)',
    '',
    '<!-- transcrito-de: memory/proibicoes.md §5 -->',
    `- ~~2026-07-29 — Ressuscitar \`${MORTO}\` (ex-MemCofre) ou recriar suas 7 tabelas~~`,
    '<!-- /transcrito-de -->',
  ].join('\n');
  ok(!temInline(md, MORTO), 'LIBERA: path deletado citado DENTRO do bloco transcrito');
}

// ── B) MORDE: a regra original continua viva fora do bloco ───────────────────
{
  const md = `- Onde o código mora: \`${MORTO}\` (features por vertical).`;
  ok(temInline(md, MORTO), 'MORDE: o MESMO path morto FORA do bloco (ponteiro inventado)');
}

// ── C) MORDE: escopo mínimo — a exceção não cobre link markdown ──────────────
{
  const md = [
    '<!-- transcrito-de: memory/proibicoes.md §5 -->',
    '- ~~lápide com [ponteiro](../requisitos/NaoExiste/SPEC.md) markdown~~',
    '<!-- /transcrito-de -->',
  ].join('\n');
  ok(deadLinks(md, OUT).includes('../requisitos/NaoExiste/SPEC.md'),
    'MORDE: link markdown morto, mesmo dentro do bloco transcrito');
}

// ── D) MORDE: marcador sem fechamento não vira coringa ───────────────────────
{
  const md = [
    '<!-- transcrito-de: memory/proibicoes.md §5 -->',
    `- ~~lápide citando \`${MORTO}\`~~`,
    '', // sem <!-- /transcrito-de -->
    `- Ponteiro de verdade: \`${MORTO}\``,
  ].join('\n');
  ok(temInline(md, MORTO), 'MORDE: bloco aberto e NÃO fechado não engole o resto do doc');
}

// ── E) LIBERA: não falsa-positiva em path vivo ───────────────────────────────
{
  const md = `- Fonte dona: \`${VIVO}\`.`;
  ok(deadLinks(md, OUT).length === 0, 'LIBERA: path vivo fora do bloco não é acusado');
}

console.log(fails === 0 ? '\n  OK — transcrição libera, ponteiro morde.\n' : `\n  ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);
