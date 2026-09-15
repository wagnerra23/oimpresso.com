/* Detalhe do Produto/Serviço na venda — equivalente ao "Detalhes do Produto / Serviço" do legado.
   Abas: Geral · Produção · Fluxo · Tributação (sub-abas por imposto) · Formação do preço · Anexos · Observação.
   Abre pela lupa da linha de item; edita uma CÓPIA e só grava no Confirmar. */

/* mesmas listas do lançamento (sells-lancamento.jsx), com a opção vazia do legado */
const LOCAIS_DET = ['', 'Fachada', 'Interno', 'Veículo', 'Painel', 'Vitrine', 'Totem', 'Obra'];
const IMPRESSOES_DET = ['', 'Digital — látex', 'Digital — UV', 'Offset', 'Recorte eletrônico', 'Sublimação', 'Sem impressão'];

const IMPOSTOS = [
  { k: 'icms', l: 'ICMS', aliq: 18 }, { k: 'ipi', l: 'IPI', aliq: 0 }, { k: 'pis', l: 'PIS', aliq: 1.65 },
  { k: 'cofins', l: 'COFINS', aliq: 7.6 }, { k: 'issqn', l: 'ISSQN', aliq: 0 }, { k: 'ii', l: 'II', aliq: 0 },
  { k: 'is', l: 'IS', aliq: 0 }, { k: 'ibs', l: 'IBS', aliq: 0.1 }, { k: 'cbs', l: 'CBS', aliq: 0.9 },
];
const CST_ICMS = ['00 — Tributada integralmente', '20 — Com redução de base', '40 — Isenta', '41 — Não tributada', '60 — ST cobrado anteriormente', '102 — Simples sem crédito'];
/* etapa: responsável é PESSOA, setor é ONDE — misturar os dois numa coluna foi o defeito apontado */
const FLUXO_PADRAO = [
  { e: 'Arte / pré-impressão', resp: 'Kamila Reis', setor: 'Criação', st: 'concluída', prev: '28/07' },
  { e: 'Impressão digital', resp: 'Guilherme Sato', setor: 'Impressão', st: 'em execução', prev: '29/07' },
  { e: 'Acabamento — ilhós', resp: 'Equipe interna — box 2', setor: 'Acabamento', st: 'pendente', prev: '30/07' },
  { e: 'Expedição', resp: 'Larissa Prado', setor: 'Balcão', st: 'pendente', prev: '31/07' },
];
const FLUXO_ST = { 'concluída': 'var(--pos)', 'em execução': 'var(--warn)', pendente: 'var(--text-mute)' };

/* Validação fiscal — formato legal + coerência CST × alíquota.
   Erro fiscal sai daqui direto pra NF-e; rejeição da SEFAZ é retrabalho da Kamila. */
const soDig = (v) => String(v == null ? '' : v).replace(/\D/g, '');
const VALIDA = {
  ncm: (v) => { const n = soDig(v); if (!n) return 'NCM é obrigatório na NF-e'; if (n.length !== 8) return 'NCM tem 8 dígitos — faltam ' + Math.abs(8 - n.length); return null; },
  cfop: (v) => { const n = soDig(v); if (!n) return 'CFOP é obrigatório'; if (n.length !== 4) return 'CFOP tem 4 dígitos'; if (!'123567'.includes(n[0])) return 'CFOP começa em 1,2,3,5,6 ou 7'; return null; },
  cest: (v) => { const n = soDig(v); if (!n) return null; if (n.length !== 7) return 'CEST tem 7 dígitos'; return null; },
  gtin: (v) => { const n = soDig(v); if (!n) return null; if (![8, 12, 13, 14].includes(n.length)) return 'GTIN tem 8, 12, 13 ou 14 dígitos'; return null; },
  cbenef: (v) => { const t = String(v || '').trim(); if (!t) return null; if (!/^[A-Za-z]{2}\d{6}$/.test(t)) return 'cBenef: 2 letras da UF + 6 dígitos'; return null; },
  aliq: (v) => { const n = parseBR(v); if (n < 0) return 'Alíquota não pode ser negativa'; if (n > 100) return 'Alíquota acima de 100%'; return null; },
  red: (v) => { const n = parseBR(v); if (n < 0 || n > 100) return 'Redução vai de 0 a 100%'; return null; },
};
/* CST 40/41/60 (isento, não tributado, ST anterior) com alíquota ≠ 0 é rejeição certa */
const CST_SEM_ALIQ = ['40', '41', '60', '04'];
function erroCoerencia(cst, aliq) {
  const cod = String(cst || '').trim().slice(0, 3).replace(/\D/g, '');
  const a = parseBR(aliq);
  if (CST_SEM_ALIQ.includes(cod.slice(0, 2)) && a > 0) return 'CST ' + cod.slice(0, 2) + ' não admite alíquota — zere ou troque o CST';
  if (cod === '00' && a <= 0) return 'CST 00 é tributado integralmente — alíquota não pode ser zero';
  return null;
}

const dv = (l, campo, padrao) => (l && l[campo] !== undefined && l[campo] !== null) ? l[campo] : padrao;

