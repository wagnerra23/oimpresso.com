// Ajustes aplicados SÓ no artefato, por cima da âncora do repo (venda-v3.jsx não é editado).
// Cada trecho `de` precisa existir exatamente uma vez; se a âncora mudar, o build para e avisa.
module.exports = [
  {
    motivo: 'desconto em % e em R$ no lançamento do item — helpers de formato',
    de: 'function LancarItem({ produto, onClose, onConfirm }) {',
    para: `/* Desconto do lançamento em % e em R$ (ajuste do artefato, 20/10/2026).
   O % gravado na linha sai com vírgula e até 6 casas: "5.125" seria lido pelo parseBR como
   milhar (5125), o vetor do incidente num_uf. */
const pctBR = (n) => Number(n).toLocaleString('pt-BR', { useGrouping: false, maximumFractionDigits: 6 });
const pctTela = (n) => Number(n).toLocaleString('pt-BR', { useGrouping: false, maximumFractionDigits: 4 });

function LancarItem({ produto, onClose, onConfirm }) {`,
  },
  {
    motivo: 'estado do desconto em R$ e de qual campo manda',
    de: "  const [desc, setDesc] = React.useState('0');\n  const [acr, setAcr]",
    para: `  const [desc, setDesc] = React.useState('0');
  /* o campo digitado por último manda; o outro é derivado dele */
  const [descRS, setDescRS] = React.useState('0,00');
  const [descModo, setDescModo] = React.useState('pct');
  const [acr, setAcr]`,
  },
  {
    motivo: 'zera o desconto em R$ ao trocar de produto',
    de: "setDesc('0'); setAcr('0');",
    para: "setDesc('0'); setDescRS('0,00'); setDescModo('pct'); setAcr('0');",
  },
  {
    motivo: 'cálculo: % e R$ derivados um do outro; total igual ao linhaTotal da grade',
    de: `  const unitario = submitSafe(parseBR(preco) * (1 - parseBR(desc) / 100) * (1 + parseBR(acr) / 100));
  const total = submitSafe(qtd * unitario);`,
    para: `  /* desconto sobre quantidade × valor unitário — a mesma base da coluna "R$ desconto" da grade */
  const bruto = qtd * parseBR(preco);
  const descValor = descModo === 'rs' ? submitSafe(parseBR(descRS)) : submitSafe(bruto * parseBR(desc) / 100);
  const descPct = descModo === 'rs' ? (bruto > 0 ? descValor / bruto * 100 : 0) : parseBR(desc);
  const descErro = (descPct < 0 || descValor < 0) ? 'Desconto não pode ser negativo'
    : (descPct > 100 || descValor > submitSafe(bruto)) ? 'Desconto maior que o valor do item' : null;
  const unitario = submitSafe(parseBR(preco) * (1 - descPct / 100) * (1 + parseBR(acr) / 100));
  /* total pela mesma conta do linhaTotal (sem arredondar o unitário antes): o mesmo
     desconto em reais dá o mesmo total aqui e na grade */
  const total = submitSafe(bruto * (1 - descPct / 100) * (1 + parseBR(acr) / 100));`,
  },
  {
    motivo: 'a linha guarda o % exato (com vírgula)',
    de: 'desc: String(parseBR(desc)), acr:',
    para: 'desc: pctBR(descPct), acr:',
  },
  {
    motivo: 'não adiciona item com desconto inválido',
    de: '<Button variant="primary" disabled={qtd <= 0} onClick={confirmar}>Adicionar à venda</Button>',
    para: '<Button variant="primary" disabled={qtd <= 0 || !!descErro} onClick={confirmar}>Adicionar à venda</Button>',
  },
  {
    motivo: 'campos: desconto em % e em R$ lado a lado',
    de: `            <Money label="Desconto" prefix="%" value={desc} onChange={setDesc} readOnly={!podePreco} />
            <Money label="Acréscimo" prefix="%" value={acr} onChange={setAcr} readOnly={!podePreco} />`,
    para: `            <Money label="Acréscimo" prefix="%" value={acr} onChange={setAcr} readOnly={!podePreco} />
            <Money label="Desconto %" prefix="%" aria="Desconto em percentual"
              value={descModo === 'pct' ? desc : pctTela(descPct)}
              onChange={(v) => { setDesc(v); setDescModo('pct'); }} readOnly={!podePreco}
              error={descModo === 'pct' ? descErro : null} />
            <Money label="Desconto R$" aria="Desconto em reais"
              value={descModo === 'rs' ? descRS : fmtBR(descValor)}
              onChange={(v) => { setDescRS(v); setDescModo('rs'); }} readOnly={!podePreco}
              error={descModo === 'rs' ? descErro : null} help="sobre quantidade × valor unitário" />`,
  },
  {
    motivo: 'resumo mostra o desconto nas duas formas',
    de: `            <div><Lbl>Quantidade</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{fmtQtd(qtd)} {produto.un}</b></div>
            <div style={{ marginLeft: 'auto' }}><Lbl c="var(--accent)">Total do item</Lbl>`,
    para: `            <div><Lbl>Quantidade</Lbl><b style={{ font: '600 15px/1 var(--font-mono)' }}>{fmtQtd(qtd)} {produto.un}</b></div>
            {descValor > 0 && !descErro && <div><Lbl>Desconto</Lbl><b style={{ font: '600 15px/1 var(--font-mono)', color: tomFg('var(--neg)') }}>− {brl(descValor)}</b><span style={{ marginLeft: 6, font: '12px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{num(descPct, 2)}%</span></div>}
            <div style={{ marginLeft: 'auto' }}><Lbl c="var(--accent)">Total do item</Lbl>`,
  },
];
