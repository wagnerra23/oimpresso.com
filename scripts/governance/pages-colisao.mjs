#!/usr/bin/env node
// @ts-check
/**
 * pages-colisao.mjs — barra DUAS fontes declarando a mesma página Inertia.
 *
 * POR QUE EXISTE: desde 2026-08-12 uma tela pode morar no núcleo (`resources/js/Pages/**`) OU
 * dentro do módulo dono (`Modules/<X>/Resources/js/Pages/**`). O resolver mescla os dois globs
 * e normaliza a chave pro mesmo namespace — é isso que faz `Inertia::render('Settings/…')`
 * funcionar sem que nenhum dos 232 call-sites mude.
 *
 * O preço dessa mescla é que a colisão é SILENCIOSA por construção — medido em POC no mesmo dia:
 * com `Whatsapp` e `Crm` declarando `Atendimento/CaixaUnificada/Index`, o build sai exit 0, um
 * dos dois VENCE e o outro SOME sem erro, sem warning, sem chunk faltando. Nenhum gate existente
 * pega: o `--all --check` do module-surface compara índice com árvore (os dois arquivos existem,
 * nada drifta) e o `casos-gate` mede cobertura, não unicidade.
 *
 * O que ele mede: a chave RESOLVÍVEL (namespace + caminho + `.tsx`), exatamente como o resolver
 * de `app.tsx`/`ssr.tsx` a monta. Não mede nome de arquivo, nem semelhança — colisão aqui é
 * identidade de chave, que é determinística e não admite julgamento.
 *
 * Uso:
 *   node scripts/governance/pages-colisao.mjs            (relatório)
 *   node scripts/governance/pages-colisao.mjs --check     (CI: exit 1 se colidir)
 *   node scripts/governance/pages-colisao.mjs --selftest  (bite-test em fixture)
 *
 * Refs: `.claude/rules/pages.md` (o local das Pages é convenção nossa) · ADR 0275 (nasce advisory).
 */
import { execFileSync } from 'node:child_process';

const args = process.argv.slice(2);
const CHECK = args.includes('--check');
const SELFTEST = args.includes('--selftest');
const ROOT = process.cwd();

// `Resources` MAIÚSCULO é a convenção nWidart deste repo — medido 2026-08-12: 711 arquivos sob
// `Modules/*/Resources/` contra 12 sob `resources/`. O casing aqui não é detalhe: quando o rename
// do piloto foi feito, este regex ainda dizia minúsculo e o total caiu de 445 pra 438 em silêncio
// — as 7 telas do módulo sumiram do índice sem nenhum erro. É por isso que o relatório IMPRIME o
// total: um detector que não diz quantos viu não deixa perceber quando ele para de ver.
// O `[Rr]` aceita as duas grafias para que casing errado apareça como COLISÃO (mensagem), e não
// como ausência (silêncio) — espelha o regex de normalização de `app.tsx`/`ssr.tsx`.
const RE_MODULO = /^Modules\/([^/]+)\/[Rr]esources\/js\/Pages\/(.+\.tsx)$/;
const RE_NUCLEO = /^resources\/js\/Pages\/(.+\.tsx)$/;

/** Universo do checkout pelo índice Git (autoridade de casing — o FS do Windows colapsa). */
function inventario() {
  const raw = execFileSync(
    'git',
    ['-c', `safe.directory=${ROOT.replaceAll('\\', '/')}`, 'ls-files', '-z'],
    { cwd: ROOT, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] },
  );
  return raw.split('\0').filter(Boolean);
}

/**
 * Mapeia cada path para a chave que o resolver produz.
 * Espelha `montarPaginas()` de app.tsx — se um mudar, o outro tem que mudar junto.
 * @param {string[]} paths
 * @returns {Map<string, {path: string, fonte: string}[]>}
 */
