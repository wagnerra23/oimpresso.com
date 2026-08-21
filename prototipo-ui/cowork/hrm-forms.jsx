// hrm-forms.jsx — HRM: os formulários que o Blade tem e a tela não tinha (onda HRM-O2).
// Cada form valida no cliente o que o servidor NÃO valida hoje (achados A2/A3/A5) e
// diz isso na tela — a guarda é do protótipo, não do backend.
// Expõe window.HrmForms. Carrega depois de hrm-ui.jsx.
(() => {
const { useState, useMemo } = React;
const H = window.HRM;
const { Drawer, Sec, Campo, Escolha, Texto, Data, Nota, Badge, Row } = window.HrmUI;

const hoje = "2026-08-21";
const num = (v) => Number(String(v).replace(/\./g, "").replace(",", ".")) || 0;

// ── Licença ──
function FormLicenca({ onClose, onSalvar }) {
  const [tipo, setTipo] = useState("");
  const [quem, setQuem] = useState(["e-1"]);
  const [ini, setIni] = useState(hoje);
  const [fim, setFim] = useState(hoje);
  const [motivo, setMotivo] = useState("");
  const [erros, setErros] = useState({});

  const t = H.TIPOS.find((x) => String(x.id) === tipo);
  const dias = ini && fim ? H.dias(ini, fim) : 0;
  const estoura = t && t.max && t.usadas + dias > t.max;

  const validar = () => {
    const e = {};
    if (!tipo) e.tipo = "Escolha o tipo de licença.";
    if (!quem.length) e.quem = "Escolha pelo menos um colaborador.";
    if (!ini) e.ini = "Informe o início.";
    if (!fim) e.fim = "Informe o fim.";
    if (ini && fim && fim < ini) e.fim = "O fim não pode ser antes do início.";
    if (!motivo.trim()) e.motivo = "Escreva o motivo — é o que o aprovador lê.";
    setErros(e);
    return !Object.keys(e).length;
  };
  const salvar = () => { if (validar()) onSalvar({ tipo:Number(tipo), quem, ini, fim, motivo }); };

  return (
    <Drawer title="Pedir licença" sub={`${H.plural(quem.length, "1 colaborador", "{n} colaboradores")} · ${H.plural(dias || 0, "1 dia", "{n} dias")}`} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button><button className="os-btn primary" onClick={salvar}>Registrar pedido</button></>}>
      <Sec title="Instruções da empresa">
        <p className="hrm-achado-d">{H.CFG.leave_instructions}</p>
      </Sec>
      <Sec title="Pedido">
        <div className="hrm-campos">
          <Escolha label="Tipo de licença" valor={tipo} erro={erros.tipo} onChange={setTipo}
            opcoes={[{ v:"", l:"Escolha…" }, ...H.TIPOS.map((x) => ({ v:String(x.id), l:`${x.nome}${x.max ? ` · limite ${x.max} d/${x.intervalo === "year" ? "ano" : "mês"}` : ""}` }))]}/>
          <Data label="Início" valor={ini} erro={erros.ini} onChange={setIni}/>
          <Data label="Fim" valor={fim} erro={erros.fim} onChange={setFim}/>
          <Texto label="Motivo" valor={motivo} onChange={setMotivo} help={erros.motivo || "Vai junto na notificação ao administrador"}/>
        </div>
        {erros.motivo && <p className="hrm-erro">{erros.motivo}</p>}
      </Sec>
      <Sec title="Para quem">
        {erros.quem && <p className="hrm-erro">{erros.quem}</p>}
        <div className="hrm-list">
          {H.EMP.map((e) => {
            const on = quem.includes(e.id);
            return (
              <label className="hrm-row hrm-pick" key={e.id}>
                <span className="hrm-row-l">
                  <span className="hrm-row-t"><input type="checkbox" checked={on} onChange={() => setQuem((q) => on ? q.filter((x) => x !== e.id) : [...q, e.id])}/> {e.nome}</span>
                  <span className="hrm-row-s">{e.cargo} · {e.setor}</span>
                </span>
              </label>);
          })}
        </div>
        <p className="hrm-achado-d">Pedir para outra pessoa exige a permissão de “todas as licenças”; sem ela, o pedido sai sempre no próprio nome.</p>
      </Sec>
      {estoura && <Nota tone="warn" title="Passa do limite do tipo">
        {t.nome} tem limite de {t.max} dias por {t.intervalo === "year" ? "ano" : "mês"} e já há {t.usadas} usados — este pedido soma {dias}. <b>O servidor não bloqueia isso hoje</b> (achado A3): a guarda é desta tela.
      </Nota>}
    </Drawer>
  );
}

// ── Feriado ──
function FormFeriado({ item, onClose, onSalvar }) {
  const [nome, setNome] = useState(item?.nome || "");
  const [ini, setIni] = useState(item?.ini || hoje);
  const [fim, setFim] = useState(item?.fim || hoje);
  const [local, setLocal] = useState(item?.local || "");
  const [nota, setNota] = useState(item?.nota || "");
  const [erros, setErros] = useState({});
  const salvar = () => {
    const e = {};
    if (!nome.trim()) e.nome = "Dê um nome ao feriado.";
    if (fim < ini) e.fim = "O fim não pode ser antes do início.";
    setErros(e);
    if (!Object.keys(e).length) onSalvar({ ...(item || {}), nome, ini, fim, local:local || null, nota });
  };
  return (
    <Drawer title={item ? `Editar ${item.nome}` : "Novo feriado"} sub={H.plural(H.dias(ini, fim), "1 dia", "{n} dias")} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button><button className="os-btn primary" onClick={salvar}>Salvar feriado</button></>}>
      <Sec title="Feriado">
        <div className="hrm-campos">
          <Campo label="Nome" valor={nome} erro={erros.nome} onChange={setNome} placeholder="Ex.: Aniversário da cidade"/>
          <Escolha label="Localidade" valor={local} onChange={setLocal} opcoes={[{ v:"", l:"Negócio inteiro" }, { v:"Matriz", l:"Matriz" }, { v:"Filial Norte", l:"Filial Norte" }]}/>
          <Data label="Início" valor={ini} onChange={setIni}/>
          <Data label="Fim" valor={fim} erro={erros.fim} onChange={setFim}/>
          <Texto label="Observação" valor={nota} onChange={setNota} linhas={2} help="Aparece na lista e no painel do colaborador"/>
        </div>
      </Sec>
      <Sec title="Quem vê">
        <p className="hrm-achado-d">Sem localidade, o feriado vale para todas as unidades. Com localidade, só quem tem acesso a ela enxerga — e apenas o administrador pode criar, editar ou excluir.</p>
      </Sec>
    </Drawer>
  );
}

