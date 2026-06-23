// os-page.jsx — Listagem + Detalhe de Ordens de Serviço
const { useState: useStateO, useEffect: useEffectO, useMemo: useMemoO } = React;

function OsStageBadge({ stage }) {
  const s = OS_DATA.OS_STAGES.find(x => x.id === stage);
  if (!s) return <span className="os-stage muted">{stage}</span>;
  return <span className={`os-stage ${s.color}`}>{s.label}</span>;
}

function OsRow({ os, selected, onSelect, onOpen, checked, onCheck }) {
  const fsm = window.osFsmStage(os.stage);
  return (
    <tr className={`os-row ${selected?"selected":""} ${os.urgent?"urgent":""}`} onClick={() => onOpen(os)}>
      <td className="os-cell-check" onClick={e => e.stopPropagation()}>
        <input type="checkbox" checked={!!checked} onChange={e => onCheck(os.id, e.target.checked)}/>
      </td>
      <td className="os-cell-num">
        <span className="mono">#{os.id}</span>
        {os.urgent && <span className="os-urgent-dot" title="Urgente"/>}
      </td>
      <td className="os-cell-client">
        <b>{os.client}</b>
        <small>{os.contact}</small>
      </td>
      <td className="os-cell-product">{os.product}</td>
      <td className="os-cell-stage">
        <window.FsmStepper domain="os" current={fsm.current} terminal={fsm.terminal} variant="dots-inline"/>
      </td>
      <td className="os-cell-resp">
        <span className="os-resp-av av-1">{os.responsible.split(" ").map(p=>p[0]).slice(0,2).join("")}</span>
        <span className="os-resp-name">{os.responsible}</span>
      </td>
      <td className={`os-cell-deadline ${os.urgent?"urgent":""}`}>{os.deadline}</td>
      <td className="os-cell-value mono">{os.value}</td>
    </tr>
  );
}

