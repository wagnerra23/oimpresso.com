// estoque-forms.jsx — formulários, drawers, folha e exclusão do módulo Estoque.
// Regras do main (lidas 2026-08-22): R-ADJ-003 recuperado ≤ total · R-XFER-004 origem≠destino ·
// R-XFER-005 status terminal move saldo · INV-2 (rascunho não move) · INV-4 (reserva ≠ baixa) ·
// INV-5 (enable_stock=0 não movimenta) · UC-EST-05 (deletar ajuste reverte) · UC-EST-06 (par de deltas).
// IIFE — expõe window.EstForms. Domínio em estoque-data.jsx.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const DS = () => window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const E = () => window.EstData;

// ══════════ Busca de produto (search_product / get_product_row) ══════════
function BuscaProduto({ local, onAdd, jaTem, aviso, verPreco }) {
  const D = E();
  const { Input } = DS();
  const [q, setQ] = useState("");
  const [open, setOpen] = useState(false);
  const ref = useRef(null);
  useEffect(() => {
    const fora = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener("mousedown", fora);
    return () => document.removeEventListener("mousedown", fora);
  }, []);
  const achados = useMemo(() => {
    const t = q.trim().toLowerCase();
    if (!t) return [];
    return D.PRODUTOS.filter((p) => (p.nome + " " + p.sku).toLowerCase().includes(t)).slice(0, 6);
  }, [q]);
  const bloqueado = !local;
  return (
    <div className="est-busca" ref={ref}>
      {Input &&
        <Input label="Buscar produto" placeholder={bloqueado ? "Escolha o local primeiro" : "Nome ou SKU — ex: lona, PROD-200"}
          value={q} disabled={bloqueado}
          help={bloqueado ? "O saldo depende do local: sem local escolhido não dá pra somar produto." : "Digite e clique no resultado pra lançar a linha. Disponível = físico menos reservado."}
          onChange={(e) => { setQ(e.target.value); setOpen(true); }} />}
      {open && achados.length > 0 &&
        <div className="est-busca-menu" role="listbox">
          {achados.map((p) => {
            const disp = D.disponivel(p, local);
            const res = D.reservado(p, local);
            const dup = jaTem(p.sku);
            const semControle = p.enable_stock === 0;
            return (
              <button key={p.sku} type="button" className={"est-busca-item" + (dup || semControle ? " dup" : "")} role="option"
                onClick={() => {
                  if (semControle) { aviso("Produto sem controle de estoque não movimenta saldo (INV-5) — não entra no lançamento.", "warn"); return; }
                  if (dup) { aviso("Este produto já está na lista — edite a quantidade da linha."); return; }
                  onAdd(p); setQ(""); setOpen(false);
                }}>
                <span className="est-bi-nome">
                  <b>{p.nome}</b>
                  <small>{p.sku} · {p.un}{p.lotes ? " · controlado por lote" : ""}{semControle ? " · sem controle de estoque" : ""}</small>
                </span>
                <span className="est-bi-saldo">
                  {semControle ? "—" : D.fmtQtd(disp) + " " + p.un}
                  <small>{semControle ? "não movimenta" : res ? D.fmtQtd(res) + " reservado" : verPreco ? D.fmt(p.custo) + "/" + p.un : "livre"}</small>
                </span>
              </button>
            );
          })}
        </div>}
    </div>
  );
}

