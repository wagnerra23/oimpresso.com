#!/usr/bin/env node
// snap-diff.mjs — LÊ o que mudou entre duas baselines de pixel (`.snap` do Pest Browser).
//
// POR QUE EXISTE (2026-08-14/15)
//   Os `.snap` do visreg são PNG em base64 numa ÚNICA linha. Consequência prática: todo
//   diff de baseline aparece como `+1 −1` de blob opaco. Ninguém consegue responder "o
//   que mudou nesta tela?" lendo o PR — e a saída natural vira aprovar no escuro, que é
//   exatamente o que o gate F1.5 existe pra impedir.
//
//   Custo medido: numa única investigação, 5 explicações plausíveis foram emitidas e
//   derrubadas por medição — a última só caiu porque alguém finalmente decodificou os
//   dois lados. Antes disso, "o placeholder causou o vermelho", "a falha é do Financeiro",
//   "o ui-impact é o culpado" e "o ruído soma com o relógio" pareciam todas razoáveis.
//
//   Este script troca a adivinhação por 3 números: QUANTO mudou, QUÃO forte, e ONDE.
//
// O QUE ELE RESPONDE (e por que esses três bastam pra classificar)
//   · px alterados — tamanho do fenômeno
//   · Δmax (0..255) — a ASSINATURA. Medido no corpus real:
//       Δmax 1..3   → antialiasing/rasterização: conteúdo byte-idêntico, borda curva.
//       Δmax > 200  → texto trocando (preto↔branco): conteúdo REALMENTE diferente.
//     Um diff de relógio ("Julho"→"Agosto") mediu Δmax=249 em 2 células contíguas;
//     um de rasterização mediu Δmax=2 em 6 células espalhadas. A assinatura separa os
//     dois sem abrir a imagem.
//   · grade 16×16 — ONDE. Células CONTÍGUAS = uma string mudando de largura;
//     células ESPALHADAS na mesma linha = ruído de borda repetido por card.
//
// O QUE ELE NÃO FAZ (de propósito)
//   Não emite veredito, não agrega nota, não decide se a mudança é boa. Direção de diff
//   visual NÃO é uniforme (proto pode estar atrás do prod) e agregar vereditos
//   incomensuráveis é proibido (§5 2026-07-17). Aqui: mede e reporta; humano decide.
//
// USO
//   node scripts/tests/snap-diff.mjs <a> <b> [--rotulo <txt>] [--json] [--grade N]
//     <a>/<b> aceitam `.snap` (base64) OU `.png`. Pra comparar contra a versão em main:
//       MSYS_NO_PATHCONV=1 git show origin/main:<path.snap> > /tmp/antes.snap
//     (o `git show <ref>:<path>` cru no Git Bash devolve VAZIO com rc=0 — mangling MSYS.)
//
// EXIT: 0 = idênticos · 1 = diferem (não é erro: é o fato) · 2 = erro de uso/formato.
//
// FAIL-LOUD: PNG fora de 8-bit RGB/RGBA não-interlaçado ABORTA com exit 2. "Não consegui
// decodificar" jamais vira "0 pixels diferentes" (§5 2026-07-29).

import { readFileSync } from 'node:fs';
import { inflateSync } from 'node:zlib';

/** Extrai o PNG de um `.snap` (base64, 1 linha) ou lê o `.png` direto. */
export function lerImagem(caminho) {
  const buf = readFileSync(caminho);
  if (buf.length > 8 && buf.readUInt32BE(0) === 0x89504e47) return buf; // já é PNG
  const txt = buf.toString('utf8').trim();
  const b64 = txt.split('\n').find((l) => l.startsWith('iVBOR')) || txt;
  const png = Buffer.from(b64.trim(), 'base64');
  if (png.length < 8 || png.readUInt32BE(0) !== 0x89504e47) {
    throw new Error(`${caminho}: não é PNG nem .snap com PNG em base64`);
  }
  return png;
}

/** Decodifica PNG 8-bit RGB/RGBA não-interlaçado. Qualquer outra forma ABORTA. */
export function decodePNG(buf) {
  if (buf.readUInt32BE(0) !== 0x89504e47) throw new Error('assinatura PNG inválida');
  let off = 8, w = 0, h = 0, depth = 0, color = -1, interlace = 0;
  const idat = [];
  while (off + 8 <= buf.length) {
    const len = buf.readUInt32BE(off);
    const type = buf.toString('ascii', off + 4, off + 8);
    const data = buf.subarray(off + 8, off + 8 + len);
    if (type === 'IHDR') {
      w = data.readUInt32BE(0); h = data.readUInt32BE(4);
      depth = data[8]; color = data[9]; interlace = data[12];
    } else if (type === 'IDAT') idat.push(data);
    else if (type === 'IEND') break;
    off += 12 + len;
  }
  if (!w || !h) throw new Error('IHDR ausente ou dimensões zeradas');
  if (depth !== 8) throw new Error(`bit-depth ${depth} não suportado — ABORTANDO (não vira 0 diff)`);
  if (interlace !== 0) throw new Error('PNG interlaçado não suportado — ABORTANDO');
  const ch = color === 6 ? 4 : color === 2 ? 3 : 0;
  if (!ch) throw new Error(`color-type ${color} não suportado (só 2=RGB e 6=RGBA) — ABORTANDO`);
  if (!idat.length) throw new Error('sem IDAT — ABORTANDO');

  const raw = inflateSync(Buffer.concat(idat));
  const stride = w * ch;
  if (raw.length < h * (stride + 1)) throw new Error('IDAT curto demais pro IHDR — ABORTANDO');
  const px = Buffer.alloc(h * stride);
  let pos = 0;
  for (let y = 0; y < h; y++) {
    const filtro = raw[pos++];
    const linha = raw.subarray(pos, pos + stride); pos += stride;
    const cur = px.subarray(y * stride, (y + 1) * stride);
    const prev = y ? px.subarray((y - 1) * stride, y * stride) : Buffer.alloc(stride);
    for (let x = 0; x < stride; x++) {
      const a = x >= ch ? cur[x - ch] : 0, b = prev[x], c = x >= ch ? prev[x - ch] : 0;
      let v = linha[x];
      if (filtro === 1) v += a;
      else if (filtro === 2) v += b;
      else if (filtro === 3) v += (a + b) >> 1;
      else if (filtro === 4) {
        const p = a + b - c, pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c);
        v += (pa <= pb && pa <= pc) ? a : (pb <= pc ? b : c);
      } else if (filtro !== 0) throw new Error(`filtro de scanline ${filtro} inválido — ABORTANDO`);
      cur[x] = v & 0xff;
    }
  }
  return { w, h, ch, px };
}

