#!/usr/bin/env node
/**
 * BITE-TEST do congelamento do relógio do navegador (gate visual-regression).
 * ---------------------------------------------------------------------------------
 * Prova, em Chromium REAL, as três pernas que um conserto de gate required exige.
 * O risco de trocar um gate barulhento por um gate CEGO é o que este arquivo mede:
 *
 *   1. CONTROLE-NEGATIVO — SEM o shim, duas capturas separadas por >1min DIFEREM.
 *      Sem esta perna, "as duas capturas ficaram idênticas" não prova nada: podia
 *      ser uma página que simplesmente não tem relógio nenhum.
 *
 *   2. LIBERA — COM o shim, as MESMAS duas capturas ficam byte-idênticas. É o ponto
 *      todo do conserto: o veredito não pode mudar entre duas execuções separadas
 *      no tempo.
 *
 *   3. MORDE — COM o shim, uma regressão de pixel REAL (cor + padding) AINDA produz
 *      captura diferente. É o que prova que o conserto não cegou o gate.
 *
 * Mais uma perna de contrato (barata, e protege o resto do app):
 *
 *   4. SEMÂNTICA — `new Date(x)` com argumento continua REAL (o parse de timestamp
 *      vindo do servidor não pode congelar), `instanceof Date` continua verdadeiro,
 *      `Date.now()` congela e `Date.parse`/`Date.UTC` seguem intactos.
 *
 * ZERO TRANSCRIÇÃO: o shim exercitado é lido de `resources/visreg/freeze-clock.js`,
 * o MESMO arquivo que `layouts/inertia.blade.php` injeta — e é montado aqui na MESMA
 * forma (primeiro <script> do <head>, com `window.__VISREG_FREEZE_AT__` antes). O que
 * é provado é o que roda.
 *
 * USO
 *   node scripts/tests/visreg-clock-bite.mjs
 *
 * Leva ~90s de propósito: espera ≥61s de relógio real E a virada do minuto. Encurtar
 * isso é desmontar a única perna que mede determinismo no tempo.
 *
 * @see resources/visreg/freeze-clock.js          (o shim provado)
 * @see resources/views/layouts/inertia.blade.php (a injeção em produção)
 * @see config/visreg.php                         (a flag)
 */

import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
import { readFileSync, existsSync } from 'node:fs';

const AQUI = dirname(fileURLToPath(import.meta.url));
const RAIZ = resolve(AQUI, '..', '..');

const CAMINHO_SHIM = resolve(RAIZ, 'resources/visreg/freeze-clock.js');

/**
 * Instante congelado. É o mesmo valor que o .env do workflow passa em
 * VISREG_FREEZE_CLOCK ("2026-06-11 12:00:00") lido no fuso da app (Europe/London,
 * config/app.php:69) — ou seja, 11:00Z. O número exato não é o ponto; o ponto é que
 * ele NÃO se move.
 */
const INSTANTE_MS = Date.UTC(2026, 5, 11, 11, 0, 0);

/** Resolve o playwright: worktree não tem node_modules próprio, o repo principal tem. */
function carregarPlaywright() {
  const candidatos = [RAIZ, resolve(RAIZ, '..', '..', '..'), 'D:/oimpresso.com'];

  for (const base of candidatos) {
    const pkg = resolve(base, 'node_modules/playwright/package.json');
    if (existsSync(pkg)) {
      const require = createRequire(resolve(base, 'noop.js'));
      return { pw: require('playwright'), base };
    }
  }

  throw new Error(
    'playwright não encontrado. Tentei: ' + candidatos.map((c) => c + '/node_modules').join(', ')
  );
}

/**
 * Página-cobaia que reproduz o defeito REAL — as três expressões medidas em
 * 2026-08-14 nas telas da Jana, copiadas na forma, não no texto:
 *   JanaAreaHeader.tsx:80   → toLocaleTimeString('pt-BR', {hour,minute})
 *   JanaCockpit.tsx:356     → toLocaleDateString('pt-BR', {day,month,year})
 *   JanaCockpit.tsx:107     → getHours() dirigindo a saudação
 */
