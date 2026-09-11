// forja-lista.jsx — Trabalho · Lista: grupos colapsáveis, rank híbrido (pin fura), seleção em
// massa, encerramento de onda e barra de totais. Tela da Onda 4 da paridade (Forja/Trabalho/Index).
// Extraído de forja-page.jsx (Onda 2 · 1 arquivo por tela); o estado segue sendo do shell (ctx).
function FjListaView({ ctx }) {
  const { ondaMsg, denso, groups, collapsed, toggleGroup, groupLabel, groupBy, encerrarOnda, setIaPanel, flat, sel, setSel, setOpenId, fav, toggleFav, selected, toggleSel, pin, togglePin, blocksCount, kidsOf, expanded, toggleExpand, filtered, bulkPhase, bulkAssign, bulkPrio, bulkOnda, bulkFav, setSelected, moveExec, totals } = ctx;
  return (
        <React.Fragment>
          {ondaMsg && <div className="fj-onda-msg">{ondaMsg}</div>}
          <div className={"fj-list" + (denso ? " compact" : "")}>
            {groups.map(([g, items]) => {
              const isC = !!collapsed[g];
              return (
                <div key={g} className={"fj-group" + (isC ? " collapsed" : "")}>
                  <div className="fj-group-head">
                    <button className="fj-group-toggle" onClick={() => toggleGroup(g)}>
                      <span className="fj-group-chev" style={{ transform: isC ? "rotate(-90deg)" : "none" }}><I.chev size={12}/></span>
                      <span className="fj-group-title">{groupLabel(g)}</span>
                      <span className="fj-group-count">{items.length}</span>
                    </button>
                    {groupBy === "onda" && g !== "Sem onda" && (() => {
                      const o = window.FORJA.ONDAS.find(x => x.id === g);
                      const carga = ["P", "M", "G", "GG"].map(t => { const n = items.filter(x => x.i.tam === t && !x.sub).length; return n ? n + t : null; }).filter(Boolean).join(" · ");
                      return (
                        <span className="fj-onda-meta">
                          {o && <span className={"fj-onda-estado " + o.estado}>{o.estado}</span>}
                          {o && <span className="fj-onda-janela">{o.janela}</span>}
                          {carga && <span className="fj-onda-carga" title="Carga da onda por tamanho (P/M/G/GG) — responde: cabe na janela?">carga {carga}</span>}
                          {o && o.estado === "ativa" && <button className="fj-onda-encerrar" onClick={() => encerrarOnda(g)} title="Encerra o ciclo: não-concluídos carregam pra próxima onda e o fechamento entra no changelog">encerrar onda</button>}
                          <button className="fj-group-ia" onClick={() => setIaPanel({ mode: "digest", onda: g })} title="Resumir onda"><span className="fj-ia-spark">✦</span>resumir</button>
                        </span>
                      );
                    })()}
                  </div>
                  {!isC && items.map(({ i: issue, sub }) => {
                    const idx = flat.indexOf(issue);
                    const kids = kidsOf[issue.id];
                    return <FjIssueRow key={(sub ? "s-" : "") + issue.id} issue={issue} active={idx === sel} fav={fav.has(issue.id)} onFav={toggleFav}
                      selected={selected.has(issue.id)} onSelect={toggleSel} pinned={pin.has(issue.id)} onPin={togglePin}
                      rankWhy={pin.has(issue.id) ? "fixado no topo por você" : fjWhyRank(issue, blocksCount[issue.id])}
                      kids={sub ? null : kids} expanded={expanded.has(issue.id)} onExpand={toggleExpand} sub={sub}
                      onClick={() => { setSel(idx); setOpenId(issue.id); }}/>;
                  })}
                </div>
              );
            })}
            {filtered.length === 0 && (
              <div className="fj-empty">
                <p>Nenhum issue casa com o filtro.</p>
                <button className="os-btn ghost" onClick={() => setIaPanel({ mode: "ask" })}><span className="fj-ia-spark">✦</span>Perguntar à memória sobre isso</button>
              </div>
            )}
          </div>
          {selected.size > 0 && (
            <div className="fj-bulkbar">
              <span className="fj-bulk-n"><b>{selected.size}</b> selecionados</span>
              <span className="fj-bulk-lbl">fase</span>
              {window.FORJA.PHASES.map(p => <button key={p.id} className="fj-bulk-fase" onClick={() => bulkPhase(p.id)}>{p.id}</button>)}
              <span className="fj-bulk-lbl">papel</span>
              {Object.keys(window.FORJA.ACTORS).map(r => <button key={r} className="fj-bulk-fase" onClick={() => bulkAssign(r)} title={window.FORJA.ACTORS[r].name}>[{r}]</button>)}
              <span className="fj-bulk-lbl">prio</span>
              {["P0", "P1", "P2", "P3"].map(p => <button key={p} className="fj-bulk-fase" onClick={() => bulkPrio(p)}>{p}</button>)}
              <span className="fj-bulk-lbl">onda</span>
              {window.FORJA.ONDAS.map(o => <button key={o.id} className="fj-bulk-fase" onClick={() => bulkOnda(o.id)} title={o.nome}>~{o.id}</button>)}
              <button className="fj-bulk-fase" onClick={() => bulkOnda(null)} title="Tirar da onda">—</button>
              <span className="fj-bulk-lbl">status</span>
              {["doing", "review", "done"].map(s => <button key={s} className="fj-bulk-fase" onClick={() => { selected.forEach(id => moveExec(id, s)); setSelected(new Set()); }}>{window.FORJA.STATUS.find(x => x.id === s).label}</button>)}
              <button className="fj-bulk-act" onClick={bulkFav}>★ favoritar</button>
              <button className="fj-bulk-act" onClick={() => setSelected(new Set())}>limpar</button>
            </div>
          )}
          <div className="fj-totalbar">
            <span><b>{totals.n}</b> issues</span>
            <span><b>{totals.p0}</b> P0</span>
            <span><b>{totals.blocked}</b> bloqueados</span>
            <span className="fj-total-warn"><b>{totals.inferido}</b> não-verificados</span>
            <span className="fj-total-rank" title="Score = prioridade × tempo parado × quantos issues destrava. Bloqueado desce. Fixado fura a fila.">ordem: automática{pin.size > 0 && <b> + {pin.size} fixado{pin.size > 1 ? "s" : ""}</b>}</span>
            <span className="fj-total-hint"><kbd>j</kbd><kbd>k</kbd> navega · <kbd>↵</kbd> abre · <kbd>?</kbd> atalhos</span>
          </div>
        </React.Fragment>
  );
}

window.FjListaView = FjListaView;
