#!/usr/bin/env node
// cowork-pele-paralela.mjs — MAQUINA contra PELE PARALELA no espelho de design.
//
// "Pele paralela" = a tela reimplementa, com nome proprio, algo que ja tem dono:
// a barra de abas, o segmented, o header de pagina, um mini design-system inteiro.
// A auditoria manual encurta a lista; a tela seguinte alonga. Isto e a regra.
//
// ORIGEM: pedido Cowork [CC] 2026-08-31 (T1), medido em main@84b62eb785.
// O .mjs original vive no projeto Cowork e NAO desceu por nenhuma das 3 rotas de
// transporte (medido 2026-08-31: `pele.paralela` = 0 hits em origin/main inteiro, com
// controle positivo `cowork-ssot-guard` = 56 hits; cowork-inbox sem drop; zero issue
// cowork-intake; DesignSync sem autorizacao em sessao nao-interativa). Esta e a
// implementacao da ESPECIFICACAO daquele pedido — 5 regras, 3 donos, waivers com
// motivo. Se o .mjs original descer depois, RECONCILIAR num dono so (nao manter dois).
//
// ================== O QUE A MEDICAO MOSTROU (2026-08-31, pos-T2) ==================
// Os 3 DONOS declarados pelo pedido NAO EXISTEM no espelho do git:
//   cli-tabs.jsx · cli-seg.js · cli-pagehead.jsx  -> 0 arquivo(s), repo inteiro.
//   (`CliTabs`/`CliSeg` = 0 hits. O `.cli-seg` que existe em styles.css e a pele do
//    modulo CLIentes — uma das 18 familias —, nao o dono canonico.)
// Eles sao LIVE-ONLY: vivem no Cowork vivo e nunca desceram (lapide §5 2026-08-11).
//
// CONSEQUENCIA DE DESENHO — e por isso que este guard e DELTA, nao ABSOLUTO:
// sem o dono no espelho, "fora do dono" acusa 100% da populacao (17 arquivos em R1,
// 18 familias em R2) e o gate nasceria VERMELHO PERMANENTE — ruido que se aprende a
// ignorar, o anti-padrao do §5 2026-07-28. Predicado absoluto sobre estado herdado
// tambem e a lapide §5 2026-08-24 (`fail == 0` trancou o auto-merge por 9 dias).
// Entao a pergunta que este guard faz e "este PR INTRODUZ pele nova?", nunca
// "existe pele?". A divida herdada mora no baseline, declarada, e so ENCOLHE.
//
// LIMITE HONESTO: as 5 regras sao SINTATICAS. O §5 tem 8 lapides de guard sintatico
// reprovado por falso-positivo. O que salva estas e o baseline: elas nao julgam o
// legado, so barram o acrescimo — e todo acrescimo tem autor presente pra decidir
// entre consertar e declarar waiver com motivo.
//
// USO:
//   node scripts/qa/cowork-pele-paralela.mjs                 # --check (delta vs baseline)
//   node scripts/qa/cowork-pele-paralela.mjs --measure       # censo completo, exit 0
//   node scripts/qa/cowork-pele-paralela.mjs --update-baseline
//   node scripts/qa/cowork-pele-paralela.mjs --selftest      # bite-test pelo CLI de fora
// Flags: --dir <path> --baseline <path> --json --aviso-so
import { readdirSync, readFileSync, existsSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const AQUI = dirname(fileURLToPath(import.meta.url));
const ESTE = fileURLToPath(import.meta.url);

// ---------------------------------------------------------------- configuracao
// DONOS — editar so com ADR. `arquivo` e onde o dono DEVERIA morar no espelho; a
// ausencia dele e REPORTADA (nunca silenciada), porque instrumento que nao mede
// nao pode afirmar verde (§5 2026-07-29).
const DONOS = {
  abas: { arquivo: 'cli-tabs.jsx', conserto: 'usar o dono de abas (window.CliTabs)' },
  segmented: { arquivo: 'cli-seg.js', conserto: 'usar o dono de segmented (window.CliSeg)' },
  pagehead: { arquivo: 'cli-pagehead.jsx', conserto: 'usar o dono de header de pagina' },
};

// WAIVERS — cada um com o motivo escrito. Encolher esta lista e progresso;
// crescer exige ADR. Chave = a mesma chave estavel do achado.
const WAIVERS = new Map([
  ['R1:produto-blade.jsx', 'rota Produtos trava com o TabBar via wrapper (pedido Cowork [CC] 2026-08-31)'],
  ['R1:produto-analises.jsx', 'idem produto-blade — mesma rota, mesmo wrapper'],
  ['R1:produto-cadastros.jsx', 'idem produto-blade — mesma rota, mesmo wrapper'],
  ['R2:pb', 'produto-blade: data-testid por botao usado em teste de contrato'],
  ['R2:hrm', 'essenciais: paginador de mes, nao escolha unica — nao e segmented'],
]);

// Mini-DS toleradas hoje. Meta = 0; cada saida daqui e uma leva de migracao pro DS.
const MINI_DS_ALLOWLIST = new Set([]);

const EXT_CODIGO = /\.(jsx|js|tsx)$/;
const EXT_CSS = /\.css$/;

// ------------------------------------------------------------------- utilidades
function andar(dir) {
  if (!existsSync(dir)) return [];
  const saida = [];
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = `${dir}/${e.name}`;
    if (e.isDirectory()) saida.push(...andar(p));
    else saida.push(p);
  }
  return saida;
}
const rel = (f, dir) => (f.startsWith(dir + '/') ? f.slice(dir.length + 1) : f);

