// produto-cadastros.jsx — cadastros de apoio do módulo Produto, importados dos blades:
//   variation/{index,create,edit} .............. aba "Variações"      (sem gate no legado)
//   selling_price_group/{index,create,edit} .... aba "Grupos de preço" (sem gate no legado)
//   unit/{index,create,edit} ................... aba "Unidades"       (unit.view/create/update/delete)
//   taxonomy/{index,create,edit} (type=product)  aba "Categorias"     (category.*)
//   brand/{index,create,edit} .................. aba "Marcas"         (brand.*)
//   warranties/{index,create,edit} ............. aba "Garantias"      (sem gate no legado)
// Tudo em componentes do DS vivo: Button · Input · Select · Textarea · Checkbox · Switch ·
// DataTable · Alert · EmptyState · Skeleton · TagChip · Tooltip (Modal vem do PBUI, que é o DS Modal).
// Expõe window.ProdutoCadastros. Depende de window.PBD, window.PBUI e window.ProdutoPerms.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const Ic = ({ name, size = 14 }) => { const F = (window.I || {})[name]; return F ? <F size={size} /> : null; };
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const D = () => window.PBD || {};
const U = () => window.PBUI || {};

const SEED = {
  variacoes: [
    { id: 1, name: "Acabamento", vals: ["Bastão + ilhós", "Solda simples", "Sem acabamento"] },
    { id: 2, name: "Cor", vals: ["Branco", "Preto", "Vermelho", "Azul", "Ciano", "Magenta", "Amarelo"] },
    { id: 3, name: "Gramatura", vals: ["280g", "380g", "440g"] },
  ],
  grupos: [
    { id: 1, name: "Varejo", desc: "Balcão, preço de tabela.", ativo: true },
    { id: 2, name: "Atacado", desc: "A partir de 20 m² por pedido.", ativo: true },
    { id: 3, name: "Convênio", desc: "Contratos com desconto fixo negociado.", ativo: true },
    { id: 4, name: "Funcionário", desc: "Uso interno — não aparece no PDV.", ativo: false },
  ],
  unidades: [
    { id: 1, name: "Unidade", short: "Un", dec: false, base: null, mult: null },
    { id: 2, name: "Metro quadrado", short: "m²", dec: true, base: null, mult: null },
    { id: 3, name: "Metro linear", short: "m", dec: true, base: null, mult: null },
    { id: 4, name: "Quilograma", short: "kg", dec: true, base: null, mult: null },
    { id: 5, name: "Caixa", short: "cx", dec: false, base: 1, mult: "1000" },
    { id: 6, name: "Peça", short: "pç", dec: false, base: null, mult: null },
  ],
  categorias: [
    { id: 1, name: "Comunicação visual", code: "CV", desc: "Lonas, fachadas, placas.", parent: null },
    { id: 2, name: "Lonas", code: "CV-LON", desc: "", parent: 1 },
    { id: 3, name: "Fachadas", code: "CV-FAC", desc: "", parent: 1 },
    { id: 4, name: "Placas", code: "CV-PLA", desc: "", parent: 1 },
    { id: 5, name: "Impressos", code: "IMP", desc: "Offset e digital.", parent: null },
    { id: 6, name: "Adesivos", code: "ADE", desc: "", parent: null },
    { id: 7, name: "Acabamento", code: "ACB", desc: "Ilhós, bastão, solda.", parent: null },
    { id: 8, name: "Insumos", code: "INS", desc: "Tintas e mídias — não vão pro balcão.", parent: null },
    { id: 9, name: "Serviços", code: "SRV", desc: "Instalação e arte.", parent: null },
  ],
  marcas: [
    { id: 1, name: "Sem marca", desc: "", repair: false },
    { id: 2, name: "Vinilcor", desc: "Lonas e banners.", repair: false },
    { id: 3, name: "3M", desc: "Vinil adesivo e laminação.", repair: false },
    { id: 4, name: "Avery", desc: "Vinil de recorte.", repair: false },
    { id: 5, name: "Coral", desc: "Tintas.", repair: false },
    { id: 6, name: "Suprema", desc: "Papéis e couché.", repair: false },
  ],
  garantias: [
    { id: 1, name: "Sem garantia", desc: "", dur: "", tipo: "" },
    { id: 2, name: "3 meses", desc: "Fachadas e placas instaladas.", dur: "3", tipo: "months" },
    { id: 3, name: "6 meses", desc: "Adesivagem de frota.", dur: "6", tipo: "months" },
    { id: 4, name: "12 meses", desc: "Estrutura metálica e luminosos.", dur: "12", tipo: "months" },
  ],
};
const DUR_TIPO = { days: "dias", months: "meses", years: "anos" };
const novoId = (rows) => Math.max(0, ...rows.map((x) => x.id)) + 1;

const contar = (chave, valor) => (D().PRODUCTS || []).filter((p) => {
  if (chave === "unit") return p.unit === valor;
  if (chave === "brand") return p.brand === valor;
  if (chave === "cat") return p.cat === valor || p.sub === valor;
  if (chave === "variacao") return p.variations.some((v) => v.name.startsWith(valor + " -"));
  return false;
}).length;

