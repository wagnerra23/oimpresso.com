/* Shell: sidebar do DS + header + navegação do domínio Sells.
   Nível de navegação = CONSULTA (Vendas · Cotações · Rascunhos · Assinaturas · Caixa).
   Ficha e Edição são de UM registro: só se chega nelas a partir de uma linha da consulta.
   Nova venda é AÇÃO (botão primário), não aba.
   A tela é só a tela — o roteiro e os CU vivem nos .md do módulo, fora da tela do operador. */
const LS_TELA = 'oimpresso.vendas.sdd.guia.tela';
const LS_TEMA = 'oimpresso.vendas.sdd.guia.tema';

const CONSULTAS = [['index', 'Vendas'], ['quotations', 'Cotações'], ['drafts', 'Rascunhos'], ['subs', 'Assinaturas'], ['caixa', 'Caixa']];
const TELA_COMP = { create: 'Create', index: 'Index', edit: 'Create', show: 'Show', caixa: 'Caixa', subs: 'Subscriptions', quotations: 'Quotations', drafts: 'Drafts' };
const TELA_LABEL = { create: 'Nova venda', index: 'Vendas', edit: 'Editar', show: 'Ficha', caixa: 'Caixa', subs: 'Assinaturas', quotations: 'Cotações', drafts: 'Rascunhos' };
const TELA_TITULO = { create: 'Nova venda', index: 'Vendas', edit: 'Editar venda', show: 'Ficha da venda', caixa: 'Caixa do dia', subs: 'Assinaturas', quotations: 'Cotações', drafts: 'Rascunhos' };
const TELA_SUB = { index: 'Todos os estágios — orçamento, produção, faturada, entregue', create: 'Cliente, itens, pagamento. O resto tem valor padrão.', edit: 'Venda emitida — alterar refaz totais e estoque.', show: null, caixa: 'Movimento de hoje', subs: 'Cobranças em ciclo', quotations: 'Propostas em aberto — viram venda ao aprovar', drafts: 'Vendas em digitação' };
const REGISTRO = { show: 1, edit: 1, create: 1 }; /* telas de um registro/ação: não são aba */

function App() {
  const [tela, setTela] = React.useState(() => localStorage.getItem(LS_TELA) || 'index');
  const [registro, setRegistro] = React.useState(null);
  const [voltarPara, setVoltarPara] = React.useState('index');
  const [cu, setCu] = React.useState(null);
  const [tema, setTema] = React.useState(() => localStorage.getItem(LS_TEMA) || 'claro');
  React.useEffect(() => { try { localStorage.setItem(LS_TEMA, tema); } catch (e) {} }, [tema]);
  React.useEffect(() => { try { localStorage.setItem(LS_TELA, tela); } catch (e) {} }, [tela]);

  const go = (destino, reg) => { if (REGISTRO[tela] !== 1) setVoltarPara(tela); setRegistro(reg || null); setTela(destino); };
  const voltar = () => { setRegistro(null); setTela(voltarPara); };
  const consulta = REGISTRO[tela] !== 1;
  const Screen = tela === 'edit' ? (p) => <Create {...p} modo="edit" /> : window[TELA_COMP[tela]];
  const trilha = [{ label: 'Comercial', href: '#' }, { label: 'Vendas', href: '#' }];
  if (!consulta && TELA_LABEL[voltarPara] !== 'Vendas') trilha.push({ label: TELA_LABEL[voltarPara], href: '#' });
  trilha.push({ label: registro ? registro.inv : TELA_LABEL[tela] });

  return (
    <MetaCtx.Provider value={false}>
    <div className="cockpit" data-theme={tema === 'escuro' ? 'dark' : undefined} style={{ display: 'flex', height: '100vh', overflow: 'hidden', background: 'var(--atmo), var(--bg)', color: 'var(--text)', fontFamily: 'var(--font-sans)', fontSize: 13.5, lineHeight: 1.45, WebkitFontSmoothing: 'antialiased' }}>
      <AppSidebar active="Vendas" />
      <main style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
        <header style={{ display: 'flex', alignItems: 'center', gap: 12, height: 46, padding: '0 18px', borderBottom: '1px solid var(--border)', background: 'var(--surface)', flex: 'none' }}>
          <Trilho items={trilha} />
          <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 12 }}>
            <button type="button" title={tema === 'escuro' ? 'Usar tema claro' : 'Usar tema escuro'} aria-label={tema === 'escuro' ? 'Usar tema claro' : 'Usar tema escuro'}
              onClick={() => setTema(tema === 'escuro' ? 'claro' : 'escuro')}
              style={{ width: 30, height: 30, borderRadius: 8, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}>
              <Icon name={tema === 'escuro' ? 'Sun' : 'Moon'} size={15} />
            </button>
          </span>
        </header>
        <div className="oi-scroll" style={{ flex: 1, overflow: 'auto', minHeight: 0 }}>
          <div style={{ padding: '0 18px' }}>
            <PageHeader title={registro ? TELA_TITULO[tela] + ' · ' + registro.inv : TELA_TITULO[tela]} subtitle={TELA_SUB[tela]}
              actions={<div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                {!consulta && <Button size="sm" onClick={voltar}>Voltar para {TELA_LABEL[voltarPara]}</Button>}
                {consulta && tela !== 'create' && <Button size="sm" variant="primary" onClick={() => go('create')}>Nova venda</Button>}
              </div>} />
          </div>
          {consulta && <div style={{ padding: '12px 18px 0' }}>
            <TabBar active={tela} onChange={(k) => { setRegistro(null); setTela(k); }} tabs={CONSULTAS.map(([k, l]) => ({ key: k, label: l }))} />
          </div>}
          <div style={{ padding: '16px 18px 40px' }}><Screen onOpen={setCu} go={go} registro={registro} /></div>
        </div>
      </main>
      <CuDrawer id={cu} onClose={() => setCu(null)} onOpen={setCu} />
    </div>
    </MetaCtx.Provider>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