// Nomes canonicos do DS, derivados do registro (nunca redigitados aqui — §5 2026-07-17:
// doc canonico nao restateia lista que outro sistema sabe melhor).
function componentesDoDs() {
  const reg = join(AQUI, '..', '..', 'prototipo-ui', 'REGISTRY_DS_COMPONENTES.md');
  if (!existsSync(reg)) return { nomes: new Set(), fonte: null };
  const nomes = new Set();
  const texto = readFileSync(reg, 'utf8');
  for (const m of texto.matchAll(/^\| \*\*([A-Za-z][A-Za-z0-9]*)\*\*/gm)) nomes.add(m[1]);
  return { nomes, fonte: 'prototipo-ui/REGISTRY_DS_COMPONENTES.md' };
}

// ----------------------------------------------------------------- o scan (R1..R5)
export function varrer(dir) {
  const todos = andar(dir);
  const codigo = todos.filter((f) => EXT_CODIGO.test(f));
  const css = todos.filter((f) => EXT_CSS.test(f));
  const achados = [];
  const notas = [];

  // Donos ausentes: reportar, nunca silenciar.
  const donosAusentes = [];
  for (const [papel, d] of Object.entries(DONOS)) {
    if (!existsSync(join(dir, d.arquivo))) donosAusentes.push(`${papel}=${d.arquivo}`);
  }
  if (donosAusentes.length) {
    notas.push(`donos AUSENTES do espelho (LIVE-ONLY, nunca desceram): ${donosAusentes.join(' · ')}`);
  }

  // R1 — classe de aba do dono usada em markup fora do dono.
  for (const f of codigo) {
    const nome = rel(f, dir);
    if (nome === DONOS.abas.arquivo) continue;
    const n = (readFileSync(f, 'utf8').match(/cli-moduletopnav[\w-]*/g) || []).length;
    if (n) {
      achados.push({
        regra: 'R1', chave: `R1:${nome}`, alvo: nome, n,
        diz: `usa a classe de aba do dono (${n}x) em markup proprio`,
        conserto: DONOS.abas.conserto,
      });
    }
  }

  // R2 — familia de segmented propria (`X-seg`) declarada fora do dono.
  const familias = new Map();
  const anota = (k, f) => {
    if (!familias.has(k)) familias.set(k, new Set());
    familias.get(k).add(rel(f, dir));
  };
  for (const f of css) for (const m of readFileSync(f, 'utf8').matchAll(/\.([a-z]{2,6})-seg\b/g)) anota(m[1], f);
  for (const f of codigo) for (const m of readFileSync(f, 'utf8').matchAll(/["'\s]([a-z]{2,6})-seg\b/g)) anota(m[1], f);
  const donoSeg = DONOS.segmented.arquivo.replace(/\.[a-z]+$/, '').replace(/^cli-/, '');
  for (const [fam, arqs] of [...familias].sort()) {
    if (fam === donoSeg) continue;
    achados.push({
      regra: 'R2', chave: `R2:${fam}`, alvo: `.${fam}-seg`, n: arqs.size,
      diz: `segmented proprio em ${arqs.size} arquivo(s): ${[...arqs].slice(0, 3).join(', ')}`,
      conserto: DONOS.segmented.conserto,
    });
  }

  // R3/R4/R5 — o que cada arquivo publica em window.
  const publicados = new Map();
  for (const f of codigo) {
    const texto = readFileSync(f, 'utf8');
    for (const m of texto.matchAll(/window\.([A-Za-z_$][\w$]*)\s*=(?!=)/g)) {
      const k = m[1];
      if (!publicados.has(k)) publicados.set(k, new Set());
      publicados.get(k).add(rel(f, dir));
    }
  }
  // R3 — mini design-system novo.
  for (const [nome, arqs] of [...publicados].sort()) {
    if (!/(UI|DS)$/.test(nome) || MINI_DS_ALLOWLIST.has(nome)) continue;
    achados.push({
      regra: 'R3', chave: `R3:${nome}`, alvo: `window.${nome}`, n: arqs.size,
      diz: `mini design-system fora do inventario (${[...arqs].join(', ')})`,
      conserto: 'estender o DS ou um dono existente',
    });
  }
  // R4 — o mesmo nome publicado por dois arquivos.
  for (const [nome, arqs] of [...publicados].sort()) {
    if (arqs.size < 2) continue;
    achados.push({
      regra: 'R4', chave: `R4:${nome}`, alvo: `window.${nome}`, n: arqs.size,
      diz: `publicado por ${arqs.size} arquivos: ${[...arqs].join(' , ')}`,
      conserto: 'apagar a segunda publicacao',
    });
  }
  // R5 — nome publicado que colide com componente do DS.
  const ds = componentesDoDs();
  if (!ds.fonte) notas.push('R5 NAO MEDIDA: REGISTRY_DS_COMPONENTES.md ausente');
  for (const [nome, arqs] of [...publicados].sort()) {
    if (!ds.nomes.has(nome)) continue;
    achados.push({
      regra: 'R5', chave: `R5:${nome}`, alvo: `window.${nome}`, n: arqs.size,
      diz: `colide com o componente ${nome} do DS (${[...arqs].join(', ')})`,
      conserto: `prefixar (window.Oi${nome})`,
    });
  }

  const comWaiver = achados.filter((a) => WAIVERS.has(a.chave));
  return {
    corpus: { arquivos: todos.length, codigo: codigo.length, css: css.length },
    notas,
    r5Fonte: ds.fonte,
    achados: achados.filter((a) => !WAIVERS.has(a.chave)),
    waivers: comWaiver.map((a) => ({ ...a, motivo: WAIVERS.get(a.chave) })),
  };
}

// ------------------------------------------------------------------- baseline
function lerBaseline(caminho) {
  if (!existsSync(caminho)) return null;
  try {
    return JSON.parse(readFileSync(caminho, 'utf8'));
  } catch (e) {
    // Baseline ilegivel e falha do consultante, nunca ausencia no consultado
    // (§5 2026-07-29): parear pra "vazio" inventaria estado.
    console.error(`[pele-paralela] baseline ILEGIVEL em ${caminho}: ${e.message}`);
    process.exit(2);
  }
}

// ------------------------------------------------------------------- selftest
// Exercita o CLI DE FORA (spawn), nunca um helper exportado — §5 2026-07-30:
// assert sobre funcao-satelite nao prova o chokepoint.
function selftest() {
  const tmp = join(AQUI, '.pele-selftest-tmp');
  const casos = [];
  const rodar = (dir, base) =>
    spawnSync(process.execPath, [ESTE, '--check', '--dir', dir, '--baseline', base], { encoding: 'utf8' });

  try {
    rmSync(tmp, { recursive: true, force: true });
    mkdirSync(tmp, { recursive: true });
    const dir = join(tmp, 'espelho');
    mkdirSync(dir, { recursive: true });
    const base = join(tmp, 'baseline.json');

    // BOA — nada que as regras peguem.
    writeFileSync(join(dir, 'ok-page.jsx'), 'const a = 1; export default a;\n');
    writeFileSync(base, JSON.stringify({ chaves: [] }, null, 2));
    let r = rodar(dir, base);
    casos.push(['fixture BOA -> exit 0', r.status === 0, `exit=${r.status}`]);

    // RUIM R1 — classe de aba do dono usada em markup proprio.
    writeFileSync(join(dir, 'aba-page.jsx'), 'const x = <nav className="cli-moduletopnav" />;\n');
    r = rodar(dir, base);
    casos.push(['fixture RUIM (R1 aba) -> exit != 0', r.status !== 0, `exit=${r.status}`]);
    rmSync(join(dir, 'aba-page.jsx'));

    // RUIM R2 — familia de segmented nova, ausente do baseline.
    writeFileSync(join(dir, 'nova-page.css'), '.xx-seg{ display:flex; }\n');
    r = rodar(dir, base);
    casos.push(['fixture RUIM (R2 novo) -> exit != 0', r.status !== 0, `exit=${r.status}`]);
    casos.push(['a mensagem NOMEIA a familia nova', /xx-seg/.test(r.stdout + r.stderr), 'sem .xx-seg na saida']);

    // RUIM R3 — mini design-system novo.
    writeFileSync(join(dir, 'mini.jsx'), 'window.FooUI = {};\n');
    r = rodar(dir, base);
    casos.push(['fixture RUIM (R3 novo) -> exit != 0', r.status !== 0, `exit=${r.status}`]);

    // RUIM R4 — mesmo nome publicado por dois arquivos.
    writeFileSync(join(dir, 'dup-a.jsx'), 'window.Dobrado = 1;\n');
    writeFileSync(join(dir, 'dup-b.jsx'), 'window.Dobrado = 2;\n');
    r = rodar(dir, base);
    casos.push(['fixture RUIM (R4 duplicata) -> exit != 0', r.status !== 0, `exit=${r.status}`]);

    // RUIM R5 — nome publicado que colide com componente do DS.
    // CONTROLE POSITIVO obrigatorio (§5 2026-08-01): no corpus real R5 da 0 achados, e
    // "0" sem prova de mordida e indistinguivel de regra que nao mede. `Badge` vem do
    // proprio REGISTRY_DS_COMPONENTES.md, entao este caso tambem vigia o extrator: se
    // o parser do registro quebrar, a lista fica vazia e este assert cai.
    writeFileSync(join(dir, 'colide.jsx'), 'window.Badge = {};\n');
    r = rodar(dir, base);
    casos.push(['fixture RUIM (R5 colide com DS) -> exit != 0', r.status !== 0, `exit=${r.status}`]);
    casos.push(['R5 nomeia o componente do DS', /Badge/.test(r.stdout + r.stderr), 'sem Badge na saida']);
    casos.push(['o registro do DS foi LIDO (extrator vivo)', (varrer(dir).achados.some((a) => a.regra === 'R5')), 'R5 nao produziu achado nem com colisao plantada']);

    // DELTA — com a divida NO baseline, o mesmo corpus fica verde.
    const todas = varrer(dir).achados.map((a) => a.chave);
    writeFileSync(base, JSON.stringify({ chaves: todas }, null, 2));
    r = rodar(dir, base);
    casos.push(['divida NO baseline -> exit 0 (predicado e DELTA)', r.status === 0, `exit=${r.status}`]);

    // ESCAPE — --aviso-so nunca reprova.
    writeFileSync(base, JSON.stringify({ chaves: [] }, null, 2));
    r = spawnSync(process.execPath, [ESTE, '--check', '--aviso-so', '--dir', dir, '--baseline', base], { encoding: 'utf8' });
    casos.push(['--aviso-so -> exit 0 mesmo com novos', r.status === 0, `exit=${r.status}`]);

    // CONTRATO DO WAIVER — waiver anunciado tem de existir de fato.
    const comWaiver = varrer('prototipo-ui/cowork').waivers.map((w) => w.chave);
    casos.push([
      'waiver declarado e reconhecido pelo scan',
      WAIVERS.size === 0 || comWaiver.length > 0,
      `${comWaiver.length} de ${WAIVERS.size} waiver(s) casaram`,
    ]);

    // BASELINE ILEGIVEL -> exit 2, nunca "vazio".
    writeFileSync(base, '{ nao e json');
    r = rodar(dir, base);
    casos.push(['baseline ilegivel -> exit 2 (nao finge vazio)', r.status === 2, `exit=${r.status}`]);
  } finally {
    rmSync(tmp, { recursive: true, force: true });
  }

  let falhou = 0;
  for (const [nome, ok, detalhe] of casos) {
    console.log(`  ${ok ? 'ok  ' : 'FALHA'} ${nome}${ok ? '' : `  (${detalhe})`}`);
    if (!ok) falhou++;
  }
  console.log(`\n${casos.length - falhou}/${casos.length} asserts`);
  return falhou === 0 ? 0 : 1;
}

// ------------------------------------------------------------------------ CLI
function main() {
  const argv = process.argv.slice(2);
  const arg = (nome, padrao) => {
    const i = argv.indexOf(nome);
    return i >= 0 && argv[i + 1] ? argv[i + 1] : padrao;
  };
  const tem = (f) => argv.includes(f);

  if (tem('--selftest')) process.exit(selftest());

  const dir = arg('--dir', 'prototipo-ui/cowork');
  const caminhoBaseline = arg('--baseline', join(AQUI, 'cowork-pele-paralela.baseline.json'));
  const r = varrer(dir);

  if (tem('--update-baseline')) {
    const antes = lerBaseline(caminhoBaseline);
    const chaves = r.achados.map((a) => a.chave).sort();
    if (antes && chaves.length > antes.chaves.length && !tem('--permitir-crescer')) {
      console.error(
        `[pele-paralela] RECUSADO: o baseline CRESCERIA ${antes.chaves.length} -> ${chaves.length}.\n` +
        '  Baseline de catraca so ENCOLHE. Conserte, declare waiver com motivo, ou\n' +
        '  passe --permitir-crescer se a decisao for consciente e registrada no PR.'
      );
      process.exit(1);
    }
    writeFileSync(
      caminhoBaseline,
      JSON.stringify({ _nota: 'Divida HERDADA de pele paralela. So encolhe. Ver docblock de cowork-pele-paralela.mjs.', dir, chaves }, null, 2) + '\n'
    );
    console.log(`[pele-paralela] baseline gravado: ${chaves.length} chave(s) em ${caminhoBaseline}`);
    process.exit(0);
  }

  const baseline = lerBaseline(caminhoBaseline);
  const conhecidas = new Set(baseline ? baseline.chaves : []);
  const novos = r.achados.filter((a) => !conhecidas.has(a.chave));
  const pagos = [...conhecidas].filter((c) => !r.achados.some((a) => a.chave === c));

  if (tem('--json')) {
    console.log(JSON.stringify({ ...r, novos, pagos, baseline: conhecidas.size }, null, 2));
    process.exit(tem('--measure') || tem('--aviso-so') || novos.length === 0 ? 0 : 1);
  }

  console.log(`pele-paralela · corpus ${r.corpus.arquivos} arquivos (${r.corpus.codigo} js/jsx, ${r.corpus.css} css) em ${dir}`);
  for (const n of r.notas) console.log(`  ! ${n}`);
  if (!baseline) console.log('  ! sem baseline: tudo conta como NOVO (rode --update-baseline pra registrar a divida herdada)');

  if (tem('--measure')) {
    const porRegra = new Map();
    for (const a of r.achados) porRegra.set(a.regra, (porRegra.get(a.regra) || 0) + 1);
    console.log(`\ncenso (${r.achados.length} achado(s), ${r.waivers.length} com waiver):`);
    for (const [reg, n] of [...porRegra].sort()) console.log(`  ${reg}  ${n}`);
    for (const a of r.achados) console.log(`  ${a.regra} ${a.alvo} — ${a.diz}`);
    for (const w of r.waivers) console.log(`  WAIVER ${w.chave} — ${w.motivo}`);
    process.exit(0);
  }

  if (pagos.length) {
    console.log(`\n${pagos.length} divida(s) do baseline PAGA(s) — rode --update-baseline pra travar o ganho:`);
    for (const p of pagos) console.log(`  - ${p}`);
  }

  if (!novos.length) {
    console.log(`\nOK: nenhuma pele paralela NOVA (${conhecidas.size} herdada(s) no baseline, ${r.waivers.length} waiver).`);
    process.exit(0);
  }

  console.log(`\n${novos.length} PELE PARALELA NOVA (nao esta no baseline):`);
  for (const a of novos) console.log(`  ${a.regra}  ${a.alvo}\n       ${a.diz}\n       conserto: ${a.conserto}`);
  console.log(
    '\nCada uma: use o dono, ou declare waiver com MOTIVO no WAIVERS de\n' +
    'scripts/qa/cowork-pele-paralela.mjs. Encolher a lista e progresso; crescer exige ADR.'
  );
  process.exit(tem('--aviso-so') ? 0 : 1);
}

if (process.argv[1] && process.argv[1].endsWith('cowork-pele-paralela.mjs')) main();