function montarHtml({ comShim, comSabotagem, shim }) {
  const cabecaShim = comShim
    ? `<script>window.__VISREG_FREEZE_AT__ = ${INSTANTE_MS};${shim}</script>`
    : '';

  // SABOTAGEM = regressão de pixel real e proposital (cor de fundo + padding).
  const sabotagem = comSabotagem
    ? '.cartao{background:#c81e1e !important;padding:48px !important;}'
    : '';

  return `<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
${cabecaShim}
<style>
  body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#fff;}
  .cartao{margin:24px;padding:20px;border:1px solid #ddd;background:#f7f7f8;}
  .rotulo{font-size:13px;color:#555;}
  .valor{font-size:22px;font-weight:700;color:#111;}
  ${sabotagem}
</style>
</head>
<body>
  <div class="cartao">
    <div class="rotulo">Brief diário ·
      <span class="valor" id="data"></span>
    </div>
    <div class="rotulo">Atualizado
      <span class="valor" id="hora"></span>
    </div>
    <div class="rotulo">Saudação
      <span class="valor" id="saudacao"></span>
    </div>
  </div>
<script>
  document.getElementById('data').textContent =
    new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
  document.getElementById('hora').textContent =
    new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  var h = new Date().getHours();
  document.getElementById('saudacao').textContent =
    h < 12 ? 'Bom dia' : (h < 18 ? 'Boa tarde' : 'Boa noite');
</script>
</body>
</html>`;
}

async function capturar(contexto, opcoes) {
  const page = await contexto.newPage();
  await page.setContent(montarHtml(opcoes), { waitUntil: 'load' });

  const textos = await page.evaluate(() => ({
    data: document.getElementById('data').textContent,
    hora: document.getElementById('hora').textContent,
    saudacao: document.getElementById('saudacao').textContent,
  }));

  const png = await page.screenshot({ type: 'png' });
  await page.close();

  return { png, textos };
}

/** Espera ≥61s de relógio real E a virada do minuto — as duas condições juntas. */
async function esperarJanelaDeTempo() {
  const inicio = Date.now();
  const minutoInicial = new Date().getMinutes();

  process.stdout.write('   aguardando (≥61s + virada de minuto)');

  // eslint-disable-next-line no-constant-condition
  while (true) {
    await new Promise((r) => setTimeout(r, 3000));
    process.stdout.write('.');

    const decorrido = Date.now() - inicio;
    const virou = new Date().getMinutes() !== minutoInicial;

    if (decorrido >= 61_000 && virou) {
      process.stdout.write(`\n   esperou ${Math.round(decorrido / 1000)}s · minuto ${minutoInicial} → ${new Date().getMinutes()}\n`);
      return { decorridoMs: decorrido, minutoInicial, minutoFinal: new Date().getMinutes() };
    }
  }
}

const iguais = (a, b) => a.length === b.length && a.equals(b);

