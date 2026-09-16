/* Telas de consulta e operação do domínio Sells: Index (lista/cobrança) · Show (ficha) ·
   Caixa · Subscriptions · Quotations · Drafts. As 6 últimas nascem SEM casos.md (§1.1). */

function Index({ onOpen, go }) {
  const meta = useMeta();
  const [q, setQ] = React.useState('');
  const [pay, setPay] = React.useState('todas');
  const [estagio, setEstagio] = React.useState('todos');
  const [sel, setSel] = React.useState([]);
  const [sheet, setSheet] = React.useState(null);
  const [estagios, setEstagios] = React.useState({});
  const [tenant, setTenant] = React.useState('vazado');
  const [aviso, setAviso] = React.useState(null);

  const estagioDe = (v) => estagios[v.id] || v.estagio;
  const base = window.SD.vendas.filter((v) => (pay === 'todas' || v.pay === pay) && (estagio === 'todos' || estagioDe(v) === estagio) && (v.cliente + v.inv).toLowerCase().includes(q.toLowerCase()));
  /* CU-SELL-32 · o indicador de devolução só conta devolução do MESMO business */
  const temRetorno = (v) => (meta && tenant === 'vazado') ? v.ret : (v.ret && v.id !== 4817);
  const somaFinal = base.reduce((s, v) => s + v.total, 0);
  const somaPago = base.reduce((s, v) => s + v.pago, 0);
  const aReceber = window.SD.vendas.reduce((s, v) => s + (v.total - v.pago), 0);
  const vencido = window.SD.vendas.filter((v) => v.atraso).reduce((s, v) => s + (v.total - v.pago), 0);

  const cols = [
    { key: 'inv', label: 'Venda', mono: true }, { key: 'cli', label: 'Cliente' }, { key: 'd', label: 'Data', mono: true },
    { key: 'est', label: 'Estágio' }, { key: 'fis', label: 'Fiscal' }, { key: 'tot', label: 'Total', align: 'right', mono: true },
    { key: 'pg', label: 'Pago', align: 'right', mono: true }, { key: 'st', label: 'Cobrança' }, { key: 'ac', label: '', align: 'center' },
  ];
  const rows = base.map((v) => ({ id: v.id, state: v.atraso ? 'urgent' : undefined, cells: {
    inv: <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>{v.inv}
      {temRetorno(v) && <span title="Esta venda tem devolução (sell_return)" style={{ display: 'inline-flex', color: 'var(--warn)' }}>
        <Icon name="Undo2" size={14} /></span>}</span>,
    cli: { primary: v.cliente, sub: v.tipo === 'pj' ? 'PJ · comissão ' + v.comiss : 'PF' },
    d: v.data, est: <EstagioPill k={estagioDe(v)} />, fis: <FiscalPill f={v.nfe} />,
    tot: fmtBR(v.total), pg: v.pago ? fmtBR(v.pago) : '—',
    st: <PayPill p={v.pay} atraso={v.atraso} />,
    ac: <DropdownMenu align="end" trigger={<span title="Ações" style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)' }}>
      <Icon name="EllipsisVertical" size={15} /></span>} items={[
      { id: 'ver', label: 'Abrir ficha', onSelect: () => go && go('show', v) },
      { id: 'edit', label: 'Editar venda', onSelect: () => go && go('edit', v) },
      { id: 'pay', label: 'Registrar pagamento' },
      { id: 'sep1', separator: true },
      { id: 'ret', label: 'Devolver (sell-return)', onSelect: () => setAviso('/sell-return/add/' + v.id + ' — fluxo separado (NG-02). Este ponto de entrada sumiu no rewrite #1032 e voltou como contrato (UC-S11).') },
      { id: 'nfe', label: 'DANFE / NF-e' },
      { id: 'del', label: 'Cancelar venda', tone: 'danger' },
    ]} />,
  } }));

  const st = window.SD.fsm.map((f, i) => {
    const atual = sheet ? window.SD.fsm.findIndex((x) => x.key === estagioDe(sheet)) : 0;
    return { label: f.l, state: f.key === 'cancelada' ? 'term' : i < atual ? 'done' : i === atual ? 'current' : 'todo' };
  }).filter((s, i) => window.SD.fsm[i].key !== 'cancelada');
  const fsmAtual = sheet ? window.SD.fsm.find((f) => f.key === estagioDe(sheet)) : null;
  const proximo = sheet && fsmAtual ? window.SD.fsm[window.SD.fsm.findIndex((f) => f.key === fsmAtual.key) + 1] : null;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <Grid cols={4} gap={12}>
        <KpiCard label="A receber" value={brl(aReceber)} tone="warning" hero />
        <KpiCard label="Vencido" value={brl(vencido)} tone="danger" />
        <KpiCard label="Recebido (jul)" value={brl(somaPago)} tone="success" spark={[8, 12, 9, 14, 18, 16]} />
        <KpiCard label="Vendas finais" value={String(window.SD.vendas.length)} tone="default" />
      </Grid>

      {aviso && <Alert tone="info" title="Devolução é fluxo separado">{aviso}</Alert>}

      <Meta><div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center' }}>
        <Lbl>Escopo da derivação de has_return</Lbl>
        {[['vazado', 'Hoje (subquery sem business_id)'], ['escopado', 'Depois de O3-1 (escopado)']].map(([k, l]) => (
          <button key={k} type="button" onClick={() => setTenant(k)} style={{ height: 26, padding: '0 12px', borderRadius: 6, cursor: 'pointer', border: '1px solid ' + (tenant === k ? 'transparent' : 'var(--border)'), background: tenant === k ? 'var(--accent)' : 'var(--surface)', color: tenant === k ? 'var(--accent-fg)' : 'var(--text-dim)', font: '600 11.5px/1 var(--font-sans)' }}>{l}</button>
        ))}
        <CuRow ids={['CU-SELL-32']} onOpen={onOpen} />
      </div></Meta>
      <Meta><Alert tone={tenant === 'vazado' ? 'warn' : 'success'} title={tenant === 'vazado' ? 'A setinha de retorno conta devolução de QUALQUER business (D-1)' : 'has_return escopado por business_id'}>
        {tenant === 'vazado'
          ? <span>A subquery <code>(SELECT COUNT(*) FROM transactions sr WHERE sr.return_parent_id = transactions.id AND sr.type = 'sell_return')</code> filtra só <code>type</code> e <code>return_parent_id</code>. <b>Limite honesto:</b> <code>return_parent_id</code> não é controlado pelo usuário no fluxo normal — não há vazamento provado; é gap de <b>defesa em profundidade</b>. Corrigir a query é decisão [W].</span>
          : <span>Estado proposto por <b>UC-SIDX-01</b>: a derivação herda <code>business_id</code> nos <b>dois</b> sites (<code>inertiaList</code> e <code>getSellsCurrentFy</code>). A venda 4817 perde o indicador porque a devolução era de outro tenant.</span>}
      </Alert></Meta>

      <Sec title="Vendas" sub={meta ? 'GET /sells-list-json → SellController@inertiaList · drawer por /sells/{id}/sheet-data' : null}
        hue="var(--accent)" ico="List" cus={['CU-SELL-30', 'CU-SELL-31', 'CU-SELL-33']} onOpen={onOpen} pad={0}>
        <div style={{ display: 'flex', gap: 12, padding: 12, alignItems: 'center', borderBottom: '1px solid var(--border)', flexWrap: 'wrap' }}>
          <div style={{ width: 280 }}><Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar cliente ou nº da venda…" /></div>
          <FilterChip label="Período" value="jul/2026" onRemove={() => {}} />
          <FilterChip label="Situação" value="final" />
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button size="sm">Exportar</Button>
          </span>
        </div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center', padding: 12, borderBottom: '1px solid var(--border)' }}>
          <Lbl>Estágio</Lbl>
          {[['todos', 'Todos'], ...window.SD.fsm.map((f) => [f.key, f.l])].map(([k, l]) => {
            const on = estagio === k;
            const c = k === 'todos' ? 'var(--accent)' : (ESTAGIO_HUE[k] || 'var(--text-mute)');
            return <button key={k} type="button" onClick={() => setEstagio(k)} style={{ height: 25, padding: '0 12px', borderRadius: 999, cursor: 'pointer', border: '1px solid ' + (on ? 'transparent' : 'var(--border)'), background: on ? c : 'var(--surface)', color: on ? '#fff' : 'var(--text-dim)', font: '600 11.5px/1 var(--font-sans)' }}>{l}
              <span style={{ marginLeft: 6, fontFamily: 'var(--font-mono)', opacity: .75 }}>{k === 'todos' ? window.SD.vendas.length : window.SD.vendas.filter((v) => estagioDe(v) === k).length}</span>
            </button>;
          })}
          <span style={{ marginLeft: 'auto', font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-mute)' }}>Orçamento ainda não aprovado vive na aba <b>Cotações</b>.</span>
        </div>
        <div style={{ padding: '0 12px' }}>
          <TabBar active={pay} onChange={setPay} tabs={[
            { key: 'todas', label: 'Todas', count: window.SD.vendas.length },
            { key: 'due', label: 'A receber', count: window.SD.vendas.filter((v) => v.pay === 'due').length },
            { key: 'partial', label: 'Parcial', count: window.SD.vendas.filter((v) => v.pay === 'partial').length },
            { key: 'paid', label: 'Pagas', count: window.SD.vendas.filter((v) => v.pay === 'paid').length },
          ]} />
        </div>
        <DataTable columns={cols} rows={rows} selectable selectedIds={sel}
          onToggleRow={(id) => setSel((s) => s.includes(id) ? s.filter((x) => x !== id) : [...s, id])}
          onToggleAll={(c) => setSel(c ? base.map((v) => v.id) : [])}
          onRowClick={(r) => setSheet(window.SD.vendas.find((v) => v.id === r.id))} />
        {!base.length && <EmptyState variant="no-results" title="Nenhuma venda neste filtro" description="Troque o estágio ou a situação de cobrança para ver mais registros." />}
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 16, padding: '12px 16px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)' }}>
          <div><Lbl>Total do período</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{brl(somaFinal)}</b></div>
          <div><Lbl>Recebido</Lbl><b style={{ font: '600 15px/1 var(--font-mono)', color: 'var(--pos)' }}>{brl(somaPago)}</b></div>
          <div><Lbl>Saldo aberto</Lbl><b style={{ font: '600 15px/1 var(--font-mono)', color: 'var(--neg)' }}>{brl(somaFinal - somaPago)}</b></div>
          <span style={{ marginLeft: 'auto' }}><Pagination page={1} pageCount={3} total={24} pageSize={base.length} onChange={() => {}} /></span>
        </div>
        <div style={{ padding: '0 14px 12px' }}>
          <TierBar>O totalizador é <b>[V0]+[T0]</b>: número de dinheiro na cara do operador. Contrato é o <b>escopo</b> (herda <code>business_id</code> da query base) — <b>não</b> a paginação: <code>$totalsQuery</code> roda sem <code>limit</code> e cobre o filtro inteiro, não a página (CU-SELL-33 item 3).</TierBar>
        </div>
      </Sec>

      {sel.length > 0 && <BulkBar count={sel.length} label="vendas" onClose={() => setSel([])} actions={[{ label: 'Registrar recebimento' }, { label: 'Enviar cobrança' }, { label: 'Exportar' }, { label: 'Cancelar', tone: 'danger' }]} />}

      <Drawer open={!!sheet} onClose={() => setSheet(null)} width={620}
        title={sheet ? sheet.inv : ''} subtitle={sheet ? sheet.cliente + ' · ' + sheet.data : ''}
        badge={sheet ? <PayPill p={sheet.pay} atraso={sheet.atraso} /> : null}
        footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
          {proximo && fsmAtual && fsmAtual.acao && <Pill mono c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">{fsmAtual.role}</Pill>}
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button>Registrar pagamento</Button>
            <Button onClick={() => go && go('show', sheet)}>Abrir ficha</Button>
            <Button onClick={() => go && go('edit', sheet)}>Editar</Button>
            {proximo && fsmAtual && fsmAtual.acao && <Button variant="primary" onClick={() => { setEstagios((s) => ({ ...s, [sheet.id]: proximo.key })); }}>{fsmAtual.acao}</Button>}
          </span>
        </div>}>
        {sheet && <>
          <DrawerSection title="Pipeline (ExecuteStageActionService)">
            <FsmStepper steps={st} variant="full" hue={295} />
            {fsmAtual && fsmAtual.acao ? (
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center', padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
                <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">{fsmAtual.acao}</Pill>
                <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-mute)' }}>exige <b style={{ fontFamily: 'var(--font-mono)' }}>{fsmAtual.role}</b></span>
                {window.SD.fsm.find((f) => f.key === (proximo || {}).key) && (proximo.efeitos || []).length > 0 &&
                  <span style={{ display: 'flex', gap: 4 }}>{proximo.efeitos.map((e) => <Pill key={e} mono c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">{e}</Pill>)}</span>}
              </div>
            ) : <Alert tone="danger" title="Venda cancelada">Cascata executada: <code>CancelarVendaCascade</code> + <code>LiberarReserva</code>. Reverter é ação nomeada com role, não botão livre.</Alert>}
            <Meta><div style={{ marginTop: 12 }}><TierBar tone="accent">Mudança de estágio só por <code>ExecuteStageActionService::execute</code>. UPDATE direto em <code>current_stage_id</code> é barrado pelo trait <code>GuardsFsmTransitions</code> (ADR 0143 · CU-SELL-22). Action <code>is_critical</code> sem role <b>nega</b> — fail-secure (CU-SELL-21).</TierBar></div></Meta>
          </DrawerSection>
          <DrawerSection title="Dinheiro">
            <Grid cols={3} gap={10}>
              <div><Lbl>Total da venda</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{brl(sheet.total)}</b></div>
              <div><Lbl>Pago</Lbl><b style={{ font: '600 15px/1 var(--font-mono)', color: 'var(--pos)' }}>{brl(sheet.pago)}</b></div>
              <div><Lbl>Vencimento</Lbl><b style={{ font: '600 15px/1 var(--font-mono)', color: sheet.atraso ? 'var(--neg)' : 'var(--text)' }}>{sheet.venc}</b></div>
            </Grid>
          </DrawerSection>
          <DrawerSection title="Fiscal e devolução">
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
              <FiscalPill f={sheet.nfe} />
              {temRetorno(sheet) ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">tem devolução</Pill> : <Pill>sem devolução</Pill>}
              <Button size="sm">Abrir devolução</Button>
            </div>
            <Meta><p style={{ margin: '8px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-mute)' }}>Devolução é <code>type='sell_return'</code> com <code>return_parent_id</code> apontando pra venda — critério canônico (mesmo JOIN <code>SR</code> de <code>getSellsCurrentFy</code>). Cancelar NF-e <b>não</b> pula sequencial (CU-SELL-20).</p></Meta>
          </DrawerSection>
          <DrawerSection title="Timeline (sale_stage_history · append-only)">
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              {[['27/07 09:12', 'Orçamento criado', 'wagner'], ['27/07 09:40', 'Aprovar orçamento', 'wagner'], ['27/07 14:05', 'Iniciar produção → ReservarEstoque', 'kamila']].map(([d, a, u]) => (
                <div key={d} style={{ display: 'flex', gap: 12, alignItems: 'baseline', font: '12.5px/1.4 var(--font-sans)' }}>
                  <span style={{ font: '11.5px/1 var(--font-mono)', color: 'var(--text-mute)', flex: 'none' }}>{d}</span>
                  <span style={{ color: 'var(--text-dim)' }}>{a}</span>
                  <Pill mono>{u}</Pill>
                </div>
              ))}
            </div>
            <Meta><CuRow ids={['CU-SELL-26', 'CU-SELL-25']} onOpen={onOpen} /></Meta>
          </DrawerSection>
        </>}
      </Drawer>
    </div>
  );
}

function Show({ onOpen, go, registro }) {
  const v = registro || window.SD.vendas[1];
  const [aba, setAba] = React.useState('itens');
  return (
    <SemContrato tela="show" onda="O6-1">
      <Sec title={v.inv + ' · ' + v.cliente} sub={'Emitida em ' + v.data} hue="var(--accent)" ico="FileText"
        cus={['CU-SELL-26', 'CU-SELL-06', 'CU-SELL-14']} onOpen={onOpen}
        right={<div style={{ display: 'flex', gap: 8 }}><Button size="sm">Imprimir</Button><Button size="sm">DANFE</Button><Button size="sm" variant="primary" onClick={() => go && go('edit', v)}>Editar</Button></div>}>
        <Grid cols={4} gap={12} style={{ marginBottom: 14 }}>
          <div><Lbl>Total da venda</Lbl><b style={{ font: '600 18px/1 var(--font-mono)' }}>{brl(v.total)}</b></div>
          <div><Lbl>Pago</Lbl><b style={{ font: '600 18px/1 var(--font-mono)', color: 'var(--pos)' }}>{brl(v.pago)}</b></div>
          <div><Lbl>Cobrança</Lbl><PayPill p={v.pay} /></div>
          <div><Lbl>Estágio</Lbl><EstagioPill k={v.estagio} /></div>
        </Grid>
        <TabBar active={aba} onChange={setAba} tabs={[{ key: 'itens', label: 'Itens', count: 3 }, { key: 'pag', label: 'Pagamentos', count: 2 }, { key: 'fsm', label: 'Timeline', count: 4 }, { key: 'os', label: 'OS vinculada', count: 1 }]} />
        <div style={{ paddingTop: 16 }}>
          {aba === 'itens' && <DataTable columns={[{ key: 'p', label: 'Item' }, { key: 'q', label: 'Qtd', align: 'right', mono: true }, { key: 'u', label: 'Unit.', align: 'right', mono: true }, { key: 't', label: 'Total', align: 'right', mono: true }]}
            rows={[['Lona 440g branca fosca', '42,00', 68.9, 2893.8], ['Acabamento com ilhós', '96', 3.5, 336], ['Instalação — hora técnica', '5', 120, 600]].map(([p, q, u, t], i) => ({ id: i, cells: { p, q, u: fmtBR(u), t: fmtBR(t) } }))} />}
          {aba === 'pag' && <DataTable columns={[{ key: 'd', label: 'Data', mono: true }, { key: 'm', label: 'Método' }, { key: 'v', label: 'Valor', align: 'right', mono: true }, { key: 'r', label: 'Tipo' }]}
            rows={[['27/07/2026', 'PIX', 1890.5, 'recebimento'], ['—', 'Boleto', 2000, 'em aberto']].map(([d, m, val, r], i) => ({ id: i, cells: { d, m, v: fmtBR(val), r: <Pill>{r}</Pill> } }))} />}
          {aba === 'fsm' && <FsmStepper variant="full" hue={295} steps={window.SD.fsm.filter((f) => f.key !== 'cancelada').map((f, i) => ({ label: f.l, state: i < 3 ? 'done' : i === 3 ? 'current' : 'todo' }))} />}
          {aba === 'os' && <EmptyState variant="done" title="OS-2026-118 · em produção" description="A venda abriu OS pelo processo Venda Com Produção — ida e volta pelo return/parent (CU-SELL-14)." action={<Button variant="primary">Abrir OS</Button>} />}
        </div>
      </Sec>
    </SemContrato>
  );
}

function Caixa({ onOpen }) {
  const meta = useMeta();
  const dia = window.SD.vendas.filter((v) => v.data === '27/07/2026');
  return (
    <SemContrato tela="caixa" onda="O6-2">
      <Grid cols={4} gap={12}>
        <KpiCard label="Entradas do dia" value={brl(dia.reduce((s, v) => s + v.pago, 0))} tone="success" hero />
        <KpiCard label="Vendas do dia" value={String(dia.length)} tone="default" />
        <KpiCard label="A receber gerado" value={brl(dia.reduce((s, v) => s + (v.total - v.pago), 0))} tone="warning" />
        <KpiCard label="Ticket médio" value={brl(dia.reduce((s, v) => s + v.total, 0) / (dia.length || 1))} tone="info" />
      </Grid>
      <Sec title="Caixa do dia · 27/07/2026" sub={meta ? 'GET /vendas/caixa → SellController@inertiaCaixa' : null} hue="var(--pos)" ico="CreditCard"
        cus={['CU-SELL-33', 'CU-SELL-06']} onOpen={onOpen} pad={0}>
        <DataTable columns={[{ key: 'i', label: 'Venda', mono: true }, { key: 'c', label: 'Cliente' }, { key: 'm', label: 'Recebido', align: 'right', mono: true }, { key: 't', label: 'Total', align: 'right', mono: true }, { key: 's', label: 'Cobrança' }]}
          rows={dia.map((v) => ({ id: v.id, cells: { i: v.inv, c: v.cliente, m: fmtBR(v.pago), t: fmtBR(v.total), s: <PayPill p={v.pay} /> } }))} />
        <div style={{ padding: '0 14px 12px', paddingTop: 12 }}><TierBar>Agregado de dinheiro <b>sem contrato nenhum</b> hoje: a tela exibe soma e não há teste que garanta o escopo por <code>business_id</code>. Escrever <code>Caixa.casos.md</code> é o item <b>O6-2</b>.</TierBar></div>
      </Sec>
    </SemContrato>
  );
}

function Subscriptions({ onOpen }) {
  return (
    <SemContrato tela="subs" onda="O6-3">
      <Sec title="Assinaturas e recorrência" sub="NG-01: não entra no Create — vive aqui" hue="var(--accent)" ico="CalendarClock"
        cus={['CU-SELL-08']} onOpen={onOpen} pad={0}>
        <DataTable columns={[{ key: 'c', label: 'Cliente' }, { key: 'p', label: 'Plano' }, { key: 'v', label: 'Valor/mês', align: 'right', mono: true }, { key: 'd', label: 'Próx. cobrança', mono: true }, { key: 's', label: 'Situação' }]}
          rows={[['Rota Livre Comércio', 'Comunicação visual — pacote mensal', 1890, '05/08/2026', 'ativa'], ['Prefeitura de Joinville', 'Manutenção de placas', 2400, '10/08/2026', 'ativa'], ['Marina Bordignon', 'Social media', 690, '—', 'suspensa']].map(([c, p, v, d, s], i) => ({ id: i, cells: { c, p, v: fmtBR(v), d, s: <Pill c={s === 'ativa' ? 'var(--pos)' : 'var(--warn)'} s={s === 'ativa' ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'color-mix(in oklch, var(--warn) 12%, var(--surface))'}>{s}</Pill> } }))} />
      </Sec>
      <Meta><Alert tone="warn" title="Blind spot do projeto">O módulo <b>recorrente</b> nunca foi auditado (score KB <code>null</code>) e a cobrança automática não tem contrato. Item <b>O6-3</b> do roteiro pede o <code>casos.md</code> <b>e</b> a auditoria KB-9.75.</Alert></Meta>
    </SemContrato>
  );
}

function Quotations({ onOpen, go }) {
  return (
    <SemContrato tela="quotations" onda="O6-3">
      <Sec title="Cotações" sub="NG-04: rota própria /sells/quotation/create · FSM quote_draft" hue="var(--color-info)" ico="FileText"
        cus={['CU-SELL-08', 'CU-SELL-07']} onOpen={onOpen} pad={0}>
        <DataTable columns={[{ key: 'i', label: 'Cotação', mono: true }, { key: 'c', label: 'Cliente' }, { key: 'v', label: 'Valor', align: 'right', mono: true }, { key: 'val', label: 'Validade', mono: true }, { key: 's', label: 'Estágio' }]}
          rows={[['CT-2026-0312', 'Prefeitura de Joinville', 18400, '10/08/2026', 'orcamento'], ['CT-2026-0311', 'Rota Livre Comércio', 5230, '02/08/2026', 'orcamento'], ['CT-2026-0308', 'Marina Bordignon', 890, 'vencida', 'cancelada']].map(([i, c, v, val, s], k) => ({ id: k, cells: { i, c, v: fmtBR(v), val, s: <EstagioPill k={s} /> } }))} />
        <div style={{ padding: 12, borderTop: '1px solid var(--border)' }}><Button size="sm" variant="primary" onClick={() => go && go('create')}>Converter em venda</Button></div>
      </Sec>
    </SemContrato>
  );
}

function Drafts({ onOpen, go }) {
  const meta = useMeta();
  let draft = null;
  try { draft = JSON.parse(localStorage.getItem('oimpresso.vendas.sdd.draft.b1.u1') || 'null'); } catch (e) {}
  return (
    <SemContrato tela="drafts" onda="O6-3">
      <Sec title="Rascunhos" sub={meta ? 'A outra ponta do auto-save por {business_id}.{user_id} (CU-SELL-13)' : null} hue="var(--warn)" ico="NotebookPen"
        cus={['CU-SELL-13', 'CU-SELL-08']} onOpen={onOpen}>
        {draft ? (
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center', padding: 12, borderRadius: 12, background: 'color-mix(in oklch, var(--accent) 12%, var(--surface))', border: '1px solid color-mix(in oklch, var(--accent) 26%, transparent)' }}>
            <div><Lbl c="var(--accent)">Rascunho vivo desta sessão</Lbl><b style={{ fontWeight: 600 }}>{draft.cliente}</b>
              <span style={{ display: 'block', font: '11.5px/1.4 var(--font-mono)', color: 'var(--text-mute)' }}>{(draft.itens || []).length} item(ns) · salvo {new Date(draft.em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</span></div>
            <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}><Button size="sm">Descartar</Button><Button size="sm" variant="primary" onClick={() => go && go('create')}>Retomar venda</Button></span>
          </div>
        ) : (
          <EmptyState variant="first" title="Nenhum rascunho" description="Comece uma venda na tela Nova venda e volte aqui: o auto-save grava por business+usuário e o rascunho aparece nesta lista." />
        )}
        <Meta><div style={{ marginTop: 12 }}><TierBar tone="accent">A chave do rascunho é <b>{'{business_id}.{user_id}'}</b> — se fosse só usuário, o rascunho de um business apareceria noutro. É [T0] mesmo sendo conveniência de UI.</TierBar></div></Meta>
      </Sec>
    </SemContrato>
  );
}

Object.assign(window, { Index, Show, Caixa, Subscriptions, Quotations, Drafts });
