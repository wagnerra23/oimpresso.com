// forja-rag.jsx — RAG mockado (busca GROUNDED no corpus: issues + changelog + ondas) e o
// auto-sugest que imita o agente propondo metadados. Consumido pela IA e pelo composer. Onda 3.
const { useState: useStateRg, useMemo: useMemoRg, useRef: useRefRg, useEffect: useEffectRg } = React;
// ─── RAG mockado: busca GROUNDED no corpus (issues + changelog + ondas) ───
function forjaRag(query) {
  const q = (query || "").toLowerCase().split(/\s+/).filter(w => w.length > 2);
  if (q.length === 0) return { verdict: "Digite uma pergunta sobre o que já foi decidido ou feito.", kind: "idle", sources: [] };
  const F = window.FORJA;
  const score = (txt) => q.reduce((a, w) => a + ((txt || "").toLowerCase().includes(w) ? 1 : 0), 0);
  const src = [];
  F.CHANGELOG.forEach(e => { const s = score(e.ref + " " + e.resumo + " " + e.modulos.join(" ")); if (s) src.push({ s, kind: e.tipo === "adr" ? "adr" : "log", ref: e.ref, label: e.resumo, when: e.data }); });
  F.ISSUES.forEach(i => { const s = score(i.titulo + " " + i.desc + " " + i.modulo + " " + i.id); if (s) src.push({ s, kind: "issue", ref: i.id, label: i.titulo, when: i.fase }); });
  F.ONDAS.forEach(o => { const s = score(o.id + " " + o.nome); if (s) src.push({ s, kind: "onda", ref: "~" + o.id, label: o.nome, when: o.estado }); });
  src.sort((a, b) => b.s - a.s);
  const top = src.slice(0, 5);
  const hasAdr = top.some(x => x.kind === "adr");
  const hasLog = top.some(x => x.kind === "log");
  let verdict;
  if (hasAdr) verdict = "Já decidido — existe ADR sobre isso. Não reproponha; cite o ADR e siga o vivo (Regra 7).";
  else if (hasLog) verdict = "Já entrou no main — há entrega no changelog. Verifique antes de refazer.";
  else if (top.length) verdict = "Há trabalho relacionado em aberto no backlog — provável duplicata.";
  else verdict = "Nada na memória casa com isso. Pode ser novo — proponha um issue (vira proposta + transporte).";
  return { verdict, kind: hasAdr ? "decidido" : hasLog ? "feito" : top.length ? "aberto" : "novo", sources: top };
}

// ─── Auto-sugest: heurística que IMITA o agente propondo metadados ───
function forjaSuggest(title) {
  const t = (title || "").toLowerCase();
  let tipo = "refino", fase = "F1", assignee = "CC", onda = null;
  if (/gate|lint|ci|e2e|playwright/.test(t)) { tipo = "gate"; assignee = "CL"; fase = "F3"; onda = "Q1"; }
  else if (/adr|token|decis|soberan/.test(t)) { tipo = "adr"; assignee = "W"; fase = "F0"; }
  else if (/bug|corrig|conserta|quebr|fix/.test(t)) { tipo = "bug"; }
  else if (/tela|drawer|p[áa]gina|tabela|kanban|board/.test(t)) { tipo = "tela"; }
  else if (/rename|migra|infra|script|fonte/.test(t)) { tipo = "infra"; assignee = "CL"; fase = "F3"; }
  else if (/doc|registro|manual/.test(t)) { tipo = "doc"; }
  if (/financ|tempero|ramp|fiscal/.test(t)) onda = "FA-1";
  return { tipo, fase, assignee, onda };
}
window.forjaRag = forjaRag;
window.forjaSuggest = forjaSuggest;
