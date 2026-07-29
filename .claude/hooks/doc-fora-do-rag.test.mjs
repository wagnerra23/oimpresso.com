#!/usr/bin/env node
// Selftest de doc-fora-do-rag.mjs.
//
// Prova as DUAS pernas — sem as duas, o hook é decorativo:
//   MORDE      — doc que nasce fora da allowlist gera aviso
//   NÃO MORDE  — os 6 caminhos que o indexador REALMENTE coleta, o legado que já
//                existe, o opt-out `_`, a tool alheia e o fail-open ficam SILENCIOSOS
//
// Advisory sempre sai com exit 0; portanto a mordida se mede pelo STDERR, nunca pelo
// código de saída. Um teste que olhasse só o exit passaria com o hook mudo.
//
// E prova a ANTI-DERIVA: lê `IndexarMemoryGitParaDb.php` e compara a allowlist e os
// globs. Se o indexador ganhar um 10º nome, ESTE teste fica vermelho — que é o
// mecanismo que impede o hook de mentir em silêncio (a allowlist é hardcoded lá).
//
// Uso: node .claude/hooks/doc-fora-do-rag.test.mjs

import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ALLOWLIST, avaliar, coletadoPeloIndexador, mensagem, paraGitPath } from './doc-fora-do-rag.mjs';

const HOOK = resolve(dirname(fileURLToPath(import.meta.url)), 'doc-fora-do-rag.mjs');
const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const PHP = join(ROOT, 'Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php');
let ok = 0, fail = 0;

const t = (nome, cond) => {
  if (cond) { ok++; console.log(`  ✅ ${nome}`); }
  else { fail++; console.log(`  ❌ ${nome}`); }
};

/** roda o hook com um payload e devolve {code, err} */
const rodar = (payload) => {
  const r = spawnSync('node', [HOOK], { input: JSON.stringify(payload), encoding: 'utf8' });
  return { code: r.status, err: r.stderr || '' };
};
const novo = (p) => avaliar({ gitPath: p, jaExiste: false }).avisar;

console.log('\n── ANTI-DERIVA (a allowlist é hardcoded — o indexador é a fonte) ──');
t('IndexarMemoryGitParaDb.php existe (sem ele o teste seria verde por não-execução)', existsSync(PHP));
if (existsSync(PHP)) {
  const src = readFileSync(PHP, 'utf8');
  const bloco = (src.match(/\$docsPorModulo\s*=\s*\[([\s\S]*?)\n\s*\];/) || [])[1] || '';
  const nomesPhp = [...bloco.matchAll(/^\s*'([A-Z_]+)'\s*=>/gm)].map((m) => m[1]);
  // SPEC e BRIEFING entram por glob DEDICADO (o laço ainda dá `continue` em SPEC),
  // por isso os 9 nomes efetivos são 7 no array + 2 avulsos.
  const PORT_DEDICADO = ['SPEC', 'BRIEFING'];
  const esperado = [...ALLOWLIST].filter((n) => !PORT_DEDICADO.includes(n)).sort().join(',');
  t(`\$docsPorModulo do PHP == allowlist do hook (sem SPEC/BRIEFING) [php: ${nomesPhp.length} nomes]`,
    nomesPhp.slice().sort().join(',') === esperado);
  t('globs dedicados de SPEC e BRIEFING ainda existem no PHP',
    src.includes('memory/requisitos/*/SPEC.md') && src.includes('memory/requisitos/*/BRIEFING.md'));
  t('glob <Mod>/*.md com o `continue` da allowlist ainda existe',
    src.includes('memory/requisitos/*/*.md') && src.includes('if (!isset($docsPorModulo[$name])) continue;'));
  t('glob de adr por categoria ainda exige <Mod>/adr/<cat>/*.md', src.includes('memory/requisitos/*/adr/*/*.md'));
  t('glob de audits ainda existe', src.includes('memory/requisitos/*/audits/*.md'));
  t('coleta recursiva de _DesignSystem ainda existe', src.includes("coletarRecursivo(\"$base/memory/requisitos/_DesignSystem\""));
  t('recursivo ainda pula `_*` e README (Regra 3)', /str_starts_with\(\$name, '_'\) \|\| \$name === 'README'/.test(src));
}