function OsListPage({ onOpenDetail }) {
  const [all, setAll] = useStateO(() => OS_DATA.OS_LIST);
  const [stage, setStage] = useStateO(() => {
    try { return localStorage.getItem("oimpresso.os.filter") || "abertas"; } catch (e) { return "abertas"; }
  });
  const [responsibleFilter, setResponsibleFilter] = useStateO("all");
  const [query, setQuery] = useStateO("");
  const [selected, setSelected] = useStateO(new Set());
  const [openDetailId, setOpenDetailId] = useStateO(null);
  const [newOpen, setNewOpen] = useStateO(false);
  const [approveOs, setApproveOs] = useStateO(null);
  const [bulkAction, setBulkAction] = useStateO(null); // 'stage' | 'assign'

  useEffectO(() => { try { localStorage.setItem("oimpresso.os.filter", stage); } catch (e) {} }, [stage]);

  const responsibles = useMemoO(() => Array.from(new Set(all.map(o => o.responsible))), [all]);

  const filtered = useMemoO(() => {
    let out = all;
    if (stage === "abertas")     out = out.filter(o => !["entregue","cancelado"].includes(o.stage));
    else if (stage === "atrasadas") out = out.filter(o => o.urgent && !["entregue","cancelado"].includes(o.stage));
    else if (stage !== "all")    out = out.filter(o => o.stage === stage);
    if (responsibleFilter !== "all") out = out.filter(o => o.responsible === responsibleFilter);
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(o =>
        o.id.includes(q) ||
        o.client.toLowerCase().includes(q) ||
        o.product.toLowerCase().includes(q) ||
        o.contact.toLowerCase().includes(q));
    }
    return out;
  }, [all, stage, responsibleFilter, query]);

  const stats = useMemoO(() => OS_DATA.osStats(all), [all]);
  const totalValor = useMemoO(() => filtered.reduce((acc, o) => acc + parseFloat(o.value.replace(/[^\d,]/g,'').replace(',','.')), 0), [filtered]);

  const handleCheck = (id, on) => {
    setSelected(s => {
      const n = new Set(s);
      if (on) n.add(id); else n.delete(id);
      return n;
    });
  };
  const handleCheckAll = (on) => {
    setSelected(on ? new Set(filtered.map(o => o.id)) : new Set());
  };

  const stageFilters = [
    { id:"abertas",   label:"Abertas",    n: stats.abertas },
    { id:"atrasadas", label:"Atrasadas",  n: stats.atrasadas, warn: stats.atrasadas > 0 },
    { id:"all",       label:"Todas",      n: stats.total },
    { id:"orcado",    label:"Orçado" },
    { id:"aprovacao", label:"Aprovação" },
    { id:"producao",  label:"Produção" },
    { id:"acabamento",label:"Acabamento" },
    { id:"expedicao", label:"Expedição" },
    { id:"entregue",  label:"Entregues" },
  ];

  const openOs = all.find(o => o.id === openDetailId);

  return (
    <div className="os-page">
      <header className="cli-pageheader">
        <div className="cli-pageheader-l">
          <div className="cli-pageheader-icon"><I.folder size={20}/></div>
          <div className="cli-pageheader-title-wrap">
            <h1>Ordens de Serviço</h1>
            <p>
              <strong>{stats.abertas}</strong> abertas
              {stats.atrasadas > 0 && <> · <strong data-tone="danger">{stats.atrasadas}</strong> atrasadas</>}
              {" · "}<strong>R$ {stats.valorAberto.toLocaleString('pt-BR',{minimumFractionDigits:2})}</strong> em aberto
            </p>
          </div>
        </div>
        <div className="cli-pageheader-r">
          <button className="os-btn ghost"><I.search size={13}/> Filtros</button>
          <button className="os-btn primary" onClick={() => setNewOpen(true)}><I.plus size={13}/> Nova OS</button>
        </div>
      </header>

      <nav className="cli-moduletopnav" aria-label="Etapa">
        {stageFilters.map(f => (
          <button key={f.id}
            className={`cli-moduletopnav-tab ${stage===f.id?"active":""} ${f.warn?"warn":""}`}
            onClick={() => setStage(f.id)}
            aria-current={stage===f.id ? "page" : undefined}>
            <span>{f.label}</span>
            {f.n != null && <span className="cli-moduletopnav-n">{f.n}</span>}
          </button>
        ))}
      </nav>

      <div className="os-toolbar">
        <div className="os-toolbar-l">
          <div className="os-search">
            <I.search size={12}/>
            <input placeholder="Buscar por cliente, produto, OS..." value={query} onChange={e => setQuery(e.target.value)}/>
          </div>
          <select className="os-select" value={responsibleFilter} onChange={e => setResponsibleFilter(e.target.value)}>
            <option value="all">Todos os responsáveis</option>
            {responsibles.map(r => <option key={r} value={r}>{r}</option>)}
          </select>
        </div>
        <div className="os-toolbar-r">
          <span className="os-toolbar-total mono">{filtered.length} OS · R$ {totalValor.toLocaleString('pt-BR',{minimumFractionDigits:2})}</span>
        </div>
      </div>

      {selected.size > 0 && (
        <div className="os-bulk">
          <b>{selected.size} selecionada{selected.size>1?"s":""}</b>
          <span className="os-bulk-spacer"/>
          <button className="os-btn ghost sm" onClick={() => setBulkAction('stage')}>Mudar etapa</button>
          <button className="os-btn ghost sm" onClick={() => setBulkAction('assign')}>Atribuir responsável</button>
          <button className="os-btn ghost sm" onClick={() => {
            // Exportar CSV das selecionadas
            const rows = all.filter(o => selected.has(o.id));
            const header = ["OS","Cliente","Produto","Etapa","Responsável","Prazo","Valor"];
            const csv = [header, ...rows.map(o => [o.id, o.client, o.product, o.stage, o.responsible, o.deadline, o.value])]
              .map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(",")).join("\n");
            const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url; a.download = `os-export-${new Date().toISOString().slice(0,10)}.csv`;
            a.click(); URL.revokeObjectURL(url);
          }}>Exportar CSV</button>
          <button className="os-btn ghost sm danger" onClick={() => setSelected(new Set())}>Limpar</button>
        </div>
      )}

      <div className="os-table-wrap">
        <table className="os-table">
          <thead>
            <tr>
              <th className="os-cell-check">
                <input type="checkbox"
                  checked={selected.size > 0 && selected.size === filtered.length}
                  onChange={e => handleCheckAll(e.target.checked)}/>
              </th>
              <th className="os-th-num">OS</th>
              <th>Cliente</th>
              <th>Produto</th>
              <th className="os-th-pipeline">Pipeline</th>
              <th>Responsável</th>
              <th>Prazo</th>
              <th className="os-th-val">Valor</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(os => (
              <OsRow key={os.id} os={os}
                selected={openDetailId===os.id}
                checked={selected.has(os.id)}
                onCheck={handleCheck}
                onOpen={() => setOpenDetailId(os.id)}/>
            ))}
            {filtered.length === 0 && (
              <tr><td colSpan={8} className="os-empty-row">
                <div className="os-empty-state">
                  {(() => {
                    // Empty state contextual
                    if (stage === "atrasadas") return (
                      <>
                        <div className="tk-empty-ico ok"><I.check size={20}/></div>
                        <b>Tudo no prazo!</b>
                        <small>Nenhuma OS atrasada hoje. Boa gestão.</small>
                      </>
                    );
                    if (stage === "arte") return (
                      <>
                        <div className="tk-empty-ico ok"><I.check size={20}/></div>
                        <b>Nenhuma arte pendente.</b>
                        <small>Todas as aprovações de arte estão em dia.</small>
                      </>
                    );
                    if (stage === "producao") return (
                      <>
                        <div className="tk-empty-ico"><I.folder size={20}/></div>
                        <b>Produção parada.</b>
                        <small>Nenhuma OS em produção neste momento.</small>
                      </>
                    );
                    if (query || responsibleFilter !== "all") return (
                      <>
                        <div className="tk-empty-ico"><I.search size={20}/></div>
                        <b>Nenhuma OS bate com esses filtros.</b>
                        <small>Ajuste a busca ou troque a etapa.</small>
                      </>
                    );
                    return (
                      <>
                        <div className="tk-empty-ico"><I.folder size={20}/></div>
                        <b>Nenhuma OS aqui ainda.</b>
                        <small>Crie a primeira clicando em "Nova OS".</small>
                      </>
                    );
                  })()}
                </div>
              </td></tr>
            )}
          </tbody>
          {filtered.length > 0 && (
            <tfoot>
              <tr>
                <td colSpan={6}/>
                <td className="os-foot-l">Total · {filtered.length} OS</td>
                <td className="mono os-foot-v">R$ {totalValor.toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
              </tr>
            </tfoot>
          )}
        </table>
      </div>

      {openOs && <OsDetailDrawer os={openOs} onClose={() => setOpenDetailId(null)} onEdit={() => { setNewOpen('edit'); }} onApprove={() => setApproveOs(openOs)}/>}
      {newOpen && <OsNewDrawer
        onClose={() => setNewOpen(false)}
        os={newOpen === 'edit' ? openOs : null}
        onSave={(data) => {
          if (newOpen === 'edit') return; // edit não cria nova
          const nextNum = Math.max(...all.map(o => +o.id)) + 1;
          const newOs = { id: String(nextNum), ...data };
          setAll([newOs, ...all]);
        }}/>}
      {approveOs && <OsApproveArtModal os={approveOs} onClose={() => setApproveOs(null)}/>}
      {bulkAction && <OsBulkModal action={bulkAction} count={selected.size}
        selectedIds={Array.from(selected)}
        onClose={() => setBulkAction(null)}
        onDone={() => { setBulkAction(null); setSelected(new Set()); }}/>}
    </div>
  );
}

