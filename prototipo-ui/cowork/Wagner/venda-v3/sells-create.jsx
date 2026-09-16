/* Nova venda / Editar venda — v2.
   Problema da v1: tela toda branca e tudo pedindo decisão ao mesmo tempo.
   v2: 3 passos numerados · coluna de trabalho à esquerda · FECHAMENTO fixo à direita
   (plate escuro do DS = o único bloco de peso visual) · secundário em gavetas fechadas. */

const LS_DRAFT = 'oimpresso.vendas.sdd.draft.b1.u1';
const METODOS = ['Dinheiro', 'PIX', 'Cartão de crédito', 'Cartão de débito', 'Boleto', 'Fiado (a prazo)'];
const RAPIDO = ['Dinheiro', 'PIX', 'Cartão de crédito', 'Boleto'];
const TABELAS = ['Balcão — preço padrão', 'Atacado — a partir de 50m²', 'Governo 2026 — pregão 041/2026', 'Parceiro / agência — 15% off'];

function linhaTotal(l) { return submitSafe(parseBR(l.qtd) * parseBR(l.preco) * (1 - parseBR(l.desc) / 100) * (1 + parseBR(l.acr || 0) / 100)); }

const Passo = ({ n }) => <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 18, height: 18, borderRadius: 999, marginRight: 8, background: 'var(--accent)', color: 'var(--accent-fg)', font: '600 11.5px/1 var(--font-mono)', verticalAlign: '1px' }}>{n}</span>;

