// forja-handoff.jsx — handoff zero-toque: release notes da onda + prompt pro [CL] em 1 paste.
// Extraído de forja-mcp.jsx (Onda 3).
const { useState: useStateHo, useMemo: useMemoHo, useRef: useRefHo, useEffect: useEffectHo } = React;
// ─── Handoff zero-toque: release notes + prompt pro [CL] (1 paste do [W]) ───
function ForjaHandoff({ onda, onClose }) {
  const F = window.FORJA;
  const o = F.ONDAS.find(x => x.id === onda);
  const issues = F.ISSUES.filter(i => i.onda === onda);
  const shipped = F.CHANGELOG.filter(e => e.ref.includes(onda));
  const [copied, setCopied] = useStateHo(false);
  const prompt =
`# Handoff zero-toque — onda ${onda}${o ? " (" + o.nome + ")" : ""}

Claude Code: aplique os issues abaixo no repo wagnerra23/oimpresso.com, abra PRs e
mergeie quando o CI ficar verde. NÃO toque na constituição (ADR/PROTOCOL/BRIEFING).
Merge fechado só por [W2].

## Issues da onda
${issues.map(i => `- [${i.id}] ${i.titulo}\n  fase ${i.fase} · ${i.modulo} · prio ${i.prio} · vínculos: ${i.vinculos.map(v => v.k + ":" + v.v).join(", ") || "—"}`).join("\n")}

## Já no main (não refazer)
${shipped.length ? shipped.map(e => `- ${e.ref} · ${e.resumo}`).join("\n") : "- (nada fechado ainda nesta onda)"}

## Transporte
URLs públicas (get_public_file_url ~1h) das fontes serão coladas aqui no transporte.
Wagner cola UMA vez no Claude Code — não toca em mais nada.`;
  const copy = () => { try { navigator.clipboard.writeText(prompt); setCopied(true); setTimeout(() => setCopied(false), 1600); } catch (e) {} };
  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-handoff" onClick={e => e.stopPropagation()}>
        <div className="fj-new-head"><span className="fj-ia-spark">✦</span><b>Handoff · onda {onda}</b><span className="fj-new-note">release notes + prompt pro [CL] · 1 paste do [W]</span><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <div className="fj-handoff-body"><pre className="fj-handoff-pre">{prompt}</pre></div>
        <div className="fj-new-foot">
          <span className="fj-foot-note">⚠ [CC]/MCP não commitam — o transporte é o paste do [W]. Não afirmo "commitado".</span>
          <button className="os-btn primary" onClick={copy}>{copied ? "copiado ✓" : "Copiar prompt"}</button>
        </div>
      </div>
    </div>
  );
}
window.ForjaHandoff = ForjaHandoff;
