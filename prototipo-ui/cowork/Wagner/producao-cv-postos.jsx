// producao-cv-postos.jsx — Postos e cadastros da Produção: postos e máquinas (com os motivos de pausa e de
// refugo de cada posto e os operadores), veículos, ferramentas com QR e custo-hora da pessoa.
// Forma trazida da tela que já está no main (Modules/Producao/Resources/js/Pages/Producao/Postos/Index.tsx +
// _components/EditorPosto.tsx, threads 03 + E03 e US-PCP-030): o protótipo estava atrás dela.
// Valores aqui são cadastro, nenhuma conta: o custo real da OS sai do apontamento (CustoRealService).
// Expõe window.PcvPostos. Depende de window.PCVD.
(() => {
const { useState } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const P = () => window.PCVD;
const B = (p) => (window.PcvBadge ? <window.PcvBadge par={p} /> : null);
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <span aria-hidden="true" style={{ display: "inline-flex" }}><F size={size} /></span> : null; };

// Motivos sugeridos ao criar um posto (Motivo::PADRAO no main).
const PADRAO = {
  pausa: ["Troca de bobina", "Setup", "Falta de material", "Manutenção", "Dúvida na arte", "Intervalo"],
  refugo: ["Defeito de mídia", "Cor", "Cabeça", "Corte", "Arte"],
};
// Quem tem acesso à Produção (candidatos a operador do posto).
const USUARIOS = ["Ana", "Diego", "Paula", "Felipe", "Marcos", "Jonas", "Rafa", "Larissa"];

// Cadastro inicial: as máquinas do protótipo viram postos. Bancada de solda custa pela pessoa.
const CUSTO = { latex: 92, eco: 78, uv: 120, recorte: 45, lamin: 38, cnc: 110, solda: 46 };
const postosIniciais = (D) => [
  ...D.MAQUINAS.map((m, i) => ({
    id: m.id, codigo: "P-" + String(i + 1).padStart(2, "0"), nome: m.nome, setor: D.setorDe(m.setor).nome,
    tipo: m.id === "solda" ? "pessoa" : "maquina", veiculo: false, placa: "", custoHora: CUSTO[m.id] || 0, custoKm: null,
    cap: m.capH || "", un: m.un === "h" ? "" : m.un, ativo: true,
    operadores: m.id === "solda" ? [] : m.operadores || [],
    motivos: { pausa: m.setor === "impressao" ? PADRAO.pausa : PADRAO.pausa.filter((x) => x !== "Troca de bobina"),
      refugo: m.setor === "impressao" ? PADRAO.refugo : ["Corte", "Medida errada", "Acabamento"] },
  })),
  { id: "vei-02", codigo: "V-02", nome: "Fiorino", setor: "", tipo: "maquina", veiculo: true, placa: "ABC-1D23", custoHora: 35, custoKm: 1.45, cap: "", un: "", ativo: true, operadores: [], motivos: { pausa: [], refugo: [] } },
  { id: "vei-03", codigo: "V-03", nome: "Strada", setor: "", tipo: "maquina", veiculo: true, placa: "DEF-4G56", custoHora: 32, custoKm: 1.30, cap: "", un: "", ativo: false, operadores: [], motivos: { pausa: [], refugo: [] } },
];
const ferramentasIniciais = (D) => D.FERRAMENTAS.filter((f) => f.tipo !== "veículo").map((f) => ({ qr: f.id, nome: f.nome }));
const CUSTO_PESSOA = { Ana: 48, Diego: 42, Paula: 44, Felipe: 38, Marcos: 46, Jonas: 40, Rafa: 36, Larissa: "" };

// Manutenção preventiva por máquina (backlog P3 do ROADMAP da Produção, "Manutenção preventiva máquinas",
// referência SAP PM e Odoo Maintenance; ainda sem US no SPEC). O plano vence por horas de uso apontadas ou por
// dias. A pausa "Manutenção" do celular continua sendo a corretiva, a que acontece quando a máquina para.
const MANUT = [
  { maq: "uv", o: "Limpeza das cabeças e troca do filtro de tinta", cada: 80, un: "h", uso: 76, ultima: "2026-09-29", quem: "Paula" },
  { maq: "cnc", o: "Lubrificação dos fusos e troca da fresa", cada: 30, un: "dias", ultima: "2026-09-10", quem: "Marcos" },
  { maq: "latex", o: "Troca do rolo de manutenção e limpeza do carro", cada: 120, un: "h", uso: 64, ultima: "2026-10-02", quem: "Diego" },
  { maq: "lamin", o: "Limpeza e regulagem dos rolos", cada: 60, un: "dias", ultima: "2026-09-01", quem: "Felipe" },
];
const addDias = (iso, n) => { const d = new Date(iso + "T12:00:00"); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10); };
// Situação do plano: vencida, vence em breve (até 10% do intervalo ou 3 dias) ou em dia.
const situacaoManut = (m, D) => {
  if (m.un === "h") { const falta = m.cada - m.uso; return { falta: falta <= 0 ? "passou " + -falta + " h do plano" : "faltam " + falta + " h de uso", sit: falta <= 0 ? "vencida" : falta <= m.cada * 0.1 ? "em breve" : "em dia",
    msg: falta <= 0 ? "Passou " + -falta + " h do plano." : "Faltam " + falta + " h de uso." }; }
  const prox = addDias(m.ultima, m.cada); const d = D.dias(D.HOJE, prox);
  return { falta: d < 0 ? "venceu em " + D.d2(prox) : "próxima em " + D.d2(prox), sit: d < 0 ? "vencida" : d <= 3 ? "em breve" : "em dia",
    msg: d < 0 ? "Venceu em " + D.d2(prox) + ", há " + -d + " dias." : "Vence em " + D.d2(prox) + "." };
};
const SIT_MANUT = { vencida: ["Vencida", "danger"], "em breve": ["Vence em breve", "warning"], "em dia": ["Em dia", "success"] };
// Para o Painel: só o que venceu ou vence em breve.
const manutencaoAlertas = () => { const D = P(); return MANUT.map((m) => ({ ...m, ...situacaoManut(m, D) })).filter((m) => m.sit !== "em dia"); };