// ── Turno ──
const DIAS = [["monday", "seg"], ["tuesday", "ter"], ["wednesday", "qua"], ["thursday", "qui"], ["friday", "sex"], ["saturday", "sáb"], ["sunday", "dom"]];
function FormTurno({ item, onClose, onSalvar }) {
  const [nome, setNome] = useState(item?.nome || "");
  const [tipo, setTipo] = useState(item?.tipo || "fixed_shift");
  const [ini, setIni] = useState(item?.ini || "08:00");
  const [fim, setFim] = useState(item?.fim || "17:00");
  const [folgas, setFolgas] = useState(item?.folgas || ["sunday"]);
  const [autoOut, setAutoOut] = useState(!!item?.autoOut);
  const [autoOutAs, setAutoOutAs] = useState(item?.autoOutAs || "18:00");
  const [erros, setErros] = useState({});
  const flex = tipo === "flexible_shift";
  const salvar = () => {
    const e = {};
    if (!nome.trim()) e.nome = "Dê um nome ao turno.";
    if (!flex && (!ini || !fim)) e.ini = "Turno fixo precisa de entrada e saída.";
    setErros(e);
    if (!Object.keys(e).length) onSalvar({ ...(item || { id:Date.now(), pessoas:0 }), nome, tipo, ini:flex ? null : ini, fim:flex ? null : fim, folgas, autoOut, autoOutAs:autoOut ? autoOutAs : null });
  };
  return (
    <Drawer title={item ? `Editar ${item.nome}` : "Novo turno"} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button><button className="os-btn primary" onClick={salvar}>Salvar turno</button></>}>
      <Sec title="Turno">
        <div className="hrm-campos">
          <Campo label="Nome" valor={nome} erro={erros.nome} onChange={setNome} placeholder="Ex.: Turno C — madrugada"/>
          <Escolha label="Tipo" valor={tipo} onChange={setTipo} opcoes={[{ v:"fixed_shift", l:"Turno fixo (com horário)" }, { v:"flexible_shift", l:"Turno flexível (sem horário)" }]}/>
          {!flex && <Campo label="Entrada" tipo="time" valor={ini} erro={erros.ini} onChange={setIni}/>}
          {!flex && <Campo label="Saída" tipo="time" valor={fim} onChange={setFim}/>}
        </div>
        {flex && <p className="hrm-achado-d">Turno flexível não compara marcação com escala — a tolerância das configurações não se aplica.</p>}
      </Sec>
      <Sec title="Folgas da semana">
        <div className="hrm-chips">
          {DIAS.map(([k, l]) => (
            <button key={k} className={`hrm-chip-btn ${folgas.includes(k) ? "on" : ""}`}
              onClick={() => setFolgas((f) => f.includes(k) ? f.filter((x) => x !== k) : [...f, k])}>{l}</button>))}
        </div>
      </Sec>
      <Sec title="Saída automática">
        <label className="hrm-row hrm-pick"><span className="hrm-row-l"><span className="hrm-row-t"><input type="checkbox" checked={autoOut} onChange={() => setAutoOut(!autoOut)}/> Fechar marcação em aberto automaticamente</span><span className="hrm-row-s">Roda pelo agendador (AutoClockOutUser)</span></span></label>
        {autoOut && <div className="hrm-campos"><Campo label="Hora do fechamento" tipo="time" valor={autoOutAs} onChange={setAutoOutAs}/></div>}
      </Sec>
    </Drawer>
  );
}

