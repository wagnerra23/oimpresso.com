// Gera os ativos de loja do app a partir da marca do Design System.
// Fonte: prototipo-ui/design-system/assets/brand/logo-mark.svg (cubo CMYK) + roxo do DS oklch(0.55 0.15 295).
// Ícone escolhido por [W] em 2026-10-01: variante A (só o cubo, fundo roxo).
// Rodar da raiz do repo:  node store-assets/gerar-ativos.mjs   (usa o Playwright da raiz)
// PNGs de loja saem em RGB sem canal alfa (a Apple recusa ícone com alfa).
import { createRequire } from 'node:module';
import fs from 'node:fs'; import zlib from 'node:zlib'; import path from 'node:path';
const ROOT = process.cwd();
const { chromium } = createRequire(path.join(ROOT, 'package.json'))('playwright');
const OUT = path.join(ROOT, 'store-assets'), IMG = path.join(OUT, 'app');
fs.mkdirSync(IMG, { recursive: true });
const cube = fs.readFileSync(path.join(ROOT, 'prototipo-ui/design-system/assets/brand/logo-mark.svg'), 'utf8')
  .replace('<svg ', '<svg style="width:100%;height:auto;display:block" ');
const PURPLE = 'oklch(0.55 0.15 295)';

function crc32(b){let c,t=[];for(let n=0;n<256;n++){c=n;for(let k=0;k<8;k++)c=c&1?0xEDB88320^(c>>>1):c>>>1;t[n]=c>>>0}c=0xFFFFFFFF;for(const x of b)c=t[(c^x)&0xFF]^(c>>>8);return (c^0xFFFFFFFF)>>>0}
function chunk(type,data){const l=Buffer.alloc(4);l.writeUInt32BE(data.length);const td=Buffer.concat([Buffer.from(type),data]);const c=Buffer.alloc(4);c.writeUInt32BE(crc32(td));return Buffer.concat([l,td,c])}
function png(w,h,rgba,alpha){const bpp=alpha?4:3,raw=Buffer.alloc((w*bpp+1)*h);for(let y=0;y<h;y++)for(let x=0;x<w;x++){const i=(y*w+x)*4,o=y*(w*bpp+1)+1+x*bpp;raw[o]=rgba[i];raw[o+1]=rgba[i+1];raw[o+2]=rgba[i+2];if(alpha)raw[o+3]=rgba[i+3]}
  const ih=Buffer.alloc(13);ih.writeUInt32BE(w,0);ih.writeUInt32BE(h,4);ih[8]=8;ih[9]=alpha?6:2;
  return Buffer.concat([Buffer.from([137,80,78,71,13,10,26,10]),chunk('IHDR',ih),chunk('IDAT',zlib.deflateSync(raw,{level:9})),chunk('IEND',Buffer.alloc(0))])}

const b = await chromium.launch(); const p = await b.newPage();
async function render(body, w, h, file, { alpha = false, jpg = false } = {}) {
  await p.setViewportSize({ width: w, height: h });
  await p.setContent(`<html><head><style>*{margin:0;box-sizing:border-box}body{width:${w}px;height:${h}px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-family:'Segoe UI',system-ui,sans-serif}</style></head><body style="${body.style}">${body.html}</body></html>`);
  if (jpg) { fs.writeFileSync(file, await p.screenshot({ type: 'jpeg', quality: 94 })); return; }
  const shot = await p.screenshot({ type: 'png', omitBackground: alpha });
  const data = await p.evaluate(async (s) => { const im = new Image(); im.src = 'data:image/png;base64,' + s; await im.decode();
    const c = document.createElement('canvas'); c.width = im.width; c.height = im.height; const x = c.getContext('2d'); x.drawImage(im, 0, 0);
    return Array.from(x.getImageData(0, 0, c.width, c.height).data); }, shot.toString('base64'));
  fs.writeFileSync(file, png(w, h, Uint8Array.from(data), alpha));
}
const marca = (w, mono = false) => `<div style="width:${w}px;${mono ? 'filter:brightness(0) invert(1)' : ''}">${cube}</div>`;
const roxo = { style: `background:${PURPLE}` }, transp = { style: 'background:transparent' };

// App — nomes do @capacitor/assets (`npx @capacitor/assets generate --assetPath store-assets/app`)
await render({ ...roxo, html: marca(1024 * 0.56) }, 1024, 1024, `${IMG}/icon-only.png`);
await render({ ...transp, html: marca(1024 * 0.46) }, 1024, 1024, `${IMG}/icon-foreground.png`, { alpha: true });
await render({ ...roxo, html: '' }, 1024, 1024, `${IMG}/icon-background.png`);
await render({ ...roxo, html: marca(2732 * 0.22) }, 2732, 2732, `${IMG}/splash.png`);
await render({ ...roxo, html: marca(2732 * 0.22) }, 2732, 2732, `${IMG}/splash-dark.png`);
// Lojas
await render({ ...roxo, html: marca(1024 * 0.56) }, 1024, 1024, `${OUT}/app-store-icon-1024.png`);
await render({ ...roxo, html: marca(512 * 0.56) }, 512, 512, `${OUT}/play-icon-512.png`);
await render({ style: 'background:radial-gradient(circle at 20% 30%, oklch(0.6 0.15 295), oklch(0.40 0.13 295));justify-content:flex-start;gap:48px;padding:0 80px;color:#fff',
  html: `<div style="width:220px;flex:none">${cube}</div><div><div style="font-size:72px;font-weight:700;letter-spacing:-1px">oimpresso</div><div style="font-size:30px;opacity:.92;margin-top:10px;line-height:1.3">Pedidos, produção, ordens de serviço<br>e o ponto da equipe no celular.</div></div>` },
  1024, 500, `${OUT}/play-feature-graphic-1024x500.jpg`, { jpg: true });
await b.close(); console.log('ativos gerados');
