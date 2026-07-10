#!/usr/bin/env node
// @ts-check
/**
 * critica.test.mjs — selftest das partes DETERMINÍSTICAS do passe crítico
 * (split de diff, trava de citação, AGREGAÇÃO DE VOTOS das lentes, montagem do
 * comentário + bloco machine-readable). As chamadas ao agente (finder e lentes)
 * NÃO são testadas aqui (é LLM; a trava mecânica + o voto majoritário são a
 * defesa). Node puro, sem rede.
 */

import assert from 'node:assert/strict';
import {
  agregarVotos,
  filtrarPorCitacao,
  idAchado,
  MARCADOR_DADOS,
  montarBlocoDados,
  montarComentario,
  montarPromptLente,
  normaliza,
  resolverProvider,
  separarDiffPorArquivo,
} from './critica.mjs';

let passou = 0;
function t(nome, fn) { fn(); passou++; console.log(`  ok - ${nome}`); }

t('separarDiffPorArquivo divide unified diff por path do lado b/', () => {
  const diff = [
    'diff --git a/resources/js/Pages/X/Index.tsx b/resources/js/Pages/X/Index.tsx',
    'index 111..222 100644',
    '--- a/resources/js/Pages/X/Index.tsx',
    '+++ b/resources/js/Pages/X/Index.tsx',
    '@@ -1 +1 @@',
    '-a',
    '+b',
    'diff --git a/Modules/Y/Z.php b/Modules/Y/Z.php',
    '@@ -2 +2 @@',
    '-c',
    '+d',
  ].join('\n');
  const blocos = separarDiffPorArquivo(diff);
  assert.equal(blocos.size, 2);
  assert.ok(blocos.get('resources/js/Pages/X/Index.tsx').includes('+b'));
  assert.ok(blocos.get('Modules/Y/Z.php').includes('+d'));
  assert.ok(!blocos.get('Modules/Y/Z.php').includes('+b'), 'hunk de um arquivo vazou pro outro');
});

t('trava de citação: mantém citação literal, descarta alucinada/curta/de contrato errado', () => {
  const contratos = new Map([
    ['charter.md', 'O primary do header é "Novo título" e abre TituloCreateSheet.\nSidebar permanece light.'],
  ]);
  const base = { arquivo: 'a.tsx', achado: 'x', severidade: 'alta', confianca: 'alta' };
  const { validos, descartados } = filtrarPorCitacao([
    // literal (whitespace/caixa diferentes — normaliza cobre)
    { ...base, contrato: 'charter.md', citacao_contrato: 'o primary do header é "novo título"   e abre TituloCreateSheet.' },
    // alucinada — não existe no contrato
    { ...base, contrato: 'charter.md', citacao_contrato: 'O header deve usar gradiente roxo em telas de venda.' },
    // curta demais pra ancorar
    { ...base, contrato: 'charter.md', citacao_contrato: 'header' },
    // contrato inexistente
    { ...base, contrato: 'outro.md', citacao_contrato: 'Sidebar permanece light.' },
  ], contratos);
  assert.equal(validos.length, 1);
  assert.equal(descartados.length, 3);
});

t('normaliza é estável pra comparação (whitespace + caixa)', () => {
  assert.equal(normaliza('  Foo\n\tBar  BAZ '), 'foo bar baz');
});

t('comentário carrega marcador, achados, e rodapé honesto (caps/descartes visíveis)', () => {
  const md = montarComentario({
    resultados: [{ grupo: 'resources/js/Pages/X', achados: [{ arquivo: 'a.tsx', contrato: 'charter.md', citacao_contrato: 'invariante Y', achado: 'diff remove Y', severidade: 'alta', confianca: 'media' }] }],
    semContrato: [{ id: 'resources/js/Pages/SemNada' }],
    descartadosCap: [{ id: 'resources/js/Pages/Cortado' }],
    totalDescartadosCitacao: 2,
    totalTruncados: 1,
    modelo: 'claude-teste',
  });
  assert.ok(md.includes('<!-- pr-critic-contrato -->'));
  assert.ok(md.includes('diff remove Y'));
  assert.ok(md.includes('SemNada'), 'grupo sem contrato precisa aparecer no rodapé');
  assert.ok(md.includes('Cortado'), 'grupo cortado pelo teto precisa aparecer no rodapé (no silent caps)');
  assert.ok(md.includes('2 achado(s) descartado(s) pela trava'));
  assert.ok(md.includes('Advisory'));
});

t('comentário sem achados declara coerência explícita', () => {
  const md = montarComentario({ resultados: [{ grupo: 'g', achados: [] }], semContrato: [], descartadosCap: [], totalDescartadosCitacao: 0, totalTruncados: 0, modelo: 'm' });
  assert.ok(md.includes('Nenhuma incoerência'));
});

t('resolverProvider: anthropic tem prioridade; openai é fallback; sem chave = null; override respeita', () => {
  assert.deepEqual(resolverProvider({ ANTHROPIC_API_KEY: 'a', OPENAI_API_KEY: 'o' }), { provider: 'anthropic', modelo: 'claude-opus-4-8' });
  assert.deepEqual(resolverProvider({ OPENAI_API_KEY: 'o' }), { provider: 'openai', modelo: 'gpt-4o' });
  assert.equal(resolverProvider({}), null);
  assert.deepEqual(resolverProvider({ ANTHROPIC_API_KEY: 'a', OPENAI_API_KEY: 'o', PR_CRITIC_PROVIDER: 'openai', PR_CRITIC_MODEL: 'gpt-4o-mini' }), { provider: 'openai', modelo: 'gpt-4o-mini' });
  // provider forçado sem a chave correspondente = null (nunca chamada quebrada)
  assert.equal(resolverProvider({ OPENAI_API_KEY: 'o', PR_CRITIC_PROVIDER: 'anthropic' }), null);
});

