// hrm-extras.jsx — HRM: visões Folha · Metas · Configurações (Presença e Turnos → Ponto).
// Primitivos e pontes do DS vivem em hrm-ui.jsx; formulários em hrm-forms.jsx.
// Ondas O1 (mecânica) · O2 (formulários) · O3 (espelho mensal, custo, cobertura) · O4 (DS vivo).
// Expõe window.HrmExtras.
(() => {
const { useState, useMemo, useRef } = React;
const H = window.HRM;
const U = window.HrmUI;
const F = window.HrmForms;
const { Badge, Card, Row, Seg, Nota, Kpis, Busca, Drawer, Sec, KV, Campo, Escolha, Periodo, Grafico, Tabela, Paginacao, Bulk, Skel, Vazio, Aviso, useAmbiente, SemPermissao, usePagina, useAviso, useAtalhos, useCarga, usePersist } = U;
const ds = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};


// Presença e Turnos saíram do HRM: a jornada e a escala são do Ponto (D1 2026-09-05 · D4 2026-09-29).

// ═══════════════════════ FOLHA DE PAGAMENTO ═══════════════════════
function Folha() {
  const A = useAmbiente();
  const [sub, setSub] = useState(A.pode("gerir_folha") ? "lotes" : "contra");
  const [lotes, setLotes] = usePersist("lotes", A.dados.lotes);
  const [folha, setFolha] = usePersist("folha", A.pode("gerir_folha") ? A.dados.folha : A.dados.folha.filter((f) => f.emp === A.eu));
  const [sel, setSel] = useState(null);
  const [gerar, setGerar] = useState(false);
  const [pagar, setPagar] = useState(null);
  const [aviso, setAviso] = useAviso();
  const carregando = useCarga();
  const ST_LOTE = { draft:{ l:"Rascunho", t:"muted" }, final:{ l:"Fechada", t:"accent" } };

  const aPagar = folha.filter((f) => f.pagamento !== "paid").reduce((s, f) => s + H.totalFolha(f), 0);
  const bruto = folha.filter((f) => f.mes === "08/2026").reduce((s, f) => s + H.totalFolha(f), 0);
  const serie = useMemo(() => ["07/2026", "08/2026"].map((m) => ({ label:m, value:folha.filter((f) => f.mes === m).reduce((s, f) => s + H.totalFolha(f), 0) })), [folha]);
  const pg = usePagina(folha, 10);

  const gerarLote = ({ comp, local, quem }) => {
    const id = Math.max(...lotes.map((l) => l.id)) + 1;
    const novos = quem.map((emp, i) => {
      const e = H.emp(emp);
      return { id:900 + i, ref:`${H.CFG.payroll_ref_no_prefix}${String(20 + i).padStart(4, "0")}`, emp, mes:comp, lote:id, base:e.salario, ganhos:[["Vale-refeição", 660]], deducoes:e.cargo.includes("Estag") ? [] : [["Vale-transporte", Math.round(e.salario * 0.06)]], pagamento:"due", horas:0, faltas:0 };
    });
    setLotes((ls) => [{ id, nome:`Folha ${comp} — ${local}`, mes:comp, local, status:"draft", pagamento:"due", bruto:novos.reduce((s, f) => s + H.totalFolha(f), 0), criadoPor:"Eliana Pereira", criadoEm:"21/08/2026", itens:novos.length }, ...ls]);
    setFolha((fs) => [...novos, ...fs]);
    setGerar(false);
    setAviso(H.plural(novos.length, `1 contracheque gerado em rascunho para ${comp}.`, `{n} contracheques gerados em rascunho para ${comp}.`));
  };
  const fecharLote = (l) => {
    if (!window.confirm(`Fechar a folha ${l.mes}?\nDepois de fechada não dá para excluir o lote — só lançar pagamento. Cada colaborador recebe a notificação.`)) return;
    setLotes((ls) => ls.map((x) => x.id === l.id ? { ...x, status:"final" } : x));
    setAviso(H.plural(l.itens, `Folha ${l.mes} fechada — 1 colaborador notificado.`, `Folha ${l.mes} fechada — {n} colaboradores notificados.`));
  };
  const excluirLote = (l) => {
    if (l.status !== "draft") return;
    if (!window.confirm(`Excluir o lote ${l.nome}?\nOs ${l.itens} contracheques em rascunho são apagados.`)) return;
    setLotes((ls) => ls.filter((x) => x.id !== l.id));
    setFolha((fs) => fs.filter((f) => f.lote !== l.id));
    setAviso("Lote em rascunho excluído.");
  };
  const lancar = ({ lote, parcial }) => {
    setFolha((fs) => fs.map((f) => f.lote === lote.id ? { ...f, pagamento:parcial ? "partial" : "paid" } : f));
    setLotes((ls) => ls.map((x) => x.id === lote.id ? { ...x, pagamento:parcial ? "partial" : "paid" } : x));
    setPagar(null);
    setAviso(parcial ? "Pagamento parcial lançado — o lote fica parcial até fechar todos." : "Pagamento lançado; lote pago.");
  };

  return (
    <>
      <Kpis items={A.pode("gerir_folha") ? [
        { l:"Folha 08/2026", v:A.din(bruto), sub:`${folha.filter((f) => f.mes === "08/2026").length} contracheques`, tone:"info" },
        { l:"A pagar", v:A.din(aPagar), sub:"sem pagamento lançado", tone:"warning" },
        { l:"Lotes fechados", v:lotes.filter((l) => l.status === "final").length, sub:lotes.length ? "07/2026 matriz e filial" : "nenhum lote ainda" },
        { l:"Ganhos e deduções ativos", v:A.dados.gd.length, sub:A.dados.gd.length ? "3 ganhos · 2 deduções" : "nada cadastrado" },
      ] : [
        { l:"Meus contracheques", v:folha.length, sub:"últimas competências", tone:"info" },
        { l:"Último líquido", v:folha.length ? A.din(H.totalFolha(folha[0])) : "—", sub:folha.length ? `competência ${folha[0].mes}` : "nada lançado" },
      ]}/>
      <Seg value={sub} onChange={setSub} options={A.pode("gerir_folha")
        ? [{ id:"lotes", label:"Lotes" }, { id:"contra", label:"Contracheques" }, { id:"gd", label:"Ganhos e deduções" }, { id:"custo", label:"Custo" }]
        : [{ id:"contra", label:"Meus contracheques" }]}/>

      {sub === "lotes" && A.pode("gerir_folha") && <>
        <div className="hrm-toolbar"><span className="usr-count">{lotes.length} lotes</span><span className="hrm-spacer"></span><button className="os-btn primary" disabled={A.demo} onClick={() => setGerar(true)}>Gerar folha do mês</button></div>
        {carregando ? <Skel n={4}/> : lotes.length ? <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th scope="col">Lote</th><th scope="col">Competência</th><th scope="col">Local</th><th scope="col">Situação</th><th scope="col">Pagamento</th><th scope="col" className="hrm-num">Bruto</th><th scope="col" className="hrm-num">Itens</th><th scope="col"></th></tr></thead>
          <tbody>{lotes.map((l) => (
            <tr key={l.id}>
              <td><div className="hrm-name">{l.nome}</div><div className="hrm-meta">{l.criadoEm} · {l.criadoPor}</div></td>
              <td className="hrm-mono">{l.mes}</td><td>{l.local}</td>
              <td><Badge tone={ST_LOTE[l.status].t}>{ST_LOTE[l.status].l}</Badge></td>
              <td><Badge tone={H.ST_PAG[l.pagamento].t}>{H.ST_PAG[l.pagamento].l}</Badge></td>
              <td className="hrm-num">{A.din(l.bruto)}</td><td className="hrm-num">{l.itens}</td>
              <td style={{ textAlign:"right" }}>
                <span className="hrm-acoes">
                  {l.status === "draft" && <button className="os-btn ghost" disabled={A.demo} onClick={() => fecharLote(l)}>Fechar</button>}
                  {l.pagamento !== "paid" && l.status === "final" && <button className="os-btn ghost" disabled={A.demo} onClick={() => setPagar(l)}>Pagar</button>}
                  {l.status === "draft" && <button className="os-btn ghost" disabled={A.demo} onClick={() => excluirLote(l)}>Excluir</button>}
                </span>
              </td>
            </tr>))}</tbody>
        </table></div>
        : <Vazio variante="first" titulo="Nenhuma folha gerada" desc="A folha nasce por competência e localidade: escolha o mês e quem entra, o sistema soma salário, comissões e ganhos recorrentes. Sem encargo — é folha gerencial." acao={<button className="os-btn primary" disabled={A.demo} onClick={() => setGerar(true)}>Gerar a primeira folha</button>}/>}
        <p className="hrm-card-sub" style={{ marginTop:10 }}>Só lote em <b>rascunho</b> pode ser excluído — depois de fechado, o caminho é lançar pagamento. Fechar com “notificar” avisa cada colaborador por e-mail.</p>
      </>}

      {sub === "contra" && (folha.length ? <>
        <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th scope="col">Ref.</th><th scope="col">Colaborador</th><th scope="col">Competência</th><th scope="col" className="hrm-num">Base</th><th scope="col" className="hrm-num">Ganhos</th><th scope="col" className="hrm-num">Deduções</th><th scope="col" className="hrm-num">Líquido</th><th scope="col">Pagamento</th></tr></thead>
          <tbody>{pg.fatia.map((f) => { const e = H.emp(f.emp); const g = f.ganhos.reduce((s, x) => s + x[1], 0); const d = f.deducoes.reduce((s, x) => s + x[1], 0); return (
            <tr key={f.id} onClick={() => setSel(f)} style={{ cursor:"pointer" }}>
              <td className="hrm-mono">{f.ref}</td>
              <td><div className="hrm-name">{e.nome}</div><div className="hrm-meta">{e.cargo} · {e.setor}</div></td>
              <td className="hrm-mono">{f.mes}</td>
              <td className="hrm-num">{A.din(f.base)}</td>
              <td className="hrm-num hrm-pos">{g ? "+" + A.din(g) : "—"}</td>
              <td className="hrm-num hrm-neg">{d ? "−" + A.din(d) : "—"}</td>
              <td className="hrm-num"><b>{A.din(H.totalFolha(f))}</b></td>
              <td><Badge tone={H.ST_PAG[f.pagamento].t}>{H.ST_PAG[f.pagamento].l}</Badge></td>
            </tr>); })}</tbody>
        </table></div>
        <Paginacao pagina={pg.pagina} paginas={pg.paginas} onMudar={pg.setPagina} total={pg.total} porPagina={pg.porPagina}/>
        <p className="hrm-card-sub" style={{ marginTop:10 }}>Comissão de venda e comissão de meta entram como <b>ganho calculado</b> na geração (percentual do colaborador × faturado, e a faixa de meta atingida). Depois disso, nada é recalculado.</p>
      </> : <Vazio variante="first" titulo="Nenhum contracheque" desc={A.pode("gerir_folha") ? "Gere a folha do mês para ver os contracheques aqui." : "Quando o RH fechar a folha do mês, o seu contracheque aparece nesta lista e você recebe um e-mail."}/>)}

      {sub === "gd" && <>
        <div className="hrm-toolbar"><span className="usr-count">{A.dados.gd.length} lançamentos recorrentes</span><span className="hrm-spacer"></span><button className="os-btn primary" disabled={A.demo || !A.pode("gerir_folha")}>Novo lançamento</button></div>
        {A.dados.gd.length ? <div className="os-table-wrap"><table className="os-table">
          <thead><tr><th scope="col">Descrição</th><th scope="col">Natureza</th><th scope="col">Forma</th><th scope="col" className="hrm-num">Valor</th><th scope="col">Aplica em</th></tr></thead>
          <tbody>{A.dados.gd.map((g) => (
            <tr key={g.id}>
              <td className="hrm-name">{g.desc}</td>
              <td>{g.tipo === "allowance" ? <Badge tone="ok">Ganho</Badge> : <Badge tone="danger">Dedução</Badge>}</td>
              <td>{g.forma === "fixed" ? "Valor fixo" : "Percentual do salário"}</td>
              <td className="hrm-num">{g.forma === "fixed" ? A.din(g.valor) : g.valor.toLocaleString("pt-BR") + "%"}</td>
              <td>{g.aplicaEm}</td>
            </tr>))}</tbody>
        </table></div>
        : <Vazio variante="first" titulo="Nenhum ganho ou dedução recorrente" desc="São os lançamentos que entram sozinhos em toda folha — vale-refeição, vale-transporte, insalubridade, adiantamento. Valor fixo ou percentual do salário."/>}
      </>}

      {sub === "custo" && A.pode("gerir_folha") && (() => {
        const porSetor = {};
        folha.filter((f) => f.mes === "08/2026").forEach((f) => { const s = H.emp(f.emp).setor; porSetor[s] = (porSetor[s] || 0) + H.totalFolha(f); });
        const setores = Object.entries(porSetor).map(([label, value]) => ({ label, value })).sort((a, b) => b.value - a.value);
        const ganhos = {};
        folha.filter((f) => f.mes === "08/2026").forEach((f) => f.ganhos.forEach(([k, v]) => { ganhos[k] = (ganhos[k] || 0) + v; }));
        return (
          <div className="hrm-grid">
            <Card title="Folha por competência" sub="Líquido dos contracheques, sem encargos.">
              <Grafico tipo="bar" dados={serie} altura={130} formata={(v) => A.din(v)} destacaUltimo/>
              <div className="hrm-list" style={{ marginTop:8 }}>{serie.map((s) => <Row key={s.label} t={s.label} v={A.din(s.value)}/>)}</div>
            </Card>
            <Card title="Custo por setor" aside="08/2026">
              <Grafico tipo="bar" dados={setores} altura={130} formata={(v) => A.din(v)}/>
              <div className="hrm-list" style={{ marginTop:8 }}>{setores.map((s) => <Row key={s.label} t={s.label} v={A.din(s.value)}/>)}</div>
            </Card>
            <Card title="Composição dos ganhos" aside="08/2026" sub="Onde o valor acima de salário base é gerado.">
              <div className="hrm-list">{Object.entries(ganhos).sort((a, b) => b[1] - a[1]).map(([k, v]) => <Row key={k} t={k} v={A.din(v)}/>)}</div>
            </Card>
          </div>);
      })()}

      {gerar && <F.FormFolha onClose={() => setGerar(false)} onGerar={gerarLote}/>}
      {pagar && <F.FormPagamento lote={pagar} onClose={() => setPagar(null)} onPagar={lancar}/>}

      {sel && (() => { const e = H.emp(sel.emp); return (
        <Drawer title={`${sel.ref} · ${e.nome}`} sub={`Competência ${sel.mes} · ${e.cargo}`} onClose={() => setSel(null)}
          footer={<><button className="os-btn ghost" onClick={() => setSel(null)}>Fechar</button>{sel.pagamento !== "paid" && A.pode("gerir_folha") && <button className="os-btn primary" disabled={A.demo} onClick={() => { const l = lotes.find((x) => x.id === sel.lote); setSel(null); if (l) setPagar(l); }}>Lançar pagamento</button>}</>}>
          <Sec title="Composição">
            <div className="hrm-list">
              <Row t="Salário base" s={sel.base ? "por mês" : ""} v={A.din(sel.base)}/>
              {sel.ganhos.map((g) => <Row key={g[0]} t={g[0]} s="ganho" v={"+ " + A.din(g[1])}/>)}
              {sel.deducoes.map((d) => <Row key={d[0]} t={d[0]} s="dedução" v={"− " + A.din(d[1])}/>)}
              <Row t="Líquido" v={A.din(H.totalFolha(sel))}/>
            </div>
          </Sec>
          <Sec title="Apuração do mês">
            <KV pairs={[["Horas apuradas", "— vêm do Ponto (integração da folha pendente)"], ["Dias de licença", H.plural(sel.faltas, "1 dia", "{n} dias")], ["Local de trabalho", e.local], ["Situação do pagamento", H.ST_PAG[sel.pagamento].l]]}/>
          </Sec>
          <Sec title="O que a folha NÃO faz aqui">
            <p className="hrm-achado-d">Sem INSS, IRRF, FGTS, 13º ou férias proporcionais: o módulo soma ganhos, subtrai deduções e grava uma despesa. Encargos e guias continuam fora do sistema.</p>
          </Sec>
        </Drawer>); })()}

      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ METAS DE VENDA ═══════════════════════
function Metas() {
  // Puxado do vivo (resources/js/Pages/Essentials/Metas.tsx, #6869): a tela CADASTRA a meta, não apura.
  // Realizado do mês e comissão em R$ ficam fora por caminho de VALOR (Metas.charter.md:53) — o Painel
  // exclui pela mesma razão (DashboardController::hrmDashboard). Quem apura é a folha. Decisão [W] 2026-09-29.
  const A = useAmbiente();
  const [metas, setMetas] = usePersist("metas", A.dados.metas);
  const [form, setForm] = useState(null);
  const [aviso, setAviso] = useAviso();
  const salvar = (id, faixas) => {
    setMetas((m) => ({ ...m, [id]:faixas }));
    setForm(null);
    setAviso(faixas.length
      ? H.plural(faixas.length, "1 faixa salva — o conjunto anterior foi substituído.", "{n} faixas salvas — o conjunto anterior foi substituído.")
      : "Todas as faixas removidas: comissão de meta zerada.");
  };
  const podeGerir = A.pode("gerir_meta") && !A.demo;
  const linhas = A.pode("ver_todos") ? H.EMP : H.EMP.filter((e) => e.id === A.eu);
  const semImposto = H.CFG.calculate_sales_target_commission_without_tax;
  const pctTxt = (fs) => { const p = fs.map((f) => f.pct); const mn = Math.min(...p), mx = Math.max(...p); return mn === mx ? `${mn}%` : `${mn}% – ${mx}%`; };

  return (
    <>
      <Nota tone="info" title="Esta tela cadastra a meta — não apura o resultado">
        Quanto cada colaborador vendeu no mês, e quanto isso vira de comissão, não é calculado aqui: quem apura é a folha de pagamento. A base configurada no módulo é <b>{semImposto ? "sem imposto" : "com imposto"}</b> — o valor vendido entra {semImposto ? "sem" : "com"} tributo quando a folha faz essa conta.
      </Nota>
      <Kpis items={[
        { l:"Com meta cadastrada", v:linhas.filter((e) => (metas[e.id] || []).length).length, sub:H.plural(linhas.length, "de 1 colaborador", "de {n} colaboradores"), tone:"info" },
        { l:"Sem meta", v:linhas.filter((e) => !(metas[e.id] || []).length).length, sub:"sem faixa, a comissão de meta é zero" },
        { l:"Base do cálculo", v:semImposto ? "Sem imposto" : "Com imposto", sub:"configuração do módulo" },
      ]}/>
      <div className="os-table-wrap"><table className="os-table">
        <caption className="sr-only">Colaboradores e as faixas de meta de venda cadastradas</caption>
        <thead><tr><th scope="col">Colaborador</th><th scope="col" className="hrm-num">Faixas</th><th scope="col" className="hrm-num">Meta inicial</th><th scope="col" className="hrm-num">Meta final</th><th scope="col" className="hrm-num">Comissão</th><th scope="col">Situação</th><th scope="col"><span className="sr-only">Ações</span></th></tr></thead>
        <tbody>{linhas.map((e) => {
          const fs = metas[e.id] || [];
          return (
            <tr key={e.id}>
              <td><div className="hrm-name">{e.nome}</div><div className="hrm-meta">{e.cargo}</div></td>
              <td className="hrm-num">{fs.length || "—"}</td>
              <td className="hrm-num">{fs.length ? A.din(Math.min(...fs.map((f) => f.ini))) : "—"}</td>
              <td className="hrm-num">{fs.length ? A.din(Math.max(...fs.map((f) => f.fim))) : "—"}</td>
              <td className="hrm-num">{fs.length ? pctTxt(fs) : "—"}</td>
              <td>{fs.length ? <Badge tone="accent">com meta</Badge> : <Badge>sem meta</Badge>}</td>
              <td style={{ textAlign:"right" }}><button className="os-btn ghost" disabled={!podeGerir} title={podeGerir ? null : "Só o administrador define meta"} onClick={() => setForm(e)} aria-label={`${fs.length ? "Editar faixas" : "Definir meta"} de ${e.nome}`}>{fs.length ? "Editar faixas" : "Definir meta"}</button></td>
            </tr>);
        })}</tbody>
      </table></div>
      <p className="hrm-card-sub" style={{ marginTop:10 }}>As faixas não podem se sobrepor, nem encostar ponta com ponta — o servidor recusa e diz o motivo.</p>

      {form && <F.FormMeta emp={form} faixas={metas[form.id] || []} onClose={() => setForm(null)} onSalvar={salvar}/>}
      <Aviso msg={aviso} tone="ok"/>
    </>
  );
}

// ═══════════════════════ CONFIGURAÇÕES ═══════════════════════
function Config() {
  const A = useAmbiente();
  const [c, setC] = useState(H.CFG);
  const [aviso, setAviso] = useAviso();
  const set = (k, v) => setC((s) => ({ ...s, [k]:v }));
  const { Switch } = ds();
  const sujo = JSON.stringify(c) !== JSON.stringify(H.CFG);
  const flag = (k, label, sub) => Switch
    ? <Switch key={k} checked={c[k]} onChange={() => set(k, !c[k])} label={label} sublabel={sub} />
    : <label key={k} className="hrm-flag"><input type="checkbox" checked={c[k]} onChange={() => set(k, !c[k])}/> <span><b>{label}</b><i>{sub}</i></span></label>;

  return (
    <>
      {!A.pode("config") ? <SemPermissao frase="As configurações do módulo são de administrador — o próprio controller recusa quem não é."/> : <>
      <Nota tone="info" title="Esta tela já é Inertia no main">
        <code>EssentialsSettingsController</code> renderiza <code>Essentials/Settings/Index</code> e grava em <code>businesses.essentials_settings</code> (JSON). Só administrador vê e edita — é a única tela do HRM que não é mais Blade.
      </Nota>
      <div className="hrm-grid">
        <Card title="Licenças">
          <div className="hrm-campos">
            <Campo label="Prefixo do número de referência" valor={c.leave_ref_no_prefix} onChange={(v) => set("leave_ref_no_prefix", v)} help="máx. 32 caracteres"/>
            <U.Texto label="Instruções ao colaborador" valor={c.leave_instructions} onChange={(v) => set("leave_instructions", v)} help="aparece no formulário de pedido de licença"/>
          </div>
        </Card>
        <Card title="Folha e tarefas">
          <div className="hrm-campos">
            <Campo label="Prefixo da folha" valor={c.payroll_ref_no_prefix} onChange={(v) => set("payroll_ref_no_prefix", v)}/>
            <Campo label="Prefixo das tarefas" valor={c.essentials_todos_prefix} onChange={(v) => set("essentials_todos_prefix", v)} help="usado ao criar tarefa do Essentials"/>
          </div>
        </Card>
        <Card title="Regras">
          <div className="hrm-cfg-flags">
            {flag("calculate_sales_target_commission_without_tax", "Apurar comissão de meta sem imposto", "O vendido entra sem tributo no cálculo da faixa")}
          </div>
          <p className="hrm-card-sub" style={{ marginTop:12 }}>Tolerância de marcação e localização obrigatória <b>saíram daqui</b>: a jornada é do Ponto, e lá a lei fixa as duas (CLT Art. 58 §1º · REP-P, Portaria 671).</p>
        </Card>
      </div>
      <div className="hrm-cfg-acoes">
        <button className="os-btn primary" disabled={!sujo || A.demo} onClick={() => setAviso("Configurações salvas (protótipo — nada gravado no banco).")}>Salvar configurações</button>
        <button className="os-btn ghost" disabled={!sujo} onClick={() => setC(H.CFG)}>Descartar alterações</button>
        {sujo && <span className="hrm-meta">alterações não salvas</span>}
      </div>
      <Aviso msg={aviso} tone="ok"/>
      </>}
    </>
  );
}

window.HrmExtras = { Folha, Metas, Config };
})();