export function indexarPaginas(paths) {
  /** @type {Map<string, {path: string, fonte: string}[]>} */
  const porChave = new Map();
  for (const p of paths) {
    const caminho = p.replaceAll('\\', '/');
    let chave = null;
    let fonte = null;
    const mod = caminho.match(RE_MODULO);
    const nuc = caminho.match(RE_NUCLEO);
    if (mod) { chave = mod[2]; fonte = `Modules/${mod[1]}`; }
    else if (nuc) { chave = nuc[1]; fonte = 'núcleo'; }
    if (!chave) continue;
    const lista = porChave.get(chave) ?? [];
    lista.push({ path: caminho, fonte });
    porChave.set(chave, lista);
  }
  return porChave;
}

function relatorio() {
  const porChave = indexarPaginas(inventario());
  const colisoes = [...porChave.entries()].filter(([, v]) => v.length > 1);
  const noModulo = [...porChave.values()].filter((v) => v.some((x) => x.fonte !== 'núcleo')).length;

  console.log(`\n  PAGES-COLISÃO — ${porChave.size} página(s) resolvível(is) · ${noModulo} já hospedada(s) em módulo\n`);
  if (!colisoes.length) {
    console.log('  ✓ nenhuma chave declarada por duas fontes.\n');
    return 0;
  }
  console.error(`  ✗ ${colisoes.length} chave(s) declarada(s) por MAIS DE UMA fonte — uma delas some sem erro:\n`);
  for (const [chave, fontes] of colisoes.sort()) {
    console.error(`      Inertia::render('${chave.replace(/\.tsx$/, '')}')`);
    for (const f of fontes) console.error(`        ← ${f.fonte}: ${f.path}`);
  }
  console.error('\n      Uma tela por chave. Escolha o dono e apague a outra cópia.\n');
  return 1;
}

/** Bite-test: fixture ruim MORDE, boa passa. Sem isto o gate é promessa. */
function selftest() {
  let falhas = 0;
  const ok = (cond, nome) => { console.log(`  ${cond ? '✓' : '✗'} ${nome}`); if (!cond) falhas++; };

  const bom = indexarPaginas([
    'resources/js/Pages/Sells/Index.tsx',
    'Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.tsx',
    'Modules/Whatsapp/Resources/js/Pages/Atendimento/Index.tsx',
  ]);
  ok([...bom.values()].every((v) => v.length === 1), 'fixture BOA: 3 chaves distintas, nenhuma colisão');
  ok(bom.has('Settings/PaymentGateways/Index.tsx'), 'tela em módulo indexa pelo NAMESPACE, não pelo path do módulo');

  const ruim = indexarPaginas([
    'Modules/Whatsapp/Resources/js/Pages/Atendimento/Index.tsx',
    'Modules/Crm/Resources/js/Pages/Atendimento/Index.tsx',
  ]);
  ok(ruim.get('Atendimento/Index.tsx')?.length === 2, 'fixture RUIM: dois módulos na mesma chave são detectados');

  const misto = indexarPaginas([
    'resources/js/Pages/Settings/PaymentGateways/Index.tsx',
    'Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/Index.tsx',
  ]);
  ok(misto.get('Settings/PaymentGateways/Index.tsx')?.length === 2, 'RUIM: núcleo + módulo na mesma chave (move pela metade) é detectado');

  // Controle negativo: o que NÃO é página resolvível não pode entrar no índice e virar FP.
  const ignora = indexarPaginas([
    'resources/js/Pages/Sells/Index.charter.md',
    'resources/js/Pages/Financeiro/_cowork-bundle/x.jsx',
    'Modules/Whatsapp/Resources/js/Components/Botao.tsx',
    'resources/js/Components/Botao.tsx',
  ]);
  ok(ignora.size === 0, 'controle negativo: charter, .jsx e Components fora de Pages NÃO entram');

  console.log(falhas ? `\n  ${falhas} falha(s)\n` : '\n  selftest OK\n');
  return falhas ? 1 : 0;
}

process.exit(SELFTEST ? selftest() : CHECK ? relatorio() : (relatorio(), 0));