function Manutencao({ D, aviso }) {
  const { Button, Input } = DS();
  const [, setN] = useState(0);
  const [feita, setFeita] = useState(null);
  return (
    <Bloco titulo="Manutenção preventiva" nota="Plano por máquina, por horas de uso apontadas ou por dias. O Painel avisa o PCP quando vence. A parada inesperada continua sendo a pausa “Manutenção” no celular.">
      <ul className="pcv-postos">{MANUT.map((m, i) => { const s = situacaoManut(m, D); const maq = D.maqDe(m.maq); return (
        <li key={m.maq} className="pcv-posto">
          <div className="pcv-posto-m"><b>{maq ? maq.nome : m.maq}</b>
            <small>{m.o} · a cada {m.cada} {m.un === "h" ? "h de uso" : "dias"}</small>
            <small>Última em {D.d2(m.ultima)} ({m.quem}){m.un === "h" ? " · " + m.uso + " h de uso desde então" : ""} · {s.falta}</small>
            {feita && feita.i === i && <div className="pcv-form">
              {Input && <Input label="O que foi feito" value={feita.txt} onChange={(e) => setFeita({ ...feita, txt: e.target.value })} />}
              <div className="pcv-bar"><Button onClick={() => setFeita(null)}>Cancelar</Button><Button variant="primary" disabled={feita.txt.trim().length < 3} onClick={() => { m.ultima = D.HOJE; m.uso = 0; m.quem = "Você"; D.registrar({ quem: "Você", tipo: "pcp", maq: m.maq, texto: "Manutenção preventiva feita em " + (maq ? maq.nome : m.maq) + ": " + feita.txt.trim() }); setFeita(null); setN((x) => x + 1); aviso("Manutenção de " + (maq ? maq.nome : m.maq) + " registrada. O plano recomeça hoje."); }}>Registrar</Button></div>
            </div>}
          </div>
          {B(SIT_MANUT[s.sit])}
          {Button && !(feita && feita.i === i) && <Button size="sm" onClick={() => setFeita({ i, txt: m.o })}>Registrar feita</Button>}
        </li>); })}</ul>
    </Bloco>
  );
}

