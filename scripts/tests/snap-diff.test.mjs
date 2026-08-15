#!/usr/bin/env node
// SELF-TEST do snap-diff — hermético: monta PNGs em memória, zero disco de fixture,
// zero dependência. Rodar: node scripts/tests/snap-diff.test.mjs (exit 0 = passa).
//
// O que trava aqui, e por quê cada um:
//   1. IDÊNTICO → 0 diff. Se falhar, todo veredito do script é lixo (controle positivo).
//   2. As DUAS ASSINATURAS discriminam — é a razão de existir do script: um Δ de 2
//      (antialiasing) e um Δ de 249 (texto) NÃO podem cair no mesmo balde.
//   3. A GEOMETRIA discrimina — contíguo (string mudando) × espalhado (borda repetida).
//   4. FAIL-LOUD: formato não suportado ABORTA; nunca vira "0 pixels diferentes".
//      Sem este, o script vira a máquina que o §5 2026-07-29 proíbe (afirmar verde
//      sobre o que não conseguiu medir).
//   5. O `.snap` (base64 numa linha) é lido igual ao `.png` — é o formato REAL do repo.

import { deflateSync } from 'node:zlib';
import { writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { lerImagem, decodePNG, comparar, assinatura } from './snap-diff.mjs';

let fails = 0;
const check = (nome, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${nome}${cond ? '' : '  → ' + extra}`);
  if (!cond) fails++;
};

// ── encoder mínimo (só pro teste): PNG 8-bit RGB, filtro 0 ───────────────────
const CRC = (() => {
  const t = new Int32Array(256);
  for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c; }
  return (buf) => { let c = -1; for (const b of buf) c = t[(c ^ b) & 0xff] ^ (c >>> 8); return (c ^ -1) >>> 0; };
})();
function chunk(tipo, dados) {
  const len = Buffer.alloc(4); len.writeUInt32BE(dados.length);
  const td = Buffer.concat([Buffer.from(tipo, 'ascii'), dados]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(CRC(td));
  return Buffer.concat([len, td, crc]);
}
/** pinta(x,y) → [r,g,b] */
function png(w, h, pinta, { depth = 8, color = 2, interlace = 0 } = {}) {
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4);
  ihdr[8] = depth; ihdr[9] = color; ihdr[12] = interlace;
  const raw = [];
  for (let y = 0; y < h; y++) {
    const linha = Buffer.alloc(1 + w * 3); // filtro 0
    for (let x = 0; x < w; x++) { const [r, g, b] = pinta(x, y); linha[1 + x * 3] = r; linha[2 + x * 3] = g; linha[3 + x * 3] = b; }
    raw.push(linha);
  }
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr), chunk('IDAT', deflateSync(Buffer.concat(raw))), chunk('IEND', Buffer.alloc(0)),
  ]);
}

const W = 128, H = 128;
const branco = () => [255, 255, 255];
const dec = (b) => decodePNG(b);

// 1. CONTROLE POSITIVO — idêntico dá zero. Sem isso, nada abaixo vale.
{
  const a = dec(png(W, H, branco)), b = dec(png(W, H, branco));
  const r = comparar(a, b);
  check('CONTROLE: imagens idênticas → 0 px, assinatura IDENTICO', r.n === 0 && assinatura(r) === 'IDENTICO', JSON.stringify({ n: r.n }));
}

// 2. AS DUAS ASSINATURAS — o ponto todo do script.
{
  // rasterização: Δ=2 em pontos ESPALHADOS (borda de card repetida)
  const ruido = dec(png(W, H, (x, y) => (y === 10 && x % 20 === 0) ? [253, 253, 253] : [255, 255, 255]));
  const base = dec(png(W, H, branco));
  const rr = comparar(base, ruido);
  check('RASTERIZAÇÃO: Δmax=2 → assinatura RASTERIZACAO?', rr.dmax === 2 && assinatura(rr) === 'RASTERIZACAO?', JSON.stringify({ dmax: rr.dmax, sig: assinatura(rr) }));

  // conteúdo: Δ=249 num bloco CONTÍGUO (texto trocando de largura)
  const texto = dec(png(W, H, (x, y) => (y >= 40 && y < 48 && x >= 30 && x < 60) ? [6, 6, 6] : [255, 255, 255]));
  const rc = comparar(base, texto);
  check('CONTEÚDO: Δmax=249 → assinatura CONTEUDO?', rc.dmax === 249 && assinatura(rc) === 'CONTEUDO?', JSON.stringify({ dmax: rc.dmax, sig: assinatura(rc) }));

  // DISCRIMINA: as duas NÃO podem cair no mesmo balde (o erro que motivou o script)
  check('DISCRIMINA: ruído e conteúdo têm assinaturas DIFERENTES', assinatura(rr) !== assinatura(rc));

  // 3. GEOMETRIA: contíguo × espalhado
  const linhasRuido = [...new Set(rr.celulas.map((c) => c.gy))];
  const colsTexto = [...new Set(rc.celulas.map((c) => c.gx))].sort((a, b) => a - b);
  const contiguo = colsTexto.every((c, i) => i === 0 || c === colsTexto[i - 1] + 1);
  check('GEOMETRIA: ruído espalha em ≥3 colunas na MESMA linha', rr.celulas.length >= 3 && linhasRuido.length === 1, JSON.stringify({ cels: rr.celulas.length, linhas: linhasRuido }));
  check('GEOMETRIA: conteúdo ocupa colunas CONTÍGUAS', contiguo && colsTexto.length >= 1, JSON.stringify({ cols: colsTexto }));

  // banda do meio não mente de verde nem de vermelho
  const meio = dec(png(W, H, (x, y) => (y === 5 && x === 5) ? [155, 155, 155] : [255, 255, 255]));
  check('banda do meio (Δ=100) → INDETERMINADO, não chuta', assinatura(comparar(base, meio)) === 'INDETERMINADO');
}

// 4. FAIL-LOUD — formato não suportado ABORTA; NUNCA vira "0 diff".
{
  const tenta = (buf, nome) => { try { decodePNG(buf); return 'NAO-ABORTOU'; } catch (e) { return e.message; } };
  check('16-bit ABORTA (não vira 0 diff)', /ABORTANDO/.test(tenta(png(8, 8, branco, { depth: 16 }))));
  check('interlaçado ABORTA', /ABORTANDO/.test(tenta(png(8, 8, branco, { interlace: 1 }))));
  check('color-type palette ABORTA', /ABORTANDO/.test(tenta(png(8, 8, branco, { color: 3 }))));
  // CONTROLE NEGATIVO do fail-loud: o formato SUPORTADO não pode abortar.
  check('CONTROLE: RGB 8-bit NÃO aborta', tenta(png(8, 8, branco)) === 'NAO-ABORTOU');
}

// 5. DIMENSÕES divergentes são reportadas, não somadas
{
  const r = comparar(dec(png(16, 16, branco)), dec(png(32, 16, branco)));
  check('dimensões diferentes → dimensoesDiferem, n=null (não inventa contagem)', r.dimensoesDiferem === true && r.n === null);
}

// 6. O formato REAL do repo: `.snap` = PNG em base64 numa linha
{
  const dir = mkdtempSync(join(tmpdir(), 'snapdiff-'));
  try {
    const buf = png(16, 16, branco);
    const pngPath = join(dir, 'a.png'), snapPath = join(dir, 'a.snap');
    writeFileSync(pngPath, buf);
    writeFileSync(snapPath, buf.toString('base64') + '\n');
    const viaPng = decodePNG(lerImagem(pngPath)), viaSnap = decodePNG(lerImagem(snapPath));
    check('.snap (base64 1 linha) decodifica igual ao .png', viaPng.px.equals(viaSnap.px) && viaSnap.w === 16);
  } finally { rmSync(dir, { recursive: true, force: true }); }
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ snap-diff mede, discrimina as 2 assinaturas e ABORTA no que não sabe ler');
process.exit(fails ? 1 : 0);