// ── agregação de votos (perspective-diverse verify) — bite/release ───────────
t('agregarVotos: maioria confirma → sobrevive; maioria refuta → cai (bite)', () => {
  const rel = agregarVotos([{ veredito: 'confirma' }, { veredito: 'confirma' }, { veredito: 'refuta' }]);
  assert.equal(rel.sobrevive, true, 'release: 2/3 confirma deve sobreviver');
  assert.equal(rel.confirma, 2);
  const bite = agregarVotos([{ veredito: 'confirma' }, { veredito: 'refuta' }, { veredito: 'refuta' }]);
  assert.equal(bite.sobrevive, false, 'bite: só 1/3 confirma deve cair (falso-positivo)');
});

t('agregarVotos: voto ausente/inválido NÃO conta como confirma (fail-safe)', () => {
  // 1 confirma + 2 nulos/ruído: NÃO atinge maioria (min 2) → cai
  const r = agregarVotos([{ veredito: 'confirma' }, null, { veredito: 'talvez' }]);
  assert.equal(r.confirma, 1);
  assert.equal(r.votos_validos, 1);
  assert.equal(r.sobrevive, false);
});

t('agregarVotos: minConfirma configurável (unânime exigido)', () => {
  const r = agregarVotos([{ veredito: 'confirma' }, { veredito: 'confirma' }, { veredito: 'refuta' }], { minConfirma: 3 });
  assert.equal(r.sobrevive, false, 'exigindo 3, 2/3 não basta');
});

t('montarPromptLente dá SÓ citação + hunk + alegação (contexto zero)', () => {
  const p = montarPromptLente(
    { arquivo: 'a.tsx', contrato: 'charter.md', citacao_contrato: 'sidebar permanece light', achado: 'diff troca sidebar pra dark' },
    'diff --git a/a.tsx b/a.tsx\n-light\n+dark',
  );
  assert.ok(p.includes('sidebar permanece light'), 'citação presente');
  assert.ok(p.includes('+dark'), 'hunk presente');
  assert.ok(p.includes('diff troca sidebar pra dark'), 'alegação presente');
  assert.ok(!/sess[aã]o|árvore|tree/i.test(p), 'não vaza contexto além do diff+contrato');
});

t('idAchado é estável e insensível a whitespace da citação', () => {
  const base = { arquivo: 'a.tsx', contrato: 'c.md', citacao_contrato: 'invariante Y do header' };
  assert.equal(idAchado(base), idAchado({ ...base, citacao_contrato: '  invariante   Y   do header ' }));
  assert.notEqual(idAchado(base), idAchado({ ...base, arquivo: 'b.tsx' }));
});

t('montarBlocoDados embute JSON machine-readable só dos sobreviventes', () => {
  const bloco = montarBlocoDados({
    modelo: 'm',
    resultados: [{ grupo: 'g', achados: [
      { arquivo: 'a.tsx', contrato: 'c.md', citacao_contrato: 'inv Y do header xyz', severidade: 'alta', votos: [{ veredito: 'confirma' }] },
    ] }],
  });
  assert.ok(bloco.startsWith(MARCADOR_DADOS), 'marcador presente');
  const json = JSON.parse(bloco.slice(MARCADOR_DADOS.length, bloco.lastIndexOf('-->')).trim());
  assert.equal(json.v, 1);
  assert.equal(json.achados.length, 1);
  assert.equal(json.achados[0].arquivo, 'a.tsx');
  assert.equal(json.achados[0].verificado, true);
});

t('comentário com voto mostra placar + conta descartes por voto/não-verificados no rodapé', () => {
  const md = montarComentario({
    resultados: [{ grupo: 'resources/js/Pages/X', achados: [
      { arquivo: 'a.tsx', contrato: 'charter.md', citacao_contrato: 'invariante Y', achado: 'diff remove Y', severidade: 'alta', confianca: 'media',
        votos: [{ lente: 'contradicao-literal', rotulo: 'contradição literal', veredito: 'confirma' }, { lente: 'regressao-vs-adicao', rotulo: 'regressão vs adição', veredito: 'confirma' }, { lente: 'advogado-do-diff', rotulo: 'advogado do diff', veredito: 'refuta' }] },
    ] }],
    semContrato: [], descartadosCap: [], totalDescartadosCitacao: 0, totalDescartadosVoto: 3, totalNaoVerificados: 1, totalTruncados: 0, modelo: 'm',
  });
  assert.ok(md.includes('✓ 2/3 lentes'), 'placar de voto exibido');
  assert.ok(md.includes('advogado do diff'), 'lente que refutou nomeada');
  assert.ok(md.includes('3 achado(s) descartado(s) pelas lentes'), 'descarte por voto no rodapé (no silent caps)');
  assert.ok(md.includes('1 achado(s) exibido(s) SEM verificação'), 'não-verificados no rodapé');
  assert.ok(md.includes(MARCADOR_DADOS), 'bloco machine-readable presente no comentário');
});

console.log(`\ncritica.test.mjs: ${passou} teste(s) OK`);
