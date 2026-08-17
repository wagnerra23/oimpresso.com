#!/usr/bin/env node
// @ts-check
/**
 * aplicar-payload.test.mjs — selftest do applier de payload do espelho Cowork.
 *
 * Roda o CLI DE FORA, num sandbox por cwd, com payload sintético. Não testa helper
 * exportado: o §5 2026-07-30 registra que assert sobre função-satélite fica verde
 * enquanto o pipeline regride — o bite tem que atravessar o chokepoint real.
 *
 * Cobre o que o applier PROMETE:
 *   1. escreve fiel e conta certo (NOVO / ATUALIZADO / inalterado)
 *   2. --dry não escreve
 *   3. recusa path que sai do espelho (../) — trava de escopo
 *   4. recusa .md dentro de cowork/ — R1 do cowork-ssot-guard
 *   5. recusa bytes divergente — corrupção de transporte
 *   6. AVISA em perda líquida de linhas — o caso qa-conformance.js (espelho À FRENTE),
 *      que sem aviso vira regressão silenciosa
 *   7. o alerta NÃO dispara em sync legítimo que cresce — controle negativo
 *
 * Uso: node scripts/design-sync/aplicar-payload.test.mjs
 */
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';

const APPLIER = resolve('scripts/design-sync/aplicar-payload.mjs');
let fails = 0;
const check = (nome, ok, detalhe = '') => {
  console.log(`[${ok ? 'OK' : 'FAIL'}] ${nome}${ok ? '' : '  → ' + detalhe}`);
  if (!ok) fails++;
};

/** sandbox: cwd próprio com `prototipo-ui/cowork/`, pra nunca tocar o repo real */
function sandbox(arquivosIniciais = {}) {
  const dir = mkdtempSync(join(tmpdir(), 'aplicar-payload-'));
  mkdirSync(join(dir, 'prototipo-ui', 'cowork'), { recursive: true });
  for (const [rel, txt] of Object.entries(arquivosIniciais)) {
    writeFileSync(join(dir, 'prototipo-ui', 'cowork', rel), txt, 'utf8');
  }
  return dir;
}
function payload(dir, files) {
  const p = join(dir, 'payload.json');
  writeFileSync(p, JSON.stringify({
    schema: 'test', generatedAt: '2026-08-17T00:00:00Z', source: 'selftest',
    totalBytes: files.reduce((a, f) => a + Buffer.byteLength(f.content, 'utf8'), 0),
    missing: [],
    files: files.map((f) => ({ bytes: Buffer.byteLength(f.content, 'utf8'), ...f })),
  }), 'utf8');
  return p;
}
function rodar(dir, pay, args = []) {
  try {
    const out = execFileSync('node', [APPLIER, pay, ...args], { cwd: dir, encoding: 'utf8' });
    return { code: 0, out };
  } catch (e) { return { code: e.status ?? 1, out: (e.stdout || '') + (e.stderr || '') }; }
}

// ── 1 + 2: escreve fiel, conta certo, e --dry não escreve ────────────────────
{
  const dir = sandbox({ 'a.jsx': 'velho\n' });
  const pay = payload(dir, [{ path: 'a.jsx', content: 'novo\n' }, { path: 'b.css', content: 'x\n' }]);
  const seco = rodar(dir, pay, ['--dry']);
  check('--dry NÃO escreve', readFileSync(join(dir, 'prototipo-ui/cowork/a.jsx'), 'utf8') === 'velho\n', seco.out);
  check('--dry conta 1 atualizado + 1 novo', /1 atualizado\(s\) · 1 novo\(s\)/.test(seco.out), seco.out);

  const r = rodar(dir, pay);
  check('aplica: conteúdo fica BYTE-idêntico ao payload',
    readFileSync(join(dir, 'prototipo-ui/cowork/a.jsx'), 'utf8') === 'novo\n' &&
    readFileSync(join(dir, 'prototipo-ui/cowork/b.css'), 'utf8') === 'x\n', r.out);
  check('aplica: rc=0 quando nada é recusado', r.code === 0, 'rc=' + r.code);

  const r2 = rodar(dir, pay);
  check('idempotente: 2ª aplicação = 2 inalterados', /0 atualizado\(s\) · 0 novo\(s\) · 2 inalterado/.test(r2.out), r2.out);
}

// ── 3: trava de escopo (path que sai do espelho) ─────────────────────────────
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: '../../fora.txt', content: 'nao deveria existir\n' }]);
  const r = rodar(dir, pay);
  check('BITE escopo: recusa path que SAI do espelho', /RECUSADO/.test(r.out) && r.code !== 0, r.out);
  check('BITE escopo: nada foi escrito fora', !existsSync(join(dir, 'fora.txt')), 'escreveu fora do espelho!');
}

// ── 4: R1 do ssot-guard (.md dentro de cowork/) ──────────────────────────────
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'LEIAME.md', content: '# nao\n' }]);
  const r = rodar(dir, pay);
  check('BITE R1: recusa .md dentro de cowork/', /R1 do ssot-guard/.test(r.out) && r.code !== 0, r.out);
  check('BITE R1: o .md não foi escrito', !existsSync(join(dir, 'prototipo-ui/cowork/LEIAME.md')));
}

// ── 5: bytes divergente = corrupção de transporte ────────────────────────────
{
  const dir = sandbox();
  const pay = join(dir, 'p.json');
  writeFileSync(pay, JSON.stringify({ files: [{ path: 'c.jsx', content: 'abc\n', bytes: 999 }] }), 'utf8');
  const r = rodar(dir, pay);
  check('BITE bytes: recusa quando declarado != real', /DIVERGENTE/.test(r.out) && r.code !== 0, r.out);
  check('BITE bytes: arquivo corrompido NÃO é escrito', !existsSync(join(dir, 'prototipo-ui/cowork/c.jsx')));
}

// ── 6 + 7: alerta de PERDA LÍQUIDA (o caso qa-conformance.js) ────────────────
{
  const grande = Array.from({ length: 200 }, (_, i) => 'linha ' + i).join('\n') + '\n';
  const pequeno = Array.from({ length: 30 }, (_, i) => 'linha ' + i).join('\n') + '\n';
  const dir = sandbox({ 'perde.jsx': grande, 'cresce.jsx': pequeno });
  const pay = payload(dir, [
    { path: 'perde.jsx', content: pequeno },   // vivo MENOR → espelho à frente
    { path: 'cresce.jsx', content: grande },   // vivo MAIOR → sync legítimo
  ]);
  const r = rodar(dir, pay, ['--dry']);
  const linhaPerde = r.out.split('\n').find((l) => l.includes('perde.jsx')) || '';
  const linhaCresce = r.out.split('\n').find((l) => l.includes('cresce.jsx')) || '';
  check('BITE regressão: AVISA quando o apply perde linhas (espelho à frente)',
    /PERDE \d+ LINHAS/.test(linhaPerde), linhaPerde || r.out);
  check('CONTROLE NEGATIVO: NÃO avisa em sync que cresce',
    !/PERDE/.test(linhaCresce), linhaCresce);
  check('o alerta é RELATO, não bloqueio (rc segue 0)', r.code === 0, 'rc=' + r.code);
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ applier: escreve fiel · dry não escreve · recusa escopo/R1/bytes · avisa regressão sem bloquear');
process.exit(fails ? 1 : 0);
