#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  RETURN_CHANNELS,
  evaluateDesignReturn,
  normalizeChangedPaths,
  validateReturnDocuments,
} from './design-return-check.mjs';

let failures = 0;
const check = (name, condition, detail = '') => {
  console.log(`${condition ? '[OK]' : '[FAIL]'} ${name}${condition ? '' : ` — ${detail}`}`);
  if (!condition) failures++;
};

const complete = [
  'resources/js/Pages/Financeiro/Index.tsx',
  ...RETURN_CHANNELS,
];

check('normaliza CRLF, barras Windows, ./ e duplicatas',
  JSON.stringify(normalizeChangedPaths('.\\resources\\js\\Pages\\A.tsx\r\nresources/js/Pages/A.tsx\r\n'))
    === '["resources/js/Pages/A.tsx"]');
check('Page .tsx torna o retorno aplicável', evaluateDesignReturn(complete).applicable);
check('arquivo do espelho Cowork torna o retorno aplicável',
  evaluateDesignReturn(['prototipo-ui/cowork/app.jsx', ...RETURN_CHANNELS]).applicable);
check('componentes/layouts compartilhados também exigem retorno',
  evaluateDesignReturn(['resources/js/Components/Button.tsx']).applicable
    && evaluateDesignReturn(['resources/js/Layouts/AppLayout.tsx']).applicable);
check('UI dentro de Modules também exige retorno',
  evaluateDesignReturn(['Modules/Financeiro/resources/js/Pages/Index.tsx']).applicable);
check('arquivo de backend não exige retorno',
  evaluateDesignReturn(['Modules/Financeiro/app/Models/Conta.php']).complete);
check('extensão parecida não vira falso positivo',
  !evaluateDesignReturn(['resources/js/Pages/A.tsx.bak']).applicable);
check('prefixo parecido com cowork não vira falso positivo',
  !evaluateDesignReturn(['prototipo-ui/cowork-old/app.jsx']).applicable);
check('os três canais fecham o protocolo', evaluateDesignReturn(complete).complete);

const onlySyncLog = evaluateDesignReturn([
  'resources/js/Pages/Financeiro/Index.tsx',
  'prototipo-ui/SYNC_LOG.md',
]);
check('só SYNC_LOG não mascara retorno incompleto',
  !onlySyncLog.complete && onlySyncLog.missing.length === 2,
  JSON.stringify(onlySyncLog));
check('nomes semelhantes não satisfazem canal exato',
  evaluateDesignReturn([
    'prototipo-ui/cowork/app.jsx',
    'prototipo-ui/SYNC_LOG.md.bak',
    'prototipo-ui/HANDOFF-old.md',
    'prototipo-ui/DS_ADOCAO_INDICE.md.tmp',
  ]).missing.length === 3);

const docsOk = {
  index: '<!-- ds:worklist:start -->\n> Gerado por `npm run ds:report -- --write`\n**Próximo da fila:** Financeiro\n<!-- ds:worklist:end -->',
  syncDiff: '+++ b/prototipo-ui/SYNC_LOG.md\n+2026-08-23 12:00 [CL] F3 Financeiro merged · ds/*: 10→9 · PR #6000\n',
  handoff: 'Agora: Financeiro\nPróximo: Cliente\nTotal restante: 9\n',
};
check('conteúdo canônico dos três canais passa', validateReturnDocuments(docsOk).length === 0);
check('SYNC_LOG sobrescrito/removendo histórico é recusado',
  validateReturnDocuments({ ...docsOk, syncDiff: '-linha antiga\n+2026-08-23 [CL] merged · PR #6000\n' }).some((x) => x.includes('append')));
check('SYNC_LOG sem data/[CL]/PR é recusado',
  validateReturnDocuments({ ...docsOk, syncDiff: '+feito\n' }).some((x) => x.includes('append')));
check('índice apenas tocado, sem bloco gerado, é recusado',
  validateReturnDocuments({ ...docsOk, index: '# toque manual' }).some((x) => x.includes('worklist')));