async function main() {
  if (!existsSync(CAMINHO_SHIM)) {
    console.error(`FALHOU: shim não encontrado em ${CAMINHO_SHIM}`);
    process.exit(1);
  }

  const shim = readFileSync(CAMINHO_SHIM, 'utf8');
  const { pw, base } = carregarPlaywright();

  console.log('BITE-TEST — congelamento do relógio do navegador');
  console.log('='.repeat(72));
  console.log(`shim.......: ${CAMINHO_SHIM} (${shim.length} bytes)`);
  console.log(`playwright.: ${base}/node_modules/playwright`);
  console.log(`instante...: ${new Date(INSTANTE_MS).toISOString()} (${INSTANTE_MS})`);
  console.log('');

  const browser = await pw.chromium.launch({ headless: true });
  console.log(`chromium...: ${browser.version()}`);

  // Espelha o contexto do pest-plugin-browser
  // (vendor/pestphp/pest-plugin-browser/src/Api/PendingAwaitablePage.php:174-176).
  const contexto = await browser.newContext({
    locale: 'en-US',
    timezoneId: 'UTC',
    viewport: { width: 1280, height: 720 },
  });

  const falhas = [];

  // ── Captura A (t0): sem shim e com shim, lado a lado ──────────────────────────
  console.log('\n[t0] capturando…');
  const semShimA = await capturar(contexto, { comShim: false, comSabotagem: false, shim });
  const comShimA = await capturar(contexto, { comShim: true, comSabotagem: false, shim });
  console.log(`   SEM shim: data="${semShimA.textos.data}" hora="${semShimA.textos.hora}" saudacao="${semShimA.textos.saudacao}"`);
  console.log(`   COM shim: data="${comShimA.textos.data}" hora="${comShimA.textos.hora}" saudacao="${comShimA.textos.saudacao}"`);

  // ── Janela de tempo real ──────────────────────────────────────────────────────
  console.log('\n[espera] separando as duas execuções no tempo real…');
  const janela = await esperarJanelaDeTempo();

  // ── Captura B (t1) ────────────────────────────────────────────────────────────
  console.log('[t1] capturando…');
  const semShimB = await capturar(contexto, { comShim: false, comSabotagem: false, shim });
  const comShimB = await capturar(contexto, { comShim: true, comSabotagem: false, shim });
  console.log(`   SEM shim: data="${semShimB.textos.data}" hora="${semShimB.textos.hora}" saudacao="${semShimB.textos.saudacao}"`);
  console.log(`   COM shim: data="${comShimB.textos.data}" hora="${comShimB.textos.hora}" saudacao="${comShimB.textos.saudacao}"`);

  // ── Sabotagem (regressão de pixel real), COM o shim ligado ────────────────────
  console.log('\n[sabotagem] regressão de pixel proposital (cor + padding), com o shim LIGADO…');
  const comShimSabotado = await capturar(contexto, { comShim: true, comSabotagem: true, shim });

  // ── Perna 4: semântica do shim ────────────────────────────────────────────────
  const paginaSemantica = await contexto.newPage();
  await paginaSemantica.setContent(montarHtml({ comShim: true, comSabotagem: false, shim }), {
    waitUntil: 'load',
  });
  const semantica = await paginaSemantica.evaluate((esperado) => {
    const comArgumento = new Date('2001-02-03T04:05:06Z');
    return {
      nowCongelado: Date.now() === esperado,
      semArgumentoCongelado: new Date().getTime() === esperado,
      comArgumentoIntacto: comArgumento.toISOString() === '2001-02-03T04:05:06.000Z',
      instanceofOk: new Date() instanceof Date,
      parseIntacto: Date.parse('2001-02-03T04:05:06Z') === 981173106000,
      utcIntacto: Date.UTC(2001, 1, 3) === 981158400000,
      chamadaComoFuncaoEhString: typeof Date() === 'string',
    };
  }, INSTANTE_MS);
  await paginaSemantica.close();

  await browser.close();

  // ── Vereditos ─────────────────────────────────────────────────────────────────
  console.log('\n' + '='.repeat(72));
  console.log('VEREDITOS');
  console.log('='.repeat(72));

  // 1. CONTROLE-NEGATIVO
  const controleDetectou = !iguais(semShimA.png, semShimB.png);
  console.log(
    `\n1. CONTROLE-NEGATIVO (sem shim, t0 vs t1 devem DIFERIR): ${controleDetectou ? '✅ DIFEREM' : '❌ IGUAIS'}`
  );
  console.log(`   hora t0="${semShimA.textos.hora}" · hora t1="${semShimB.textos.hora}"`);
  if (!controleDetectou) {
    falhas.push(
      'CONTROLE-NEGATIVO: sem o shim as capturas ficaram iguais — a cobaia não exercita o relógio, '
        + 'então a perna LIBERA seria vacuosa.'
    );
  }

  // 2. LIBERA
  const liberou = iguais(comShimA.png, comShimB.png);
  console.log(`\n2. LIBERA (com shim, t0 vs t1 devem ser IDÊNTICAS): ${liberou ? '✅ IDÊNTICAS' : '❌ DIFEREM'}`);
  console.log(`   hora t0="${comShimA.textos.hora}" · hora t1="${comShimB.textos.hora}"`);
  console.log(`   bytes t0=${comShimA.png.length} · t1=${comShimB.png.length}`);
  console.log(`   separação real: ${Math.round(janela.decorridoMs / 1000)}s (minuto ${janela.minutoInicial} → ${janela.minutoFinal})`);
  if (!liberou) {
    falhas.push('LIBERA: com o shim as duas capturas separadas no tempo AINDA diferem — o conserto não pegou.');
  }

  // 3. MORDE
  const mordeu = !iguais(comShimA.png, comShimSabotado.png);
  console.log(`\n3. MORDE (com shim, regressão real deve DIFERIR): ${mordeu ? '✅ DIFERE' : '❌ IGUAIS'}`);
  console.log(`   bytes limpo=${comShimA.png.length} · sabotado=${comShimSabotado.png.length}`);
  if (!mordeu) {
    falhas.push('MORDE: a regressão de pixel proposital NÃO mudou a captura — o gate ficou CEGO.');
  }

  // 4. SEMÂNTICA
  const semanticaOk = Object.values(semantica).every(Boolean);
  console.log(`\n4. SEMÂNTICA do shim: ${semanticaOk ? '✅ OK' : '❌ QUEBRADA'}`);
  for (const [chave, valor] of Object.entries(semantica)) {
    console.log(`   ${valor ? '✅' : '❌'} ${chave}`);
  }
  if (!semanticaOk) {
    falhas.push('SEMÂNTICA: o shim quebrou contrato do Date (ver as chaves ❌ acima).');
  }

  console.log('\n' + '='.repeat(72));
  if (falhas.length > 0) {
    console.log(`RESULTADO: ❌ ${falhas.length} falha(s)`);
    for (const f of falhas) console.log(`  - ${f}`);
    process.exit(1);
  }

  console.log('RESULTADO: ✅ 4/4 — morde, libera, controle-negativo detecta, semântica intacta.');
}

main().catch((e) => {
  console.error('ERRO:', e && e.stack ? e.stack : e);
  process.exit(1);
});
