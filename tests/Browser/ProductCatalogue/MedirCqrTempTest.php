<?php

// TEMPORARIO: mede a tela real com a mesma sonda do prototipo. Sai antes do merge.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

$cqrSonda = <<<'JS'
(() => {
  const q = (s, r = document) => r.querySelector(s);
  const form = q('[data-contract="formulario"]');
  const out = q('[data-contract="saida"]');
  const cs = (e, ps) => (e ? Object.fromEntries(ps.map((p) => [p, getComputedStyle(e)[p]])) : null);
  const box = (e) => { if (!e) return null; const b = e.getBoundingClientRect(); return { x: Math.round(b.x), y: Math.round(b.y), w: Math.round(b.width), h: Math.round(b.height) }; };
  const paraRgb = (cor) => { const c = document.createElement('canvas'); c.width = c.height = 1; const x = c.getContext('2d'); x.fillStyle = cor; x.fillRect(0, 0, 1, 1); const d = x.getImageData(0, 0, 1, 1).data; return `rgb(${d[0]}, ${d[1]}, ${d[2]})`; };
  const sw = form ? [...form.querySelectorAll('button')].filter((b) => (b.getAttribute('aria-label') || '').startsWith('Cor ')) : [];
  const ativo = sw.findIndex((b) => b.classList.contains('active') || b.getAttribute('aria-pressed') === 'true');
  const inputs = form ? [...form.querySelectorAll('input')].filter((i) => !['checkbox', 'hidden', 'radio'].includes(i.type) && i.offsetParent) : [];
  const gerar = form ? [...form.querySelectorAll('button')].find((b) => b.textContent.includes('Gerar QR code')) : null;
  const passos = form ? [...form.querySelectorAll('[data-contract="instrucoes"] li')] : [];
  const fb = form && out ? { form: box(form), out: box(out) } : null;
  const canvas = out ? q('canvas', out) : null;
  const svgQr = out ? q('svg[role="img"]', out) : null;
  const code = out ? q('code', out) : null;
  const card = out ? q('.cqr-card', out) : null;
  const acoes = out ? [...out.querySelectorAll('button')].map((b) => b.textContent.trim()) : [];
  return {
    viewport: innerWidth,
    tema: document.documentElement.getAttribute('data-theme') || document.documentElement.className.slice(0, 60),
    colunas: fb ? (Math.abs(fb.form.y - fb.out.y) < 4 ? 2 : 1) : null,
    secoes: fb,
    razaoLarguras: fb ? +(fb.form.w / fb.out.w).toFixed(3) : null,
    secaoCss: cs(form, ['borderRadius', 'paddingTop', 'backgroundColor', 'borderTopColor']),
    titulo: cs(q('h1'), ['fontSize', 'fontWeight']),
    h2: cs(form && q('h2', form), ['fontSize', 'fontWeight']),
    swatches: sw.map((b) => paraRgb(getComputedStyle(b).backgroundColor)),
    swatchAtivo: ativo,
    swatchCss: cs(sw[0], ['width', 'height', 'borderRadius']),
    swatchAtivoCss: cs(sw[ativo], ['borderTopColor', 'boxShadow']),
    campos: inputs.map((i) => ({ ...box(i), fs: getComputedStyle(i).fontSize })),
    camposColunas: inputs.length ? new Set(inputs.map((i) => Math.round(i.getBoundingClientRect().x))).size : 0,
    gerar: gerar ? { ...box(gerar), bg: paraRgb(getComputedStyle(gerar).backgroundColor), fs: getComputedStyle(gerar).fontSize, disabled: gerar.disabled } : null,
    passos: passos.map((li) => ({ chip: !!li.querySelector('span'), lista: getComputedStyle(li).listStyleType, fs: getComputedStyle(li).fontSize })),
    saida: { canvas: canvas ? { w: canvas.width, h: canvas.height, css: box(canvas) } : null, svg: box(svgQr), card: box(card), linkDentroDoCard: !!(card && code && card.contains(code)), tituloEmHtml: !!(card && card.textContent.trim()), code: cs(code, ['fontSize', 'fontFamily']), acoes },
    scrollX: document.documentElement.scrollWidth - innerWidth,
  };
})()

JS;

beforeEach(function () {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'oimpresso_test']);
    DB::purge('mysql');
    File::ensureDirectoryExists(public_path('modules/productcatalogue/plugins'));
    File::copy(base_path('Modules/ProductCatalogue/Resources/assets/plugins/easy.qrcode.min.js'), public_path('modules/productcatalogue/plugins/easy.qrcode.min.js'));
    DB::table('subscriptions')->where('id', 900098)->delete();
    DB::table('subscriptions')->insert(['id' => 900098, 'business_id' => 98, 'package_id' => 900001, 'start_date' => '2026-01-01', 'end_date' => '2099-12-31', 'package_price' => 0,
        'package_details' => json_encode(['name' => 'x', 'productcatalogue_module' => '1']), 'created_id' => 98, 'status' => 'approved']);
});

afterEach(function () { DB::table('subscriptions')->where('id', 900098)->delete(); });

it('mede', function () use ($cqrSonda) {
    $saida = [];
    $dd = (string) shell_exec('node scripts/design/design-diff.mjs --probe');
    $page = visit('/_visreg-login/98?to='.urlencode('/product-catalogue/catalogue-qr'))->resize(1280, 800);
    for ($i = 0; $i < 40 && ! $page->script("!!document.querySelector('[data-contract=\"formulario\"]')"); $i++) { $page->wait(0.25); }
    $page->wait(1);
    $saida['form1280'] = $page->script($cqrSonda);
    $page->script("window.__DD_ROLES={title:'h1',primary:'[data-contract=\"formulario\"] button:not([role]):not([aria-label])'}");
    $saida['dd'] = $page->script($dd);
    foreach ([1101, 1100, 1024, 900] as $w) {
        $page->resize($w, 800);
        $page->wait(0.5);
        $r = $page->script($cqrSonda);
        $saida['w'.$w] = ['v' => $r['viewport'], 'colunas' => $r['colunas'], 'campos' => $r['camposColunas'], 'secoes' => $r['secoes']];
    }
    $page->resize(1280, 800);
    $page->wait(0.5);
    $page->click('#cqr-local');
    $page->click('[role="option"]');
    $page->click('Gerar QR code');
    for ($i = 0; $i < 40 && (int) $page->script("document.querySelectorAll('[data-contract=\"saida\"] canvas').length") === 0; $i++) { $page->wait(0.25); }
    $page->wait(0.5);
    $saida['gerado'] = $page->script($cqrSonda);
    fwrite(STDERR, "\nCQR-MEDIDA ".json_encode($saida, JSON_UNESCAPED_UNICODE)."\nCQR-FIM\n");
    expect(true)->toBeTrue();
});