// ══════════ Linhas de item ══════════
// INV-4: o teto é o DISPONÍVEL (físico − reservado), não o físico.
function LinhasItens({ linhas, local, destino, onQtd, onLote, onRemove, verPreco, lote }) {
  const D = E();
  if (!linhas.length) {
    return <div className="est-vazio">Nenhum produto lançado. Use a busca acima — cada produto entra como uma linha.</div>;
  }
  return (
    <table className="est-itens">
      <thead>
        <tr>
          <th>Produto</th>
          {lote && <th>Lote e validade</th>}
          <th className="num">Quantidade</th>
          <th>Efeito no saldo</th>
          {verPreco && <th className="num">Custo unitário</th>}
          {verPreco && <th className="num">Subtotal</th>}
          <th className="num">Remover</th>
        </tr>
      </thead>
      <tbody>
        {linhas.map((l) => {
          const p = D.acharProd(l.sku);
          const lotes = lote ? D.lotesDo(p, local) : [];
          const oLote = lotes.find((x) => x.lote === l.lote);
          const fisico = oLote ? oLote.qtd : D.saldo(p, local);
          const res = oLote ? 0 : D.reservado(p, local);
          const disp = Math.max(0, fisico - res);
          const estoura = l.qtd > disp;
          const faltaLote = lote && lotes.length > 0 && !l.lote;
          const vencido = oLote && D.dias(oLote.val, D.HOJE) < 0;
          const dFis = D.saldo(p, local), dDest = destino ? D.saldo(p, destino) : 0;
          return (
            <tr key={l.sku} className={estoura || faltaLote ? "erro" : ""}>
              <td>
                <b>{p.nome}</b>
                <small>{p.sku} · físico {D.fmtQtd(fisico)}{res ? " · reservado " + D.fmtQtd(res) : ""} · disponível {D.fmtQtd(disp)} {p.un}</small>
                {estoura && <em className="est-erro">Acima do disponível — reserva de venda/OS não pode ser consumida (INV-4).</em>}
                {faltaLote && <em className="est-erro">Produto controlado por lote: escolha o lote.</em>}
              </td>
              {lote &&
                <td>
                  {lotes.length
                    ? <select className="est-lote" value={l.lote || ""} aria-label={"Lote de " + p.nome}
                        onChange={(e) => onLote(l.sku, e.target.value)}>
                        <option value="">Selecione o lote...</option>
                        {lotes.map((x) => <option key={x.lote} value={x.lote}>{x.lote} · val. {D.fmtData(x.val)} · {D.fmtQtd(x.qtd)} {p.un}{D.dias(x.val, D.HOJE) < 0 ? " (vencido)" : ""}</option>)}
                      </select>
                    : <span className="est-sem-lote">sem controle de lote</span>}
                  {vencido && <em className="est-erro">Lote vencido — só ajuste anormal deve consumir.</em>}
                </td>}
              <td className="num">
                <div className="est-qtd">
                  <input type="number" min="0" step="any" value={l.qtd} aria-label={"Quantidade de " + p.nome}
                    onChange={(e) => onQtd(l.sku, Number(e.target.value))} />
                  <span>{p.un}</span>
                </div>
              </td>
              <td>
                <span className="est-delta">
                  <b>{D.LOCAIS[local] ? D.LOCAIS[local].l : "origem"}</b>
                  <span className="mono">{D.fmtQtd(dFis)} → {D.fmtQtd(dFis - l.qtd)}</span>
                </span>
                {destino &&
                  <span className="est-delta ok">
                    <b>{D.LOCAIS[destino].l}</b>
                    <span className="mono">{D.fmtQtd(dDest)} → {D.fmtQtd(dDest + l.qtd)}</span>
                  </span>}
              </td>
              {verPreco && <td className="num mono">{D.fmt(p.custo)}</td>}
              {verPreco && <td className="num mono"><b>{D.fmt(p.custo * l.qtd)}</b></td>}
              <td className="num">
                <button type="button" className="est-x" onClick={() => onRemove(l.sku)} aria-label={"Remover " + p.nome}>✕</button>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

const semPermissao = (titulo, desc, onVoltar) => {
  const { EmptyState } = DS();
  return EmptyState
    ? <EmptyState variant="no-perm" title={titulo} description={desc}
        action={<button className="jc-btn ghost" onClick={onVoltar}>Voltar pra lista</button>} />
    : <div className="est-vazio">{titulo}</div>;
};

// ══════════ Formulário de ajuste ══════════
function FormAjuste({ papel, lote, inicial, onCancelar, onSalvar, aviso }) {
  const D = E();
  const { Input, Select, Textarea, Button, Alert, DatePicker } = DS();
  const verPreco = D.can(papel, "preco");
  const permitidos = D.locaisDe(papel);
  const [local, setLocal] = useState(inicial && inicial.local || (permitidos.length === 1 ? permitidos[0] : ""));
  const [ref, setRef] = useState("");
  const [data, setData] = useState(new Date(D.HOJE + "T00:00"));
  const [tipo, setTipo] = useState(inicial && inicial.tipo || "");
  const [linhas, setLinhas] = useState(inicial && inicial.itens || []);
  const [recuperado, setRecuperado] = useState(String(inicial && inicial.recuperado || "0"));
  const [motivo, setMotivo] = useState(inicial && inicial.motivo || "");
  const [tentou, setTentou] = useState(false);

  const total = linhas.reduce((s, l) => s + D.acharProd(l.sku).custo * l.qtd, 0);
  const rec = Number(String(recuperado).replace(",", ".")) || 0;
  const dispDe = (l) => { const p = D.acharProd(l.sku); const lt = lote && D.lotesDo(p, local).find((x) => x.lote === l.lote); return lt ? lt.qtd : D.disponivel(p, local); };
  const negativa = linhas.some((l) => l.qtd > dispDe(l));
  const semLote = lote && linhas.some((l) => D.lotesDo(D.acharProd(l.sku), local).length > 0 && !l.lote);
  const faltaMotivo = tipo === "abnormal" && !motivo.trim();
  const recExcede = rec > total; // R-ADJ-003
  const podeSalvar = local && tipo && linhas.length > 0 && linhas.every((l) => l.qtd > 0) && !negativa && !semLote && !faltaMotivo && !recExcede;

  const trocarLocal = (v) => { setLocal(v); if (linhas.length) { setLinhas([]); aviso("Local trocado — as linhas foram limpas porque o saldo é por local."); } };

  if (!D.can(papel, "criar")) {
    return semPermissao("Seu papel não lança ajuste",
      "O papel " + D.papel(papel).l + " é de consulta neste módulo. Quem lança é o gestor ou o conferente do depósito.", onCancelar);
  }

  return (
    <div className="est-form">
      <section className="est-card">
        <h3>Dados do ajuste</h3>
        <div className="est-grid4">
          {Select && <Select label="Local *" value={local} onChange={(e) => trocarLocal(e.target.value)}
            error={tentou && !local ? "Escolha o local do ajuste." : ""}
            help={permitidos.length < Object.keys(D.LOCAIS).length ? "Seu papel enxerga só " + permitidos.map((k) => D.LOCAIS[k].l).join(" e ") + "." : ""}
            options={[{ value: "", label: "Selecione..." }, ...permitidos.map((k) => ({ value: k, label: D.LOCAIS[k].l }))]} />}
          {Input && <Input label="Referência" value={ref} placeholder="Gerada automática se vazio" onChange={(e) => setRef(e.target.value)} />}
          {DatePicker && <DatePicker label="Data *" value={data} onChange={setData} />}
          {Select && <Select label="Tipo de ajuste *" value={tipo} onChange={(e) => setTipo(e.target.value)}
            error={tentou && !tipo ? "Normal ou anormal — a régua fiscal depende disso." : ""}
            help="Normal = refile esperado. Anormal = perda evitável, sinistro, vencimento."
            options={[{ value: "", label: "Selecione..." }, { value: "normal", label: "Normal" }, { value: "abnormal", label: "Anormal" }]} />}
        </div>
        {Alert && <Alert tone="info" title="O que este lançamento faz com o saldo">Ajuste de saída baixa o saldo na hora (UC-EST-05). Excluir o ajuste depois devolve a quantidade — é reversível e fica auditado.</Alert>}
      </section>

      <section className="est-card">
        <h3>Produtos ajustados</h3>
        <BuscaProduto local={local} verPreco={verPreco} aviso={aviso}
          onAdd={(p) => setLinhas((v) => [...v, { sku: p.sku, qtd: 1, lote: "" }])}
          jaTem={(sku) => linhas.some((l) => l.sku === sku)} />
        <LinhasItens linhas={linhas} local={local} verPreco={verPreco} lote={lote}
          onQtd={(sku, q) => setLinhas((v) => v.map((l) => l.sku === sku ? { ...l, qtd: q } : l))}
          onLote={(sku, lt) => setLinhas((v) => v.map((l) => l.sku === sku ? { ...l, lote: lt } : l))}
          onRemove={(sku) => setLinhas((v) => v.filter((l) => l.sku !== sku))} />
        {negativa && Alert &&
          <Alert tone="danger" title="Acima do disponível">Alguma linha pede mais do que está livre no local. Reserva de venda ou OS aberta não pode ser consumida por ajuste (INV-4).</Alert>}
      </section>

      <section className="est-card">
        <h3>Fecho do ajuste</h3>
        <div className="est-grid2">
          {verPreco && Input && <Input label="Valor recuperado" value={recuperado} onChange={(e) => setRecuperado(e.target.value)}
            error={recExcede ? "Recuperado não pode passar do total ajustado (" + D.fmt(total) + ")." : ""}
            help="O que voltou como crédito: devolução ao fornecedor, venda de sucata, seguro." />}
          {Textarea && <Textarea label={"Motivo do ajuste" + (tipo === "abnormal" ? " *" : "")} rows={3} value={motivo}
            error={tentou && faltaMotivo ? "Ajuste anormal sem motivo não passa na auditoria." : ""}
            onChange={(e) => setMotivo(e.target.value)} placeholder="Ex: lona riscada na descarga — 3 bobinas inutilizadas." />}
        </div>
        {verPreco &&
          <div className="est-fecho">
            <div><small>Valor ajustado</small><b>{D.fmt(total)}</b></div>
            <div className="ok"><small>Recuperado</small><b>{D.fmt(rec)}</b></div>
            <div className="warn"><small>Perda líquida</small><b>{D.fmt(Math.max(0, total - rec))}</b></div>
          </div>}
        {!verPreco && Alert &&
          <Alert tone="info" title="Valores ocultos">Seu papel não tem a permissão de ver preço de compra, então custo, subtotal e total do ajuste não aparecem. A quantidade é o que você lança.</Alert>}
      </section>

      <div className="est-form-acoes">
        {Button && <Button variant="ghost" onClick={onCancelar}>Cancelar</Button>}
        <div className="est-sp" />
        {Button && <Button variant="primary" onClick={() => {
          setTentou(true);
          if (!podeSalvar) { aviso(recExcede ? "Recuperado passou do total ajustado (R-ADJ-003)." : "Faltam campos obrigatórios pra salvar o ajuste.", "warn"); return; }
          onSalvar({ local, tipo, itens: linhas, motivo, recuperado: rec, total });
        }}>Salvar ajuste</Button>}
      </div>
    </div>
  );
}

// ══════════ Formulário de transferência (create + edit) ══════════
function FormTransferencia({ papel, lote, editar, onCancelar, onSalvar, aviso }) {
  const D = E();
  const { Input, Select, Textarea, Button, Alert, DatePicker } = DS();
  const verPreco = D.can(papel, "preco");
  const permitidos = D.locaisDe(papel);
  const [data, setData] = useState(new Date((editar ? editar.data : D.HOJE) + "T00:00"));
  const [ref, setRef] = useState(editar ? editar.ref : "");
  const [status, setStatus] = useState(editar ? editar.status : "");
  const [de, setDe] = useState(editar ? editar.de : "");
  const [para, setPara] = useState(editar ? editar.para : "");
  const [linhas, setLinhas] = useState(editar ? editar.itens.map((i) => ({ ...i })) : []);
  const [frete, setFrete] = useState(editar ? String(editar.frete) : "0");
  const [obs, setObs] = useState(editar ? editar.obs : "");
  const [tentou, setTentou] = useState(false);

  const total = linhas.reduce((s, l) => s + D.acharProd(l.sku).custo * l.qtd, 0);
  const fr = Number(String(frete).replace(",", ".")) || 0;
  const mesmoLocal = de && para && de === para;
  const dispDe = (l) => { const p = D.acharProd(l.sku); const lt = lote && D.lotesDo(p, de).find((x) => x.lote === l.lote); return lt ? lt.qtd : D.disponivel(p, de); };
  const negativa = linhas.some((l) => l.qtd > dispDe(l));
  const semLote = lote && linhas.some((l) => D.lotesDo(D.acharProd(l.sku), de).length > 0 && !l.lote);
  const podeSalvar = de && para && !mesmoLocal && status && linhas.length > 0 && linhas.every((l) => l.qtd > 0) && !negativa && !semLote;
  const move = status && D.STATUS_TRF[status].move;
  const qtdTotal = linhas.reduce((s, l) => s + l.qtd, 0);

  const trocarDe = (v) => { setDe(v); if (linhas.length) { setLinhas([]); aviso("Origem trocada — as linhas foram limpas porque o saldo é da origem."); } };

  if (!D.can(papel, editar ? "editar" : "criar")) {
    return semPermissao(editar ? "Seu papel não edita transferência" : "Seu papel não cria transferência",
      "O papel " + D.papel(papel).l + " não tem essa permissão. No vivo quem faz é quem tem purchase.create.", onCancelar);
  }

  return (
    <div className="est-form">
      <section className="est-card">
        <h3>Dados da transferência</h3>
        <div className="est-grid3">
          {DatePicker && <DatePicker label="Data *" value={data} onChange={setData} />}
          {Input && <Input label="Referência" value={ref} placeholder="Gerada automática se vazio" onChange={(e) => setRef(e.target.value)} />}
          {Select && <Select label="Status *" value={status} onChange={(e) => setStatus(e.target.value)}
            error={tentou && !status ? "Escolha o status." : ""}
            help={status ? D.STATUS_TRF[status].efeito : "O status decide se o saldo se move agora ou só reserva."}
            options={[{ value: "", label: "Selecione..." }, ...Object.keys(D.STATUS_TRF).map((k) => ({ value: k, label: D.STATUS_TRF[k].l }))]} />}
        </div>
        <div className="est-grid2">
          {Select && <Select label="Local de origem *" value={de} onChange={(e) => trocarDe(e.target.value)}
            error={tentou && !de ? "Escolha de onde o material sai." : ""}
            options={[{ value: "", label: "Selecione..." }, ...permitidos.map((k) => ({ value: k, label: D.LOCAIS[k].l }))]} />}
          {Select && <Select label="Local de destino *" value={para} onChange={(e) => setPara(e.target.value)}
            error={mesmoLocal ? "Origem e destino não podem ser o mesmo local." : tentou && !para ? "Escolha pra onde o material vai." : ""}
            options={[{ value: "", label: "Selecione..." }, ...Object.keys(D.LOCAIS).filter((k) => k !== de).map((k) => ({ value: k, label: D.LOCAIS[k].l }))]} />}
        </div>
        {status && Alert &&
          <Alert tone={move ? "warn" : "info"} title={move ? "Este status move o saldo agora" : "Este status ainda não move o saldo"}>
            {D.STATUS_TRF[status].efeito} {move ? "Depois de concluída, o material já pode ser vendido no destino." : "Rascunho e trânsito só reservam — quem recebe é quem conclui (INV-2)."}
          </Alert>}
      </section>

      <section className="est-card">
        <h3>Produtos transferidos</h3>
        <BuscaProduto local={de} verPreco={verPreco} aviso={aviso}
          onAdd={(p) => setLinhas((v) => [...v, { sku: p.sku, qtd: 1, lote: "" }])}
          jaTem={(sku) => linhas.some((l) => l.sku === sku)} />
        <LinhasItens linhas={linhas} local={de} destino={para} verPreco={verPreco} lote={lote}
          onQtd={(sku, q) => setLinhas((v) => v.map((l) => l.sku === sku ? { ...l, qtd: q } : l))}
          onLote={(sku, lt) => setLinhas((v) => v.map((l) => l.sku === sku ? { ...l, lote: lt } : l))}
          onRemove={(sku) => setLinhas((v) => v.filter((l) => l.sku !== sku))} />
        {negativa && Alert &&
          <Alert tone="danger" title="Acima do disponível na origem">Não dá pra transferir o que está reservado em venda ou OS aberta em {de ? D.LOCAIS[de].l : "—"} (INV-4).</Alert>}
        {linhas.length > 0 && de && para &&
          <div className="est-conserv">
            <span>Conservação do total</span>
            <b className="mono">{D.fmtQtd(qtdTotal)} sai de {D.LOCAIS[de].l} · {D.fmtQtd(qtdTotal)} entra em {D.LOCAIS[para].l}</b>
            <small>Transferência não cria nem destrói saldo — o total da empresa fica igual (UC-EST-06).</small>
          </div>}
      </section>

      <section className="est-card">
        <h3>Frete e observação</h3>
        <div className="est-grid2">
          {verPreco && Input && <Input label="Frete" value={frete} onChange={(e) => setFrete(e.target.value)} help="Entra no custo do material que chega no destino." />}
          {Textarea && <Textarea label="Observação" rows={3} value={obs} onChange={(e) => setObs(e.target.value)} placeholder="Ex: carga sai às 14h — motorista Jorge." />}
        </div>
        {verPreco &&
          <div className="est-fecho">
            <div><small>Subtotal dos itens</small><b>{D.fmt(total)}</b></div>
            <div><small>Frete</small><b>{D.fmt(fr)}</b></div>
            <div className="tot"><small>Total da transferência</small><b>{D.fmt(total + fr)}</b></div>
          </div>}
      </section>

      <div className="est-form-acoes">
        {Button && <Button variant="ghost" onClick={onCancelar}>Cancelar</Button>}
        <div className="est-sp" />
        {Button && <Button variant="primary" onClick={() => {
          setTentou(true);
          if (!podeSalvar) { aviso("Faltam campos obrigatórios pra salvar a transferência.", "warn"); return; }
          onSalvar({ de, para, status, frete: fr, obs, itens: linhas, data: D.iso(data), ref: ref || "TR2026/nova" });
        }}>{editar ? "Salvar alterações" : "Salvar transferência"}</Button>}
      </div>
    </div>
  );
}

// ══════════ Exclusão com consequência (UC-EST-05) ══════════
function ModalExcluir({ alvo, onClose, onConfirmar }) {
  const D = E();
  const { Modal, Button, Alert } = DS();
  if (!Modal || !alvo) return null;
  const ajuste = alvo.tipo === "ajuste";
  return (
    <Modal open onClose={onClose} title={"Excluir " + alvo.id} width={480}
      footer={<>
        {Button && <Button variant="ghost" onClick={onClose}>Cancelar</Button>}
        {Button && <Button variant="danger" onClick={() => onConfirmar(alvo)}>Excluir e reverter</Button>}
      </>}>
      <p className="est-modal-p">
        {ajuste
          ? "Excluir o ajuste devolve a quantidade ao saldo do local — o estoque volta ao que era antes do lançamento."
          : "Excluir a transferência desfaz o movimento entre os locais: o saldo volta pra origem."}
      </p>
      <ul className="est-modal-ul">
        {alvo.itens.map((i) => { const p = D.acharProd(i.sku); return (
          <li key={i.sku}><b>{p.nome}</b> <span className="mono">+{D.fmtQtd(i.qtd)} {p.un}</span> {i.lote ? <small>lote {i.lote}</small> : null}</li>); })}
      </ul>
      {Alert && <Alert tone="warn" title="Fica no histórico">A exclusão é auditada (INV-1): quem excluiu, quando e o que voltou. Não é apagar, é reverter.</Alert>}
    </Modal>
  );
}

// ══════════ Folha de impressão (stock-transfers/print) ══════════
function Barras({ texto }) {
  const barras = useMemo(() => {
    const out = [];
    for (let i = 0; i < texto.length; i++) {
      const c = texto.charCodeAt(i);
      for (let b = 0; b < 4; b++) out.push(((c >> b) & 1) ? 3 : 1);
    }
    return out;
  }, [texto]);
  return (
    <div className="est-barcode" aria-label={"Código de barras " + texto}>
      <div className="est-barcode-bars">
        {barras.map((w, i) => <span key={i} style={{ width: w + "px", background: i % 2 ? "transparent" : "var(--text)" }} />)}
      </div>
      <span className="est-barcode-txt">{texto}</span>
    </div>
  );
}

function FolhaTransferencia({ t, verPreco, onFechar }) {
  const D = E();
  const { ProofFrame, RegistrationMark, Button } = DS();
  const sub = D.totalItens(t.itens);
  const End = ({ k }) => (
    <address>
      <strong>{D.LOCAIS[k].l}</strong>
      <span>{D.LOCAIS[k].end}</span>
      <span>{D.LOCAIS[k].cidade} · CEP {D.LOCAIS[k].cep}</span>
      <span>CNPJ {D.LOCAIS[k].cnpj}</span>
      <span>Tel. {D.LOCAIS[k].tel}</span>
    </address>
  );
  const miolo = (
    <div className="est-folha-in">
      <header>
        <div className="est-folha-h-l">
          {RegistrationMark && <RegistrationMark size={22} />}
          <div>
            <h1>Transferência de estoque</h1>
            <p>Ref. <b>#{t.ref}</b> · {D.fmtData(t.data)} · lançada por {t.por}</p>
          </div>
        </div>
        <div className="est-folha-h-r">
          <small>Status</small><b>{D.STATUS_TRF[t.status].l}</b>
        </div>
      </header>
      <div className="est-folha-ends">
        <div><small>Sai de</small><End k={t.de} /></div>
        <div><small>Vai para</small><End k={t.para} /></div>
        <div className="est-folha-meta">
          <small>Documento</small>
          <b>{t.id}</b>
          <span>{t.itens.length} produto{t.itens.length > 1 ? "s" : ""}</span>
          <span>Emissão {D.fmtData(D.HOJE)}</span>
        </div>
      </div>
      <table className="est-folha-tbl">
        <thead>
          <tr><th>#</th><th>Produto</th><th className="num">Qtd</th>{verPreco && <th className="num">Subtotal</th>}</tr>
        </thead>
        <tbody>
          {t.itens.map((i, n) => { const p = D.acharProd(i.sku); return (
            <tr key={i.sku}>
              <td className="mono">{n + 1}</td>
              <td>
                <b>{p.nome}</b><small>{p.sku}</small>
                {i.lote && <small>Lote e validade: {i.lote} · {D.fmtData((D.lotesDo(p).find((x) => x.lote === i.lote) || {}).val)}</small>}
              </td>
              <td className="num mono">{D.fmtQtd(i.qtd)} {p.un}</td>
              {verPreco && <td className="num mono">{D.fmt(p.custo * i.qtd)}</td>}
            </tr>); })}
        </tbody>
      </table>
      {verPreco &&
        <div className="est-folha-tot">
          <div><span>Total líquido</span><b>{D.fmt(sub)}</b></div>
          {t.frete > 0 && <div><span>Frete adicional (+)</span><b>{D.fmt(t.frete)}</b></div>}
          <div className="tot"><span>Total da transferência</span><b>{D.fmt(sub + t.frete)}</b></div>
        </div>}
      <div className="est-folha-obs">
        <small>Observação</small>
        <p>{t.obs || "—"}</p>
      </div>
      <div className="est-folha-assin">
        <div><span /><small>Conferente da origem</small></div>
        <div><span /><small>Motorista / transporte</small></div>
        <div><span /><small>Recebedor do destino</small></div>
      </div>
      <Barras texto={t.ref} />
    </div>
  );
  return (
    <div className="est-folha-back">
      <div className="est-folha-bar no-print">
        {Button && <Button variant="ghost" onClick={onFechar}>Fechar</Button>}
        <span>Folha de transferência · substitui a print blade em aba nova</span>
        <div className="est-sp" />
        {Button && <Button variant="primary" onClick={() => window.print()}>Imprimir</Button>}
      </div>
      <div className="est-folha">
        {ProofFrame ? <ProofFrame cropMarks grid padding={28}>{miolo}</ProofFrame> : miolo}
      </div>
    </div>
  );
}

// ══════════ Drawers ══════════
function F({ l, v, mono }) {
  return <div className="est-f"><label>{l}</label><span className={mono ? "mono" : ""}>{v}</span></div>;
}

function Links({ itens, onIr }) {
  return (
    <div className="est-links">
      {itens.map((k) => <button key={k.r} className="est-link-card" onClick={() => onIr(k.r)}><b>{k.l}</b><small>{k.s}</small><span>→</span></button>)}
    </div>
  );
}

function DrawerAjuste({ a, papel, lote, onClose, onIr, onExcluir, aviso }) {
  const D = E();
  const { Drawer, DrawerSection, Button, StatusBadge, Alert } = DS();
  if (!Drawer) return null;
  const verPreco = D.can(papel, "preco");
  const total = D.totalItens(a.itens);
  return (
    <Drawer open onClose={onClose} width={580}
      title={a.id}
      subtitle={a.ref + " · " + D.fmtData(a.data) + " · " + D.LOCAIS[a.local].l}
      badge={StatusBadge ? <StatusBadge label={D.TIPOS[a.tipo]} tone={a.tipo === "abnormal" ? "warning" : "neutral"} /> : D.TIPOS[a.tipo]}
      footer={<>
        {Button && <Button variant="ghost" onClick={onClose}>Fechar</Button>}
        <div className="est-sp" />
        {D.can(papel, "excluir") && Button && <Button variant="danger" onClick={() => onExcluir(a.id)}>Excluir</Button>}
        {verPreco && a.tipo === "abnormal" && Button &&
          <Button variant="primary" onClick={() => { onIr("financeiro"); aviso("Perda anormal vai como despesa no Financeiro — protótipo, nada foi lançado."); }}>Lançar perda no financeiro</Button>}
      </>}>
      <DrawerSection title="Dados do ajuste">
        <div className="est-fields">
          <F l="Referência" v={a.ref} mono />
          <F l="Data" v={D.fmtData(a.data)} mono />
          <F l="Local" v={D.LOCAIS[a.local].l} />
          <F l="Tipo" v={D.TIPOS[a.tipo]} />
          <F l="Lançado por" v={a.por} />
          <F l="Origem do lançamento" v={a.contagem ? "Contagem " + a.contagem : "Manual"} />
        </div>
      </DrawerSection>
      <DrawerSection title="Itens ajustados">
        <table className="est-drw-tbl">
          <thead><tr><th>Produto</th><th className="num">Qtd</th>{verPreco && <th className="num">Custo</th>}{verPreco && <th className="num">Subtotal</th>}</tr></thead>
          <tbody>
            {a.itens.map((i) => { const p = D.acharProd(i.sku); const lt = i.lote && D.lotesDo(p).find((x) => x.lote === i.lote); return (
              <tr key={i.sku}>
                <td><b>{p.nome}</b><small>{p.sku}{lote && i.lote ? " · lote " + i.lote + " · val. " + D.fmtData(lt && lt.val) : ""}</small></td>
                <td className="num mono">{D.fmtQtd(i.qtd)} {p.un}</td>
                {verPreco && <td className="num mono">{D.fmt(p.custo)}</td>}
                {verPreco && <td className="num mono"><b>{D.fmt(p.custo * i.qtd)}</b></td>}
              </tr>); })}
          </tbody>
        </table>
      </DrawerSection>
      {verPreco &&
        <DrawerSection title="Fecho">
          <div className="est-fecho">
            <div><small>Valor ajustado</small><b>{D.fmt(total)}</b></div>
            <div className="ok"><small>Recuperado</small><b>{D.fmt(a.recuperado)}</b></div>
            <div className="warn"><small>Perda líquida</small><b>{D.fmt(total - a.recuperado)}</b></div>
          </div>
        </DrawerSection>}
      <DrawerSection title="Motivo">{a.motivo || "—"}</DrawerSection>
      <DrawerSection title="Onde este ajuste aparece">
        <Links onIr={onIr} itens={[
          { r: "prod-historico", l: "Histórico do produto", s: "a mesma baixa vista pela ficha" },
          { r: "rel-estoque", l: "Relatório de ajuste de estoque", s: "stock_adjustment_report" },
          { r: "financeiro", l: "Financeiro", s: "perda anormal como despesa" },
        ]} />
      </DrawerSection>
      {a.tipo === "abnormal" && Alert &&
        <Alert tone="warn" title="Ajuste anormal">Perda evitável entra na apuração de resultado e pede justificativa em auditoria. O motivo acima é o que a auditoria lê.</Alert>}
    </Drawer>
  );
}

function DrawerTransferencia({ t, papel, lote, onClose, onStatus, onEditar, onImprimir, onExcluir, onIr, aviso }) {
  const D = E();
  const { Drawer, DrawerSection, Button, StatusBadge, Modal, Select, Alert } = DS();
  const [modal, setModal] = useState(false);
  const [novo, setNovo] = useState(t.status);
  useEffect(() => { setNovo(t.status); }, [t.status]);
  if (!Drawer) return null;
  const verPreco = D.can(papel, "preco");
  const sub = D.totalItens(t.itens);
  const terminal = D.STATUS_TRF[t.status].move;
  const podeStatus = D.can(papel, "status") && !terminal;
  const podeEditar = D.can(papel, "editar") && !terminal;
  return (
    <>
      <Drawer open onClose={onClose} width={620}
        title={t.id}
        subtitle={t.ref + " · " + D.fmtData(t.data) + " · " + D.LOCAIS[t.de].l + " → " + D.LOCAIS[t.para].l}
        badge={StatusBadge ? <StatusBadge label={D.STATUS_TRF[t.status].l} tone={D.STATUS_TRF[t.status].tone} /> : D.STATUS_TRF[t.status].l}
        footer={<>
          {Button && <Button variant="ghost" onClick={onClose}>Fechar</Button>}
          <div className="est-sp" />
          {Button && <Button variant="ghost" onClick={() => onImprimir(t.id)}>Folha</Button>}
          {podeEditar && Button && <Button variant="ghost" onClick={() => onEditar(t.id)}>Editar</Button>}
          {D.can(papel, "excluir") && Button && <Button variant="danger" onClick={() => onExcluir(t.id)}>Excluir</Button>}
          {Button && <Button variant="primary" onClick={() => setModal(true)} disabled={!podeStatus}>
            {terminal ? "Terminal" : podeStatus ? "Atualizar status" : "Sem permissão"}
          </Button>}
        </>}>
        <DrawerSection title="Origem e destino">
          <div className="est-rota">
            <div><label>Sai de</label><b>{D.LOCAIS[t.de].l}</b><small>{D.LOCAIS[t.de].end}</small><small>{D.LOCAIS[t.de].cidade}</small><small>{D.LOCAIS[t.de].tel}</small></div>
            <span className="est-rota-arr" aria-hidden="true">→</span>
            <div><label>Vai para</label><b>{D.LOCAIS[t.para].l}</b><small>{D.LOCAIS[t.para].end}</small><small>{D.LOCAIS[t.para].cidade}</small><small>{D.LOCAIS[t.para].tel}</small></div>
          </div>
        </DrawerSection>
        <DrawerSection title="Itens">
          <table className="est-drw-tbl">
            <thead><tr><th>Produto</th><th className="num">Qtd</th>{verPreco && <th className="num">Custo</th>}{verPreco && <th className="num">Subtotal</th>}</tr></thead>
            <tbody>
              {t.itens.map((i) => { const p = D.acharProd(i.sku); const lt = i.lote && D.lotesDo(p).find((x) => x.lote === i.lote); return (
                <tr key={i.sku}>
                  <td><b>{p.nome}</b><small>{p.sku}{lote && i.lote ? " · lote " + i.lote + " · val. " + D.fmtData(lt && lt.val) : ""}</small></td>
                  <td className="num mono">{D.fmtQtd(i.qtd)} {p.un}</td>
                  {verPreco && <td className="num mono">{D.fmt(p.custo)}</td>}
                  {verPreco && <td className="num mono"><b>{D.fmt(p.custo * i.qtd)}</b></td>}
                </tr>); })}
            </tbody>
          </table>
        </DrawerSection>
        {verPreco &&
          <DrawerSection title="Totais">
            <div className="est-fecho">
              <div><small>Subtotal dos itens</small><b>{D.fmt(sub)}</b></div>
              <div><small>Frete</small><b>{D.fmt(t.frete)}</b></div>
              <div className="tot"><small>Total</small><b>{D.fmt(sub + t.frete)}</b></div>
            </div>
          </DrawerSection>}
        <DrawerSection title="Observação">{t.obs || "—"}</DrawerSection>
        <DrawerSection title="Onde esta transferência aparece">
          <Links onIr={onIr} itens={[
            { r: "prod-estoque", l: "Estoque do produto", s: "saldo por local depois da conclusão" },
            { r: "rel-estoque", l: "Relatórios de estoque", s: "detalhes de estoque do produto" },
          ]} />
        </DrawerSection>
        {!terminal && Alert &&
          <Alert tone="warn" title="Material em rota">{D.STATUS_TRF[t.status].efeito} Quem recebe é quem conclui — até lá o destino não pode vender.</Alert>}
      </Drawer>
      {Modal && modal &&
        <Modal open onClose={() => setModal(false)} title={"Atualizar status · " + t.id}
          footer={<>
            {Button && <Button variant="ghost" onClick={() => setModal(false)}>Cancelar</Button>}
            {Button && <Button variant="primary" onClick={() => { onStatus(t.id, novo); setModal(false); aviso("Status de " + t.id + " agora é " + D.STATUS_TRF[novo].l + ".", "ok"); }}>Atualizar</Button>}
          </>}>
          {Select && <Select label="Status" value={novo} onChange={(e) => setNovo(e.target.value)}
            help={D.STATUS_TRF[novo] ? D.STATUS_TRF[novo].efeito : ""}
            options={Object.keys(D.STATUS_TRF).map((k) => ({ value: k, label: D.STATUS_TRF[k].l }))} />}
          <div className="est-modal-delta">
            {t.itens.map((i) => { const p = D.acharProd(i.sku); const o = D.saldo(p, t.de), d = D.saldo(p, t.para); const mv = D.STATUS_TRF[novo] && D.STATUS_TRF[novo].move; return (
              <div key={i.sku}>
                <b>{p.sku}</b>
                <span className="mono">{D.LOCAIS[t.de].l} {D.fmtQtd(o)} → {D.fmtQtd(mv ? o - i.qtd : o)}</span>
                <span className="mono">{D.LOCAIS[t.para].l} {D.fmtQtd(d)} → {D.fmtQtd(mv ? d + i.qtd : d)}</span>
              </div>); })}
          </div>
        </Modal>}
    </>
  );
}

window.EstForms = { BuscaProduto, LinhasItens, FormAjuste, FormTransferencia, FolhaTransferencia, DrawerAjuste, DrawerTransferencia, ModalExcluir, semPermissao };
})();
