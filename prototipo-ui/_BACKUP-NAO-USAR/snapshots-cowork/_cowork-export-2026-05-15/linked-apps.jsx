// linked-apps.jsx — coluna direita "Apps Vinculados"
// Cada bloco é colapsável, mostra resumo enxuto + 1 ação primária.
// Se a conversa não tem o tipo de dado, o bloco não renderiza.

const { useState: useStateL, useEffect: useEffectL } = React;

function useCollapsed(key, initial = false) {
  const [c, setC] = useStateL(() => {
    try { const v = localStorage.getItem(`oimpresso.linked.${key}.collapsed`); return v == null ? initial : v === "1"; }
    catch (e) { return initial; }
  });
  useEffectL(() => {
    try { localStorage.setItem(`oimpresso.linked.${key}.collapsed`, c ? "1" : "0"); } catch (e) {}
  }, [c]);
  return [c, setC];
}

function LBlock({ title, icon, originBadge, children, blockKey, action }) {
  const [collapsed, setCollapsed] = useCollapsed(blockKey, false);
  const Icon = icon;
  return (
    <section className={"lblock" + (collapsed ? " collapsed" : "")}>
      <header className="lblock-h" onClick={() => setCollapsed(c => !c)}>
        <Icon size={13}/>
        <b>{title}</b>
        {originBadge && <span className={`origin-badge o-${originBadge}`}>{originBadge}</span>}
        <span className="spacer"/>
        <I.chevR size={11} className={"lblock-chev" + (collapsed ? "" : " open")}/>
      </header>
      {!collapsed && (
        <div className="lblock-b">
          {children}
          {action && <button className="lblock-cta">{action.label} <I.chevR size={11}/></button>}
        </div>
      )}
    </section>
  );
}

function LinkedOs({ os, client, stage, deadline }) {
  return (
    <LBlock title="Ordem de Serviço" icon={I.orders} originBadge="OS" blockKey="os"
            action={{label: "Abrir OS"}}>
      <div className="lkv"><span>Número</span><b className="mono">{os}</b></div>
      <div className="lkv"><span>Cliente</span><b>{client}</b></div>
      <div className="lkv"><span>Estágio</span><span className="lstage">● {stage}</span></div>
      <div className="lkv"><span>Prazo</span><b>{deadline}</b></div>
    </LBlock>
  );
}

function LinkedClient({ name, phone, lastTouch }) {
  return (
    <LBlock title="Cliente" icon={I.clients} originBadge="CRM" blockKey="client"
            action={{label: "Ligar agora"}}>
      <div className="lkv"><span>Nome</span><b>{name}</b></div>
      {phone && <div className="lkv"><span>Telefone</span><b className="mono">{phone}</b></div>}
      {lastTouch && <div className="lkv col"><span>Último contato</span><span className="lhint">{lastTouch}</span></div>}
      <div className="lrow-btns">
        <button className="lbtn-sec"><I.phone size={11}/> Ligar</button>
        <button className="lbtn-sec"><I.chat size={11}/> WhatsApp</button>
      </div>
    </LBlock>
  );
}

function LinkedPonto({ collaborator, marcacoes }) {
  return (
    <LBlock title="Ponto" icon={I.user} originBadge="PNT" blockKey="ponto"
            action={{label: "Ver espelho"}}>
      <div className="lkv"><span>Colaborador</span><b>{collaborator}</b></div>
      <div className="lpunch">
        {marcacoes.map((m, i) => (
          <div key={i} className={"lpunch-row" + (m.missing ? " missing" : "")}>
            <span>{m.label}</span>
            <b className="mono">{m.time}</b>
          </div>
        ))}
      </div>
    </LBlock>
  );
}

function LinkedFin({ balance, openBills }) {
  return (
    <LBlock title="Financeiro" icon={I.cash} originBadge="FIN" blockKey="fin"
            action={{label: "Emitir cobrança"}}>
      <div className="lkv"><span>Saldo cliente</span><b>{balance}</b></div>
      <div className="lkv"><span>Boletos abertos</span><b>{openBills}</b></div>
    </LBlock>
  );
}

