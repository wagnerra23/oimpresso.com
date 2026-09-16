/* Entrega e frete da venda — transporte (modelo da NF-e) + endereço de entrega alternativo.
   Peso bruto vem somado dos itens; o checkbox libera digitação manual.
   Endereço vazio = usa o do cadastro do cliente. */

const FRETE_CONTA = [
  '0 — Contratação por conta do Remetente (CIF)',
  '1 — Contratação por conta do Destinatário (FOB)',
  '2 — Contratação por conta de Terceiros',
  '3 — Transporte próprio por conta do Remetente',
  '4 — Transporte próprio por conta do Destinatário',
  '9 — Sem ocorrência de transporte',
];
const ESPECIES = ['Caixa', 'Palete', 'Pacote', 'Rolo', 'Fardo', 'Tubo', 'Bobina', 'Volume'];
const UFS = ['SC', 'PR', 'RS', 'SP', 'RJ', 'MG', 'BA', 'GO', 'DF'];
const TRANSPORTADORAS = [
  { cod: '014', nome: 'Transportadora Sul Ltda', doc: '84.512.330/0001-07', uf: 'SC', cidade: 'Joinville', placa: 'QHA5F21', antt: '58412330', modal: 'Rodoviário' },
  { cod: '027', nome: 'Rodoviário Bordignon Transportes ME', doc: '11.204.877/0001-55', uf: 'SC', cidade: 'Blumenau', placa: 'MKL2B88', antt: '41120487', modal: 'Rodoviário' },
  { cod: '031', nome: 'Expresso Norte Catarinense S/A', doc: '02.998.140/0001-92', uf: 'PR', cidade: 'Curitiba', placa: 'BEE7J45', antt: '30299814', modal: 'Rodoviário' },
  { cod: '045', nome: 'Frota própria — Office Impresso', doc: '—', uf: 'SC', cidade: 'Joinville', placa: 'RJP1A09', antt: '—', modal: 'Frota própria' },
  { cod: '052', nome: 'Log Fácil Entregas Rápidas Eireli', doc: '38.771.905/0001-13', uf: 'SC', cidade: 'Joinville', placa: 'SDA9C77', antt: '73877190', modal: 'Motoboy' },
];

