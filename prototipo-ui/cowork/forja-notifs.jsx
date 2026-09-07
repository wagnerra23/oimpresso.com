// forja-notifs.jsx — minha fila (notificações): triagem, meus, seguindo, comentários e
// não-verificados. Extraído de forja-mcp.jsx (Onda 3).
const { useState: useStateNo, useMemo: useMemoNo, useRef: useRefNo, useEffect: useEffectNo } = React;
// ─── Notificações / minha fila ───
function ForjaNotifs({ notifs, seen, onOpen, onTriage, onSeen, onMarkAll, onClose }) {
  const RoleBadge = window.FjRoleBadge;
  const Sec = ({ title, sec, items, onClick }) => items.length === 0 ? null : (
    <div className="fj-notif-sec">
      <h4>{title}<span className="fj-notif-n">{items.length}</span></h4>
      <ul>{items.map(i => {
        const unread = !seen.has(sec + ":" + i.id);
        return (
          <li key={i.id} className={unread ? "unread" : ""} onClick={() => { onSeen(sec + ":" + i.id); onClick(i.id); }}>
            <span className={"fj-notif-dot" + (unread ? " on" : "")}/>
            <span className="fj-id">{i.id}</span>
            <span className="fj-notif-t">{i.titulo}</span>
            <RoleBadge role={i.assignee}/>
          </li>
        );
      })}</ul>
    </div>
  );
  const empty = !notifs.triagem.length && !notifs.mine.length && !notifs.follow.length && !notifs.comments.length && !notifs.inferido.length;
  return (
    <div className="fj-pal-back fj-notif-back" onClick={onClose}>
      <div className="fj-notif-pop" onClick={e => e.stopPropagation()}>
        <div className="fj-notif-head"><b>Minha fila</b><button className="fj-notif-markall" onClick={onMarkAll}>marcar tudo lido</button><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <div className="fj-notif-body">
          <Sec title="Aguardando sua aprovação" sec="triagem" items={notifs.triagem} onClick={onTriage}/>
          <Sec title="Atribuídos a você [W]" sec="mine" items={notifs.mine} onClick={onOpen}/>
          <Sec title="Seguindo" sec="follow" items={notifs.follow} onClick={onOpen}/>
          <Sec title="Comentários recentes" sec="comments" items={notifs.comments} onClick={onOpen}/>
          <Sec title="Não-verificados @main" sec="inferido" items={notifs.inferido} onClick={onOpen}/>
          {empty && <div className="fj-notif-empty">Nada pendente. Tudo limpo.</div>}
        </div>
      </div>
    </div>
  );
}
window.ForjaNotifs = ForjaNotifs;