function ItemDetail({ linha, index, total, onClose, onSave, onNav, abaInicial = 'geral' }) {
  const [aba, setAba] = React.useState(abaInicial);
  const [trib, setTrib] = React.useState('resumo');
  const [etapas, setEtapas] = React.useState(FLUXO_PADRAO);
  const [novaEtapa, setNovaEtapa] = React.useState(null);
  const inicial = (l) => ({ ncm: '39199090', cfop: '5102', ...l });
  const [d, setD] = React.useState(() => inicial(linha));
  const [base, setBase] = React.useState(() => inicial(linha));
  const [tocado, setTocado] = React.useState({});
  const [carregando, setCarregando] = React.useState(false);
  const [confirmando, setConfirmando] = React.useState(false);
  const [pendente, setPendente] = React.useState(null); /* navegação com edição não confirmada */

  /* carrega o item: skeleton curto (estado 3) — o dado fiscal vem do cadastro do produto */
  React.useEffect(() => {
    if (!linha) return;
    setCarregando(true);
    const copia = inicial(linha);
    setD(copia); setBase(copia);
    setTocado({}); setAba(abaInicial); setTrib('resumo');
    const t = setTimeout(() => setCarregando(false), 380);
    return () => clearTimeout(t);
  }, [linha, abaInicial]);
  if (!linha) return null;

  /* alteracao pendente: compara com o snapshot de abertura — a linha da venda nao tem campo fiscal */
  const sujo = JSON.stringify(d) !== JSON.stringify(base);
  const marcar = (campo) => () => setTocado((s) => ({ ...s, [campo]: true }));
  /* set + marca: digitar já marca o campo, então o erro aparece sem depender de onBlur
     (o Input/Select do DS não repassa onBlur — foi o que escondeu a validação) */
  const setV = (campo) => (v) => { setD((st) => ({ ...st, [campo]: (v && v.target) ? v.target.value : v })); setTocado((st) => st[campo] ? st : { ...st, [campo]: true }); };
  const erroDe = (campo, fn, valor) => (tocado[campo] ? fn(valor) : null);

  const set = (campo) => (e) => setD((s) => ({ ...s, [campo]: (e && e.target) ? e.target.value : e }));
  /* o tipo de comissionado segue o cadastro de quem está no item — não um default fixo,
     porque comTipo é o que decide folha de pagamento vs título a pagar */
  const comTipoItem = dv(d, 'comTipo', ((window.SD.pessoas.find((p) => p.nome === d.func) || {}).tipo) || 'funcionario');
  const valorLinha = submitSafe(parseBR(d.qtd) * parseBR(d.preco) * (1 - parseBR(d.desc) / 100) * (1 + parseBR(dv(d, 'acr', '0')) / 100));
  const m2 = submitSafe(parseBR(dv(d, 'altura', '1,00')) * parseBR(dv(d, 'largura', '1,00')) * parseBR(dv(d, 'pecas', '1')));
  const impostoDe = (i) => submitSafe(valorLinha * (parseBR(dv(d, 'aliq_' + i.k, fmtBR(i.aliq))) / 100));
  const somaImpostos = submitSafe(IMPOSTOS.reduce((s, i) => s + impostoDe(i), 0));

  const ABAS = [
    { key: 'geral', label: 'Geral' }, { key: 'producao', label: 'Produção' }, { key: 'fluxo', label: 'Fluxo de produção', count: etapas.length },
    { key: 'trib', label: 'Tributação' }, { key: 'preco', label: 'Preço' },
    { key: 'anexos', label: 'Anexos', count: 2 }, { key: 'obs', label: 'Observação' },
  ];

  const campoTrib = (i) => {
    const opcoes = i.k === 'icms' ? CST_ICMS : ['01 — Tributado', '04 — Isento', '49 — Outros', '99 — Outras saídas'];
    const cst = dv(d, 'cst_' + i.k, opcoes[0]);
    const aliq = dv(d, 'aliq_' + i.k, fmtBR(i.aliq));
    const eFaixa = erroDe('aliq_' + i.k, VALIDA.aliq, aliq);
    const eCoer = tocado['aliq_' + i.k] || tocado['cst_' + i.k] ? erroCoerencia(cst, aliq) : null;
    return (
    <>
    <Grid cols={4} gap={10}>
      <div><Select label={'CST / situação — ' + i.l} value={cst} onChange={(e) => { set('cst_' + i.k)(e); setTocado((s) => ({ ...s, ['cst_' + i.k]: true })); }} options={opcoes} error={eCoer} /></div>
      <Money label="Base de cálculo" value={fmtBR(valorLinha)} onChange={() => {}} />
      <Money label="Alíquota" prefix="%" value={aliq} onChange={setV('aliq_' + i.k)} onBlur={marcar('aliq_' + i.k)} error={eFaixa || eCoer} />
      <Money label="Valor do imposto" value={fmtBR(impostoDe(i))} onChange={() => {}} readOnly />
      {i.k === 'icms' && <>
        <Money label="Redução de base" prefix="%" value={dv(d, 'red_icms', '0,00')} onChange={setV('red_icms')} onBlur={marcar('red_icms')} error={erroDe('red_icms', VALIDA.red, dv(d, 'red_icms', '0,00'))} />
        <Money label="MVA / margem ST" prefix="%" value={dv(d, 'mva', '0,00')} onChange={setV('mva')} onBlur={marcar('mva')} error={erroDe('mva', VALIDA.red, dv(d, 'mva', '0,00'))} />
        <Money label="Base ST" value={fmtBR(0)} onChange={() => {}} readOnly />
        <Money label="ICMS ST" value={fmtBR(0)} onChange={() => {}} readOnly />
      </>}
      {(i.k === 'ibs' || i.k === 'cbs') && <>
        <div><Select label="Classificação tributária (cClassTrib)" options={['000001 — Regra geral', '200001 — Alíquota reduzida', '400001 — Isenção']} help="código de 6 dígitos da reforma tributária" /></div>
        <Money label="Alíquota efetiva" prefix="%" value={dv(d, 'aliq_ef_' + i.k, fmtBR(i.aliq))} onChange={set('aliq_ef_' + i.k)} />
        <Money label="Crédito presumido" value={fmtBR(0)} onChange={() => {}} readOnly help="calculado pela classificação" />
        <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="Monofasia" sublabel="Reforma tributária — transição 2026" checked={false} onChange={() => {}} /></div>
      </>}
      {i.k === 'issqn' && <>
        <div><Input label="Código do serviço (LC 116)" placeholder="17.06" /></div>
        <div><Select label="Município de incidência" options={['Joinville/SC — 4209102', 'Outro município']} /></div>
        <div style={{ display: 'flex', alignItems: 'flex-end' }}><Switch label="ISS retido na fonte" checked={false} onChange={() => {}} /></div>
        <Money label="Base reduzida" value={fmtBR(valorLinha)} onChange={() => {}} readOnly />
      </>}
    </Grid>
    {i.k === 'icms' && (() => {
      /* DIFAL — EC 87/2015: venda interestadual para NÃO contribuinte recolhe a diferença
         de alíquota para a UF de destino (partilha 100% destino desde 2019). */
      const difal = dv(d, 'difal', false);
      const bc = valorLinha;
      const aInter = parseBR(dv(d, 'difal_inter', '12,00'));
      const aDest = parseBR(dv(d, 'difal_dest', '18,00'));
      const pFcp = parseBR(dv(d, 'difal_fcp', '2,00'));
      const vRemet = submitSafe(bc * aInter / 100);
      const vDest = submitSafe(bc * aDest / 100 - vRemet);
      const vFcp = submitSafe(bc * pFcp / 100);
      const invertido = aDest < aInter;
      return (
        <div style={{ marginTop: 12, padding: 12, borderRadius: 12, background: difal ? 'color-mix(in oklch, var(--warn) 7%, var(--surface))' : 'var(--bg-2)', border: '1px solid ' + (difal ? 'color-mix(in oklch, var(--warn) 26%, var(--border))' : 'var(--border)') }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 10, marginBottom: difal ? 12 : 0 }}>
            <Switch label="DIFAL — diferencial de alíquota" sublabel="venda interestadual para não contribuinte (EC 87/2015)" checked={difal} onChange={(v) => setD((s) => ({ ...s, difal: v }))} />
            {difal && <span style={{ marginLeft: 'auto', display: 'flex', gap: 10, alignItems: 'baseline' }}>
              <span><Lbl>Total DIFAL + FCP</Lbl><b style={{ font: '600 14px/1 var(--font-mono)' }}>{brl(submitSafe(Math.max(0, vDest) + vFcp))}</b></span>
            </span>}
          </div>
          {difal && <>
            <Grid cols={4} gap={10}>
              <div><Select label="UF de destino" value={dv(d, 'difal_uf', 'PR')} onChange={set('difal_uf')} options={['PR', 'SP', 'RJ', 'MG', 'RS', 'BA', 'PE', 'GO', 'DF']} /></div>
              <Money label="Alíquota interestadual" prefix="%" value={dv(d, 'difal_inter', '12,00')} onChange={setV('difal_inter')} help="7% ou 12% conforme a origem" />
              <Money label="Alíquota interna do destino" prefix="%" value={dv(d, 'difal_dest', '18,00')} onChange={setV('difal_dest')} error={invertido ? 'menor que a interestadual — não há DIFAL a recolher' : null} />
              <Money label="FCP do destino" prefix="%" value={dv(d, 'difal_fcp', '2,00')} onChange={setV('difal_fcp')} help="fundo de combate à pobreza" />
            </Grid>
            <div style={{ marginTop: 10, display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 10, background: 'var(--surface)', border: '1px solid var(--border)' }}>
              {[['Base do DIFAL', bc], ['ICMS UF remetente', vRemet], ['ICMS UF destino', Math.max(0, vDest)], ['FCP UF destino', vFcp]].map(([l, v]) => (
                <div key={l}><Lbl>{l}</Lbl><b style={{ font: '600 13px/1 var(--font-mono)' }}>{fmtBR(v)}</b></div>
              ))}
              <span style={{ marginLeft: 'auto', font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)', maxWidth: 300 }}>
                Partilha <b>100% para o destino</b> desde 2019. Sai na NF-e como <code>vICMSUFDest</code>, <code>vICMSUFRemet</code> e <code>vFCPUFDest</code>.
              </span>
            </div>
            <Meta><p style={{ margin: '8px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Quem liga o DIFAL não deveria ser o operador: o gatilho é <b>UF do destinatário ≠ UF da empresa</b> + destinatário <b>não contribuinte</b> — os dois dados já estão no cadastro do cliente. Aqui está manual porque essa derivação automática <b>não tem CU</b> nos 33 do SDD v1.0.</p></Meta>
          </>}
        </div>
      );
    })()}
    </>
  ); };

  /* pendências fiscais do item — o que barra o Confirmar */
  const pendencias = [
    ['NCM', VALIDA.ncm(dv(d, 'ncm', ''))], ['CFOP', VALIDA.cfop(dv(d, 'cfop', ''))],
    ['CEST', VALIDA.cest(dv(d, 'cest', ''))], ['GTIN', VALIDA.gtin(dv(d, 'gtin', ''))],
    ['cBenef', VALIDA.cbenef(dv(d, 'cbenef', ''))],
    ...IMPOSTOS.map((i) => {
      const cst = dv(d, 'cst_' + i.k, (i.k === 'icms' ? CST_ICMS : ['01 — Tributado'])[0]);
      const aliq = dv(d, 'aliq_' + i.k, fmtBR(i.aliq));
      return [i.l, VALIDA.aliq(aliq) || erroCoerencia(cst, aliq)];
    }),
  ].filter(([, e]) => e);

  const confirmar = () => {
    if (pendencias.length) { setTocado(Object.fromEntries([...Object.keys(d), ...IMPOSTOS.map((i) => 'aliq_' + i.k), 'ncm', 'cfop', 'cest', 'gtin', 'cbenef'].map((k) => [k, true]))); setAba('trib'); return; }
    setConfirmando(true);
    setTimeout(() => { setConfirmando(false); onSave(d); }, 420);
  };
  /* navegar com edição pendente pede confirmação (achado ALTA nº2) */
  const navegar = (i) => { if (sujo) { setPendente(i); return; } onNav(i); };

  return (
    <Drawer open={!!linha} onClose={onClose} width={880}
      title={'Item ' + (index + 1) + ' · ' + (linha.nome.length > 48 ? linha.nome.slice(0, 47) + '…' : linha.nome)}
      subtitle={linha.sku + ' · ' + linha.un + ' · ' + brl(valorLinha)}
      badge={<Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))" mono>{(index + 1) + '/' + total}</Pill>}
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <Button size="sm" disabled={index === 0} onClick={() => navegar(index - 1)}>‹ Anterior</Button>
        <Button size="sm" disabled={index === total - 1} onClick={() => navegar(index + 1)}>Próximo ›</Button>
        {sujo && <Pill mono c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">alteração não confirmada</Pill>}
        {pendencias.length > 0 && <Pill mono c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">{pendencias.length === 1 ? '1 pendência fiscal' : pendencias.length + ' pendências fiscais'}</Pill>}
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={onClose}>Cancelar</Button>
          <Button variant="primary" disabled={confirmando} onClick={confirmar}>{confirmando ? 'Confirmando…' : 'Confirmar item'}</Button>
        </span>
      </div>}>
      <div style={{ padding: '0 0 4px' }}><TabBar active={aba} onChange={setAba} tabs={ABAS} /></div>

      {pendencias.length > 0 && Object.keys(tocado).length > 0 && <div style={{ padding: '12px 0 0' }}>
        <Alert tone="danger" title={pendencias.length === 1 ? 'Uma pendência fiscal impede confirmar' : pendencias.length + ' pendências fiscais impedem confirmar'}
          action={aba !== 'trib' ? <Button size="sm" onClick={() => setAba('trib')}>Ir para Tributação</Button> : null}>
          {pendencias.slice(0, 3).map(([campo, erro]) => <span key={campo} style={{ display: 'block' }}><b>{campo}:</b> {erro}</span>)}
          {pendencias.length > 3 && <span style={{ display: 'block', color: 'var(--text-dim)' }}>e mais {pendencias.length - 3}…</span>}
        </Alert>
      </div>}

      {carregando && <DrawerSection title="Carregando dados fiscais do produto">
        <Grid cols={4} gap={10}>{Array.from({ length: 8 }).map((_, i) => <div key={i}><Skeleton variant="caption" width="60%" /><Skeleton variant="row" /></div>)}</Grid>
      </DrawerSection>}

      <Modal open={pendente !== null} onClose={() => setPendente(null)} width={480} title="Há alteração não confirmada neste item"
        footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
          <Button onClick={() => { const i = pendente; setPendente(null); onNav(i); }}>Descartar e continuar</Button>
          <span style={{ marginLeft: 'auto' }}><Button variant="primary" onClick={() => { const i = pendente; setPendente(null); if (!pendencias.length) { onSave(d); onNav(i); } else { setAba('trib'); } }}>Confirmar e continuar</Button></span>
        </div>}>
        <p style={{ margin: 0, font: '13.5px/1.5 var(--font-sans)' }}>Você alterou este item e ainda não confirmou. Ir para outro item agora <b>descarta a alteração</b>.</p>
      </Modal>

      {!carregando && aba === 'geral' && <>
        <DrawerSection title="Identificação e medidas">
          <Grid cols={4} gap={10}>
            <div><Input label="Código do produto" defaultValue={linha.sku} readOnly /></div>
            <div><Input label="Descrição na venda" value={d.nome} onChange={set('nome')} /></div>
            <div><Select label="Unidade" defaultValue={linha.un} options={['m²', 'un', 'h', 'm', 'kg']} /></div>
            <Money label="Peças" prefix="qt" value={dv(d, 'pecas', '1')} onChange={set('pecas')} />
            <Money label="Altura" prefix="m" value={dv(d, 'altura', '1,00')} onChange={set('altura')} />
            <Money label="Largura" prefix="m" value={dv(d, 'largura', '1,00')} onChange={set('largura')} />
            <Money label="Espessura" prefix="mm" value={dv(d, 'esp', '0,00')} onChange={set('esp')} />
            <Money label="Área calculada" prefix="m²" value={fmtBR(m2)} onChange={() => {}} readOnly help="peças × altura × largura" />
          </Grid>
        </DrawerSection>
        <DrawerSection title="Valores da linha">
          <Grid cols={4} gap={10}>
            <Money label="Quantidade" prefix="qt" value={d.qtd} onChange={set('qtd')} />
            <Money label="Valor unitário" value={d.preco} onChange={set('preco')} />
            <div><Select label="Tipo de preço" options={['Tabela do grupo', 'Manual', 'Por m²', 'Por milheiro']} /></div>
            <Money label="% desconto" prefix="%" value={d.desc} onChange={set('desc')} />
            <Money label="Desconto R$" value={fmtBR(submitSafe(parseBR(d.qtd) * parseBR(d.preco) * parseBR(d.desc) / 100))} onChange={() => {}} readOnly />
            <Money label="% acréscimo" prefix="%" value={dv(d, 'acr', '0')} onChange={set('acr')} />
            <Money label="Total deste item" value={fmtBR(valorLinha)} onChange={() => {}} readOnly help="quantidade × valor unitário, com desconto e acréscimo" />
          </Grid>
        </DrawerSection>
        <DrawerSection title="Comissão deste item">
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: 10, marginBottom: dv(d, 'comiss', true) ? 10 : 0 }}>
            <Switch label="Comissiona este item" sublabel="desligado, o item sai da base de comissão da venda" checked={dv(d, 'comiss', true)} onChange={(v) => setD((s) => ({ ...s, comiss: v }))} />
          </div>
          {dv(d, 'comiss', true) && <>
            <Grid cols={4} gap={10}>
              <div><Select label="Tipo de comissionado" value={comTipoItem} onChange={set('comTipo')} options={COM_TIPOS.map((t) => ({ value: t.k, label: t.l }))} /></div>
              <div><Select label="Quem executa / vende" value={dv(d, 'func', '')} onChange={set('func')}
                options={(() => {
                  const base = comOpcoes(comTipoItem);
                  const atual = dv(d, 'func', '');
                  /* valor gravado que não pertence ao tipo escolhido não pode virar a opção 0 em silêncio */
                  return (!atual || base.some((o) => o.value === atual)) ? [{ value: '', label: '—' }, ...base] : [{ value: atual, label: atual + ' · fora deste tipo' }, ...base];
                })()} /></div>
              <div><Select label="Base de cálculo" value={dv(d, 'comBase', 'liquido')} onChange={set('comBase')} options={COM_BASES} /></div>
              <Money label="Percentual" prefix="%" value={dv(d, 'comPct', '3,00')} onChange={set('comPct')} />
            </Grid>
            <div style={{ marginTop: 10, display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
              <div><Lbl>Base do item</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(valorLinha)}</b></div>
              <div><Lbl>Comissão do item</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(submitSafe(valorLinha * parseBR(dv(d, 'comPct', '3,00')) / 100))}</b></div>
              <span style={{ marginLeft: 'auto', font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)', maxWidth: 360 }}>
                Somada à comissão da venda e apurada para <b>quem executou</b> — não para quem digitou. É por aqui que serviço com técnico próprio recebe percentual diferente do produto.
              </span>
            </div>
          </>}
        </DrawerSection>
      </>}

      {!carregando && aba === 'producao' && <>
        <DrawerSection title="Instruções de produção">
          <Grid cols={3} gap={10}>
            <div><Select label="Em produção" defaultValue="Não" options={['Não', 'Sim']} /></div>
            <div><Select label="Tipo de impressão" value={dv(d, 'impressao', IMPRESSOES_DET[0])} onChange={set('impressao')} options={IMPRESSOES_DET} /></div>
            <div><Select label="Acabamento" options={['Sem acabamento', 'Ilhós a cada 50cm', 'Bastão + corda', 'Solda perimetral', 'Laminação']} /></div>
            <div><Select label="Local de aplicação" value={dv(d, 'local', LOCAIS_DET[0])} onChange={set('local')} options={LOCAIS_DET} /></div>
            <div><Select label="Equipamento / setor" value={dv(d, 'equip', '')} onChange={set('equip')}
              options={[{ value: '', label: '—' }, ...window.SD.equipamentos.map((e) => ({ value: e.nome, label: e.nome + ' · ' + e.setor }))]} /></div>
            <div><Select label="Prioridade" options={['Normal', 'Urgente', 'Programada']} /></div>
            <div><Select label="Requisitar do estoque" value={dv(d, 'localEstoque', (window.SD.catalogo.find((p) => p.sku === linha.sku) || {}).localEstoque || '')} onChange={set('localEstoque')}
              options={[{ value: '', label: 'Não requisita (serviço)' }, ...window.SD.locaisEstoque.map((l) => ({ value: l, label: l }))]} help="de onde a produção retira o material" /></div>
            <div><DataCampo label="Prazo da equipe (produção)" value={dv(d, 'prazoEquipe', null)} onChange={set('prazoEquipe')} /></div>
            <div><DataCampo label="Prazo da etapa" value={dv(d, 'prazoEtapa', null)} onChange={set('prazoEtapa')} /></div>
            <div />
          </Grid>
          <div style={{ marginTop: 12 }}><Textarea label="Observação de produção (vai na OP, não sai no documento do cliente)" rows={3} value={dv(d, 'obsProd', '')} onChange={set('obsProd')} placeholder="Sangria de 5cm. Cliente aprovou arte por e-mail em 27/07." /></div>
        </DrawerSection>
        <DrawerSection title="Arquivo de arte">
          <div style={{ display: 'flex', gap: 12, alignItems: 'center', padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px dashed var(--border)' }}>
            <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Caminho do arquivo na rede</span>
            <div style={{ flex: 1, minWidth: 0 }}><Input placeholder="\\\\servidor\\arte\\2026\\07\\lona-prefeitura-v3.pdf" /></div>
            <Button size="sm">Anexar arquivo</Button>
          </div>
        </DrawerSection>
      </>}

      {!carregando && aba === 'fluxo' && <DrawerSection title="Fluxo de produção deste item">
        {etapas.length === 0 && <EmptyState variant="first" title="Nenhuma etapa neste item" description="Este produto não tem fluxo de produção configurado. Aplique o fluxo padrão do cadastro ou monte as etapas à mão." action={<Button size="sm" variant="primary">Aplicar fluxo padrão</Button>} />}
        {etapas.length > 0 && <DataTable
          columns={[{ key: 'e', label: 'Etapa' }, { key: 'r', label: 'Responsável' }, { key: 'se', label: 'Setor' }, { key: 'st', label: 'Situação' }, { key: 'p', label: 'Previsão', mono: true }, { key: 'x', label: '', align: 'center' }]}
          rows={etapas.map((et, i) => ({ id: i, cells: {
            e: et.e, r: et.resp, se: <Pill>{et.setor}</Pill>,
            st: <Pill c={FLUXO_ST[et.st]} s={et.st === 'pendente' ? 'var(--bg-2)' : 'color-mix(in oklch, ' + FLUXO_ST[et.st] + ' 12%, var(--surface))'}>{et.st}</Pill>,
            p: et.prev,
            x: <button type="button" aria-label={'Remover etapa ' + et.e} onClick={() => setEtapas(etapas.filter((_, j) => j !== i))}
              style={{ width: 26, height: 26, borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="X" size={13} /></button>,
          } }))} />}
        <div style={{ marginTop: 12, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <Button size="sm" onClick={() => setNovaEtapa({ e: '', resp: (window.SD.pessoas[0] || {}).nome, setor: window.SD.setores[0], st: 'pendente', prev: '' })}>Adicionar etapa</Button>
          <Button size="sm" onClick={() => setEtapas(FLUXO_PADRAO)}>Aplicar fluxo padrão do produto</Button>
        </div>
        <Modal open={!!novaEtapa} onClose={() => setNovaEtapa(null)} width={560} title="Nova etapa do fluxo"
          footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
            <Button onClick={() => setNovaEtapa(null)}>Cancelar</Button>
            <span style={{ marginLeft: 'auto' }}><Button variant="primary" disabled={!novaEtapa || !novaEtapa.e.trim()}
              onClick={() => { setEtapas([...etapas, novaEtapa]); setNovaEtapa(null); }}>Adicionar</Button></span>
          </div>}>
          {novaEtapa && <Grid cols={2} gap={10}>
            <div><Input label="Etapa" value={novaEtapa.e} onChange={(e) => setNovaEtapa({ ...novaEtapa, e: e.target.value })} placeholder="Ex: aplicação no local" /></div>
            <div><Select label="Setor" value={novaEtapa.setor} onChange={(e) => setNovaEtapa({ ...novaEtapa, setor: e.target.value })} options={window.SD.setores} /></div>
            <div><Select label="Responsável" value={novaEtapa.resp} onChange={(e) => setNovaEtapa({ ...novaEtapa, resp: e.target.value })} options={window.SD.pessoas.map((p) => p.nome)} /></div>
            <div><Input label="Previsão" value={novaEtapa.prev} onChange={(e) => setNovaEtapa({ ...novaEtapa, prev: e.target.value })} placeholder="dd/mm" /></div>
          </Grid>}
        </Modal>
      </DrawerSection>}

      {!carregando && aba === 'trib' && <>
        <DrawerSection title="Classificação fiscal">
          <Grid cols={4} gap={10}>
            <div><Select label="Grupo do produto" options={['17 — VENDA', '18 — SERVIÇO', '21 — REVENDA']} /></div>
            <Campo label="NCM" value={dv(d, 'ncm', '')} onChange={setV('ncm')} onBlur={marcar('ncm')} error={erroDe('ncm', VALIDA.ncm, dv(d, 'ncm', ''))} help="8 dígitos — Mercosul" />
            <Campo label="CEST" value={dv(d, 'cest', '')} onChange={setV('cest')} onBlur={marcar('cest')} error={erroDe('cest', VALIDA.cest, dv(d, 'cest', ''))} placeholder="sem CEST" />
            <Campo label="CFOP" value={dv(d, 'cfop', '')} onChange={setV('cfop')} onBlur={marcar('cfop')} error={erroDe('cfop', VALIDA.cfop, dv(d, 'cfop', ''))} help="natureza da operação" />
            <div><Select label="Origem da mercadoria" options={['0 — Nacional', '1 — Importação direta', '2 — Adquirida no mercado interno']} /></div>
            <div><Input label="Cód. de fábrica" placeholder="não informado" /></div>
            <Campo label="Cód. EAN / GTIN" value={dv(d, 'gtin', '')} onChange={setV('gtin')} onBlur={marcar('gtin')} error={erroDe('gtin', VALIDA.gtin, dv(d, 'gtin', ''))} placeholder="sem GTIN" />
            <Campo label="cBenef" value={dv(d, 'cbenef', '')} onChange={setV('cbenef')} onBlur={marcar('cbenef')} error={erroDe('cbenef', VALIDA.cbenef, dv(d, 'cbenef', ''))} placeholder="ex: SC830001" help="2 letras da UF + 6 dígitos" />
          </Grid>
          <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center' }}>
            <Switch label="Não recalcular impostos na impressão da nota" checked={dv(d, 'norecalc', false)} onChange={(v) => setD((s) => ({ ...s, norecalc: v }))} />
            <span style={{ marginLeft: 'auto' }}><Button size="sm">Recalcular impostos</Button></span>
          </div>
        </DrawerSection>
        <DrawerSection title="Impostos do item">
          <span style={{ display: 'block', font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)', marginBottom: 8 }}>Um imposto por linha — abra a setinha para ver e editar os campos. O ponto verde marca o que tem valor nesta venda.</span>
          <div style={{ border: '1px solid var(--border)', borderRadius: 12, overflow: 'hidden' }}>
            <div className="imp-linha" style={{ padding: '8px 12px', background: 'var(--bg-2)', borderBottom: '1px solid var(--border)' }}>
              {['Imposto', 'Base de cálculo', 'Alíquota', 'Valor', ''].map((h, k) => (
                <span key={h + k} style={{ font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', textAlign: k === 0 ? 'left' : k === 4 ? 'center' : 'right' }}>{h}</span>
              ))}
            </div>
            {IMPOSTOS.map((i) => {
              const aberto = trib === i.k;
              const val = impostoDe(i);
              const temValor = val > 0.005;
              const cstAtual = dv(d, 'cst_' + i.k, (i.k === 'icms' ? CST_ICMS : ['01 — Tributado'])[0]);
              const aliqAtual = dv(d, 'aliq_' + i.k, fmtBR(i.aliq));
              const erro = !!(VALIDA.aliq(aliqAtual) || erroCoerencia(cstAtual, aliqAtual));
              return (
                <div key={i.k} style={{ borderBottom: '1px solid var(--border-2)', background: aberto ? 'var(--bg-2)' : 'transparent' }}>
                  <button type="button" className="imp-linha" onClick={() => setTrib(aberto ? 'resumo' : i.k)} aria-expanded={aberto}
                    style={{ width: '100%', padding: '9px 12px', border: 0, background: 'transparent', cursor: 'pointer', textAlign: 'left', font: '13.5px/1.4 var(--font-sans)', color: 'var(--text)' }}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 7, minWidth: 0 }}>
                      <b style={{ fontWeight: 600 }}>{i.l}</b>
                      {erro
                        ? <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, font: '600 11px/1 var(--font-sans)', color: tomFg('var(--neg)') }}><Icon name="AlertTriangle" size={11} />pendência</span>
                        : temValor && <span aria-label="tem valor nesta venda" style={{ width: 5, height: 5, borderRadius: 999, background: 'var(--pos)' }}></span>}
                      {i.k === 'icms' && (dv(d, 'difal', false)
                        ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">DIFAL ligado</Pill>
                        : <Pill>tem DIFAL</Pill>)}
                    </span>
                    <span style={{ textAlign: 'right', font: '13px/1 var(--font-mono)', color: 'var(--text-dim)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(valorLinha)}</span>
                    <span style={{ textAlign: 'right', font: '13px/1 var(--font-mono)', color: erro ? tomFg('var(--neg)') : temValor ? 'var(--text)' : 'var(--text-dim)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(parseBR(aliqAtual))}%</span>
                    <span style={{ textAlign: 'right', font: '600 13.5px/1 var(--font-mono)', color: temValor ? 'var(--text)' : 'var(--text-dim)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(val)}</span>
                    <span aria-hidden="true" style={{ display: 'inline-flex', justifyContent: 'center', color: 'var(--text-dim)', transform: aberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }}><Icon name="ChevronDown" size={15} /></span>
                  </button>
                  {aberto && <div style={{ padding: '2px 12px 14px' }}>{campoTrib(i)}</div>}
                </div>
              );
            })}
            <div className="imp-linha" style={{ padding: '10px 12px', background: 'var(--bg-2)' }}>
              <span style={{ gridColumn: 'span 3', textAlign: 'right', font: '600 11px/1 var(--font-sans)', letterSpacing: '.04em', textTransform: 'uppercase', color: 'var(--text-dim)' }}>Total de impostos do item</span>
              <span style={{ textAlign: 'right', font: '600 15px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(somaImpostos)}</span>
              <span></span>
            </div>
          </div>

          <div style={{ marginTop: 14 }}><Grid cols={4} gap={10}>
            <Money label="IBPT nacional" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="IBPT importação" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="IBPT estadual" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="IBPT municipal" prefix="%" value="0,00" onChange={() => {}} />
            <Money label="Peso líquido" prefix="kg" value={dv(d, 'peso', '0,00')} onChange={set('peso')} help="só o produto" />
            <Money label="Peso bruto" prefix="kg" value={dv(d, 'peso_bruto', dv(d, 'peso', '0,00'))} onChange={set('peso_bruto')} help="com embalagem" />
            <Money label="Despesas acessórias" value={dv(d, 'desp', '0,00')} onChange={set('desp')} />
            <Money label="Frete do item" value={dv(d, 'frete_item', '0,00')} onChange={set('frete_item')} />
          </Grid></div>
        </DrawerSection>
        <DrawerSection title="Importação">
          <Grid cols={3} gap={10}>
            <div><Input label="Nº da DI / DUIMP" placeholder="produto nacional" /></div>
            <div><DataCampo label="Data do desembaraço" /></div>
            <div><Input label="Local do desembaraço" placeholder="não se aplica" /></div>
            <Money label="Valor aduaneiro" value="0,00" onChange={() => {}} />
            <Money label="AFRMM" value="0,00" onChange={() => {}} />
            <div><Select label="Via de transporte" options={['Marítima', 'Aérea', 'Rodoviária']} /></div>
          </Grid>
        </DrawerSection>
        <DrawerSection title="Descrição na NF-e">
          <Textarea label="Descrição do produto como sai na NF-e" rows={4} defaultValue={linha.nome} help="é este texto que o cliente lê na nota — não a descrição interna" />
        </DrawerSection>
      </>}

      {!carregando && aba === 'preco' && <DrawerSection title="Preço deste item">
        <Grid cols={3} gap={10}>
          <Money label="Preço de tabela" value="68,90" onChange={() => {}} readOnly help="o que a tabela do cliente indica" />
          <Money label="Menor preço permitido" value="58,40" onChange={() => {}} readOnly help="abaixo disto precisa liberação do supervisor" />
          <Money label="Preço nesta venda" value={d.preco} onChange={set('preco')} help="o valor que vai para o cliente" />
        </Grid>
        <div style={{ marginTop: 12, display: 'flex', flexWrap: 'wrap', gap: 14, alignItems: 'center', padding: 12, borderRadius: 12, background: parseBR(d.preco) >= 58.4 ? 'color-mix(in oklch, var(--pos) 8%, var(--surface))' : 'color-mix(in oklch, var(--neg) 8%, var(--surface))', border: '1px solid ' + (parseBR(d.preco) >= 58.4 ? 'color-mix(in oklch, var(--pos) 26%, transparent)' : 'color-mix(in oklch, var(--neg) 26%, transparent)') }}>
          {parseBR(d.preco) >= 58.4
            ? <><Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">preço liberado</Pill>
              <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Está {brl(submitSafe(parseBR(d.preco) - 58.4))} acima do menor preço permitido — pode fechar sem pedir nada.</span></>
            : <><Pill c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">precisa liberação</Pill>
              <span style={{ font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Faltam {brl(submitSafe(58.4 - parseBR(d.preco)))} para chegar ao menor preço permitido. Chame o supervisor antes de fechar.</span></>}
          <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'baseline', gap: 6 }}>
            <Lbl>Desconto sobre a tabela</Lbl>
            <b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{num(Math.max(0, (68.9 - parseBR(d.preco)) / 68.9 * 100), 1)}%</b>
          </span>
        </div>
        <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Custo, markup e margem <b>não aparecem para o vendedor</b> — o limite comercial já está no "menor preço permitido". Quem forma preço faz isso no cadastro do produto, com permissão própria.</p>
      </DrawerSection>}

      {!carregando && aba === 'anexos' && <DrawerSection title="Anexos do item">
        <div style={{ padding: 16, borderRadius: 12, border: '1px dashed var(--border)', background: 'var(--bg-2)', textAlign: 'center' }}>
          <p style={{ margin: '0 0 8px', font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Arraste o arquivo de arte, a foto do local ou o comprovante de aprovação.</p>
          <Button size="sm" variant="primary">Escolher arquivo</Button>
        </div>
        <div style={{ marginTop: 12 }}>
          <DataTable columns={[{ key: 'n', label: 'Arquivo' }, { key: 't', label: 'Tipo' }, { key: 'd', label: 'Enviado', mono: true }, { key: 'a', label: '', align: 'center' }]}
            rows={[['lona-prefeitura-v3.pdf', 'arte final', '27/07/2026'], ['aprovacao-email.png', 'aprovação', '27/07/2026']].map(([n, t, dt], i) => ({ id: i, cells: { n, t: <Pill>{t}</Pill>, d: dt, a: <Button size="sm">Baixar</Button> } }))} />
        </div>
      </DrawerSection>}

      {!carregando && aba === 'obs' && <DrawerSection title="Observações do produto">
        <Textarea label="Observação geral do produto (sai no documento do cliente)" rows={4} placeholder="Lona com 5cm de sangria em cada lado, ilhós a cada 50cm." />
        <div style={{ marginTop: 12 }}><Textarea label="Observação interna (não sai no documento)" rows={3} value={dv(d, 'obsItem', '')} onChange={set('obsItem')} placeholder="Cliente reclamou da cor na última compra — conferir perfil ICC." /></div>
        <Meta><p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-mute)' }}>Duas observações separadas de propósito: a do cliente vai pro PDF/NF-e, a interna fica na OP. Unificar as duas foi o que gerou a reclamação de vazamento de nota interna no documento (CU-SELL-12).</p></Meta>
      </DrawerSection>}
    </Drawer>
  );
}

Object.assign(window, { ItemDetail, IMPOSTOS });
