// Superadmin · Usuário 360° — PUXAR (thread superadmin/00, 2026-10-01).
// Fonte viva: Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/{Index,Show}.tsx @main.
// Index: busca 300ms, paginação client-side de 10, 2 estados vazios. Show: 9 blocos.
// Divergência declarada: em produção o 360° é página cheia (/superadmin/usuarios/{id}/360);
// aqui é drawer (PT-02). Decisão de [W] — está no _saida-00.
(function () {
const { useState, useEffect, useMemo, useRef } = React;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};

const USUARIOS = [
  { id: 1, nome: "Wagner Rocha", email: "wagner@oimpresso.com", username: "wagner", biz: null, bizNome: null, status: "active", tipo: "superadmin", travado: false, criado: "02/01/2024" },
  { id: 412, nome: "Larissa Souza", email: "larissa@rotalivre.com", username: "larissa.rl", biz: 171, bizNome: "ROTA LIVRE Comunicação Visual", status: "active", tipo: "user", travado: false, criado: "14/03/2025" },
  { id: 418, nome: "Diego Prado", email: "diego@rotalivre.com", username: "diego.balcao", biz: 171, bizNome: "ROTA LIVRE Comunicação Visual", status: "active", tipo: "user", travado: false, criado: "02/04/2025" },
  { id: 503, nome: "Martinho Alves", email: "martinho@oficinamartinho.com.br", username: "martinho", biz: 164, bizNome: "Martinho Oficina", status: "active", tipo: "user", travado: false, criado: "21/07/2025" },
  { id: 511, nome: "Renan Oliveira", email: "renan@oficinamartinho.com.br", username: "renan.tecnico", biz: 164, bizNome: "Martinho Oficina", status: "inactive", tipo: "user", travado: true, criado: "03/08/2025" },
  { id: 640, nome: "Eliana Torres", email: "eliana@grupossinaliza.com.br", username: "eliana.fin", biz: 188, bizNome: "Grupo Sinaliza", status: "active", tipo: "user", travado: false, criado: "11/01/2026" },
  { id: 655, nome: "Paulo Mendes", email: "paulo@fachadasnorte.com.br", username: "paulo", biz: 192, bizNome: "Fachadas Norte", status: "active", tipo: "user", travado: false, criado: "13/08/2026" },
  { id: 702, nome: "Camila Reis", email: "camila@grupossinaliza.com.br", username: "camila.vendas", biz: 188, bizNome: "Grupo Sinaliza", status: "active", tipo: "user", travado: false, criado: "20/08/2026" },
  { id: 715, nome: "Thiago Lins", email: "thiago@grupossinaliza.com.br", username: "thiago", biz: 188, bizNome: "Grupo Sinaliza", status: "active", tipo: "user", travado: false, criado: "27/08/2026" },
  { id: 731, nome: "Bianca Freitas", email: "bianca@rotalivre.com", username: "bianca.arte", biz: 171, bizNome: "ROTA LIVRE Comunicação Visual", status: "active", tipo: "user", travado: false, criado: "02/09/2026" },
  { id: 744, nome: "Ícaro Nunes", email: "icaro@oficinamartinho.com.br", username: "icaro", biz: 164, bizNome: "Martinho Oficina", status: "active", tipo: "user", travado: false, criado: "15/09/2026" },
  { id: 760, nome: "Sofia Andrade", email: "sofia@fachadasnorte.com.br", username: "sofia", biz: 192, bizNome: "Fachadas Norte", status: "active", tipo: "user", travado: false, criado: "28/09/2026" },
];

const RISCO = {
  low: { l: "Baixo", tone: "success" }, medium: { l: "Médio", tone: "info" },
  high: { l: "Alto", tone: "warning" }, critical: { l: "Crítico", tone: "danger" },
};

// 360° — mesmos 9 blocos do Show.tsx; dado ilustrativo, derivado do id pra ser estável.
function raioX(u) {
  const adm = u.tipo === "superadmin";
  return {
    papeis: adm ? [{ nome: "Superadmin", biz: "todos" }] : [{ nome: u.id % 2 ? "Balcão" : "Gerente", biz: u.bizNome }],
    modulos: [
      { modulo: "Vendas", total: 14, dados: adm ? 14 : 9, critico: false, alto: true, perms: [
        { l: "Vender no PDV", risk: "low", ok: true }, { l: "Cancelar venda finalizada", risk: "high", ok: adm }, { l: "Dar desconto acima do teto", risk: "high", ok: adm || u.id % 3 === 0 }] },
      { modulo: "Financeiro", total: 11, dados: adm ? 11 : u.id % 2 ? 2 : 7, critico: true, alto: true, perms: [
        { l: "Ver títulos", risk: "medium", ok: true }, { l: "Baixar título", risk: "high", ok: !(u.id % 2) || adm }, { l: "Excluir lançamento", risk: "critical", ok: adm }] },
      { modulo: "Usuários e acessos", total: 6, dados: adm ? 6 : 0, critico: true, alto: false, perms: [
        { l: "Criar usuário", risk: "high", ok: adm }, { l: "Trocar papel de outro usuário", risk: "critical", ok: adm }] },
    ],
    scopes: adm ? [{ slug: "ads.admin", desc: "Administração de anúncios", em: "02/01/2024" }, { slug: "mcp.full", desc: "MCP sem restrição", em: "02/01/2024" }] : u.id % 3 === 0 ? [{ slug: "mcp.read", desc: "MCP só leitura", em: u.criado }] : [],
    tokens: adm || u.id % 3 === 0 ? [{ id: 90 + u.id % 7, nome: "Claude Desktop", mask: "oi_mcp_••••" + String(u.id).padStart(4, "0"), uso: "hoje 08:41", ip: "187.32.14.9", expira: "31/12/2026", revogado: u.travado }] : [],
    quota: { usado: adm ? 1840 : (u.id * 37) % 400, teto: adm ? 0 : 500 },
    sessoes: u.travado ? [] : [{ disp: "Chrome · Windows", ip: "187.32.14." + (u.id % 200), desde: "hoje 07:58" }, ...(u.id % 2 ? [] : [{ disp: "Safari · iPhone", ip: "177.81.2.40", desde: "ontem 19:12" }])],
    auditoria: [
      { quando: "hoje 08:41", acao: "consultou", alvo: "financeiro.titulos", tone: "default" },
      { quando: "ontem 17:20", acao: "baixou título", alvo: "TIT-20931", tone: "warn" },
      { quando: "28/09 10:02", acao: "exportou", alvo: "vendas.csv", tone: "default" },
    ],
    lockouts: u.travado ? [{ quando: "29/09/2026 16:10", por: "wagner", motivo: "Acesso a títulos fora do expediente", tokens: 1 }] : [],
  };
}

const Ico = {
  busca: <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>,
  x: <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>,
  cadeado: <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>,
  aberto: <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.5-2"/></svg>,
};

function Badge({ tone, children }) {
  const { StatusBadge } = ds();
  if (!StatusBadge) return <span className="sa-mono">{children}</span>;
  return <StatusBadge tone={tone} label={children}/>;
}

function Sec({ titulo, nota, children }) {
  return (
    <section className="sa-dr-sec">
      <h3>{titulo}{nota && <span className="sa-u-nota"> · {nota}</span>}</h3>
      {children}
    </section>
  );
}

function Drawer360({ u, onClose, onTrancar, onDestrancar }) {
  const [tranca, setTranca] = useState(false);
  const [motivo, setMotivo] = useState("");
  const [destranca, setDestranca] = useState(false);
  useEffect(() => {
    const h = (e) => { if (e.key === "Escape" && !tranca && !destranca) onClose(); };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, [onClose, tranca, destranca]);
  const r = useMemo(() => raioX(u), [u]);
  const { Modal, Progress, Textarea } = ds();
  const piorRisco = r.modulos.some((m) => m.critico && m.perms.some((p) => p.risk === "critical" && p.ok)) ? "critical"
    : r.modulos.some((m) => m.perms.some((p) => p.risk === "high" && p.ok)) ? "high" : "low";
  return (
    <>
      <div className="sa-scrim" onClick={onClose}></div>
      <aside className="sa-drawer sa-drawer--360" role="dialog" aria-label={`Usuário 360° — ${u.nome}`}>
        <header className="sa-dr-h">
          <div>
            <span className="sa-mono sa-dr-id">user #{u.id} · {u.username}</span>
            <h2>{u.nome}</h2>
            <p>{u.bizNome ? `${u.bizNome} · biz #${u.biz}` : "Todos os negócios"} · desde {u.criado}</p>
          </div>
          <button className="sa-dr-x" onClick={onClose} title="Fechar (esc)" aria-label="Fechar">{Ico.x}</button>
        </header>
        <div className="sa-dr-body">
          <Sec titulo="Identidade">
            <div className="sa-dr-rows">
              <div><span>Situação</span>{u.travado ? <Badge tone="danger">Trancado</Badge> : <span className={"sa-dot " + (u.status === "active" ? "on" : "off")}><i></i>{u.status === "active" ? "Ativo" : "Inativo"}</span>}</div>
              <div><span>Risco do acesso</span><Badge tone={RISCO[piorRisco].tone}>{RISCO[piorRisco].l}</Badge></div>
              <div><span>E-mail</span><b>{u.email}</b></div>
              <div><span>Tipo</span><b className="sa-mono">{u.tipo}</b></div>
            </div>
          </Sec>
          <Sec titulo="Papéis">
            <ul className="sa-dr-hist">{r.papeis.map((p) => <li key={p.nome}><b>{p.nome}</b><span>{p.biz}</span></li>)}</ul>
          </Sec>
          <Sec titulo="Permissões efetivas" nota="por módulo, com risco">
            {r.modulos.map((m) => (
              <div key={m.modulo} className="sa-u-mod">
                <div className="sa-u-mod-h"><b>{m.modulo}</b><span className="sa-mono">{m.dados} de {m.total}</span></div>
                <ul className="sa-u-perms">
                  {m.perms.map((p) => (
                    <li key={p.l} className={p.ok ? "" : "off"}>
                      <span>{p.ok ? "Concedida" : "Negada"} · {p.l}</span>
                      <Badge tone={RISCO[p.risk].tone}>{RISCO[p.risk].l}</Badge>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </Sec>
          <Sec titulo="Scopes ADS / MCP">
            {r.scopes.length === 0 ? <p className="sa-dr-empty">Nenhum scope concedido.</p> :
              <ul className="sa-dr-hist">{r.scopes.map((s) => <li key={s.slug}><b className="sa-mono">{s.slug}</b><span>{s.desc} · desde {s.em}</span></li>)}</ul>}
          </Sec>
          <Sec titulo="Tokens MCP" nota="mascarados">
            {r.tokens.length === 0 ? <p className="sa-dr-empty">Nenhum token emitido.</p> :
              <ul className="sa-dr-hist">{r.tokens.map((t) => (
                <li key={t.id}><b className="sa-mono">{t.mask}</b><span>{t.nome} · último uso {t.uso} de {t.ip} · expira {t.expira}</span>{t.revogado ? <Badge tone="neutral">Revogado</Badge> : <Badge tone="success">Ativo</Badge>}</li>
              ))}</ul>}
          </Sec>
          <Sec titulo="Quota do Copiloto" nota="mês corrente">
            {Progress ? <Progress value={r.quota.teto ? r.quota.usado : 0} max={r.quota.teto || 100} tone={r.quota.teto && r.quota.usado / r.quota.teto > 0.8 ? "warning" : "accent"} label="Consultas" showValue formatValue={() => r.quota.teto ? `${r.quota.usado} de ${r.quota.teto}` : `${r.quota.usado} · sem teto`}/>
              : <b className="sa-mono">{r.quota.usado} de {r.quota.teto || "∞"}</b>}
          </Sec>
          <Sec titulo="Sessões ativas">
            {r.sessoes.length === 0 ? <p className="sa-dr-empty">Nenhuma sessão aberta.</p> :
              <ul className="sa-dr-hist">{r.sessoes.map((s) => <li key={s.ip + s.desde}><b>{s.disp}</b><span className="sa-mono">{s.ip} · desde {s.desde}</span></li>)}</ul>}
          </Sec>
          <Sec titulo="Auditoria recente">
            <ul className="sa-dr-hist">{r.auditoria.map((a) => <li key={a.quando + a.alvo}><b className="sa-mono">{a.quando}</b><span>{a.acao} <span className="sa-mono">{a.alvo}</span></span></li>)}</ul>
          </Sec>
          <Sec titulo="Histórico de trancamentos">
            {r.lockouts.length === 0 ? <p className="sa-dr-empty">Nunca foi trancado.</p> :
              <ul className="sa-dr-hist">{r.lockouts.map((l) => <li key={l.quando}><b className="sa-mono">{l.quando}</b><span>por {l.por} · {l.motivo} · {l.tokens} token revogado</span></li>)}</ul>}
          </Sec>
        </div>
        <footer className="sa-dr-f">
          {u.tipo !== "superadmin" && (u.travado
            ? <button className="os-btn ghost" onClick={() => setDestranca(true)}>{Ico.aberto} Destrancar</button>
            : <button className="os-btn danger" onClick={() => setTranca(true)}>{Ico.cadeado} Trancar</button>)}
        </footer>
      </aside>
      {Modal && tranca && (
        <Modal open onClose={() => setTranca(false)} title="Trancar usuário"
          footer={<>
            <button className="os-btn ghost" onClick={() => setTranca(false)}>Cancelar</button>
            <button className="os-btn danger" disabled={motivo.trim().length < 5} onClick={() => { onTrancar(u, motivo.trim()); setTranca(false); setMotivo(""); }}>Trancar</button>
          </>}>
          <p className="sa-modal-p">{u.nome} perde o acesso agora: a sessão cai e os tokens MCP são revogados. Fica um registro com o motivo e o estado de hoje.</p>
          {Textarea
            ? <Textarea label="Motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} help="Obrigatório — mínimo 5 caracteres." rows={3}/>
            : <textarea aria-label="Motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} rows={3}></textarea>}
        </Modal>
      )}
      {Modal && destranca && (
        <Modal open onClose={() => setDestranca(false)} title="Destrancar usuário"
          footer={<>
            <button className="os-btn ghost" onClick={() => setDestranca(false)}>Cancelar</button>
            <button className="os-btn primary" onClick={() => { onDestrancar(u); setDestranca(false); }}>Destrancar</button>
          </>}>
          <p className="sa-modal-p">{u.nome} volta a entrar. Os tokens MCP revogados <b>não voltam</b> — se precisar, gere um novo.</p>
        </Modal>
      )}
    </>
  );
}

const PAGE_SIZE = 10;
function SuperadminUsuariosPage() {
  const [lista, setLista] = useState(USUARIOS);
  const [q, setQ] = useState("");
  const [busca, setBusca] = useState("");
  const [carregando, setCarregando] = useState(false);
  const [page, setPage] = useState(1);
  const [aberto, setAberto] = useState(null);
  const [toast, setToast] = useState(null);
  const ref = useRef(null);
  const primeira = useRef(true);
  const { PageHeader, EmptyState, Skeleton, Pagination, Toast } = ds();

  useEffect(() => {
    if (primeira.current) { primeira.current = false; return; }
    setCarregando(true);
    const t = setTimeout(() => { setBusca(q.trim()); setPage(1); setCarregando(false); }, 300);
    return () => clearTimeout(t);
  }, [q]);
  useEffect(() => {
    const h = (e) => { if (e.key === "/" && !/INPUT|TEXTAREA/.test(e.target.tagName)) { e.preventDefault(); ref.current?.focus(); } };
    document.addEventListener("keydown", h);
    return () => document.removeEventListener("keydown", h);
  }, []);
  useEffect(() => { if (!toast) return; const t = setTimeout(() => setToast(null), 3200); return () => clearTimeout(t); }, [toast]);

  const achados = useMemo(() => {
    if (!busca) return [];
    const s = busca.toLowerCase();
    return lista.filter((u) => [u.nome, u.email, u.username].some((v) => v.toLowerCase().includes(s)));
  }, [busca, lista]);
  const pageCount = Math.max(1, Math.ceil(achados.length / PAGE_SIZE));
  const pagina = achados.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);
  const atual = aberto && lista.find((u) => u.id === aberto);
  const muda = (id, patch) => setLista((l) => l.map((u) => (u.id === id ? { ...u, ...patch } : u)));

  return (
    <div className="os-page sa-page" data-screen-label="Superadmin · Usuário 360°">
      {PageHeader
        ? <div className="sa-ph"><PageHeader title="Usuário 360°" subtitle="Tudo sobre um usuário num lugar só — papéis, permissões, tokens, sessões e auditoria."/></div>
        : <header className="os-page-h"><div className="os-page-h-l"><h1>Usuário 360°</h1></div></header>}
      {window.SaSubnav && <window.SaSubnav ativo="sa-usuarios" />}
      <div className="sa-toolbar">
        <div className="sa-search">
          {Ico.busca}
          <input ref={ref} type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nome, e-mail ou username…" aria-label="Buscar usuário"/>
          {q ? <button className="sa-clear" onClick={() => setQ("")} aria-label="Limpar busca">Limpar</button> : <kbd className="sa-kbd">/</kbd>}
        </div>
      </div>
      {carregando ? (
        <div className="sa-u-skel" aria-busy="true">{Array.from({ length: 6 }).map((_, i) => Skeleton ? <Skeleton key={i} variant="row"/> : <div key={i} className="sa-skel"></div>)}</div>
      ) : !busca ? (
        <div className="sa-vazio-wrap">{EmptyState ? <EmptyState variant="first" title="Comece uma busca" description="Digite um nome, e-mail ou username para localizar o usuário. A lista não aparece sem busca — ela é sensível."/> : <p>Comece uma busca</p>}</div>
      ) : achados.length === 0 ? (
        <div className="sa-vazio-wrap">{EmptyState ? <EmptyState variant="no-results" title="Nenhum usuário encontrado" description={`Nada corresponde a "${busca}". Tente outro termo.`} action={<button className="os-btn ghost" onClick={() => setQ("")}>Limpar busca</button>}/> : <p>Nenhum usuário encontrado</p>}</div>
      ) : (
        <>
          <div className="os-table-wrap">
            <table className="os-table sa-table">
              <thead><tr><th>ID</th><th>Nome</th><th>E-mail</th><th>Username</th><th>Negócio</th><th>Situação</th><th>Tipo</th><th aria-label="Ações"></th></tr></thead>
              <tbody>
                {pagina.map((u) => (
                  <tr key={u.id} onClick={() => setAberto(u.id)} className="sa-row-click">
                    <td className="sa-mono">{u.id}</td>
                    <td><b>{u.nome}</b></td>
                    <td>{u.email}</td>
                    <td className="sa-mono">{u.username}</td>
                    <td>{u.bizNome ? <span>{u.bizNome} <span className="sa-mono">#{u.biz}</span></span> : "—"}</td>
                    <td>{u.travado ? <Badge tone="danger">Trancado</Badge> : <span className={"sa-dot " + (u.status === "active" ? "on" : "off")}><i></i>{u.status === "active" ? "Ativo" : "Inativo"}</span>}</td>
                    <td className="sa-mono">{u.tipo}</td>
                    <td><button className="os-btn ghost sm" onClick={(e) => { e.stopPropagation(); setAberto(u.id); }}>Ver 360°</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="sa-pag">
            {Pagination && pageCount > 1
              ? <Pagination page={page} pageCount={pageCount} total={achados.length} pageSize={PAGE_SIZE} onChange={setPage} nextLabel="Próxima"/>
              : <span className="sa-pag-meta">Mostrando {(page - 1) * PAGE_SIZE + 1}–{Math.min(page * PAGE_SIZE, achados.length)} de {achados.length}</span>}
          </div>
        </>
      )}
      {atual && <Drawer360 u={atual} onClose={() => setAberto(null)}
        onTrancar={(u, m) => { muda(u.id, { travado: true, status: "inactive" }); setToast({ msg: `${u.nome} trancado — sessão encerrada e tokens revogados`, tone: "warn" }); }}
        onDestrancar={(u) => { muda(u.id, { travado: false, status: "active" }); setToast({ msg: `${u.nome} destrancado — tokens não foram devolvidos`, tone: "ok" }); }}/>}
      {toast && <div className="sa-toast-wrap" role="status">{Toast ? <Toast tone={toast.tone}>{toast.msg}</Toast> : <span className="sa-toast">{toast.msg}</span>}</div>}
    </div>
  );
}
window.SuperadminUsuariosPage = SuperadminUsuariosPage;
})();
