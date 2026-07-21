#!/usr/bin/env node
// @ts-check
// lapide-recheck.test.mjs — selftest hermético do re-check de frescor das lápides §5.
// Prova: extrai âncoras concretas (backtick + markdown-link), ignora URL/template, resolve
// contra o repo, classifica drift vs intacta, e a amostra é determinística. Report-only:
// NÃO existe modo que bloqueia — só o teste da lógica sai != 0. Node puro, sem deps.
// Registrado em .github/workflows/governance-script-tests.yml (selftest-registry).

import { mkdtempSync, writeFileSync, mkdirSync, rmSync } from 'node:fs';
import { join } from 'node:path';
import { tmpdir } from 'node:os';
import {
  extractPaths, resolveRef, makeResolverFromIndex, classifyTombstone, sampleDeterministic, recheck,
} from './lapide-recheck.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log((c ? '[OK]   ' : '[FAIL] ') + n + (c ? '' : '  → ' + extra)); if (!c) fails++; };

// ── extractPaths: pega arquivo real, ignora URL/template/âncora ───────────────
const body = `
Texto citando \`scripts/governance/foo.mjs\` e o workflow [x](../.github/workflows/bar.yml).
Link ADR [ADR 0264](decisions/0264-algo.md). URL https://github.com/o/r/pull/1 não conta.
Template \`Modules/<Mod>/Http/Controllers/X.php\` não conta (placeholder).
Ref com linha \`resources/js/Pages/Sells/Index.tsx:2034\` conta (sem a linha).
`;
const paths = extractPaths(body);
check('extrai .mjs em backtick', paths.includes('scripts/governance/foo.mjs'), JSON.stringify(paths));
check('extrai .yml de markdown-link (limpa ../)', paths.includes('../.github/workflows/bar.yml'), JSON.stringify(paths));
check('extrai .md de ADR link', paths.includes('decisions/0264-algo.md'));
check('tira :linha do path', paths.includes('resources/js/Pages/Sells/Index.tsx'), JSON.stringify(paths));
check('ignora URL http', !paths.some((p) => p.includes('github.com')));
check('ignora template <Mod>', !paths.some((p) => p.includes('<Mod>')));

// ── resolveRef: existe sob root OU sob linkBase ──────────────────────────────
const tmp = mkdtempSync(join(tmpdir(), 'lapide-rc-'));
mkdirSync(join(tmp, 'scripts', 'governance'), { recursive: true });
mkdirSync(join(tmp, 'memory', 'decisions'), { recursive: true });
writeFileSync(join(tmp, 'scripts', 'governance', 'vivo.mjs'), '// vivo\n');
writeFileSync(join(tmp, 'memory', 'decisions', '0100-x.md'), '# adr\n');
const root = tmp, linkBase = join(tmp, 'memory');
check('resolveRef: path de root existente → true', resolveRef('scripts/governance/vivo.mjs', { root, linkBase }));
check('resolveRef: markdown-link relativo a memory/ existente → true', resolveRef('decisions/0100-x.md', { root, linkBase }));
check('resolveRef: path inexistente → false', !resolveRef('scripts/governance/sumido.mjs', { root, linkBase }));

// ── classifyTombstone ────────────────────────────────────────────────────────
const resolver = (r) => resolveRef(r, { root, linkBase });
check('intacta: todas âncoras resolvem',
  classifyTombstone({ date: '2026-01-01', title: 't', body: 'ok `scripts/governance/vivo.mjs`' }, resolver).veredito === 'ancoras-intactas');
const drift = classifyTombstone({ date: '2026-02-02', title: 't2', body: 'agora é máquina via `scripts/governance/sumido.mjs`' }, resolver);
check('drift: âncora sumida → revisar-drift-de-ancora', drift.veredito === 'revisar-drift-de-ancora', JSON.stringify(drift));
check('drift: flag reivindica_defesa_mecanica pega "agora é máquina"', drift.reivindica_defesa_mecanica === true);
check('sem âncora → sem-ancora-de-arquivo (não classifica como drift)',
  classifyTombstone({ date: '2026-03-03', title: 't3', body: 'só prosa, zero path' }, resolver).veredito === 'sem-ancora-de-arquivo');

// ── makeResolverFromIndex: mata os 3 FALSOS-POSITIVOS reais (do §5 vivo) ──────
// Índice sintético espelhando os caminhos reais que causaram FP na 1ª corrida.
const idx = [
  'resources/js/Pages/Sells/Index.casos.md',
  'memory/requisitos/_DesignSystem/SAFE-SELECT-ITEM.md',
  '.claude/workflows/reguas-do-sistema.js',
  'memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md',
];
const rr = makeResolverFromIndex({ root: '/repo', linkBase: '/repo/memory', files: idx });
check('FP morto: shorthand de backtick resolve por suffix (Sells/Index.casos.md)', rr('Sells/Index.casos.md'));
check('FP morto: shorthand _DesignSystem/... resolve por suffix', rr('_DesignSystem/SAFE-SELECT-ITEM.md'));
check('FP morto: link com ../ errado resolve (../../.claude/workflows/reguas-do-sistema.js)', rr('../../.claude/workflows/reguas-do-sistema.js'));
check('FP morto: ADR por NÚMERO — slug driftado resolve (0275-slug-velho.md)', rr('decisions/0275-calendario-promocao-gates-sdd.md'));
check('drift REAL sobrevive: script deletado NÃO resolve', !rr('scripts/governance/deletado-de-verdade.mjs'));
check('drift REAL sobrevive: ADR número inexistente NÃO resolve', !rr('decisions/9999-inexistente.md'));

// ── sampleDeterministic ──────────────────────────────────────────────────────
const items = Array.from({ length: 10 }, (_, i) => ({ date: `2026-01-${String(i + 1).padStart(2, '0')}`, title: `x${i}` }));
const s1 = sampleDeterministic(items, 4, 0);
const s1b = sampleDeterministic(items, 4, 0);
check('amostra determinística: (N,seed) iguais → mesma amostra', JSON.stringify(s1) === JSON.stringify(s1b));
check('amostra: seed diferente → amostra diferente (rotaciona)',
  JSON.stringify(sampleDeterministic(items, 4, 0)) !== JSON.stringify(sampleDeterministic(items, 4, 3)));
check('amostra: N>=total → devolve tudo', sampleDeterministic(items, 99, 0).length === 10);

// ── recheck E2E sobre um §5 sintético ────────────────────────────────────────
const proib = `# proibicoes

## Ideias avaliadas e DESCARTADAS — não re-propor

### 2026-05-01 — ideia intacta
- **Agora é máquina:** \`scripts/governance/vivo.mjs\`.

### 2026-06-01 — ideia com âncora driftada
- **Defesa mecânica:** o gate \`scripts/governance/sumido.mjs\` (deletado).

### 2026-07-01 — só prosa
- Sem nenhum arquivo citado aqui.

## Sempre fazer
(fora do §5)
`;
const r = recheck(proib, { root, linkBase });
check('recheck: 3 lápides na §5 (para no próximo ## )', r.total_lapides_secao5 === 3, JSON.stringify(r));
check('recheck: 1 revisar (drift)', r.revisar.length === 1 && r.revisar[0].date === '2026-06-01', JSON.stringify(r.revisar));
check('recheck: 1 intacta + 1 sem-ancora', r.intactas === 1 && r.sem_ancora === 1);

rmSync(tmp, { recursive: true, force: true });
console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — re-check surfaça drift de âncora do §5, ignora URL/template, amostra determinística.');
process.exit(fails ? 1 : 0);