// ─── Detalhe (drawer lateral direito) ───
function OsDetailDrawer({ os, onClose, onEdit, onApprove }) {
  const timeline = OS_DATA.OS_TIMELINE[os.id] || [
    { who:"sistema", role:"—", when:os.created, what:"OS criada", kind:"create" },
    { who:os.responsible, role:"Resp.", when:os.updated, what:"Última atualização", kind:"art" },
  ];

  return (
    <>
      <div className="os-drawer-back" onClick={onClose}/>
      <aside className="os-drawer">
        <div className="os-drawer-h">
          <div className="os-drawer-h-l">
            <span className="mono os-drawer-num">#{os.id}</span>
            <OsStageBadge stage={os.stage}/>
            {os.urgent && <span className="os-urgent-tag">Urgente</span>}
          </div>
          <button className="icon-btn" onClick={onClose} title="Fechar"><I.close size={14}/></button>
        </div>
        <div className="os-drawer-title">
          <h2>{os.product}</h2>
          <p><b>{os.client}</b> · {os.contact}</p>
        </div>

        <div className="os-drawer-meta">
          <div><small>Quantidade</small><b>{os.qty.toLocaleString('pt-BR')}</b></div>
          <div><small>Valor</small><b className="mono">{os.value}</b></div>
          <div><small>Prazo</small><b className={os.urgent?"urgent":""}>{os.deadline}</b></div>
          <div><small>Equipe</small><b>{os.team}</b></div>
        </div>

        <div className="os-drawer-section">
          <h3>Responsável</h3>
          <div className="os-drawer-resp">
            <span className="os-resp-av lg av-1">{os.responsible.split(" ").map(p=>p[0]).slice(0,2).join("")}</span>
            <div>
              <b>{os.responsible}</b>
              <small>{os.team}</small>
            </div>
            <button className="os-btn ghost sm">Reatribuir</button>
          </div>
        </div>

        <div className="os-drawer-section">
          <h3>Pipeline</h3>
          {(() => {
            const fsm = window.osFsmStage(os.stage);
            return <window.FsmStepper domain="os" current={fsm.current} terminal={fsm.terminal} variant="full-stepper"/>;
          })()}
        </div>

        <div className="os-drawer-section">
          <h3>Histórico</h3>
          <ol className="os-timeline">
            {timeline.map((t, i) => (
              <li key={i} className={`os-tl-item ${t.kind}`}>
                <span className="os-tl-dot"/>
                <div className="os-tl-content">
                  <div className="os-tl-h">
                    <b>{t.who}</b> <small>· {t.role}</small>
                    <span className="os-tl-when">{t.when}</span>
                  </div>
                  <p>{t.what}</p>
                  {t.file && (
                    <a className="os-tl-file"><I.folder size={11}/> {t.file}</a>
                  )}
                </div>
              </li>
            ))}
          </ol>
        </div>

        <div className="os-drawer-actions">
          {os.stage === "aprovacao" ? (
            <button className="os-btn primary" onClick={onApprove}><I.check size={13}/> Aprovar arte</button>
          ) : (
            <button className="os-btn primary"><I.check size={13}/> Avançar etapa</button>
          )}
          <button className="os-btn ghost">Abrir conversa</button>
          <button className="os-btn ghost" onClick={onEdit}>Editar</button>
          <button className="os-btn ghost" onClick={() => window.print()} title="Imprimir / Salvar PDF">
            <I.folder size={13}/> Imprimir
          </button>
          <span className="os-bulk-spacer"/>
          <button className="os-btn ghost danger">Cancelar OS</button>
        </div>
      </aside>
    </>
  );
}

window.OsListPage = OsListPage;

