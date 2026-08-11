/* Financeiro da venda — condição de pagamento, geração e edição de múltiplas parcelas.
   Equivalente à aba "Financeiro" + diálogo "Parcela" do legado. */

const CONDICOES = [
  { id: '18', nome: 'PIX', parcelas: 1, intervalo: 0, tipo: 'PIX' },
  { id: '02', nome: 'Boleto 30/60', parcelas: 2, intervalo: 30, tipo: 'Boleto' },
  { id: '05', nome: 'Cartão 3x sem juros', parcelas: 3, intervalo: 30, tipo: 'Cartão de crédito' },
  { id: '09', nome: 'Entrada + 2x', parcelas: 3, intervalo: 28, tipo: 'Boleto' },
  { id: '12', nome: 'À vista — dinheiro', parcelas: 1, intervalo: 0, tipo: 'Dinheiro' },
];
const PLANOS = ['1.1.5 — Recebido em depósito', '1.1.1 — Caixa', '1.2.1 — Duplicatas a receber'];
const CONTAS = ['1 — Caixa financeiro', '2 — Banco Itaú c/c', '3 — Banco Sicredi'];
const LANC = ['A RECEBER', 'RECEBIDA'];

const hoje0 = () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d; };
const dia0 = (v) => { const d = new Date(v); d.setHours(0, 0, 0, 0); return d; };
const vencida = (v) => dia0(v) < hoje0();
const addDias = (base, dias) => { const d = new Date(base.getTime()); d.setDate(d.getDate() + dias); return d; };
const dBR = (d) => d.toLocaleDateString('pt-BR');

/* Divide `total` em n parcelas com centavo de ajuste na PRIMEIRA (evita soma ≠ total) */
function ratear(total, n) {
  const cent = Math.round(submitSafe(total) * 100);
  const base = Math.floor(cent / n);
  const resto = cent - base * n;
  return Array.from({ length: n }, (_, i) => (base + (i < resto ? 1 : 0)) / 100);
}

