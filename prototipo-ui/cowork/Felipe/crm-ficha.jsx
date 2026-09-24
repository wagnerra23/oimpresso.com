// crm-ficha.jsx — Ficha da Frota (CRM 360°). O CRM como CAMADA DE CONTEXTO:
// frota → veículos → contatos + timeline unificada + próxima-melhor-ação (Jana).
// NÃO é o funil (esse é o crm-page.jsx). É a ficha do cliente que faltava.
// Token-driven → claro/escuro pelo toggle do host. Expõe window.CrmFicha.
(() => {
const { useState } = React;

const I = {
  truck: (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 6h11v9H3z"/><path d="M14 9h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>,
  wrench:(p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14.7 6.3a4 4 0 0 0 5.4 5.4L21 12l-9 9-2-2 9-9-1.7-1.7a4 4 0 0 0-5.4-5.4L13 5l-9 9 2 2 9-9-.3-.7Z"/></svg>,
  wa:    (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>,
  phone: (p) => <svg width={p.s||13} height={p.s||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3-8.6A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.4-1.2a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.6 2Z"/></svg>,
  cash:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>,
  doc:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>,
  bolt:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M13 2L4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z"/></svg>,
  cal:   (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>,
  plus:  (p) => <svg width={p.s||14} height={p.s||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14"/></svg>,
  arrow: (p) => <svg width={p.s||13} height={p.s||13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>,
};

const FROTA = {
  nome: "Frota Boa Esperança", sigla: "BE", tipo: "Cliente PJ · Frota · desde mar/2019",
  cnpj: "12.345.678/0001-90", ltv: "R$ 142.300", saude: "atencao",
  kpis: { aberto: "R$ 4.200", ticket: "R$ 3.840", ultima: "Hoje", veiculos: "8" },
};

const VEICULOS = [
  { mod: "VW Constellation 24.280", placa: "RBA-2H78", km: "412.500", rev: "na oficina agora", st: "now" },
  { mod: "Mercedes Actros 2651",    placa: "GHS-8E22", km: "455.900", rev: "revisão vencida",   st: "bad" },
  { mod: "Volvo FH 540",            placa: "FZJ-4F12", km: "388.100", rev: "revisão em 4.800 km", st: "warn" },
  { mod: "Scania P310",             placa: "KKQ-7H44", km: "88.650",  rev: "revisão em 2.100 km", st: "warn" },
  { mod: "Scania R450",             placa: "QXM-1B33", km: "201.300", rev: "em dia",             st: "ok" },
  { mod: "VW Constellation 19.360", placa: "OWD-5R09", km: "297.300", rev: "em dia",             st: "ok" },
  { mod: "Volvo FH 460",            placa: "MNT-3T55", km: "134.500", rev: "em dia",             st: "ok" },
  { mod: "Iveco Tector 240",        placa: "BCY-9G07", km: "162.110", rev: "em dia",             st: "ok" },
];

const TIMELINE = [
  { o: "OS",  when: "Hoje 08:14",  text: "Constellation 24.280 recepcionado — perda de força + fumaça", tone: "" },
  { o: "FIN", when: "Ontem",       text: "Boleto NF 1198 vencido · R$ 4.200,00", tone: "neg" },
  { o: "VENDA", when: "07/05",     text: "Revisão Scania R450 faturada · R$ 2.250,00 · NF-e 001.190", tone: "pos" },
  { o: "WA",  when: "03/05",       text: "Cláudia confirmou retirada do Volvo FH 540", tone: "" },
  { o: "OS",  when: "28/04",       text: "Mercedes Actros — troca de embreagem concluída", tone: "" },
  { o: "VENDA", when: "15/04",     text: "4 pneus Iveco Tector · R$ 3.840,00", tone: "pos" },
];

const ACOES = [
  { ic: "cash", tone: "neg",    title: "Cobrar antes de entregar", body: "Boleto NF 1198 vencido há 1 dia (R$ 4.200). O Constellation está pronto pra retirar — cobre na entrega.", cta: "Cobrar" },
  { ic: "wrench", tone: "warn", title: "Revisão vencida no Actros", body: "Mercedes Actros 2651 passou da revisão (455.900 km). Ofereça agendamento enquanto a frota está na casa.", cta: "Agendar" },
  { ic: "bolt", tone: "accent", title: "Pacote de frota", body: "2 caminhões vencem revisão em < 5.000 km. Proponha um pacote preventivo da frota inteira.", cta: "Propor" },
];

const CONTATOS = [
  { ini: "CR", nome: "Cláudia Reis",  papel: "Gestora de frota", tel: "(34) 9 9988-7766", main: true },
  { ini: "AL", nome: "Anderson Luz",  papel: "Motorista",         tel: "(34) 9 9712-0033" },
  { ini: "MV", nome: "Marcos Vale",   papel: "Financeiro",        tel: "(34) 3221-4500" },
];

const ST = {
  now:  { lbl: "na oficina", cls: "now" },
  bad:  { lbl: "vencida",    cls: "bad" },
  warn: { lbl: "em breve",   cls: "warn" },
  ok:   { lbl: "em dia",     cls: "ok" },
};
const ORIGIN = {
  OS:    { lbl: "OS",     cls: "os" },
  FIN:   { lbl: "FIN",    cls: "fin" },
  VENDA: { lbl: "VENDA",  cls: "venda" },
  WA:    { lbl: "WhatsApp", cls: "wa" },
};

function Kpi({ label, val, tone }) {
  return <div className={"crmf-kpi" + (tone ? " " + tone : "")}><span className="crmf-kpi-l">{label}</span><b className="crmf-kpi-v">{val}</b></div>;
}

function VehicleCard({ v }) {
  const s = ST[v.st];
  return (
    <div className={"crmf-veh st-" + s.cls} style={{ cursor: "pointer" }} title="Abrir na Oficina"
      onClick={() => { try { window.__selectRoute && window.__selectRoute("oficinaauto"); if (v.st === "now") setTimeout(() => window.dispatchEvent(new CustomEvent("oimpresso:open-os", { detail: { os_id: "8821" } })), 280); } catch (e) {} }}>
      <div className="crmf-veh-top">
        <span className="crmf-veh-ic"><I.truck s={15}/></span>
        <span className={"crmf-veh-pill " + s.cls}>{s.lbl}</span>
      </div>
      <div className="crmf-veh-mod">{v.mod}</div>
      <div className="crmf-plate"><span className="pt">BR · MERCOSUL</span><span className="pn">{v.placa}</span></div>
      <div className="crmf-veh-foot"><span className="mono">{v.km} km</span><span className="crmf-veh-rev">{v.rev}</span></div>
    </div>
  );
}

function CrmFicha() {
  const [tab, setTab] = useState("visao");
  const f = FROTA;
  return (
    <div className="crmf" data-screen-label="01 CRM · Ficha da Frota">
      {/* header de identidade */}
      <header className="crmf-head">
        <div className="crmf-id">
          <div className="crmf-avatar">{f.sigla}</div>
          <div className="crmf-id-meta">
            <div className="crmf-id-name">{f.nome}<span className={"crmf-saude " + f.saude}>{f.saude === "atencao" ? "atenção financeira" : "em dia"}</span></div>
            <div className="crmf-id-sub">{f.tipo} · CNPJ {f.cnpj} · LTV {f.ltv}</div>
          </div>
        </div>
        <div className="crmf-actions">
          <button className="crmf-btn"><I.wa s={13}/>WhatsApp</button>
          <button className="crmf-btn"><I.cash s={13}/>Cobrar em aberto</button>
          <button className="crmf-btn primary" onClick={() => { try { window.__selectRoute && window.__selectRoute("oficinaauto"); setTimeout(() => window.dispatchEvent(new CustomEvent("oimpresso:nova-os", { detail: { frota: f.nome } })), 280); } catch (e) {} }}><I.plus s={13}/>Nova OS</button>
        </div>
      </header>

      {/* KPIs de relacionamento */}
      <div className="crmf-kpis">
        <Kpi label="Em aberto" val={f.kpis.aberto} tone="neg"/>
        <Kpi label="Ticket médio" val={f.kpis.ticket}/>
        <Kpi label="Última visita" val={f.kpis.ultima}/>
        <Kpi label="Veículos" val={f.kpis.veiculos}/>
      </div>

      <nav className="crmf-tabs">
        {[["visao", "Visão geral"], ["veiculos", "Veículos"], ["historico", "Histórico"]].map(([k, l]) => (
          <button key={k} className={"crmf-tab" + (tab === k ? " active" : "")} onClick={() => setTab(k)}>{l}</button>
        ))}
      </nav>

      <div className="crmf-body">
        <div className="crmf-main">
          {(tab === "visao" || tab === "veiculos") && (
            <section className="crmf-sec">
              <div className="crmf-sec-h"><h3>Veículos da frota</h3><span className="crmf-sec-c">{VEICULOS.length}</span></div>
              <div className="crmf-veh-grid">{VEICULOS.map((v, i) => <VehicleCard key={i} v={v}/>)}</div>
            </section>
          )}
          {(tab === "visao" || tab === "historico") && (
            <section className="crmf-sec">
              <div className="crmf-sec-h"><h3>Linha do tempo</h3><span className="crmf-sec-sub">tudo: OS · vendas · financeiro · conversas</span></div>
              <div className="crmf-tl">{TIMELINE.map((t, i) => {
                const o = ORIGIN[t.o];
                return (
                  <div key={i} className="crmf-tl-row">
                    <span className={"crmf-tl-dot " + (t.tone || "")}/>
                    <span className={"crmf-origin " + o.cls}>{o.lbl}</span>
                    <span className={"crmf-tl-text" + (t.tone ? " " + t.tone : "")}>{t.text}</span>
                    <span className="crmf-tl-when">{t.when}</span>
                  </div>
                );
              })}</div>
            </section>
          )}
        </div>

        {/* rail: próxima-melhor-ação + contatos */}
        <aside className="crmf-rail">
          <section className="crmf-card">
            <div className="crmf-card-h"><span className="crmf-jana"><I.bolt s={12}/></span>Próxima melhor ação<small>Jana</small></div>
            <div className="crmf-acoes">{ACOES.map((a, i) => (
              <div key={i} className={"crmf-acao " + a.tone}>
                <div className="crmf-acao-h"><span className={"crmf-acao-ic " + a.tone}>{I[a.ic]({ s: 13 })}</span><b>{a.title}</b></div>
                <p>{a.body}</p>
                <button className={"crmf-acao-cta " + a.tone}>{a.cta}<I.arrow s={12}/></button>
              </div>
            ))}</div>
          </section>

          <section className="crmf-card">
            <div className="crmf-card-h">Contatos<small>{CONTATOS.length}</small></div>
            <div className="crmf-contatos">{CONTATOS.map((c, i) => (
              <div key={i} className="crmf-contato">
                <span className="crmf-contato-av">{c.ini}</span>
                <div className="crmf-contato-meta"><b>{c.nome}{c.main && <span className="crmf-contato-main">principal</span>}</b><small>{c.papel}</small></div>
                <span className="crmf-contato-tel mono">{c.tel}</span>
                <button className="crmf-contato-wa" title="WhatsApp"><I.wa s={13}/></button>
              </div>
            ))}</div>
          </section>
        </aside>
      </div>
    </div>
  );
}

window.CrmFicha = CrmFicha;
})();
