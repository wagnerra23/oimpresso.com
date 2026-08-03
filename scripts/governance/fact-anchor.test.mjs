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

import { factAnchorScan, factAnchorTabelas, factAnchorPaths, ehPlaceholderDePath, majorFrom, tabelasAfirmadasNo52 } from './fact-anchor.mjs';

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

// ── 8. ÂNCORA DE TABELA (§5.2 dos SDDs vs schema versionado) ────────────────
// Fixtures boa/ruim da classe REAL medida em 2026-08-03: 2 contradições em 84
// afirmações, ambas erro de 1 letra (`nfe_dfe_nsu_states` vs `nfe_dfe_nsu_state`,
// `ponto_escalas_turnos` vs `ponto_escala_turnos`). Os controles negativos abaixo
// existem porque a 1ª versão do extrator era LEXICAL e deu 234 FP — todos COLUNAS.
const tabelasReais = new Set(['nfe_dfe_nsu_state', 'ponto_escala_turnos', 'ponto_escalas', 'fin_titulos', 'transactions']);
const scanTab = (txt) => factAnchorTabelas({ docs: [{ rel: 'SDD-x.md', txt }], tableExists: (t) => tabelasReais.has(t) });
const sdd = (linhas) => ['### 5.2 Modelo de dados (núcleo)', '', ...linhas, '', '### 5.3 Fluxos críticos', '| Tabela | x |', '|---|---|', '| `tabela_que_nao_existe_na_53` | y |'].join('\n');

// MORDE: a classe real (nome com "s" a mais)
check('§5.2 com tabela inexistente FLAGRA',
  scanTab(sdd(['| Tabela | Papel |', '|---|---|', '| `nfe_dfe_nsu_states` | cursor |'])).length === 1);
// controle negativo 1: o nome certo não flagra
check('§5.2 com tabela correta NÃO flagra',
  scanTab(sdd(['| Tabela | Papel |', '|---|---|', '| `nfe_dfe_nsu_state` | cursor |'])).length === 0);
// controle negativo 2 — o FP que matou a v1: COLUNA em backtick fora da coluna "Tabela"
check('coluna citada em backtick (fora da col Tabela) NÃO flagra (FP da v1 lexical)',
  scanTab(sdd(['| Tabela | Invariante |', '|---|---|', '| `fin_titulos` | `valor_aberto` ≤ `valor_total`; `business_id` obrigatório |'])).length === 0);
// controle negativo 3: `tabela.coluna` ancora na TABELA
check('`tabela.coluna` ancora na tabela (existente → 0 hit)',
  scanTab(sdd(['| Tabela | Papel |', '|---|---|', '| `fin_titulos.titulo_pai_id` | split |'])).length === 0);
check('`tabela.coluna` com tabela inexistente FLAGRA',
  scanTab(sdd(['| Tabela | Papel |', '|---|---|', '| `fin_titulo.titulo_pai_id` | split |'])).length === 1);
// controle negativo 4: duas tabelas na mesma célula (caso Ponto) — as duas são checadas
check('2 tabelas na mesma célula: só a errada flagra',
  scanTab(sdd(['| Tabela | Papel |', '|---|---|', '| `ponto_escalas` / `ponto_escalas_turnos` | escala |'])).length === 1);
// controle negativo 5: doc sem §5.2 não é varrido
check('SDD sem §5.2 NÃO flagra', scanTab('# SDD\n\n### 6. Casos de uso\n| Tabela |\n|---|\n| `nao_existe` |').length === 0);
// controle negativo 6: tabela markdown SEM cabeçalho "Tabela" é ignorada (2 SDDs reais)
check('§5.2 sem coluna "Tabela" NÃO flagra',
  scanTab(sdd(['| Campo | Papel |', '|---|---|', '| `nao_existe_nenhuma` | x |'])).length === 0);
// controle negativo 7: escopo da seção — a §5.3 não é varrida (o fixture sdd() planta isca lá)
check('tabela errada na §5.3 NÃO flagra (escopo da seção)',
  scanTab(sdd(['| Tabela | Papel |', '|---|---|', '| `fin_titulos` | ok |'])).length === 0);
// extrator puro
check('tabelasAfirmadasNo52 devolve a base sem a coluna',
  tabelasAfirmadasNo52(sdd(['| Tabela | x |', '|---|---|', '| `fin_titulos.titulo_pai_id` | y |'])).has('fin_titulos'));

// ── 9. ÂNCORA DE PATH/COMANDO (docs de instrução vs árvore real) ────────────
// Fixtures da classe medida na Fase 0 (2026-08-03). O controle negativo mais
// importante é o PLACEHOLDER: 18 dos 41 paths do corpus são padrão de nome, não
// afirmação de existência — cobrá-los seria FP por construção.
const arvore = new Set(['scripts/governance/memory-health.mjs', 'memory/proibicoes.md', 'prototipo-ui/ancora.mjs']);
const scripts = new Set(['casos:report', 'screen:files']);
const scanP = (txt) => factAnchorPaths({ docs: [{ rel: 'd.md', txt }], existe: (p) => arvore.has(p), npmScripts: scripts });

// MORDE
check('path inexistente FLAGRA', scanP('veja `scripts/governance/sumiu.mjs` aqui').length === 1);
check('`npm run` inexistente FLAGRA', scanP('rode `npm run nao-existe` antes').length === 1);
check('`node <script>` inexistente FLAGRA', scanP('rode `node scripts/governance/fantasma.mjs`').length === 1);
// controles negativos
check('path existente NÃO flagra', scanP('abra `scripts/governance/memory-health.mjs`').length === 0);
check('`npm run` existente NÃO flagra', scanP('rode `npm run casos:report` sempre').length === 0);
check('`node` existente NÃO flagra', scanP('rode `node prototipo-ui/ancora.mjs`').length === 0);
check('PLACEHOLDER <Mod> NÃO flagra', scanP('edite `Modules/<Mod>/Http/Controllers/X.php`').length === 0);
check('PLACEHOLDER YYYY-MM-DD NÃO flagra', scanP('crie `memory/sessions/YYYY-MM-DD-slug.md`').length === 0);
check('PLACEHOLDER glob * NÃO flagra', scanP('veja `memory/decisions/*.md` todos').length === 0);
check('path FORA de backtick NÃO flagra (prosa solta)', scanP('o arquivo scripts/governance/sumiu.mjs some').length === 0);
check('path de prefixo desconhecido NÃO flagra', scanP('veja `vendor/laravel/boost/x.php` aqui').length === 0);
check('doc vazio NÃO flagra', factAnchorPaths({ docs: [{ rel: 'd.md', txt: '' }] }).length === 0);
// helper puro
check('ehPlaceholderDePath("a/<X>.md") === true', ehPlaceholderDePath('a/<X>.md') === true);
check('ehPlaceholderDePath("a/b.md") === false', ehPlaceholderDePath('a/b.md') === false);
// 2 erros no mesmo doc → 2 hits
check('2 alvos mortos devolvem 2 hits', scanP('`scripts/governance/x.mjs` e `npm run nada`').length === 2);

if (fails) {
  console.error(`\n❌ fact-anchor.test: ${fails} falha(s)`);
  process.exit(1);
}
console.log('\n✅ fact-anchor.test: todos os casos passaram');
