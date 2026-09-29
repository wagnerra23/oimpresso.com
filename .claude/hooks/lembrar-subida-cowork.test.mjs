#!/usr/bin/env node
// lembrar-subida-cowork.test.mjs — quando o hook olha, o que ele diz, e que ele fica calado
// quando não há nada a dizer (ruído a cada push é o que faz um aviso ser ignorado).
//   BITE     gh pr create / git push -> olha; resumo com pendência -> aviso com os 2 caminhos
//   RELEASE  outro comando -> não olha; resumo "0 a subir" nos 2 projetos -> sem aviso
//   CANAL    a saída é JSON com hookSpecificOutput.additionalContext (o que chega ao agente)

import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { deveOlhar, mensagem } from './lembrar-subida-cowork.mjs';

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

ok(deveOlhar('gh pr create --title x --body-file y'), 'BITE: gh pr create é olhado');
ok(deveOlhar('cd a && git push -u origin HEAD'), 'BITE: git push é olhado');
ok(!deveOlhar('git status --short'), 'RELEASE: git status não é olhado');
ok(!deveOlhar('gh pr view 1'), 'RELEASE: gh pr view não é olhado');
ok(!deveOlhar(undefined), 'RELEASE: comando ausente não quebra');

const m = mensagem('w: 0 a subir\ncopia: 3 a subir — a.jsx, b.jsx, c.jsx\n', 3);
ok(m && m.includes('copia: 3 a subir') && !m.includes('w: 0'), 'BITE: aviso cita só o projeto com pendência');
ok(m && m.includes('--conferir') && m.includes('--plano'), 'BITE: aviso dá o caminho (plano -> subir -> conferir)');
ok(mensagem('w: 0 a subir\ncopia: 0 a subir\n', 2) === null, 'RELEASE: nada a subir nos 2 projetos -> sem aviso');
// O caso medido no branch do #8110: script antigo ignora --resumo e imprime o relatório longo.
const antigo = 'pendentes para o Cowork: 1 não enviado(s) (0 recibo(s)) · 17 enviado(s) aguardando o retorno\n  + cowork-inbox/x.md\n';
ok(mensagem(antigo, 3) === undefined, 'NÃO MEDI: saída que não é resumo não vira aviso de pendência');

// CLI de fora: comando que não é de publicação sai calado e com exit 0.
const hook = join(dirname(fileURLToPath(import.meta.url)), 'lembrar-subida-cowork.mjs');
const r = spawnSync(process.execPath, [hook], { input: JSON.stringify({ tool_name: 'Bash', tool_input: { command: 'ls' } }), encoding: 'utf8' });
ok(r.status === 0 && r.stdout === '', `RELEASE CLI: comando alheio -> exit 0 e nada no stdout (exit ${r.status})`);
const r2 = spawnSync(process.execPath, [hook], { input: 'isto não é json', encoding: 'utf8' });
ok(r2.status === 0, 'RELEASE CLI: entrada inválida não derruba a sessão (advisory)');

// CANAL: o que o hook emite é JSON no formato que o harness entrega ao agente.
const canal = JSON.stringify({ hookSpecificOutput: { hookEventName: 'PostToolUse', additionalContext: m } });
ok(JSON.parse(canal).hookSpecificOutput.additionalContext === m, 'CANAL: additionalContext carrega o aviso');

console.log(`\n${fails ? `${fails} FALHA(S)` : 'todos os casos passaram'}`);
process.exit(fails ? 1 : 0);
