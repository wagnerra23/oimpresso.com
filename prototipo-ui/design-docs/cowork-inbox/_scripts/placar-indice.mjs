#!/usr/bin/env node
// placar-indice.mjs — PLACAR da LISTA de um playbook (SINCRONIZAR <Mod>), derivado do repo.
// Ponte pro Code: destino sugerido scripts/qa/placar-indice.mjs (ou flag --indice em scripts/qa/placar.mjs, PR-A8).
// Uso: node placar-indice.mjs --indice cowork-inbox/hrm/playbook/playbook.json --root . [--proximo] [--json] [--todos 'cowork-inbox/*/playbook/playbook.json']
// Estado NUNCA é lido do JSON — é calculado das provas + do _saida-NN.md (Lei 2 por construção).
// _saida-NN.md é prova IMPLÍCITA de toda thread; provas explícitas são evidência de trabalho NOVO (arquivo pré-existente não é prova — falseava "em curso").
// Aceite T5: apagar uma prova do repo derruba X→X−1 nomeando a thread (ver teste no fim).

import { readdirSync, existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { compararNoRepo } from './placar-comparacao.mjs';

export function descobrirIndices(root) {
  const base = 'prototipo-ui/design-docs/cowork-inbox';
  if (!existsSync(join(root, base))) return [];
  return readdirSync(join(root, base), { withFileTypes: true })
    .filter(d => d.isDirectory())
    .map(d => `${base}/${d.name}/playbook/00-INDICE.md`)
    .filter(p => existsSync(join(root, p))).sort();
}

export function coberturaModulos(root) {
  const porModulo = new Map(), semVinculo = [];
  for (const p of descobrirIndices(root)) {
    const index=JSON.parse(readFileSync(join(root,p),'utf8').match(/```json\s*\n([\s\S]*?)\n```/)[1]);
    validarIndice(index);
    if (!index.modulo_codigo) { semVinculo.push({modulo:null,scope:null,playbook:p,entrada:'vínculo de módulo não declarado; consultar índice',status:'não avaliado'}); continue; }
    if (porModulo.has(index.modulo_codigo)) throw Error('Mais de um playbook para '+index.modulo_codigo);
    porModulo.set(index.modulo_codigo,p);
  }
  const modulos = readdirSync(join(root,'memory/requisitos'),{withFileTypes:true})
    .filter(d=>d.isDirectory() && existsSync(join(root,'memory/requisitos',d.name,'SCOPE.md')))
    .map(d=>({modulo:d.name,scope:'memory/requisitos/'+d.name+'/SCOPE.md',
      playbook:porModulo.get(d.name)||null,
      entrada:porModulo.has(d.name)?'playbook':'contratos do módulo + design-sync',
      status:'não avaliado'})).sort((a,b)=>a.modulo.localeCompare(b.modulo));
  for (const m of porModulo.keys()) if (!modulos.some(r=>r.modulo===m)) throw Error('Módulo declarado sem SCOPE: '+m);
  return [...modulos,...semVinculo];
}

import { validarIndice, avaliarExecucao, pathSeguro } from './placar-evidencia.mjs';
export const ESTADOS = ["feito", "em curso", "proximo", "pendente", "bloqueada"];
export const proximas = (r) => r.linhas.filter(l => l.executavel);

export function resolverPath(p, variaveis = {}) {
  let indefinida = false;
  const out = p.replace(/\$\{([A-Z0-9_]+)\}/g, (_, k) => {
    const v = variaveis[k];
    if (v === null || v === undefined) { indefinida = true; return `\${${k}}`; }
    return v.replace(/\/$/, "");
  });
  if (!pathSeguro(out)) throw Error(`Path resolvido fora do repositório: ${out}`);
  return { path: out, indefinida };
}

export function avaliarProva(prova, ctx) {
  if (['execucao','comparacao','revisao'].includes(prova.tipo)) {
    const resolver = p => resolverPath(p, ctx.variaveis);
    const paths=[prova.path,...(prova.testes || []),...(prova.fontes || []),...(prova.contrato ? [prova.contrato] : [])].map(resolver);
    if (paths.some(p=>p.indefinida)) return {ok:false,indefinida:true,path:prova.path,motivo:'variável não decidida'};
    return avaliarExecucao({...prova,path:resolver(prova.path).path,...(prova.contrato?{contrato:resolver(prova.contrato).path}:{})}, {...ctx,resolver:p=>resolver(p).path});
  }
  // `um_de` usa `paths` (plural) e basta UMA existir — é como o schema expressa
  // "flat Licencas.tsx OU pasta Licencas/Index.tsx, quem decide é o criar-tela.mjs".
  // Tem de vir ANTES do resolverPath: sem `prova.path`, o replace estourava.
  if (prova.tipo === "um_de") {
    const alts = (prova.paths || []).map((p) => resolverPath(p, ctx.variaveis));
    if (alts.some((a) => a.indefinida)) return { ok: false, indefinida: true, path: alts[0]?.path ?? "", motivo: "variável não decidida" };
    const achado = alts.find((a) => ctx.existe(a.path));
    return { ok: !!achado, path: achado ? achado.path : (alts[0]?.path ?? ""), motivo: achado ? "" : `nenhum de ${alts.map((a) => a.path).join(" | ")}` };
  }
  const { path, indefinida } = resolverPath(prova.path ?? "", ctx.variaveis);
  if (indefinida) return { ok: false, indefinida: true, path, motivo: "variável não decidida" };
  const existe = ctx.existe(path);
  switch (prova.tipo) {
    case "arquivo": return { ok: existe, path, motivo: existe ? "" : "arquivo ausente" };
    case "ausente": return { ok: !existe, path, motivo: existe ? "arquivo ainda existe" : "" };
    case "contem": { if (!existe) return { ok: false, path, motivo: "arquivo ausente" };
      const ok = ctx.ler(path).includes(prova.padrao); return { ok, path, motivo: ok ? "" : `não contém "${prova.padrao}"` }; }
    case "nao_contem": { if (!existe) return { ok: false, path, motivo: "arquivo ausente" };
      const ok = !ctx.ler(path).includes(prova.padrao); return { ok, path, motivo: ok ? "" : `ainda contém "${prova.padrao}"` }; }
    case "json_com_chaves": { if (!existe) return { ok: false, path, motivo: "arquivo ausente" };
      let j; try { j = JSON.parse(ctx.ler(path)); } catch { return { ok: false, path, motivo: "JSON inválido" }; }
      if (!j || typeof j !== 'object' || Array.isArray(j)) return { ok: false, path, motivo: 'JSON precisa ser objeto' };
      const faltam = (prova.chaves || []).filter((k) => !(k in j));
      return { ok: faltam.length === 0, path, motivo: faltam.length ? `faltam chaves ${faltam.join(", ")}` : "" }; }
    default: return { ok: false, path, motivo: `tipo desconhecido ${prova.tipo}` };
  }
}

export function avaliar(indice, ctx) {
  validarIndice(indice);
  const dir = ctx.dirPlaybook; // pasta onde vivem NN-*.md e _saida-NN.md
  const decis = Object.fromEntries((indice.decisoes || []).map((d) => [d.id, d]));
  const byId = {};
  const linhas = indice.threads.map((t) => {
    const provas = t.provas.map((p) => ({ ...p, ...avaliarProva(p, { ...ctx, thread: t, saidaPath: `${dir}/_saida-${t.id}.md`, variaveis: indice.variaveis || {} }) }));
    const saida = ctx.existe(`${dir}/_saida-${t.id}.md`);
    const provasOk = provas.every((p) => p.ok) && provas.some(p => ['execucao','comparacao','revisao'].includes(p.tipo) && p.ok);
    const decisPend = (t.depende_decisoes || []).filter((id) => !(decis[id] && decis[id].respondida));
    let estado;
    if (t.bloqueio) estado = "bloqueada";
    else if (saida) estado = "em curso";
    else estado = "pendente";
    const l = { id: t.id, titulo: t.titulo, dono: t.dono, vaga: t.vaga ?? null, estado, saida, provas, decisPend,
      pronto: saida && provasOk, executavel: false,
      depende_threads: t.depende_threads || [], ausentes: provas.filter((p) => !p.ok).map((p) => `${p.path} (${p.motivo})`) };
    if (!provas.some(p => ['execucao','comparacao','revisao'].includes(p.tipo))) l.ausentes.push('sem evidência de conclusão — estrutura não prova entrega');
    byId[t.id] = l; return l;
  });
  // Resolve em ordem topológica: recibo verde também depende das predecessoras.
  const resolvidas = new Set();
  function resolver(l) {
    if (resolvidas.has(l.id)) return;
    for (const id of l.depende_threads) resolver(byId[id]);
    const depsOk = l.depende_threads.every(id => byId[id].estado === 'feito');
    if (l.estado !== 'bloqueada' && depsOk && !l.decisPend.length && l.pronto) l.estado = 'feito';
    resolvidas.add(l.id);
  }
  for (const l of linhas) resolver(l);
  for (const l of linhas) {
    if (l.estado === "bloqueada" || l.estado === "feito") continue;
    const depsOk = l.depende_threads.every((id) => byId[id] && byId[id].estado === "feito");
    const semIndef = !l.provas.some((p) => p.indefinida);
    l.executavel = depsOk && l.decisPend.length === 0 && semIndef;
    if (l.executavel && l.estado === "pendente") l.estado = "proximo";
  }
  const cont = Object.fromEntries(ESTADOS.map((e) => [e, linhas.filter((l) => l.estado === e).length]));
  const total = linhas.length;
  return { modulo: indice.modulo, sha: indice.sha, total, feito: cont.feito, cont, linhas,
    resumo: `${indice.modulo}: entregue ${cont.feito} de ${total} · próximo ${cont.proximo} · em curso ${cont["em curso"]} · pendente ${cont.pendente} · bloqueada ${cont.bloqueada}`,
    ausentes: linhas.filter((l) => l.estado !== "feito" && l.estado !== "bloqueada").map((l) => `${l.id} ${l.titulo} — ${l.saida ? "" : "sem _saida; "}${l.decisPend.length ? "decisão pendente " + l.decisPend.join(",") + "; " : ""}${l.ausentes.join("; ")}`) };
}

// --- CLI ---
const isMain = typeof process !== "undefined" && process.argv && process.argv[1] && import.meta.url.endsWith(process.argv[1].split(/[\\/]/).pop());
if (isMain) {
  const fs = await import("node:fs"); const path = await import("node:path"); const { globSync } = await import("node:fs");
  const arg = (k, d) => { const i = process.argv.indexOf(k); return i > -1 ? process.argv[i + 1] : d; };
  const root = path.resolve(arg("--root", "."));
  if (process.argv.includes('--modulos')) {
    try { console.log(JSON.stringify(coberturaModulos(root),null,2)); process.exit(0); }
    catch (e) {console.error(e.message);process.exit(2);}
  }
  const todos = process.argv.includes('--todos');
  const padrao = arg('--todos');
  let alvos;
  if (todos && padrao && !padrao.startsWith('--')) {
    if (!globSync) { console.error('--todos com glob exige Node >=22; use --todos sem padrão no Node 20'); process.exit(2); }
    alvos = globSync(padrao, { cwd: root });
  } else alvos = todos ? descobrirIndices(root) : [arg('--indice')].filter(Boolean);
  if (!alvos.length) { console.error('Nenhum índice selecionado; use --indice <arquivo> ou --todos'); process.exit(2); }
  const ctxBase = { comparar: (r,p) => compararNoRepo(root,r,p), existe: (p) => fs.existsSync(path.join(root, p)), ler: (p) => fs.readFileSync(path.join(root, p), "utf8") };
  let exit = 0;
  for (const idxPath of alvos) {
    // A fonte é o PRIMEIRO bloco ```json embutido no 00-INDICE.md — só .md roteia pelo
    // DesignSync, então .json solto não chega (00-INDICE §0 + _schema/playbook.schema.json).
    // Antes daqui o script fazia JSON.parse do arquivo cru e só rodava com um playbook.json
    // paralelo, que é justamente o segundo dono do estado que o §0 proíbe.
    let bruto;
    try { bruto = fs.readFileSync(path.join(root, idxPath), "utf8"); }
    catch (e) { console.error(`${idxPath}: ${e.message}`); exit = 2; continue; }
    const embutido = idxPath.endsWith(".md") ? (bruto.match(/```json\s*\n([\s\S]*?)\n```/) || [])[1] : bruto;
    if (!embutido) { console.error(`sem bloco json embutido em ${idxPath}`); exit = 2; continue; }
    let indice;
    try { indice = JSON.parse(embutido); validarIndice(indice); }
    catch (e) { console.error(`${idxPath}: ${e.message}`); exit = 2; continue; }
    const r = avaliar(indice, { ...ctxBase, dirPlaybook: path.dirname(idxPath) });
    if (process.argv.includes("--json")) console.log(JSON.stringify(r, null, 2));
    else {
      console.log(r.resumo);
      for (const l of r.linhas) console.log(`  ${l.id} [${l.estado.padEnd(9)}] ${l.titulo}${l.estado === "feito" || l.estado === "bloqueada" ? "" : " — " + (l.ausentes[0] || (l.saida ? "" : "sem _saida"))}`);
      if (process.argv.includes("--proximo")) { const p = proximas(r); console.log(p.length ? `PRÓXIMO: ${p.map((l) => l.id + " " + l.titulo + " [" + l.dono + "]" + (l.estado === 'em curso' ? ' (retomar/validar)' : '')).join(" · ")}` : "PRÓXIMO: nenhum executável — consulte dependências e decisões acima"); }
    }
    if (r.feito < r.total - r.cont.bloqueada) exit = Math.max(exit, 1);
  }
  process.exit(exit);
}
