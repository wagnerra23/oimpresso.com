// fsm-regras.jsx — FSM com regras: cada etapa declara os efeitos que dispara ao entrar nela,
// e cada efeito tem parâmetros configuráveis (a configuração mora na etapa, não espalhada pela tela).
// Motor genérico por domínio (window.OiFsmRegras); o 1º domínio registrado é "compra".
// Expõe também CmpRecebimento — o passo Em trânsito → Recebido, que executa as regras da etapa.
(() => {
const { useState, useEffect, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const fmt = (n) => "R$ " + Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (n, d = 0) => Number(n || 0).toLocaleString("pt-BR", { minimumFractionDigits: d, maximumFractionDigits: d });
const pctS = (n) => (n > 0 ? "+" : n < 0 ? "−" : "") + Math.abs(n).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + "%";
const up10 = (v) => Math.ceil(v * 10 - 1e-9) / 10;

// ─── Opções dos parâmetros (as perguntas viram configuração da etapa) ───
const OPC = {
  etapa: { l: "Dispara na etapa", o: [["recebido", "Recebido — mercadoria entrou no estoque"], ["conferido", "Conferido — NF-e conferida"]] },
  metodo: { l: "Novo custo do insumo", o: [["medio", "Custo médio ponderado"], ["ultimo", "Último preço de compra"], ["por_insumo", "Definido em cada insumo"]] },
  preco: { l: "Preço de venda do produto", o: [["so_custo", "Só recalcula o custo"], ["propor", "Propõe novo preço — alguém aprova"], ["aplicar", "Aplica o novo preço sozinho"]] },
  regraEm: { l: "Quais produtos seguem", o: [["produto", "Política de preço do produto"], ["receita", "Marcação na receita"], ["insumo", "Marcação na linha do insumo"], ["categoria", "Categoria do produto"]] },
  aprovacao: { l: "Quem aprova e onde", o: [["recebimento", "No próprio recebimento"], ["fabricacao", "Fila de reajustes da Fabricação"], ["pendencias", "Pendências da Visão geral"]] },
  ops: { l: "Ordens de produção abertas", o: [["congela", "Mantêm o custo da abertura"], ["vivo", "Passam a usar o custo novo"]] },
};
const rot = (k, v) => ((OPC[k] && OPC[k].o.find((x) => x[0] === v)) || [v, v])[1];
// Quais parâmetros aparecem dependem dos outros (não mostrar escolha que não tem efeito).
const visivel = (k, p) => k === "aprovacao" ? p.preco === "propor" : k === "regraEm" ? p.preco !== "so_custo" : true;

const PADRAO = {
  compra: [
    { id: "pedido.email", etapa: "pedido", titulo: "Enviar o pedido ao fornecedor por e-mail", desc: "PDF do pedido com prazo e condição de pagamento.", ativo: true },
    { id: "recebido.estoque", etapa: "recebido", titulo: "Dar entrada no estoque", desc: "Soma a quantidade recebida ao saldo do local da compra.", ativo: true, trava: true },
    { id: "insumo.custo", etapa: "recebido", titulo: "Atualizar o custo dos insumos", desc: "Recalcula o custo de cada item comprado e propaga para os produtos fabricados que o consomem.",
      ativo: true, params: { etapa: "recebido", metodo: "medio", preco: "propor", regraEm: "produto", aprovacao: "recebimento", ops: "congela" } },
    { id: "conferido.pagar", etapa: "conferido", titulo: "Gerar conta a pagar no Financeiro", desc: "Um título por parcela da condição de pagamento.", ativo: true, params: { tolerancia: 2 } },
    { id: "pago.baixa", etapa: "pago", titulo: "Baixar o título e conciliar", desc: "Liquida no Financeiro e fecha a compra.", ativo: true },
  ],
};
const KEY = "oimpresso.fsm.regras.v1";
const lerSalvo = () => { try { return JSON.parse(localStorage.getItem(KEY) || "{}") || {}; } catch (e) { return {}; } };
function regrasDe(dom) {
  const s = lerSalvo()[dom] || {};
  return (PADRAO[dom] || []).map((e) => {
    const o = s[e.id] || {};
    const params = e.params ? { ...e.params, ...(o.params || {}) } : undefined;
    return { ...e, ativo: e.trava ? true : (o.ativo != null ? o.ativo : e.ativo), params, etapaEf: params && params.etapa ? params.etapa : e.etapa };
  });
}
function salvar(dom, id, patch) {
  const all = lerSalvo(); all[dom] = all[dom] || {};
  const cur = all[dom][id] || {};
  all[dom][id] = { ...cur, ...patch, params: { ...(cur.params || {}), ...(patch.params || {}) } };
  try { localStorage.setItem(KEY, JSON.stringify(all)); } catch (e) {}
  window.dispatchEvent(new Event("oi-fsm-regras"));
}
function restaurar(dom) {
  const all = lerSalvo(); delete all[dom];
  try { localStorage.setItem(KEY, JSON.stringify(all)); } catch (e) {}
  window.dispatchEvent(new Event("oi-fsm-regras"));
}
function useRegras(dom) {
  const [v, setV] = useState(() => regrasDe(dom));
  useEffect(() => { const h = () => setV(regrasDe(dom)); window.addEventListener("oi-fsm-regras", h); return () => window.removeEventListener("oi-fsm-regras", h); }, [dom]);
  return v;
}

// ─── Painel: regras por etapa ───
function Param({ k, value, onChange }) {
  const { Select } = DS();
  const op = OPC[k];
  if (!op) return null;
  if (Select) return <Select label={op.l} value={value} onChange={(e) => onChange(e.target.value)}>{op.o.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</Select>;
  return <label className="fsr-f"><span>{op.l}</span><select value={value} onChange={(e) => onChange(e.target.value)}>{op.o.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></label>;
}

function FsmRegrasPanel({ dom = "compra", stages, foco, onClose }) {
  const regras = useRegras(dom);
  const [et, setEt] = useState(foco || "recebido");
  const { Switch, Input } = DS();
  const daEtapa = regras.filter((r) => r.etapaEf === et);
  return (
    <>
      <div className="os-drawer-body drw-body fsr">
        <div className="fsr-intro">
          <b>Regras das etapas</b>
          <span>O que o sistema faz quando a compra entra em cada etapa. Vale para todas as compras da empresa.</span>
        </div>
        <div className="fsr-etapas" role="tablist" aria-label="Etapas da compra">
          {stages.map((s, i) => {
            const n = regras.filter((r) => r.etapaEf === s.id && r.ativo).length;
            return (
              <button key={s.id} role="tab" aria-selected={et === s.id} className={"fsr-etapa" + (et === s.id ? " on" : "")} onClick={() => setEt(s.id)}>
                <span className="fsr-etapa-n">{i + 1}</span>
                <span className="fsr-etapa-l">{s.l}</span>
                <span className="fsr-etapa-c">{n ? n + (n === 1 ? " regra" : " regras") : "—"}</span>
              </button>
            );
          })}
        </div>
        <div className="fsr-lista">
          {daEtapa.length === 0 && <div className="fsr-vazio">Nenhuma regra nesta etapa. A compra só muda de estado.</div>}
          {daEtapa.map((r) => (
            <div key={r.id} className={"fsr-regra" + (r.ativo ? "" : " off")}>
              <div className="fsr-regra-h">
                {Switch
                  ? <Switch checked={r.ativo} disabled={r.trava} onChange={(v) => salvar(dom, r.id, { ativo: v })} label={r.titulo} sublabel={r.trava ? r.desc + " Obrigatória." : r.desc} />
                  : <label><input type="checkbox" checked={r.ativo} disabled={r.trava} onChange={(e) => salvar(dom, r.id, { ativo: e.target.checked })} /> {r.titulo}</label>}
              </div>
              {r.ativo && r.params && (
                <div className="fsr-params">
                  {Object.keys(r.params).filter((k) => OPC[k] && visivel(k, r.params)).map((k) => (
                    <Param key={k} k={k} value={r.params[k]} onChange={(v) => salvar(dom, r.id, { params: { [k]: v } })} />
                  ))}
                  {"tolerancia" in r.params && (Input
                    ? <Input label="Tolerância de divergência (%)" type="number" value={r.params.tolerancia} onChange={(e) => salvar(dom, r.id, { params: { tolerancia: Number(e.target.value) } })} help="Acima disso a conferência pede justificativa." />
                    : null)}
                </div>
              )}
              {r.ativo && r.id === "insumo.custo" && <div className="fsr-resumo">{resumoCusto(r.params)}</div>}
            </div>
          ))}
        </div>
      </div>
      <footer className="os-drawer-actions">
        <button className="btn ghost" onClick={() => restaurar(dom)}>Restaurar padrão</button>
        <div style={{ flex: 1 }} />
        <button className="btn primary" onClick={onClose}>Concluir</button>
      </footer>
    </>
  );
}
function resumoCusto(p) {
  const a = "Ao entrar em " + rot("etapa", p.etapa).split(" —")[0] + ", o custo vira " + rot("metodo", p.metodo).toLowerCase() + ". ";
  const b = p.preco === "so_custo" ? "O preço de venda não muda; a margem é recalculada."
    : p.preco === "aplicar" ? "Produtos que acompanham o custo têm o preço trocado na hora."
    : "O novo preço fica proposto — " + rot("aprovacao", p.aprovacao).toLowerCase() + ".";
  return a + b + " OPs abertas: " + rot("ops", p.ops).toLowerCase() + ".";
}

// ─── Dados da propagação (mock): política de preço por produto ───
const POLITICAS = {
  "PROD-BAN-440": { tipo: "margem", alvo: 45 }, "PROD-ADE-REC": { tipo: "acompanha" }, "PROD-PLA-A3": { tipo: "fixo" },
  "PROD-BAN-BLK": { tipo: "margem", alvo: 50 }, "PROD-ACM-MOD": { tipo: "acompanha" }, "PROD-CAM-DTF": { tipo: "fixo" },
};
const POL_ROT = { fixo: "Preço fixo", acompanha: "Acompanha o custo", margem: "Margem alvo" };
const METODO_INSUMO = { "INS-014": "ultimo" };

function calcular(p, qtdRec, metodo) {
  const MFG = window.MFG;
  const insumos = (p.products || []).map((it, i) => {
    const ins = MFG && MFG.bySku(it.sku);
    const est = ins ? ins.est : 0, cAnt = ins ? ins.c : it.costBefore, q = Number(qtdRec[i] || 0);
    const m = metodo === "por_insumo" ? (METODO_INSUMO[it.sku] || "medio") : metodo;
    const cNovo = m === "ultimo" ? it.net : (est + q > 0 ? (est * cAnt + q * it.net) / (est + q) : it.net);
    return { ...it, est, cAnt, q, cNovo, m, var: cAnt ? (cNovo - cAnt) / cAnt * 100 : 0 };
  });
  const novoDe = {}; insumos.forEach((x) => { novoDe[x.sku] = x; });
  const produtos = !MFG ? [] : MFG.RECIPES.map((r) => {
    let delta = 0, usa = [];
    r.grupos.forEach((g) => g.itens.forEach((it) => { const x = novoDe[it.sku]; if (x && x.q > 0) { delta += it.q * MFG.multDe(it) * (x.cNovo - x.cAnt); usa.push(x.name); } }));
    if (!usa.length) return null;
    if (r.custoTipo === "percentual") delta *= 1 + Number(r.extra) / 100;
    const base = MFG.custos(r).total, cA = r.qtd ? base / r.qtd : 0, cN = r.qtd ? (base + delta) / r.qtd : 0;
    const pol = r.politica || POLITICAS[r.produto] || { tipo: "fixo" };
    const P = r.venda;
    const prop = pol.tipo === "margem" ? up10(cN / (1 - pol.alvo / 100)) : pol.tipo === "acompanha" ? up10(cA ? P * cN / cA : P) : P;
    return { r, usa, cA, cN, pol, P, prop, mgA: P ? (P - cA) / P * 100 : 0 };
  }).filter(Boolean);
  return { insumos, produtos };
}

// ─── Passo de recebimento (Em trânsito → Recebido) ───
function CmpRecebimento({ p, stages, onCancel, onDone, abrirRegras }) {
  const regras = useRegras("compra");
  const custoR = regras.find((r) => r.id === "insumo.custo");
  const custoAqui = custoR.ativo && custoR.params.etapa === "recebido";
  const [ov, setOv] = useState({});                     // ajuste só deste recebimento
  const par = { ...custoR.params, ...ov };
  const [qtdRec, setQtdRec] = useState(() => (p.products || []).map((x) => x.qty));
  const calc = useMemo(() => calcular(p, qtdRec, par.metodo), [p, qtdRec, par.metodo]);
  const [precos, setPrecos] = useState({});
  const [aprov, setAprov] = useState({});
  const propoe = par.preco === "propor", aplica = par.preco === "aplicar", aprovaAqui = propoe && par.aprovacao === "recebimento";
  const passos = ["Quantidades"].concat(custoAqui ? ["Custo dos insumos", "Preço dos produtos"] : []).concat(["Confirmar"]);
  const [i, setI] = useState(0);
  const passo = passos[i];
  const precoDe = (x) => precos[x.r.id] != null ? precos[x.r.id] : x.prop;
  const aprovado = (x) => aprov[x.r.id] != null ? aprov[x.r.id] : x.pol.tipo !== "fixo";
  const comMudanca = calc.produtos.filter((x) => x.pol.tipo !== "fixo" && Math.abs(x.prop - x.P) >= 0.01);
  const efeitos = regras.filter((r) => r.etapaEf === "recebido" && r.ativo);
  const { Alert, Segmented } = DS();
  const AlertX = ({ tone, title, children }) => Alert ? <Alert tone={tone} title={title}>{children}</Alert> : <div className="fsr-vazio"><b>{title}</b> {children}</div>;

  const Ajuste = ({ k }) => (
    <label className="fsr-ajuste">
      <span>{OPC[k].l}</span>
      <select value={par[k]} onChange={(e) => setOv({ ...ov, [k]: e.target.value })}>{OPC[k].o.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>
      {ov[k] && ov[k] !== custoR.params[k] && <em>só neste recebimento</em>}
    </label>
  );

  const confirmar = () => {
    const MFG = window.MFG;
    const tl = [{ t: "ok", by: "Wagner", at: "agora", title: "Em trânsito → Recebido", notes: calc.insumos.filter((x) => x.q > 0).length + " itens conferidos na entrada." }];
    calc.insumos.forEach((x) => { const ins = MFG && MFG.bySku(x.sku); if (ins && x.q > 0) { ins.est += x.q; if (custoAqui) ins.c = Math.round(x.cNovo * 10000) / 10000; } });
    tl.push({ t: "ok", by: "Regra", at: "agora", title: "Estoque: entrada em " + p.locName });
    if (custoAqui) {
      tl.push({ t: "ok", by: "Regra", at: "agora", title: "Custo atualizado (" + rot("metodo", par.metodo).toLowerCase() + ") em " + calc.insumos.length + " insumos" });
      const trocar = aplica ? comMudanca : aprovaAqui ? calc.produtos.filter((x) => x.pol.tipo !== "fixo" && aprovado(x)) : [];
      trocar.forEach((x) => { x.r.venda = Number(precoDe(x)); x.r.atualizado = "agora"; });
      if (trocar.length) tl.push({ t: "ok", by: "Wagner", at: "agora", title: "Preço de venda reajustado em " + trocar.length + (trocar.length === 1 ? " produto" : " produtos") });
      if (propoe && !aprovaAqui && comMudanca.length) tl.push({ t: "now", by: "Regra", at: "agora", title: comMudanca.length + " propostas de preço enviadas — " + rot("aprovacao", par.aprovacao).toLowerCase() });
    }
    p.stage = "recebido";
    p.timeline = tl.reverse().concat(p.timeline || []);
    onDone();
  };

  return (
    <>
      <div className="fsr-passos" aria-label="Passos do recebimento">
        {passos.map((n, k) => <span key={n} className={"fsr-passo" + (k < i ? " done" : k === i ? " now" : "")}><b>{k + 1}</b>{n}</span>)}
        <button className="btn sm ghost fsr-regras-btn" onClick={abrirRegras}>Regras da etapa</button>
      </div>
      <div className="os-drawer-body drw-body fsr">
        {passo === "Quantidades" && (
          <div className="sec">
            <h4>Quantidade que chegou</h4>
            <table className="items-tbl">
              <thead><tr><th scope="col">Item</th><th scope="col" className="num">Pedido</th><th scope="col" className="num">Recebido</th><th scope="col" className="num">Custo unit.</th></tr></thead>
              <tbody>{(p.products || []).map((it, k) => (
                <tr key={it.sku}>
                  <td><b>{it.name}</b><small>{it.sku}</small></td>
                  <td className="num">{num(it.qty)} <small>{it.unit}</small></td>
                  <td className="num"><input className="fsr-qtd" type="number" min="0" aria-label={"Recebido de " + it.name} value={qtdRec[k]} onChange={(e) => { const a = qtdRec.slice(); a[k] = e.target.value; setQtdRec(a); }} /></td>
                  <td className="num">{fmt(it.net)}</td>
                </tr>))}
              </tbody>
            </table>
            {!custoAqui && <AlertX tone="info" title="Custo não muda nesta etapa">{custoR.ativo ? "A regra de custo dispara em " + rot("etapa", custoR.params.etapa).split(" —")[0] + "." : "A regra de custo está desligada."}</AlertX>}
          </div>
        )}

        {passo === "Custo dos insumos" && (
          <div className="sec">
            <div className="fsr-ajustes"><Ajuste k="metodo" /></div>
            <table className="items-tbl">
              <thead><tr><th scope="col">Insumo</th><th scope="col" className="num">Estoque</th><th scope="col" className="num">Custo atual</th><th scope="col" className="num">Entra</th><th scope="col" className="num">Novo custo</th></tr></thead>
              <tbody>{calc.insumos.map((x) => (
                <tr key={x.sku}>
                  <td><b>{x.name}</b><small>{x.m === "ultimo" ? "último preço" : "custo médio"}</small></td>
                  <td className="num">{num(x.est)} <small>{x.unit}</small></td>
                  <td className="num">{fmt(x.cAnt)}</td>
                  <td className="num">{num(x.q)} × {fmt(x.net)}</td>
                  <td className="num"><b>{fmt(x.cNovo)}</b><small className={x.var > 0.05 ? "fsr-sobe" : x.var < -0.05 ? "fsr-desce" : ""}>{pctS(x.var)}</small></td>
                </tr>))}
              </tbody>
            </table>
            <p className="fsr-nota">{calc.produtos.length ? calc.produtos.length + (calc.produtos.length === 1 ? " produto fabricado consome" : " produtos fabricados consomem") + " esses insumos." : "Nenhum produto fabricado consome esses insumos."} OPs abertas {par.ops === "congela" ? "mantêm o custo da abertura" : "passam a usar o custo novo"}.</p>
          </div>
        )}

        {passo === "Preço dos produtos" && (
          <div className="sec">
            <div className="fsr-ajustes"><Ajuste k="preco" />{propoe && <Ajuste k="aprovacao" />}</div>
            {propoe && !aprovaAqui && comMudanca.length > 0 && <AlertX tone="info" title={comMudanca.length + " propostas vão para aprovação"}>{rot("aprovacao", par.aprovacao)}. O preço atual vale até alguém aprovar.</AlertX>}
            {calc.produtos.length === 0 ? <div className="fsr-vazio">Nenhum produto fabricado é afetado.</div> : (
              <table className="items-tbl fsr-precos">
                <thead><tr>{aprovaAqui && <th scope="col"><span className="sr-only">Aprovar</span></th>}<th scope="col">Produto</th><th scope="col" className="num">Custo unit.</th><th scope="col" className="num">Preço atual</th><th scope="col" className="num">{par.preco === "so_custo" ? "Margem" : "Novo preço"}</th></tr></thead>
                <tbody>{calc.produtos.map((x) => {
                  const fixo = x.pol.tipo === "fixo", novo = par.preco === "so_custo" || fixo ? x.P : Number(precoDe(x));
                  const mg = novo ? (novo - x.cN) / novo * 100 : 0;
                  return (
                    <tr key={x.r.id} className={aprovaAqui && !fixo && !aprovado(x) ? "fsr-recusado" : ""}>
                      {aprovaAqui && <td>{!fixo && <input type="checkbox" aria-label={"Aprovar preço de " + x.r.name} checked={aprovado(x)} onChange={(e) => setAprov({ ...aprov, [x.r.id]: e.target.checked })} />}</td>}
                      <td><b>{x.r.name}</b><small><span className={"fsr-pol " + x.pol.tipo}>{POL_ROT[x.pol.tipo]}{x.pol.alvo ? " " + x.pol.alvo + "%" : ""}</span> · {x.usa.join(", ")}</small></td>
                      <td className="num">{fmt(x.cN)}<small>era {fmt(x.cA)}</small></td>
                      <td className="num">{fmt(x.P)}<small>margem {num(x.mgA, 1)}%</small></td>
                      <td className="num">
                        {par.preco === "so_custo" || fixo
                          ? <><b>{fmt(x.P)}</b><small className={mg < x.mgA - 0.05 ? "fsr-sobe" : ""}>margem {num(mg, 1)}%</small></>
                          : aprovaAqui
                            ? <><input className="fsr-qtd" type="number" step="0.10" min="0" aria-label={"Novo preço de " + x.r.name} value={precoDe(x)} onChange={(e) => setPrecos({ ...precos, [x.r.id]: e.target.value })} /><small>margem {num(mg, 1)}%</small></>
                            : <><b>{fmt(novo)}</b><small>margem {num(mg, 1)}%</small></>}
                      </td>
                    </tr>);
                })}</tbody>
              </table>
            )}
            <p className="fsr-nota">A política de preço vem do cadastro de cada produto. Preço fixo nunca muda sozinho — só a margem.</p>
          </div>
        )}

        {passo === "Confirmar" && (
          <div className="sec">
            <h4>Ao confirmar, a compra entra em Recebido e dispara</h4>
            <ol className="fsr-efeitos">
              {efeitos.map((r) => (
                <li key={r.id}><b>{r.titulo}</b><span>{r.id === "insumo.custo" ? resumoCusto(par) : r.desc}</span></li>
              ))}
            </ol>
            {Object.keys(ov).some((k) => ov[k] !== custoR.params[k]) && <AlertX tone="warn" title="Ajuste só deste recebimento">A regra da etapa continua a mesma para as próximas compras.</AlertX>}
          </div>
        )}
      </div>
      <footer className="os-drawer-actions">
        <button className="btn ghost" onClick={i === 0 ? onCancel : () => setI(i - 1)}>{i === 0 ? "Cancelar" : "← Voltar"}</button>
        <div style={{ flex: 1 }} />
        {i < passos.length - 1
          ? <button className="btn primary" onClick={() => setI(i + 1)}>{passos[i + 1]} →</button>
          : <button className="btn primary" onClick={confirmar}>Confirmar recebimento</button>}
      </footer>
    </>
  );
}

window.OiFsmRegras = { PADRAO, OPC, regrasDe, salvar, restaurar, useRegras, FsmRegrasPanel, CmpRecebimento, POLITICAS };
})();
