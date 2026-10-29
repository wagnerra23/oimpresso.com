// producao-cv-os.jsx — a OS como projeto: ficha (drawer PT-02 760px), Custo real × orçado e Roteiros.
// Ficha: etapas do roteiro com responsável, máquina, previsto × apontado · arte (aprovação online) e arquivos ·
// custo por etapa · refugo/retrabalho com motivo · histórico append-only. Expõe window.PcvOs.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const P = () => window.PCVD;
const B = (p) => (window.PcvBadge ? <window.PcvBadge par={p} /> : null);
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <span aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span> : null; };
const EST = { feito: ["Feito", "success"], pronto: ["Pronto", "success"], andamento: ["Em andamento", "info"], fila: ["Na fila", "neutral"], agendada: ["Agendada", "info"], "a agendar": ["A agendar", "warning"] };
const MOTIVOS = ["Banding / falha de cabeça", "Cor fora do padrão", "Medida errada", "Mídia com defeito", "Erro de arte", "Dano no manuseio"];
const HORA = 85; // R$/h de máquina+operador (custo-hora configurável por posto no real)
// Despesas da OS (P-098, comparação com concorrentes de 2026-10-23, fonte: Omie): deslocamento, hospedagem e afins,
// com o recibo anexado. Reembolsável = cobrada do cliente, separada do serviço; não reembolsável = fica só no custo.
// Mexe em valor: no ERP segue a regra mestre (dupla prova + antes → depois para o [W]). Como a despesa aparece na
// nota (NF-e/NFS-e) é decisão do [W]; a regra A de 2026-10-21 divide o que entra na venda pelo bruto.
const TIPOS_DESP = ["Deslocamento", "Hospedagem", "Alimentação", "Pedágio e estacionamento", "Outra"];
const DESPESAS = {
  "OS-2608": [
    { tipo: "Deslocamento", descricao: "Combustível e pedágio até Jaú (ida e volta)", valor: 186.4, reembolsavel: true, recibo: "recibo-posto-1510.jpg", quem: "Jonas", quando: "15/10 07:40" },
    { tipo: "Alimentação", descricao: "Almoço da equipe de instalação", valor: 64, reembolsavel: false, recibo: "nota-restaurante.jpg", quem: "Jonas", quando: "15/10 12:50" },
  ],
};

function EtapaLi({ e, i, D }) {
  return (
    <li className={"is-" + e.estado.replace(" ", "-")} style={{ "--c": D.setorDe(e.setor).cor }}>
      <span className="pcv-et-n" aria-hidden="true">{i + 1}</span>
      <div className="pcv-et-m"><b>{e.nome}</b><small>{D.setorDe(e.setor).nome}{e.maq ? " · " + D.maqDe(e.maq).nome : ""} · {e.resp}</small></div>
      <div className="pcv-et-t mono" aria-label={`${D.h1(e.real)} de ${D.h1(e.prev)} horas`}><span className={e.real > e.prev ? "pcv-late" : ""}>{D.h1(e.real)}</span>/{D.h1(e.prev)} h</div>
      {B(EST[e.estado])}
    </li>
  );
}

