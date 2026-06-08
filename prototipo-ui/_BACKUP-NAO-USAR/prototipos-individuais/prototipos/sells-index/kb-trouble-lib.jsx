// kb-trouble-lib.jsx — Biblioteca de troubleshooters + Modo apresentação + helpers de link
// Exporta: KB_TROUBLES, KBTroubleshooterDialog, KBPresenter, kbLinkifyText
(() => {
const { useState, useEffect, useMemo } = React;

// ─────────────────────────────────────────────────────────────────
// BIBLIOTECA DE TROUBLESHOOTERS — 3 árvores reais
// ─────────────────────────────────────────────────────────────────
// Formato: cada step tem q (pergunta) + yes/no (number = vai pra step N; objeto {fix} = solução)
// fix pode citar artigos como #a3 — viram link clicável via kbLinkifyText

const KB_TROUBLES = [
  {
    id: "tr-roland",
    title: "Roland VS-540 não imprime",
    equip: "Roland VS-540",
    hue: 280,
    when: "máquina parou ou job não sai",
    steps: [
      { q: "A impressora liga (LCD acende, motor faz ruído inicial)?",
        yes: 1,
        no:  { fix: "Cheque cabo de força e disjuntor da bancada. Se ok, abra OS interna 'manutenção elétrica'." } },
      { q: "Reconhece a bobina (mostra largura no painel)?",
        yes: 2,
        no:  { fix: "Recarregue: Unload + Load. Se persistir, limpe o sensor de mídia com pano seco — área branca à esquerda. Detalhe em #a2." } },
      { q: "Os bicos (nozzle check) saem completos?",
        yes: 3,
        no:  { fix: "Faça a rotina de limpeza diária — passo a passo em #a3. Se faltar bico após 2 limpezas, abra OS 'manutenção cabeça'." } },
      { q: "O VersaWorks reconhece a impressora (status verde)?",
        yes: 4,
        no:  { fix: "Cabo USB ou rede caiu. Reinicie o serviço Roland Print no Windows (services.msc → restart). Se persistir, troque o cabo USB." } },
      { q: "O job inicia mas para no meio?",
        yes: { fix: "Provavelmente perfil ICC errado pro material. Confira #a1 e reaplique o perfil correto antes de re-enviar." },
        no:  { fix: "Tudo certo na máquina. Re-envie o job e monitore o primeiro 1 metro. Se sair com cor estranha, confira #a1." } },
    ],
  },
  {
    id: "tr-latex",
    title: "HP Latex 365 — cor saindo errada",
    equip: "HP Latex 365",
    hue: 220,
    when: "cliente reclamou de cor, ΔE > 4",
    steps: [
      { q: "A bobina é nova (trocada nas últimas 24h)?",
        yes: 1,
        no:  { fix: "Antes de tudo, refaça calibragem ICC — bobinas envelhecem. Procedimento em #a1." } },
      { q: "O ICC aplicado bate com o LOTE da bobina nova?",
        yes: 2,
        no:  { fix: "Aplique o ICC correto. Cada lote tem um perfil — Mateus arquiva em VersaWorks/Profiles. Procedimento em #a1." } },
      { q: "A laminação foi feita antes ou depois do problema aparecer?",
        yes: 3,
        no:  { fix: "Imprima nova prova SEM laminação. Se cor ok, problema é o filme de laminação — troque lote." } },
      { q: "O ambiente da gráfica está com temperatura > 28°C?",
        yes: { fix: "Tinta Latex sofre acima de 28°C. Resfrie a sala (AC mínimo 22°C) e re-imprima após 30 min de estabilização." },
        no:  { fix: "Refaça calibragem ICC completa (#a1). Se ΔE continuar acima de 4 após calibragem, abra OS 'assistência HP'." } },
    ],
  },
  {
    id: "tr-nfe",
    title: "NF-e rejeitada pela SEFAZ",
    equip: "—",
    hue: 60,
    when: "código de rejeição apareceu",
    steps: [
      { q: "A rejeição é código 539 (duplicidade)?",
        yes: { fix: "Você emitiu esse número antes. Vá em Fiscal → NF-e → consultar último número emitido. Use o próximo da sequência. Mais em #a9." },
        no:  1 },
      { q: "É código 692 (Inscrição Estadual inválida)?",
        yes: { fix: "IE do destinatário está errada ou desativada. Consulte o cliente no SINTEGRA da UF dele e atualize o cadastro. Mais em #a9." },
        no:  2 },
      { q: "É código 778 (CFOP inválido)?",
        yes: { fix: "Para operação dentro do estado use 5102. Fora do estado, 6102. Ajuste no produto ou na nota. Mais em #a9." },
        no:  3 },
      { q: "É código 402 (origem da mercadoria)?",
        yes: { fix: "Cadastre origem 0 (nacional) ou 1 (estrangeira) no produto. Re-emita." },
        no:  { fix: "Erro fora da lista frequente. Abra o XML da rejeição (Fiscal → Logs → última transmissão) e poste o código no #financeiro pra Eliana analisar. Antes de re-emitir: SEMPRE inutilize o número rejeitado pra não furar a sequência." } },
    ],
  },
];

window.KB_TROUBLES = KB_TROUBLES;

// ─────────────────────────────────────────────────────────────────
// LINKIFY — transforma "#a3" e "#t1" em links clicáveis
// ─────────────────────────────────────────────────────────────────
function kbLinkifyText(text, onPickArticle) {
  if (!text || typeof text !== "string") return text;
  const parts = [];
  const re = /#(a\d+|t\d+|tr-[a-z]+)/g;
  let last = 0;
  let m;
  let i = 0;
  while ((m = re.exec(text)) !== null) {
    if (m.index > last) parts.push(text.slice(last, m.index));
    const id = m[1];
    parts.push(
      <button
        key={"lk" + i++}
        className="kb-link"
        onClick={(e) => { e.stopPropagation(); onPickArticle && onPickArticle(id); }}>
        #{id}
      </button>
    );
    last = m.index + m[0].length;
  }
  if (last < text.length) parts.push(text.slice(last));
  return parts;
}

window.kbLinkifyText = kbLinkifyText;

// ─────────────────────────────────────────────────────────────────
// TROUBLESHOOTER DIALOG (com biblioteca + seletor)
// ─────────────────────────────────────────────────────────────────
function KBTroubleshooterDialog({ onPickArticle, onClose, onCreateNew, customTroubles }) {
  const [activeId, setActiveId] = useState(null);
  const [step, setStep] = useState(0);
  const [fix, setFix] = useState(null);
  const [path, setPath] = useState([]); // array of {stepIdx, answer}

  const active = (window.KB_TROUBLES.concat(customTroubles || [])).find(t => t.id === activeId);

  const answer = (ans) => {
    if (!active) return;
    const s = active.steps[step];
    const next = ans ? s.yes : s.no;
    setPath(p => [...p, { stepIdx: step, q: s.q, answer: ans }]);
    if (typeof next === "number") setStep(next);
    else setFix(next.fix);
  };

  const restart = () => { setStep(0); setFix(null); setPath([]); };
  const backToLib = () => { setActiveId(null); setStep(0); setFix(null); setPath([]); };

  // Lista de troubleshooters
  if (!active) {
    return (
      <React.Fragment>
        <div className="kb-modal-back" onClick={onClose}/>
        <div className="kb-modal kb-trouble kb-trouble-lib" role="dialog">
          <header className="kb-modal-h">
            <div>
              <small>Diagnóstico guiado</small>
              <h3>Troubleshooter — escolha o problema</h3>
            </div>
            <button className="kb-x" onClick={onClose}>×</button>
          </header>
          <div className="kb-trouble-lib-body">
            {KB_TROUBLES.map(t => (
              <button key={t.id} className="kb-trouble-card" onClick={() => setActiveId(t.id)}
                      style={{ borderLeftColor: `oklch(0.55 0.13 ${t.hue})` }}>
                <small style={{ color: `oklch(0.42 0.13 ${t.hue})` }}>
                  {t.equip !== "—" ? t.equip : "fiscal"}
                </small>
                <h4>{t.title}</h4>
                <p>Use quando {t.when}.</p>
                <span className="kb-trouble-steps-n">{t.steps.length} perguntas</span>
              </button>
            ))}
            {(customTroubles || []).length > 0 && (
              <React.Fragment>
                <div style={{margin: "8px 0 4px", fontSize: 10, fontWeight: 700, color: "var(--text-mute)", textTransform: "uppercase", letterSpacing: "0.06em"}}>
                  Criados pela sua equipe
                </div>
                {(customTroubles || []).map(t => (
                  <button key={t.id} className="kb-trouble-card" onClick={() => setActiveId(t.id)}
                          style={{ borderLeftColor: `oklch(0.55 0.13 ${t.hue})` }}>
                    <small style={{ color: `oklch(0.42 0.13 ${t.hue})` }}>
                      {t.equip !== "—" ? t.equip : "personalizado"}
                    </small>
                    <h4>{t.title}</h4>
                    <p>Use quando {t.when}.</p>
                    <span className="kb-trouble-steps-n">{t.steps.length} perguntas</span>
                  </button>
                ))}
              </React.Fragment>
            )}
          </div>
          <footer className="kb-trouble-foot-lib" style={{display:"flex", alignItems:"center", justifyContent:"space-between", gap:8}}>
            <small>Não achou seu caso? <button className="kb-link-btn" onClick={onClose}>Perguntar à IA →</button></small>
            {onCreateNew && (
              <button className="os-btn primary" onClick={() => { onClose(); onCreateNew(); }} style={{fontSize:11.5}}>
                + Criar troubleshoot
              </button>
            )}
          </footer>
        </div>
      </React.Fragment>
    );
  }

  // Wizard
  const current = active.steps[step];
  return (
    <React.Fragment>
      <div className="kb-modal-back" onClick={onClose}/>
      <div className="kb-modal kb-trouble" role="dialog">
        <header className="kb-modal-h">
          <div>
            <small>
              <button className="kb-link-btn" onClick={backToLib}>‹ Troubleshooters</button>
              {" · "}{active.equip !== "—" ? active.equip : "fiscal"}
            </small>
            <h3>{active.title}</h3>
          </div>
          <button className="kb-x" onClick={onClose}>×</button>
        </header>

        <div className="kb-trouble-body">
          {/* histórico de respostas */}
          {path.length > 0 && (
            <div className="kb-trouble-history">
              {path.map((p, i) => (
                <div key={i} className="kb-trouble-history-row">
                  <span className="kb-trouble-history-q">{p.q}</span>
                  <span className={"kb-trouble-history-a " + (p.answer ? "yes" : "no")}>
                    {p.answer ? "Sim" : "Não"}
                  </span>
                </div>
              ))}
            </div>
          )}

          {!fix ? (
            <React.Fragment>
              <div className="kb-trouble-step">
                <span className="kb-trouble-n" style={{ background: `oklch(0.94 0.06 ${active.hue})`, color: `oklch(0.36 0.13 ${active.hue})` }}>
                  {path.length + 1}
                </span>
                <p>{current.q}</p>
              </div>
              <div className="kb-trouble-actions">
                <button className="kb-tb-yes" onClick={() => answer(true)}>Sim</button>
                <button className="kb-tb-no"  onClick={() => answer(false)}>Não</button>
              </div>
              <div className="kb-trouble-path">
                {active.steps.map((_, i) => (
                  <span key={i} className={"kb-trouble-dot" + (i <= step ? " on" : "")}
                        style={i <= step ? { background: `oklch(0.55 0.13 ${active.hue})` } : null}/>
                ))}
              </div>
            </React.Fragment>
          ) : (
            <React.Fragment>
              <div className="kb-trouble-fix" style={{ background: `oklch(0.97 0.025 ${active.hue})`, borderColor: `oklch(0.86 0.06 ${active.hue})` }}>
                <small style={{ color: `oklch(0.42 0.13 ${active.hue})` }}>Solução sugerida</small>
                <p>{kbLinkifyText(fix, onPickArticle)}</p>
              </div>
              <div className="kb-trouble-actions">
                <button className="os-btn ghost" onClick={restart}>Recomeçar</button>
                <button className="os-btn ghost" onClick={backToLib}>Outro problema</button>
                <button className="os-btn primary" onClick={onClose}>Resolvi, obrigado</button>
              </div>
            </React.Fragment>
          )}
        </div>
      </div>
    </React.Fragment>
  );
}

window.KBTroubleshooterDialog = KBTroubleshooterDialog;

// ─────────────────────────────────────────────────────────────────
// MODO APRESENTAÇÃO — cada h2 vira slide
// ─────────────────────────────────────────────────────────────────
// O artigo é dividido em slides: capa + um slide por seção h2.

function buildSlides(article) {
  const slides = [];
  // Capa
  slides.push({ kind: "cover", title: article.title, excerpt: article.excerpt, author: article.author, time: article.readTime });

  // Partilha em seções por h2
  let currentSection = { title: "Introdução", blocks: [] };
  article.body.forEach(b => {
    if (b.kind === "h2") {
      if (currentSection.blocks.length > 0) slides.push({ kind: "section", ...currentSection });
      currentSection = { title: b.t, blocks: [] };
    } else {
      currentSection.blocks.push(b);
    }
  });
  if (currentSection.blocks.length > 0) slides.push({ kind: "section", ...currentSection });

  return slides;
}

function KBPresenter({ article, onClose, onPickArticle }) {
  const slides = useMemo(() => buildSlides(article), [article]);
  const [idx, setIdx] = useState(0);
  const total = slides.length;

  useEffect(() => {
    const onKey = (e) => {
      if (e.key === "ArrowRight" || e.key === " " || e.key === "PageDown") {
        e.preventDefault();
        setIdx(i => Math.min(total - 1, i + 1));
      } else if (e.key === "ArrowLeft" || e.key === "PageUp") {
        e.preventDefault();
        setIdx(i => Math.max(0, i - 1));
      } else if (e.key === "Escape") {
        onClose();
      } else if (e.key === "Home") {
        setIdx(0);
      } else if (e.key === "End") {
        setIdx(total - 1);
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [total, onClose]);

  const s = slides[idx];

  return (
    <div className="kb-pres" role="dialog" aria-label="Modo apresentação">
      <div className="kb-pres-stage">
        {s.kind === "cover" && (
          <div className="kb-pres-cover">
            <small className="kb-pres-eyebrow">Apresentação · base de conhecimento</small>
            <h1>{s.title}</h1>
            <p className="kb-pres-excerpt">{s.excerpt}</p>
            <div className="kb-pres-cover-meta">
              <span>{s.author}</span>
              <span className="kb-sep">·</span>
              <span>{s.time} min de leitura</span>
            </div>
          </div>
        )}
        {s.kind === "section" && (
          <div className="kb-pres-section">
            <h2>{s.title}</h2>
            <div className="kb-pres-content">
              {s.blocks.map((b, i) => {
                if (b.kind === "para") return <p key={i}>{kbLinkifyText(b.t, onPickArticle)}</p>;
                if (b.kind === "list") return (
                  <ol key={i} className="kb-pres-list">
                    {b.items.map((it, j) => <li key={j}>{kbLinkifyText(it, onPickArticle)}</li>)}
                  </ol>
                );
                if (b.kind === "callout") return (
                  <div key={i} className={"kb-callout kb-callout--" + (b.tone || "info")}>
                    <span className="kb-callout-icon">{b.tone === "bad" ? "✕" : b.tone === "warn" ? "!" : b.tone === "ok" ? "✓" : "i"}</span>
                    <p>{kbLinkifyText(b.t, onPickArticle)}</p>
                  </div>
                );
                if (b.kind === "image" && window.KBImageBlockView) return <window.KBImageBlockView key={i} block={b}/>;
                return null;
              })}
            </div>
          </div>
        )}
      </div>

      <footer className="kb-pres-foot">
        <button className="kb-pres-nav" onClick={() => setIdx(i => Math.max(0, i - 1))} disabled={idx === 0}>‹ anterior</button>
        <div className="kb-pres-dots">
          {slides.map((_, i) => (
            <button key={i} className={"kb-pres-dot" + (i === idx ? " on" : "")} onClick={() => setIdx(i)}/>
          ))}
        </div>
        <span className="kb-pres-counter mono">{idx + 1}/{total}</span>
        <button className="kb-pres-nav" onClick={() => setIdx(i => Math.min(total - 1, i + 1))} disabled={idx === total - 1}>próximo ›</button>
        <button className="kb-pres-exit" onClick={onClose} title="Sair (Esc)">Sair</button>
      </footer>
    </div>
  );
}

window.KBPresenter = KBPresenter;

})();