const linhas = (t) => t.split("\n").map((x) => x.trim()).filter(Boolean);
const dec = (v) => (v === "" || v === null || v === undefined ? "" : String(v).replace(",", "."));

function Bloco({ titulo, nota, acao, children }) {
  return (
    <section className="pcv-cad" aria-label={titulo}>
      <div className="pcv-cad-h"><div className="pcv-cad-t"><h2 className="pcv-h2">{titulo}</h2><p className="pcv-help">{nota}</p></div>{acao}</div>
      {children}
    </section>
  );
}

function ListaPostos({ itens, onEditar, D }) {
  if (!itens.length) return <p className="pcv-help">Nenhum cadastrado ainda.</p>;
  return (
    <ul className="pcv-postos">
      {itens.map((p) => (
        <li key={p.id} className={"pcv-posto" + (p.ativo ? "" : " is-inativo")}>
          <div className="pcv-posto-m">
            <b>{p.nome}</b>
            <small>{[p.codigo, p.setor, p.veiculo ? p.placa : null, p.tipo === "maquina" ? "custo da máquina" : "custo da pessoa"].filter(Boolean).join(" · ")}
              {!p.veiculo && p.cap ? " · " + D.h1(p.cap) + " " + p.un + "/h" : ""}</small>
            {!p.veiculo && <small>Operadores: {p.operadores.join(", ") || "qualquer pessoa da Produção"}</small>}
            {!p.veiculo && <small>Pausa: {p.motivos.pausa.join(", ") || "—"} · Refugo: {p.motivos.refugo.join(", ") || "—"}</small>}
          </div>
          <span className="pcv-posto-v mono">{D.fmt(p.custoHora)}/h{p.veiculo && p.custoKm ? " · " + D.fmt(p.custoKm) + "/km" : ""}</span>
          {!p.ativo && B(["Inativo", "neutral"])}
          <button type="button" className="pcv-posto-ed" onClick={() => onEditar(p)} aria-label={"Editar " + p.nome}><Ic name="pencil" size={14} /></button>
        </li>
      ))}
    </ul>
  );
}