// Situação da OS por item (processo "OS e produção por item" de fluxos-processos.jsx). O roteiro (aba Etapas) diz
// ONDE o item está; a situação diz COMO ele está: na fila, em execução, pausado, em retrabalho, concluído.
// Cada ação que pede dado o pede na prévia (campos do OiEtapaPainel), antes de confirmar.
function situacaoDe(os, es) {
  if (es.every((e) => e.estado === "feito" || e.estado === "pronto")) return "Concluída";
  if (os.arte !== "aprovada" || os.liberada === false) return "Aguardando liberação";
  // Liberada sem material (US-PCP-032 · D3): nenhuma etapa começa até o PCP conferir.
  if (os.material === "aguardando") return "Aguardando material";
  return es.some((e) => e.estado === "andamento") ? "Em execução" : "Na fila";
}
// O que falta de material para a OS sair de Aguardando material (UC-PCMAT-02/03). Só lê estoque.
function TabelaFalta({ falta, D }) {
  const n = (v) => Number(v).toLocaleString("pt-BR", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  return (
    <table className="pcv-baixa" aria-label="Material que falta">
      <thead><tr><th>Insumo</th><th className="ta-r">Necessário</th><th className="ta-r">Disponível</th><th className="ta-r">Falta</th><th>A caminho</th></tr></thead>
      <tbody>{falta.map((f) => <tr key={f.insumo}><td>{f.insumo}</td><td className="ta-r mono">{n(f.necessario)} {f.un}</td><td className="ta-r mono">{n(f.disponivel)} {f.un}</td>
        <td className="ta-r mono pcv-late">{n(f.falta)} {f.un}</td>
        <td>{f.caminho.length ? f.caminho.map((c) => c.compra + " · " + n(c.qtd) + " " + f.un + " · chega " + D.d2(c.chega)).join("; ") : "nenhuma compra pedida"}</td></tr>)}</tbody>
    </table>
  );
}

function SituacaoItem({ os, es, D, irAba }) {
  const { Select } = DS();
  if (!window.OiEtapaPainel) return null;
  const org = D.origemDe ? D.origemDe(os.id) : null;
  const falta = es.filter((e) => e.estado !== "feito" && e.estado !== "pronto");
  const feitas = es.filter((e) => e.estado === "feito" || e.estado === "pronto" || e.estado === "andamento");
  const mats = (D.MATERIAIS || {})[os.id] || [];
  const faltaMat = os.material === "aguardando" && D.faltaDe ? D.faltaDe(os.id) : [];
  // Sinal mínimo pendente: o Liberar é recusado com o motivo, inclusive para quem tem producao.liberar (UC-PCLIB-05).
  const sin = D.sinalDe ? D.sinalDe(os) : null;
  const sinalFalta = os.arte === "aprovada" && sin && !sin.ok;
  const radio = (nome, ops, val, set, k) => <div className="pcv-mot" role="radiogroup" aria-label={nome}>{ops.map((o) => <label key={o}><input type="radio" name={nome} checked={val[k] === o} onChange={() => set({ ...val, [k]: o })} /> {o}</label>)}</div>;
  const pessoas = Object.keys(D.PESSOAS || {});
  const abertas = es.map((e, i) => [e, i]).filter(([e]) => e.estado !== "feito" && e.estado !== "pronto");
  const campos = {
    // Decisão [W] 16/10 (opção c): o PCP escolhe o responsável de cada etapa ao Liberar. Sugestão = o de
    // sempre do roteiro; só o responsável aponta na etapa pelo celular.
    "Liberar": { ok: (v) => abertas.every(([e, i]) => (v["r" + i] ?? e.resp)), texto: (v) => abertas.map(([e, i]) => e.nome + ": " + (v["r" + i] ?? e.resp)).join(" · "),
      render: (v, set) => <><span className="oie-l">Responsável de cada etapa</span>
        <table className="pcv-baixa"><thead><tr><th>Etapa</th><th>Setor</th><th>Responsável</th></tr></thead>
          <tbody>{abertas.map(([e, i]) => <tr key={i}><td>{e.nome}</td><td>{D.setorDe(e.setor).nome}</td><td>
            <select aria-label={"Responsável da etapa " + e.nome} value={v["r" + i] ?? e.resp} onChange={(ev) => set({ ...v, ["r" + i]: ev.target.value })}>
              {pessoas.map((p) => <option key={p} value={p}>{p} · {D.PESSOAS[p]}</option>)}</select></td></tr>)}</tbody></table>
        <p className="oie-dim">Só o responsável aponta na etapa pelo celular. Dá para trocar depois, enquanto a etapa não começou.</p></> },
    "Pausar": { ok: (v) => !!v.motivo, texto: (v) => v.motivo, render: (v, set) => <><span className="oie-l">Motivo da pausa</span>{radio("Motivo da pausa", ["Falta material", "Máquina", "Aguardando cliente"], v, set, "motivo")}</> },
    "Reprovar no controle": { ok: (v) => !!(v.motivo && v.origem), texto: (v) => v.motivo + " · volta para " + v.origem, render: (v, set) => <div className="pcv-mot-g">
      {Select && <Select label="Motivo" value={v.motivo || ""} onChange={(e) => set({ ...v, motivo: e.target.value })} options={[{ value: "", label: "Escolha" }].concat(MOTIVOS.map((m) => ({ value: m, label: m })))} />}
      {Select && <Select label="Etapa de origem (reabre)" value={v.origem || ""} onChange={(e) => set({ ...v, origem: e.target.value })} options={[{ value: "", label: "Escolha" }].concat(feitas.map((e) => ({ value: e.nome, label: e.nome })))} />}
      <p className="oie-dim">O custo do refugo soma na OS; a etapa de origem volta para a fila.</p></div> },
    "Concluir item": { ok: () => true, texto: () => "baixa de " + mats.length + " materiais", render: () => <><span className="oie-l">Baixa do material real (antes → depois)</span>
      <table className="pcv-baixa"><thead><tr><th>Material</th><th>Estoque antes</th><th>Baixa real</th><th>Depois</th></tr></thead>
        <tbody>{mats.map(([n, u, prev, real]) => { const b = real || prev, antes = Math.ceil(b * 2.5) + 10; return <tr key={n}><td>{n}</td><td className="mono">{D.h1(antes)} {u}</td><td className="mono">− {D.h1(b)} {u}{!real ? " (previsto)" : ""}</td><td className="mono">{D.h1(antes - b)} {u}</td></tr>; })}</tbody></table>
      <p className="oie-dim">Custo congelado em {D.fmt(os.real)}. {org && org.total > 1 ? "A venda " + org.venda + " fica pronta quando as " + org.total + " OS terminarem." : "Esta é a única OS da venda: a venda fica pronta para concluir."}</p></> },
    "Conferir material": { ok: () => true, texto: () => "nada falta", render: () => <><span className="oie-l">Material da receita</span>
      <p>Nada falta: a OS volta para Na fila e a primeira etapa pode começar. Conferir só lê o estoque, não baixa nada.</p></> },
    "Cancelar item": { ok: (v) => !!v.consumo, texto: (v) => v.consumo, render: (v, set) => <><span className="oie-l">Consumo até aqui</span>{radio("Consumo", ["Nada consumido", "Lançar consumo como perda"], v, set, "consumo")}</> },
  };
  return (
    <div className="pcv-sit">
      {org && <p className="pcv-org">Item {org.item} de {org.total} da venda <b className="mono">{org.venda}</b>{org.irmas.length > 1 ? " · outras OS: " + org.irmas.filter((k) => k !== os.id).join(", ") : ""}{org.outros ? " · " + org.outros : ""}</p>}
      <window.OiEtapaPainel proc="producao" modo="acao" docId={os.id} estado={situacaoDe(os, es)} campos={campos}
        nota={falta.length ? "Roteiro: " + (es.length - falta.length) + " de " + es.length + " etapas feitas — próxima: " + falta[0].nome + "." : null}
        bloqueios={() => ({ ...(os.arte !== "aprovada" ? { "Liberar": "arte ainda não aprovada pelo cliente" } : {}),
          ...(sinalFalta ? { "Liberar": "a empresa só libera a produção com " + sin.pct + "% da venda pago: exigido " + D.fmt(sin.exigido) + ", pago " + D.fmt(sin.pago) } : {}),
          ...(faltaMat.length ? { "Conferir material": "ainda falta " + faltaMat.map((f) => D.h1(f.falta) + " " + f.un + " de " + f.insumo).join(" e ") } : {}), ...(falta.length > 1 ? { "Concluir item": falta.length === 2 ? "falta 1 etapa do roteiro" : "faltam " + (falta.length - 1) + " etapas do roteiro" } : {}) })}
        resolver={() => ({ "Liberar": sinalFalta
            ? { label: "Simular: o cliente pagou o sinal", onClick: () => {
                D.pagarSinal(os.id);
                window.OiEtapa.registrar("producao", os.id, { quem: "Sistema", acao: "Liberar pelo sinal", de: "Aguardando liberação", para: "Na fila" }, "Na fila", "Na fila");
              } }
            : { label: "Ver arte e arquivos", onClick: () => irAba("arte") }, "Concluir item": { label: "Ver etapas", onClick: () => irAba("etapas") }, "Conferir material": { label: "Ver o que falta", onClick: () => irAba("materiais") } })}
        interceptar={{ "Conferir material": (t) => {
          // A tela é dona da situação da OS: muda o dado e informa o painel (sem registrar "mudança feita na tela").
          os.material = null;
          window.OiEtapa.registrar("producao", os.id, { quem: "Você", acao: t.acao, de: "Aguardando material", para: t.para }, t.para, t.para);
          D.registrar({ os: os.id, tipo: "material", texto: "Conferiu o material: nada falta, a OS voltou para Na fila" });
        } }} />
    </div>
  );
}

function FichaOs({ id, onClose }) {
  const D = P(); const { Drawer, TabBar, Button, Progress, Alert, Select, Input, FsmStepper } = DS();
  const os = D.osDe(id);
  const [aba, setAba] = useState("etapas");
  const [refugo, setRefugo] = useState(os ? os.refugo || [] : []);
  const [novo, setNovo] = useState(null);
  const [corr, setCorr] = useState(null);
  // Terceiros da OS (US-COMVIS-005): serviço comprado fora que entra no custo real. Não mexe em preço.
  const [terc, setTerc] = useState(os ? os.terceiros || [] : []);
  const [novoTerc, setNovoTerc] = useState(null);
  const [desp, setDesp] = useState(os ? DESPESAS[os.id] || [] : []);
  const [novaDesp, setNovaDesp] = useState(null);
  if (window.PcvEventos) window.PcvEventos.useEventos();
  if (!os || !Drawer) return null;
  const es = D.etapas(os); const pr = D.progresso(os); const atr = D.atraso(os);
  const prev = es.reduce((s, e) => s + e.prev, 0), real = es.reduce((s, e) => s + e.real, 0);
  const steps = es.map((e) => ({ label: e.nome, state: e.estado === "feito" || e.estado === "pronto" ? "done" : e.estado === "andamento" ? "current" : "todo" }));
  return (
    <Drawer open width={760} onClose={onClose} title={os.id + " · " + os.titulo} subtitle={os.cliente + " · " + os.qtd + " · entrega " + D.d2(os.prazo) + (atr ? " (" + atr + ")" : "")}
      badge={B(os.arte === "aprovada" ? ["Arte aprovada", "success"] : os.arte === "ajuste" ? ["Ajuste pedido", "danger"] : ["Aguardando arte", "warning"])}
      footer={<div className="pcv-bar" style={{ justifyContent: "flex-end" }}><Button onClick={onClose}>Fechar</Button><Button variant="primary" onClick={() => setAba("refugo")}>Registrar refugo</Button></div>}>
      <div className="pcv-ficha">
        <div className="pcv-ficha-top">
          <div><span className="pcv-eyebrow">Progresso</span>{Progress && <Progress value={pr} size={6} showValue />}</div>
          <div><span className="pcv-eyebrow">Tempo</span><b className="mono">{D.h1(real)} h</b> <small>de {D.h1(prev)} h previstas</small></div>
          <div><span className="pcv-eyebrow">Custo</span><b className="mono">{D.fmt(os.real)}</b> <small>de {D.fmt(os.orcado)} orçado</small></div>
        </div>
        {FsmStepper && <div className="pcv-fsm"><FsmStepper variant="inline" hue={295} steps={steps} /></div>}
        <SituacaoItem os={os} es={es} D={D} irAba={setAba} />
        {TabBar && <TabBar ariaLabel="Seções da OS" size="sm" tabs={[{ key: "etapas", label: "Etapas", count: es.length }, ...(window.PcvTarefas ? [{ key: "tarefas", label: "Tarefas", count: window.PcvTarefas.abertas(os.id).length }] : []), { key: "arte", label: "Arte e arquivos" }, { key: "materiais", label: "Materiais" }, { key: "custo", label: "Custo" }, { key: "refugo", label: "Refugo", count: refugo.length }, ...(window.PcvQualidade ? [{ key: "qualidade", label: "Qualidade", count: window.PcvQualidade.ncsDe(os.id).filter((n) => n.estado !== "encerrada").length }] : []), { key: "historico", label: "Histórico" }]} active={aba} onChange={setAba} />}

        {aba === "etapas" && <ol className="pcv-etapas">
          {(() => { const out = []; let i = 0; while (i < es.length) { if (es[i].trilha) { const bloco = []; while (i < es.length && es[i].trilha) { bloco.push([es[i], i]); i++; } out.push({ par: bloco }); } else { out.push({ e: es[i], i }); i++; } } return out; })().map((it, k) => it.par
            ? <li key={"p" + k} className="pcv-par" aria-label={"Em paralelo, junta em " + (os.junta || "")}>
                <span className="pcv-par-h">Em paralelo — junta em <b>{os.junta}</b></span>
                <div className="pcv-par-g">{["A", "B"].map((t) => <ol key={t} className="pcv-etapas" aria-label={"Trilha " + t}>{it.par.filter(([e]) => e.trilha === t).map(([e, i]) => <EtapaLi key={i} e={e} i={i} D={D} />)}</ol>)}</div>
              </li>
            : <EtapaLi key={it.i} e={it.e} i={it.i} D={D} />)}
        </ol>}

        {aba === "tarefas" && window.PcvTarefas && <window.PcvTarefas.AbaTarefas os={os} />}

        {aba === "arte" && <div className="pcv-sec">
          {os.arte === "aprovada"
            ? Alert && <Alert tone="success" title={"Aprovada pelo cliente em " + os.aprovadaEm.split(" ")[0].split("-").reverse().join("/") + " às " + os.aprovadaEm.split(" ")[1]}>Aprovação pelo link de prova (sem login). A versão aprovada fica travada: qualquer troca de arquivo pede nova aprovação.</Alert>
            : Alert && <Alert tone={os.arte === "ajuste" ? "danger" : "warn"} title={os.arte === "ajuste" ? "O cliente pediu ajuste" : "Aguardando aprovação do cliente"}>{os.arte === "ajuste" ? "\"Aumentar o logo e trocar o telefone.\" — enviado pelo link de prova às 08:47." : "Link de prova enviado ontem às 17:30 e reenviado hoje às 09:12. Visualizado 2 vezes."}<div className="pcv-al-a"><Button size="sm" onClick={() => {}}>Reenviar link de prova</Button></div></Alert>}
          <ul className="pcv-arq">
            {[["arte-final-v3.pdf", "12,4 MB", "versão aprovada"], ["prova-cliente-v3.jpg", "1,1 MB", "prova enviada"], ["arquivo-corte.ai", "640 KB", "corte CNC / recorte"], ["fotos-medicao.zip", "8,2 MB", "medição em campo"]].slice(0, os.produto === "lona" ? 2 : 4).map(([n, t, d]) => (
              <li key={n}><Ic name="doc" size={16} /><div><b>{n}</b><small>{t} · {d}</small></div><Button size="sm" variant="ghost">Baixar</Button></li>))}
          </ul>
          <p className="pcv-help">Os arquivos andam com a OS: o operador abre do celular, na etapa dele, a versão aprovada — nunca uma prova antiga.</p>
        </div>}

        {aba === "materiais" && <div className="pcv-sec">
          {os.material === "aguardando" && (() => { const falta = D.faltaDe(os.id); return <div className="pcv-falta" role="region" aria-label="Aguardando material">
            {Alert && <Alert tone={falta.length ? "warn" : "success"} title={falta.length ? "Aguardando material — nenhuma etapa começa" : "O material chegou — falta o PCP conferir"}>
              {falta.length ? "A OS foi liberada, mas falta insumo da receita no estoque do local da venda. Quando a compra chegar, o PCP confere e a OS volta para Na fila." : "Use Conferir material, no topo da ficha, para a OS voltar para Na fila."}</Alert>}
            {falta.length > 0 && <TabelaFalta falta={falta} D={D} />}
            {falta.length > 0 && <div className="pcv-bar">
              {Button && <Button size="sm" onClick={() => window.__go && window.__go("compras")}>Abrir compras</Button>}
              {Button && <Button size="sm" variant="ghost" onClick={() => D.receberCompra(os.id)}>Simular: a compra chegou</Button>}
            </div>}
          </div>; })()}
          <table className="pcv-tbl">
            <thead><tr><th scope="col">Material</th><th scope="col" className="ta-r">Empenhado</th><th scope="col" className="ta-r">Consumido</th><th scope="col" className="ta-r">Saldo</th></tr></thead>
            <tbody>{(D.MATERIAIS[os.id] || []).map(([n, un, emp, con], i) => { const sal = emp - con; const fmtN = (v) => Number(v).toLocaleString("pt-BR", { maximumFractionDigits: 1 }); return (
              <tr key={i}><td>{n}</td><td className="ta-r mono">{fmtN(emp)} {un}</td><td className="ta-r mono">{con ? fmtN(con) + " " + un : "—"}</td><td className={"ta-r mono" + (sal < 0 ? " pcv-late" : "")}>{sal < 0 ? "estourou " + fmtN(-sal) : fmtN(sal)} {un}</td></tr>); })}</tbody>
          </table>
          <p className="pcv-help">O empenho nasce do orçamento aprovado e reserva o estoque. O consumido vem da baixa que o operador faz no apontamento; o que estoura vira alerta para o PCP e entra no custo real.</p>
        </div>}

        {aba === "custo" && <div className="pcv-sec">
          <table className="pcv-tbl">
            <thead><tr><th scope="col">Etapa</th><th scope="col" className="ta-r">Previsto</th><th scope="col" className="ta-r">Real</th><th scope="col" className="ta-r">Desvio</th></tr></thead>
            <tbody>{es.map((e, i) => { const p = e.prev * HORA, r = e.real * HORA; return <tr key={i}><td>{e.nome}</td><td className="ta-r mono">{D.fmt(p)}</td><td className="ta-r mono">{e.real ? D.fmt(r) : "—"}</td><td className={"ta-r mono" + (r > p ? " pcv-late" : "")}>{e.real ? (r > p ? "+" : "") + D.fmt(r - p) : "—"}</td></tr>; })}
              <tr className="pcv-tbl-sub"><td>Material (mídia, tinta, ferragem)</td><td className="ta-r mono">{D.fmt(os.orcado * 0.42)}</td><td className="ta-r mono">{D.fmt(os.real * 0.45)}</td><td className="ta-r mono">{os.perda ? "perda " + os.perda.toString().replace(".", ",") + "%" : "—"}</td></tr>
              <tr className="pcv-tbl-sub"><td>Terceiros (serviço comprado fora)</td><td className="ta-r mono">—</td><td className="ta-r mono">{terc.length ? D.fmt(terc.reduce((s, t) => s + t.valor, 0)) : "—"}</td><td className="ta-r mono">{terc.length ? "+" + D.fmt(terc.reduce((s, t) => s + t.valor, 0)) : "—"}</td></tr>
              {(() => { const so = desp.filter((d) => !d.reembolsavel).reduce((s, d) => s + d.valor, 0); return <tr className="pcv-tbl-sub"><td>Despesas não reembolsáveis</td><td className="ta-r mono">—</td><td className="ta-r mono">{so ? D.fmt(so) : "—"}</td><td className="ta-r mono">{so ? "+" + D.fmt(so) : "—"}</td></tr>; })()}
            </tbody>
          </table>
          <p className="pcv-help">Hora de máquina + operador a {D.fmt(HORA)}/h (configurável por posto). Material real vem da baixa feita no apontamento.</p>

          <h4 className="pcv-eyebrow">Terceiros</h4>
          {terc.length ? <ul className="pcv-refugo" aria-label="Terceiros lançados nesta OS">{terc.map((t, i) => (
            <li key={i}><div><b>{t.descricao}</b><small>{(t.fornecedor || "sem fornecedor") + " · " + t.quem + " · " + t.quando}</small></div>
              <b className="mono">{D.fmt(t.valor)}</b>
              <Button size="sm" variant="ghost" onClick={() => { D.registrar({ os: os.id, tipo: "pcp", texto: "Removeu terceiro: " + t.descricao + " (" + D.fmt(t.valor) + ")" }); setTerc(terc.filter((_, k) => k !== i)); }}>Remover</Button></li>))}</ul>
            : <p className="pcv-help">Nenhum terceiro lançado nesta OS.</p>}
          {novoTerc ? <div className="pcv-form">
            {Input && <Input label="O que foi comprado fora" value={novoTerc.descricao} placeholder="Ex.: corte CNC, instalação terceirizada, frete" onChange={(e) => setNovoTerc({ ...novoTerc, descricao: e.target.value })} />}
            {Input && <Input label="Fornecedor (opcional)" value={novoTerc.fornecedor} onChange={(e) => setNovoTerc({ ...novoTerc, fornecedor: e.target.value })} />}
            {Input && <Input label="Valor (R$)" value={novoTerc.valor} placeholder="0,00" onChange={(e) => setNovoTerc({ ...novoTerc, valor: e.target.value })} />}
            <div className="pcv-bar"><Button onClick={() => setNovoTerc(null)}>Cancelar</Button><Button variant="primary" disabled={!novoTerc.descricao.trim() || !(parseFloat(String(novoTerc.valor).replace(".", "").replace(",", ".")) > 0)} onClick={() => { const valor = parseFloat(String(novoTerc.valor).replace(".", "").replace(",", ".")); D.registrar({ os: os.id, tipo: "pcp", texto: "Lançou terceiro: " + novoTerc.descricao + " (" + D.fmt(valor) + ")" }); setTerc([...terc, { descricao: novoTerc.descricao.trim(), fornecedor: novoTerc.fornecedor.trim(), valor, quem: "Você", quando: "agora" }]); setNovoTerc(null); }}>Lançar</Button></div>
          </div> : Button && <Button onClick={() => setNovoTerc({ descricao: "", fornecedor: "", valor: "" })}>Lançar terceiro</Button>}
          <p className="pcv-help">O terceiro entra no custo real da OS e na margem; o preço da OS não muda. Só quem cuida do custo lança ou remove.</p>

          <h4 className="pcv-eyebrow">Despesas da OS</h4>
          {(() => { const cobrar = desp.filter((d) => d.reembolsavel).reduce((s, d) => s + d.valor, 0), custo = desp.filter((d) => !d.reembolsavel).reduce((s, d) => s + d.valor, 0); const org = D.origemDe ? D.origemDe(os.id) : null; return desp.length ? <>
            <ul className="pcv-refugo" aria-label="Despesas lançadas nesta OS">{desp.map((d, i) => (
              <li key={i}><div><b>{d.descricao}</b><small>{d.tipo + " · recibo " + (d.recibo || "não anexado") + " · " + d.quem + " · " + d.quando}</small></div>
                {B(d.reembolsavel ? ["Cobrar do cliente", "info"] : ["Só no custo", "neutral"])}
                <b className="mono">{D.fmt(d.valor)}</b>
                <Button size="sm" variant="ghost" onClick={() => { D.registrar({ os: os.id, tipo: "pcp", texto: "Removeu despesa: " + d.descricao + " (" + D.fmt(d.valor) + ")" }); const nv = desp.filter((_, k) => k !== i); DESPESAS[os.id] = nv; setDesp(nv); }}>Remover</Button></li>))}</ul>
            <div className="pcv-dp-tot">
              <span><small className="pcv-eyebrow">A cobrar do cliente</small><b className="mono">{D.fmt(cobrar)}</b><small>vai para o faturamento{org ? " da venda " + org.venda : ""}, separada do serviço</small></span>
              <span><small className="pcv-eyebrow">Fica só no custo</small><b className="mono">{D.fmt(custo)}</b><small>entra no custo real e na margem</small></span>
            </div></> : <p className="pcv-help">Nenhuma despesa lançada nesta OS.</p>; })()}
          {novaDesp ? <div className="pcv-form">
            {Select && <Select label="Tipo" value={novaDesp.tipo} onChange={(e) => setNovaDesp({ ...novaDesp, tipo: e.target.value })} options={TIPOS_DESP.map((t) => ({ value: t, label: t }))} />}
            {Input && <Input label="Descrição" value={novaDesp.descricao} placeholder="Ex.: hospedagem da equipe em Bauru, 1 diária" onChange={(e) => setNovaDesp({ ...novaDesp, descricao: e.target.value })} />}
            {Input && <Input label="Valor (R$)" value={novaDesp.valor} placeholder="0,00" onChange={(e) => setNovaDesp({ ...novaDesp, valor: e.target.value })} />}
            <label className="pcv-dp-arq">Recibo (foto ou PDF)<input type="file" accept="image/*,application/pdf" onChange={(e) => setNovaDesp({ ...novaDesp, recibo: e.target.files && e.target.files[0] ? e.target.files[0].name : "" })} /></label>
            <label className="pcv-dp-ck"><input type="checkbox" checked={novaDesp.reembolsavel} onChange={(e) => setNovaDesp({ ...novaDesp, reembolsavel: e.target.checked })} /> Cobrar do cliente (reembolsável)</label>
            <p className="pcv-help">{novaDesp.reembolsavel ? "Vai para o faturamento, separada do serviço. Como aparece na nota (NF-e ou NFS-e) é decisão do Wagner, ainda pendente." : "Não é cobrada do cliente: fica só no custo real da OS."}</p>
            <div className="pcv-bar"><Button onClick={() => setNovaDesp(null)}>Cancelar</Button><Button variant="primary" disabled={!novaDesp.descricao.trim() || !(parseFloat(String(novaDesp.valor).replace(".", "").replace(",", ".")) > 0)} onClick={() => { const valor = parseFloat(String(novaDesp.valor).replace(".", "").replace(",", ".")); const d = { tipo: novaDesp.tipo, descricao: novaDesp.descricao.trim(), valor, reembolsavel: novaDesp.reembolsavel, recibo: novaDesp.recibo, quem: "Você", quando: "agora" }; D.registrar({ os: os.id, tipo: "pcp", texto: "Lançou despesa: " + d.descricao + " (" + D.fmt(valor) + (d.reembolsavel ? ", cobrar do cliente" : ", só no custo") + ")" }); const nv = [...desp, d]; DESPESAS[os.id] = nv; setDesp(nv); setNovaDesp(null); }}>Lançar</Button></div>
          </div> : Button && <Button onClick={() => setNovaDesp({ tipo: TIPOS_DESP[0], descricao: "", valor: "", recibo: "", reembolsavel: true })}>Lançar despesa</Button>}
          <p className="pcv-help">Despesa de instalação ou visita, com o recibo anexado. A reembolsável é cobrada do cliente e não pesa na margem; a não reembolsável entra no custo real. Só quem cuida do custo lança ou remove.</p>
        </div>}

        {aba === "refugo" && <div className="pcv-sec">
          {refugo.length ? <ul className="pcv-refugo">{refugo.map((r, i) => <li key={i}><div><b>{r.motivo}</b><small>{r.etapa} · {r.m2.toString().replace(".", ",")} m² · {r.quem} · {r.quando}</small></div><b className="mono pcv-late">{D.fmt(r.custo)}</b></li>)}</ul>
            : <p className="pcv-help">Nenhum refugo nesta OS.</p>}
          {novo ? <div className="pcv-form">
            {Select && <Select label="Etapa" value={novo.etapa} onChange={(e) => setNovo({ ...novo, etapa: e.target.value })} options={es.map((e) => ({ value: e.nome, label: e.nome }))} />}
            {Select && <Select label="Motivo" value={novo.motivo} onChange={(e) => setNovo({ ...novo, motivo: e.target.value })} options={MOTIVOS.map((m) => ({ value: m, label: m }))} />}
            {Input && <Input label="Quantidade perdida (m²)" value={novo.m2} onChange={(e) => setNovo({ ...novo, m2: e.target.value })} />}
            <div className="pcv-bar"><Button onClick={() => setNovo(null)}>Cancelar</Button><Button variant="primary" disabled={!novo.m2} onClick={() => { const m2 = parseFloat(String(novo.m2).replace(",", ".")) || 0; D.registrar({ os: os.id, tipo: "refugo", texto: "Refugo de " + novo.m2 + " m² em " + novo.etapa + " — " + novo.motivo }); setRefugo([...refugo, { ...novo, m2, custo: Math.round(m2 * 36), quem: "Você", quando: "agora" }]); setNovo(null); }}>Registrar</Button></div>
          </div> : Button && <Button onClick={() => setNovo({ etapa: es[0].nome, motivo: MOTIVOS[0], m2: "" })}>Registrar refugo ou retrabalho</Button>}
          <p className="pcv-help">O refugo entra no custo real da OS e no relatório de perdas por motivo e por máquina.</p>
        </div>}

        {aba === "qualidade" && window.PcvQualidade && <window.PcvQualidade.AbaQualidade os={os} />}

        {aba === "historico" && <div className="pcv-sec">
          <div className="pcv-autofecha">
            <div><b>Apontamento fechado automaticamente às 18:00</b><small>14/10 · {D.atual(os).resp} · {D.atual(os).nome} — o cronômetro ficou aberto no fim do turno</small></div>
            {corr === null && Button && <Button size="sm" onClick={() => setCorr({ hora: "16:40", just: "" })}>Corrigir</Button>}
            {corr && corr.ok && <span className="pcv-help">Corrigido para {corr.hora} — "{corr.just}". O registro original continua no histórico.</span>}
          </div>
          {corr && !corr.ok && <div className="pcv-form">
            {Input && <Input label="Horário real do fim" value={corr.hora} onChange={(e) => setCorr({ ...corr, hora: e.target.value })} />}
            {Input && <Input label="Justificativa (obrigatória)" value={corr.just} placeholder="Ex.: esqueci de concluir ao trocar de máquina" onChange={(e) => setCorr({ ...corr, just: e.target.value })} />}
            <div className="pcv-bar"><Button onClick={() => setCorr(null)}>Cancelar</Button><Button variant="primary" disabled={corr.just.trim().length < 5} onClick={() => { setCorr({ ...corr, ok: true }); D.registrar({ os: os.id, tipo: "pcp", texto: "Corrigiu o fim do apontamento para " + corr.hora + " — " + corr.just.trim() }); }}>Registrar correção</Button></div>
          </div>}
          {window.PcvEventos && <window.PcvEventos.Feed eventos={D.eventosDe(os.id)} max={30} vazio="Sem eventos nesta OS ainda." />}
        </div>}
      </div>
    </Drawer>
  );
}

function Custo({ onAbrir }) {
  const D = P(); const { KpiCard, Widget } = DS();
  const linhas = D.OS.map((o) => ({ o, pr: D.progresso(o), esperado: o.orcado * D.progresso(o) / 100 }));
  const fechadas = D.OS.filter((o) => D.atual(o).estado === "pronto");
  const margem = fechadas.reduce((s, o) => s + (o.orcado - o.real), 0);
  return (
    <div className="pcv-body">
      <div className="pcv-kpis">
        {KpiCard && <KpiCard label="Orçado em produção" value={D.fmt(D.OS.reduce((s, o) => s + o.orcado, 0))} tone="info" />}
        {KpiCard && <KpiCard label="Gasto até agora" value={D.fmt(D.OS.reduce((s, o) => s + o.real, 0))} description="hora apontada + material baixado" />}
        {KpiCard && <KpiCard label="Acima do esperado" value={linhas.filter((l) => l.o.real > l.esperado * 1.05).length + " OS"} description="gasto > 105% do previsto para o progresso" tone="warning" />}
        {KpiCard && <KpiCard label="Sobra nas OS fechadas" value={D.fmt(margem)} description={fechadas.length + " OS prontas"} tone="success" />}
      </div>
      {Widget && (() => { const porMotivo = {}; const porMaq = {}; D.PERDAS.forEach((r) => { porMotivo[r.motivo] = (porMotivo[r.motivo] || 0) + r.custo; porMaq[r.maq] = (porMaq[r.maq] || 0) + r.custo; }); const tot = D.PERDAS.reduce((s, r) => s + r.custo, 0); const lst = (obj, nome) => Object.entries(obj).sort((a, b) => b[1] - a[1]).map(([k, v]) => (
          <li key={k}><span>{nome(k)}</span><i style={{ width: Math.round((v / tot) * 100) + "%" }} aria-hidden="true" /><b className="mono">{D.fmt(v)}</b></li>)); return (
        <div className="pcv-grid2">
          <Widget title="Perdas por motivo" note={"últimos 30 dias · " + D.fmt(tot)}><ul className="pcv-perdas">{lst(porMotivo, (k) => k)}</ul></Widget>
          <Widget title="Perdas por máquina" note="onde o refugo nasce"><ul className="pcv-perdas">{lst(porMaq, (k) => (D.maqDe(k) || {}).nome || k)}</ul></Widget>
        </div>); })()}
      {Widget && <Widget title="Por OS" note="o esperado é o orçado × o progresso — compara o que já deveria ter custado" flush>
        <table className="pcv-tbl">
          <thead><tr><th scope="col">OS</th><th scope="col">Cliente</th><th scope="col" className="ta-r">Progresso</th><th scope="col" className="ta-r">Orçado</th><th scope="col" className="ta-r">Esperado agora</th><th scope="col" className="ta-r">Real</th><th scope="col" className="ta-r">Desvio</th><th scope="col"><span className="sr-only">Abrir</span></th></tr></thead>
          <tbody>{linhas.map(({ o, pr, esperado }) => { const dv = esperado ? ((o.real - esperado) / esperado) * 100 : 0; return (
            <tr key={o.id}><td className="mono">{o.id}</td><td>{o.cliente}</td><td className="ta-r mono">{pr}%</td><td className="ta-r mono">{D.fmt(o.orcado)}</td><td className="ta-r mono">{D.fmt(esperado)}</td><td className="ta-r mono">{D.fmt(o.real)}</td>
              <td className={"ta-r mono" + (dv > 5 ? " pcv-late" : dv < -5 ? " pcv-ok" : "")}>{(dv > 0 ? "+" : "") + dv.toFixed(0)}%</td><td><button className="pcv-link" onClick={() => onAbrir(o.id)}>Abrir</button></td></tr>); })}</tbody>
        </table>
      </Widget>}
    </div>
  );
}

// Produto → roteiro (UC-FLX-07). Exemplo; no ERP a ligação é por empresa.
const PROD_ROT = { lona: ["Lona 440 g impressa", "Banner roll-up"], adesivo: ["Adesivo vinil impresso", "Adesivo de frota"], acm: ["Placa ACM 3 mm"], fachada: ["Fachada ACM sob medida"], letra: ["Letra-caixa iluminada"] };

function Roteiros() {
  const D = P(); const { Widget, Button, Alert } = DS();
  const [sel, setSel] = useState("fachada");
  const r = D.ROTEIROS[sel];
  const fams = [...new Set(Object.values(D.ROTEIROS).map((v) => v.familia))];
  let n = 0;
  const Passo = ({ p }) => { const [setor, nome, x, opc] = p; const m = typeof x === "string" ? D.maqDe(x) : null; n += 1; return (
    <li className={"is-fila" + (opc ? " is-opc" : "")} style={{ "--c": D.setorDe(setor).cor }}><span className="pcv-et-n" aria-hidden="true">{n}</span>
      <div className="pcv-et-m"><b>{nome}</b><small>{D.setorDe(setor).nome}{m ? " · " + m.nome : ""}{opc ? " · só " + opc : ""}</small></div>
      <div className="pcv-et-t mono">{m ? m.cap : D.h1(x) + " h"}</div></li>); };
  return (
    <div className="pcv-body pcv-rot">
      <nav className="pcv-rot-l" aria-label="Roteiros por família de produto">
        {fams.map((f) => <React.Fragment key={f}><span className="pcv-eyebrow pcv-rot-fam">{f}</span>
          {Object.entries(D.ROTEIROS).filter(([, v]) => v.familia === f).map(([k, v]) => <button key={k} aria-current={sel === k ? "true" : undefined} onClick={() => setSel(k)}><b>{v.nome}</b><small>{v.etapas.some((e) => e.paralelo) ? "com etapas em paralelo" : "em sequência"}</small></button>)}</React.Fragment>)}
      </nav>
      <div className="pcv-rot-r">
        {Widget && <Widget title={r.nome} note={r.familia + " · vira as etapas da OS quando o orçamento é aprovado"} actions={Button && <Button size="sm">Ajustar tempos</Button>}>
          <ol className="pcv-etapas pcv-rot-fluxo">
            {r.etapas.map((p, k) => p.paralelo
              ? <li key={k} className="pcv-par" aria-label={"Em paralelo, junta em " + p.junta}>
                  <span className="pcv-par-h">Em paralelo — as duas trilhas andam ao mesmo tempo e se juntam em <b>{p.junta}</b></span>
                  <div className="pcv-par-g">{p.paralelo.map((t, ti) => <ol key={ti} className="pcv-etapas" aria-label={"Trilha " + (ti + 1)}>{t.map((q, qi) => <Passo key={qi} p={q} />)}</ol>)}</div>
                </li>
              : <Passo key={k} p={p} />)}
          </ol>
          <p className="pcv-help">Tempo de máquina = quantidade ÷ capacidade do posto. Etapa marcada "só com…" entra na OS quando a opção vem marcada no orçamento.</p>
          <div className="pcv-rot-prod">
            <span className="pcv-eyebrow">Produtos que usam este roteiro</span>
            <div className="pcv-rot-chips">{(PROD_ROT[sel] || []).map((x) => <span key={x} className="pcv-chip">{x}</span>)}<Button size="sm">Ligar produto</Button></div>
            <p className="pcv-help">Cada item aprovado vira uma OS com o roteiro do produto dele. Produto sem roteiro usa as etapas de produção do modelo da empresa (Sistema › Fluxos e regras).</p>
          </div>
        </Widget>}
        {Alert && <Alert tone="info" title="Por que no máximo duas trilhas">Linha reta demais faz a impressão esperar a serralheria sem motivo. Fluxograma livre demais ninguém treina. O meio-termo: famílias prontas, até 2 trilhas que sempre se juntam, e opcionais ligados pelo orçamento. O operador nunca vê o roteiro inteiro — só "minhas etapas de hoje".</Alert>}
      </div>
    </div>
  );
}

// Passo "orçamento aprovado → OS" (UC-PCV-12, 14): escolhe o orçamento, o roteiro da família vem sozinho,
// as opções marcadas no orçamento ligam as etapas opcionais e os tempos saem de quantidade ÷ capacidade do posto.
function NovaOs({ onClose, onCriada }) {
  const D = P(); const { Drawer, Button, Alert, Switch } = DS();
  const [orc, setOrc] = useState(D.ORCAMENTOS[0].id);
  const o = D.ORCAMENTOS.find((x) => x.id === orc);
  const [ops, setOps] = useState(o.opcoes);
  const r = D.ROTEIROS[o.produto];
  const tempo = (x) => { if (typeof x !== "string") return x; const m = D.maqDe(x); return m && m.capH ? o.qtd / m.capH : 0; };
  const lista = []; r.etapas.forEach((p) => { if (p.paralelo) p.paralelo.forEach((t, ti) => t.forEach((q) => lista.push([...q, ti ? "B" : "A"]))); else lista.push(p); });
  const etapas = lista.filter((p) => !p[3] || p[3] === "A" || p[3] === "B" || ops[p[3]]);
  const total = etapas.reduce((s, p) => s + tempo(p[2]), 0);
  return (
    <Drawer open width={760} onClose={onClose} title="Nova OS a partir do orçamento" subtitle="o roteiro da família vira as etapas — ninguém redigita"
      footer={<div className="pcv-bar" style={{ justifyContent: "flex-end" }}><Button onClick={onClose}>Cancelar</Button><Button variant="primary" onClick={() => onCriada(o, etapas.map((p) => ({ setor: p[0], nome: p[1], prev: Math.round(tempo(p[2]) * 10) / 10, maq: typeof p[2] === "string" ? p[2] : null, trilha: p[3] === "A" || p[3] === "B" ? p[3] : null })), r)}>Criar OS com {etapas.length} etapas</Button></div>}>
      <div className="pcv-ficha">
        <div className="pcv-orc-l" role="radiogroup" aria-label="Orçamentos aprovados">
          {D.ORCAMENTOS.map((x) => <button key={x.id} role="radio" aria-checked={orc === x.id} onClick={() => { setOrc(x.id); setOps(x.opcoes); }}><b className="mono">{x.id}</b><span>{x.cliente} · {x.desc}</span><small>{x.qtd} {x.un} · {D.fmt(x.valor)} · entrega {D.d2(x.prazo)}</small></button>)}
        </div>
        {Alert && <Alert tone="info" title={"Roteiro: " + r.nome}>Família {r.familia}. Previsto {D.h1(total)} h de produção.</Alert>}
        {Object.keys(ops).length > 0 && <div className="pcv-sec"><span className="pcv-eyebrow">Opções do orçamento</span>
          {Object.keys(ops).map((k) => Switch && <Switch key={k} checked={ops[k]} onChange={(v) => setOps({ ...ops, [k]: v })} label={k} sublabel={ops[k] ? "a etapa entra na OS" : "a etapa fica de fora"} />)}</div>}
        <ol className="pcv-etapas">{etapas.map((p, i) => { const m = typeof p[2] === "string" ? D.maqDe(p[2]) : null; return (
          <li key={i} className="is-fila" style={{ "--c": D.setorDe(p[0]).cor }}><span className="pcv-et-n" aria-hidden="true">{i + 1}</span>
            <div className="pcv-et-m"><b>{p[1]}</b><small>{D.setorDe(p[0]).nome}{m ? " · " + m.nome : ""}{p[3] === "A" || p[3] === "B" ? " · trilha " + p[3] : p[3] ? " · " + p[3] : ""}</small></div>
            <div className="pcv-et-t mono">{D.h1(tempo(p[2]))} h</div></li>); })}</ol>
      </div>
    </Drawer>
  );
}

window.PcvOs = { FichaOs, Custo, Roteiros, NovaOs };
})();
