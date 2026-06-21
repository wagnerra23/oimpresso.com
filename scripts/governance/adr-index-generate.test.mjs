#!/usr/bin/env node
// Teste de regressao do decoder de titulos YAML !!binary do adr-index-generate
// (Onda 1, gap #1, PR #3056). Caracterizacao do comportamento ja shipado
// (decodeBinaryScalar) -- protege contra regressao no gerador de indice; o RED
// aqui foi o proprio bug dos 56 titulos base64 ilegiveis na fonte unica queryable.
//
// Hermetico: monta um memory/decisions/ temporario com ADRs fixos e roda o gerador
// como subprocesso (cwd=tmp), depois inspeciona o _INDEX-GENERATED.md gerado.
//
// Rodar: node scripts/governance/adr-index-generate.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'adr-index-generate.mjs');
const b64 = (s) => Buffer.from(s, 'utf8').toString('base64');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

// --- fixtures: memory/decisions/ temporario ---
const tmp = mkdtempSync(join(tmpdir(), 'adr-idx-test-'));
const dir = join(tmp, 'memory', 'decisions');
mkdirSync(dir, { recursive: true });

function writeAdr(file, title, binary) {
  const tline = binary ? `title: !!binary ${b64(title)}` : `title: ${title}`;
  writeFileSync(join(dir, file), `---\n${tline}\ntype: adr\nstatus: aceito\n---\n\n# ${file}\n`, 'utf8');
}

// 1. titulo binario arbitrario -> roundtrip identico (base64 ida e volta)
const ROUNDTRIP = 'Maquina de estados canonica FSM e RBAC';
writeAdr('0001-roundtrip.md', ROUNDTRIP, true);

// 2. caso REAL legado: bytes de lixo (0x80 0x94) antes do texto + corpo UTF-8 multibyte
//    (este base64 e de um ADR real; "proprio" tem o e' acentuado -> testa multibyte).
const REAL_LEGACY_B64 = 'gJQgRXN0ZW5kZXIgVWx0aW1hdGVQT1MgZW0gdmV6IGRlIGJ1aWxkIHByw7NwcmlvIG91IGZvcms=';
writeFileSync(
  join(dir, '0002-legado.md'),
  `---\ntitle: !!binary ${REAL_LEGACY_B64}\ntype: adr\nstatus: aceito\n---\n\n# 0002\n`,
  'utf8',
);

// 3. titulo normal (nao-binario) -> passthrough intacto
writeAdr('0003-normal.md', 'Titulo Normal Sem Binario', false);

// 4. dash INTERNO nao pode ser comido (a poda so vale pro prefixo lixo)
writeAdr('0004-dash.md', 'Pre-venda e pos-venda no fluxo', true);

// --- roda o gerador como subprocesso ---
const r = spawnSync('node', [SCRIPT, '--write'], { cwd: tmp, encoding: 'utf8' });
const idx = readFileSync(join(dir, '_INDEX-GENERATED.md'), 'utf8');

check('gerador roda sem erro (exit 0)', r.status === 0);
check('nenhum !!binary sobra no indice', !idx.includes('!!binary'));
check('titulo binario faz roundtrip identico', idx.includes(ROUNDTRIP));
check('lixo de prefixo legado podado + corpo decodificado (0002 real)', idx.includes('Estender UltimatePOS em vez de build'));
check('titulo normal passa intacto', idx.includes('Titulo Normal Sem Binario'));
check('dash interno preservado (nao comido pela poda)', idx.includes('Pre-venda e pos-venda no fluxo'));

// cleanup
rmSync(tmp, { recursive: true, force: true });

console.log('');
if (fails === 0) {
  console.log('[PASS] decoder !!binary integro (6/6).');
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) -- decoder de titulo regrediu.`);
  process.exit(1);
}
