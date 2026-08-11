/* Lançamento do item — entre escolher o produto e ele entrar na venda.
   Medidas (peças × altura × largura × espessura) quando a unidade é dimensional,
   valor unitário sob permissão, e funcionário vinculado quando o item é serviço.
   Também mora aqui a consulta de produtos (mesmo padrão de cliente/transportadora). */

/* quem executa serviço: funcionário ou técnico do cadastro único (window.SD.pessoas) */
const execOpcoes = () => [...comOpcoes('funcionario'), ...comOpcoes('tecnico')];
const DIMENSIONAL = { 'm²': ['pecas', 'altura', 'largura'], 'm³': ['pecas', 'altura', 'largura', 'esp'], m: ['pecas', 'largura'] };
/* "Informações Adicionais do Produto / Serviço" do legado — o que a maioria dos clientes preenche em todo item */
const LOCAIS = ['Fachada', 'Interno', 'Veículo', 'Painel', 'Vitrine', 'Totem', 'Obra'];
const IMPRESSOES = ['Digital — látex', 'Digital — UV', 'Offset', 'Recorte eletrônico', 'Sublimação', 'Sem impressão'];
/* data guardada em ISO (o DataCampo aceita Date|ISO|dd/mm/aaaa e exibe dd/mm/aaaa) */
const dISO = (d) => (d instanceof Date && !isNaN(d)) ? d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') : '';