// Drawer PT-02 (760px). Posto novo nasce com os motivos sugeridos; motivos e operadores só se ajustam depois de salvo.
function EditorPosto({ posto, veiculo, onSalvar, onClose }) {
  const { Drawer, Button, Input, Select, Switch, Checkbox, Textarea } = DS();
  const [v, setV] = useState(posto ? { ...posto } : { codigo: "", nome: "", setor: "", tipo: "maquina", veiculo, placa: "", custoHora: "", custoKm: "", cap: "", un: "", ativo: true, operadores: [], motivos: { pausa: PADRAO.pausa, refugo: PADRAO.refugo } });
  const [pausa, setPausa] = useState(v.motivos.pausa.join("\n"));
  const [refugo, setRefugo] = useState(v.motivos.refugo.join("\n"));
  const [erro, setErro] = useState(null);
  const set = (k, x) => setV((a) => ({ ...a, [k]: x }));
  const editaMotivos = posto !== null && !veiculo;
  const alternar = (nome, on) => set("operadores", on ? [...v.operadores, nome] : v.operadores.filter((x) => x !== nome));
  const TA = Textarea || ((p) => <label className="pcv-campo"><span>{p.label}</span><textarea rows={p.rows} value={p.value} onChange={p.onChange} /></label>);
  const salvar = () => {
    if (!v.codigo.trim() || !v.nome.trim()) return setErro("Código e nome são obrigatórios.");
    if (dec(v.custoHora) === "" || isNaN(+dec(v.custoHora))) return setErro("Informe o custo-hora.");
    onSalvar({ ...v, custoHora: +dec(v.custoHora), custoKm: veiculo ? +dec(v.custoKm) || 0 : null, cap: dec(v.cap) === "" ? "" : +dec(v.cap),
      motivos: editaMotivos ? { pausa: linhas(pausa), refugo: linhas(refugo) } : v.motivos });
  };
  if (!Drawer) return null;
  return (
    <Drawer open width={760} onClose={onClose} title={posto ? "Editar " + posto.nome : veiculo ? "Novo veículo" : "Novo posto"}
      subtitle={veiculo ? "Veículo custa pela hora e pelo km." : "Capacidade e custo-hora são da sua empresa."}
      footer={<div className="pcv-bar" style={{ justifyContent: "flex-end" }}>{erro && <span className="pcv-cad-erro" role="alert">{erro}</span>}<Button onClick={onClose}>Cancelar</Button><Button variant="primary" onClick={salvar}>Salvar</Button></div>}>
      <div className="pcv-ficha">
        <div className="pcv-cad-grid">
          <Input label="Código" value={v.codigo} onChange={(e) => set("codigo", e.target.value)} />
          <Input label="Nome" value={v.nome} onChange={(e) => set("nome", e.target.value)} />
          {veiculo
            ? <Input label="Placa" value={v.placa} onChange={(e) => set("placa", e.target.value.toUpperCase())} />
            : <Input label="Setor" value={v.setor} placeholder="Impressão" onChange={(e) => set("setor", e.target.value)} />}
          {!veiculo && <Select label="Custo por" value={v.tipo} onChange={(e) => set("tipo", e.target.value)}
            options={[{ value: "maquina", label: "Hora da máquina" }, { value: "pessoa", label: "Hora da pessoa" }]} />}
          <Input label="Custo-hora (R$)" value={String(v.custoHora)} placeholder="ex.: 85,00" onChange={(e) => set("custoHora", e.target.value)} />
          {veiculo
            ? <Input label="Custo por km (R$)" value={String(v.custoKm ?? "")} placeholder="ex.: 1,45" onChange={(e) => set("custoKm", e.target.value)} />
            : <div className="pcv-cad-cap"><Input label="Capacidade por hora" value={String(v.cap)} onChange={(e) => set("cap", e.target.value)} /><Input label="Unidade" value={v.un} placeholder="m²" onChange={(e) => set("un", e.target.value)} /></div>}
        </div>
        {Switch && <Switch checked={v.ativo} onChange={(x) => set("ativo", x)} label="Ativo" sublabel={v.ativo ? "aparece no quadro e no celular" : "fica fora da programação"} />}
        {editaMotivos && <div className="pcv-cad-grid">
          <TA label="Motivos de pausa (um por linha)" rows={6} value={pausa} onChange={(e) => setPausa(e.target.value)} />
          <TA label="Motivos de refugo (um por linha)" rows={6} value={refugo} onChange={(e) => setRefugo(e.target.value)} />
        </div>}
        {editaMotivos && <fieldset className="pcv-cad-ops">
          <legend>Operadores do posto</legend>
          <p className="pcv-help">Só eles podem ser responsáveis e começar as etapas deste posto. Nenhum marcado: qualquer pessoa da Produção.</p>
          <ul>{USUARIOS.map((u) => <li key={u}>{Checkbox
            ? <Checkbox checked={v.operadores.includes(u)} onChange={(on) => alternar(u, on)} label={u} />
            : <label><input type="checkbox" checked={v.operadores.includes(u)} onChange={(e) => alternar(u, e.target.checked)} /> {u}</label>}</li>)}</ul>
        </fieldset>}
        {!posto && !veiculo && <p className="pcv-help">O posto novo já nasce com os motivos sugeridos (troca de bobina, setup, falta de material…). Ajuste depois de salvar.</p>}
      </div>
    </Drawer>
  );
}

function Ferramentas({ itens, onAdd }) {
  const { Button, Input } = DS();
  const [f, setF] = useState({ qr: "", nome: "" });
  const [erro, setErro] = useState(null);
  const add = (e) => {
    e.preventDefault();
    if (!f.qr.trim() || !f.nome.trim()) return setErro("Informe o código QR e o nome.");
    if (itens.some((x) => x.qr.toLowerCase() === f.qr.trim().toLowerCase())) return setErro("Esse código QR já é de outra ferramenta.");
    onAdd({ qr: f.qr.trim(), nome: f.nome.trim() }); setF({ qr: "", nome: "" }); setErro(null);
  };
  return (
    <Bloco titulo="Ferramentas" nota="Cada ferramenta tem um código QR para sair e voltar com a equipe de campo.">
      <form className="pcv-cad-add" onSubmit={add}>
        <div className="pcv-cad-qr"><Input placeholder="Código QR" value={f.qr} onChange={(e) => setF({ ...f, qr: e.target.value })} /></div>
        <div className="pcv-cad-nome"><Input placeholder="Furadeira de impacto" value={f.nome} onChange={(e) => setF({ ...f, nome: e.target.value })} /></div>
        {Button && <Button type="submit"><Ic name="plus" size={13} /> Adicionar</Button>}
      </form>
      {erro && <p className="pcv-cad-erro" role="alert">{erro}</p>}
      <ul className="pcv-rot-chips">{itens.map((x) => <li key={x.qr} className="pcv-chip"><span className="mono">{x.qr}</span> {x.nome}</li>)}</ul>
    </Bloco>
  );
}

