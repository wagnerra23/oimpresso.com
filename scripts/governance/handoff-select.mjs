#!/usr/bin/env node
// Seleciona handoffs novos/modificados sem reimplementar git/YAML dentro do workflow.
import { execFileSync } from 'node:child_process';
import { existsSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

export const HANDOFF_RE = /^prototipo-ui\/handoffs\/[^/]+\.md$/;
export const isHandoffPath = (path) => HANDOFF_RE.test(String(path).replaceAll('\\', '/'));

function valueAfter(args, flag) {
  const index = args.indexOf(flag);
  return index === -1 ? null : args[index + 1] || null;
}

export function selectHandoffs(args, cwd = process.cwd()) {
  const dispatch = valueAfter(args, '--dispatch');
  if (dispatch !== null) {
    const normalized = dispatch.replaceAll('\\', '/');
    if (!isHandoffPath(normalized)) throw new Error(`handoff inválido: ${dispatch}`);
    if (!existsSync(`${cwd}/${normalized}`)) throw new Error(`handoff não existe neste ref: ${normalized}`);
    return [normalized];
  }

  const base = valueAfter(args, '--base');
  const head = valueAfter(args, '--head');
  if (!base || !head) throw new Error('--base e --head são obrigatórios no modo push');

  let raw;
  if (/^0+$/.test(base)) {
    raw = execFileSync('git', ['diff-tree', '--root', '--no-commit-id', '--name-only', '-r', '-z', '--diff-filter=AM', head, '--', 'prototipo-ui/handoffs/*.md'], { cwd });
  } else {
    try { execFileSync('git', ['cat-file', '-e', `${base}^{commit}`], { cwd, stdio: 'ignore' }); }
    catch { throw new Error(`base ${base} não existe; sem universo, sem seleção`); }
    raw = execFileSync('git', ['diff', '--name-only', '--diff-filter=AM', '-z', base, head, '--', 'prototipo-ui/handoffs/*.md'], { cwd });
  }
  return [...new Set(raw.toString('utf8').split('\0').filter(isHandoffPath))].sort();
}

export function main(args = process.argv.slice(2)) {
  try {
    const selected = selectHandoffs(args);
    const out = selected.length ? Buffer.from(selected.join('\0') + '\0') : Buffer.alloc(0);
    const output = valueAfter(args, '--output');
    if (output) writeFileSync(output, out);
    else process.stdout.write(out);
    return 0;
  } catch (error) {
    console.error(`✗ handoff-select: ${error.message}`);
    return 2;
  }
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) process.exitCode = main();
