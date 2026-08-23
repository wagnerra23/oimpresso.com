#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isHandoffPath, selectHandoffs } from './handoff-select.mjs';

let fails = 0;
const check = (name, ok, detail = '') => { console.log(`${ok ? '[OK]' : '[FAIL]'} ${name}${ok ? '' : ` — ${detail}`}`); if (!ok) fails++; };
check('aceita somente .md direto em prototipo-ui/handoffs',
  isHandoffPath('prototipo-ui/handoffs/a b.md')
    && !isHandoffPath('prototipo-ui/handoffs/sub/a.md')
    && !isHandoffPath('prototipo-ui/handoffs/a.txt'));

const tmp = mkdtempSync(join(tmpdir(), 'handoff-select-'));
const g = (args) => execFileSync('git', args, { cwd: tmp, encoding: 'utf8' }).trim();
try {
  g(['init', '-q']); g(['config', 'user.email', 'fixture@example.invalid']); g(['config', 'user.name', 'Fixture']);
  mkdirSync(join(tmp, 'prototipo-ui', 'handoffs'), { recursive: true });
  writeFileSync(join(tmp, 'README.md'), 'base\n'); g(['add', '.']); g(['commit', '-qm', 'base']);
  const base = g(['rev-parse', 'HEAD']);

  writeFileSync(join(tmp, 'prototipo-ui', 'handoffs', 'b com espaço.md'), 'b1\n');
  writeFileSync(join(tmp, 'prototipo-ui', 'handoffs', 'a.md'), 'a1\n');
  g(['add', '.']); g(['commit', '-qm', 'adiciona dois']);
  writeFileSync(join(tmp, 'prototipo-ui', 'handoffs', 'a.md'), 'a2\n');
  g(['add', '.']); g(['commit', '-qm', 'modifica no segundo commit']);
  const head = g(['rev-parse', 'HEAD']);
  const multi = selectHandoffs(['--base', base, '--head', head], tmp);
  check('push multi-commit seleciona added+modified, ordena, deduplica e preserva espaços',
    JSON.stringify(multi) === '["prototipo-ui/handoffs/a.md","prototipo-ui/handoffs/b com espaço.md"]', JSON.stringify(multi));

  g(['rm', '-q', 'prototipo-ui/handoffs/a.md']); g(['commit', '-qm', 'deleta']);
  const deletedHead = g(['rev-parse', 'HEAD']);
  check('deletado é ignorado', selectHandoffs(['--base', head, '--head', deletedHead], tmp).length === 0);

  const zeros = '0'.repeat(40);
  const rootCommit = g(['rev-list', '--max-parents=0', 'HEAD']);
  check('before=000… usa árvore vazia e não fica verde por ausência de pai',
    selectHandoffs(['--base', zeros, '--head', rootCommit], tmp).length === 0);
  let invalidBase = '';
  try { selectHandoffs(['--base', '1'.repeat(40), '--head', head], tmp); } catch (error) { invalidBase = error.message; }
  check('base inexistente falha inconclusiva, nunca cai silenciosamente no pai', /sem universo/.test(invalidBase));

  check('dispatch válido existente seleciona exatamente um',
    JSON.stringify(selectHandoffs(['--dispatch', 'prototipo-ui/handoffs/b com espaço.md'], tmp))
      === '["prototipo-ui/handoffs/b com espaço.md"]');
  for (const bad of ['', '../segredo.md', 'prototipo-ui/handoffs/sub/a.md', 'prototipo-ui/handoffs/inexistente.md']) {
    let erro = '';
    try { selectHandoffs(['--dispatch', bad], tmp); } catch (error) { erro = error.message; }
    check(`dispatch inválido/inexistente é recusado (${bad || 'vazio'})`, erro !== '');
  }

  const repo = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
  const workflow = readFileSync(join(repo, '.github', 'workflows', 'handoff-sign-submit.yml'), 'utf8');
  check('workflow delega seleção e verifica o exit antes de submeter',
    /node scripts\/governance\/handoff-select\.mjs/.test(workflow) && /needs: selftest/.test(workflow));
  check('mudança no transporte/testes dispara o próprio workflow',
    workflow.includes("'bin/submit-handoff.sh'") && workflow.includes("'bin/submit-handoff.test.sh'"));
} finally { rmSync(tmp, { recursive: true, force: true }); }

if (fails) { console.error(`${fails} falha(s) na seleção de handoffs.`); process.exit(1); }
console.log('Seleção de handoffs: todos os cenários passaram.');
