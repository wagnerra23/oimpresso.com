// planilhas-page.jsx — Planilhas (import da rota /spreadsheet/sheets do Modules/Spreadsheet).
// Espelho lido no main NESTE turno: Routes/web.php (resource sheets exceto edit + add-folder + move-to-folder
// + get-sheet/{id}/share + post-share-sheet, throttle 60/1), views/sheet/index.blade.php (árvore jstree
// pastas → planilhas, busca, expandir/recolher, ações por linha só para o criador, "Untitled" = sem pasta,
// meta updated_at + created_by + compartilhado com usuários/funções/todos), create/show.blade.php (editor
// Luckysheet: importar .xlsx via LuckyExcel, salvar name+sheet_data, "Download" ainda não implementado no repo),
// partials/share_sheet.blade.php (compartilhar com Todos · Usuários · Funções), lang/pt (Planilha, Minhas
// planilhas, Criar planilha, Compartilhar planilha), memory/modulos/Spreadsheet.md (permissões access./create.spreadsheet,
// tabelas sheet_spreadsheets / sheet_spreadsheet_shares).
// Expõe window.PlanilhasPage.
(() => {
const { useState, useMemo, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const { Nota, Vazio, Confirm } = window.AcessosDS;

const EU = "Larissa Ferraz";
const AGORA = new Date(2026, 7, 20, 10, 40);
const p2 = (n) => String(n).padStart(2, "0");
const dt = (d) => `${p2(d.getDate())}/${p2(d.getMonth() + 1)}/${d.getFullYear()} ${p2(d.getHours())}:${p2(d.getMinutes())}`;
const rel = (d) => {
  const h = (AGORA - d) / 36e5;
  if (h < 1) return "agora";
  if (h < 24) return `há ${Math.round(h)} h`;
  const dias = Math.round(h / 24);
  return dias === 1 ? "ontem" : `há ${dias} dias`;
};
const atras = (dias, hora, min) => { const d = new Date(AGORA); d.setDate(d.getDate() - dias); d.setHours(hora, min, 0, 0); return d; };

const PASTAS = [
  { id: 1, name: "Financeiro" },
  { id: 2, name: "Produção" },
  { id: 3, name: "Comercial" },
];

const PLANILHAS = [
  { id: 21, name: "Fechamento diário — caixa", folder: 1, upd: atras(0, 9, 12), by: EU, mine: true, shares: { users: ["Eliana Souza"], roles: ["Financeiro"], todos: [] } },
  { id: 22, name: "Conciliação Pix — agosto", folder: 1, upd: atras(1, 18, 4), by: "Eliana Souza", mine: false, shares: { users: [EU], roles: [], todos: [] } },
  { id: 23, name: "Comissões vendedores 2026", folder: 1, upd: atras(6, 11, 30), by: EU, mine: true, shares: { users: [], roles: [], todos: [] } },
  { id: 31, name: "Consumo de lona por m²", folder: 2, upd: atras(2, 15, 45), by: "Wagner Rocha", mine: false, shares: { users: [EU], roles: ["Produção"], todos: ["Repor bobina 1,60m"] } },
  { id: 32, name: "Fila das impressoras — turnos", folder: 2, upd: atras(9, 8, 20), by: EU, mine: true, shares: { users: [], roles: ["Produção"], todos: [] } },
  { id: 41, name: "Metas de balcão — semana", folder: 3, upd: atras(0, 7, 55), by: EU, mine: true, shares: { users: ["Wagner Rocha"], roles: [], todos: ["Ligar para clientes inativos"] } },
  { id: 42, name: "Tabela de preços — adesivos", folder: 3, upd: atras(21, 16, 10), by: "Wagner Rocha", mine: false, shares: { users: [], roles: ["Comercial"], todos: [] } },
  { id: 51, name: "Rascunho — orçamento fachada", folder: null, upd: atras(3, 14, 2), by: EU, mine: true, shares: { users: [], roles: [], todos: [] } },
  { id: 52, name: "Inventário de insumos (import xlsx)", folder: null, upd: atras(12, 10, 38), by: EU, mine: true, shares: { users: [], roles: [], todos: [] } },
];

const USUARIOS = ["Wagner Rocha", "Eliana Souza", "Marcos Lima", "Téc. Repair — Diego", "Larissa Ferraz"];
const FUNCOES = ["Administrador", "Financeiro", "Produção", "Comercial", "Balcão"];
const TODOS_LISTA = ["Repor bobina 1,60m", "Ligar para clientes inativos", "Fechar caixa da semana", "Revisar tabela de preços"];

const COLS = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J"];
const LARG = { A: 96, B: 190, C: 104, D: 104, E: 104, F: 112, G: 112 };
const CELULAS = {
  A1: "Data", B1: "Operador", C1: "Dinheiro", D1: "Pix", E1: "Cartão", F1: "Total", G1: "Sangria",
  A2: "17/08", B2: "Larissa", C2: "412,00", D2: "1.980,50", E2: "2.240,00", F2: "4.632,50", G2: "300,00",
  A3: "18/08", B3: "Larissa", C3: "298,00", D3: "2.415,00", E3: "1.870,90", F3: "4.583,90", G3: "200,00",
  A4: "19/08", B4: "Marcos", C4: "531,50", D4: "1.204,00", E4: "3.110,00", F4: "4.845,50", G4: "400,00",
  A5: "20/08", B5: "Larissa", C5: "180,00", D5: "962,30", E5: "1.455,00", F5: "2.597,30", G5: "—",
  A7: "Semana", C7: "1.421,50", D7: "6.561,80", E7: "8.675,90", F7: "16.659,20", G7: "900,00",
};
const NUMERICAS = ["C", "D", "E", "F", "G"];
const FORMULAS = { F2: "=SOMA(C2:E2)", F3: "=SOMA(C3:E3)", F4: "=SOMA(C4:E4)", F5: "=SOMA(C5:E5)", F7: "=SOMA(F2:F5)", C7: "=SOMA(C2:C5)", D7: "=SOMA(D2:D5)", E7: "=SOMA(E2:E5)", G7: "=SOMA(G2:G5)" };

const IcFolder = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>;
const IcSheet = () => <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 9h16M4 15h16M10 3v18M15 3v18"/></svg>;
const IcChev = ({ open }) => <svg className={"pl-chev" + (open ? " open" : "")} width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round"><path d="m9 6 6 6-6 6"/></svg>;
const IcPlus = () => <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round"><path d="M12 5v14M5 12h14"/></svg>;
const IcKebab = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>;
const IcSearch = () => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>;

function Btn({ children, ...p }) {
  const { Button } = DS();
  if (!Button) return <button className={`os-btn ${p.variant === "primary" ? "primary" : "ghost"}`} disabled={p.disabled} onClick={p.onClick} title={p.title}>{children}</button>;
  return <Button {...p}>{children}</Button>;
}

function Toast({ texto }) {
  const { Toast: T } = DS();
  if (!texto) return null;
  return <div className="pl-toast">{T ? <T tone="ok">{texto}</T> : <div className="pl-toast-fb">{texto}</div>}</div>;
}

function Tag({ children, tone }) {
  const { StatusBadge } = DS();
  if (StatusBadge) return <StatusBadge tone={tone || "neutral"} label={children} />;
  return <span className="pl-tag">{children}</span>;
}

function Campo({ label, value, onChange, placeholder, autoFocus }) {
  return (
    <label className="pl-campo">
      <span>{label}</span>
      <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} autoFocus={autoFocus} />
    </label>
  );
}

function MultiPick({ label, opcoes, valor, onChange, vazio }) {
  return (
    <div className="pl-pick">
      <span className="pl-pick-l">{label}</span>
      <div className="pl-pick-opts">
        {opcoes.map((o) => {
          const on = valor.includes(o);
          return (
            <button key={o} type="button" className={"pl-chip" + (on ? " on" : "")}
              onClick={() => onChange(on ? valor.filter((x) => x !== o) : [...valor, o])}>{o}</button>
          );
        })}
      </div>
      {valor.length === 0 && <small>{vazio}</small>}
    </div>
  );
}

// ── Linha de planilha ───────────────────────────────────────────────
function LinhaSheet({ s, pastas, onAbrir, onCompartilhar, onMover, onExcluir, busca }) {
  const { DropdownMenu } = DS();
  const compart = [
    s.shares.users.length && `${s.shares.users.length} usuário${s.shares.users.length > 1 ? "s" : ""}`,
    s.shares.roles.length && `${s.shares.roles.length} função${s.shares.roles.length > 1 ? "ões" : ""}`,
    s.shares.todos.length && `${s.shares.todos.length} tarefa${s.shares.todos.length > 1 ? "s" : ""}`,
  ].filter(Boolean).join(" · ");
  const itens = [
    { id: "abrir", label: "Abrir planilha", onSelect: onAbrir },
    { id: "compartilhar", label: s.mine ? "Compartilhar…" : "Compartilhar — só quem criou", disabled: !s.mine, onSelect: onCompartilhar },
    { id: "mover", label: s.mine ? "Mover para outra pasta…" : "Mover — só quem criou", disabled: !s.mine, onSelect: onMover },
    { id: "sep", separator: true },
    { id: "excluir", label: s.mine ? "Excluir planilha" : "Excluir — só quem criou", tone: "danger", disabled: !s.mine, onSelect: onExcluir },
  ];
  const marca = (txt) => {
    if (!busca) return txt;
    const i = txt.toLowerCase().indexOf(busca.toLowerCase());
    if (i < 0) return txt;
    return <>{txt.slice(0, i)}<mark>{txt.slice(i, i + busca.length)}</mark>{txt.slice(i + busca.length)}</>;
  };
  return (
    <li className="pl-sheet">
      <button className="pl-sheet-main" onClick={onAbrir}>
        <span className="pl-sheet-ic" aria-hidden="true"><IcSheet /></span>
        <span className="pl-sheet-txt">
          <b>{marca(s.name)}</b>
          <small>
            <span title={dt(s.upd)}>Editada {rel(s.upd)}</span>
            <span className="pl-sep">·</span>
            <span>{s.mine ? "criada por você" : `criada por ${s.by}`}</span>
            {compart && <><span className="pl-sep">·</span><span>compartilhada com {compart}</span></>}
          </small>
        </span>
      </button>
      <span className="pl-sheet-acts">
        {!s.mine && <Tag tone="neutral">compartilhada com você</Tag>}
        <Btn size="default" onClick={onAbrir}>Abrir</Btn>
        {DropdownMenu
          ? <DropdownMenu align="end" items={itens} trigger={<span className="pl-kebab" aria-hidden="true"><IcKebab /></span>} />
          : <Btn size="sm" icon onClick={s.mine ? onExcluir : undefined}><IcKebab /></Btn>}
      </span>
    </li>
  );
}

// ── Editor (create/show) ────────────────────────────────────────────
function Editor({ sheet, onVoltar, fala }) {
  const [nome, setNome] = useState(sheet ? sheet.name : "Planilha sem título");
  const [sel, setSel] = useState("F2");
  const [dados, setDados] = useState(() => (sheet ? { ...CELULAS } : {}));
  const [aba, setAba] = useState(0);
  const [sujo, setSujo] = useState(false);
  const file = useRef(null);
  const abas = sheet ? ["Caixa", "Resumo"] : ["Planilha1"];
  const linhas = 14;

  const setCel = (ref, v) => { setDados((d) => ({ ...d, [ref]: v })); setSujo(true); };
  const importar = (e) => {
    const f = e.target.files && e.target.files[0];
    if (!f) return;
    if (!/\.xlsx$/i.test(f.name)) { fala("Só arquivos .xlsx — o conversor do módulo não lê .xls.", 6000); return; }
    setNome(f.name.replace(/\.xlsx$/i, ""));
    setDados({ ...CELULAS });
    setSujo(true);
    fala(`“${f.name}” carregado na planilha — revise e salve para gravar.`, 6000);
  };

  return (
    <div className="pl-editor">
      <div className="pl-ed-bar">
        <Btn onClick={onVoltar}>← Voltar</Btn>
        <input className="pl-ed-nome" value={nome} onChange={(e) => { setNome(e.target.value); setSujo(true); }} aria-label="Nome da planilha" />
        <span className="pl-ed-estado">{sujo ? "alterações não salvas" : sheet ? `salva ${rel(sheet.upd)}` : "nunca salva"}</span>
        <span className="pl-ed-sp" />
        <input ref={file} type="file" accept=".xlsx" className="pl-ed-file" onChange={importar} id="pl-import" />
        <Btn onClick={() => file.current && file.current.click()}>Importar .xlsx</Btn>
        <Btn disabled title="Não implementado no módulo — a rota de download ainda não existe no repo.">Baixar</Btn>
        <Btn variant="primary" onClick={() => { setSujo(false); fala(sheet ? "Planilha atualizada." : "Planilha criada em Sem pasta.", 5000); }}>
          {sheet ? "Atualizar" : "Salvar"}
        </Btn>
      </div>
      <div className="pl-ed-formula">
        <span className="pl-ed-ref">{sel}</span>
        <input value={FORMULAS[sel] || dados[sel] || ""} onChange={(e) => setCel(sel, e.target.value)} placeholder="Valor ou fórmula" aria-label="Barra de fórmula" />
      </div>
      <div className="pl-grid-wrap">
        <table className="pl-grid">
          <thead>
            <tr><th className="pl-gc" /> {COLS.map((c) => <th key={c} style={{ width: LARG[c] || 92 }} className={sel[0] === c ? "on" : ""}>{c}</th>)}</tr>
          </thead>
          <tbody>
            {Array.from({ length: linhas }, (_, i) => i + 1).map((r) => (
              <tr key={r}>
                <th className={"pl-gr" + (String(r) === sel.slice(1) ? " on" : "")}>{r}</th>
                {COLS.map((c) => {
                  const ref = c + r;
                  const v = dados[ref] || "";
                  return (
                    <td key={ref} className={(sel === ref ? "sel " : "") + (r === 1 && v ? "head " : "") + (r === 7 && v ? "tot " : "") + (NUMERICAS.includes(c) ? "num" : "")}
                      onClick={() => setSel(ref)}>
                      <input value={v} onChange={(e) => setCel(ref, e.target.value)} onFocus={() => setSel(ref)} tabIndex={-1} />
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <div className="pl-ed-abas">
        {abas.map((a, i) => <button key={a} className={"pl-aba" + (i === aba ? " on" : "")} onClick={() => setAba(i)}>{a}</button>)}
        <button className="pl-aba pl-aba-add" onClick={() => fala("Abas novas são criadas no editor do módulo ao salvar.", 4500)} aria-label="Nova aba"><IcPlus /></button>
        <span className="pl-ed-hint">O conteúdo vai para <code>sheet_data</code> (JSON) ao salvar — o payload inteiro, não a célula.</span>
      </div>
    </div>
  );
}

// ── Página ──────────────────────────────────────────────────────────
function PlanilhasPage({ view = "lista", permissao = "criar" }) {
  const { PageHeader } = DS();
  const podeCriar = permissao === "criar";
  const [pastas, setPastas] = useState(PASTAS);
  const [lista, setLista] = useState(PLANILHAS);
  const [busca, setBusca] = useState("");
  const [abertas, setAbertas] = useState(() => new Set([1, 2, 3, 0]));
  const [editor, setEditor] = useState(view === "nova" ? { sheet: null } : null);
  const [novaPasta, setNovaPasta] = useState(null);
  const [mover, setMover] = useState(null);
  const [excluir, setExcluir] = useState(null);
  const [share, setShare] = useState(null);
  const [aviso, setAviso] = useState(null);
  const fala = (t, ms = 5000) => { setAviso(t); setTimeout(() => setAviso(null), ms); };

  const grupos = useMemo(() => {
    const q = busca.trim().toLowerCase();
    const filtra = (arr) => (q ? arr.filter((s) => s.name.toLowerCase().includes(q)) : arr);
    const g = pastas.map((p) => ({ ...p, sheets: filtra(lista.filter((s) => s.folder === p.id)) }));
    g.push({ id: 0, name: "Sem pasta", sheets: filtra(lista.filter((s) => s.folder === null)) });
    return g;
  }, [pastas, lista, busca]);

  const totalVis = grupos.reduce((a, g) => a + g.sheets.length, 0);
  const minhas = lista.filter((s) => s.mine).length;
  const toggle = (id) => setAbertas((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const todas = (abrir) => setAbertas(abrir ? new Set(grupos.map((g) => g.id)) : new Set());

  if (editor) {
    return (
      <div className="os-page pl-page" data-screen-label="Planilhas · Editor">
        <Editor sheet={editor.sheet} fala={fala} onVoltar={() => setEditor(null)} />
        <Toast texto={aviso} />
      </div>
    );
  }

  const acoes = (
    <div className="pl-h-acts">
      <Btn disabled={!podeCriar} onClick={() => setNovaPasta({ nome: "", id: null })}>Nova pasta</Btn>
      <Btn variant="primary" disabled={!podeCriar} onClick={() => setEditor({ sheet: null })}><IcPlus /> Criar planilha</Btn>
    </div>
  );

  return (
    <div className="os-page pl-page" data-screen-label="Planilhas · Minhas planilhas">
      <div data-contract="cabecalho">
        {PageHeader
          ? <PageHeader title="Planilhas" subtitle={`${lista.length} planilhas em ${pastas.length} pastas · ${minhas} criadas por você`} actions={acoes} />
          : <header className="os-page-h"><div className="os-page-h-l"><h1>Planilhas</h1></div><div className="os-page-h-r">{acoes}</div></header>}
      </div>

      {!podeCriar && (
        <div className="pl-nota">
          <Nota tone="info" title="Você só tem acesso de leitura">
            Sua função tem <code>access.spreadsheet</code> mas não <code>create.spreadsheet</code> — dá para abrir e editar o que
            foi compartilhado com você, não para criar pasta ou planilha nova.
          </Nota>
        </div>
      )}

      <div className="pl-toolbar">
        <label className="pl-search">
          <IcSearch />
          <input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar planilha por nome" aria-label="Buscar planilha" />
        </label>
        <div className="pl-toolbar-r">
          <Btn size="sm" onClick={() => todas(true)}>Expandir tudo</Btn>
          <Btn size="sm" onClick={() => todas(false)}>Recolher tudo</Btn>
        </div>
      </div>

      <div className="pl-arvore" data-contract="arvore">
        {busca && totalVis === 0 ? (
          <Vazio variant="no-results" title={`Nenhuma planilha com “${busca}”.`} description="A busca olha só o nome da planilha — não o conteúdo das células." />
        ) : grupos.map((g) => {
          const open = abertas.has(g.id) || (!!busca && g.sheets.length > 0);
          if (busca && g.sheets.length === 0) return null;
          return (
            <section key={g.id} className={"pl-pasta" + (open ? " open" : "")}>
              <div className="pl-pasta-h">
                <button className="pl-pasta-t" onClick={() => toggle(g.id)} aria-expanded={open}>
                  <IcChev open={open} />
                  <span className="pl-pasta-ic" aria-hidden="true"><IcFolder /></span>
                  <b>{g.name}</b>
                  <span className="pl-pasta-n">{g.sheets.length}</span>
                </button>
                <span className="pl-pasta-acts">
                  {podeCriar && <Btn size="sm" onClick={() => setEditor({ sheet: null, folder: g.id })}>Criar aqui</Btn>}
                  {podeCriar && g.id !== 0 && <Btn size="sm" onClick={() => setNovaPasta({ nome: g.name, id: g.id })}>Renomear</Btn>}
                </span>
              </div>
              {open && (
                <ul className="pl-sheets">
                  {g.sheets.length === 0
                    ? <li className="pl-vazia">Pasta vazia{podeCriar ? " — crie a primeira planilha aqui." : "."}</li>
                    : g.sheets.map((s) => (
                      <LinhaSheet key={s.id} s={s} pastas={pastas} busca={busca.trim()}
                        onAbrir={() => setEditor({ sheet: s })}
                        onCompartilhar={() => setShare({ s, users: [...s.shares.users], roles: [...s.shares.roles], todos: [...s.shares.todos] })}
                        onMover={() => setMover({ s, destino: s.folder || "" })}
                        onExcluir={() => setExcluir(s)} />
                    ))}
                </ul>
              )}
            </section>
          );
        })}
      </div>

      <p className="pl-rodape">
        Sem pasta é o grupo <code>folder_id = null</code> do módulo. Compartilhar, mover e excluir aparecem só para quem criou a
        planilha — quem recebeu abre e edita, mas não redistribui.
      </p>

      <Toast texto={aviso} />

      <Confirm open={!!novaPasta} title={novaPasta && novaPasta.id ? "Renomear pasta" : "Nova pasta"}
        cta={novaPasta && novaPasta.id ? "Salvar nome" : "Criar pasta"} ctaTone="primary"
        ctaDisabled={!novaPasta || !novaPasta.nome.trim()}
        onClose={() => setNovaPasta(null)}
        onConfirm={() => {
          const n = novaPasta.nome.trim();
          if (novaPasta.id) { setPastas((s) => s.map((p) => (p.id === novaPasta.id ? { ...p, name: n } : p))); fala(`Pasta renomeada para “${n}”.`); }
          else { const id = Math.max(0, ...pastas.map((p) => p.id)) + 1; setPastas((s) => [...s, { id, name: n }]); setAbertas((s) => new Set([...s, id])); fala(`Pasta “${n}” criada — vazia até você criar uma planilha nela.`); }
          setNovaPasta(null);
        }}>
        {novaPasta && <>
          <Campo label="Nome da pasta" value={novaPasta.nome} autoFocus onChange={(v) => setNovaPasta((s) => ({ ...s, nome: v }))} placeholder="Ex.: Financeiro" />
          <p className="pl-modal-alt">Pastas são um nível só — o módulo não tem subpasta.</p>
        </>}
      </Confirm>

      <Confirm open={!!mover} title="Mover para outra pasta" cta="Mover" ctaTone="primary"
        ctaDisabled={!mover || mover.destino === "" || mover.destino === mover.s.folder}
        onClose={() => setMover(null)}
        onConfirm={() => {
          const dest = mover.destino === 0 ? null : Number(mover.destino);
          setLista((s) => s.map((x) => (x.id === mover.s.id ? { ...x, folder: dest } : x)));
          const nome = dest === null ? "Sem pasta" : pastas.find((p) => p.id === dest).name;
          fala(`“${mover.s.name}” agora está em ${nome}.`);
          setMover(null);
        }}>
        {mover && <>
          <p>Mover <b>{mover.s.name}</b> — hoje em {mover.s.folder ? pastas.find((p) => p.id === mover.s.folder).name : "Sem pasta"}.</p>
          <div className="pl-pick">
            <span className="pl-pick-l">Pasta de destino</span>
            <div className="pl-pick-opts">
              {[...pastas, { id: 0, name: "Sem pasta" }].map((p) => (
                <button key={p.id} type="button" className={"pl-chip" + (String(mover.destino) === String(p.id) ? " on" : "")}
                  onClick={() => setMover((s) => ({ ...s, destino: p.id }))}>{p.name}</button>
              ))}
            </div>
          </div>
          <p className="pl-modal-alt">Mover não muda quem tem acesso — o compartilhamento é por planilha, não por pasta.</p>
        </>}
      </Confirm>

      <Confirm open={!!share} title="Compartilhar planilha" cta="Compartilhar" ctaTone="primary"
        onClose={() => setShare(null)}
        onConfirm={() => {
          setLista((s) => s.map((x) => (x.id === share.s.id ? { ...x, shares: { users: share.users, roles: share.roles, todos: share.todos } } : x)));
          const n = share.users.length + share.roles.length + share.todos.length;
          fala(n === 0 ? "Compartilhamento removido — só você vê a planilha agora." : `Compartilhada com ${n} destino${n > 1 ? "s" : ""}. Quem entrou recebe notificação.`, 6000);
          setShare(null);
        }}>
        {share && <>
          <p className="pl-modal-alt">Quem recebe pode <b>abrir e editar</b> — o módulo não tem acesso somente-leitura.</p>
          <MultiPick label="Usuários" opcoes={USUARIOS.filter((u) => u !== EU)} valor={share.users} vazio="Ninguém nominal."
            onChange={(v) => setShare((s) => ({ ...s, users: v }))} />
          <MultiPick label="Funções" opcoes={FUNCOES} valor={share.roles} vazio="Nenhuma função — só quem for nomeado acima."
            onChange={(v) => setShare((s) => ({ ...s, roles: v }))} />
          <MultiPick label="Tarefas" opcoes={TODOS_LISTA} valor={share.todos} vazio="Sem vínculo com tarefa."
            onChange={(v) => setShare((s) => ({ ...s, todos: v }))} />
          <p className="pl-modal-alt">Vincular a uma tarefa deixa a planilha aberta para todos os envolvidos nela — o vínculo segue a tarefa, não a pessoa.</p>
        </>}
      </Confirm>

      <Confirm open={!!excluir} title="Excluir esta planilha?" cta="Excluir planilha"
        onClose={() => setExcluir(null)}
        onConfirm={() => { setLista((s) => s.filter((x) => x.id !== excluir.id)); fala(`“${excluir.name}” excluída.`); setExcluir(null); }}>
        {excluir && <>
          <p><b>{excluir.name}</b> sai da lista para você e para quem recebeu o compartilhamento. Não tem lixeira.</p>
          {(excluir.shares.users.length + excluir.shares.roles.length + excluir.shares.todos.length) > 0 &&
            <p className="pl-modal-alt">Hoje ela está compartilhada — quem depende dela perde o acesso na hora.</p>}
        </>}
      </Confirm>
    </div>
  );
}

window.PlanilhasPage = PlanilhasPage;
})();
