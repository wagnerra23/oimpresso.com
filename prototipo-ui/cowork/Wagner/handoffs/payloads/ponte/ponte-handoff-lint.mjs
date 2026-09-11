#!/usr/bin/env node
// scripts/qa/ponte-handoff-lint.mjs — valida artefatos de handoff do Cowork ANTES de virarem PR.
// Classe de falha que ele mata: pedido que cita arquivo invisível ao [CL], id de UC fora do
// limite, pedido sem prefixo/recusa declarados, sessão sem contrato de paralelismo, ✅ sem
// veredito, número medido sem data. Padrão do repo: script + *.test.mjs irmão + controle negativo.
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

export const UC_VALIDO = /^UC-[A-Z][A-Z0-9]{0,5}-\d{2}$/;
export const UC_QUALQUER = /\bUC-([A-Z0-9]+)-(\d+)\b/g;

export function lint(store) {
  const f = [];
  const push = (id, p, msg) => f.push({ id, path: p, msg });
  for (const [p, t] of Object.entries(store)) {
    const ehPedido = /_pedido-CL|COLAR-NO-CODE/.test(p);
    const ehSessao = /[1-6]0-S\d/.test(p);
    const ehCasos = p.endsWith('.casos.md');

    // V.01 — cita arquivo do Cowork sem anexo inline
    if (ehPedido) {
      const refs = new Set([...t.matchAll(/cowork-inbox\/[\w./-]+\.md/g)].map(m => m[0]).filter(r => !r.includes('/ponte/')));
      const temAnexo = /INÍCIO DO ARQUIVO|ANEXO A/.test(t);
      const temNota = /Nota de handoff/.test(t);
      for (const r of refs) if (!temAnexo && !temNota) push('V.01', p, `cita ${r} (invisível ao [CL]) sem anexo nem nota`);
    }
    // V.02 — id de UC fora do limite (prefixo <= 6 chars)
    for (const m of t.matchAll(UC_QUALQUER))
      if (!UC_VALIDO.test(m[0])) push('V.02', p, `id ${m[0]} inválido — prefixo ${m[1]} tem ${m[1].length} chars (máx 6)`);
    // V.03 — pedido sem prefixo permitido / sem recusa legítima
    if (ehPedido) {
      if (!/prefixo/i.test(t)) push('V.03', p, 'não declara prefixo permitido');
      if (!/recusa leg|Não conserte|Não mexa|Não crie/i.test(t)) push('V.03', p, 'não declara o que é recusa legítima');
    }
    // V.04 — sessão sem contrato de paralelismo (Lei 1)
    if (ehSessao && !t.includes('Contrato de paralelismo')) push('V.04', p, 'sessão sem bloco Contrato de paralelismo');
    // V.05 — UC ✅ sem veredito real
    if (ehCasos && /\|\s*✅/.test(t)) {
      const ci = (t.match(/last_run_ci:\s*"?([^"\n]*)/) || [])[1] || '';
      if (!ci.trim() || /0 UC executado|^—$/.test(ci.trim())) push('V.05', p, 'UC ✅ sem veredito em last_run_ci');
    }
    // V.06 — número medido sem data/base declarada
    const afirma = /\b\d{2,3} (telas|charters|processos|arquivos)\b/.test(t);
    if (afirma && !/^(criado|data|atualizado|base|fonte|last_run):/m.test(t)) push('V.06', p, 'número medido sem data/base no frontmatter');
  }
  return f;
}

export function controleNegativo() {
  const casos = [
    ['V.02', { 'fx.md': 'UC-PTPAINEL-01' }, 'V.02'],
    ['V.01', { '_pedido-CL-fx.md': 'ver cowork-inbox/x/y.casos.md — prefixo X, recusa legítima Y' }, 'V.01'],
    ['V.04', { '10-S1-fx.md': 'sessão sem o bloco' }, 'V.04'],
  ];
  return casos.map(([nome, store, esperado]) => ({
    nome, reprovou: lint(store).some(x => x.id === esperado),
  }));
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const dir = process.argv[2] || 'cowork-inbox/ponte';
  const store = {};
  for (const n of readdirSync(dir)) if (n.endsWith('.md')) store[join(dir, n)] = readFileSync(join(dir, n), 'utf8');
  const neg = controleNegativo();
  const negOk = neg.every(n => n.reprovou);
  for (const n of neg) console.log(`controle-negativo ${n.nome}: ${n.reprovou ? 'reprova OK' : 'NAO REPROVOU — validador quebrado'}`);
  if (!negOk) { console.error('validador sem controle negativo válido — abortando'); process.exit(2); }
  const f = lint(store);
  for (const x of f) console.log(`${x.id} · ${x.path} · ${x.msg}`);
  console.log(`\n${f.length} reprovação(ões)`);
  process.exit(f.length ? 1 : 0);
}
