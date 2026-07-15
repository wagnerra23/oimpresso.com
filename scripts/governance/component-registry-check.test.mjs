#!/usr/bin/env node
// @ts-check
// SELF-TEST — prova que component-registry-check.mjs DETECTA drift (--strict exit 1) e
// LIBERA quando o registro bate com o código (exit 0). Monta repo-fixture temporário com
// componentes React fake + um registry, e varia o registry pra exercitar cada modo de drift.
// Rodar: node scripts/governance/component-registry-check.test.mjs — exit 0 = passa.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'component-registry-check.mjs');

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

// ── fixture: repo com 2 componentes ui + 1 barril de layout ──
const root = mkdtempSync(join(tmpdir(), 'compreg-'));
const uiDir = join(root, 'resources', 'js', 'Components', 'ui');
const layoutDir = join(root, 'resources', 'js', 'Components', 'layout');
mkdirSync(uiDir, { recursive: true });
mkdirSync(layoutDir, { recursive: true });
mkdirSync(join(root, 'prototipo-ui'), { recursive: true });

writeFileSync(join(uiDir, 'button.tsx'), `
export function Button() { return null }
export { Button as default }
export const buttonVariants = () => {}
`);
writeFileSync(join(uiDir, 'badge.tsx'), `export { Badge, badgeVariants }\n`);
// barril que re-exporta de outro arquivo (testa export-from)
writeFileSync(join(layoutDir, 'box.tsx'), `export function Box() { return null }\n`);
writeFileSync(join(layoutDir, 'index.ts'), `export { Box, type BoxProps } from "./box"\n`);

const REG = join(root, 'prototipo-ui', 'component-registry.json');
const writeReg = (entries) => writeFileSync(REG, JSON.stringify({ version: '1', entries }, null, 2));
const run = (extra = []) => spawnSync('node', [SCRIPT, '--root', root, '--registry', REG, ...extra], { encoding: 'utf8' });

// registro BOM (bate com tudo): Button (com re-export-from no barril), gap explícito
const goodEntries = [
  { bloco_prototipo: '.cw-btn-primary', componente_react: 'Button', import_path: '@/Components/ui/button', file: 'resources/js/Components/ui/button.tsx', exports: ['Button', 'buttonVariants', 'default'], status: 'mapped' },
  { bloco_prototipo: '.badge', componente_react: 'Badge', import_path: '@/Components/ui/badge', file: 'resources/js/Components/ui/badge.tsx', exports: ['Badge', 'badgeVariants'], status: 'mapped' },
  { bloco_prototipo: 'Box', componente_react: 'Box', import_path: '@/Components/layout', file: 'resources/js/Components/layout/index.ts', exports: ['Box'], status: 'mapped' },
  { bloco_prototipo: 'c-nba (buraco DS)', componente_react: null, import_path: null, file: null, exports: [], status: 'gap' },
];

// 1. LIBERA: registro íntegro → advisory exit 0 E strict exit 0
writeReg(goodEntries);
const okAdvisory = run(['--check']);
check('advisory passa com registro íntegro (exit 0)', okAdvisory.status === 0);
const okStrict = run(['--check', '--strict']);
check('strict passa com registro íntegro (exit 0)', okStrict.status === 0);
check('barril re-export-from resolve (Box via index.ts → box.tsx)', /íntegro/.test(okStrict.stdout));

// 2. DRIFT — export removido: registry pede símbolo que não existe
writeReg([{ ...goodEntries[0], exports: ['Button', 'FantasmaQueNaoExiste'] }]);
const missExport = run(['--check', '--strict']);
check('strict MORDE export ausente (exit 1)', missExport.status === 1);
check('aponta o export fantasma', /FantasmaQueNaoExiste/.test(missExport.stdout));
check('advisory NÃO morde (exit 0) mesmo com drift', run(['--check']).status === 0);

// 3. DRIFT — import_path quebrado (componente renomeado/movido)
writeReg([{ ...goodEntries[0], import_path: '@/Components/ui/botao-renomeado' }]);
const badImport = run(['--check', '--strict']);
check('strict MORDE import_path que não resolve (exit 1)', badImport.status === 1);
check('aponta o import quebrado', /não resolve/.test(badImport.stdout));

// 4. DRIFT — file não existe (componente deletado)
writeReg([{ ...goodEntries[0], file: 'resources/js/Components/ui/deletado.tsx', import_path: '@/Components/ui/deletado' }]);
const noFile = run(['--check', '--strict']);
check('strict MORDE file inexistente (exit 1)', noFile.status === 1);

// 5. DRIFT — gap FABRICADO: status gap mas com file/import (M-AP-6 violado)
writeReg([{ bloco_prototipo: 'c-id fabricado', componente_react: 'FichaId', import_path: '@/Components/ui/button', file: 'resources/js/Components/ui/button.tsx', exports: ['Button'], status: 'gap' }]);
const fabricatedGap = run(['--check', '--strict']);
check('strict MORDE gap com fabricação (exit 1)', fabricatedGap.status === 1);
check('aponta fabricação no gap', /fabricação/.test(fabricatedGap.stdout));