// ── Gerar folha do mês ──
function FormFolha({ onClose, onGerar }) {
  const [mes, setMes] = useState("09");
  const [ano, setAno] = useState("2026");
  const [local, setLocal] = useState("Matriz");
  const [quem, setQuem] = useState(H.EMP.filter((e) => e.local === "Matriz").map((e) => e.id));
  const comp = `${mes}/${ano}`;
  const jaTem = H.FOLHA.filter((f) => f.mes === comp && quem.includes(f.emp)).map((f) => f.emp);
  const doLocal = H.EMP.filter((e) => e.local === local);
  const base = H.EMP.filter((e) => quem.includes(e.id) && !jaTem.includes(e.id)).reduce((s, e) => s + e.salario, 0);
  return (
    <Drawer title="Gerar folha do mês" sub={`${comp} · ${local}`} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" disabled={quem.length === jaTem.length} onClick={() => onGerar({ comp, local, quem:quem.filter((q) => !jaTem.includes(q)) })}>{H.plural(quem.length - jaTem.length, "Gerar 1 contracheque", "Gerar {n} contracheques")}</button></>}>
      <Sec title="Competência">
        <div className="hrm-campos">
          <Escolha label="Mês" valor={mes} onChange={setMes} opcoes={["01","02","03","04","05","06","07","08","09","10","11","12"].map((m) => ({ v:m, l:m }))}/>
          <Escolha label="Ano" valor={ano} onChange={setAno} opcoes={[{ v:"2026", l:"2026" }, { v:"2027", l:"2027" }]}/>
          <Escolha label="Localidade" valor={local} onChange={(v) => { setLocal(v); setQuem(H.EMP.filter((e) => e.local === v).map((e) => e.id)); }}
            opcoes={[{ v:"Matriz", l:"Matriz" }, { v:"Filial Norte", l:"Filial Norte" }]}/>
        </div>
      </Sec>
      <Sec title="Colaboradores">
        <div className="hrm-list">
          {doLocal.map((e) => {
            const on = quem.includes(e.id);
            const bloq = jaTem.includes(e.id);
            return (
              <label className={`hrm-row hrm-pick ${bloq ? "bloq" : ""}`} key={e.id}>
                <span className="hrm-row-l">
                  <span className="hrm-row-t"><input type="checkbox" checked={on} disabled={bloq} onChange={() => setQuem((q) => on ? q.filter((x) => x !== e.id) : [...q, e.id])}/> {e.nome}</span>
                  <span className="hrm-row-s">{e.cargo} · base {H.brl(e.salario)}</span>
                </span>
                {bloq && <Badge tone="warn">já tem folha em {comp}</Badge>}
              </label>);
          })}
        </div>
      </Sec>
      <Sec title="O que entra automático">
        <div className="hrm-list">
          <Row t="Comissão de venda" s="percentual do colaborador × faturado no mês"/>
          <Row t="Comissão de meta" s="faixa atingida × vendido (sem imposto, se a config estiver ligada)"/>
          <Row t="Ganhos e deduções recorrentes" s="do cadastro, por colaborador ou setor"/>
          <Row t="Base do lote" v={H.brl(base)}/>
        </div>
        <p className="hrm-achado-d">Sem INSS, IRRF, FGTS, 13º ou férias proporcionais — o lote soma ganhos, subtrai deduções e grava despesa.</p>
      </Sec>
    </Drawer>
  );
}

