/* Colunas do grid de itens — adaptação da lista de colunas habilitáveis do legado.
   Só entra coluna que a venda consegue PREENCHER: nada de cabeçalho com "—" eterno.
   Ficaram fora, de propósito, os ~40 campos "Nf. v*" / "Nf. vALIQ *" (valores calculados do
   XML da NF-e): são resultado, não entrada — vivem na aba Tributação do detalhe e na NF-e. */

const COL_LS = 'oimpresso.vendas.sdd.colunas';

const cat = (l) => window.SD.catalogo.find((p) => p.sku === l.sku) || {};
const dimensional = (l) => l.un === 'm²' || l.un === 'm³' || l.un === 'm';
const areaDe = (l) => l.un === 'm²' ? submitSafe(parseBR(l.altura) * parseBR(l.largura))
  : l.un === 'm³' ? submitSafe(parseBR(l.altura) * parseBR(l.largura) * parseBR(l.esp))
  : l.un === 'm' ? parseBR(l.largura) : 0;
const txt = (v) => (v === undefined || v === null || v === '') ? '—' : v;

/* célula de número editável (mesmo padrão dos campos de valor da linha) */
const cellNum = (ctx, campo, afixo, rotulo, padrao) => (
  <div className={'dsfa' + (afixo === 'R$' ? ' pre' : afixo ? ' suf' : '')}>
    {afixo === 'R$' && <span className="afx l">R$</span>}
    {afixo && afixo !== 'R$' && <span className="afx r">{afixo}</span>}
    <input value={ctx.l[campo] === undefined ? padrao : ctx.l[campo]} readOnly={ctx.travada} inputMode="decimal"
      aria-label={rotulo + ' — ' + ctx.l.nome} onChange={(e) => ctx.setLinha(ctx.l.k, campo, e.target.value)}
      style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} />
  </div>
);