function CustoPessoa({ D, aviso }) {
  const { Button, Input } = DS();
  const [v, setV] = useState(CUSTO_PESSOA);
  return (
    <Bloco titulo="Custo-hora da pessoa" nota="Usado no custo real das etapas manuais. Só quem vê o desempenho da equipe vê e altera este valor.">
      <ul className="pcv-cad-pessoas">
        {Object.keys(v).map((n) => (
          <li key={n}><span>{n}<small>{D.PESSOAS[n] || ""}</small></span>
            <div className="pcv-cad-val"><Input value={String(v[n])} placeholder="—" onChange={(e) => setV({ ...v, [n]: e.target.value })} /></div>
            {Button && <Button size="sm" onClick={() => aviso("Custo-hora de " + n + " salvo: " + (dec(v[n]) === "" ? "sem valor" : D.fmt(+dec(v[n])) + "/h") + ".")}>Salvar</Button>}</li>
        ))}
      </ul>
    </Bloco>
  );
}

function Postos() {
  const D = P(); const { Button } = DS();
  const [postos, setPostos] = useState(() => postosIniciais(D));
  const [ferr, setFerr] = useState(() => ferramentasIniciais(D));
  const [editando, setEditando] = useState(null);
  const [aviso, setAviso] = useState(null);
  const salvar = (p) => {
    setPostos((l) => (editando && typeof editando === "object" ? l.map((x) => (x.id === editando.id ? { ...p, id: x.id } : x)) : [...l, { ...p, id: "novo-" + Date.now() }]));
    setAviso((p.veiculo ? "Veículo " : "Posto ") + p.nome + " salvo."); setEditando(null);
  };
  // Campos vêm do DS; sem ele (bundle ainda chegando) a vista espera em vez de quebrar a rota.
  if (!DS().Input) return <div className="pcv-body"><p className="pcv-help">Carregando os cadastros…</p></div>;
  const novo = (tipo) => Button && <Button size="sm" onClick={() => setEditando(tipo)}><Ic name="plus" size={13} /> {tipo === "veiculo" ? "Novo veículo" : "Novo posto"}</Button>;
  return (
    <div className="pcv-body">
      <Bloco titulo="Postos e máquinas" nota="Capacidade e custo-hora são da sua empresa. Os motivos de pausa e de refugo do posto são a lista que o operador vê no celular." acao={novo("posto")}>
        <ListaPostos D={D} itens={postos.filter((p) => !p.veiculo)} onEditar={setEditando} />
      </Bloco>
      <Manutencao D={D} aviso={setAviso} />
      <Bloco titulo="Veículos" nota="Veículo é posto de máquina: entra no custo da OS por hora e por km." acao={novo("veiculo")}>
        <ListaPostos D={D} itens={postos.filter((p) => p.veiculo)} onEditar={setEditando} />
      </Bloco>
      <Ferramentas itens={ferr} onAdd={(x) => { setFerr((l) => [...l, x]); setAviso("Ferramenta " + x.nome + " cadastrada com o QR " + x.qr + "."); }} />
      <CustoPessoa D={D} aviso={setAviso} />
      {editando && <EditorPosto posto={typeof editando === "object" ? editando : null} veiculo={editando === "veiculo" || (typeof editando === "object" && editando.veiculo)}
        onSalvar={salvar} onClose={() => setEditando(null)} />}
      {aviso && <div className="pcv-toast" role="status">{aviso}<button className="pcv-link" onClick={() => setAviso(null)}>Fechar</button></div>}
    </div>
  );
}

window.PcvPostos = { Postos, manutencaoAlertas };
})();