console.log('\n── núcleo: caminhos que o indexador COLETA (não pode avisar) ──');
t('<Mod>/SPEC.md', coletadoPeloIndexador('memory/requisitos/Jana/SPEC.md') === true);
t('<Mod>/BRIEFING.md', coletadoPeloIndexador('memory/requisitos/Jana/BRIEFING.md') === true);
t('<Mod>/SUPERFICIE.md', coletadoPeloIndexador('memory/requisitos/Jana/SUPERFICIE.md') === true);
t('<Mod>/adr/<cat>/x.md', coletadoPeloIndexador('memory/requisitos/ADS/adr/arq/ARQ-0001-x.md') === true);
t('<Mod>/audits/2026-01-01.md', coletadoPeloIndexador('memory/requisitos/ADS/audits/2026-01-01.md') === true);
t('_DesignSystem/QUALQUER.md (recursivo)', coletadoPeloIndexador('memory/requisitos/_DesignSystem/PT-01-Lista.md') === true);
t('_DesignSystem/adr/ui/x.md (recursivo)', coletadoPeloIndexador('memory/requisitos/_DesignSystem/adr/ui/0001-x.md') === true);
t('_DesignSystem/README.md ENTRA pelo laço da allowlist (bug que o oráculo pegou)',
  coletadoPeloIndexador('memory/requisitos/_DesignSystem/README.md') === true);
t('raiz memory/requisitos/BI.md', coletadoPeloIndexador('memory/requisitos/BI.md') === true);

console.log('\n── núcleo: caminhos FORA do RAG ──');
t('<Mod>/PEGADINHA-x.md', coletadoPeloIndexador('memory/requisitos/Infra/PEGADINHA-x.md') === false);
t('<Mod>/RUNBOOK-algo.md (prefixo NÃO casa RUNBOOK)', coletadoPeloIndexador('memory/requisitos/Infra/RUNBOOK-criar-modulo.md') === false);
t('<Mod>/adr/x.md sem categoria (glob exige adr/<cat>/)', coletadoPeloIndexador('memory/requisitos/MemCofre/adr/0001-x.md') === false);
t('<Mod>/_telas/x.casos.md (subárvore sem glob)', coletadoPeloIndexador('memory/requisitos/Produto/_telas/x.casos.md') === false);
t('_DesignSystem/_TEMPLATE.md (Regra 3 pula `_`)', coletadoPeloIndexador('memory/requisitos/_DesignSystem/_TEMPLATE.md') === false);
t('raiz memory/requisitos/_TEMPLATE_SPEC.md (laço da raiz pula `_`)', coletadoPeloIndexador('memory/requisitos/_TEMPLATE_SPEC.md') === false);
t('fora de escopo devolve null', coletadoPeloIndexador('memory/reference/x.md') === null);
t('não-.md devolve null', coletadoPeloIndexador('memory/requisitos/Infra/notas.txt') === null);
t('case-sensitive: <Mod>/spec.md NÃO é SPEC.md', coletadoPeloIndexador('memory/requisitos/Jana/spec.md') === false);

console.log('\n── MORDE ──');
t('doc novo fora da allowlist avisa', novo('memory/requisitos/Infra/PEGADINHA-junction.md'));
t('RUNBOOK- com sufixo avisa', novo('memory/requisitos/Infra/RUNBOOK-criar-modulo.md'));
t('SDD-*/CAPTERRA-*/AUDITORIA-* avisam',
  novo('memory/requisitos/Sells/SDD-tela-venda-v1.0.md') &&
  novo('memory/requisitos/Sells/CAPTERRA-FICHA.md') &&
  novo('memory/requisitos/Sells/AUDITORIA-x.md'));
t('mensagem nomeia o arquivo, os 9 nomes e o dono do tema', (() => {
  const m = mensagem('memory/requisitos/Infra/PEGADINHA-x.md', 'PEGADINHA-x', 'Infra');
  return m.includes('PEGADINHA-x') && ALLOWLIST.every((n) => m.includes(n)) &&
    m.includes('como-escrever-doc-para-o-rag.md') && m.includes('advisory');
})());