const COLUNAS = [
  /* fixas — a linha não existe sem elas */
  { k: 'produto', l: 'Produto / serviço', g: 'ident', fixa: true, w: 260, cell: (ctx) => (
    <>
      <b title={ctx.l.nome} style={{ fontWeight: 600, display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{ctx.l.nome}</b>
      <span style={{ display: 'block', font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{ctx.l.sku} · {ctx.l.un}
        {ctx.l.pecas && parseBR(ctx.l.pecas) > 0 && dimensional(ctx.l) ? ' · ' + num(parseBR(ctx.l.pecas), 0) + '× ' + num(parseBR(ctx.l.altura), 2) + 'x' + num(parseBR(ctx.l.largura), 2) + 'm' : ''}
        {ctx.l.func ? ' · ' + ctx.l.func : ''}</span>
    </>
  ) },

  /* identificação */
  { k: 'seq', l: 'Seq.', g: 'ident', w: 52, align: 'right', mono: true, cell: (ctx) => ctx.i + 1 },
  { k: 'codigo', l: 'Cód. produto', g: 'ident', w: 120, mono: true, cell: (ctx) => ctx.l.sku },
  { k: 'unidade', l: 'Unidade', g: 'ident', w: 76, mono: true, cell: (ctx) => ctx.l.un },
  { k: 'tipoProd', l: 'Tipo', g: 'ident', w: 92, cell: (ctx) => cat(ctx.l).tipo === 'servico'
    ? <Pill c="var(--color-info)" s="color-mix(in oklch, var(--color-info) 12%, var(--surface))">serviço</Pill>
    : <Pill>produto</Pill> },

  /* medidas */
  { k: 'pecas', l: 'Qtd. peças', g: 'medida', w: 92, align: 'right', cell: (ctx) => dimensional(ctx.l) ? cellNum(ctx, 'pecas', 'qt', 'Peças', '1') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'altura', l: 'Comprimento', g: 'medida', w: 100, align: 'right', cell: (ctx) => dimensional(ctx.l) ? cellNum(ctx, 'altura', 'm', 'Comprimento', '0,00') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'largura', l: 'Largura', g: 'medida', w: 100, align: 'right', cell: (ctx) => dimensional(ctx.l) ? cellNum(ctx, 'largura', 'm', 'Largura', '0,00') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'esp', l: 'Espessura', g: 'medida', w: 100, align: 'right', cell: (ctx) => ctx.l.un === 'm³' ? cellNum(ctx, 'esp', 'm', 'Espessura', '0,00') : <span style={{ color: 'var(--text-dim)' }}>—</span> },
  { k: 'medidas', l: 'Medidas', g: 'medida', w: 128, mono: true, cell: (ctx) => dimensional(ctx.l)
    ? num(parseBR(ctx.l.altura), 2) + ' × ' + num(parseBR(ctx.l.largura), 2) + ' m'
    : '—' },
  { k: 'formula', l: 'Fórmula', g: 'medida', w: 150, cell: (ctx) => dimensional(ctx.l)
    ? <span style={{ font: '11.5px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{num(parseBR(ctx.l.pecas), 0)} × {num(areaDe(ctx.l), 3)} {ctx.l.un}</span>
    : <span style={{ color: 'var(--text-dim)' }}>—</span> },

  /* valores */
  { k: 'qtd', l: 'Quant.', g: 'valor', padrao: true, w: 100, align: 'right', cell: (ctx) => (
    <div className="dsfa"><input value={ctx.l.qtd} readOnly={ctx.travada} inputMode="decimal" aria-invalid={parseBR(ctx.l.qtd) <= 0}
      aria-label={'Quantidade — ' + ctx.l.nome} onChange={(e) => ctx.setLinha(ctx.l.k, 'qtd', e.target.value)}
      style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} /></div>
  ) },
  { k: 'tabela', l: 'R$ tabela', g: 'valor', w: 104, align: 'right', mono: true, cell: (ctx) => cat(ctx.l).preco !== undefined ? fmtBR(cat(ctx.l).preco) : '—' },
  { k: 'preco', l: 'R$ valor', g: 'valor', padrao: true, w: 130, align: 'right', cell: (ctx) => (
    <div className="dsfa pre"><span className="afx l">R$</span><input value={ctx.l.preco} readOnly={ctx.travada} inputMode="decimal" aria-invalid={parseBR(ctx.l.preco) <= 0}
      aria-label={'Preço unitário — ' + ctx.l.nome} onChange={(e) => ctx.setLinha(ctx.l.k, 'preco', e.target.value)}
      style={{ textAlign: 'right', fontFamily: 'var(--font-mono)' }} /></div>
  ) },
  { k: 'desc', l: 'Desc. %', g: 'valor', padrao: true, w: 92, align: 'right', cell: (ctx) => cellNum(ctx, 'desc', '%', 'Desconto percentual', '0') },
  { k: 'descRS', l: 'R$ desconto', g: 'valor', w: 110, align: 'right', mono: true, cell: (ctx) => fmtBR(submitSafe(parseBR(ctx.l.qtd) * parseBR(ctx.l.preco) * parseBR(ctx.l.desc) / 100)) },
  { k: 'acr', l: 'Acrésc. %', g: 'valor', padrao: true, w: 92, align: 'right', cell: (ctx) => cellNum(ctx, 'acr', '%', 'Acréscimo percentual', '0') },
  { k: 'acrRS', l: 'R$ outros', g: 'valor', w: 104, align: 'right', mono: true, cell: (ctx) => fmtBR(submitSafe(parseBR(ctx.l.qtd) * parseBR(ctx.l.preco) * (1 - parseBR(ctx.l.desc) / 100) * parseBR(ctx.l.acr || 0) / 100)) },
  { k: 'total', l: 'R$ total', g: 'valor', fixa: true, w: 116, align: 'right', cell: (ctx) => (
    <b style={{ font: '600 13.5px/1 var(--font-mono)', fontVariantNumeric: 'tabular-nums' }}>{fmtBR(linhaTotal(ctx.l))}</b>
  ) },

  /* fiscal — classificação (entrada), não valor calculado de NF-e */
  { k: 'ncm', l: 'NCM', g: 'fiscal', w: 104, mono: true, cell: (ctx) => txt(ctx.l.ncm || cat(ctx.l).ncm) },
  { k: 'cfop', l: 'CFOP', g: 'fiscal', w: 80, mono: true, cell: (ctx) => txt(ctx.l.cfop) },
  { k: 'cst', l: 'CST', g: 'fiscal', w: 80, mono: true, cell: (ctx) => txt((ctx.l.cst_icms || '').split(' —')[0]) },
  { k: 'cest', l: 'CEST', g: 'fiscal', w: 96, mono: true, cell: (ctx) => txt(ctx.l.cest) },

  /* produção */
  { k: 'emProducao', l: 'Em produção', g: 'producao', w: 104, cell: (ctx) => ctx.l.emProducao ? <Pill c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">sim</Pill> : <Pill>não</Pill> },
  { k: 'impressao', l: 'Tipo de impressão', g: 'producao', w: 150, cell: (ctx) => <span style={{ font: '12px/1.3 var(--font-sans)', color: ctx.l.impressao ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.impressao)}</span> },
  { k: 'local', l: 'Local da aplicação', g: 'producao', w: 140, cell: (ctx) => <span style={{ font: '12px/1.3 var(--font-sans)', color: ctx.l.local ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.local)}</span> },
  { k: 'prazoEtapa', l: 'Prazo da etapa', g: 'producao', w: 116, mono: true, cell: (ctx) => txt(dTexto(dParse(ctx.l.prazoEtapa))) },
  { k: 'func', l: 'Funcionário', g: 'producao', w: 140, cell: (ctx) => <span style={{ font: '12px/1.3 var(--font-sans)', color: ctx.l.func ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.func)}</span> },
  { k: 'obsProd', l: 'Obs. da produção', g: 'producao', w: 200, cell: (ctx) => (
    <span title={ctx.l.obsProd || ''} style={{ display: 'block', maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', font: '12px/1.3 var(--font-sans)', color: ctx.l.obsProd ? 'var(--text)' : 'var(--text-dim)' }}>{txt(ctx.l.obsProd)}</span>
  ) },

  /* estoque */
  { k: 'estoque', l: 'Estoque', g: 'estoque', w: 104, align: 'right', cell: (ctx) => {
    const p = cat(ctx.l);
    if (p.estoque === null || p.estoque === undefined) return <span style={{ color: 'var(--text-dim)' }}>—</span>;
    const falta = parseBR(ctx.l.qtd) > p.estoque;
    return <Pill mono c={falta ? 'var(--neg)' : 'var(--pos)'} s={'color-mix(in oklch, ' + (falta ? 'var(--neg)' : 'var(--pos)') + ' 12%, var(--surface))'}>{num(p.estoque, 2)}</Pill>;
  } },
  { k: 'localEstoque', l: 'Local de estoque', g: 'estoque', w: 130, cell: (ctx) => txt(cat(ctx.l).localEstoque) },
];

const COL_GRUPOS = [
  { k: 'ident', l: 'Identificação' }, { k: 'medida', l: 'Medidas' }, { k: 'valor', l: 'Valores' },
  { k: 'fiscal', l: 'Classificação fiscal' }, { k: 'producao', l: 'Produção' }, { k: 'estoque', l: 'Estoque' },
];

const colunasPadrao = () => COLUNAS.filter((c) => c.fixa || c.padrao).map((c) => c.k);
const carregarColunas = () => {
  try {
    const s = JSON.parse(localStorage.getItem(COL_LS) || 'null');
    if (Array.isArray(s) && s.length) return s.filter((k) => COLUNAS.some((c) => c.k === k));
  } catch (e) {}
  return colunasPadrao();
};

function ColunasModal({ open, onClose, ativas, setAtivas }) {
  const def = (k) => COLUNAS.find((c) => c.k === k);
  const ativa = ativas.map(def).filter(Boolean);
  const fora = COLUNAS.filter((c) => !ativas.includes(c.k));
  const mover = (i, d) => {
    const j = i + d;
    if (j < 0 || j >= ativas.length) return;
    const s = [...ativas];
    s.splice(j, 0, s.splice(i, 1)[0]);
    setAtivas(s);
  };
  const arrasta = React.useRef(null);
  const soltar = (i) => {
    const de = arrasta.current;
    arrasta.current = null;
    if (de === null || de === i) return;
    const s = [...ativas];
    s.splice(i, 0, s.splice(de, 1)[0]);
    setAtivas(s);
  };

  return (
    <Modal open={open} onClose={onClose} width={860} title="Colunas do grid de itens"
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>{ativas.length} de {COLUNAS.length} colunas · ordem e escolha ficam salvas neste navegador</span>
        <span style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}>
          <Button onClick={() => setAtivas(colunasPadrao())}>Restaurar padrão</Button>
          <Button variant="primary" onClick={onClose}>Fechar</Button>
        </span>
      </div>}>
      <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)', gap: 18 }}>
        <div>
          <Lbl>No grid — de cima para baixo é a ordem das colunas</Lbl>
          <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
            {ativa.map((c, i) => (
              <li key={c.k} draggable onDragStart={() => { arrasta.current = i; }} onDragOver={(e) => e.preventDefault()} onDrop={() => soltar(i)}
                style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '5px 8px', borderRadius: 8, background: 'var(--bg-2)', border: '1px solid var(--border)', cursor: 'grab' }}>
                <span aria-hidden="true" style={{ color: 'var(--text-dim)', display: 'inline-flex' }}><Icon name="GripVertical" size={13} /></span>
                <span style={{ width: 18, flex: 'none', font: '11px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{i + 1}</span>
                <span style={{ flex: 1, minWidth: 0, font: '12.5px/1.4 var(--font-sans)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.l}</span>
                {c.fixa && <Pill>fixa</Pill>}
                <button type="button" aria-label={'Subir ' + c.l} disabled={i === 0} onClick={() => mover(i, -1)} style={{ width: 24, height: 24, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: i === 0 ? 'default' : 'pointer', opacity: i === 0 ? .4 : 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronUp" size={13} /></button>
                <button type="button" aria-label={'Descer ' + c.l} disabled={i === ativa.length - 1} onClick={() => mover(i, 1)} style={{ width: 24, height: 24, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: i === ativa.length - 1 ? 'default' : 'pointer', opacity: i === ativa.length - 1 ? .4 : 1, display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="ChevronDown" size={13} /></button>
                {!c.fixa && <button type="button" aria-label={'Tirar ' + c.l + ' do grid'} onClick={() => setAtivas(ativas.filter((x) => x !== c.k))} style={{ width: 24, height: 24, flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}><Icon name="X" size={13} /></button>}
              </li>
            ))}
          </ol>
        </div>
        <div>
          <Lbl>Disponíveis</Lbl>
          {fora.length === 0
            ? <p style={{ margin: 0, font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Todas as colunas estão no grid.</p>
            : COL_GRUPOS.map((g) => {
              const itens = fora.filter((c) => c.g === g.k);
              if (!itens.length) return null;
              return (
                <div key={g.k} style={{ marginBottom: 10 }}>
                  <span style={{ display: 'block', marginBottom: 4, font: '600 10.5px/1.6 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)' }}>{g.l}</span>
                  <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                    {itens.map((c) => (
                      <li key={c.k} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '5px 8px', borderRadius: 8, background: 'var(--surface)', border: '1px dashed var(--border)' }}>
                        <span style={{ flex: 1, minWidth: 0, font: '12.5px/1.4 var(--font-sans)', color: 'var(--text-dim)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.l}</span>
                        <button type="button" aria-label={'Colocar ' + c.l + ' no grid'} onClick={() => setAtivas([...ativas, c.k])}
                          style={{ display: 'inline-flex', alignItems: 'center', gap: 4, height: 24, padding: '0 8px', flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--accent)', cursor: 'pointer', font: '600 11.5px/1 var(--font-sans)' }}>
                          <Icon name="Plus" size={12} />usar
                        </button>
                      </li>
                    ))}
                  </ul>
                </div>
              );
            })}
        </div>
      </div>
      <p style={{ margin: '14px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>
        A coluna de <b>ações</b> (impostos, detalhes, remover) fica sempre grudada na direita do grid — não entra nesta lista porque não é informação, é operação.
        Os campos de <b>valor de imposto</b> da lista antiga (Nf. vICMS, vPIS, vBC, vALIQ…) também não entram: são <b>resultado</b> do cálculo, não algo que se digita na linha — ficam na aba <b>Tributação</b> do detalhe do item e na NF-e.
      </p>
    </Modal>
  );
}

Object.assign(window, { COLUNAS, COL_GRUPOS, ColunasModal, carregarColunas, colunasPadrao, COL_LS });