// 6. gap LEGÍTIMO (sem file/import/exports) NÃO morde
writeReg([{ bloco_prototipo: 'c-tl', componente_react: null, import_path: null, file: null, exports: [], status: 'gap' }]);
check('gap legítimo passa (exit 0)', run(['--check', '--strict']).status === 0);

// ── MODO --roles: detector de papel-duplicado (ADR proposta tab-nav-canonico) ──
const sharedDir = join(root, 'resources', 'js', 'Components', 'shared');
const fakePages = join(root, 'resources', 'js', 'Pages', 'Fake');
mkdirSync(sharedDir, { recursive: true });
mkdirSync(fakePages, { recursive: true });
// canon do papel (path EXATO que ROLE_SIGNATURES canoniza) + markup de barra de topo
writeFileSync(join(sharedDir, 'PageHeaderTabs.tsx'),
  `export default function PageHeaderTabs(){ return <div role="tablist" className="moduletopnav" /> }\n`);
// consumidor legítimo: importa o canon (wrapper)
writeFileSync(join(fakePages, 'FakeSubNav.tsx'),
  `import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';\nexport default function FakeSubNav(){ return <PageHeaderTabs /> }\n`);

// 7. LIBERA: só canon + consumer → sem independente → advisory E strict exit 0
const rolesClean = run(['--roles']);
check('roles: advisory exit 0 sem hand-roll independente', rolesClean.status === 0);
check('roles: strict exit 0 sem hand-roll independente', run(['--roles', '--strict']).status === 0);
check('roles: classifica o wrapper como consumidor (não drift)', /FakeSubNav/.test(rolesClean.stdout) && /consumidores/.test(rolesClean.stdout));

// 8. DRIFT: injeta hand-roll INDEPENDENTE (markup de topo, NÃO importa o canon)
writeFileSync(join(fakePages, 'FakeTopNav.tsx'),
  `export default function FakeTopNav(){ return <nav role="tablist" className="fx-subtabs" /> }\n`);
const rolesDrift = run(['--roles']);
check('roles: advisory NÃO morde mesmo com drift (exit 0)', rolesDrift.status === 0);
check('roles: strict MORDE hand-roll independente (exit 1)', run(['--roles', '--strict']).status === 1);
check('roles: aponta o independente FakeTopNav', /FakeTopNav/.test(rolesDrift.stdout) && /INDEPENDENTE/i.test(rolesDrift.stdout));
check('roles: NÃO marca o consumer como independente', !new RegExp('⚠️[^\\n]*FakeSubNav').test(rolesDrift.stdout));

// 9. TRANSITIVO — tela que renderiza o wrapper (FakeSubNav → importa o canon) +
// tem markup de topo NÃO é independente: consome o papel via wrapper. Sem essa
// regra, Financeiro/Unificado (importa FinanceiroSubNav + tablist de drawer)
// virava falso-positivo de drift.
writeFileSync(join(fakePages, 'FakeUnificado.tsx'),
  `import FakeSubNav from '@/Pages/Fake/FakeSubNav';\nexport default function FakeUnificado(){ return <div><FakeSubNav /><nav role="tablist" className="subnav-inner" /></div> }\n`);
const rolesTrans = run(['--roles']);
check('roles: consumo TRANSITIVO (renderiza wrapper) conta como consumidor', new RegExp('✓[^\\n]*FakeUnificado').test(rolesTrans.stdout));
check('roles: consumo TRANSITIVO NÃO é marcado independente', !new RegExp('⚠️[^\\n]*FakeUnificado').test(rolesTrans.stdout));
check('roles: FakeTopNav (sem wrapper nem canon) segue independente', new RegExp('⚠️[^\\n]*FakeTopNav').test(rolesTrans.stdout));

// ── MODO --roles: papel "combobox" (campo de busca com dropdown · esta onda) ──
// canon do papel = o MOTOR cmdk (self-matcha pelo basename command.tsx). Escrito
// em Components/ui (mesmo dir das fixtures button/badge, que NÃO casam combobox).
writeFileSync(join(uiDir, 'command.tsx'),
  `import { Command as CommandPrimitive } from 'cmdk';\nexport function Command(p){ return <CommandPrimitive {...p} /> }\nexport function CommandInput(){ return null }\n`);
// consumidor legítimo: constrói SOBRE o motor (importa @/Components/ui/command) —
// espelha ServiceOrders/Create: role=combobox no Button trigger + Popover+Command.
writeFileSync(join(fakePages, 'FakeVehiclePicker.tsx'),
  `import { Command, CommandInput } from '@/Components/ui/command';\nimport { Popover } from '@/Components/ui/popover';\nexport default function FakeVehiclePicker(){ return <Popover><button role="combobox" /><Command><CommandInput /></Command></Popover> }\n`);

