#!/usr/bin/env node
// placar-indice.mjs — PLACAR da LISTA de um playbook (SINCRONIZAR <Mod>), derivado do repo. Zero dependências.
// Ponte pro Code (PR-A8): destino scripts/qa/placar-indice.mjs (ou flag --indice em scripts/qa/placar.mjs).
// Uso: node placar-indice.mjs --indice <00-INDICE.md | playbook.json> --root . [--proximo] [--json] [--todos 'prototipo-ui/design-docs/cowork-inbox/*/playbook/00-INDICE.md']
//   --indice aceita .json OU .md: no .md a fonte é o PRIMEIRO bloco ```json (só .md roteia pelo DesignSync — o JSON viaja embutido).
// Estado NUNCA é lido da fonte — é calculado das provas + do _saida-NN.md (Lei 2 por construção).
// _saida-NN.md é prova IMPLÍCITA de toda thread; provas explícitas são evidência de trabalho NOVO (arquivo pré-existente não é prova — falseava "em curso").
// prova com "guarda": true = PRESERVAÇÃO (já é verdade hoje e tem de continuar: ex. PDF vivo não pode sumir, selfie não pode voltar). Conta para "feito", NUNCA para "em curso".
// Aceite T5: apagar uma prova do repo derruba X→X−1 nomeando a thread e o arquivo.

export const ESTADOS = ["feito", "em curso", "proximo", "pendente", "bloqueada"];

export function extrairIndice(texto, nome = "") {
  if (nome.endsWith(".json")) return JSON.parse(texto);
  const m = texto.match(/```json\s*\n([\s\S]*?)\n```/);
  if (!m) throw new Error(`nenhum bloco \`\`\`json em ${nome || "indice"}`);
  return JSON.parse(m[1]);
}

export function resolverPath(p, variaveis = {}) {
  let indefinida = false;
  const out = p.replace(/\$\{([A-Z0-9_]+)\}/g, (_, k) => {
    const v = variaveis[k];
    if (v === null || v === undefined) { indefinida = true; return `\${${k}}`; }
    return v.replace(/\/$/, "");
  });
  return { path: out, indefinida };
}

export function avaliarProva(prova, ctx) {
  if (prova.tipo === "um_de") {
    const res = (prova.paths || []).map((p) => resolverPath(p, ctx.variaveis));
    if (res.some((r) => r.indefinida)) return { ok: false, indefinida: true, path: res.map((r) => r.path).join(" | "), motivo: "variável não decidida" };
    const achado = res.find((r) => ctx.existe(r.path));
    return { ok: !!achado, path: achado ? achado.path : res.map((r) => r.path).join(" | "), motivo: achado ? "" : "nenhuma das formas existe" };
  }
  const { path, indefinida } = resolverPath(prova.path, ctx.variaveis);
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
      const faltam = (prova.chaves || []).filter((k) => !(k in j));
      return { ok: faltam.length === 0, path, motivo: faltam.length ? `faltam chaves ${faltam.join(", ")}` : "" }; }
    default: return { ok: false, path, motivo: `tipo desconhecido ${prova.tipo}` };
  }
}

export function avaliar(indice, ctx) {
  const dir = ctx.dirPlaybook; // pasta onde vivem NN-*.md e _saida-NN.md
  const decis = Object.fromEntries((indice.decisoes || []).map((d) => [d.id, d]));
  const byId = {};
  const linhas = indice.threads.map((t) => {
    const provas = t.provas.map((p) => ({ ...p, ...avaliarProva(p, { ...ctx, variaveis: indice.variaveis || {} }) }));
    const saida = ctx.existe(`${dir}/_saida-${t.id}.md`);
    const provasOk = provas.every((p) => p.ok);
    const algumaOk = provas.some((p) => p.ok && !p.guarda);
    const decisPend = (t.depende_decisoes || []).filter((id) => !(decis[id] && decis[id].respondida));
    let estado;
    if (t.bloqueio) estado = "bloqueada";
    else if (saida && provasOk) estado = "feito";
    else if (saida || algumaOk) estado = "em curso";
    else estado = "pendente";
    const l = { id: t.id, titulo: t.titulo, dono: t.dono, vaga: t.vaga ?? null, estado, saida, provas, decisPend,
      depende_threads: t.depende_threads || [], ausentes: provas.filter((p) => !p.ok).map((p) => `${p.path} (${p.motivo})`) };
    byId[t.id] = l; return l;
  });
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
  const fs = await import("node:fs"); const path = await import("node:path");
  const arg = (k, d) => { const i = process.argv.indexOf(k); return i > -1 ? process.argv[i + 1] : d; };
  const root = path.resolve(arg("--root", "."));
  const alvos = process.argv.includes("--todos") ? (fs.globSync ? fs.globSync(arg("--todos"), { cwd: root }) : []) : [arg("--indice")];
  const ctxBase = { existe: (p) => fs.existsSync(path.join(root, p)), ler: (p) => fs.readFileSync(path.join(root, p), "utf8") };
  let exit = 0;
  for (const idxPath of alvos) {
    const indice = extrairIndice(fs.readFileSync(path.join(root, idxPath), "utf8"), idxPath);
    const r = avaliar(indice, { ...ctxBase, dirPlaybook: path.dirname(idxPath) });
    if (process.argv.includes("--json")) console.log(JSON.stringify(r, null, 2));
    else {
      console.log(r.resumo);
      for (const l of r.linhas) console.log(`  ${l.id} [${l.estado.padEnd(9)}] ${l.titulo}${l.estado === "feito" || l.estado === "bloqueada" ? "" : " — " + (l.ausentes[0] || (l.saida ? "" : "sem _saida"))}`);
      if (process.argv.includes("--proximo")) { const p = r.linhas.filter((l) => l.estado === "proximo"); console.log(p.length ? `PRÓXIMO: ${p.map((l) => l.id + " " + l.titulo + " [" + l.dono + "]").join(" · ")}` : "PRÓXIMO: nenhum executável — decisões pendentes: " + [...new Set(r.linhas.flatMap((l) => l.decisPend))].join(", ")); }
    }
    if (r.feito < r.total - r.cont.bloqueada) exit = 1;
  }
  process.exit(exit);
}
