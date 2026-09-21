// estoque-contagem.jsx — contagem cíclica / inventário (ESCOPO NOVO).
// Não existe no Blade nem no React vivo: UC-EST-07 cobre só opening_stock. Proposta de tela —
// a contagem não movimenta saldo sozinha; fechá-la GERA um ajuste (UC-EST-05), que é o
// único caminho auditável de mexer no saldo (INV-1).
// IIFE — expõe window.EstContagem. Domínio em estoque-data.jsx.
(() => {
const { useState, useMemo } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const E = () => window.EstData;
const FM = () => window.EstForms || {};
const alturaGrid = (n, dense) => 56 + Math.max(1, n) * (dense ? 50 : 58);

// ══════════ Lista de contagens ══════════
function AbaContagens({ papel, dense, dados, onAbrir, onNova, aviso }) {
  const D = E();
  const { DataTablePro, Button, StatusBadge, EmptyState, Alert } = DS();
  const verPreco = D.can(papel, "preco");
  const rows = dados.filter((c) => D.contagemVisivel(papel, c));
  if (!DataTablePro) return null;
  const colunas = [
    { key: "ct", label: "Contagem", width: 150, sortable: true, resizable: true },
    { key: "data", label: "Data", mono: true, width: 100, sortable: true, sortValue: (r) => r.raw.data },
    { key: "local", label: "Local", width: 130, sortable: true },
    { key: "status", label: "Status", width: 120, sortable: true },
    { key: "itens", label: "Itens", align: "right", width: 74, sortable: true, sortValue: (r) => r.raw.itens.length },
    { key: "contados", label: "Contados", align: "right", width: 100, sortable: true },
    { key: "diverg", label: "Divergências", align: "right", width: 120, sortable: true, sortValue: (r) => D.divergencias(r.raw).length },
    ...(verPreco ? [{ key: "valor", label: "Valor divergente", align: "right", mono: true, width: 150, sortable: true, sortValue: (r) => D.valorDiverg(r.raw) }] : []),
    { key: "ajuste", label: "Ajuste gerado", width: 130 },
    { key: "por", label: "Contado por", width: 120, sortable: true },
  ];
  const linhas = rows.map((c) => {
    const div = D.divergencias(c);
    const contados = c.itens.filter((i) => i.contado != null).length;
    return {
      id: c.id, raw: c,
      state: c.status !== "fechada" ? "urgent" : undefined,
      cells: {
        ct: { primary: c.id, sub: c.itens.length + (c.itens.length === 1 ? " linha" : " linhas") },
        data: D.fmtData(c.data),
        local: D.LOCAIS[c.local].l,
        status: StatusBadge ? <StatusBadge label={D.STATUS_CT[c.status].l} tone={D.STATUS_CT[c.status].tone} /> : D.STATUS_CT[c.status].l,
        itens: c.itens.length,
        contados: contados + "/" + c.itens.length,
        diverg: div.length ? String(div.length) : "—",
        valor: D.fmt(D.valorDiverg(c)),
        ajuste: c.ajuste || (c.status === "fechada" ? "sem ajuste" : "—"),
        por: c.por,
      },
    };
  });
  const abertas = rows.filter((c) => c.status !== "fechada");
  return (
    <>
      <div className="est-toolbar">
        <span className="est-tb-info">Contagem não mexe no saldo. Fechar a contagem <b>gera um ajuste</b> com a diferença — esse sim move o estoque, auditado (INV-1).</span>
        <div className="est-sp" />
        {Button && D.can(papel, "criar") && <Button variant="primary" size="sm" onClick={onNova}>Nova contagem</Button>}
      </div>
      {abertas.length > 0 && Alert &&
        <Alert tone="warn" title={abertas.length === 1 ? "Uma contagem aberta" : abertas.length + " contagens abertas"}>
          Contagem aberta é foto que envelhece: enquanto ela não fecha, venda e transferência continuam mexendo o saldo e a diferença apurada deixa de valer.
        </Alert>}
      <div className="est-tbl">
        {linhas.length
          ? <DataTablePro columns={colunas} rows={linhas} height={alturaGrid(linhas.length, dense)}
              density={dense ? "compact" : "comfortable"} onRowClick={(r) => onAbrir(r.id)} defaultSort={{ key: "data", dir: "desc" }} />
          : EmptyState && <EmptyState variant="first" title="Nenhuma contagem registrada"
              description="Contagem cíclica é conferir um punhado de itens por vez em vez de parar a operação pra inventário geral."
              action={D.can(papel, "criar") && Button ? <Button variant="primary" size="sm" onClick={onNova}>Começar a primeira</Button> : null} />}
      </div>
    </>
  );
}

// ══════════ Drawer de contagem ══════════
function DrawerContagem({ c, papel, onClose, onFechar, onIr, onContar, aviso }) {
  const D = E();
  const { Drawer, DrawerSection, Button, StatusBadge, Alert } = DS();
  if (!Drawer) return null;
  const verPreco = D.can(papel, "preco");
  const div = D.divergencias(c);
  const faltam = c.itens.filter((i) => i.contado == null);
  const podeFechar = D.can(papel, "criar") && c.status !== "fechada" && faltam.length === 0;
  const podeContar = D.can(papel, "criar") && c.status !== "fechada";
  return (
    <Drawer open onClose={onClose} width={620}
      title={c.id}
      subtitle={D.fmtData(c.data) + " · " + D.LOCAIS[c.local].l + " · contado por " + c.por}
      badge={StatusBadge ? <StatusBadge label={D.STATUS_CT[c.status].l} tone={D.STATUS_CT[c.status].tone} /> : D.STATUS_CT[c.status].l}
      footer={<>
        {Button && <Button variant="ghost" onClick={onClose}>Fechar painel</Button>}
        <div className="est-sp" />
        {Button && <Button variant="primary" disabled={!podeFechar}
          onClick={() => onFechar(c)}>
          {c.status === "fechada" ? "Contagem fechada" : faltam.length ? faltam.length + (faltam.length === 1 ? " item sem contagem" : " itens sem contagem") : div.length ? "Fechar e gerar ajuste" : "Fechar sem divergência"}
        </Button>}
      </>}>
      <DrawerSection title={podeContar ? "Linhas a contar" : "Linhas contadas"}>
        <table className="est-drw-tbl">
          <thead><tr><th>Produto</th><th className="num">Sistema</th><th className="num">Contado</th><th className="num">Diferença</th></tr></thead>
          <tbody>
            {c.itens.map((i, idx) => { const p = D.acharProd(i.sku); const dif = i.contado == null ? null : i.contado - i.sistema; return (
              <tr key={i.sku + (i.lote || "")}>
                <td><b>{p.nome}</b><small>{p.sku}{i.lote ? " · lote " + i.lote : ""}</small></td>
                <td className="num mono">{D.fmtQtd(i.sistema)}</td>
                <td className="num">
                  {podeContar
                    ? <div className="est-qtd"><input type="number" min="0" step="any" value={i.contado == null ? "" : i.contado}
                        placeholder="—" aria-label={"Contado de " + p.nome}
                        onChange={(e) => onContar && onContar(c.id, idx, e.target.value)} /><span>{p.un}</span></div>
                    : <span className="mono">{i.contado == null ? "—" : D.fmtQtd(i.contado)}</span>}
                </td>
                <td className={"num mono " + (dif == null ? "" : dif === 0 ? "est-ok" : "est-dif")}>
                  {dif == null ? "—" : dif === 0 ? "confere" : (dif > 0 ? "+" : "") + D.fmtQtd(dif)}
                </td>
              </tr>); })}
          </tbody>
        </table>
      </DrawerSection>
      <DrawerSection title="Apuração">
        <div className="est-fecho">
          <div><small>Linhas</small><b>{c.itens.length}</b></div>
          <div><small>Divergentes</small><b>{div.length}</b></div>
          {verPreco && <div className="warn"><small>Valor divergente</small><b>{D.fmt(D.valorDiverg(c))}</b></div>}
        </div>
        {faltam.length > 0 && Alert && <Alert tone="info" title={faltam.length + (faltam.length === 1 ? " item ainda sem contagem" : " itens ainda sem contagem")}>Digite a quantidade contada na coluna acima. Só dá pra fechar quando toda linha tem número — em branco não é zero.</Alert>}
      </DrawerSection>
      {c.ajuste &&
        <DrawerSection title="Ajuste gerado">
          <button className="est-link-card" onClick={() => onIr({ aba: "ajustes", id: c.ajuste })}>
            <b>{c.ajuste}</b><small>a diferença desta contagem virou este ajuste</small><span>→</span>
          </button>
        </DrawerSection>}
      {c.status !== "fechada" && Alert &&
        <Alert tone="warn" title="Contagem não move saldo">O saldo só muda quando você fecha e o ajuste é lançado. Até aqui é papel — nada foi baixado.</Alert>}
    </Drawer>
  );
}

// ══════════ Nova contagem ══════════
function FormContagem({ papel, lote, onCancelar, onSalvar, aviso }) {
  const D = E();
  const F = FM();
  const { Select, Button, Alert, DatePicker } = DS();
  const verPreco = D.can(papel, "preco");
  const permitidos = D.locaisDe(papel);
  const [local, setLocal] = useState(permitidos.length === 1 ? permitidos[0] : "");
  const [data, setData] = useState(new Date(D.HOJE + "T00:00"));
  const [linhas, setLinhas] = useState([]);
  const [tentou, setTentou] = useState(false);

  const div = linhas.filter((l) => l.contado !== "" && Number(l.contado) !== l.sistema);
  const valor = div.reduce((s, l) => s + Math.abs(Number(l.contado) - l.sistema) * D.acharProd(l.sku).custo, 0);
  const podeSalvar = local && linhas.length > 0;

  if (!D.can(papel, "criar")) {
    return F.semPermissao("Seu papel não abre contagem",
      "O papel " + D.papel(papel).l + " é de consulta. Contagem é do gestor ou do conferente do local.", onCancelar);
  }

  const addProduto = (p) => {
    if (lote && D.lotesDo(p, local).length) {
      setLinhas((v) => [...v, ...D.lotesDo(p, local).map((lt) => ({ sku: p.sku, lote: lt.lote, sistema: lt.qtd, contado: "" }))]);
    } else {
      setLinhas((v) => [...v, { sku: p.sku, lote: "", sistema: D.saldo(p, local), contado: "" }]);
    }
  };
  const todosDoLocal = () => {
    const out = [];
    for (const p of D.PRODUTOS) {
      if (p.enable_stock === 0) continue;
      if (lote && D.lotesDo(p, local).length) for (const lt of D.lotesDo(p, local)) out.push({ sku: p.sku, lote: lt.lote, sistema: lt.qtd, contado: "" });
      else if (D.saldo(p, local) > 0) out.push({ sku: p.sku, lote: "", sistema: D.saldo(p, local), contado: "" });
    }
    setLinhas(out);
    aviso(out.length + " linhas carregadas do saldo atual de " + D.LOCAIS[local].l + ".", "ok");
  };

  return (
    <div className="est-form">
      <section className="est-card">
        <h3>Dados da contagem</h3>
        <div className="est-grid3">
          {Select && <Select label="Local *" value={local}
            onChange={(e) => { setLocal(e.target.value); setLinhas([]); }}
            error={tentou && !local ? "Escolha o local da contagem." : ""}
            options={[{ value: "", label: "Selecione..." }, ...permitidos.map((k) => ({ value: k, label: D.LOCAIS[k].l }))]} />}
          {DatePicker && <DatePicker label="Data *" value={data} onChange={setData} />}
          <div className="est-card-acao">
            {Button && <Button variant="ghost" disabled={!local} onClick={todosDoLocal}>Carregar todo o local</Button>}
            <small>Contagem cíclica é um punhado por vez — carregar tudo é pra fechamento de mês.</small>
          </div>
        </div>
        {Alert && <Alert tone="info" title="Contagem cíclica não para a operação">Conferir um grupo de itens por vez, com frequência, encontra a diferença antes de ela virar orçamento errado. Fechar a contagem gera o ajuste.</Alert>}
      </section>

      <section className="est-card">
        <h3>Linhas a conferir</h3>
        {F.BuscaProduto &&
          <F.BuscaProduto local={local} verPreco={verPreco} aviso={aviso} onAdd={addProduto}
            jaTem={(sku) => linhas.some((l) => l.sku === sku)} />}
        {linhas.length === 0
          ? <div className="est-vazio">Nenhuma linha. Busque um produto ou carregue todo o local.</div>
          : <table className="est-itens">
              <thead>
                <tr>
                  <th>Produto</th>
                  {lote && <th>Lote</th>}
                  <th className="num">Sistema</th>
                  <th className="num">Contado</th>
                  <th className="num">Diferença</th>
                  <th className="num">Remover</th>
                </tr>
              </thead>
              <tbody>
                {linhas.map((l, idx) => {
                  const p = D.acharProd(l.sku);
                  const dif = l.contado === "" ? null : Number(l.contado) - l.sistema;
                  return (
                    <tr key={l.sku + l.lote} className={dif != null && dif !== 0 ? "erro" : ""}>
                      <td><b>{p.nome}</b><small>{p.sku}</small></td>
                      {lote && <td className="mono">{l.lote || "—"}</td>}
                      <td className="num mono">{D.fmtQtd(l.sistema)} {p.un}</td>
                      <td className="num">
                        <div className="est-qtd">
                          <input type="number" min="0" step="any" value={l.contado} placeholder="—"
                            aria-label={"Contado de " + p.nome}
                            onChange={(e) => setLinhas((v) => v.map((x, i) => i === idx ? { ...x, contado: e.target.value } : x))} />
                          <span>{p.un}</span>
                        </div>
                      </td>
                      <td className={"num mono " + (dif == null ? "" : dif === 0 ? "est-ok" : "est-dif")}>
                        {dif == null ? "—" : dif === 0 ? "confere" : (dif > 0 ? "+" : "") + D.fmtQtd(dif)}
                      </td>
                      <td className="num">
                        <button type="button" className="est-x" aria-label={"Remover " + p.nome}
                          onClick={() => setLinhas((v) => v.filter((_, i) => i !== idx))}>✕</button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>}
        {linhas.length > 0 &&
          <div className="est-fecho">
            <div><small>Linhas</small><b>{linhas.length}</b></div>
            <div><small>Sem contagem</small><b>{linhas.filter((l) => l.contado === "").length}</b></div>
            <div className="warn"><small>Divergentes</small><b>{div.length}</b></div>
            {verPreco && <div className="warn"><small>Valor divergente</small><b>{D.fmt(valor)}</b></div>}
          </div>}
      </section>

      <div className="est-form-acoes">
        {Button && <Button variant="ghost" onClick={onCancelar}>Cancelar</Button>}
        <div className="est-sp" />
        {Button && <Button variant="primary" onClick={() => {
          setTentou(true);
          if (!podeSalvar) { aviso("Escolha o local e ao menos uma linha pra contar.", "warn"); return; }
          onSalvar({ local, data: D.iso(data), status: "contando",
            itens: linhas.map((l) => ({ sku: l.sku, lote: l.lote || undefined, sistema: l.sistema, contado: l.contado === "" ? null : Number(l.contado) })) });
        }}>Abrir contagem</Button>}
      </div>
    </div>
  );
}

window.EstContagem = { AbaContagens, DrawerContagem, FormContagem };
})();