// ── Lançar pagamento do lote ──
function FormPagamento({ lote, onClose, onPagar }) {
  const itens = H.FOLHA.filter((f) => f.lote === lote.id);
  const [pag, setPag] = useState(() => Object.fromEntries(itens.map((f) => [f.id, { valor:H.totalFolha(f).toFixed(2), forma:"pix", conta:"Itaú · 1234" }])));
  const total = itens.reduce((s, f) => s + (f.pagamento === "paid" ? 0 : num(pag[f.id].valor)), 0);
  const devido = itens.filter((f) => f.pagamento !== "paid").reduce((s, f) => s + H.totalFolha(f), 0);
  const parcial = total > 0 && total < devido;
  return (
    <Drawer largo title={`Lançar pagamento · ${lote.nome}`} sub={`${H.plural(itens.length, "1 contracheque", "{n} contracheques")} · devido ${H.brl(devido)}`} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" disabled={!total} onClick={() => onPagar({ lote, total, parcial })}>Pagar {H.brl(total)}</button></>}>
      <Sec title="Por colaborador">
        <div className="hrm-list">
          {itens.map((f) => {
            const e = H.emp(f.emp);
            const pago = f.pagamento === "paid";
            return (
              <div className="hrm-row" key={f.id}>
                <span className="hrm-row-l"><span className="hrm-row-t">{e.nome}</span><span className="hrm-row-s">{f.ref} · líquido {H.brl(H.totalFolha(f))}</span></span>
                {pago ? <Badge tone="ok">pago</Badge> : (
                  <span className="hrm-pag-campos">
                    <input className="hrm-mini" value={pag[f.id].valor} onChange={(ev) => setPag((p) => ({ ...p, [f.id]:{ ...p[f.id], valor:ev.target.value } }))} aria-label={`Valor pago a ${e.nome}`}/>
                    <select className="hrm-sel" value={pag[f.id].forma} onChange={(ev) => setPag((p) => ({ ...p, [f.id]:{ ...p[f.id], forma:ev.target.value } }))} aria-label="Forma">
                      <option value="pix">Pix</option><option value="transferencia">Transferência</option><option value="dinheiro">Dinheiro</option><option value="cheque">Cheque</option>
                    </select>
                  </span>)}
              </div>);
          })}
        </div>
      </Sec>
      <Sec title="Efeito">
        <div className="hrm-list">
          <Row t="Total do lançamento" v={H.brl(total)}/>
          <Row t="Situação do lote depois" v={parcial ? "Parcial" : total >= devido ? "Pago" : "A pagar"}/>
        </div>
        <p className="hrm-achado-d">O lote fica <b>parcial</b> enquanto houver contracheque em aberto: a situação do lote é derivada dos itens, não escolhida à mão.</p>
      </Sec>
    </Drawer>
  );
}

