#!/usr/bin/env node
// @ts-check
// Teste hermético do fact-anchor (Check T de memory-health.mjs). Fixtures boa/ruim
// que PROVAM que o gate morde (proibicoes §5 2026-07-09 "fixture boa/ruim") — sem
// rodar a CLI inteira. Cobre: a classe real "React 18 (era 19)" (bug 2026-07-04),
// a emenda E1 (regex `v?` — "Pest v4" não flagra / "Pest v3" flagra), o major-only
// deliberado (floor ≠ runtime: "Laravel 13.6" vs `^13.0` NÃO flagra), o guard de
// migração ("X → Y"), e Modules/<Nome> inexistente.
//
// Rodar: node scripts/governance/fact-anchor.test.mjs   (exit 0 = passa)

import { factAnchorScan, majorFrom } from './fact-anchor.mjs';

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK] ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

// Fonte-de-verdade fixa (espelha o repo: react 19, inertia 3, tailwind 4, pest 4,
// phpunit 12, laravel 13). Não lê disco — hermético.
const pkg = { dependencies: { react: '^19.0.0', '@inertiajs/react': '^3.0.3', tailwindcss: '^4.0.0' } };
const comp = {
  require: { 'laravel/framework': '^13.0', 'inertiajs/inertia-laravel': '^3.0' },
  'require-dev': { 'pestphp/pest': '^4.0', 'phpunit/phpunit': '^12.0' },
};
const moduleExists = (n) => ['Jana', 'Financeiro'].includes(n);
const scan = (txt) => factAnchorScan({ docs: [{ rel: 't.md', txt }], pkg, comp, moduleExists });
const afirmou = (hits, s) => hits.some((h) => h.afirma === s);

// helper puro
check('majorFrom("^13.0") === "13"', majorFrom('^13.0') === '13');
check('majorFrom("React 19") === "19"', majorFrom('React 19') === '19');
check('majorFrom("") === null', majorFrom('') === null);

// 1. DOC BOM (todas as versões corretas + módulo real) → 0 contradições
const bom = 'Laravel 13.6 · React 19 · Inertia v3 · Tailwind 4 · Pest v4 · PHPUnit v12 · Modules/Jana + Modules/Financeiro';
check('doc bom não flagra nada', scan(bom).length === 0);

// 2. RUIM — a classe real "React 18 (era 19)" (bug 2026-07-04)
check('React 18 flagra', afirmou(scan('stack React 18 aqui'), 'React 18'));
check('React 19 não flagra', scan('stack React 19 aqui').length === 0);

// 3. E1 — regex `v?`: pega o token v-prefixado real e só flagra se o major diverge
check('Pest v4 (correto) NÃO flagra (E1: v? casa, major igual)', scan('rodamos Pest v4').length === 0);
check('Pest v3 (errado) flagra (E1: v? casa, major diverge)', afirmou(scan('rodamos Pest v3'), 'Pest 3'));
check('PHPUnit v12 (correto) NÃO flagra', scan('sobre PHPUnit v12').length === 0);
check('PHPUnit v11 (errado) flagra', afirmou(scan('sobre PHPUnit v11'), 'PHPUnit 11'));
check('Inertia v3 (correto) NÃO flagra', scan('Inertia v3 + React').length === 0);
check('Inertia v2 (errado) flagra', afirmou(scan('Inertia v2 legado'), 'Inertia 2'));
check('Tailwind 4 (correto, sem v) NÃO flagra', scan('Tailwind 4 tokens').length === 0);
check('Tailwind 3 (errado) flagra', afirmou(scan('Tailwind 3 tokens'), 'Tailwind 3'));

// 4. major-only deliberado: minor divergente NÃO falsa-positiva (floor ≠ runtime)
check('Laravel 13.6 vs ^13.0 NÃO flagra (major-only)', scan('Laravel 13.6 prod').length === 0);

// 5. guard de migração "X → Y": o X é história, não contradição
check('"Laravel 9 → 13.6" NÃO flagra (migração)', scan('Migração Laravel 9 → 13.6 concluída').length === 0);
check('"React 18 -> 19" NÃO flagra (migração ASCII)', scan('subimos React 18 -> 19').length === 0);
check('"Inertia v2 → v3" NÃO flagra (migração com alvo v-prefixado — FP real README:51)', scan('- Inertia v2 → v3').length === 0);

// 6. Modules/<Nome> inexistente flagra; existente não
check('Modules/MemCofre (inexistente) flagra', afirmou(scan('ver Modules/MemCofre legado'), 'Modules/MemCofre'));
check('Modules/Jana (existente) NÃO flagra', scan('ver Modules/Jana').length === 0);
check('Modules/<X> placeholder NÃO flagra (regex exige letra)', scan('crie Modules/<Vertical> novo').length === 0);

// 7. count/relato de hit: doc com 2 erros → 2 contradições
const doisErros = 'stack antiga: React 18 + Pest v3';
check('doc com 2 erros devolve 2 hits', scan(doisErros).length === 2);

if (fails) {
  console.error(`\n❌ fact-anchor.test: ${fails} falha(s)`);
  process.exit(1);
}
console.log('\n✅ fact-anchor.test: todos os casos passaram');