for (const field of ['Agora', 'Próximo', 'restante']) {
  check(`HANDOFF sem ${field} é recusado`,
    validateReturnDocuments({ ...docsOk, handoff: docsOk.handoff.replace(new RegExp(`^.*${field}.*$`, 'imu'), '') })
      .some((x) => x.toLowerCase().includes(field.toLowerCase())));
}

const root = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
const cli = join(root, 'scripts', 'governance', 'design-return-check.mjs');
const tmp = mkdtempSync(join(tmpdir(), 'design-return-check-'));
const run = (args, env = {}) => {
  try {
    return { code: 0, out: execFileSync(process.execPath, [cli, ...args], { cwd: root, encoding: 'utf8', env: { ...process.env, ...env } }) };
  } catch (error) {
    return { code: error.status, out: String(error.stdout || '') + String(error.stderr || '') };
  }
};

try {
  const good = join(tmp, 'good.txt');
  const bad = join(tmp, 'bad.txt');
  const summary = join(tmp, 'summary.md');
  writeFileSync(good, complete.join('\n') + '\n');
  writeFileSync(bad, 'resources/js/Pages/Financeiro/Index.tsx\nprototipo-ui/SYNC_LOG.md\n');
  writeFileSync(summary, '');

  const goodRun = run(['--changed-from', good, '--check']);
  check('CLI good libera com exit 0 pelo motivo certo',
    goodRun.code === 0 && /3\/3 canais/.test(goodRun.out), goodRun.out);

  const badRun = run(['--changed-from', bad, '--check']);
  check('CLI bad morde com exit 1 e nomeia os dois canais ausentes',
    badRun.code === 1
      && /DS_ADOCAO_INDICE\.md/.test(badRun.out)
      && /HANDOFF\.md/.test(badRun.out), badRun.out);

  const advisory = run(['--changed-from', bad, '--github-summary'], { GITHUB_STEP_SUMMARY: summary });
  check('modo do workflow continua advisory (exit 0), mas não fica mudo',
    advisory.code === 0 && /ADVISORY/.test(advisory.out) && /::warning/.test(advisory.out), advisory.out);
  const summaryText = readFileSync(summary, 'utf8');
  check('job summary registra arquivos afetados e todos os canais ausentes',
    /Index\.tsx/.test(summaryText) && /DS_ADOCAO_INDICE\.md/.test(summaryText) && /HANDOFF\.md/.test(summaryText), summaryText);

  const missingInput = run(['--changed-from', join(tmp, 'nao-existe.txt')]);
  check('entrada inexistente falha fechada com exit 2 (sem universo != OK)',
    missingInput.code === 2 && /sem veredito|não existe/.test(missingInput.out), missingInput.out);

  const workflow = readFileSync(join(root, '.github', 'workflows', 'design-return-gate.yml'), 'utf8');
  check('workflow invoca o verificador de produção, sem copiar a regra no YAML',
    /node scripts\/governance\/design-return-check\.mjs --base "\$BEFORE_SHA" --head "\$AFTER_SHA" --validate-content --github-summary/.test(workflow)
      && !/grep -qx ['"]?prototipo-ui\/SYNC_LOG\.md/.test(workflow));
  check('workflow nasce advisory: não passa --check ao verificador',
    !/design-return-check\.mjs[^\n]*--check/.test(workflow));
  check('path filter inclui os três canais canônicos',
    RETURN_CHANNELS.every((channel) => workflow.includes(`'${channel}'`)));
  check('workflow mede o push inteiro via before/after e cobre UI compartilhada/modular',
    /github\.event\.before/.test(workflow)
      && workflow.includes("'resources/js/Components/**'")
      && workflow.includes("'Modules/*/resources/js/**'"));
} finally {
  rmSync(tmp, { recursive: true, force: true });
}

if (failures) {
  console.error(`\n${failures} regressão(ões) no retorno Code -> Design.`);
  process.exit(1);
}
console.log('\nRetorno Code -> Design: todos os cenários passaram.');
