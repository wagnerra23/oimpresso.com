// Validação do playbook e da evidência: o placar não executa comandos do documento.
import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import Ajv from 'ajv';
import addFormats from 'ajv-formats';
import { validarPlaywright, validarRevisao } from './placar-formatos.mjs';

const ajv = new Ajv({ allErrors: true, strict: false });
addFormats(ajv);
const validar = ajv.compile(JSON.parse(readFileSync(new URL('../_schema/playbook.schema.json', import.meta.url))));
export const hash = (texto) => createHash('sha256').update(texto).digest('hex');
export const pathSeguro = (p) => typeof p === 'string' && p.length > 0 && !/^[\\/]|^[A-Za-z]:|\\/.test(p) && !p.split('/').includes('..');

export function validarIndice(indice) {
  if (!validar(indice)) throw Error(`Playbook inválido: ${ajv.errorsText(validar.errors)}`);
  for (const p of Object.values(indice.variaveis || {})) if (p !== null && !pathSeguro(p)) throw Error(`Variável fora do repositório: ${p}`);
  const ids = new Set(), decisoes = new Set();
  for (const d of indice.decisoes || []) {
    if (decisoes.has(d.id)) throw Error(`Decisão duplicada: ${d.id}`);
    decisoes.add(d.id);
  }
  for (const t of indice.threads) {
    if (ids.has(t.id)) throw Error(`Thread duplicada: ${t.id}`);
    ids.add(t.id);
    for (const p of [t.arquivo, ...t.prefixo, ...(t.nao_toca || []), ...t.provas.flatMap(p => [...(p.tipo === 'um_de' ? p.paths : [p.path]), ...(p.testes || []), ...(p.fontes || []), ...(p.contrato ? [p.contrato] : []), ...(p.raiz_testes ? [p.raiz_testes] : [])])]) {
      if (!pathSeguro(p)) throw Error(`Path fora do repositório: ${p}`);
    }
  }
  const visitados = new Set(), pilha = new Set();
  function visitar(id) {
    if (!ids.has(id)) throw Error(`Dependência inexistente: ${id}`);
    if (pilha.has(id)) throw Error(`Dependência circular: ${id}`);
    if (visitados.has(id)) return;
    pilha.add(id);
    const t = indice.threads.find(t => t.id === id);
    for (const d of t.depende_decisoes || []) if (!decisoes.has(d)) throw Error(`Decisão inexistente: ${d}`);
    for (const dep of t.depende_threads || []) visitar(dep);
    pilha.delete(id); visitados.add(id);
  }
  for (const id of ids) visitar(id);
}

// Reusa o JSON produzido por scripts/tests/junit-summary.mjs. O recibo vincula
// saída, resumo JUnit, teste e arquivos implementados pelos respectivos SHA-256.
// Não certifica a honestidade do emissor; certifica execução declarada e frescor.
export function avaliarExecucao(prova, ctx) {
  const falha = (motivo) => ({ ok: false, path: prova.path, motivo });
  try {
    const r = JSON.parse(ctx.ler(prova.path));
    if (r.thread !== ctx.thread.id) return falha('recibo pertence a outra tarefa');
    if (prova.tipo === 'execucao' && (r.exitCode !== 0 || !['ct100', 'ci'].includes(r.runner)
      || !Array.isArray(r.command) || !r.command.length || r.command.some(x => typeof x !== 'string' || !x.trim()))) return falha('recibo sem execução válida desta thread');
    const arquivos = r.arquivos;
    if (!arquivos || typeof arquivos !== 'object' || Array.isArray(arquivos)) return falha('hashes ausentes');
    if (prova.tipo === 'execucao' && (!Array.isArray(r.testes) || !r.testes.length || prova.testes.map(ctx.resolver).some(p => !r.testes.includes(p)))) return falha('recibo não executou os testes exigidos pelo plano');
    const obrigatorios = [ctx.saidaPath, ...(prova.tipo === 'execucao' ? [r.summary, ...r.testes] : prova.fontes.map(ctx.resolver)),
      ...(prova.tipo === 'comparacao' ? [r.producao, r.prototipo, prova.contrato] : []),
      ...ctx.thread.provas.filter(p => !['execucao','revisao','comparacao','ausente'].includes(p.tipo)).flatMap(p => {
        const candidatos = (p.paths || [p.path]).map(ctx.resolver);
        return p.tipo === 'um_de' ? [candidatos.find(p => ctx.existe(p) && Object.hasOwn(arquivos,p))] : candidatos;
      })];
    if (obrigatorios.some(p => !pathSeguro(p) || !Object.hasOwn(arquivos, p))) return falha('recibo não vincula saída, testes e alvos');
    for (const [p, h] of Object.entries(arquivos)) {
      if (!pathSeguro(p) || !/^[a-f0-9]{64}$/.test(h) || !ctx.existe(p) || hash(ctx.ler(p)) !== h) return falha(`evidência ausente ou stale: ${p}`);
    }
    if (prova.tipo === 'revisao') {
      if (ctx.thread.prefixo.some(p => !/\.md$|\.contract\.json$/.test(p))) return falha('revisão documental não encerra alteração de código');
      return validarRevisao(r, prova.criterios) ? {ok:true,path:prova.path,motivo:''} : falha('parecer incompleto ou não aprovado');
    }
    if (prova.tipo === 'comparacao') {
      if (!ctx.comparar) return falha('comparador não disponível');
      const resultado = ctx.comparar(r, prova);
      return resultado ? {ok:true,path:prova.path,motivo:''} : falha('comparação divergente, incompleta ou sem proveniência');
    }
    const s = JSON.parse(ctx.ler(r.summary));
    if (prova.formato === 'playwright-json') {
      return validarPlaywright(s, r.testes, prova.raiz_testes) ? {ok:true,path:prova.path,motivo:''} : falha('Playwright sem execução integral dos arquivos exigidos');
    }
    if (s.schema !== 'junit-summary/v1' || s.invalid || s.coherent !== true || s.provou_algo !== true
      || !Array.isArray(s.files)) return falha('resumo JUnit inválido');
    for (const p of r.testes) {
      const f = s.files.find(f => f.file === p);
      if (!f || !(f.passed > 0) || !(f.assertions > 0) || f.failed !== 0 || f.errors !== 0 || f.skipped !== 0
        || f.tests !== f.passed) return falha(`teste não comprovado integralmente: ${p}`);
    }
    return { ok: true, path: prova.path, motivo: '' };
  } catch { return falha('recibo ausente ou ilegível'); }
}