// ── Metas por colaborador ──
function FormMeta({ emp, faixas, onClose, onSalvar }) {
  const [lista, setLista] = useState(faixas.length ? faixas : [{ id:Date.now(), ini:0, fim:0, pct:0 }]);
  const set = (i, k, v) => setLista((l) => l.map((f, ix) => ix === i ? { ...f, [k]:num(v) } : f));
  const sobrepostas = useMemo(() => {
    const s = new Set();
    lista.forEach((a, i) => lista.forEach((b, j) => { if (i < j && a.ini <= b.fim && b.ini <= a.fim) { s.add(i); s.add(j); } }));
    return s;
  }, [lista]);
  const invertida = lista.some((f) => f.fim < f.ini);
  const r = H.REALIZADO[emp.id] || { mes:0 };
  return (
    <Drawer title={`Metas · ${emp.nome}`} sub={`vendido no mês ${H.brl(r.mes)}`} onClose={onClose}
      footer={<><button className="os-btn ghost" onClick={onClose}>Cancelar</button>
        <button className="os-btn primary" disabled={sobrepostas.size > 0 || invertida} onClick={() => onSalvar(emp.id, lista.filter((f) => f.fim > 0))}>Salvar metas</button></>}>
      <Sec title="Faixas de comissão">
        <div className="hrm-faixas">
          {lista.map((f, i) => (
            <div className={`hrm-faixa ${sobrepostas.has(i) ? "erro" : ""}`} key={f.id}>
              <label>De<input className="hrm-mini" value={f.ini} onChange={(e) => set(i, "ini", e.target.value)} aria-label="Faixa de"/></label>
              <label>Até<input className="hrm-mini" value={f.fim} onChange={(e) => set(i, "fim", e.target.value)} aria-label="Faixa até"/></label>
              <label>%<input className="hrm-mini" value={f.pct} onChange={(e) => set(i, "pct", e.target.value)} aria-label="Percentual"/></label>
              <button className="os-btn ghost" onClick={() => setLista((l) => l.filter((_, ix) => ix !== i))}>Remover</button>
            </div>))}
        </div>
        <button className="os-btn ghost" onClick={() => setLista((l) => [...l, { id:Date.now(), ini:0, fim:0, pct:0 }])}>Adicionar faixa</button>
      </Sec>
      {(sobrepostas.size > 0 || invertida) && <Nota tone="danger" title={invertida ? "Faixa invertida" : "Faixas sobrepostas"}>
        {invertida ? "Uma faixa termina antes de começar." : "Duas faixas cobrem o mesmo valor vendido — a apuração pegaria a primeira do banco."} <b>O servidor aceita isso hoje</b> (achado A5); esta tela recusa salvar.
      </Nota>}
      <Sec title="Regra">
        <p className="hrm-achado-d">Salvar substitui o conjunto: faixa que não vier no envio é apagada. Sem faixa, a comissão de meta é zero — a comissão fixa do cadastro ({emp.comissao}%) continua valendo.</p>
      </Sec>
    </Drawer>
  );
}

window.HrmForms = { FormLicenca, FormFeriado, FormTurno, FormFolha, FormPagamento, FormMeta };
})();