// ─────────── Peças (todas sobre o DS) ───────────
function Barra({ busca, setBusca, placeholder, onAdd, addLabel, extra, podeCriar, motivo }) {
  const { Button, Input, Tooltip } = DS();
  const ref = useRef(null);
  useEffect(() => {
    const onKey = (e) => {
      if (e.key !== "/" || e.metaKey || e.ctrlKey) return;
      const t = e.target.tagName;
      if (t === "INPUT" || t === "TEXTAREA" || t === "SELECT") return;
      e.preventDefault();
      const el = ref.current?.querySelector("input");
      el?.focus();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);
  const botao = <Button variant="primary" size="sm" disabled={!podeCriar} onClick={onAdd}><Ic name="plus" size={13} /> {addLabel}</Button>;
  return (
    <div className="pb-filters-h" style={{ marginBottom: 12, gap: 10, alignItems: "flex-end" }}>
      <div ref={ref} style={{ maxWidth: 300, flex: "1 1 260px" }}>
        <Input value={busca} placeholder={placeholder} onChange={(e) => setBusca(e.target.value)} />
      </div>
      <span className="pb-help" style={{ whiteSpace: "nowrap", paddingBottom: 8 }}><kbd>/</kbd> foca · <kbd>⌘K</kbd> troca de tela</span>
      {extra}
      {podeCriar || !Tooltip ? botao : <Tooltip content={motivo}>{botao}</Tooltip>}
    </div>
  );
}
function Uso({ n, filtro, onIr }) {
  const { Button } = DS();
  if (!n) return <span className="pb-uso" style={{ textDecoration: "none", color: "var(--text-mute)" }}>0</span>;
  return <Button size="sm" onClick={() => { window.__PBFiltro = filtro; onIr("lista"); }}>{n}</Button>;
}
function AcoesLinha({ onEditar, onExcluir, podeEditar, podeExcluir, motivo }) {
  const { Button, Tooltip } = DS();
  const editar = <Button size="sm" disabled={!podeEditar} onClick={onEditar}>Editar</Button>;
  return (
    <div style={{ display: "flex", gap: 6 }}>
      {podeEditar || !Tooltip ? editar : <Tooltip content={motivo}>{editar}</Tooltip>}
      {podeExcluir && <Button size="sm" variant="danger" onClick={onExcluir}>Excluir</Button>}
    </div>
  );
}
function EstadoAba({ estado, o, primeiroTexto, onAdd, addLabel, podeCriar }) {
  const { Skeleton, EmptyState, Button } = DS();
  if (estado === "carregando") return Skeleton ? <Skeleton variant="row" count={5} /> : <p className="pb-help">Carregando…</p>;
  if (estado === "sem-permissao") {
    return <EmptyState variant="no-perm" title={"Você não vê " + o}
      description={"Seu papel não tem a permissão de leitura deste cadastro. Quem libera é o administrador, em Papéis — a permissão chama-se " + o + ".view no sistema."} />;
  }
  if (estado === "erro") {
    return <EmptyState variant="error" title="Não foi possível carregar"
      description={"O servidor recusou a leitura de " + o + ". Nada foi alterado — tente recarregar; se persistir, é permissão do papel."}
      action={<Button onClick={() => window.location.reload()}>Recarregar</Button>} />;
  }
  return <EmptyState variant="first" title={"Nenhum registro de " + o} description={primeiroTexto}
    action={podeCriar ? <Button variant="primary" onClick={onAdd}><Ic name="plus" size={13} /> {addLabel}</Button> : null} />;
}
function Confirmar({ pedido, onClose }) {
  const { Modal } = U();
  const { Alert, Button } = DS();
  if (!Modal) return null;
  const bloqueado = pedido.emUso > 0;
  return (
    <Modal titulo={pedido.titulo} onClose={onClose} largura={520}
      acoes={<><Button onClick={onClose}>{bloqueado ? "Fechar" : "Cancelar"}</Button>
        {!bloqueado && <Button variant="danger" onClick={() => { onClose(); pedido.on?.(); }}>{pedido.cta}</Button>}</>}>
      {bloqueado
        ? <Alert tone="danger" title="O servidor recusa esta exclusão">{pedido.emUso} produto(s) usam este registro. Troque o valor nesses produtos primeiro — pela <b>Edição em massa</b> resolve em uma passada.</Alert>
        : <Alert tone="warn" title="Ação sem volta">{pedido.corpo}</Alert>}
    </Modal>
  );
}
function FormModal({ titulo, largura, onClose, podeSalvar, onSalvar, children }) {
  const { Modal } = U();
  const { Button } = DS();
  if (!Modal) return null;
  return (
    <Modal titulo={titulo} largura={largura} onClose={onClose}
      acoes={<><Button onClick={onClose}>Cancelar</Button>
        <Button variant="primary" disabled={!podeSalvar} onClick={onSalvar} kbd="↵">Salvar</Button></>}>
      <div onKeyDown={(e) => { if (e.key === "Enter" && !e.shiftKey && e.target.tagName !== "TEXTAREA" && podeSalvar) { e.preventDefault(); onSalvar(); } }}>
        {children}
      </div>
    </Modal>
  );
}
// Casca comum: permissão → estado → busca → tabela do DS.
function Aba({ perms, base, semGate, estado, rows, busca, setBusca, placeholder, addLabel, onAdd, extra, o, primeiroTexto, ajuda, columns, linhas }) {
  const { DataTable, EmptyState, Button, Alert } = DS();
  const podeVer = base ? perms.can(base + ".view") || perms.can(base + ".create") : true;
  const podeCriar = base ? perms.can(base + ".create") : true;
  if (!podeVer) return <EstadoAba estado="sem-permissao" o={base || o} />;
  if (estado !== "dados") return <EstadoAba estado={estado} o={o} primeiroTexto={primeiroTexto} onAdd={onAdd} addLabel={addLabel} podeCriar={podeCriar} />;
  const vazio = rows.length === 0;
  return (
    <>
      <Barra busca={busca} setBusca={setBusca} placeholder={placeholder} addLabel={addLabel} onAdd={onAdd} extra={extra}
        podeCriar={podeCriar} motivo={"Seu papel não tem " + base + ".create — quem libera é o administrador, em Papéis."} />
      {semGate && Alert &&
        <div style={{ marginBottom: 10 }}>
          <Alert tone="warn" title="Cadastro sem permissão no legado (achado A-P1)">Qualquer usuário com acesso ao menu cria, edita e exclui aqui — o <b>{semGate}</b> não tem <code>can()</code> e a rota só passa pela autenticação. Precisa de decisão de [W] antes do F3.</Alert>
        </div>}
      {ajuda && <p className="pb-help" style={{ marginBottom: 10 }}>{ajuda}</p>}
      {vazio
        ? <EstadoAba estado="vazio" o={o} primeiroTexto={primeiroTexto} onAdd={onAdd} addLabel={addLabel} podeCriar={podeCriar} />
        : linhas.length === 0
          ? <EmptyState variant="no-results" title="Nada com esse termo"
            description={"Nenhum registro de " + o + " casa com “" + busca + "”. Apague a busca pra ver os " + rows.length + " existentes."}
            action={<Button onClick={() => setBusca("")}>Limpar busca</Button>} />
          : <DataTable columns={columns} rows={linhas} />}
    </>
  );
}

// ─────────── Variações (variation/*) ───────────
function AbaVariacoes({ rows, setRows, avisar, onIr, estado, perms }) {
  const [busca, setBusca] = useState("");
  const [edit, setEdit] = useState(null);
  const [confirmar, setConfirmar] = useState(null);
  const { Input, TagChip, Button } = DS();
  const vis = rows.filter((r) => (r.name + " " + r.vals.join(" ")).toLowerCase().includes(busca.toLowerCase()));
  const abrirNovo = () => setEdit({ name: "", vals: [""] });
  const linhas = vis.map((r) => {
    const emUso = contar("variacao", r.name);
    return {
      id: r.id,
      cells: {
        name: { primary: r.name, sub: r.vals.length + " valor(es)" },
        vals: <div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>{r.vals.map((v, i) => TagChip ? <TagChip key={i} label={v} /> : <span className="pb-tag" key={i}>{v}</span>)}</div>,
        uso: <Uso n={emUso} filtro={{ type: "variable" }} onIr={onIr} />,
        acoes: <AcoesLinha podeEditar podeExcluir onEditar={() => setEdit({ ...r })}
          onExcluir={() => setConfirmar({ titulo: "Excluir “" + r.name + "”", cta: "Excluir", emUso, corpo: "O modelo deixa de aparecer no cadastro novo. Nenhum produto usa esses valores hoje.", on: () => { setRows(rows.filter((x) => x.id !== r.id)); avisar("Modelo de variação excluído.", "warn"); } })} />,
      },
    };
  });
  return (
    <>
      <Aba perms={perms} semGate={perms.semGate("variacoes")} estado={estado} rows={rows} busca={busca} setBusca={setBusca}
        placeholder="Buscar variação ou valor…" addLabel="Nova variação" onAdd={abrirNovo} o="variações" linhas={linhas}
        primeiroTexto="Modelo de variação é o atalho do produto variável: cadastre “Cor” uma vez e todo produto novo já oferece os valores."
        ajuda={<>Modelo reaproveitado no cadastro de produto variável: escolher “Cor” já traz os valores abaixo como variações.</>}
        columns={[{ key: "name", label: "Variação", width: 200 }, { key: "vals", label: "Valores" }, { key: "uso", label: "Em uso", align: "right", width: 90 }, { key: "acoes", label: "Ações", width: 160 }]} />
      {edit &&
        <FormModal titulo={edit.id ? "Editar variação" : "Nova variação"} largura={620} onClose={() => setEdit(null)}
          podeSalvar={!!edit.name.trim() && !!edit.vals.filter((v) => v.trim()).length}
          onSalvar={() => { const r = { ...edit, vals: edit.vals.map((v) => v.trim()).filter(Boolean) }; setRows(r.id ? rows.map((x) => x.id === r.id ? r : x) : [...rows, { ...r, id: novoId(rows) }]); setEdit(null); avisar("Modelo de variação “" + r.name + "” salvo.", "ok"); }}>
          <Input label="Nome da variação *" value={edit.name} placeholder="Cor, Acabamento, Gramatura…" onChange={(e) => setEdit({ ...edit, name: e.target.value })} />
          <div style={{ marginTop: 12, display: "flex", flexDirection: "column", gap: 6 }}>
            {edit.vals.map((v, i) => (
              <div key={i} style={{ display: "flex", gap: 6, alignItems: "flex-end" }}>
                <div style={{ flex: 1 }}><Input label={i === 0 ? "Valores da variação *" : undefined} value={v} placeholder={"Valor " + (i + 1)} onChange={(e) => setEdit({ ...edit, vals: edit.vals.map((x, k) => k === i ? e.target.value : x) })} /></div>
                <Button size="sm" disabled={edit.vals.length === 1} onClick={() => setEdit({ ...edit, vals: edit.vals.filter((_, k) => k !== i) })}>✕</Button>
              </div>
            ))}
            <Button size="sm" style={{ alignSelf: "flex-start" }} onClick={() => setEdit({ ...edit, vals: [...edit.vals, ""] })}><Ic name="plus" size={12} /> Adicionar valor</Button>
          </div>
        </FormModal>}
      {confirmar && <Confirmar pedido={confirmar} onClose={() => setConfirmar(null)} />}
    </>
  );
}

// ─────────── Grupos de preço (selling_price_group/*) ───────────
function AbaGrupos({ rows, setRows, avisar, onIr, estado, perms }) {
  const [busca, setBusca] = useState("");
  const [edit, setEdit] = useState(null);
  const [confirmar, setConfirmar] = useState(null);
  const { Input, Textarea, Switch, Button } = DS();
  const vis = rows.filter((r) => (r.name + " " + r.desc).toLowerCase().includes(busca.toLowerCase()));
  const abrirNovo = () => setEdit({ name: "", desc: "", ativo: true });
  const linhas = vis.map((r) => ({
    id: r.id,
    state: r.ativo ? undefined : "archived",
    cells: {
      name: { primary: r.name, sub: r.ativo ? "ativo" : "inativo" },
      desc: r.desc || "—",
      situacao: <Switch checked={r.ativo} label={r.ativo ? "Ativo" : "Inativo"}
        onChange={() => { setRows(rows.map((x) => x.id === r.id ? { ...x, ativo: !x.ativo } : x)); avisar("Grupo “" + r.name + "” " + (r.ativo ? "desativado." : "ativado."), r.ativo ? "warn" : "ok"); }} />,
      acoes: <div style={{ display: "flex", gap: 6 }}>
        <Button size="sm" onClick={() => setEdit({ ...r })}>Editar</Button>
        <Button size="sm" onClick={() => onIr("precos")}>Preços</Button>
        <Button size="sm" variant="danger" onClick={() => setConfirmar({ titulo: "Excluir “" + r.name + "”", cta: "Excluir", emUso: 0, corpo: "Os preços digitados neste grupo somem junto. Vendas já emitidas mantêm o valor praticado. Se a ideia é só esconder, desative.", on: () => { setRows(rows.filter((x) => x.id !== r.id)); avisar("Grupo de preço excluído.", "danger"); } })}>Excluir</Button>
      </div>,
    },
  }));
  return (
    <>
      <Aba perms={perms} semGate={perms.semGate("grupos")} estado={estado} rows={rows} busca={busca} setBusca={setBusca}
        placeholder="Buscar grupo…" addLabel="Novo grupo" onAdd={abrirNovo} o="grupos de preço" linhas={linhas}
        extra={<Button size="sm" onClick={() => onIr("atualizar-preco")}><Ic name="cash" size={13} /> Atualizar preços em planilha</Button>}
        primeiroTexto="Sem grupo cadastrado todo mundo paga o preço de tabela. Crie “Atacado” pra ter um segundo preço no PDV e no orçamento."
        ajuda={<>Cada grupo ativo vira uma coluna em <b>Preços por grupo</b> e uma opção de preço no PDV e no orçamento. Desativar esconde o grupo sem apagar os preços já digitados.</>}
        columns={[{ key: "name", label: "Grupo", width: 200 }, { key: "desc", label: "Descrição" }, { key: "situacao", label: "Situação", width: 140 }, { key: "acoes", label: "Ações", width: 230 }]} />
      {edit &&
        <FormModal titulo={edit.id ? "Editar grupo de preço" : "Novo grupo de preço"} largura={560} onClose={() => setEdit(null)}
          podeSalvar={!!edit.name.trim()}
          onSalvar={() => { setRows(edit.id ? rows.map((x) => x.id === edit.id ? edit : x) : [...rows, { ...edit, id: novoId(rows) }]); setEdit(null); avisar("Grupo “" + edit.name + "” salvo.", "ok"); }}>
          <Input label="Nome *" value={edit.name} placeholder="Atacado" onChange={(e) => setEdit({ ...edit, name: e.target.value })} />
          <div style={{ marginTop: 12 }}><Textarea label="Descrição" value={edit.desc} placeholder="Quando este grupo se aplica." onChange={(e) => setEdit({ ...edit, desc: e.target.value })} /></div>
          <div style={{ marginTop: 12 }}><Switch checked={edit.ativo} label="Grupo ativo" sublabel="Inativo não aparece no PDV nem na exportação de preços." onChange={(v) => setEdit({ ...edit, ativo: v })} /></div>
        </FormModal>}
      {confirmar && <Confirmar pedido={confirmar} onClose={() => setConfirmar(null)} />}
    </>
  );
}

// ─────────── Unidades (unit/*) ───────────
function AbaUnidades({ rows, setRows, avisar, onIr, estado, perms }) {
  const [busca, setBusca] = useState("");
  const [edit, setEdit] = useState(null);
  const [confirmar, setConfirmar] = useState(null);
  const { Input, Select, Switch } = DS();
  const vis = rows.filter((r) => (r.name + " " + r.short).toLowerCase().includes(busca.toLowerCase()));
  const nomeBase = (id) => (rows.find((x) => x.id === Number(id)) || {}).short || "—";
  const abrirNovo = () => setEdit({ name: "", short: "", dec: false, base: null, mult: "" });
  const linhas = vis.map((r) => {
    const emUso = contar("unit", r.short);
    return {
      id: r.id,
      cells: {
        name: { primary: r.name, sub: r.short },
        dec: r.dec ? "Sim" : "Não",
        base: r.base ? "1 " + r.short + " = " + r.mult + " " + nomeBase(r.base) : "—",
        uso: <Uso n={emUso} filtro={{ unit: r.short }} onIr={onIr} />,
        acoes: <AcoesLinha podeEditar={perms.can("unit.update")} podeExcluir={perms.can("unit.delete")}
          motivo="Seu papel não tem unit.update — quem libera é o administrador, em Papéis."
          onEditar={() => setEdit({ ...r, mult: r.mult || "" })}
          onExcluir={() => setConfirmar({ titulo: "Excluir “" + r.name + "”", cta: "Excluir", emUso, corpo: "Nenhum produto usa esta unidade — pode sair.", on: () => { setRows(rows.filter((x) => x.id !== r.id)); avisar("Unidade excluída.", "warn"); } })} />,
      },
    };
  });
  return (
    <>
      <Aba perms={perms} base="unit" estado={estado} rows={rows} busca={busca} setBusca={setBusca}
        placeholder="Buscar unidade…" addLabel="Nova unidade" onAdd={abrirNovo} o="unidades" linhas={linhas}
        primeiroTexto="Todo produto precisa de unidade. Comece pelas três da gráfica: Unidade (Un), Metro quadrado (m²) e Metro linear (m)."
        ajuda={<>Unidade decimal aceita quantidade fracionada (m², kg). Múltiplo de unidade base converte compra em caixa para venda em peça.</>}
        columns={[{ key: "name", label: "Unidade", width: 210 }, { key: "dec", label: "Aceita decimal", width: 130 }, { key: "base", label: "Múltiplo da base", mono: true }, { key: "uso", label: "Produtos", align: "right", width: 100 }, { key: "acoes", label: "Ações", width: 160 }]} />
      {edit &&
        <FormModal titulo={edit.id ? "Editar unidade" : "Nova unidade"} largura={620} onClose={() => setEdit(null)}
          podeSalvar={!!edit.name.trim() && !!edit.short.trim()}
          onSalvar={() => { setRows(edit.id ? rows.map((x) => x.id === edit.id ? edit : x) : [...rows, { ...edit, id: novoId(rows) }]); setEdit(null); avisar("Unidade “" + edit.name + "” salva.", "ok"); }}>
          <div className="pb-grid c2">
            <Input label="Nome *" value={edit.name} placeholder="Metro quadrado" onChange={(e) => setEdit({ ...edit, name: e.target.value })} />
            <Input label="Símbolo *" help="Como aparece na tabela, na OS e na nota." value={edit.short} placeholder="m²" onChange={(e) => setEdit({ ...edit, short: e.target.value })} />
          </div>
          <div style={{ marginTop: 12 }}>
            <Switch checked={edit.dec} label="Aceita quantidade decimal" sublabel="Ligado para m² e kg; desligado para peça e caixa." onChange={(v) => setEdit({ ...edit, dec: v })} />
          </div>
          <div style={{ marginTop: 12 }}>
            <Switch checked={!!edit.base} label="Cadastrar como múltiplo de uma unidade base" sublabel="Ex.: 1 caixa = 1.000 peças. A conversão vale na compra e na venda." onChange={(v) => setEdit({ ...edit, base: v ? rows[0].id : null })} />
          </div>
          {edit.base &&
            <div className="pb-grid c2" style={{ marginTop: 12 }}>
              <Input label="Quantidade da base" value={edit.mult} placeholder="1000" onChange={(e) => setEdit({ ...edit, mult: e.target.value })} />
              <Select label="Unidade base" value={String(edit.base)} onChange={(e) => setEdit({ ...edit, base: Number(e.target.value) })}
                options={rows.filter((x) => !x.base && x.id !== edit.id).map((x) => ({ value: String(x.id), label: x.name + " (" + x.short + ")" }))} />
            </div>}
        </FormModal>}
      {confirmar && <Confirmar pedido={confirmar} onClose={() => setConfirmar(null)} />}
    </>
  );
}

// ─────────── Categorias (taxonomy/*, type=product) ───────────
function AbaCategorias({ rows, setRows, avisar, onIr, estado, perms }) {
  const [busca, setBusca] = useState("");
  const [edit, setEdit] = useState(null);
  const [confirmar, setConfirmar] = useState(null);
  const { Input, Textarea, Select, Switch } = DS();
  const pais = rows.filter((r) => !r.parent);
  const nomePai = (id) => (rows.find((x) => x.id === id) || {}).name || "—";
  const vis = useMemo(() => {
    const t = busca.toLowerCase();
    const ordem = [];
    pais.forEach((p) => { ordem.push(p); rows.filter((c) => c.parent === p.id).forEach((c) => ordem.push(c)); });
    return ordem.filter((r) => (r.name + " " + r.code + " " + r.desc).toLowerCase().includes(t));
  }, [rows, busca]);
  const abrirNovo = () => setEdit({ name: "", code: "", desc: "", parent: null });
  const linhas = vis.map((r) => {
    const emUso = contar("cat", r.name);
    return {
      id: r.id,
      cells: {
        name: { primary: (r.parent ? "↳ " : "") + r.name, sub: r.parent ? "em " + nomePai(r.parent) : "categoria" },
        code: r.code || "—",
        desc: r.desc || "—",
        uso: <Uso n={emUso} filtro={r.parent ? { cat: nomePai(r.parent) } : { cat: r.name }} onIr={onIr} />,
        acoes: <AcoesLinha podeEditar={perms.can("category.update")} podeExcluir={perms.can("category.delete")}
          motivo="Seu papel não tem category.update — quem libera é o administrador, em Papéis."
          onEditar={() => setEdit({ ...r })}
          onExcluir={() => setConfirmar({ titulo: "Excluir “" + r.name + "”", cta: "Excluir", emUso, corpo: r.parent ? "Subcategoria sem produto — sai limpo." : "As subcategorias vão junto.", on: () => { setRows(rows.filter((x) => x.id !== r.id && x.parent !== r.id)); avisar("Categoria excluída.", "warn"); } })} />,
      },
    };
  });
  return (
    <>
      <Aba perms={perms} base="category" estado={estado} rows={rows} busca={busca} setBusca={setBusca}
        placeholder="Buscar categoria ou código…" addLabel="Nova categoria" onAdd={abrirNovo} o="categorias" linhas={linhas}
        primeiroTexto="Categoria é o que faz o relatório de lucro por categoria existir e o SKU sair automático. Comece pelas famílias que você orça."
        ajuda={<>Categoria e subcategoria do produto — as mesmas do filtro do índice e do relatório de lucro por categoria. Código curto entra no SKU automático.</>}
        columns={[{ key: "name", label: "Categoria", width: 240 }, { key: "code", label: "Código", mono: true, width: 110 }, { key: "desc", label: "Descrição" }, { key: "uso", label: "Produtos", align: "right", width: 100 }, { key: "acoes", label: "Ações", width: 160 }]} />
      {edit &&
        <FormModal titulo={edit.id ? "Editar categoria" : "Nova categoria"} largura={620} onClose={() => setEdit(null)}
          podeSalvar={!!edit.name.trim()}
          onSalvar={() => { setRows(edit.id ? rows.map((x) => x.id === edit.id ? edit : x) : [...rows, { ...edit, id: novoId(rows) }]); setEdit(null); avisar("Categoria “" + edit.name + "” salva.", "ok"); }}>
          <div className="pb-grid c2">
            <Input label="Nome da categoria *" value={edit.name} placeholder="Comunicação visual" onChange={(e) => setEdit({ ...edit, name: e.target.value })} />
            <Input label="Código" help="Curto e único — entra no SKU gerado." value={edit.code} placeholder="CV" onChange={(e) => setEdit({ ...edit, code: e.target.value })} />
          </div>
          <div style={{ marginTop: 12 }}><Textarea label="Descrição" value={edit.desc} onChange={(e) => setEdit({ ...edit, desc: e.target.value })} /></div>
          <div style={{ marginTop: 12 }}><Switch checked={!!edit.parent} label="Cadastrar como subcategoria" onChange={(v) => setEdit({ ...edit, parent: v ? pais[0].id : null })} /></div>
          {edit.parent &&
            <div style={{ marginTop: 12 }}>
              <Select label="Categoria pai *" value={String(edit.parent)} onChange={(e) => setEdit({ ...edit, parent: Number(e.target.value) })}
                options={pais.filter((p) => p.id !== edit.id).map((p) => ({ value: String(p.id), label: p.name }))} />
            </div>}
        </FormModal>}
      {confirmar && <Confirmar pedido={confirmar} onClose={() => setConfirmar(null)} />}
    </>
  );
}

// ─────────── Marcas (brand/*) ───────────
function AbaMarcas({ rows, setRows, avisar, onIr, estado, perms }) {
  const [busca, setBusca] = useState("");
  const [edit, setEdit] = useState(null);
  const [confirmar, setConfirmar] = useState(null);
  const { Input, Switch } = DS();
  const vis = rows.filter((r) => (r.name + " " + r.desc).toLowerCase().includes(busca.toLowerCase()));
  const abrirNovo = () => setEdit({ name: "", desc: "", repair: false });
  const linhas = vis.map((r) => {
    const emUso = contar("brand", r.name);
    return {
      id: r.id,
      cells: {
        name: { primary: r.name, sub: r.repair ? "também na Oficina" : "catálogo" },
        desc: r.desc || "—",
        uso: <Uso n={emUso} filtro={{ brand: r.name }} onIr={onIr} />,
        acoes: <AcoesLinha podeEditar={perms.can("brand.update")} podeExcluir={perms.can("brand.delete")}
          motivo="Seu papel não tem brand.update — quem libera é o administrador, em Papéis."
          onEditar={() => setEdit({ ...r })}
          onExcluir={() => setConfirmar({ titulo: "Excluir “" + r.name + "”", cta: "Excluir", emUso, corpo: "Nenhum produto nesta marca — sai limpo.", on: () => { setRows(rows.filter((x) => x.id !== r.id)); avisar("Marca excluída.", "warn"); } })} />,
      },
    };
  });
  return (
    <>
      <Aba perms={perms} base="brand" estado={estado} rows={rows} busca={busca} setBusca={setBusca}
        placeholder="Buscar marca…" addLabel="Nova marca" onAdd={abrirNovo} o="marcas" linhas={linhas}
        primeiroTexto="Marca é opcional no produto, mas é ela que faz o relatório por marca e a lista de aparelhos da Oficina."
        ajuda={<>Marca do produto — filtro do índice, relatório por marca e, quando marcada, lista de marcas de aparelho da Oficina.</>}
        columns={[{ key: "name", label: "Marca", width: 220 }, { key: "desc", label: "Descrição curta" }, { key: "uso", label: "Produtos", align: "right", width: 100 }, { key: "acoes", label: "Ações", width: 160 }]} />
      {edit &&
        <FormModal titulo={edit.id ? "Editar marca" : "Nova marca"} largura={560} onClose={() => setEdit(null)}
          podeSalvar={!!edit.name.trim()}
          onSalvar={() => { setRows(edit.id ? rows.map((x) => x.id === edit.id ? edit : x) : [...rows, { ...edit, id: novoId(rows) }]); setEdit(null); avisar("Marca “" + edit.name + "” salva.", "ok"); }}>
          <Input label="Nome da marca *" value={edit.name} placeholder="Vinilcor" onChange={(e) => setEdit({ ...edit, name: e.target.value })} />
          <div style={{ marginTop: 12 }}><Input label="Descrição curta" value={edit.desc} placeholder="Lonas e banners." onChange={(e) => setEdit({ ...edit, desc: e.target.value })} /></div>
          <div style={{ marginTop: 12 }}><Switch checked={edit.repair} label="Usar como marca de aparelho na Oficina" sublabel="Aparece na abertura de OS de reparo, além do catálogo." onChange={(v) => setEdit({ ...edit, repair: v })} /></div>
        </FormModal>}
      {confirmar && <Confirmar pedido={confirmar} onClose={() => setConfirmar(null)} />}
    </>
  );
}

// ─────────── Garantias (warranties/*) ───────────
function AbaGarantias({ rows, setRows, avisar, onIr, estado, perms }) {
  const [busca, setBusca] = useState("");
  const [edit, setEdit] = useState(null);
  const [confirmar, setConfirmar] = useState(null);
  const { Input, Textarea, Select } = DS();
  const vis = rows.filter((r) => (r.name + " " + r.desc).toLowerCase().includes(busca.toLowerCase()));
  const abrirNovo = () => setEdit({ name: "", desc: "", dur: "", tipo: "months" });
  const linhas = vis.map((r) => ({
    id: r.id,
    cells: {
      name: { primary: r.name, sub: r.dur ? r.dur + " " + DUR_TIPO[r.tipo] : "sem prazo" },
      desc: r.desc || "—",
      dur: r.dur ? r.dur + " " + DUR_TIPO[r.tipo] : "—",
      acoes: <AcoesLinha podeEditar podeExcluir onEditar={() => setEdit({ ...r })}
        onExcluir={() => setConfirmar({ titulo: "Excluir “" + r.name + "”", cta: "Excluir", emUso: 0, corpo: "Produtos com essa garantia ficam sem prazo. OS já emitidas mantêm o texto impresso.", on: () => { setRows(rows.filter((x) => x.id !== r.id)); avisar("Garantia excluída.", "warn"); } })} />,
    },
  }));
  return (
    <>
      <Aba perms={perms} semGate={perms.semGate("garantias")} estado={estado} rows={rows} busca={busca} setBusca={setBusca}
        placeholder="Buscar garantia…" addLabel="Nova garantia" onAdd={abrirNovo} o="garantias" linhas={linhas}
        primeiroTexto="Sem prazo cadastrado o atendimento decide retorno de cabeça. Cadastre os prazos que você já pratica."
        ajuda={<>Prazo de garantia do produto — imprime na OS e na nota, e serve de base pro atendimento aceitar ou recusar retorno.</>}
        columns={[{ key: "name", label: "Garantia", width: 200 }, { key: "desc", label: "Descrição" }, { key: "dur", label: "Duração", mono: true, width: 140 }, { key: "acoes", label: "Ações", width: 160 }]} />
      {edit &&
        <FormModal titulo={edit.id ? "Editar garantia" : "Nova garantia"} largura={560} onClose={() => setEdit(null)}
          podeSalvar={!!edit.name.trim()}
          onSalvar={() => { setRows(edit.id ? rows.map((x) => x.id === edit.id ? edit : x) : [...rows, { ...edit, id: novoId(rows) }]); setEdit(null); avisar("Garantia “" + edit.name + "” salva.", "ok"); }}>
          <Input label="Nome *" value={edit.name} placeholder="12 meses" onChange={(e) => setEdit({ ...edit, name: e.target.value })} />
          <div style={{ marginTop: 12 }}><Textarea label="Descrição" value={edit.desc} placeholder="O que cobre e o que não cobre." onChange={(e) => setEdit({ ...edit, desc: e.target.value })} /></div>
          <div className="pb-grid c2" style={{ marginTop: 12 }}>
            <Input label="Duração *" value={edit.dur} placeholder="12" onChange={(e) => setEdit({ ...edit, dur: e.target.value })} />
            <Select label="Unidade do prazo *" value={edit.tipo} onChange={(e) => setEdit({ ...edit, tipo: e.target.value })}
              options={[{ value: "days", label: "Dias" }, { value: "months", label: "Meses" }, { value: "years", label: "Anos" }]} />
          </div>
        </FormModal>}
      {confirmar && <Confirmar pedido={confirmar} onClose={() => setConfirmar(null)} />}
    </>
  );
}

// ─────────── Tela ───────────
const ABAS = [
  { id: "variacoes", l: "Variações", C: AbaVariacoes },
  { id: "grupos", l: "Grupos de preço", C: AbaGrupos },
  { id: "unidades", l: "Unidades", C: AbaUnidades, base: "unit" },
  { id: "categorias", l: "Categorias", C: AbaCategorias, base: "category" },
  { id: "marcas", l: "Marcas", C: AbaMarcas, base: "brand" },
  { id: "garantias", l: "Garantias", C: AbaGarantias },
];
function ProdutoCadastros({ aba: abaInicial = "variacoes", onIr, avisar, estado = "dados", perms }) {
  const { Widget } = U();
  const [aba, setAba] = useState(abaInicial);
  const [dados, setDados] = useState(SEED);
  const P = perms || (window.ProdutoPerms ? window.ProdutoPerms.criar("administrador") : { can: () => true, semGate: () => null, label: "Administrador" });
  const setRows = (chave) => (rows) => setDados((s) => ({ ...s, [chave]: rows }));
  const vazio = estado === "vazio";
  const atual = ABAS.find((a) => a.id === aba) || ABAS[0];
  if (!Widget) return null;
  return (
    <Widget titulo={<><Ic name="grid" size={13} /> Cadastros de apoio</>} nota={atual.l + " · papel: " + P.label}>
      <nav className="cli-moduletopnav" data-contract="produto-cadastros-abas" aria-label="Cadastros de apoio do produto" style={{ padding: 0, marginBottom: 14, flexWrap: "wrap", rowGap: 4 }}>
        {ABAS.map((a) => {
          const oculta = a.base && !P.can(a.base + ".view") && !P.can(a.base + ".create");
          return (
            <button key={a.id} className={"cli-moduletopnav-tab " + (aba === a.id ? "active" : "")} onClick={() => setAba(a.id)}>
              {a.l}<span className="cli-moduletopnav-n">{oculta ? "—" : (vazio ? 0 : dados[a.id].length)}</span>
            </button>
          );
        })}
      </nav>
      <atual.C rows={vazio ? [] : dados[atual.id]} setRows={setRows(atual.id)} avisar={avisar} onIr={onIr} perms={P}
        estado={estado === "vazio" ? "dados" : estado} />
    </Widget>
  );
}

window.ProdutoCadastros = ProdutoCadastros;
})();