// 10. combobox: canon presente + consumidor classificado (constrói sobre Command)
const cbxClean = run(['--roles']);
check('roles/combobox: canon command.tsx presente (não AUSENTE)',
  /papel "combobox"[\s\S]*?canon:\s*resources\/js\/Components\/ui\/command\.tsx/.test(cbxClean.stdout));
check('roles/combobox: consumidor FakeVehiclePicker NÃO é independente (importa o motor)',
  /FakeVehiclePicker/.test(cbxClean.stdout) && !new RegExp('⚠️[^\\n]*FakeVehiclePicker').test(cbxClean.stdout));

// 11. combobox: hand-roll INDEPENDENTE (nome *Combobox + markup à mão, NÃO importa command)
writeFileSync(join(fakePages, 'FakeClienteCombobox.tsx'),
  `export default function FakeClienteCombobox(){ return <div><input role="combobox" aria-autocomplete="list" /><ul role="listbox" /></div> }\n`);
const cbxDrift = run(['--roles']);
check('roles/combobox: aponta o hand-roll independente FakeClienteCombobox',
  new RegExp('⚠️[^\\n]*FakeClienteCombobox').test(cbxDrift.stdout));
check('roles/combobox: strict MORDE o hand-roll independente (exit 1)',
  run(['--roles', '--strict']).status === 1);
check('roles/combobox: NÃO marca o consumer como independente mesmo com drift',
  !new RegExp('⚠️[^\\n]*FakeVehiclePicker').test(cbxDrift.stdout));

// ── MODO --roles: papel status-badge (Onda 2026-07-15, ADR proposta tab-nav/papel) ──
// Repo-fixture SEPARADO (root2) pra isolar do cluster tab-nav acima.
const root2 = mkdtempSync(join(tmpdir(), 'compreg-sb-'));
const sharedDir2 = join(root2, 'resources', 'js', 'Components', 'shared');
const uiDir2 = join(root2, 'resources', 'js', 'Components', 'ui');
const pages2 = join(root2, 'resources', 'js', 'Pages', 'Fake');
mkdirSync(sharedDir2, { recursive: true });
mkdirSync(uiDir2, { recursive: true });
mkdirSync(pages2, { recursive: true });
// canon do papel = wrapper de domínio StatusBadge (path EXATO que ROLE_SIGNATURES canoniza)
writeFileSync(join(sharedDir2, 'StatusBadge.tsx'),
  `import { Badge } from '@/Components/ui/badge';\nexport default function StatusBadge(){ return <Badge/> }\n`);
// primitivo Badge (não casa por nome → não entra no cluster; alvo de import)
writeFileSync(join(uiDir2, 'badge.tsx'), `export function Badge(){ return null }\n`);
// consumidor via PRIMITIVO @/Components/ui/badge — exercita canonImport COMO LISTA
writeFileSync(join(pages2, 'OkStageBadge.tsx'),
  `import { Badge } from '@/Components/ui/badge';\nexport default function OkStageBadge(){ return <Badge variant="success" /> }\n`);
const run2 = (extra = []) => spawnSync('node', [SCRIPT, '--root', root2, '--roles', ...extra], { encoding: 'utf8' });

// 12. LIBERA: canon + consumidor-via-primitivo → sem independente (advisory E strict exit 0)
const sbClean = run2();
check('status-badge: advisory exit 0 sem independente', sbClean.status === 0);
check('status-badge: consumidor via @/Components/ui/badge (canonImport LISTA) = consumer', /OkStageBadge/.test(sbClean.stdout) && /consumidores/.test(sbClean.stdout));
check('status-badge: strict exit 0 sem independente', run2(['--strict']).status === 0);

// 13. DRIFT: hand-roll INDEPENDENTE (*StatusBadge, NÃO importa nenhum canon)
writeFileSync(join(pages2, 'VehicleStatusBadge.tsx'),
  `export default function VehicleStatusBadge(){ return <span className="inline-flex rounded-full px-2 py-0.5 bg-success-soft text-success-fg">ok</span> }\n`);
const sbDrift = run2();
check('status-badge: advisory NÃO morde com drift (exit 0)', sbDrift.status === 0);
check('status-badge: strict MORDE hand-roll independente (exit 1)', run2(['--strict']).status === 1);
check('status-badge: aponta VehicleStatusBadge como INDEPENDENTE', /VehicleStatusBadge/.test(sbDrift.stdout) && /INDEPENDENTE/i.test(sbDrift.stdout));

// 14. EXCEÇÃO documentada: FiscalStatusBadge (R-DS-002/ADR 0235) NÃO entra no cluster
writeFileSync(join(pages2, 'FiscalStatusBadge.tsx'),
  `export default function FiscalStatusBadge(){ return <span className="rounded-full px-2 py-0.5">fiscal</span> }\n`);
check('status-badge: FiscalStatusBadge (exceção documentada) NÃO é reportado como drift', !/FiscalStatusBadge/.test(run2().stdout));

console.log(fails === 0 ? '\n✓ todos os checks passaram' : `\n✗ ${fails} check(s) falharam`);
process.exit(fails === 0 ? 0 : 1);