function LancarItem({ produto, onClose, onConfirm }) {
  const meta = useMeta();
  const podePreco = window.SD.permissoes.editarPrecoItem;
  const dims = produto ? DIMENSIONAL[produto.un] : null;
  const servico = produto && produto.tipo === 'servico';
  const [pecas, setPecas] = React.useState('1');
  const [altura, setAltura] = React.useState('0,50');
  const [largura, setLargura] = React.useState('0,10');
  const [esp, setEsp] = React.useState('0,00');
  const [qtdDireta, setQtdDireta] = React.useState('1');
  const [preco, setPreco] = React.useState('0,00');
  const [desc, setDesc] = React.useState('0');
  const [acr, setAcr] = React.useState('0');
  const [func, setFunc] = React.useState(() => comPrimeira('funcionario'));
  const [obs, setObs] = React.useState('');
  const [obsProd, setObsProd] = React.useState('');
  const [local, setLocal] = React.useState('');
  const [impressao, setImpressao] = React.useState('');
  const [prazoEquipe, setPrazoEquipe] = React.useState('');
  const [prazoEtapa, setPrazoEtapa] = React.useState('');
  const [adicAberto, setAdicAberto] = React.useState(false);

  React.useEffect(() => {
    if (!produto) return;
    setPreco(fmtBR(produto.preco)); setPecas('1'); setQtdDireta('1'); setDesc('0'); setAcr('0');
    setAltura(produto.un === 'm²' || produto.un === 'm³' ? '0,50' : '0,00');
    setLargura(produto.un === 'm²' || produto.un === 'm³' || produto.un === 'm' ? '0,10' : '0,00');
    setEsp('0,00'); setObs(''); setObsProd(''); setLocal(''); setImpressao(''); setPrazoEquipe(''); setPrazoEtapa('');
  }, [produto]);
  if (!produto) return null;

  const nPecas = Math.max(parseBR(pecas), 0);
  const areaUn = produto.un === 'm²' ? submitSafe(parseBR(altura) * parseBR(largura))
    : produto.un === 'm³' ? submitSafe(parseBR(altura) * parseBR(largura) * parseBR(esp))
    : produto.un === 'm' ? submitSafe(parseBR(largura)) : 1;
  const qtd = dims ? submitSafe(nPecas * areaUn) : submitSafe(parseBR(qtdDireta));
  const unitario = submitSafe(parseBR(preco) * (1 - parseBR(desc) / 100) * (1 + parseBR(acr) / 100));
  const total = submitSafe(qtd * unitario);
  const abaixoDoPiso = parseBR(preco) < produto.preco * 0.85;
  const preenchidos = [obs, obsProd, local, impressao, prazoEquipe, prazoEtapa].filter((v) => v && v.trim()).length;
  const semEstoque = produto.estoque !== null && qtd > produto.estoque;

  const confirmar = () => onConfirm({
    k: Date.now(), sku: produto.sku, nome: produto.nome, un: produto.un,
    qtd: fmtBR(qtd), preco: fmtBR(parseBR(preco)), desc: String(parseBR(desc)), acr: String(parseBR(acr)),
    pecas: fmtBR(nPecas), altura, largura, esp, func: servico ? func : null, obsItem: obs,
    obsProd, local, impressao, prazoEquipe, prazoEtapa,
  });

  return (
    <Modal open={!!produto} onClose={onClose} width={720}
      title={'Lançar ' + produto.nome}
      footer={<div style={{ display: 'flex', gap: 12, alignItems: 'center', width: '100%' }}>
        <div>
          <Lbl>Total do item</Lbl>
          <b style={{ font: '600 18px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{brl(total)}</b>
        </div>
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={onClose}>Cancelar</Button>
          <Button variant="primary" disabled={qtd <= 0} onClick={confirmar}>Adicionar à venda</Button>
        </span>
      </div>}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
          <Pill mono>{produto.sku}</Pill>
          <Pill c={servico ? 'var(--color-info)' : 'var(--accent)'} s={servico ? 'color-mix(in oklch, var(--color-info) 12%, transparent)' : 'color-mix(in oklch, var(--accent) 12%, var(--surface))'}>{servico ? 'serviço' : 'produto'}</Pill>
          <Pill mono>unidade {produto.un}</Pill>
          {produto.estoque !== null
            ? <Pill c={semEstoque ? 'var(--neg)' : 'var(--pos)'} s={semEstoque ? 'color-mix(in oklch, var(--neg) 12%, var(--surface))' : 'color-mix(in oklch, var(--pos) 12%, var(--surface))'} mono>estoque {num(produto.estoque, 2)} {produto.un}</Pill>
            : <Pill mono>não controla estoque</Pill>}
        </div>

        {dims && <div>
          <Lbl>Medidas</Lbl>
          <Grid cols={4} gap={12}>
            <Money label="Peças" prefix="qt" value={pecas} onChange={setPecas} />
            <Money label="Altura" prefix="m" value={altura} onChange={setAltura} />
            <Money label="Largura" prefix="m" value={largura} onChange={setLargura} />
            {produto.un === 'm³'
              ? <Money label="Espessura" prefix="m" value={esp} onChange={setEsp} />
              : <div><Lbl>Medida da peça</Lbl><b style={{ font: '600 13.5px/1.4 var(--font-mono)' }}>{num(parseBR(altura), 2)} × {num(parseBR(largura), 2)} m</b><span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>{num(areaUn, 3)} {produto.un} por peça</span></div>}
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center', padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
            <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>
              {num(nPecas, 0)} peça(s) de {num(parseBR(altura), 2)} × {num(parseBR(largura), 2)} m
            </span>
            <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'baseline', gap: 8 }}>
              <Lbl>Quantidade faturada</Lbl>
              <b style={{ font: '600 15px/1 var(--font-mono)' }}>{num(qtd, 2)} {produto.un}</b>
            </span>
          </div>
        </div>}

        {!dims && <div>
          <Grid cols={3} gap={12}>
            <Money label={produto.un === 'h' ? 'Horas' : 'Quantidade'} prefix={produto.un === 'h' ? 'h' : 'qt'} value={qtdDireta} onChange={setQtdDireta} />
            <div><Lbl>Unidade</Lbl><b style={{ font: '600 13.5px/1.4 var(--font-mono)' }}>{produto.un}</b></div>
            <div />
          </Grid>
        </div>}

        <div>
          <Lbl>Valores</Lbl>
          <Grid cols={4} gap={12}>
            <Money label="Valor de tabela" value={fmtBR(produto.preco)} onChange={() => {}} readOnly />
            <Money label="Valor unitário" value={preco} onChange={setPreco} readOnly={!podePreco}
              help={podePreco ? null : 'Seu perfil não pode alterar preço'} hue={abaixoDoPiso ? 'var(--neg)' : 'var(--text-dim)'} />
            <Money label="Desconto" prefix="%" value={desc} onChange={setDesc} readOnly={!podePreco} />
            <Money label="Acréscimo" prefix="%" value={acr} onChange={setAcr} readOnly={!podePreco} />
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
            <div><Lbl>Unitário líquido</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{brl(unitario)}</b></div>
            <div><Lbl>Quantidade</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{num(qtd, 2)} {produto.un}</b></div>
            <div style={{ marginLeft: 'auto' }}><Lbl c="var(--accent)">Total do item</Lbl><b style={{ font: '600 17px/1 var(--font-mono)' }}>{brl(total)}</b></div>
          </div>
          {!podePreco && <div style={{ marginTop: 8 }}><Alert tone="info" title="Preço travado pelo perfil">O valor vem da tabela aplicada na venda. Pedir liberação ao supervisor para alterar.</Alert></div>}
          {podePreco && abaixoDoPiso && <div style={{ marginTop: 8 }}><Alert tone="warn" title="Abaixo do piso de preço">{brl(parseBR(preco))} está mais de 15% abaixo da tabela ({brl(produto.preco)}). Finalizar exige liberação de supervisor.</Alert></div>}
          {semEstoque && <div style={{ marginTop: 8 }}><Alert tone="danger" title="Quantidade acima do estoque">Pedido de {num(qtd, 2)} {produto.un} com {num(produto.estoque, 2)} em estoque — vai gerar saldo negativo ou pedido de compra.</Alert></div>}
        </div>

        {servico && <div>
          <Lbl>Execução do serviço</Lbl>
          <Grid cols={3} gap={12}>
            <div><Select label="Funcionário vinculado" value={func} onChange={(e) => setFunc(e.target.value)} options={execOpcoes()} /></div>
            <div><DataCampo label="Data prevista" /></div>
            <Money label="Comissão do serviço" prefix="%" value="3,00" onChange={() => {}} />
          </Grid>
          <Meta><p style={{ margin: '8px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Serviço não move estoque e o funcionário vinculado é quem entra na apuração de comissão e na OP — por isso o campo só aparece quando <code>tipo=servico</code>.</p></Meta>
        </div>}

        <div>
          <button type="button" onClick={() => setAdicAberto(!adicAberto)} aria-expanded={adicAberto}
            style={{ display: 'flex', alignItems: 'center', gap: 6, padding: '4px 0', border: 0, background: 'transparent', cursor: 'pointer', color: 'var(--text-dim)', font: '600 11px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase' }}>
            <Icon name="ChevronDown" size={13} style={{ transform: adicAberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
            Informações adicionais do item
            {!adicAberto && preenchidos > 0 && <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">{preenchidos} preenchido{preenchidos > 1 ? 's' : ''}</Pill>}
          </button>
          {adicAberto && <div style={{ marginTop: 8, display: 'flex', flexDirection: 'column', gap: 12 }}>
            <Grid cols={2} gap={12}>
              <Textarea label="Observação (uso interno)" rows={2} value={obs} onChange={(e) => setObs(e.target.value)} placeholder="Cliente aprovou arte por e-mail em 27/07" />
              <Textarea label="Observação para a produção" rows={2} value={obsProd} onChange={(e) => setObsProd(e.target.value)} placeholder="Sangria de 5cm, ilhós a cada 50cm" />
            </Grid>
            <Grid cols={4} gap={12}>
              <div><Select label="Local da aplicação" value={local} onChange={(e) => setLocal(e.target.value)} options={[{ value: '', label: '—' }, ...LOCAIS.map((l) => ({ value: l, label: l }))]} /></div>
              <div><Select label="Tipo de impressão" value={impressao} onChange={(e) => setImpressao(e.target.value)} options={[{ value: '', label: '—' }, ...IMPRESSOES.map((l) => ({ value: l, label: l }))]} /></div>
              <div><DataCampo label="Prazo da equipe (produção)" value={prazoEquipe} onChange={(dt) => setPrazoEquipe(dISO(dt))} /></div>
              <div><DataCampo label="Prazo da etapa" value={prazoEtapa} onChange={(dt) => setPrazoEtapa(dISO(dt))} /></div>
            </Grid>
            <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>A observação de uso interno e a de produção <b>não saem</b> no documento do cliente — a de produção vai na OP. Dá pra revisar tudo depois pela lupa da linha.</span>
          </div>}
        </div>
      </div>
    </Modal>
  );
}

function ConsultaProduto({ open, onClose, onPick }) {
  const [q, setQ] = React.useState('');
  const lista = window.SD.catalogo.filter((p) => (p.sku + p.nome + (p.ean || '') + (p.fabrica || '') + (p.categoria || '')).toLowerCase().includes(q.toLowerCase()));
  return (
    <Modal open={open} onClose={onClose} width={880} title="Consulta de produtos e serviços"
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{window.SD.catalogo.length} itens ativos no business atual</span>
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={onClose}>Fechar</Button>
          <Button variant="primary">Novo cadastro</Button>
        </span>
      </div>}>
      <div style={{ marginBottom: 12 }}>
        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nome, SKU, EAN, código de fábrica ou categoria…" />
      </div>
      <div className="oi-scroll" style={{ overflowX: 'auto' }}>
        <DataTable
          columns={[{ key: 's', label: 'SKU', mono: true }, { key: 'n', label: 'Produto / serviço' }, { key: 'ean', label: 'Cód. EAN', mono: true }, { key: 'fab', label: 'Cód. fábrica', mono: true }, { key: 'cat', label: 'Categoria' }, { key: 't', label: 'Tipo' }, { key: 'u', label: 'Unid.', mono: true }, { key: 'p', label: 'Tabela', align: 'right', mono: true }, { key: 'e', label: 'Estoque', align: 'right', mono: true }]}
          rows={lista.map((p) => ({
            id: p.sku,
            cells: {
              s: p.sku, n: { primary: p.nome, sub: p.obs || '' },
              ean: p.ean || '—', fab: p.fabrica || '—', cat: <Pill>{p.categoria || '—'}</Pill>,
              t: p.tipo === 'servico' ? <Pill c="var(--color-info)" s="color-mix(in oklch, var(--color-info) 12%, transparent)">serviço</Pill> : <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">produto</Pill>,
              u: p.un, p: fmtBR(p.preco),
              e: p.estoque === null ? '—' : num(p.estoque, 2),
            },
          }))}
          onRowClick={(r) => { const p = window.SD.catalogo.find((x) => x.sku === r.id); onClose(); onPick(p); }} />
      </div>
      <p style={{ margin: '12px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Clique na linha para lançar o item — a próxima tela pede medidas, quantidade e, se for serviço, o funcionário vinculado.</p>
    </Modal>
  );
}

Object.assign(window, { LancarItem, ConsultaProduto, execOpcoes });
