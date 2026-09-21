// Apps Vinculados — contextual right panel for the focused cliente.
// Different cards: OS recentes, Financeiro snapshot, Documentos, Histórico,
// "Falar com Copiloto" entry point.

const { useState: useSCL } = React;

function CLBlock({ title, badge, badgeClass, icon, defaultOpen = true, children }) {
  const [open, setOpen] = useSCL(defaultOpen);
  return (
    <section className={`lblock ${open ? '' : 'collapsed'}`}>
      <header className="lblock-h" onClick={() => setOpen(o => !o)}>
        {icon}
        <b>{title}</b>
        {badge && <span className={`origin-badge ${badgeClass}`}>{badge}</span>}
        <span className="spacer"></span>
        <span className={`lblock-chev ${open ? 'open' : ''}`}>
          <Icon.ChevronRight size={11} />
        </span>
      </header>
      {open && <div className="lblock-b">{children}</div>}
    </section>
  );
}

function ClientesLinkedEmpty() {
  return (
    <aside className="linked">
      <div className="linked-h">
        <b>Apps Vinculados</b>
      </div>
      <div className="linked-empty">
        <Icon.User size={36} style={{ opacity: 0.25 }} />
        <p style={{ margin: '12px 0 4px', fontSize: 12.5, fontWeight: 500, color: 'var(--text)' }}>
          Nenhum cliente em foco
        </p>
        <p>Selecione um cliente na lista para ver OSs, financeiro e histórico contextuais.</p>
      </div>
    </aside>
  );
}

function ClientesLinked({ cliente, collapsed, onCollapse }) {
  if (collapsed) {
    return (
      <aside className="linked collapsed">
        <div className="linked-h">
          <button className="icon-btn" title="Expandir" onClick={onCollapse}>
            <Icon.PanelRight size={14} />
          </button>
        </div>
      </aside>
    );
  }
  if (!cliente) return <ClientesLinkedEmpty />;

  const oss = window.fakeOSs(cliente);

  return (
    <aside className="linked">
      <div className="linked-h">
        <b>Apps Vinculados</b>
        <span className="spacer"></span>
        <button className="icon-btn" title="Recolher" onClick={onCollapse}>
          <Icon.X size={14} />
        </button>
      </div>
      <div className="linked-body">
        {/* Cliente snapshot card */}
        <section className="lblock" style={{ background: 'var(--accent-soft)', borderColor: 'transparent' }}>
          <header className="lblock-h" style={{ background: 'transparent', borderBottom: 0 }}>
            <Icon.User size={13} style={{ color: 'var(--accent)' }} />
            <b style={{ color: 'var(--accent)' }}>Cliente em foco</b>
          </header>
          <div className="lblock-b" style={{ paddingTop: 0 }}>
            <div className="lkv col">
              <span>{cliente.tipo === 'PJ' ? 'Razão social' : 'Nome'}</span>
              <b style={{ fontSize: 13 }}>{cliente.nome}</b>
            </div>
            {cliente.fantasia && cliente.fantasia !== cliente.nome && (
              <div className="lkv"><span>Fantasia</span><b>{cliente.fantasia}</b></div>
            )}
            <div className="lkv"><span>{cliente.tipo === 'PF' ? 'CPF' : 'CNPJ'}</span><b className="mono">{cliente.doc}</b></div>
            <div className="lkv"><span>Telefone</span><b className="mono">{cliente.tel}</b></div>
            <div className="lkv"><span>Cidade</span><b>{cliente.cidade}/{cliente.uf}</b></div>
            <div className="lrow-btns">
              <button className="lbtn-sec"><Icon.Phone size={11} /> Ligar</button>
              <button className="lbtn-sec"><Icon.MessageSquare size={11} /> WhatsApp</button>
            </div>
          </div>
        </section>

        {/* OS recentes */}
        <CLBlock title="OSs recentes" badge="OS" badgeClass="o-OS"
          icon={<Icon.Briefcase size={13} />}>
          {oss.slice(0, 3).map(os => (
            <div key={os.id} style={{
              display: 'flex', justifyContent: 'space-between', alignItems: 'center',
              padding: '6px 0', borderBottom: '1px solid var(--border-2)',
              fontSize: 12,
            }}>
              <div style={{ minWidth: 0 }}>
                <div style={{ color: 'var(--accent)', fontFamily: 'var(--font-mono)', fontSize: 11, fontWeight: 600 }}>{os.id}</div>
                <div style={{ color: 'var(--text)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 180 }}>
                  {os.titulo}
                </div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontWeight: 600, fontVariantNumeric: 'tabular-nums' }}>{window.BRL(os.valor)}</div>
                <div style={{ fontSize: 10.5, color: 'var(--text-mute)' }}>{window.relDate(os.em)}</div>
              </div>
            </div>
          ))}
          <button className="lblock-cta" type="button">
            Ver todas ({cliente.totalOSs})
            <Icon.ChevronRight size={11} />
          </button>
        </CLBlock>

        {/* Financeiro */}
        <CLBlock title="Financeiro" badge="FIN" badgeClass="o-FIN"
          icon={<Icon.DollarSign size={13} />}>
          <div className="lkv"><span>Saldo aberto</span>
            <b style={cliente.saldo > 0 ? { color: 'oklch(0.55 0.18 25)' } : {}}>{window.BRL(cliente.saldo)}</b>
          </div>
          <div className="lkv"><span>Ticket médio</span><b>{window.BRL(cliente.ticketMedio)}</b></div>
          <div className="lkv"><span>Total OSs</span><b>{cliente.totalOSs}</b></div>
          <div className="lkv"><span>Última compra</span><b>{window.relDate(cliente.ultimaCompra)}</b></div>
          {cliente.saldo > 0 && (
            <button className="lblock-cta" type="button">
              Emitir cobrança
              <Icon.ChevronRight size={11} />
            </button>
          )}
        </CLBlock>

        {/* Copiloto */}
        <CLBlock title="Copiloto" badge="IA" badgeClass="o-CRM"
          icon={<Icon.Sparkles size={13} />} defaultOpen={false}>
          <p style={{ margin: 0, fontSize: 12, color: 'var(--text-dim)', lineHeight: 1.5 }}>
            Quer entender o comportamento deste cliente? Eu posso te mostrar tendência de compra,
            margem média e propor próximos contatos.
          </p>
          <button className="lblock-cta" type="button" style={{ marginTop: 8 }}>
            <Icon.Sparkles size={11} /> Falar com Copiloto sobre {cliente.fantasia || cliente.nome.split(' ')[0]}
          </button>
        </CLBlock>
      </div>
    </aside>
  );
}

window.ClientesLinked = ClientesLinked;