function LinkedAttachments({ files }) {
  if (!files || !files.length) return null;
  return (
    <LBlock title="Anexos" icon={I.paperclip} blockKey="att">
      <div className="latts">
        {files.map((f, i) => (
          <div key={i} className="latt">
            <I.paperclip size={11}/>
            <div className="latt-body">
              <b>{f.name}</b>
              <small>{f.size}</small>
            </div>
          </div>
        ))}
      </div>
    </LBlock>
  );
}

function LinkedHistory({ events }) {
  if (!events || !events.length) return null;
  return (
    <LBlock title="Histórico" icon={I.bell} blockKey="hist">
      <ul className="lhist">
        {events.map((e, i) => (
          <li key={i}>
            <span className="lhist-when">{e.when}</span>
            <span className="lhist-who"><b>{e.who}</b> {e.what}</span>
          </li>
        ))}
      </ul>
    </LBlock>
  );
}

// ─── Painel inteiro: deduz blocos a partir da conversa ativa ───
function LinkedAppsPanel({ conv, collapsed, onToggle }) {
  if (!conv) {
    return (
      <aside className={"linked" + (collapsed ? " collapsed" : "")}>
        <header className="linked-h">
          {!collapsed && <b>Apps vinculados</b>}
          <span className="spacer"/>
          <button className="icon-btn" onClick={onToggle} title={collapsed ? "Expandir" : "Recolher"}>
            <I.chevR size={12} style={{transform: collapsed ? "rotate(180deg)" : "none"}}/>
          </button>
        </header>
        {!collapsed && (
          <div className="linked-empty">
            <I.folder size={20}/>
            <p>Selecione uma conversa para ver os apps vinculados.</p>
          </div>
        )}
      </aside>
    );
  }

  // Mock de dados derivados da conversa
  const isOs = conv.kind === "os";
  const isClient = conv.kind === "client";
  const today = [
    { label: "Entrada",        time: "08:02" },
    { label: "Saída almoço",   time: "12:00" },
    { label: "Retorno almoço", time: "13:05" },
    { label: "Saída",          time: "—", missing: true },
  ];
  const files = (conv.msgs || []).filter(m => m.file).map(m => m.file);
  const events = [
    { when: "14:32", who: "Mateus PCP", what: "liberou para impressão" },
    { when: "13:55", who: "Joana Lima", what: "subiu versão v3" },
    { when: "10:02", who: "Mateus PCP", what: "alocou na Roland 540" },
    { when: "ontem 17:30", who: "Camila (cli)", what: "pediu logo +6%" },
  ];

  return (
    <aside className={"linked" + (collapsed ? " collapsed" : "")}>
      <header className="linked-h">
        {!collapsed && <b>Apps vinculados</b>}
        <span className="spacer"/>
        <button className="icon-btn" onClick={onToggle} title={collapsed ? "Expandir" : "Recolher"}>
          <I.chevR size={12} style={{transform: collapsed ? "rotate(180deg)" : "none"}}/>
        </button>
      </header>
      {!collapsed && (
        <div className="linked-body">
          {isOs && (
            <LinkedOs
              os={conv.os}
              client={conv.client}
              stage={conv.stage || "Em produção"}
              deadline="30/04 às 16h"/>
          )}
          {(isOs || isClient) && (
            <LinkedClient
              name={conv.client || conv.title}
              phone="+55 11 98712-3344"
              lastTouch="hoje 11:48 — perguntou se pode retirar 9h amanhã"/>
          )}
          {isOs && (
            <LinkedFin
              balance="R$ 4.820,00 a receber"
              openBills="2 boletos · R$ 4.820,00"/>
          )}
          {conv.kind === "team" && (
            <LinkedPonto
              collaborator={conv.title}
              marcacoes={today}/>
          )}
          <LinkedAttachments files={files}/>
          <LinkedHistory events={isOs ? events : []}/>
        </div>
      )}
    </aside>
  );
}

window.LinkedAppsPanel = LinkedAppsPanel;