/** Compara duas imagens decodificadas. Só canais de COR (ignora alfa de propósito). */
export function comparar(A, B, { grade = 16 } = {}) {
  if (A.w !== B.w || A.h !== B.h) {
    return { dimensoesDiferem: true, a: `${A.w}x${A.h}`, b: `${B.w}x${B.h}`, n: null };
  }
  const g = Array.from({ length: grade }, () => new Array(grade).fill(0));
  let n = 0, dmax = 0;
  for (let y = 0; y < A.h; y++) {
    for (let x = 0; x < A.w; x++) {
      const i = (y * A.w + x) * A.ch;
      let d = 0;
      for (let c = 0; c < 3; c++) { const dd = Math.abs(A.px[i + c] - B.px[i + c]); if (dd > d) d = dd; }
      if (d) { n++; if (d > dmax) dmax = d; g[((y * grade / A.h) | 0)][((x * grade / A.w) | 0)]++; }
    }
  }
  const celulas = [];
  for (let gy = 0; gy < grade; gy++) for (let gx = 0; gx < grade; gx++) if (g[gy][gx]) celulas.push({ gx, gy, n: g[gy][gx] });
  celulas.sort((p, q) => q.n - p.n);
  return { dimensoesDiferem: false, n, dmax, total: A.w * A.h, celulas, grade, w: A.w, h: A.h };
}

/** Rótulo da ASSINATURA — heurística DECLARADA, nunca veredito. */
export function assinatura(r) {
  if (r.dimensoesDiferem) return 'DIMENSOES';
  if (!r.n) return 'IDENTICO';
  if (r.dmax <= 3) return 'RASTERIZACAO?';   // antialiasing: conteúdo idêntico
  if (r.dmax >= 200) return 'CONTEUDO?';     // texto trocando
  return 'INDETERMINADO';                    // banda do meio: humano olha
}

const argv = process.argv.slice(2);
const invocadoDireto = process.argv[1] && process.argv[1].endsWith('snap-diff.mjs');
if (invocadoDireto) {
  const pos = argv.filter((a) => !a.startsWith('--'));
  if (pos.length < 2) {
    console.error('uso: node scripts/tests/snap-diff.mjs <a.snap|a.png> <b.snap|b.png> [--rotulo X] [--json] [--grade N]');
    process.exit(2);
  }
  const iG = argv.indexOf('--grade');
  const grade = iG >= 0 ? Number(argv[iG + 1]) || 16 : 16;
  const iR = argv.indexOf('--rotulo');
  const rotulo = iR >= 0 ? argv[iR + 1] : `${pos[0]} × ${pos[1]}`;
  let A, B;
  try {
    A = decodePNG(lerImagem(pos[0]));
    B = decodePNG(lerImagem(pos[1]));
  } catch (e) {
    console.error(`✗ ${e.message}`);
    process.exit(2);
  }
  const r = comparar(A, B, { grade });
  const sig = assinatura(r);
  if (argv.includes('--json')) {
    console.log(JSON.stringify({ rotulo, assinatura: sig, ...r }, null, 2));
  } else {
    console.log(`${rotulo}`);
    if (r.dimensoesDiferem) console.log(`  DIMENSÕES DIFEREM: ${r.a} vs ${r.b}`);
    else {
      console.log(`  ${A.w}x${A.h} · px alterados: ${r.n} de ${r.total} (${(100 * r.n / r.total).toFixed(4)}%) · Δmax=${r.dmax}`);
      console.log(`  assinatura: ${sig}   (Δ≤3 rasterização · Δ≥200 conteúdo · meio = humano olha)`);
      if (r.n) {
        console.log(`  células ${grade}×${grade} com mudança: ${r.celulas.length} de ${grade * grade}`);
        console.log(`  top: ${r.celulas.slice(0, 6).map((c) => `(col${c.gx},lin${c.gy})=${c.n}px`).join(' ')}`);
        const linhas = [...new Set(r.celulas.map((c) => c.gy))].sort((a, b) => a - b);
        console.log(`  linhas afetadas: ${linhas.join(',')}   (contíguas = string mudando · espalhadas = borda repetida)`);
      }
    }
  }
  process.exit(r.dimensoesDiferem || r.n ? 1 : 0);
}