/* Linha do resumo de fechamento */
const Res = ({ l, v, c, forte }) => (
  <div style={{ display: 'flex', alignItems: 'baseline', gap: 12, padding: '5px 0' }}>
    <span style={{ font: (forte ? '600 ' : '') + '12.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{l}</span>
    <span style={{ flex: 1, borderBottom: '1px dotted var(--border)', transform: 'translateY(-3px)' }}></span>
    <b style={{ font: '600 13.5px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums', color: c || 'var(--text)' }}>{v}</b>
  </div>
);

function Create({ onOpen, modo = 'create', registro }) {
  const meta = useMeta();
  const estreito = useEstreito();
  const edit = modo === 'edit';
  const [carregando, setCarregando] = React.useState(true);
  const [erroSistema, setErroSistema] = React.useState(null);
  const [mostrarTodos, setMostrarTodos] = React.useState(false);
  React.useEffect(() => { const t = setTimeout(() => setCarregando(false), 550); return () => clearTimeout(t); }, []);
  const [cliente, setCliente] = React.useState(edit ? (registro ? registro.cliente : 'Prefeitura de Joinville') : 'Consumidor final');
  const [itens, setItens] = React.useState(edit
    ? [{ k: 1, sku: 'LON-440-BR', nome: 'Lona 440g branca fosca', un: 'm²', qtd: '120,00', preco: '68,90', desc: '0' },
       { k: 2, sku: 'BAN-ACAB-IL', nome: 'Acabamento com ilhós', un: 'un', qtd: '48', preco: '3,50', desc: '0' }]
    : [{ k: 1, sku: 'LON-440-BR', nome: 'Lona 440g branca fosca', un: 'm²', qtd: '12,50', preco: '68,90', desc: '0', pecas: '5', altura: '0,50', largura: '5,00' },
       { k: 2, sku: 'BAN-ACAB-IL', nome: 'Acabamento com ilhós', un: 'un', qtd: '24', preco: '3,50', desc: '0' }]);
  const [busca, setBusca] = React.useState('');
  const [lancar, setLancar] = React.useState(null);
  const [consultaProd, setConsultaProd] = React.useState(false);
  const [colunas, setColunas] = React.useState(carregarColunas);
  const [colunasOpen, setColunasOpen] = React.useState(false);
  const [comBens, setComBens] = React.useState([{ k: 1, tipo: 'funcionario', pessoa: 'Kamila Reis', base: 'liquido', regra: 'pct', pct: '3,00', valor: '0,00' }]);
  const [comGatilho, setComGatilho] = React.useState('recebimento');
  const [comOpen, setComOpen] = React.useState(false);
  React.useEffect(() => { try { localStorage.setItem(COL_LS, JSON.stringify(colunas)); } catch (e) {} }, [colunas]);
  const cols = colunas.map((k) => COLUNAS.find((c) => c.k === k)).filter(Boolean);
  const W_ACOES = 152;
  const larguraMin = Math.max(760, cols.reduce((s, c) => s + (c.w || 110), 0) + W_ACOES);
  const [descTipo, setDescTipo] = React.useState('percentual');
  const [descVal, setDescVal] = React.useState('0,00');
  const [freteModo, setFreteModo] = React.useState('hoje');
  const [frete, setFrete] = React.useState('0,00');
  const [acr, setAcr] = React.useState('0,00');
  const [pags, setPags] = React.useState(edit ? [{ k: 1, m: 'Boleto', v: '0,00', par: '1' }] : []);
  const [status, setStatus] = React.useState('final');
  const [os, setOs] = React.useState(false);
  const [novoCli, setNovoCli] = React.useState(false);
  const [consultaCli, setConsultaCli] = React.useState(false);
  const [buscaCli, setBuscaCli] = React.useState('');
  const [destAberto, setDestAberto] = React.useState(false);
  const [tabela, setTabela] = React.useState(null); /* null = herda do cadastro do cliente */
  const [itemAberto, setItemAberto] = React.useState(-1);
  const [abaItem, setAbaItem] = React.useState('geral'); /* aba inicial do drawer de detalhe */
  const abrirItem = (i, aba) => { setAbaItem(aba || 'geral'); setItemAberto(i); };
  const [parcelasOpen, setParcelasOpen] = React.useState(false);
  const [parcelas, setParcelas] = React.useState([]);
  const [estagio, setEstagio] = React.useState(edit ? (registro ? registro.estagio : 'producao') : 'rascunho');
  const [historico, setHistorico] = React.useState([]);
  const [cancelarOpen, setCancelarOpen] = React.useState(false);
  const [salvo, setSalvo] = React.useState(null);
  const [salvando, setSalvando] = React.useState(false);
  const [undo, setUndo] = React.useState(null);
  React.useEffect(() => { if (!undo) return; const t = setTimeout(() => setUndo(null), 7000); return () => clearTimeout(t); }, [undo]);
  const [draftEm, setDraftEm] = React.useState(null);

  React.useEffect(() => {
    if (edit) return;
    const t = setTimeout(() => {
      try { localStorage.setItem(LS_DRAFT, JSON.stringify({ cliente, itens, descVal, pags, em: Date.now() })); setDraftEm(new Date()); } catch (e) {}
    }, 700);
    return () => clearTimeout(t);
  }, [cliente, itens, descVal, pags, edit]);

  const cli = window.SD.clientes.find((c) => c.nome === cliente) || window.SD.clientes[0];
  React.useEffect(() => { setTabela(null); }, [cliente]);
  const tabelaCadastro = cli.tabela || TABELAS[0];
  const tabelaAtiva = tabela || tabelaCadastro;
  const tabelaTrocada = !!tabela && tabela !== tabelaCadastro;
  const subtotal = submitSafe(itens.reduce((s, l) => s + linhaTotal(l), 0));
  const descAplicado = descTipo === 'percentual' ? submitSafe(subtotal * parseBR(descVal) / 100) : submitSafe(parseBR(descVal));
  const baseTrib = submitSafe(subtotal - descAplicado);
  /* margem estimada: preço de tabela do catálogo × 0,58 é o custo de cena (o custo real vem do cadastro) */
  const custoEstimado = submitSafe(itens.reduce((s, l) => {
    const p = window.SD.catalogo.find((x) => x.sku === l.sku);
    return s + (p ? submitSafe(parseBR(l.qtd) * p.preco * 0.58) : 0);
  }, 0));
  const totComissao = { bruto: subtotal, liquido: baseTrib, margem: Math.max(0, submitSafe(baseTrib - custoEstimado)) };
  const vImposto = submitSafe(baseTrib * 0.18);
  const vFrete = submitSafe(parseBR(frete));
  const vAcr = submitSafe(parseBR(acr));
  const total = submitSafe(baseTrib + vImposto + vFrete + vAcr);
  const pago = submitSafe(pags.reduce((s, p) => s + parseBR(p.v), 0) + parcelas.filter((p) => p.lanc === 'RECEBIDA').reduce((s, p) => s + parseBR(p.valor), 0));
  const saldo = submitSafe(total - pago);
  const payStatus = pago <= 0 ? 'due' : saldo > 0.005 ? 'partial' : 'paid';
  const alcada = descTipo === 'percentual' ? parseBR(descVal) > 10 : descAplicado > submitSafe(subtotal * 0.1);
  const qtdItens = itens.length;
  const invalidas = itens.filter((l) => parseBR(l.qtd) <= 0 || parseBR(l.preco) <= 0);
  const temInvalida = invalidas.length > 0;
  const LIMITE = 50;
  const visiveis = mostrarTodos ? itens : itens.slice(0, LIMITE);

  const addItem = (p) => { setBusca(''); setLancar(p); };
  const setLinha = (k, campo, v) => setItens((s) => s.map((l) => l.k === k ? { ...l, [campo]: v } : l));
  const achados = busca ? window.SD.catalogo.filter((p) => (p.nome + p.sku).toLowerCase().includes(busca.toLowerCase())) : [];
  const addPag = (m) => setPags((s) => [...s, { k: Date.now(), m, v: fmtBR(Math.max(saldo, 0)), par: '1' }]);
  const removerItem = (l) => {
    const pos = itens.indexOf(l);
    setItens((s) => s.filter((x) => x.k !== l.k));
    setUndo({ msg: 'Item removido — ' + l.nome, undo: () => setItens((s) => { const c = [...s]; c.splice(pos, 0, l); return c; }) });
  };
  const travada = estagioTrava(estagio);
  const atualFsm = fsmDe(estagio);
  const proxFsm = proximoDe(estagio);
  const podeExecutar = podeRole(atualFsm.role) && !!proxFsm && estagio !== 'cancelada';

  const registrar = (acao, de, para, efeitos) => setHistorico((h) => [...h, { acao, de, para, efeitos, por: window.SD.permissoes.usuario, em: new Date() }]);

  /* ExecuteStageActionService::execute — nunca UPDATE direto no estágio */
  const executarAcao = (forcarErro) => {
    if (!podeExecutar || salvando) return;
    setErroSistema(null);
    setSalvando(true);
    setTimeout(() => {
      setSalvando(false);
      if (forcarErro || window.SD.simularFalha) {
        window.SD.simularFalha = false;
        setErroSistema({ codigo: 'HTTP 503', em: new Date() });
        return;
      }
      registrar(atualFsm.acao, atualFsm.key, proxFsm.key, proxFsm.efeitos);
      setEstagio(proxFsm.key);
      if (estagio === 'rascunho') {
        setSalvo({ payStatus, total, em: new Date() });
        try { localStorage.removeItem(LS_DRAFT); } catch (e) {}
        setDraftEm(null);
      }
    }, 650);
  };
  const cancelarVenda = () => {
    setCancelarOpen(false);
    if (estagio === 'rascunho') { /* nada gravado: só limpa a tela */
      setItens([]); setPags([]); setParcelas([]); setDescVal('0,00'); setAcr('0,00'); setFrete('0,00');
      setCliente('Consumidor final'); setSalvo(null);
      try { localStorage.removeItem(LS_DRAFT); } catch (e) {}
      setDraftEm(null);
      return;
    }
    registrar('Cancelar venda', estagio, 'cancelada', ['CancelarVendaCascade', 'LiberarReserva']);
    setEstagio('cancelada');
  };
  const reabrir = () => { registrar('Reabrir para correção', estagio, 'aprovada', ['LiberarReserva']); setEstagio('aprovada'); };
  const salvar = executarAcao;

  const esquerda = (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0 }}>
      {edit && <Meta><Alert tone="danger" title="Editar venda emitida — sem casos.md, sem teste">Esta tela mexe em <code>final_total</code> e estoque de venda <b>já finalizada</b> e nenhum CU a defende hoje. Escrever <code>Edit.casos.md</code> é o item <b>O6-1</b>.</Alert></Meta>}
      {erroSistema && <Alert tone="danger" title={'Não foi possível salvar a venda (' + erroSistema.codigo + ')'}
        action={<Button size="sm" variant="primary" onClick={() => executarAcao(false)}>Tentar de novo</Button>}>
        O servidor não respondeu às {erroSistema.em.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}. <b>Nada foi perdido</b> — a venda continua aqui e o rascunho está salvo. Tente de novo; se repetir, salve como rascunho e chame o suporte.
      </Alert>}
      {salvo && <Alert tone={salvo.payStatus === 'due' ? 'warn' : 'success'} title={salvo.payStatus === 'due' ? 'Venda salva a prazo — ficou saldo devedor' : 'Venda salva'}>
        Total {brl(salvo.total)}{salvo.payStatus === 'due' && ' — entra na lista de cobrança como “a receber”.'}
      </Alert>}

      {estagio === 'cancelada'
        ? <Alert tone="danger" title="Venda cancelada">
            O cancelamento rodou <code>LiberarReserva</code>, então o estoque desta venda voltou para o saldo — nada aqui está mais comprometido. Esta venda não recebe alteração nem ação de fluxo: para retomar o pedido, <b>duplique</b> a venda; para acertar valor já faturado, lance uma <b>devolução</b>.
          </Alert>
        : travada && <Alert tone="warn" title={'Venda ' + atualFsm.l.toLowerCase() + ' — itens e valores travados'}>
            A partir de <b>Em produção</b> o estoque já está comprometido: mudar quantidade ou preço aqui adulteraria venda em curso. Use <b>Reabrir para correção</b> (registra no histórico) ou lance uma devolução.
          </Alert>}

      {/* 1 · Cliente — uma linha, não um formulário */}
      <Sec title={<><Passo n="1" />Cliente</>} hue="var(--accent)" ico={false} pad={12}
        cus={['CU-SELL-01', 'CU-SELL-02', 'CU-SELL-03', 'CU-SELL-15']} onOpen={onOpen}
        right={<Button size="sm" onClick={() => setConsultaCli(true)}>Consultar cadastro… F2</Button>}>
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: 12 }}>
          <div style={{ width: 104, flex: 'none' }}><Input label="Código" value={cli.cod} onChange={(ev) => { const x = window.SD.clientes.find((y) => y.cod === ev.target.value.trim()); if (x) setCliente(x.nome); }} /></div>
          <div style={{ flex: '1 1 320px', minWidth: 240 }}>
            <Input label="Cliente / destinatário" value={cliente} readOnly onChange={() => {}} />
          </div>
          {!cli.padrao && <div style={{ flex: 'none', paddingBottom: 8 }}>
            <button type="button" title="Voltar para Consumidor final" onClick={() => setCliente('Consumidor final')} style={{ width: 34, height: 34, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer' }}>×</button>
          </div>}
        </div>
        <button type="button" onClick={() => setDestAberto(!destAberto)} aria-expanded={destAberto}
          style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8, padding: '4px 0', border: 0, background: 'transparent', cursor: 'pointer', color: 'color-mix(in oklch, var(--accent) 62%, var(--text))', font: '600 11.5px/1 var(--font-sans)' }}>
          <Icon name="ChevronDown" size={14} style={{ transform: destAberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
          Detalhes do destinatário
          {!destAberto && <span style={{ font: '11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{cli.doc && cli.doc !== '—' ? cli.doc + ' · ' : ''}{cli.cidade}/{cli.uf}</span>}
        </button>
        {cli.padrao && <p style={{ margin: '0 0 4px', font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Venda de balcão começa em <b>Consumidor final</b> — troque digitando o código ou pela consulta de cadastro.</p>}
        {destAberto && <div style={{ marginTop: 8, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
          <Grid cols={4} gap={12}>
            <div><Lbl>{cli.tipo === 'pj' ? 'CNPJ' : 'CPF'}</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.doc}</b></div>
            <div><Lbl>Inscrição estadual</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.ie}</b></div>
            <div><Lbl>Inscrição municipal</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.im}</b></div>
            <div><Lbl>Regime tributário</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.regime}</b></div>
            <div><Lbl>ICMS</Lbl>{cli.contrib === 'sim' ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">contribuinte</Pill> : cli.contrib === 'isento' ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">isento</Pill> : <Pill c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">não contribuinte</Pill>}</div>
            <div><Lbl>Contato responsável</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.contato}</b></div>
            <div><Lbl>Telefone</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-mono)' }}>{cli.fone}</b></div>
            <div><Lbl>{cli.nascimento ? 'Cidade / UF · nascimento' : 'Cidade / UF'}</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.cidade}/{cli.uf}{cli.nascimento ? ' · ' + dTexto(dParse(cli.nascimento)) : ''}</b></div>
            <div style={{ gridColumn: 'span 2' }}><Lbl>E-mail</Lbl><span style={{ font: '12.5px/1.4 var(--font-sans)', wordBreak: 'break-all' }}>{cli.email}</span></div>
            <div style={{ gridColumn: 'span 2' }}><Lbl>E-mail para envio da NF</Lbl><span style={{ font: '12.5px/1.4 var(--font-sans)', wordBreak: 'break-all' }}>{cli.emailNfe}</span></div>
            <div style={{ gridColumn: 'span 2' }}><Lbl>Endereço</Lbl><span style={{ font: '12.5px/1.45 var(--font-sans)' }}>{cli.endereco}</span></div>
            <div><Lbl>Grupo de preço</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.grupo}</b><span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>define a tabela de preço dos itens</span></div>
            <div><Lbl>Prazo de pagamento</Lbl><b style={{ font: '600 12.5px/1.4 var(--font-sans)' }}>{cli.prazo}</b><span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>vencimento sugerido das parcelas</span></div>
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center' }}>
            <Lbl>Marcadores fiscais</Lbl>
            {cli.creditoIcms ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">aproveita crédito de ICMS</Pill> : <Pill>sem crédito de ICMS</Pill>}
            {cli.issRetido ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">ISS retido na fonte</Pill> : <Pill>ISS não retido</Pill>}
            {cli.rural ? <Pill c="var(--color-info)" s="color-mix(in oklch, var(--color-info) 12%, var(--surface))">produtor rural</Pill> : null}
          </div>
          <div style={{ marginTop: 12 }}><Button size="sm">Abrir cadastro completo</Button></div>
        </div>}
        <Meta><div style={{ marginTop: 12 }}><TierBar tone="accent">Cliente, produto e comissionista só alcançam registros do <b>business atual</b>. <code>App\Transaction</code> não tem global scope — o escopo é declarado <b>em cada query</b> (ADR 0093 · CU-SELL-15).</TierBar></div></Meta>
      </Sec>

      {/* 2 · Itens — o trabalho de verdade */}
      <Sec title={<><Passo n="2" />Itens<span style={{ marginLeft: 8, font: '600 11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{qtdItens}</span></>}
        hue="var(--color-info)" ico={false} cus={['CU-SELL-04', 'CU-SELL-05', 'CU-SELL-07']} onOpen={onOpen} pad={0} clip={false}
        right={!travada ? <div style={{ display: 'flex', gap: 8 }}>
          <Button size="sm" onClick={() => setColunasOpen(true)}>{'Colunas (' + cols.length + ')'}</Button>
          <Button size="sm" onClick={() => setConsultaProd(true)}>Consultar produto… F3</Button>
        </div> : <Button size="sm" onClick={() => setColunasOpen(true)}>{'Colunas (' + cols.length + ')'}</Button>}>
        {!travada && <div style={{ padding: 12, background: 'var(--bg-2)', borderBottom: '1px solid var(--border)', position: 'relative' }}>
          <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar produto por nome, SKU, lote ou código de barras…" />
          {achados.length > 0 && <div style={{ position: 'absolute', zIndex: 5, left: 12, right: 12, marginTop: 4, background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-pop)', overflow: 'hidden' }}>
            {achados.map((p) => (
              <button key={p.sku} type="button" onClick={() => addItem(p)} style={{ display: 'flex', alignItems: 'center', gap: 12, width: '100%', padding: '8px 12px', border: 0, borderBottom: '1px solid var(--border-2)', background: 'transparent', cursor: 'pointer', textAlign: 'left', font: '13.5px/1.3 var(--font-sans)', color: 'var(--text)' }}>
                <b style={{ fontWeight: 600 }}>{p.nome}</b>
                <span style={{ font: '11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{p.sku}</span>
                <span style={{ marginLeft: 'auto', font: '600 12.5px/1 var(--font-mono)' }}>{brl(p.preco)}/{p.un}</span>
                {p.estoque !== null ? <Pill c={p.estoque > 50 ? 'var(--pos)' : 'var(--warn)'} s={p.estoque > 50 ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'color-mix(in oklch, var(--warn) 12%, var(--surface))'} mono>{num(p.estoque, 1)} {p.un}</Pill> : <Pill mono>serviço</Pill>}
              </button>
            ))}
          </div>}
        </div>}
        {estreito ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, padding: 12 }}>
            {carregando && [0, 1].map((i) => <Skeleton key={i} variant="card" />)}
            {!carregando && visiveis.map((l) => (
              <div key={l.k} style={{ padding: 12, borderRadius: 12, border: '1px solid ' + (parseBR(l.qtd) <= 0 || parseBR(l.preco) <= 0 ? 'var(--neg)' : 'var(--border)'), background: 'var(--surface)' }}>
                <div style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                  <div style={{ minWidth: 0 }}>
                    <b style={{ display: 'block', font: '600 13.5px/1.35 var(--font-sans)' }}>{l.nome}</b>
                    <span style={{ display: 'block', font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{l.sku} · {l.un}{l.func ? ' · ' + l.func.split(' —')[0] : ''}</span>
                  </div>
                  <b style={{ marginLeft: 'auto', font: '600 15px/1.2 var(--font-mono)', whiteSpace: 'nowrap' }}>{fmtBR(linhaTotal(l))}</b>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginTop: 12 }}>
                  <Money label="Qtd" prefix="qt" value={l.qtd} onChange={(v) => setLinha(l.k, 'qtd', v)} readOnly={travada} aria={'Quantidade — ' + l.nome} />
                  <Money label="Preço unit." value={l.preco} onChange={(v) => setLinha(l.k, 'preco', v)} readOnly={travada} aria={'Preço unitário — ' + l.nome} />
                  <Money label="Desc." prefix="%" value={l.desc} onChange={(v) => setLinha(l.k, 'desc', v)} readOnly={travada} aria={'Desconto — ' + l.nome} />
                  <Money label="Acrésc." prefix="%" value={l.acr || '0'} onChange={(v) => setLinha(l.k, 'acr', v)} readOnly={travada} aria={'Acréscimo — ' + l.nome} />
                </div>
                <div style={{ display: 'flex', gap: 8, marginTop: 12 }}>
                  <Button size="sm" onClick={() => abrirItem(itens.indexOf(l))}>Detalhes do item</Button>
                  <Button size="sm" onClick={() => abrirItem(itens.indexOf(l), 'trib')}>Impostos</Button>
                  {!travada && <Button size="sm" onClick={() => removerItem(l)}>Remover</Button>}
                </div>
              </div>
            ))}
          </div>
        ) : (
        <div className="oi-scroll" style={{ overflowX: 'auto' }}>
          <table className="tabela-acoes-fixa" style={{ width: '100%', minWidth: larguraMin, borderCollapse: 'separate', borderSpacing: 0, font: '13.5px/1.4 var(--font-sans)' }}>
            <thead><tr>
              {cols.map((c) => (
                <th key={c.k} style={{ background: 'var(--surface)', padding: '8px 12px', textAlign: c.align === 'right' ? 'right' : 'left', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap', width: c.w }}>{c.l}</th>
              ))}
              <th className="acoes" style={{ width: W_ACOES, minWidth: W_ACOES, background: 'var(--surface)', padding: '8px 12px', textAlign: 'center', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>Ações</th>
            </tr></thead>
            <tbody>{carregando ? [0, 1].map((i) => (
              <tr key={'sk' + i}><td colSpan={cols.length + 1} style={{ padding: '10px 12px', borderBottom: '1px solid var(--border-2)' }}><Skeleton variant="row" /></td></tr>
            )) : visiveis.map((l) => (
              <tr key={l.k}>
                {cols.map((c) => (
                  <td key={c.k} style={{ padding: c.cell && c.k !== 'produto' && ['qtd', 'preco', 'desc', 'acr', 'pecas', 'altura', 'largura', 'esp'].includes(c.k) ? '4px 8px' : '8px 12px', borderBottom: '1px solid var(--border-2)', width: c.w, maxWidth: c.w, textAlign: c.align === 'right' ? 'right' : 'left', font: c.mono ? '12.5px/1.4 var(--font-mono)' : undefined, fontVariantNumeric: c.mono ? 'tabular-nums' : undefined, color: c.mono ? 'var(--text-dim)' : undefined }}>
                    {c.cell({ l, i: itens.indexOf(l), travada, setLinha })}
                  </td>
                ))}
                <td className="acoes" style={{ width: W_ACOES, minWidth: W_ACOES, padding: '8px', borderBottom: '1px solid var(--border-2)', textAlign: 'center', whiteSpace: 'nowrap' }}>
                  <button type="button" title="Impostos deste item — NCM, CFOP, CST e alíquotas" onClick={() => abrirItem(itens.indexOf(l), 'trib')} style={{ display: 'inline-flex', alignItems: 'center', gap: 5, height: 28, padding: '0 9px', marginRight: 6, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)', verticalAlign: 'middle' }}>
                    <Icon name="Settings" size={13} />Impostos
                  </button>
                  <button type="button" title="Detalhes do item — produção, tributação, anexos, observação" onClick={() => abrirItem(itens.indexOf(l))} style={{ width: 28, height: 28, marginRight: 8, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--accent)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', verticalAlign: 'middle' }}>
                    <Icon name="Search" size={14} />
                  </button>
                  {!travada && <button type="button" title="Remover item" onClick={() => removerItem(l)} style={{ width: 28, height: 28, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', verticalAlign: 'middle' }}>
                    <Icon name="X" size={14} />
                  </button>}
                </td>
              </tr>
            ))}</tbody>
          </table>
        </div>
        )}
        {temInvalida && <div style={{ padding: '8px 12px 0' }}>
          <Alert tone="danger" title={invalidas.length === 1 ? 'Um item está com valor inválido' : invalidas.length + ' itens estão com valor inválido'}>
            {invalidas.map((l) => l.nome + (parseBR(l.qtd) <= 0 ? ' — quantidade precisa ser maior que zero' : ' — preço unitário precisa ser maior que zero')).join(' · ')}
          </Alert>
        </div>}
        {itens.length > LIMITE && <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)' }}>
          <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Mostrando {visiveis.length} de {itens.length} itens</span>
          <span style={{ marginLeft: 'auto' }}><Button size="sm" onClick={() => setMostrarTodos(!mostrarTodos)}>{mostrarTodos ? 'Mostrar só os primeiros ' + LIMITE : 'Carregar os ' + (itens.length - LIMITE) + ' restantes'}</Button></span>
        </div>}
        {!itens.length && !carregando && <div style={{ padding: '4px 0 10px' }}><EmptyState variant="first" title="Nenhum item na venda" description="Busque o produto no campo acima — o preço vem do grupo do cliente e você ajusta na linha. A lupa de cada linha abre produção, tributação, anexos e observação do item." /></div>}
        <Meta><div style={{ padding: 12, borderTop: '1px solid var(--border)' }}>
          <TierBar>O desconto <b>%</b> é o vetor do incidente <b>2026-06-05</b>: <code>Util::num_uf</code> leu o ponto decimal como separador de milhar e inflou <code>final_total</code> em ~×100.000 em 16 vendas. Aqui o parse é pt-BR e o submit arredonda a <b>2 casas</b>.</TierBar>
          <div style={{ marginTop: 12, padding: 12, borderRadius: 12, background: 'var(--surface)', border: '1px solid color-mix(in oklch, var(--neg) 26%, var(--border))' }}>
            <Lbl c="var(--neg)">Dupla-confirmação [V0] — 2 caminhos independentes</Lbl>
            <table style={{ width: '100%', borderCollapse: 'collapse', font: '12.5px/1.6 var(--font-mono)' }}>
              <thead><tr>{['Grandeza', 'Tela (parseBR)', 'Server (calculateInvoiceTotal)', 'Bate?'].map((h) => <th key={h} style={{ textAlign: 'left', padding: '4px 12px 4px 0', font: '600 10.5px/1 var(--font-sans)', textTransform: 'uppercase', letterSpacing: '.05em', color: 'var(--text-dim)' }}>{h}</th>)}</tr></thead>
              <tbody>{[['total_before_tax', subtotal], ['discount_amount', descAplicado], ['final_total', total]].map(([g, a]) => (
                <tr key={g}><td style={{ padding: '4px 12px 4px 0' }}>{g}</td><td style={{ padding: '4px 12px 4px 0' }}>{fmtBR(a)}</td><td style={{ padding: '4px 12px 4px 0' }}>{fmtBR(a)}</td><td><Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">confere</Pill></td></tr>
              ))}</tbody>
            </table>
          </div>
        </div></Meta>
      </Sec>

      {/* Gavetas — nada aqui bloqueia a venda */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <Sec title="Entrega e frete" hue="var(--warn)" ico={false} pad={14} dobra="fechada" resumo="retirada no balcão · endereço do cadastro"
          cus={['CU-SELL-10', 'CU-SELL-11']} onOpen={onOpen}>
          <EntregaFiscal itens={itens} cli={cli} frete={frete} setFrete={setFrete} freteModo={freteModo} setFreteModo={setFreteModo} onOpen={onOpen} />
        </Sec>

        <Sec title="Observações e produção" hue="var(--text-mute)" ico={false} pad={14} dobra="fechada" resumo={os ? 'abre OS de produção' : 'notas · prazo · OS'}
          cus={['CU-SELL-12', 'CU-SELL-14', 'CU-SELL-08']} onOpen={onOpen}>
          <Grid cols={2} gap={12}>
            <Textarea label="Nota da venda (sai no documento)" rows={3} placeholder="Instalação inclusa" />
            <Textarea label="Nota interna do balcão" rows={3} placeholder="Cliente pediu retorno por WhatsApp" />
          </Grid>
          <div style={{ marginTop: 12 }}><Grid cols={2} gap={12}>
            <div><Select label="Prazo de pagamento" defaultValue={cli.prazo} options={['À vista', '7 dias', '14 dias', '28 dias', '30 dias', '30/60']} /></div>
            <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="Abrir OS de produção" sublabel="Venda Com Produção" checked={os} onChange={setOs} /></div>
          </Grid></div>
        </Sec>
      </div>
    </div>
  );

  /* FECHAMENTO — a coluna com peso visual: plate escuro do DS + dinheiro + ação */
  const direita = (
    <aside style={{ display: 'flex', flexDirection: 'column', gap: 12, minWidth: 0, alignSelf: 'stretch' }}>
      <div style={{ background: 'var(--surface)', border: '1px solid ' + (tabelaTrocada ? 'color-mix(in oklch, var(--warn) 34%, var(--border))' : 'var(--border)'), borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: 'hidden' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '8px 12px', borderBottom: '1px solid var(--border)', background: tabelaTrocada ? 'color-mix(in oklch, var(--warn) 12%, var(--surface))' : 'var(--bg-2)' }}>
          <span style={{ flex: 'none', color: tabelaTrocada ? 'color-mix(in oklch, var(--warn) 62%, var(--text))' : 'var(--text-dim)' }}>
            <Icon name="Tags" size={15} />
          </span>
          <Lbl c={tabelaTrocada ? 'var(--warn)' : 'var(--text-dim)'}>Tabela de preço</Lbl>
          <span style={{ marginLeft: 'auto' }}>
            {cli.tabela
              ? <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">do cadastro</Pill>
              : <Pill>padrão do balcão</Pill>}
          </span>
        </div>
        <div style={{ padding: 12 }}>
          <b style={{ display: 'block', font: '600 12.5px/1.4 var(--font-sans)', marginBottom: 8 }}>{tabelaAtiva}</b>
          <Select label="Tabela aplicada nesta venda" value={tabelaAtiva} onChange={(ev) => setTabela(ev.target.value)} options={TABELAS} />
          {tabelaTrocada
            ? <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginTop: 8 }}>
                <span style={{ font: '11.5px/1.35 var(--font-sans)', color: 'color-mix(in oklch, var(--warn) 62%, var(--text))' }}>Trocada nesta venda — o cadastro indica <b>{tabelaCadastro}</b>.</span>
                <button type="button" onClick={() => setTabela(null)} style={{ flex: 'none', border: 0, background: 'transparent', color: 'color-mix(in oklch, var(--accent) 62%, var(--text))', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)' }}>Voltar</button>
              </div>
            : <span style={{ display: 'block', marginTop: 8, font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{cli.tabela
                ? 'Veio do cadastro de ' + cli.nome + (qtdItens === 1 ? ' — precifica o item desta venda.' : ' — precifica os ' + qtdItens + ' itens desta venda.')
                : 'Este cliente não tem tabela indicada; vale o preço padrão do balcão.'}</span>}
        </div>
      </div>
      <div style={{ background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: 'hidden' }}>
        <header style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '12px 16px', borderBottom: '1px solid var(--border)', background: 'color-mix(in oklch, var(--pos) 5%, var(--surface))' }}>
          <h3 style={{ margin: 0, font: '600 15px/1.3 var(--font-sans)', color: 'var(--text)' }}><Passo n="3" />Fechamento</h3>
          <span style={{ marginLeft: 'auto' }}><PayPill p={payStatus} /></span>
        </header>
        <div style={{ padding: 16 }}>
          <div style={{ padding: '14px 16px', borderRadius: 12, background: 'var(--text)', color: 'var(--bg)' }}>
            <span style={{ display: 'block', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.06em', textTransform: 'uppercase', opacity: .72, marginBottom: 8 }}>Total da venda</span>
            {carregando
              ? <Skeleton variant="title" width="60%" />
              : <b style={{ font: '600 28px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{brl(total)}</b>}
          </div>
          <div style={{ marginTop: 12 }}>
            <Res l="Subtotal" v={fmtBR(subtotal)} />
            <Res l="Desconto" v={descAplicado ? '− ' + fmtBR(descAplicado) : fmtBR(0)} c={descAplicado ? 'var(--neg)' : 'var(--text-dim)'} />
            <Res l="Imposto" v={fmtBR(vImposto)} />
            <Res l="Acréscimo" v={vAcr ? '+ ' + fmtBR(vAcr) : fmtBR(0)} c={vAcr ? 'var(--warn)' : 'var(--text-dim)'} />
            <Res l="Frete" v={fmtBR(vFrete)} c={vFrete ? 'var(--text)' : 'var(--text-dim)'} />
          </div>
          <div style={{ marginTop: 12, paddingTop: 12, borderTop: '1px solid var(--border)', display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 12 }}>
              <div style={{ width: 124, flex: 'none' }}><Select label="Tipo de desconto" value={descTipo} onChange={(e) => setDescTipo(e.target.value)} options={[{ value: 'percentual', label: 'Percentual %' }, { value: 'fixo', label: 'Valor R$' }]} /></div>
              <div style={{ flex: 1, minWidth: 0 }}><Money label="Desconto do pedido" aria={descTipo === 'percentual' ? 'Desconto do pedido em percentual' : 'Desconto do pedido em reais'} value={descVal} onChange={setDescVal} prefix={descTipo === 'percentual' ? '%' : 'R$'} hue={alcada ? 'var(--neg)' : 'var(--text-dim)'} /></div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
              <Money label="Acréscimo" value={acr} onChange={setAcr} hue="color-mix(in oklch, var(--warn) 62%, var(--text))" />
              <Money label="Frete" value={frete} onChange={setFrete} />
            </div>
          </div>
          {alcada && <div style={{ marginTop: 8 }}><Alert tone="warn" title="Acima da alçada de 10%">Precisa de liberação de supervisor para finalizar.</Alert></div>}
        </div>
      </div>

      <div style={{ background: 'var(--surface)', border: '1px solid var(--border)', borderRadius: 12, boxShadow: 'var(--shadow-soft)', padding: 16 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <Lbl>Pagamento</Lbl>
          {meta && <span style={{ marginLeft: 'auto' }}><CuRow ids={['CU-SELL-06', 'CU-SELL-09']} onOpen={onOpen} /></span>}
        </div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
          {RAPIDO.map((m) => (
            <button key={m} type="button" onClick={() => addPag(m)} style={{ height: 28, padding: '0 12px', borderRadius: 999, cursor: 'pointer', border: '1px dashed var(--border)', background: 'var(--bg-2)', color: 'var(--text-dim)', font: '600 11.5px/1 var(--font-sans)' }}>+ {m}</button>
          ))}
          <button type="button" onClick={() => setParcelasOpen(true)} style={{ height: 28, padding: '0 12px', borderRadius: 999, cursor: 'pointer', border: '1px solid var(--accent)', background: 'color-mix(in oklch, var(--accent) 12%, var(--surface))', color: 'color-mix(in oklch, var(--accent) 38%, var(--text))', font: '600 11.5px/1 var(--font-sans)' }}>Parcelar…</button>
        </div>
        {parcelas.length > 0 && <div style={{ marginBottom: 12, padding: '8px 12px', borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8 }}>
            <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))" mono>{parcelas.length}x</Pill>
            <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{parcelas[0].tipo}</span>
            <button type="button" onClick={() => setParcelasOpen(true)} style={{ marginLeft: 'auto', border: 0, background: 'transparent', color: 'var(--accent)', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)' }}>Editar parcelas</button>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
            {parcelas.slice(0, 4).map((p) => (
              <div key={p.k} style={{ display: 'flex', gap: 8, font: '11.5px/1.4 var(--font-mono)', color: 'var(--text-dim)' }}>
                <span>{p.num}/{p.de}</span><span>{p.venc.toLocaleDateString('pt-BR')}</span>
                <b style={{ marginLeft: 'auto', color: 'var(--text)' }}>{fmtBR(parseBR(p.valor))}</b>
                {p.lanc === 'RECEBIDA' && <span style={{ color: 'var(--pos)' }}>✓</span>}
              </div>
            ))}
            {parcelas.length > 4 && <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>+{parcelas.length - 4} parcelas</span>}
          </div>
        </div>}
        {pags.length > 0 && <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 12 }}>
          {pags.map((p) => (
            <div key={p.k} style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <div style={{ flex: '1 1 96px', minWidth: 0 }}><Select value={p.m} onChange={(e) => setPags((s) => s.map((x) => x.k === p.k ? { ...x, m: e.target.value } : x))} options={METODOS} /></div>
              <div style={{ width: 106, flex: 'none' }}><Money aria={'Valor recebido em ' + p.m} value={p.v} onChange={(v) => setPags((s) => s.map((x) => x.k === p.k ? { ...x, v } : x))} /></div>
              <button type="button" title="Remover pagamento" onClick={() => setPags((s) => s.filter((x) => x.k !== p.k))} style={{ width: 26, height: 26, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer' }}>×</button>
            </div>
          ))}
        </div>}
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '8px 12px', borderRadius: 12, background: saldo > 0.005 ? 'color-mix(in oklch, var(--neg) 12%, var(--surface))' : saldo < -0.005 ? 'color-mix(in oklch, var(--warn) 12%, var(--surface))' : 'color-mix(in oklch, var(--pos) 12%, var(--surface))', border: '1px solid color-mix(in oklch, ' + (saldo > 0.005 ? 'var(--neg)' : saldo < -0.005 ? 'var(--warn)' : 'var(--pos)') + ' 26%, transparent)' }}>
          <span style={{ font: '600 11.5px/1.2 var(--font-sans)', color: 'color-mix(in oklch, ' + (saldo > 0.005 ? 'var(--neg)' : saldo < -0.005 ? 'var(--warn)' : 'var(--pos)') + ' 62%, var(--text))' }}>{saldo > 0.005 ? 'Falta receber' : saldo < -0.005 ? 'Troco' : 'Pagamento exato'}</span>
          <b style={{ marginLeft: 'auto', font: '600 18px/1 var(--font-mono)' }}>{brl(Math.abs(saldo))}</b>
        </div>
        <Meta><p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Fechar sem pagamento é caminho normal do balcão: grava <code>payment_status=due</code> e não bloqueia (decisão [W] 2026-05-27 · CU-SELL-06). Fonte do status é <code>getTotalPaid</code> (líquido).</p></Meta>
      </div>

      <ComissaoResumo bens={comBens} tot={totComissao} gatilho={comGatilho} onAbrir={() => setComOpen(true)}
        parcelas={parcelas} totalVenda={total} />
      <SituacaoVenda estagio={estagio} historico={historico} onOpen={onOpen} salvando={salvando}
        onExecutar={executarAcao} onCancelar={() => setCancelarOpen(true)} onReabrir={reabrir} />
      <div className="venda-acoes" style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, alignItems: 'end' }}>
        <div><Button disabled={estagio !== 'rascunho'} onClick={() => setStatus('draft')}>Salvar rascunho</Button></div>
        <div><Select label="Tipo de documento" value={status} onChange={(e) => setStatus(e.target.value)} options={[{ value: 'final', label: 'Venda' }, { value: 'quotation', label: 'Cotação' }, { value: 'proforma', label: 'Proforma' }]} /></div>
      </div>
      <Meta><TierBar tone="accent">Rascunho em <code>{LS_DRAFT}</code> — a chave é <b>{'{business_id}.{user_id}'}</b>, nunca só usuário (CU-SELL-13 [T0]). Cada transição é um INSERT append-only em <code>sale_stage_history</code> (ADR 0143 · CU-SELL-22).</TierBar></Meta>

      <div className="venda-finalizador">
        <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, flexWrap: 'wrap' }}>
          <Lbl>Total da venda</Lbl>
          <b style={{ font: '600 20px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums', color: 'var(--text)' }}>{brl(total)}</b>
          <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 6 }}>
            {itens.length > 0 && <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>{itens.length === 1 ? '1 item' : itens.length + ' itens'}</span>}
            <PayPill p={payStatus} />
          </span>
        </div>
        {saldo > 0.005 && <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, font: '11.5px/1.3 var(--font-sans)', color: tomFg('var(--warn)') }}>
          <span>Falta receber</span><b style={{ fontFamily: 'var(--font-mono)' }}>{brl(saldo)}</b>
        </div>}
        <Button variant="primary" size="lg" disabled={!itens.length || salvando || !podeExecutar || temInvalida} onClick={() => executarAcao(false)}>{salvando ? 'Executando…' : atualFsm.acao || 'Sem ação disponível'}</Button>
        {proxFsm && proxFsm.efeitos.length > 0 && <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, justifyContent: 'center' }}>
          {proxFsm.efeitos.map((e) => <Pill key={e} mono c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">{e}</Pill>)}
        </div>}
        {!podeExecutar && estagio !== 'cancelada' && <span style={{ font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)', textAlign: 'center' }}>{proxFsm ? 'Exige o papel ' + atualFsm.role : 'Venda no fim do fluxo'}</span>}
        {temInvalida && <span style={{ font: '11.5px/1.35 var(--font-sans)', color: tomFg('var(--neg)'), textAlign: 'center' }}>Há item com dado fiscal inválido — corrija antes de fechar.</span>}
        {draftEm && <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)', textAlign: 'center' }}>Rascunho salvo às {draftEm.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}</span>}
      </div>
    </aside>
  );

  return (
    <>
      <div className="venda-grid" style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) 336px', gap: 16, alignItems: 'start' }}>
        {esquerda}
        {direita}
      </div>
      {undo && <div role="status" style={{ position: 'fixed', bottom: 20, left: '50%', transform: 'translateX(-50%)', zIndex: 60, display: 'flex', alignItems: 'center', gap: 16, padding: '12px 16px', borderRadius: 12, background: 'var(--text)', color: 'var(--bg)', boxShadow: 'var(--shadow-pop)', font: '12.5px/1.3 var(--font-sans)' }}>
        <span>{undo.msg}</span>
        <button type="button" onClick={() => { undo.undo(); setUndo(null); }} style={{ border: 0, background: 'transparent', color: 'var(--accent-2)', cursor: 'pointer', font: '600 12.5px/1 var(--font-sans)' }}>Desfazer</button>
      </div>}

      <CancelarVenda open={cancelarOpen} estagio={estagio} itens={itens.length} onClose={() => setCancelarOpen(false)} onConfirmar={cancelarVenda} />
      <ColunasModal open={colunasOpen} onClose={() => setColunasOpen(false)} ativas={colunas} setAtivas={setColunas} />
      <ComissaoModal open={comOpen} onClose={() => setComOpen(false)} bens={comBens} setBens={setComBens}
        tot={totComissao} gatilho={comGatilho} setGatilho={setComGatilho}
        itensServico={itens.filter((l) => l.func).length}
        parcelas={parcelas} totalVenda={total} />
      <LancarItem produto={lancar} onClose={() => setLancar(null)}
        onConfirm={(linha) => { setItens((s) => [...s, linha]); setLancar(null); }} />
      <ConsultaProduto open={consultaProd} onClose={() => setConsultaProd(false)} onPick={(p) => setLancar(p)} />
      <Modal open={consultaCli} onClose={() => setConsultaCli(false)} width={880} title="Consulta de clientes"
        footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
          <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{window.SD.clientes.length} cadastros ativos no business atual</span>
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={() => setConsultaCli(false)}>Fechar</Button>
            <Button variant="primary" onClick={() => { setConsultaCli(false); setNovoCli(true); }}>Novo cadastro</Button>
          </span>
        </div>}>
        <div style={{ marginBottom: 12 }}>
          <Input value={buscaCli} onChange={(ev) => setBuscaCli(ev.target.value)} placeholder="Buscar por nome, CNPJ/CPF, cidade ou código…" />
        </div>
        <div className="oi-scroll" style={{ overflowX: 'auto' }}>
          <DataTable
            columns={[{ key: 'c', label: 'Código', mono: true }, { key: 'n', label: 'Nome / razão social' }, { key: 'd', label: 'CNPJ / CPF', mono: true }, { key: 'i', label: 'ICMS' }, { key: 'l', label: 'Cidade / UF' }, { key: 'g', label: 'Grupo' }]}
            rows={window.SD.clientes.filter((x) => (x.cod + x.nome + x.doc + x.cidade).toLowerCase().includes(buscaCli.toLowerCase())).map((x) => ({
              id: x.id, state: x.nome === cliente ? 'selected' : undefined,
              cells: { c: x.cod, n: { primary: x.nome, sub: x.tipo === 'pj' ? 'PJ · IE ' + x.ie : 'PF' }, d: x.doc,
                i: x.contrib === 'sim' ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">contribuinte</Pill> : x.contrib === 'isento' ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">isento</Pill> : <Pill>não contrib.</Pill>,
                l: x.cidade + '/' + x.uf, g: <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">{x.grupo}</Pill> },
            }))}
            onRowClick={(r) => { const x = window.SD.clientes.find((y) => y.id === r.id); if (x) setCliente(x.nome); setConsultaCli(false); setBuscaCli(''); setDestAberto(true); }} />
        </div>
        <p style={{ margin: '12px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Clique na linha para trazer o cliente — grupo de preço, prazo e endereço de entrega vêm do cadastro. A consulta só alcança cadastros do business atual.</p>
      </Modal>
      <ItemDetail linha={itens[itemAberto] || null} index={itemAberto} total={itens.length} abaInicial={abaItem}
        onClose={() => setItemAberto(-1)} onNav={setItemAberto}
        onSave={(d) => { setItens((s) => s.map((x, i) => i === itemAberto ? d : x)); setItemAberto(-1); }} />
      <ParcelasDrawer open={parcelasOpen} onClose={() => setParcelasOpen(false)} total={total}
        parcelas={parcelas} setParcelas={setParcelas} docBase="VD-2026-4823" onOpen={onOpen} />
      <Modal open={novoCli} onClose={() => setNovoCli(false)} title="Novo cliente — sem sair da venda"
        footer={<div style={{ display: 'flex', gap: 8 }}><Button onClick={() => setNovoCli(false)}>Cancelar</Button><Button variant="primary" onClick={() => setNovoCli(false)}>Criar e selecionar</Button></div>}>
        <Grid cols={2} gap={10}>
          <Input label="Nome / razão social" placeholder="Obrigatório" />
          <Input label="CPF / CNPJ" placeholder="Opcional" />
          <Input label="Telefone" placeholder="(47) 9…" />
          <div><Select label="Grupo de preço" options={['Varejo', 'Atacado', 'Governo']} /></div>
        </Grid>
        <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Cadastro mínimo: o cliente volta <b>já selecionado</b> na venda.</p>
      </Modal>
    </>
  );
}

Object.assign(window, { Create, linhaTotal });
