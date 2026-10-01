#!/usr/bin/env node
// Bite-test do detect do check "Contratos de tela" (.github/scripts/contrato-de-tela-detect.sh).
//
// Exercita o CLI DE FORA: monta um repo git real num diretório temporário, copia o detect e o
// predicado, faz um commit-base e um commit por caso, roda o `.sh` como o workflow roda (env +
// GITHUB_OUTPUT) e lê `relevant=` do arquivo de saída. Testar só a função pura deixaria de fora
// o pipe bash→node, que é onde o gate pode ficar mudo.
//
// Controle de mutação: o mesmo sandbox roda o filtro ANTIGO (regex à mão só com
// `^resources/js/Pages/`) e o caso "só tela de módulo" tem de sair `false` nele. Se não sair, a
// fixture não discrimina e o verde do caso novo não prova nada.
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, copyFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { relevante } from './contrato-de-tela-relevante.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const fwd = (p) => p.replace(/\\/g, '/');
let falhas = 0;
const ok = (cond, msg) => { console.log(`${cond ? 'ok  ' : 'FAIL'} ${msg}`); if (!cond) falhas++; };

const sh = (cwd, args, env = {}) => {
  const r = spawnSync(args[0], args.slice(1), { cwd, encoding: 'utf8', env: { ...process.env, ...env } });
  if (r.status !== 0) throw new Error(`${args.join(' ')} → rc=${r.status}\n${r.stdout}\n${r.stderr}`);
  return r.stdout.trim();
};
const put = (dir, rel, body = 'x\n') => { mkdirSync(dirname(join(dir, rel)), { recursive: true }); writeFileSync(join(dir, rel), body); };

const DETECT_ANTIGO = `#!/usr/bin/env bash
set -euo pipefail
base="\${PUSH_BEFORE}"
changed="$(git diff --name-only "\${base}" HEAD || true)"
if echo "\${changed}" | grep -Eq '(^governance/design/contracts/|^resources/js/Pages/.+\\.tsx?$|\\.contract\\.json$)'; then
  echo "relevant=true" >> "\${GITHUB_OUTPUT}"
else
  echo "relevant=false" >> "\${GITHUB_OUTPUT}"
fi
`;

function sandbox() {
  const dir = mkdtempSync(join(tmpdir(), 'contrato-detect-'));
  for (const rel of ['.github/scripts/contrato-de-tela-detect.sh', 'scripts/contrato-de-tela-relevante.mjs', 'scripts/qa/page-path.mjs']) {
    mkdirSync(dirname(join(dir, rel)), { recursive: true });
    copyFileSync(join(ROOT, rel), join(dir, rel));
  }
  put(dir, '.github/scripts/detect-antigo.sh', DETECT_ANTIGO);
  put(dir, 'governance/design/contracts/fixture.contract.json', JSON.stringify({
    tela: 'Fixture', alvo: ['resources/js/Components/cockpit/Sidebar.tsx', 'Modules/Foo/Resources/js/Pages/Foo/Lista'],
  }));
  put(dir, 'README.md');
  sh(dir, ['git', 'init', '-q']);
  sh(dir, ['git', '-c', 'user.email=t@t', '-c', 'user.name=t', 'add', '-A']);
  sh(dir, ['git', '-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-qm', 'base']);
  return dir;
}

function caso(dir, script, arquivos) {
  const base = sh(dir, ['git', 'rev-parse', 'HEAD']);
  for (const a of arquivos) put(dir, a, `${Math.random()}\n`);
  sh(dir, ['git', 'add', '-A']);
  sh(dir, ['git', '-c', 'user.email=t@t', '-c', 'user.name=t', 'commit', '-qm', 'caso']);
  const out = join(dir, `out-${Math.random().toString(36).slice(2)}.txt`);
  writeFileSync(out, '');
  sh(dir, ['bash', script], { EVENT_NAME: 'push', PUSH_BEFORE: base, PR_BASE_SHA: '', GITHUB_OUTPUT: fwd(out) });
  const m = readFileSync(out, 'utf8').match(/^relevant=(true|false)$/m);
  rmSync(out);
  // volta a árvore pro base, para o próximo caso diffar só o que ele mesmo tocou
  sh(dir, ['git', 'reset', '-q', '--hard', base]);
  return m ? m[1] : `SEM relevant= (saída ilegível)`;
}

const NOVO = '.github/scripts/contrato-de-tela-detect.sh';
const ANTIGO = '.github/scripts/detect-antigo.sh';
const dir = sandbox();
try {
  const tabela = [
    ['só tela de módulo (Resources)', ['Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx'], 'true'],
    ['só tela de módulo (resources minúsculo)', ['Modules/Bar/resources/js/Pages/Bar/Index.tsx'], 'true'],
    ['componente sob Pages de módulo', ['Modules/Crm/Resources/js/Pages/Crm/Leads/_components/Filtro.tsx'], 'true'],
    ['alvo de contrato fora de Pages', ['resources/js/Components/cockpit/Sidebar.tsx'], 'true'],
    ['alvo-diretório de contrato', ['Modules/Foo/Resources/js/Pages/Foo/Lista/Index.tsx'], 'true'],
    ['tela do núcleo (comportamento antigo preservado)', ['resources/js/Pages/Sells/Index.tsx'], 'true'],
    ['só doc', ['README.md', 'memory/x.md'], 'false'],
    ['componente fora de Pages e sem contrato', ['resources/js/Components/outro/Botao.tsx'], 'false'],
    ['markdown ao lado da tela de módulo', ['Modules/Crm/Resources/js/Pages/Crm/Leads/Index.charter.md'], 'false'],
    ['PHP de módulo', ['Modules/Crm/Http/Controllers/LeadController.php'], 'false'],
  ];
  for (const [nome, arquivos, esperado] of tabela) {
    const r = caso(dir, NOVO, arquivos);
    ok(r === esperado, `${nome}: detect diz ${r} (esperado ${esperado})`);
  }
  // Controle de mutação: o filtro antigo NÃO via tela de módulo nem alvo fora de Pages.
  ok(caso(dir, ANTIGO, ['Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.tsx']) === 'false',
    'CONTROLE: filtro antigo pula tela de módulo (a fixture discrimina)');
  ok(caso(dir, ANTIGO, ['resources/js/Components/cockpit/Sidebar.tsx']) === 'false',
    'CONTROLE: filtro antigo pula alvo de contrato fora de Pages');
  ok(caso(dir, ANTIGO, ['resources/js/Pages/Sells/Index.tsx']) === 'true',
    'CONTROLE: filtro antigo roda em tela do núcleo (o sandbox mede)');
} finally {
  rmSync(dir, { recursive: true, force: true });
}

// Função pura: entrada vazia e linhas em branco não disparam.
ok(relevante(['', '  '], []).relevante === false, 'stdin vazio → false');
ok(relevante(['governance/design/contracts/novo.contract.json'], []).relevante === true, 'contrato novo → true');

if (falhas) { console.error(`\n${falhas} falha(s)`); process.exit(1); }
console.log('\ncontrato-de-tela-relevante: todos os casos ok');