function ParcelasDrawer({ open, onClose, total, parcelas, setParcelas, docBase, onOpen }) {
  const meta = useMeta();
  const [cond, setCond] = React.useState('18');
  const [n, setN] = React.useState('2');
  const [intervalo, setIntervalo] = React.useState('30');
  const [porMes, setPorMes] = React.useState(true);
  const [caixa, setCaixa] = React.useState(CONTAS[0]);
  const [primeiro, setPrimeiro] = React.useState(hoje0);
  const [edit, setEdit] = React.useState(null);
  const [undo, setUndo] = React.useState(null);
  React.useEffect(() => { if (!undo) return; const t = setTimeout(() => setUndo(null), 7000); return () => clearTimeout(t); }, [undo]);

  const c = CONDICOES.find((x) => x.id === cond) || CONDICOES[0];
  const soma = submitSafe(parcelas.reduce((s, p) => s + parseBR(p.valor), 0));
  const dif = submitSafe(total - soma);

  const aplicarCondicao = (id) => {
    const x = CONDICOES.find((y) => y.id === id) || CONDICOES[0];
    setCond(id); setN(String(x.parcelas)); setIntervalo(String(x.intervalo));
  };

  const gerar = () => {
    const qtd = Math.max(1, Math.min(48, Math.round(parseBR(n)) || 1));
    const passo = porMes ? 30 : (Math.round(parseBR(intervalo)) || 0);
    const valores = ratear(total, qtd);
    setParcelas(valores.map((v, i) => ({
      k: Date.now() + i, num: i + 1, de: qtd, valor: fmtBR(v),
      venc: addDias(dia0(primeiro), i * passo), pgto: null,
      tipo: c.tipo, lanc: 'A RECEBER', plano: PLANOS[0], conta: caixa,
      doc: docBase + ' ' + (i + 1) + '/' + qtd, resp: '', hist: '',
    })));
  };
  const setP = (k, campo, v) => setParcelas((s) => s.map((p) => p.k === k ? { ...p, [campo]: v } : p));
  const receber = (k) => setParcelas((s) => s.map((p) => p.k === k ? { ...p, lanc: 'RECEBIDA', pgto: new Date() } : p));
  const ajustarUltima = () => setParcelas((s) => s.map((p, i) => i === s.length - 1 ? { ...p, valor: fmtBR(submitSafe(parseBR(p.valor) + dif)) } : p));

  return (
    <>
      <Drawer open={open} onClose={onClose} width={860} title="Financeiro da venda — parcelas"
        subtitle={'Total a parcelar ' + brl(total)}
        badge={<Pill c={Math.abs(dif) < 0.005 ? 'var(--pos)' : 'var(--neg)'} s={Math.abs(dif) < 0.005 ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'color-mix(in oklch, var(--neg) 12%, var(--surface))'} mono>{parcelas.length ? parcelas.length + 'x' : 'sem parcelas'}</Pill>}
        footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
          <div>
            <Lbl>Soma das parcelas</Lbl>
            <b style={{ font: '600 15px/1 var(--font-mono)', color: Math.abs(dif) < 0.005 ? 'var(--pos)' : 'var(--neg)' }}>{brl(soma)}</b>
          </div>
          {Math.abs(dif) >= 0.005 && <Button size="sm" onClick={ajustarUltima}>Jogar {brl(Math.abs(dif))} na última</Button>}
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={onClose}>Fechar</Button>
            <Button variant="primary" disabled={Math.abs(dif) >= 0.005} onClick={onClose}>Confirmar parcelas</Button>
          </span>
        </div>}>
        <DrawerSection title="Condição de pagamento">
          <Grid cols={4} gap={10}>
            <div><Select label="Condição" value={cond} onChange={(e) => aplicarCondicao(e.target.value)} options={CONDICOES.map((x) => ({ value: x.id, label: x.id + ' — ' + x.nome }))} /></div>
            <Money label="Parcelas" prefix="qt" value={n} onChange={setN} />
            <Money label="Intervalo (dias)" prefix="d" value={porMes ? '30' : intervalo} onChange={setIntervalo} readOnly={porMes} />
            <div><DataCampo label="1º vencimento" value={primeiro} onChange={(d) => d && setPrimeiro(dia0(d))} /></div>
            <div><Select label="Caixa / conta de destino" value={caixa} onChange={(e) => setCaixa(e.target.value)} options={CONTAS} /></div>
            <div><Select label="Tipo de pagamento" defaultValue={c.tipo} options={['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Cheque', 'Fiado (a prazo)']} /></div>
            <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="Mês fechado" sublabel="Vence no mesmo dia de cada mês" checked={porMes} onChange={setPorMes} /></div>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 8 }}>
              <Button variant="primary" onClick={gerar}>Gerar parcelas</Button>
              {parcelas.length > 0 && <Button onClick={() => setParcelas([])}>Limpar</Button>}
            </div>
          </Grid>
        </DrawerSection>

        <DrawerSection title="Recebimento">
          {parcelas.length === 0
            ? <EmptyState variant="first" title="Nenhuma parcela gerada" description="Escolha a condição, o número de parcelas e o 1º vencimento e clique em Gerar parcelas. Depois você pode editar valor, data e conta de cada uma." />
            : <div className="oi-scroll" style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', minWidth: 760, borderCollapse: 'separate', borderSpacing: 0, font: '13.5px/1.4 var(--font-sans)' }}>
                <thead><tr>{['#', 'Valor', 'Vencimento', 'Tipo', 'Conta', 'Documento', 'Situação', ''].map((h, i) => (
                  <th key={h + i} style={{ background: 'var(--bg-2)', padding: '8px 12px', textAlign: i === 1 ? 'right' : i === 7 ? 'center' : 'left', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>{h}</th>
                ))}</tr></thead>
                <tbody>{parcelas.map((p) => (
                  <tr key={p.k}>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)', font: '600 12.5px/1 var(--font-mono)', whiteSpace: 'nowrap' }}>{p.num}/{p.de}</td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', width: 118 }}>
                      <div className="dsfa pre"><span className="afx l">R$</span><input value={p.valor} inputMode="decimal" aria-label={'Valor da parcela ' + p.num + ' de ' + p.de} onChange={(e) => setP(p.k, 'valor', e.target.value)} style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} /></div>
                    </td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', width: 150 }}>
                      <DataCampo value={p.venc} onChange={(d) => d && setP(p.k, 'venc', dia0(d))} />
                    </td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', width: 130 }}>
                      <Select value={p.tipo} onChange={(e) => setP(p.k, 'tipo', e.target.value)} options={['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Cheque']} />
                    </td>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)', font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{p.conta}</td>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)', font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-mute)', whiteSpace: 'nowrap' }}>{p.doc}</td>
                    <td style={{ padding: '8px 12px', borderBottom: '1px solid var(--border-2)' }}>
                      {p.lanc === 'RECEBIDA'
                        ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">recebida {p.pgto ? dBR(p.pgto) : ''}</Pill>
                        : <Pill c={vencida(p.venc) ? 'var(--neg)' : 'var(--warn)'} s={vencida(p.venc) ? 'color-mix(in oklch, var(--neg) 12%, var(--surface))' : 'color-mix(in oklch, var(--warn) 12%, var(--surface))'}>{vencida(p.venc) ? 'vencida' : 'a receber'}</Pill>}
                    </td>
                    <td style={{ padding: '4px 8px', borderBottom: '1px solid var(--border-2)', textAlign: 'center', whiteSpace: 'nowrap' }}>
                      <DropdownMenu align="end" trigger={<span title="Ações da parcela" style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)' }}>
                        <Icon name="EllipsisVertical" size={15} /></span>}
                        items={[
                          { id: 'ed', label: 'Editar parcela…', onSelect: () => setEdit(p) },
                          { id: 'rc', label: 'Marcar como recebida', disabled: p.lanc === 'RECEBIDA', onSelect: () => receber(p.k) },
                          { id: 'rec', label: 'Imprimir recibo' },
                          { id: 's1', separator: true },
                          { id: 'del', label: 'Excluir parcela', tone: 'danger', onSelect: () => { const pos = parcelas.indexOf(p); setParcelas((s) => s.filter((x) => x.k !== p.k)); setUndo({ msg: 'Parcela ' + p.num + '/' + p.de + ' excluída', undo: () => setParcelas((s) => { const c = [...s]; c.splice(pos, 0, p); return c; }) }); } },
                        ]} />
                    </td>
                  </tr>
                ))}</tbody>
              </table>
            </div>}
          {undo && <div role="status" style={{ display: 'flex', alignItems: 'center', gap: 16, marginTop: 12, padding: '10px 12px', borderRadius: 12, background: 'var(--text)', color: 'var(--bg)', font: '12.5px/1.3 var(--font-sans)' }}>
            <span>{undo.msg}</span>
            <button type="button" onClick={() => { undo.undo(); setUndo(null); }} style={{ marginLeft: 'auto', border: 0, background: 'transparent', color: 'var(--accent-2)', cursor: 'pointer', font: '600 12.5px/1 var(--font-sans)' }}>Desfazer</button>
          </div>}
          {parcelas.length > 0 && Math.abs(dif) >= 0.005 && <div style={{ marginTop: 12 }}>
            <Alert tone="danger" title="A soma das parcelas não fecha com o total da venda">Diferença de <b>{brl(Math.abs(dif))}</b> {dif > 0 ? 'faltando' : 'sobrando'}. Ajuste um valor ou jogue a diferença na última parcela — a venda não fecha com parcelas divergentes.</Alert>
          </div>}
          <Meta><div style={{ marginTop: 12 }}><TierBar>Rateio com centavo de ajuste: <code>ratear()</code> distribui em centavos inteiros e sobra o resto nas primeiras parcelas — <b>soma sempre igual ao <code>final_total</code></b>. Dividir por float e arredondar cada parcela é o que produz o clássico R$ 0,01 perdido (CU-SELL-09).</TierBar></div></Meta>
        </DrawerSection>

      </Drawer>

      <Modal open={!!edit} onClose={() => setEdit(null)} title={edit ? 'Parcela ' + edit.num + '/' + edit.de : 'Parcela'}
        footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
          <Button onClick={() => setEdit(null)}>Recibo</Button>
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={() => setEdit(null)}>Cancelar</Button>
            <Button variant="primary" onClick={() => { setParcelas((s) => s.map((x) => x.k === edit.k ? edit : x)); setEdit(null); }}>Confirmar</Button>
          </span>
        </div>}>
        {edit && <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <Grid cols={2} gap={10}>
            <div><Input label="Responsável" value={edit.resp} onChange={(e) => setEdit({ ...edit, resp: e.target.value })} placeholder="Cliente da venda" /></div>
            <div><Select label="Lançamento" value={edit.lanc} onChange={(e) => setEdit({ ...edit, lanc: e.target.value })} options={LANC} /></div>
          </Grid>
          <Grid cols={3} gap={10}>
            <div><Select label="Tipo de pagamento" value={edit.tipo} onChange={(e) => setEdit({ ...edit, tipo: e.target.value })} options={['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Cheque']} /></div>
            <div><Input label="Documento" value={edit.doc} onChange={(e) => setEdit({ ...edit, doc: e.target.value })} /></div>
            <Money label="Valor" value={edit.valor} onChange={(v) => setEdit({ ...edit, valor: v })} />
          </Grid>
          <Grid cols={2} gap={10}>
            <div><DataCampo label="Vencimento" value={edit.venc} onChange={(d) => d && setEdit({ ...edit, venc: dia0(d) })} /></div>
            <div><DataCampo label="Pagamento" value={edit.pgto} onChange={(d) => setEdit({ ...edit, pgto: d })} /></div>
          </Grid>
          <Grid cols={2} gap={10}>
            <div><Select label="Plano de contas" value={edit.plano} onChange={(e) => setEdit({ ...edit, plano: e.target.value })} options={PLANOS} /></div>
            <div><Select label="Conta" value={edit.conta} onChange={(e) => setEdit({ ...edit, conta: e.target.value })} options={CONTAS} /></div>
          </Grid>
          <Textarea label="Histórico" rows={2} value={edit.hist} onChange={(e) => setEdit({ ...edit, hist: e.target.value })} placeholder="Cliente pediu boleto por e-mail" />
        </div>}
      </Modal>
    </>
  );
}

Object.assign(window, { ParcelasDrawer, ratear, CONDICOES, dia0, hoje0, vencida });
