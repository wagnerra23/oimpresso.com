#!/usr/bin/env node
// Teste do permission-drift.mjs. Hermético: injeta conteúdo, não lê o repo.
//
// Cada CONTROLE NEGATIVO aqui corresponde a uma classe de falso-positivo que foi
// MEDIDA no corpus real (2026-07-27) e que teria feito o gate reprovar código
// correto. Sem eles o censo acusava 79-89 órfãs; com eles, 47.
// Rodar: node scripts/governance/permission-drift.test.mjs

import { coletarDeclaradas, coletarUsadas, abilitiesDoGateBefore } from './permission-drift.mjs';

let fails = 0;
const check = (n, c) => { console.log((c ? '[OK]   ' : '[FAIL] ') + n); if (!c) fails++; };
const reader = (mapa) => (rel) => mapa[rel] ?? '';

// ── DECLARAÇÃO: as 5 formas ─────────────────────────────────────────────────
const decl = coletarDeclaradas(
  ['Modules/X/Http/Controllers/DataController.php', 'Modules/X/Resources/permissions.php',
   'resources/views/role/create.blade.php', 'database/seeders/PermissionsTableSeeder.php',
   'app/Utils/BusinessUtil.php'],
  reader({
    'Modules/X/Http/Controllers/DataController.php': "['value' => 'x.access', 'label' => 'a']",
    'Modules/X/Resources/permissions.php': "['key' => 'x.registry', 'risk' => 'low']",
    'resources/views/role/create.blade.php':
      "{!! Form::checkbox('permissions[]', 'x.checkbox', false) !!}\n{!! Form::radio('radio_option[v]', 'x.radio', false) !!}",
    'database/seeders/PermissionsTableSeeder.php': "['name' => 'x.seeder'],",
    'app/Utils/BusinessUtil.php': "\$role->syncPermissions(['x.sync', 'x.sync2']);",
  }),
);
check('declara: DataController', decl.has('x.access'));
check('declara: Resources/permissions.php', decl.has('x.registry'));
check('declara: Form::checkbox', decl.has('x.checkbox'));
check('declara: Form::radio (5ª forma — medida no corpus)', decl.has('x.radio'));
check('declara: seeder do core (4ª fonte — medida no corpus)', decl.has('x.seeder'));
check('declara: syncPermissions em runtime', decl.has('x.sync') && decl.has('x.sync2'));

// ── USO: as formas de enforcement ───────────────────────────────────────────
const u = (src) => coletarUsadas(['f.php'], reader({ 'f.php': src })).usadas;
check("usa: ->can('p')", u("\$u->can('p.um')").has('p.um'));
check("usa: @can('p')", u("@can('p.dois')").has('p.dois'));
check('usa: Gate::allows', u("Gate::allows('p.tres')").has('p.tres'));
check('usa: hasPermissionTo', u("\$r->hasPermissionTo('p.quatro')").has('p.quatro'));
check('usa: middleware can:', u("->middleware('can:p.cinco')").has('p.cinco'));
check("usa: menu 'can' =>", u("['label'=>'X','can'=>'p.seis']").has('p.seis'));
check('usa: array em canany', u("\$u->canany(['p.sete','p.oito'])").has('p.sete'));

// ── CONTROLES NEGATIVOS (cada um = um FP medido no corpus real) ─────────────
const policy = u("Gate::authorize('create', Subscription::class); \$u->can('view', \$m);");
check('FP-1: ability de Policy (2 argumentos) NÃO conta como permissão',
  !policy.has('create') && !policy.has('view'));

const gb = abilitiesDoGateBefore(
  "Gate::before(function (\$user, \$ability) { if (in_array(\$ability, ['backup', 'superadmin', 'manage_modules', ])) { return true; } });",
);
check('FP-3: abilities do Gate::before derivadas do arquivo (3)',
  gb.has('backup') && gb.has('superadmin') && gb.has('manage_modules') && gb.size === 3);
check('FP-3: parse falha → conjunto VAZIO (falha visível, nunca silenciosa)',
  abilitiesDoGateBefore('class Foo {}').size === 0);

const din = coletarUsadas(['d.php'], reader({ 'd.php': "\$u->can(\$perm); \$u->can(self::PERM);" }));
check('FP-2: alvo dinâmico vai pra quarentena, não vira permissão',
  din.usadas.size === 0 && din.dinamicas === 2);

// ── MORDE: o drift real que motivou o script ────────────────────────────────
// Caso literal do repo: código checa `copiloto.superadmin`, registry declara
// `jana.superadmin`. Ninguém consegue conceder → gate efetivamente só-admin.
const d2 = coletarDeclaradas(['Modules/Jana/Resources/permissions.php'],
  reader({ 'Modules/Jana/Resources/permissions.php': "['key' => 'jana.superadmin']" }));
const u2 = coletarUsadas(['r.php'], reader({ 'r.php': "->middleware('can:copiloto.superadmin')" })).usadas;
const orfas = [...u2.keys()].filter((p) => !d2.has(p));
check('MORDE: renome copiloto.*→jana.* vira órfã detectada', orfas.includes('copiloto.superadmin'));

const teatro = [...d2.keys()].filter((p) => !u2.has(p));
check('MORDE: declarada e nunca aplicada vira teatro detectado (caso jana.chat)',
  teatro.includes('jana.superadmin'));

// controle: declarada E usada não aparece em nenhuma das listas
const d3 = coletarDeclaradas(['Modules/Y/Resources/permissions.php'],
  reader({ 'Modules/Y/Resources/permissions.php': "['key' => 'y.ok']" }));
const u3 = coletarUsadas(['y.php'], reader({ 'y.php': "\$u->can('y.ok')" })).usadas;
check('controle: declarada E usada → limpa nas duas direções',
  [...u3.keys()].filter((p) => !d3.has(p)).length === 0
  && [...d3.keys()].filter((p) => !u3.has(p)).length === 0);

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — extrai as 5 formas de declaração, 7 de uso, e os 3 controles de FP medidos no corpus real.');
process.exit(fails ? 1 : 0);