console.log('\n── NÃO MORDE (controles negativos) ──');
t('nome da allowlist não avisa', !novo('memory/requisitos/Infra/RUNBOOK.md'));
t('_DesignSystem não avisa (cobertura recursiva)', !novo('memory/requisitos/_DesignSystem/PT-01-Lista.md'));
t('adr/<cat>/ não avisa', !novo('memory/requisitos/ADS/adr/arq/ARQ-0001-x.md'));
t('audits/ não avisa', !novo('memory/requisitos/ADS/audits/2026-01-01.md'));
t('subpasta não-coberta está FORA DE ESCOPO (não avisa)', !novo('memory/requisitos/Produto/_telas/x.casos.md'));
t('basename `_` é opt-out declarado (Regra 3) — não avisa', !novo('memory/requisitos/KB/_STATUS-GENERATED.md'));
t('raiz memory/requisitos/*.md não avisa', !novo('memory/requisitos/BI.md'));
t('fora de memory/requisitos/ não avisa', !novo('memory/reference/x.md') && !novo('resources/js/Pages/Sells/Index.tsx'));
t('FORWARD-ONLY: arquivo que já existe não avisa',
  avaliar({ gitPath: 'memory/requisitos/Infra/PEGADINHA-junction.md', jaExiste: true }).avisar === false);

console.log('\n── paraGitPath ──');
t('absoluto dentro do repo vira relativo', paraGitPath(join(ROOT, 'memory/requisitos/X/Y.md')) === 'memory/requisitos/X/Y.md');
t('backslash do Windows normaliza', paraGitPath(`${ROOT}\\memory\\requisitos\\X\\Y.md`) === 'memory/requisitos/X/Y.md');
t('relativo passa direto', paraGitPath('memory/requisitos/X/Y.md') === 'memory/requisitos/X/Y.md');
t('absoluto de FORA do repo devolve vazio', paraGitPath('/tmp/outro/memory/requisitos/X/Y.md') === '');

console.log('\n── E2E pelo stdin (invocação real, não só a lógica) ──');
const ruim = rodar({ tool_name: 'Write', tool_input: { file_path: join(ROOT, 'memory/requisitos/Infra/PEGADINHA-fixture-inexistente.md') } });
t('E2E ruim: avisa no stderr', /FORA DO ÍNDICE DA IA/.test(ruim.err));
t('E2E ruim: NÃO bloqueia (advisory, exit 0)', ruim.code === 0);

const bom = rodar({ tool_name: 'Write', tool_input: { file_path: join(ROOT, 'memory/requisitos/InfraFixture/RUNBOOK.md') } });
t('E2E nome da allowlist: silencioso', bom.err.trim() === '' && bom.code === 0);

// Legado real: um .md que EXISTE e está fora da allowlist. Resolvido da árvore (não
// hardcoded) — path fixo apodrece no primeiro rename.
const legado = (() => {
  const raiz = join(ROOT, 'memory/requisitos');
  for (const mod of readdirSync(raiz, { withFileTypes: true }).filter((d) => d.isDirectory() && d.name !== '_DesignSystem')) {
    for (const f of readdirSync(join(raiz, mod.name), { withFileTypes: true })) {
      if (!f.isFile() || !f.name.endsWith('.md') || f.name.startsWith('_')) continue;
      if (!ALLOWLIST.includes(f.name.replace(/\.md$/, ''))) return join(raiz, mod.name, f.name);
    }
  }
  return null;
})();
t('achou um legado fora da allowlist pra provar o forward-only', legado !== null);
if (legado) {
  const l = rodar({ tool_name: 'Write', tool_input: { file_path: legado } });
  t('E2E legado que JÁ EXISTE: silencioso (forward-only)', l.err.trim() === '' && l.code === 0);
}

const edit = rodar({ tool_name: 'Edit', tool_input: { file_path: join(ROOT, 'memory/requisitos/Infra/PEGADINHA-fixture-inexistente.md') } });
t('E2E tool Edit: silencioso (legado se edita)', edit.err.trim() === '' && edit.code === 0);

const alheia = rodar({ tool_name: 'Bash', tool_input: { command: 'ls' } });
t('E2E tool alheia: silenciosa', alheia.err.trim() === '' && alheia.code === 0);

const semPath = rodar({ tool_name: 'Write', tool_input: {} });
t('E2E sem file_path: silencioso', semPath.err.trim() === '' && semPath.code === 0);

const lixo = spawnSync('node', [HOOK], { input: 'isto não é json', encoding: 'utf8' });
t('fail-open: stdin inválido não trava nem avisa', lixo.status === 0 && (lixo.stderr || '').trim() === '');

const vazio = spawnSync('node', [HOOK], { input: '', encoding: 'utf8' });
t('fail-open: stdin vazio não trava nem avisa', vazio.status === 0 && (vazio.stderr || '').trim() === '');

console.log(`\n${fail === 0 ? '✅' : '❌'} ${ok} passaram, ${fail} falharam\n`);
process.exit(fail === 0 ? 0 : 1);
