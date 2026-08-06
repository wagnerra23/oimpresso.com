#!/usr/bin/env node
// permission-drift.mjs — mede o drift entre permissão DECLARADA e permissão APLICADA.
//
// ── POR QUE EXISTE (o defeito que ele mede) ──────────────────────────────────
// O oimpresso declara permissão Spatie em TRÊS lugares sem autoridade única, e
// NADA verifica se o que foi declarado é de fato usado como gate — nem o inverso.
// Consequências já provadas à mão (2026-07-27):
//
//   · `jana.chat` — declarada, com label, no menu do topo, e NUNCA num gate.
//     A rota /ia não tem `can:` nenhum. A permissão MENTE pro admin que a marca:
//     ele acha que controlou acesso e só escondeu um item de menu.
//   · `copiloto.superadmin` — USADA em `Modules/Jana/Http/routes.php:167`
//     (`->middleware('can:copiloto.superadmin')`) e declarada em lugar NENHUM
//     (o registry declara `jana.superadmin` — drift de renome Copiloto→Jana).
//     Ninguém consegue conceder essa permissão. E como o `Gate::before` de
//     `app/Providers/AuthServiceProvider.php` devolve `true` pra quem tem
//     `Admin#{business_id}`, a rota que o comentário diz ser "Wagner inicial"
//     está aberta a TODO admin de negócio.
//
// É a classe LC-11 (presença ≠ comportamento) aplicada a permissão: declarar não
// é aplicar, e aparecer na tela de papéis não é proteger nada.
//
// ── O QUE ELE NÃO FAZ (limites honestos, medidos) ────────────────────────────
// 1. NÃO decide se a permissão está no gate CERTO — só se existe gate.
// 2. Checagem DINÂMICA (`can($var)`, `can(self::PERM)`) é indecidível por texto.
//    Elas são CONTADAS e reportadas, e todo alvo dinâmico entra em quarentena
//    (nunca vira achado) — senão o gate acusaria código correto.
// 3. `Gate::before` (admin bypass) faz TODA permissão passar pra `Admin#{biz}`.
//    Logo "tem gate" nunca significa "está protegido do dono". Fora de escopo.
//
// Uso:
//   node scripts/governance/permission-drift.mjs --measure   # censo + FP no corpus
//   node scripts/governance/permission-drift.mjs --json
//   node scripts/governance/permission-drift.mjs --selftest
//
// Ainda SEM veredito de CI: esta rodada é medição (a regra do §5 é medir o
// falso-positivo ANTES de instalar gate). Ver relatório no fim do --measure.

import { readFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');

/** roda git com args, devolve stdout (string vazia em erro). */
function git(args) {
  try {
    return execFileSync('git', args, { cwd: ROOT, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  } catch { return ''; }
}

/** lista arquivos trackeados que casam um pathspec do git. */
export function lsFiles(pathspec) {
  return git(['ls-files', pathspec]).split('\n').map((s) => s.trim()).filter(Boolean);
}

const readSafe = (rel) => { try { return readFileSync(join(ROOT, rel), 'utf8'); } catch { return ''; } };

// ── DECLARAÇÃO — as 3 fontes ────────────────────────────────────────────────
// Nenhuma é "a" fonte; o gate precisa das três pra ter denominador honesto.
// ⚠️ São QUATRO, não três. A 4ª (seeder do core) foi achada MEDINDO: sem ela, 89
// órfãs incluíam `access_all_locations`, `admin`, `backup`, `configure_dashboard`
// — permissões core perfeitamente declaradas, só que num lugar que o extrator
// não lia. Denominador incompleto fabrica achado; é a lápide §5 2026-07-15.
export const FONTES_DECLARACAO = [
  { id: 'datacontroller', glob: 'Modules/*/Http/Controllers/DataController.php', re: /'value'\s*=>\s*'([a-zA-Z][\w.\-]*)'/g },
  { id: 'registry', glob: 'Modules/*/Resources/permissions.php', re: /'key'\s*=>\s*'([a-zA-Z][\w.\-]*)'/g },
  { id: 'blade-role', glob: 'resources/views/role/*.blade.php', re: /Form::checkbox\(\s*'permissions\[\]'\s*,\s*'([a-zA-Z][\w.\-]*)'/g },
  // 5ª forma: grupo de RADIO. `all_expense.access` / `view_own_sell_only` etc são
  // exclusivos entre si (ver todas × ver só as próprias), então a tela usa radio.
  // Achado medindo — sem isto, permissão declarada e concedível virava "órfã".
  { id: 'blade-radio', glob: 'resources/views/role/*.blade.php', re: /Form::radio\(\s*'[^']*'\s*,\s*'([a-zA-Z][\w.\-]*)'/g },
  { id: 'seeder-core', glob: 'database/seeders/PermissionsTableSeeder.php', re: /'name'\s*=>\s*'([a-zA-Z][\w.\-]*)'/g },
  // syncPermissions/givePermissionTo em runtime (BusinessUtil semeia o papel Cashier
  // com `access_all_locations` etc). Conceder é uma forma de declarar: a permissão
  // existe e é atribuível, mesmo sem checkbox.
  { id: 'sync-runtime', glob: 'app/**/*.php', re: /(?:syncPermissions|givePermissionTo|->permission)\(\s*\[([^\]]*)\]/g, lista: true },
];

export function coletarDeclaradas(files = null, reader = readSafe) {
  const out = new Map(); // perm -> Set(origem)
  for (const f of FONTES_DECLARACAO) {
    for (const rel of (files ?? lsFiles(f.glob))) {
      const txt = reader(rel);
      if (!txt) continue;
      for (const m of txt.matchAll(f.re)) {
        const alvos = f.lista ? [...m[1].matchAll(/'([a-zA-Z][\w.\-]*)'/g)].map((x) => x[1]) : [m[1]];
        for (const p of alvos) {
          if (!out.has(p)) out.set(p, new Set());
          out.get(p).add(f.id);
        }
      }
    }
  }
  return out;
}

// ── USO — todas as formas de enforcement que o Laravel/Spatie aceita ─────────
// Incompleto = falso-positivo garantido. A 1ª versão à mão só via `can('x')`,
// `@can` e `permission:` — e por isso inflou "declarada sem uso" pra 82.
// ⚠️ O `\)` final NÃO é decoração — é o discriminador que separa PERMISSÃO de
// ABILITY DE POLICY. `Gate::authorize('create', Subscription::class)` e
// `->can('view', $model)` têm SEGUNDO argumento: o Laravel resolve por Policy,
// não por permissão Spatie. Sem esta trava o censo acusava `create`/`cancel` do
// RecurringBillingController — código correto — como permissão órfã. Medido.
export const FORMAS_USO = [
  { id: 'can', re: /(?:->|@|\s|\()can(?:any)?\(\s*'([a-zA-Z][\w.\-]*)'\s*\)/g },
  { id: 'can-array', re: /(?:->|@|\s|\()can(?:any)?\(\s*\[([^\]]*)\]\s*\)/g, lista: true },
  { id: 'cannot', re: /->cannot\(\s*'([a-zA-Z][\w.\-]*)'\s*\)/g },
  { id: 'gate', re: /Gate::(?:allows|denies|check|authorize|any)\(\s*'([a-zA-Z][\w.\-]*)'\s*\)/g },
  { id: 'gate-array', re: /Gate::(?:allows|denies|check|any)\(\s*\[([^\]]*)\]\s*\)/g, lista: true },
  { id: 'spatie', re: /has(?:Permission|DirectPermission|PermissionTo|AnyPermission|AllPermissions)\w*\(\s*'([a-zA-Z][\w.\-]*)'\s*\)/g },
  { id: 'spatie-array', re: /has(?:AnyPermission|AllPermissions)\(\s*\[([^\]]*)\]\s*\)/g, lista: true },
  { id: 'authorize', re: /(?:->|\s)authorize\(\s*'([a-zA-Z][\w.\-]*)'\s*\)/g },
  { id: 'middleware', re: /(?:can|permission):([a-zA-Z][\w.\-]*)/g },
  { id: 'menu', re: /'can'\s*=>\s*'([a-zA-Z][\w.\-]*)'/g },
  { id: 'blade-directive', re: /@(?:can|cannot|canany)\(\s*'([a-zA-Z][\w.\-]*)'\s*\)/g },
];

// Chamada com alvo NÃO-literal — indecidível por texto. Quem cai aqui vai pra
// quarentena: o gate nunca acusa, porque não dá pra saber qual permissão é.
export const RE_DINAMICO = /(?:->can\(\s*\$|->can\(\s*self::|->can\(\s*static::|Gate::allows\(\s*\$|hasPermissionTo\(\s*\$|authorize\(\s*\$)/g;

const GLOBS_CODIGO = ['Modules/**/*.php', 'app/**/*.php', 'resources/views/**/*.php', 'routes/*.php'];

// COMENTÁRIO NÃO É USO. O detector lia prosa como se fosse código: a forma
// `middleware` (/(?:can|permission):([a-zA-Z][\w.\-]*)/) casa dentro de qualquer
// texto, e o corpus tem 36 linhas de comentário citando `can:`/`permission:`.
// Três "órfãs" reportadas em 2026-08-06 eram exatamente isso, e nenhuma era
// permissão de verdade:
//   · `x`            ← `// … por formatação do gate (\`can:x\` vs \`can:x,arg\`)`
//   · `kb.`          ← `* … (Spatie — pra middleware can:kb.*)` — o `*` do glob
//                       corta a captura, sobrando o prefixo com ponto
//   · `financeiro.`  ← `// … via middleware can:financeiro.{a|b}.baixar` — para no `{`
// Uma permissão citada SÓ em comentário não é usada; contá-la infla o número que
// o time triaria. Achado ao triar a US-GOV-059.
//
// CONSERVADOR de propósito: descarta apenas a linha cujo conteúdo COMEÇA com
// marcador de comentário. NÃO faz strip de `//` no meio da linha — isso quebraria
// `'url' => 'https://…'` e mataria uso legítimo em `$this->middleware('can:x'); // nota`,
// que continua sendo lido inteiro. `#[Attribute]` do PHP 8 é preservado (não é comentário).
export const semComentarios = (txt) => txt.split('\n').map((l) => {
  const t = l.trimStart();
  const ehComentario = t.startsWith('//') || t.startsWith('*') || t.startsWith('/*')
    || (t.startsWith('#') && !t.startsWith('#['));
  return ehComentario ? '' : l;
}).join('\n');

export function coletarUsadas(files = null, reader = readSafe) {
  const usadas = new Map(); // perm -> Set(arquivo)
  let dinamicas = 0;
  const arquivosDinamicos = new Set();
  const lista = files ?? GLOBS_CODIGO.flatMap((g) => lsFiles(g));
  for (const rel of lista) {
    const bruto = reader(rel);
    if (!bruto) continue;
    const txt = semComentarios(bruto);
    for (const forma of FORMAS_USO) {
      for (const m of txt.matchAll(forma.re)) {
        const alvos = forma.lista
          ? [...m[1].matchAll(/'([a-zA-Z][\w.\-]*)'/g)].map((x) => x[1])
          : [m[1]];
        for (const p of alvos) {
          if (!usadas.has(p)) usadas.set(p, new Set());
          usadas.get(p).add(rel);
        }
      }
    }
    const din = [...txt.matchAll(RE_DINAMICO)].length;
    if (din > 0) { dinamicas += din; arquivosDinamicos.add(rel); }
  }
  return { usadas, dinamicas, arquivosDinamicos };
}

/**
 * Abilities tratadas pelo `Gate::before` (app/Providers/AuthServiceProvider.php):
 * são resolvidas por LISTA DE USERNAME (`constants.administrator_usernames`), e por
 * isso NÃO são declaradas como permissão concedível — de propósito. Acusá-las de
 * órfãs é reprovar código correto.
 *
 * DERIVADO do arquivo, nunca hardcodado: se alguém mexer na lista lá, esta acompanha
 * sozinha (ADR 0256 — derivado sobrevive, escrito apodrece). Se o parse falhar,
 * devolve vazio e o censo volta a acusá-las — falha VISÍVEL, nunca silenciosa.
 */
export function abilitiesDoGateBefore(txt = readSafe('app/Providers/AuthServiceProvider.php')) {
  const bloco = txt.match(/Gate::before\([\s\S]{0,400}?in_array\(\s*\$ability\s*,\s*\[([^\]]*)\]/);
  if (!bloco) return new Set();
  return new Set([...bloco[1].matchAll(/'([a-zA-Z][\w.\-]*)'/g)].map((m) => m[1]));
}

/** censo completo — as duas direções de drift + a quarentena dos dinâmicos. */
export function medir() {
  const declaradas = coletarDeclaradas();
  const { usadas, dinamicas, arquivosDinamicos } = coletarUsadas();
  const gateBefore = abilitiesDoGateBefore();
  const orfas = [...usadas.keys()].filter((p) => !declaradas.has(p) && !gateBefore.has(p)).sort();
  const teatro = [...declaradas.keys()].filter((p) => !usadas.has(p)).sort();
  return {
    declaradas, usadas, dinamicas, arquivosDinamicos, gateBefore,
    n_declaradas: declaradas.size,
    n_usadas: usadas.size,
    orfas,      // usada no código, declarada em lugar nenhum → ninguém consegue conceder
    teatro,     // declarada na tela de papéis, nunca aplicada → mente pro admin
  };
}

function main() {
  const argv = process.argv.slice(2);
  const r = medir();
  if (argv.includes('--json')) {
    process.stdout.write(JSON.stringify({
      n_declaradas: r.n_declaradas, n_usadas: r.n_usadas,
      orfas: r.orfas, teatro: r.teatro,
      chamadas_dinamicas: r.dinamicas, arquivos_dinamicos: r.arquivosDinamicos.size,
    }, null, 2) + '\n');
    process.exit(0);
  }
  console.log('=== permission-drift — censo (MEDIÇÃO, sem veredito de CI) ===\n');
  console.log(`declaradas: ${r.n_declaradas}   usadas (alvo literal): ${r.n_usadas}`);
  console.log(`chamadas com alvo DINÂMICO: ${r.dinamicas} em ${r.arquivosDinamicos.size} arquivos (quarentena — indecidível por texto)\n`);
  console.log(`── ÓRFÃS (${r.orfas.length}) — usadas no código, declaradas em lugar nenhum`);
  console.log('   ninguém consegue conceder; com o Gate::before, viram "só admin" por acidente');
  for (const p of r.orfas.slice(0, 25)) console.log(`   · ${p}   [${[...(r.usadas.get(p) ?? [])][0] ?? ''}]`);
  if (r.orfas.length > 25) console.log(`   … +${r.orfas.length - 25}`);
  console.log(`\n── TEATRO (${r.teatro.length}) — declaradas na tela de papéis, nunca aplicadas`);
  console.log('   o admin marca o checkbox achando que controlou acesso (caso jana.chat)');
  for (const p of r.teatro.slice(0, 25)) console.log(`   · ${p}`);
  if (r.teatro.length > 25) console.log(`   … +${r.teatro.length - 25}`);
  console.log('\n(advisory por construção nesta fase — decidir forma de gate SÓ depois do FP medido)');
  process.exit(0);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  if (process.argv.includes('--selftest')) {
    const t = new URL('./permission-drift.test.mjs', import.meta.url);
    const { status } = (await import('node:child_process')).spawnSync(process.execPath, [fileURLToPath(t)], { stdio: 'inherit' });
    process.exit(status ?? 1);
  }
  main();
}