function EntregaFiscal({ itens, cli, frete, setFrete, freteModo, setFreteModo, onOpen }) {
  const meta = useMeta();
  const [conta, setConta] = React.useState(FRETE_CONTA[0]);
  const [pesoManual, setPesoManual] = React.useState(false);
  const [pesoBrutoM, setPesoBrutoM] = React.useState('0,000');
  const [pesoLiqM, setPesoLiqM] = React.useState('0,000');
  const [entregaOutro, setEntregaOutro] = React.useState(false);
  const [saiEm, setSaiEm] = React.useState({ orc: false, vd: true, nf: false });
  const [transp, setTransp] = React.useState(null);
  const [buscaT, setBuscaT] = React.useState('');
  const [consulta, setConsulta] = React.useState(false);
  const [ufVeic, setUfVeic] = React.useState('SC');
  const [modal, setModal] = React.useState('Rodoviário');
  const trazer = (t) => { setTransp(t || null); if (t) { setUfVeic(t.uf); setModal(t.modal); } };

  /* peso somado dos itens (kg por unidade × quantidade) */
  const pesoUn = (l) => parseBR(l.peso !== undefined ? l.peso : (l.un === 'm²' ? '0,450' : l.un === 'un' ? '0,080' : '0,000'));
  const pesoCalc = submitSafe(itens.reduce((s, l) => s + pesoUn(l) * parseBR(l.qtd), 0));
  const pesoBruto = pesoManual ? parseBR(pesoBrutoM) : pesoCalc;
  const pesoLiq = pesoManual ? parseBR(pesoLiqM) : submitSafe(pesoCalc * 0.94);
  const semTransporte = conta.startsWith('9');

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      {/* ————— Frete / transporte ————— */}
      <div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
          <Lbl>Frete e transporte</Lbl>
          <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>o <b>valor</b> do frete fica no fechamento, à direita</span>
          {meta && <span style={{ marginLeft: 'auto' }}><CuRow ids={['CU-SELL-11']} onOpen={onOpen} /></span>}
        </div>
        <Grid cols={2} gap={12}>
          <div><Select label="Frete por conta" value={conta} onChange={(e) => setConta(e.target.value)} options={FRETE_CONTA} /></div>
          <Money label="Valor do frete (entra no total)" value={frete} onChange={setFrete} />
        </Grid>
        {!semTransporte && <>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: 12 }}>
            <div style={{ width: 104, flex: 'none' }}><Input label="Código" value={transp ? transp.cod : ''} onChange={(e) => trazer(TRANSPORTADORAS.find((x) => x.cod === e.target.value))} placeholder="000" /></div>
            <div style={{ flex: '1 1 420px', minWidth: 260 }}><Input label="Nome / razão social da transportadora" value={transp ? transp.nome : ''} onChange={() => {}} placeholder="Digite o código, ou consulte o cadastro →" /></div>
            <div style={{ flex: 'none', paddingBottom: 1 }}><Button onClick={() => setConsulta(true)}>Consultar cadastro… F2</Button></div>
            {transp && <div style={{ flex: 'none', paddingBottom: 7, display: 'flex', gap: 8, alignItems: 'center' }}>
              <Pill mono>{transp.doc}</Pill>
              <button type="button" title="Limpar transportadora" onClick={() => setTransp(null)} style={{ width: 24, height: 24, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer' }}>×</button>
            </div>}
          </div>
          <div style={{ marginTop: 12 }}><Grid cols={4} gap={12}>
            <div><Input label="Placa do veículo" value={transp ? transp.placa : ''} onChange={() => {}} placeholder="ABC1D23" /></div>
            <div><Select label="UF do veículo" value={ufVeic} onChange={(e) => setUfVeic(e.target.value)} options={UFS} /></div>
            <div><Input label="Renavam" placeholder="00000000000" /></div>
            <div><Input label="ANTT / RNTRC" value={transp ? transp.antt : ''} onChange={() => {}} placeholder="sem registro" /></div>
            <div><Select label="Modalidade" value={modal} onChange={(e) => setModal(e.target.value)} options={['Rodoviário', 'Retirada no balcão', 'Frota própria', 'Motoboy', 'Correios / Sedex']} /></div>
            <div><Select label="UF de destino do transporte" options={UFS} /></div>
          </Grid></div>
          <div style={{ marginTop: 12, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12, flexWrap: 'wrap' }}>
              <Lbl>Volumes</Lbl>
              <span style={{ marginLeft: 'auto' }}><Switch label="Informar peso manualmente" sublabel={'Somado dos itens: ' + num(pesoCalc, 3) + ' kg'} checked={pesoManual} onChange={setPesoManual} /></span>
            </div>
            <Grid cols={4} gap={12}>
              <Money label="Quantidade de volumes" prefix="qt" value="1" onChange={() => {}} />
              <div><Select label="Espécie" options={ESPECIES} /></div>
              <div><Input label="Marca" placeholder="Sem marca" /></div>
              <div><Input label="Numeração dos volumes" defaultValue="1" /></div>
              <Money label="Peso bruto (kg)" prefix="kg" value={pesoManual ? pesoBrutoM : num(pesoBruto, 3)} onChange={setPesoBrutoM} readOnly={!pesoManual}
                help={pesoManual ? 'Digitado — ignora o peso dos itens' : 'Calculado pelo peso cadastrado de cada item'} />
              <Money label="Peso líquido (kg)" prefix="kg" value={pesoManual ? pesoLiqM : num(pesoLiq, 3)} onChange={setPesoLiqM} readOnly={!pesoManual} />
              <div><Input label="Código de coleta" placeholder="—" /></div>
              <div><DataCampo label="Data da coleta" /></div>
            </Grid>
          </div>
          <div style={{ marginTop: 12 }}><Grid cols={3} gap={12}>
            <div><Select label="Status da remessa" options={['Pendente', 'Em separação', 'Despachado', 'Entregue', 'Devolvido']} /></div>
            <div><DataCampo label="Previsão de entrega" /></div>
            <div><Input label="Rastreio / conhecimento" placeholder="CT-e ou código de rastreio" /></div>
          </Grid></div>
        </>}
        {semTransporte && <div style={{ marginTop: 12 }}><Alert tone="info" title="Sem ocorrência de transporte">O cliente retira no balcão — a NF-e sai sem grupo de transporte e nenhum campo de volume é exigido.</Alert></div>}
      </div>

      {/* ————— Endereço de entrega ————— */}
      <div style={{ paddingTop: 16, borderTop: '1px solid var(--border)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12, flexWrap: 'wrap' }}>
          <Lbl>Endereço de entrega</Lbl>
          <Switch label="Entregar em outro endereço" sublabel="Vazio = usa o endereço do cadastro do cliente" checked={entregaOutro} onChange={setEntregaOutro} />
        </div>
        {!entregaOutro ? (
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', padding: 12, borderRadius: 12, background: 'color-mix(in oklch, var(--accent) 12%, var(--surface))', border: '1px solid color-mix(in oklch, var(--accent) 22%, transparent)' }}>
            <span style={{ flex: 'none', color: 'var(--accent)' }}>
              <Icon name="MapPin" size={18} />
            </span>
            <div style={{ minWidth: 0 }}>
              <b style={{ font: '600 13.5px/1.4 var(--font-sans)' }}>{cli.nome}</b>
              <span style={{ display: 'block', font: '12.5px/1.45 var(--font-sans)', color: 'var(--text-dim)' }}>
                {cli.tipo === 'pj' ? 'Rua XV de Novembro, 1400 — Centro · Joinville/SC · 89201-601' : 'Endereço do cadastro do cliente'}
              </span>
            </div>
            <span style={{ marginLeft: 'auto' }}><Pill c="var(--accent)" s="transparent">do cadastro</Pill></span>
          </div>
        ) : (
          <>
            <Grid cols={4} gap={12}>
              <div><Input label="Cód. cidade (IBGE)" placeholder="4209102" /></div>
              <div><Input label="Cidade" placeholder="Joinville" /></div>
              <div><Select label="UF" options={UFS} /></div>
              <div><Input label="CEP" placeholder="89201-601" /></div>
              <div><Input label="Logradouro" placeholder="Rua, avenida, rodovia…" /></div>
              <div><Input label="Número" placeholder="1400" /></div>
              <div><Input label="Bairro" placeholder="Centro" /></div>
              <div><Input label="Complemento" placeholder="Galpão 2, fundos" /></div>
              <div><Select label="País" options={['1058 — Brasil', 'Outro']} /></div>
              <div><Input label="Nome do recebedor" placeholder="Quem recebe na obra" /></div>
              <div><Input label="Telefone" placeholder="(47) 9…" /></div>
              <div><Input label="E-mail" placeholder="obra@cliente.com.br" /></div>
              <div><Input label="Inscrição estadual" placeholder="Isento" /></div>
            </Grid>
          </>
        )}
      </div>

      {/* ————— Fiscal do pedido ————— */}
      <div style={{ paddingTop: 16, borderTop: '1px solid var(--border)' }}>
        <Lbl>Fiscal do pedido</Lbl>
        <Grid cols={4} gap={12}>
          <div><Select label="Natureza da operação" options={['Venda de mercadoria', 'Venda de serviço', 'Remessa para conserto', 'Bonificação']} /></div>
          <div><Select label="Esquema de numeração" options={['VD-2026 (padrão)', 'Manual']} /></div>
          <div><Input label="Nº da fatura" placeholder="automático" /></div>
          <div><Select label="Imposto do pedido" options={['ICMS 18% — exclusivo', 'ICMS 12% — exclusivo', 'Isento']} /></div>
        </Grid>
        <div style={{ marginTop: 12 }}><Textarea label="Informações complementares da NF-e" rows={2} placeholder="Pedido de compra 4471. Entregar em horário comercial." /></div>
      </div>

      <Modal open={consulta} onClose={() => setConsulta(false)} width={880} title="Consulta de transportadoras"
        footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
          <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{TRANSPORTADORAS.length} cadastros ativos</span>
          <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
            <Button onClick={() => setConsulta(false)}>Fechar</Button>
            <Button variant="primary" onClick={() => setConsulta(false)}>Novo cadastro</Button>
          </span>
        </div>}>
        <div style={{ marginBottom: 12 }}>
          <Input value={buscaT} onChange={(e) => setBuscaT(e.target.value)} placeholder="Buscar por razão social, CNPJ, cidade ou código…" />
        </div>
        <div className="oi-scroll" style={{ overflowX: 'auto' }}>
        <DataTable
          columns={[{ key: 'c', label: 'Código', mono: true }, { key: 'n', label: 'Razão social' }, { key: 'd', label: 'CNPJ', mono: true }, { key: 'l', label: 'Cidade / UF' }, { key: 'm', label: 'Modalidade' }]}
          rows={TRANSPORTADORAS.filter((t) => (t.cod + t.nome + t.doc + t.cidade).toLowerCase().includes(buscaT.toLowerCase())).map((t) => ({
            id: t.cod, state: transp && transp.cod === t.cod ? 'selected' : undefined,
            cells: { c: t.cod, n: { primary: t.nome, sub: 'placa ' + t.placa }, d: t.doc, l: t.cidade + '/' + t.uf, m: <Pill>{t.modal}</Pill> },
          }))}
          onRowClick={(r) => { trazer(TRANSPORTADORAS.find((t) => t.cod === r.id)); setConsulta(false); setBuscaT(''); }} />
        </div>
        <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Clique na linha para trazer a transportadora — placa, ANTT, UF e modalidade vêm preenchidas do cadastro e podem ser ajustadas nesta venda.</p>
      </Modal>

      <Meta>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <Lbl>Estado do frete no código</Lbl>
            {[['hoje', 'Hoje (free-text · parcial)'], ['depois', 'Depois de O6-4 (estruturado)']].map(([k, l]) => (
              <button key={k} type="button" onClick={() => setFreteModo(k)} style={{ height: 24, padding: '0 12px', borderRadius: 6, cursor: 'pointer', border: '1px solid ' + (freteModo === k ? 'transparent' : 'var(--border)'), background: freteModo === k ? 'var(--accent)' : 'var(--surface)', color: freteModo === k ? 'var(--accent-fg)' : 'var(--text-dim)', font: '600 11.5px/1 var(--font-sans)' }}>{l}</button>
            ))}
          </div>
          <Alert tone="warn" title="CU-SELL-11 é parcial — e o PR anterior foi revertido">
            Em produção hoje só existe <b>um campo de texto</b> de entrega e um valor de frete. Todo este grupo (frete por conta, transportadora, volumes, peso, endereço alternativo) é o <b>PR2 #2104</b>, revertido no incidente <b>2026-06-02</b>: religar exige <b>smoke biz=4</b> antes (item O6-4 · dívida D-6). Peso somado dos itens depende do cadastro de peso — item sem peso entra como zero.
          </Alert>
        </div>
      </Meta>
    </div>
  );
}

Object.assign(window, { EntregaFiscal, FRETE_CONTA });