// ─── Nova OS / Editar OS (drawer com 4 seções) ───
// Quando recebe `os`, entra em modo edição (preenche campos, troca títulos e ação).
function OsNewDrawer({ onClose, onSave, os: editingOs = null }) {
  const { OS_CLIENTS, OS_PRODUCTS, OS_RESPONSIBLES } = OS_DATA;
  const isEdit = !!editingOs;

  // Pré-resolve dados quando estamos editando uma OS existente
  const initialClient  = isEdit ? OS_CLIENTS.find(c => c.name === editingOs.client) || null : null;
  const initialResp    = isEdit ? OS_RESPONSIBLES.find(r => r.name === editingOs.responsible) || null : null;
  // Tenta inferir um produto do catálogo a partir do nome textual da OS
  const initialItems = isEdit ? (() => {
    const guess = OS_PRODUCTS.find(p => editingOs.product.toLowerCase().includes(p.name.toLowerCase().split(' ')[0]))
               || OS_PRODUCTS[0];
    const valor = parseFloat(editingOs.value.replace(/[^\d,]/g,'').replace(',','.')) || 0;
    const qty = editingOs.qty || 1;
    return [{ productId: guess.id, qty, unitPrice: qty > 0 ? valor / qty : guess.price, note: "" }];
  })() : [];
  // converte "22/04" → "2024-04-22" (heurística para o mock)
  const initialDeadline = isEdit && editingOs.deadline ? (() => {
    const m = editingOs.deadline.match(/(\d{2})\/(\d{2})/);
    if (!m) return "";
    const yr = new Date().getFullYear();
    return `${yr}-${m[2]}-${m[1]}`;
  })() : "";

  const [section, setSection] = useStateO("cliente"); // cliente | produto | prazo | resp
  const [done, setDone]       = useStateO({
    cliente: !!initialClient, produto: initialItems.length > 0, prazo: !!initialDeadline, resp: !!initialResp
  });

  // Cliente
  const [clientQuery, setClientQuery] = useStateO("");
  const [client, setClient]           = useStateO(initialClient);
  const [contact, setContact]         = useStateO(initialClient?.contact || (isEdit ? editingOs.contact : ""));
  const [phone, setPhone]             = useStateO(initialClient?.phone || "");
  const clientMatches = useMemoO(() => {
    if (!clientQuery.trim()) return [];
    const q = clientQuery.toLowerCase();
    return OS_CLIENTS.filter(c =>
      c.name.toLowerCase().includes(q) || c.doc.includes(q) || c.contact.toLowerCase().includes(q)
    ).slice(0, 6);
  }, [clientQuery, OS_CLIENTS]);

  // Produto (lista de itens)
  const [items, setItems]   = useStateO(initialItems); // {productId, qty, unitPrice, note}
  const [prodQuery, setProdQuery] = useStateO("");
  const [prodCat, setProdCat]     = useStateO("all");
  const cats = useMemoO(() => ["all", ...Array.from(new Set(OS_PRODUCTS.map(p => p.cat)))], [OS_PRODUCTS]);
  const prodMatches = useMemoO(() => {
    let out = OS_PRODUCTS;
    if (prodCat !== "all") out = out.filter(p => p.cat === prodCat);
    if (prodQuery.trim()) {
      const q = prodQuery.toLowerCase();
      out = out.filter(p => p.name.toLowerCase().includes(q) || p.cat.toLowerCase().includes(q));
    }
    return out.slice(0, 8);
  }, [prodQuery, prodCat, OS_PRODUCTS]);
  const total = useMemoO(() => items.reduce((acc, it) => acc + (it.qty * it.unitPrice), 0), [items]);

  // Prazo
  const [deadline, setDeadline] = useStateO(initialDeadline);
  const [urgent, setUrgent]     = useStateO(isEdit ? !!editingOs.urgent : false);
  const [obs, setObs]           = useStateO("");

  // Responsáveis
  const [resp, setResp]   = useStateO(initialResp);
  const [team, setTeam]   = useStateO(initialResp?.team || null);
  const [files, setFiles] = useStateO([]); // {name, kind}

  // Marca seções como completas conforme usuário preenche
  React.useEffect(() => { setDone(d => ({ ...d, cliente: !!client })); }, [client]);
  React.useEffect(() => { setDone(d => ({ ...d, produto: items.length > 0 })); }, [items.length]);
  React.useEffect(() => { setDone(d => ({ ...d, prazo: !!deadline })); }, [deadline]);
  React.useEffect(() => { setDone(d => ({ ...d, resp: !!resp })); }, [resp]);

  const canSubmit = client && items.length > 0 && deadline && resp;

  const addProduct = (p) => {
    setItems(arr => [...arr, { productId: p.id, qty: 1, unitPrice: p.price, note: "" }]);
    setProdQuery("");
  };
  const updateItem = (i, patch) => setItems(arr => arr.map((it, idx) => idx===i ? { ...it, ...patch } : it));
  const removeItem = (i) => setItems(arr => arr.filter((_, idx) => idx!==i));

  const addFile = (kind) => {
    const stubs = {
      arte:    { name:`arte-${Math.random().toString(36).slice(2,6)}.pdf`, kind:"arte" },
      foto:    { name:`foto-referencia-${Math.random().toString(36).slice(2,6)}.jpg`, kind:"foto" },
      briefing:{ name:`briefing-${Math.random().toString(36).slice(2,6)}.docx`, kind:"briefing" },
    };
    setFiles(f => [...f, stubs[kind]]);
  };

  return (
    <>
      <div className="os-drawer-back" onClick={onClose}/>
      <aside className="os-drawer wide">
        <div className="os-drawer-h">
          <div className="os-drawer-h-l">
            <span className="mono os-drawer-num os-new-num">{isEdit ? `Editar #${editingOs.id}` : "Nova OS"}</span>
            <span className="os-stage muted">{isEdit ? "Edição" : "Rascunho"}</span>
          </div>
          <button className="icon-btn" onClick={onClose} title="Fechar"><I.close size={14}/></button>
        </div>

        {/* Stepper / nav lateral */}
        <nav className="os-new-stepper">
          {[
            { id:"cliente", label:"Cliente",      hint: client ? client.name : "Selecione" },
            { id:"produto", label:"Produto",      hint: items.length ? `${items.length} item(s) · R$ ${total.toLocaleString('pt-BR',{minimumFractionDigits:2})}` : "Adicione itens" },
            { id:"prazo",   label:"Prazo & obs.", hint: deadline || "Defina entrega" },
            { id:"resp",    label:"Resp. & anexos", hint: resp ? resp.name : "Atribuir equipe" },
          ].map(s => (
            <button key={s.id}
              className={`os-step ${section===s.id?"active":""} ${done[s.id]?"done":""}`}
              onClick={() => setSection(s.id)}>
              <span className="os-step-bullet">{done[s.id] ? <I.check size={11}/> : null}</span>
              <span className="os-step-text">
                <b>{s.label}</b>
                <small>{s.hint}</small>
              </span>
            </button>
          ))}
        </nav>

        <div className="os-new-body">
          {/* CLIENTE */}
          {section==="cliente" && (
            <section className="os-new-sec">
              <h3>Cliente</h3>
              <p className="os-new-help">Buscar por nome, CNPJ ou contato. Ou cadastrar novo cliente.</p>

              {!client && (
                <>
                  <div className="os-new-search">
                    <I.search size={13}/>
                    <input autoFocus placeholder="Ex.: Acme, 12.345, Camila..."
                      value={clientQuery} onChange={e => setClientQuery(e.target.value)}/>
                  </div>
                  {clientQuery && (
                    <ul className="os-new-results">
                      {clientMatches.map(c => (
                        <li key={c.id} onClick={() => { setClient(c); setContact(c.contact); setPhone(c.phone); }}>
                          <div>
                            <b>{c.name}</b>
                            <small>{c.doc} · {c.contact}</small>
                          </div>
                          <span className="os-new-last">Última OS {c.lastOs}</span>
                        </li>
                      ))}
                      {clientMatches.length===0 && (
                        <li className="empty">Nenhum cliente encontrado · <a>+ Cadastrar "{clientQuery}"</a></li>
                      )}
                    </ul>
                  )}
                </>
              )}

              {client && (
                <div className="os-new-client-card">
                  <div className="os-new-client-h">
                    <div>
                      <b>{client.name}</b>
                      <small>{client.doc}</small>
                    </div>
                    <button className="os-btn ghost sm" onClick={() => { setClient(null); setClientQuery(""); }}>Trocar</button>
                  </div>
                  <div className="os-new-row2">
                    <label>
                      <small>Contato</small>
                      <input value={contact} onChange={e => setContact(e.target.value)}/>
                    </label>
                    <label>
                      <small>Telefone / WhatsApp</small>
                      <input value={phone} onChange={e => setPhone(e.target.value)} className="mono"/>
                    </label>
                  </div>
                </div>
              )}
            </section>
          )}

          {/* PRODUTO */}
          {section==="produto" && (
            <section className="os-new-sec">
              <h3>Produto / itens</h3>
              <p className="os-new-help">Adicione um ou mais itens do catálogo. Quantidade e valor podem ser ajustados.</p>

              <div className="os-new-prod-pickers">
                <div className="os-new-search">
                  <I.search size={13}/>
                  <input placeholder="Buscar produto..." value={prodQuery} onChange={e => setProdQuery(e.target.value)}/>
                </div>
                <div className="os-new-cats">
                  {cats.map(c => (
                    <button key={c}
                      className={`os-cat ${prodCat===c?"active":""}`}
                      onClick={() => setProdCat(c)}>
                      {c==="all" ? "Todas" : c}
                    </button>
                  ))}
                </div>
              </div>

              {(prodQuery || prodCat!=="all") && (
                <ul className="os-new-results">
                  {prodMatches.map(p => (
                    <li key={p.id} onClick={() => addProduct(p)}>
                      <div>
                        <b>{p.name}</b>
                        <small>{p.cat} · {p.desc}</small>
                      </div>
                      <span className="os-new-price mono">R$ {p.price.toLocaleString('pt-BR',{minimumFractionDigits:2})}/{p.unit}</span>
                    </li>
                  ))}
                </ul>
              )}

              {items.length > 0 ? (
                <div className="os-new-items">
                  <table className="os-new-itable">
                    <thead>
                      <tr>
                        <th>Item</th>
                        <th className="r">Qtd</th>
                        <th className="r">Unit.</th>
                        <th className="r">Subtotal</th>
                        <th/>
                      </tr>
                    </thead>
                    <tbody>
                      {items.map((it, i) => {
                        const p = OS_PRODUCTS.find(x => x.id === it.productId);
                        return (
                          <tr key={i}>
                            <td>
                              <b>{p.name}</b>
                              <input className="os-new-itemnote"
                                placeholder="Observação do item (cor, dobra, acabamento...)"
                                value={it.note} onChange={e => updateItem(i, { note: e.target.value })}/>
                            </td>
                            <td className="r">
                              <input type="number" min="1" className="mono os-new-qty"
                                value={it.qty} onChange={e => updateItem(i, { qty: parseFloat(e.target.value)||0 })}/>
                              <small className="os-new-unit">{p.unit}</small>
                            </td>
                            <td className="r mono">
                              <input type="number" step="0.01" className="mono os-new-price-i"
                                value={it.unitPrice} onChange={e => updateItem(i, { unitPrice: parseFloat(e.target.value)||0 })}/>
                            </td>
                            <td className="r mono">R$ {(it.qty*it.unitPrice).toLocaleString('pt-BR',{minimumFractionDigits:2})}</td>
                            <td>
                              <button className="icon-btn sm" onClick={() => removeItem(i)} title="Remover"><I.close size={11}/></button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colSpan={3} className="r">Total</td>
                        <td className="r mono"><b>R$ {total.toLocaleString('pt-BR',{minimumFractionDigits:2})}</b></td>
                        <td/>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              ) : (
                !prodQuery && prodCat==="all" && (
                  <div className="os-new-empty">
                    <I.folder size={18}/>
                    <b>Nenhum item adicionado</b>
                    <small>Comece digitando ou escolhendo uma categoria.</small>
                  </div>
                )
              )}
            </section>
          )}

          {/* PRAZO */}
          {section==="prazo" && (
            <section className="os-new-sec">
              <h3>Prazo & observações</h3>
              <p className="os-new-help">Data de entrega prometida ao cliente. Marque urgente se passar na frente.</p>

              <div className="os-new-row2">
                <label>
                  <small>Data de entrega</small>
                  <input type="date" value={deadline} onChange={e => setDeadline(e.target.value)} className="mono"/>
                </label>
                <label className="os-new-toggle">
                  <input type="checkbox" checked={urgent} onChange={e => setUrgent(e.target.checked)}/>
                  <span>Marcar como <b>Urgente</b></span>
                </label>
              </div>

              <label className="os-new-textarea">
                <small>Observações internas</small>
                <textarea rows="5"
                  placeholder="Cliente vai retirar na sexta. Cuidado com o sangramento do logo. Etc."
                  value={obs} onChange={e => setObs(e.target.value)}/>
              </label>

              <div className="os-new-shortcuts">
                <small>Sugestões:</small>
                <button className="os-chip" onClick={() => setDeadline(new Date(Date.now()+2*864e5).toISOString().slice(0,10))}>+ 2 dias</button>
                <button className="os-chip" onClick={() => setDeadline(new Date(Date.now()+5*864e5).toISOString().slice(0,10))}>+ 5 dias</button>
                <button className="os-chip" onClick={() => setDeadline(new Date(Date.now()+10*864e5).toISOString().slice(0,10))}>+ 10 dias</button>
              </div>
            </section>
          )}

          {/* RESP & ANEXOS */}
          {section==="resp" && (
            <section className="os-new-sec">
              <h3>Responsável & equipe</h3>
              <p className="os-new-help">Quem assume a OS agora — geralmente Comercial ou Arte na criação.</p>

              <div className="os-new-resps">
                {OS_RESPONSIBLES.map(r => (
                  <button key={r.id}
                    className={`os-new-resp ${resp?.id===r.id?"active":""}`}
                    onClick={() => { setResp(r); setTeam(r.team); }}>
                    <span className={`os-resp-av lg ${r.grad}`}>{r.initials}</span>
                    <div>
                      <b>{r.name}</b>
                      <small>{r.team}</small>
                    </div>
                  </button>
                ))}
              </div>

              <h3 style={{marginTop:24}}>Anexos</h3>
              <p className="os-new-help">Arte final, fotos de referência ou briefing assinado.</p>

              <div className="os-new-attach">
                <button className="os-btn ghost" onClick={() => addFile("arte")}><I.plus size={12}/> Arte</button>
                <button className="os-btn ghost" onClick={() => addFile("foto")}><I.plus size={12}/> Foto referência</button>
                <button className="os-btn ghost" onClick={() => addFile("briefing")}><I.plus size={12}/> Briefing</button>
              </div>

              {files.length > 0 && (
                <ul className="os-new-files">
                  {files.map((f, i) => (
                    <li key={i}>
                      <I.folder size={12}/>
                      <span>{f.name}</span>
                      <small className="os-new-fkind">{f.kind}</small>
                      <button className="icon-btn sm" onClick={() => setFiles(arr => arr.filter((_,idx) => idx!==i))}><I.close size={11}/></button>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}
        </div>

        {/* Footer */}
        <div className="os-drawer-actions os-new-foot">
          <div className="os-new-summary">
            {client && <span>{client.name}</span>}
            {items.length > 0 && <span>· {items.length} item(s)</span>}
            {deadline && <span>· entrega {deadline}</span>}
            {resp && <span>· {resp.name}</span>}
            {total > 0 && <b className="mono os-new-total">R$ {total.toLocaleString('pt-BR',{minimumFractionDigits:2})}</b>}
          </div>
          <span className="os-bulk-spacer"/>
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <button className="os-btn ghost" onClick={onClose}>{isEdit ? "Descartar alterações" : "Cancelar"}</button>
          <button className={`os-btn primary ${!canSubmit?"disabled":""}`} disabled={!canSubmit}
            onClick={() => {
              if (!canSubmit) return;
              if (onSave) {
                const cli = OS_CLIENTS.find(c => c.id === client);
                const items = products;
                const total = items.reduce((s,p) => s + p.qty * p.price, 0);
                onSave({
                  client: cli?.name || "",
                  product: items.map(i => i.name).join(" + ") || "—",
                  qty: items.reduce((s,p)=>s+p.qty,0),
                  responsible: OS_RESPONSIBLES.find(r => r.id === resp)?.name || "",
                  deadline: deadline || "—",
                  value: "R$ " + total.toLocaleString('pt-BR', { minimumFractionDigits: 2 }),
                  stage: "orcamento",
                  urgency: "normal",
                });
              }
              onClose();
            }}>
            <I.check size={13}/> {isEdit ? "Salvar alterações" : "Criar OS"}
          </button>
        </div>
      </aside>
    </>
  );
}

window.OsNewDrawer = OsNewDrawer;

// ─── Aprovar Arte (modal com comparação de versões) ───
function OsApproveArtModal({ os, onClose }) {
  // Mocka 3 versões com observações; em produção viria do AnexoVersaoController
  const versions = useMemoO(() => ([
    { v:"v1", who:"Joana Lima", when:"22/04 10:18", note:"Primeira proposta — fontes e cores conforme briefing.", thumb:"art-1" },
    { v:"v2", who:"Joana Lima", when:"23/04 14:02", note:"Logo aumentado +6% e contraste no slogan.", thumb:"art-2" },
    { v:"v3", who:"Joana Lima", when:"hoje 09:14", note:"Sangramento ajustado (3mm). Ready para impressão.", thumb:"art-3", current:true },
  ]), []);

  const [selected, setSelected]   = useStateO("v3");
  const [compare, setCompare]     = useStateO("v2");
  const [mode, setMode]           = useStateO("solo"); // solo | compare
  const [decision, setDecision]   = useStateO(null);   // approve | adjust | reject
  const [comment, setComment]     = useStateO("");

  const cur = versions.find(x => x.v === selected);
  const cmp = versions.find(x => x.v === compare);

  // Keyboard shortcuts: A=aprovar · J=ajuste · R=rejeitar · ←/→ navega versões · Esc fecha
  useEffectO(() => {
    function onKey(e) {
      if (e.target.tagName === "TEXTAREA" || e.target.tagName === "INPUT") return;
      if (e.key === "Escape") { onClose(); return; }
      if (e.key === "a" || e.key === "A") setDecision("approve");
      if (e.key === "j" || e.key === "J") setDecision("adjust");
      if (e.key === "r" || e.key === "R") setDecision("reject");
      if (e.key === "ArrowLeft" || e.key === "ArrowRight") {
        const idx = versions.findIndex(x => x.v === selected);
        const nx = e.key === "ArrowLeft"
          ? Math.max(0, idx - 1)
          : Math.min(versions.length - 1, idx + 1);
        setSelected(versions[nx].v);
      }
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [selected, versions, onClose]);

  const Thumb = ({ v, label }) => (
    <div className={`os-art-thumb art-${v}`}>
      <div className="os-art-frame">
        <div className="os-art-corner tl"/>
        <div className="os-art-corner tr"/>
        <div className="os-art-corner bl"/>
        <div className="os-art-corner br"/>
        <div className="os-art-content">
          {/* placeholder gráfico — em produção o anexo PDF/PNG */}
          <div className="os-art-blob a"/>
          <div className="os-art-blob b"/>
          <div className="os-art-blob c"/>
          <div className="os-art-text">
            <b>{os?.client || "Cliente"}</b>
            <span>{os?.product || "Banner"}</span>
          </div>
        </div>
      </div>
      <small>{label}</small>
    </div>
  );

  return (
    <>
      <div className="os-modal-back" onClick={onClose}/>
      <div className="os-modal os-approve">
        <div className="os-modal-h">
          <div>
            <h2>Aprovar arte</h2>
            <p>OS <span className="mono">#{os?.id || "—"}</span> · {os?.client} · {os?.product}</p>
          </div>
          <button className="icon-btn" onClick={onClose}><I.close size={14}/></button>
        </div>

        <div className="os-approve-body">
          <aside className="os-approve-versions">
            <div className="os-approve-modes">
              <button className={`os-cat ${mode==="solo"?"active":""}`} onClick={() => setMode("solo")}>Versão única</button>
              <button className={`os-cat ${mode==="compare"?"active":""}`} onClick={() => setMode("compare")}>Comparar</button>
            </div>
            <ul className="os-version-list">
              {versions.map(v => (
                <li key={v.v}
                  className={`${selected===v.v?"selected":""} ${mode==="compare" && compare===v.v?"compare":""}`}
                  onClick={() => mode==="compare" && selected!==v.v ? setCompare(v.v) : setSelected(v.v)}>
                  <div className="os-version-h">
                    <b>{v.v}</b>
                    {v.current && <span className="os-version-tag">atual</span>}
                  </div>
                  <small>{v.who} · {v.when}</small>
                  <p>{v.note}</p>
                </li>
              ))}
            </ul>
          </aside>

          <main className={`os-approve-canvas ${mode}`}>
            {mode === "solo" ? (
              <Thumb v={cur.v} label={`${cur.v} — ${cur.when}`}/>
            ) : (
              <>
                <Thumb v={cmp.v} label={`${cmp.v} (anterior)`}/>
                <div className="os-approve-vs">
                  <span>vs</span>
                </div>
                <Thumb v={cur.v} label={`${cur.v} (selecionada)`}/>
              </>
            )}
          </main>
        </div>

        <div className="os-approve-decision">
          <div className="os-decision-options">
            <button className={`os-decision approve ${decision==="approve"?"active":""}`} onClick={() => setDecision("approve")}>
              <I.check size={14}/>
              <div><b>Aprovar <kbd className="os-kbd">A</kbd></b><small>Liberar para produção</small></div>
            </button>
            <button className={`os-decision adjust ${decision==="adjust"?"active":""}`} onClick={() => setDecision("adjust")}>
              <I.pencil size={14}/>
              <div><b>Pedir ajuste <kbd className="os-kbd">J</kbd></b><small>Volta pra arte com observação</small></div>
            </button>
            <button className={`os-decision reject ${decision==="reject"?"active":""}`} onClick={() => setDecision("reject")}>
              <I.close size={14}/>
              <div><b>Rejeitar <kbd className="os-kbd">R</kbd></b><small>Cancela arte e abre nova rodada</small></div>
            </button>
          </div>

          {decision && decision !== "approve" && (
            <textarea className="os-decision-comment"
              placeholder={decision === "adjust" ? "Descreva o ajuste pedido (ex.: aumentar logo +10%, mudar fundo pra azul)..." : "Motivo da rejeição..."}
              value={comment} onChange={e => setComment(e.target.value)}/>
          )}
          {decision === "approve" && (
            <div className="os-decision-confirm">
              <I.check size={13}/>
              <span>Ao aprovar, a OS avança para <b>Produção</b> e a equipe é notificada.</span>
            </div>
          )}
        </div>

        <div className="os-modal-foot">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <span className="os-bulk-spacer"/>
          <button className={`os-btn primary ${!decision?"disabled":""}`} disabled={!decision}>
            <I.check size={13}/>
            {decision === "approve" ? "Aprovar e avançar" :
             decision === "adjust"  ? "Enviar ajuste" :
             decision === "reject"  ? "Rejeitar arte" : "Decidir"}
          </button>
        </div>
      </div>
    </>
  );
}

window.OsApproveArtModal = OsApproveArtModal;

// ─── Bulk: Mudar etapa / Atribuir responsável (modal compacto) ───
function OsBulkModal({ action, count, selectedIds, onClose, onDone }) {
  const { OS_STAGES, OS_RESPONSIBLES } = OS_DATA;
  const isStage = action === "stage";
  const title   = isStage ? "Mudar etapa" : "Atribuir responsável";
  const help    = isStage
    ? "A nova etapa será aplicada em todas as OS selecionadas. Histórico será registrado."
    : "O responsável atual será substituído nas OS selecionadas. Anteriores ficam no histórico.";

  const [pick, setPick]       = useStateO(null);   // id da etapa ou do responsável
  const [notify, setNotify]   = useStateO(true);
  const [comment, setComment] = useStateO("");

  // Filtra etapas válidas (sem cancelado quando é avanço normal)
  const stages = useMemoO(() => OS_STAGES.filter(s => s.id !== "cancelado"), [OS_STAGES]);

  return (
    <>
      <div className="os-modal-back" onClick={onClose}/>
      <div className="os-modal os-bulk-modal">
        <div className="os-modal-h">
          <div>
            <h2>{title}</h2>
            <p>
              <b>{count}</b> OS selecionada{count>1?"s":""} · <span className="mono os-bulk-ids">
                {selectedIds.slice(0,4).map(id => `#${id}`).join(", ")}
                {selectedIds.length > 4 && ` +${selectedIds.length-4}`}
              </span>
            </p>
          </div>
          <button className="icon-btn" onClick={onClose}><I.close size={14}/></button>
        </div>

        <div className="os-bulk-body">
          <p className="os-new-help">{help}</p>

          {isStage ? (
            <div className="os-bulk-stages">
              {stages.map(s => (
                <button key={s.id}
                  className={`os-bulk-stage ${pick===s.id?"active":""}`}
                  onClick={() => setPick(s.id)}>
                  <span className={`os-stage ${s.color}`}>{s.label}</span>
                  {pick===s.id && <I.check size={13}/>}
                </button>
              ))}
            </div>
          ) : (
            <div className="os-new-resps">
              {OS_RESPONSIBLES.map(r => (
                <button key={r.id}
                  className={`os-new-resp ${pick===r.id?"active":""}`}
                  onClick={() => setPick(r.id)}>
                  <span className={`os-resp-av lg ${r.grad}`}>{r.initials}</span>
                  <div>
                    <b>{r.name}</b>
                    <small>{r.team}</small>
                  </div>
                </button>
              ))}
            </div>
          )}

          <label className="os-bulk-toggle">
            <input type="checkbox" checked={notify} onChange={e => setNotify(e.target.checked)}/>
            <span>Notificar {isStage ? "responsáveis das OS" : "novo responsável"} por chat</span>
          </label>

          <label className="os-new-textarea">
            <small>Comentário (opcional)</small>
            <textarea rows="3"
              placeholder={isStage ? "Motivo da mudança de etapa..." : "Contexto para o novo responsável..."}
              value={comment} onChange={e => setComment(e.target.value)}/>
          </label>
        </div>

        <div className="os-modal-foot">
          <button className="os-btn ghost" onClick={onClose}>Cancelar</button>
          <span className="os-bulk-spacer"/>
          <button className={`os-btn primary ${!pick?"disabled":""}`} disabled={!pick} onClick={onDone}>
            <I.check size={13}/> Aplicar em {count} OS
          </button>
        </div>
      </div>
    </>
  );
}

window.OsBulkModal = OsBulkModal;
