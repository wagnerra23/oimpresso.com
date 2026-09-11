/* Comissão da venda — modelo de ERP, não um campo "Comissionista".
   O que um ERP sério exige e o select único não dava:
   1. VÁRIOS beneficiários na mesma venda, de tipos diferentes (funcionário, representante,
      agência, técnico) — quem vendeu, quem trouxe o cliente e quem executou raramente
      são a mesma pessoa, e cada um tem regra própria;
   2. BASE declarada por beneficiário: bruto, líquido de desconto ou MARGEM. Comissão sobre
      bruto paga o vendedor para dar desconto; sobre margem, alinha o incentivo;
   3. REGRA: percentual, valor fixo, ou faixa progressiva (tiered) por volume;
   4. GATILHO de direito: emissão, faturamento ou RECEBIMENTO (accrual vs cash) — é o que
      decide se a empresa paga comissão de venda que o cliente não pagou;
   5. ESTORNO (clawback) na devolução e no cancelamento, proporcional ao devolvido;
   6. SNAPSHOT da regra no momento da venda: mudar a política não pode reescrever comissão
      de venda passada;
   7. Apuração em ciclo próprio (provisionada → aprovada → paga) e teto/piso por venda.
   Aqui a tela cobre 1–6 e mostra o estado da apuração (7), que é do módulo Financeiro/RH. */

const COM_TIPOS = [
  { k: 'funcionario', l: 'Funcionário (vendedor interno)', hue: 'var(--accent)', pgto: 'folha de pagamento', doc: 'CLT' },
  { k: 'representante', l: 'Representante (externo)', hue: 'var(--color-info)', pgto: 'título a pagar', doc: 'RPA / nota' },
  { k: 'agencia', l: 'Agência / parceiro', hue: 'var(--warn)', pgto: 'título a pagar', doc: 'nota de serviço' },
  { k: 'tecnico', l: 'Técnico / instalador', hue: 'var(--pos)', pgto: 'folha ou produção', doc: 'CLT / autônomo' },
];
/* fonte única: window.SD.pessoas (sells-data.js). Rótulo mostra o papel, valor é só o nome. */
const comOpcoes = (tipo) => window.SD.pessoasDe(tipo).map((p) => ({ value: p.nome, label: p.papel ? p.nome + ' · ' + p.papel : p.nome }));
const comPrimeira = (tipo) => (window.SD.pessoasDe(tipo)[0] || {}).nome || '';
const COM_BASES = [
  { value: 'liquido', label: 'Líquido de desconto' },
  { value: 'bruto', label: 'Bruto (antes do desconto)' },
  { value: 'margem', label: 'Margem (venda − custo)' },
];
const COM_GATILHOS = [
  { value: 'recebimento', label: 'A cada parcela recebida' },
  { value: 'faturamento', label: 'No faturamento da venda' },
  { value: 'emissao', label: 'Na emissão da venda' },
];
const COM_FAIXAS = [{ ate: 5000, p: 2 }, { ate: 20000, p: 3 }, { ate: null, p: 4 }];

const comTipo = (k) => COM_TIPOS.find((t) => t.k === k) || COM_TIPOS[0];
const comFaixa = (base) => (COM_FAIXAS.find((f) => f.ate === null || base <= f.ate) || COM_FAIXAS[0]).p;

/* base de cálculo de cada beneficiário, a partir dos totais da venda */
function comBase(b, tot) {
  if (b.base === 'bruto') return tot.bruto;
  if (b.base === 'margem') return tot.margem;
  return tot.liquido;
}
function comValor(b, tot) {
  const base = comBase(b, tot);
  if (b.regra === 'fixo') return submitSafe(parseBR(b.valor));
  const p = b.regra === 'faixa' ? comFaixa(base) : parseBR(b.pct);
  return submitSafe(base * p / 100);
}

/* Resumo na tela — abre o modal; nunca fica escondido atrás de "mais opções" */
function ComissaoResumo({ bens, tot, gatilho, onAbrir, parcelas = [], totalVenda = 0 }) {
  const soma = submitSafe(bens.reduce((s, b) => s + comValor(b, tot), 0));
  const pctVenda = tot.liquido > 0 ? soma / tot.liquido * 100 : 0;
  const g = COM_GATILHOS.find((x) => x.value === gatilho) || COM_GATILHOS[0];
  const recebidas = gatilho === 'recebimento' ? parcelas.filter((p) => p.lanc === 'RECEBIDA') : [];
  const liberada = gatilho === 'recebimento' && totalVenda > 0
    ? submitSafe(recebidas.reduce((s, p) => s + soma * parseBR(p.valor) / totalVenda, 0)) : 0;
  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 12, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
      <div>
        <Lbl>Comissão da venda</Lbl>
        <b style={{ font: '600 15px/1 var(--font-mono)' }}>{brl(soma)}</b>
        {soma > 0 && <span style={{ font: '11.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}> · {num(pctVenda, 2)}% da venda</span>}
      </div>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5, minWidth: 0 }}>
        {bens.length === 0
          ? <span style={{ font: '12px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Nenhum beneficiário — venda sem comissão</span>
          : bens.map((b) => (
            <Pill key={b.k} c={comTipo(b.tipo).hue} s={'color-mix(in oklch, ' + comTipo(b.tipo).hue + ' 12%, var(--surface))'}>
              {b.pessoa || comTipo(b.tipo).l} · {brl(comValor(b, tot))}
            </Pill>
          ))}
      </div>
      <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
        {gatilho === 'recebimento' && parcelas.length > 0 && <Pill mono c={liberada > 0 ? 'var(--pos)' : 'var(--text-dim)'} s={liberada > 0 ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'transparent'} title="Comissão já devida pelas parcelas baixadas">{brl(liberada)} devida · {recebidas.length}/{parcelas.length} parcelas</Pill>}
        <Pill mono title="Quando a comissão passa a ser devida">{g.label}</Pill>
        <Button size="sm" onClick={onAbrir}>Configurar comissão</Button>
      </span>
    </div>
  );
}

/* rateio da comissão pelas parcelas — só faz sentido no gatilho "a cada parcela recebida" */
function comPorParcela(parcelas, soma, totalVenda) {
  if (!parcelas.length || totalVenda <= 0) return [];
  return parcelas.map((p) => {
    const v = parseBR(p.valor);
    return { num: p.num, de: p.de, venc: p.venc, valor: v, recebida: p.lanc === 'RECEBIDA', pgto: p.pgto, com: submitSafe(soma * v / totalVenda) };
  });
}

function ComissaoModal({ open, onClose, bens, setBens, tot, gatilho, setGatilho, itensServico, parcelas = [], totalVenda = 0 }) {
  const add = (tipo) => setBens([...bens, { k: Date.now(), tipo, pessoa: comPrimeira(tipo), base: tipo === 'agencia' ? 'margem' : 'liquido', regra: 'pct', pct: tipo === 'agencia' ? '5,00' : '3,00', valor: '0,00' }]);
  const setB = (k, campo, v) => setBens(bens.map((b) => b.k === k ? { ...b, [campo]: v } : b));
  const soma = submitSafe(bens.reduce((s, b) => s + comValor(b, tot), 0));
  const sobreMargem = tot.margem > 0 ? soma / tot.margem * 100 : 0;
  const comeMargem = soma > tot.margem * 0.5;

  return (
    <Modal open={open} onClose={onClose} width={880} title="Comissão desta venda"
      footer={<div style={{ display: 'flex', gap: 8, alignItems: 'center', width: '100%' }}>
        <div>
          <Lbl>Total de comissão</Lbl>
          <b style={{ font: '600 17px/1 var(--font-mono)' }}>{brl(soma)}</b>
          {tot.margem > 0 && <span style={{ font: '11.5px/1 var(--font-sans)', color: comeMargem ? tomFg('var(--neg)') : 'var(--text-dim)' }}> · {num(sobreMargem, 1)}% da margem</span>}
        </div>
        <span style={{ marginLeft: 'auto' }}><Button variant="primary" onClick={onClose}>Fechar</Button></span>
      </div>}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, padding: 12, borderRadius: 12, background: 'var(--bg-2)', border: '1px solid var(--border)' }}>
          {[['Bruto', tot.bruto], ['Líquido de desconto', tot.liquido], ['Margem estimada', tot.margem]].map(([l, v]) => (
            <div key={l}><Lbl>{l}</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(v)}</b></div>
          ))}
          <div style={{ minWidth: 220, marginLeft: 'auto' }}>
            <Select label="Quando a comissão é devida" value={gatilho} onChange={(e) => setGatilho(e.target.value)} options={COM_GATILHOS} />
          </div>
        </div>

        <div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap', marginBottom: 8 }}>
            <Lbl>Beneficiários</Lbl>
            <span style={{ marginLeft: 'auto', display: 'flex', gap: 5, flexWrap: 'wrap' }}>
              {COM_TIPOS.map((t) => (
                <button key={t.k} type="button" onClick={() => add(t.k)}
                  style={{ display: 'inline-flex', alignItems: 'center', gap: 5, height: 26, padding: '0 9px', borderRadius: 999, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text)', cursor: 'pointer', font: '12px/1 var(--font-sans)' }}>
                  <Icon name="Plus" size={12} />{t.l.split(' (')[0]}
                </button>
              ))}
            </span>
          </div>
          {bens.length === 0
            ? <p style={{ margin: 0, font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Nenhum beneficiário. Uma venda pode ter mais de um: o vendedor interno que digitou, o representante da região e a agência que trouxe o cliente — cada um com base e percentual próprios.</p>
            : <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {bens.map((b) => {
                const t = comTipo(b.tipo);
                const base = comBase(b, tot);
                return (
                  <div key={b.k} style={{ padding: 12, borderRadius: 12, background: 'var(--surface)', border: '1px solid color-mix(in oklch, ' + t.hue + ' 24%, var(--border))' }}>
                    <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 8, marginBottom: 10 }}>
                      <Pill c={t.hue} s={'color-mix(in oklch, ' + t.hue + ' 12%, var(--surface))'}>{t.l}</Pill>
                      <span style={{ font: '11.5px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>paga por <b>{t.pgto}</b> · documento {t.doc}</span>
                      <span style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
                        <span><Lbl>Comissão</Lbl><b style={{ font: '600 14px/1 var(--font-mono)' }}>{brl(comValor(b, tot))}</b></span>
                        <Button size="sm" onClick={() => setBens(bens.filter((x) => x.k !== b.k))}>Remover</Button>
                      </span>
                    </div>
                    <Grid cols={4} gap={10}>
                      <div><Select label="Quem" value={b.pessoa} onChange={(e) => setB(b.k, 'pessoa', e.target.value)} options={comOpcoes(b.tipo)} /></div>
                      <div><Select label="Base de cálculo" value={b.base} onChange={(e) => setB(b.k, 'base', e.target.value)} options={COM_BASES} /></div>
                      <div><Select label="Regra" value={b.regra} onChange={(e) => setB(b.k, 'regra', e.target.value)} options={[{ value: 'pct', label: 'Percentual' }, { value: 'faixa', label: 'Faixa progressiva' }, { value: 'fixo', label: 'Valor fixo' }]} /></div>
                      {b.regra === 'fixo'
                        ? <Money label="Valor" value={b.valor} onChange={(v) => setB(b.k, 'valor', v)} />
                        : b.regra === 'faixa'
                          ? <div><Lbl>Faixa aplicada</Lbl><b style={{ font: '600 13.5px/1.4 var(--font-mono)' }}>{num(comFaixa(base), 2)}%</b><span style={{ display: 'block', font: '11px/1.3 var(--font-sans)', color: 'var(--text-dim)' }}>até 5 mil 2% · até 20 mil 3% · acima 4%</span></div>
                          : <Money label="Percentual" prefix="%" value={b.pct} onChange={(v) => setB(b.k, 'pct', v)} />}
                    </Grid>
                    <span style={{ display: 'block', marginTop: 8, font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>
                      Base de {brl(base)} ({(COM_BASES.find((x) => x.value === b.base) || {}).label.toLowerCase()})
                      {b.base === 'bruto' && <span style={{ color: tomFg('var(--warn)') }}> — comissão sobre bruto paga o mesmo com ou sem desconto; é o incentivo invertido.</span>}
                    </span>
                  </div>
                );
              })}
            </div>}
        </div>

        {gatilho === 'recebimento' && (() => {
          const rat = comPorParcela(parcelas, soma, totalVenda);
          const liberada = submitSafe(rat.filter((r) => r.recebida).reduce((s, r) => s + r.com, 0));
          return (
            <div>
              <Lbl>Liberação por parcela recebida</Lbl>
              {rat.length === 0
                ? <p style={{ margin: 0, font: '12.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>Esta venda ainda não tem parcelas. Com o gatilho <b>a cada parcela recebida</b>, a comissão é rateada na proporção de cada parcela e só vira devida quando a parcela é baixada — venda em 3× libera a comissão em 3 vezes.</p>
                : <>
                  <div className="oi-scroll" style={{ overflowX: 'auto', border: '1px solid var(--border)', borderRadius: 10 }}>
                    <table style={{ width: '100%', minWidth: 460, borderCollapse: 'separate', borderSpacing: 0, font: '12.5px/1.4 var(--font-sans)' }}>
                      <thead><tr>{['Parcela', 'Vencimento', 'Valor', 'Comissão', 'Situação'].map((h, i) => (
                        <th key={h} style={{ background: 'var(--bg-2)', padding: '7px 10px', textAlign: i >= 2 && i <= 3 ? 'right' : 'left', font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)', borderBottom: '1px solid var(--border)', whiteSpace: 'nowrap' }}>{h}</th>
                      ))}</tr></thead>
                      <tbody>{rat.map((r) => (
                        <tr key={r.num}>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', font: '12.5px/1 var(--font-mono)' }}>{r.num}/{r.de}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', font: '12.5px/1 var(--font-mono)', color: 'var(--text-dim)' }}>{dTexto(dParse(r.venc))}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', textAlign: 'right', font: '12.5px/1 var(--font-mono)' }}>{fmtBR(r.valor)}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)', textAlign: 'right', font: '600 12.5px/1 var(--font-mono)' }}>{fmtBR(r.com)}</td>
                          <td style={{ padding: '7px 10px', borderBottom: '1px solid var(--border-2)' }}>
                            {r.recebida
                              ? <Pill c="var(--pos)" s="color-mix(in oklch, var(--pos) 12%, var(--surface))">comissão liberada</Pill>
                              : <Pill>aguarda recebimento</Pill>}
                          </td>
                        </tr>
                      ))}</tbody>
                    </table>
                  </div>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 16, marginTop: 10 }}>
                    <div><Lbl c="var(--pos)">Já devida</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(liberada)}</b></div>
                    <div><Lbl>Ainda provisionada</Lbl><b style={{ font: '600 13.5px/1 var(--font-mono)' }}>{brl(submitSafe(soma - liberada))}</b></div>
                    <span style={{ marginLeft: 'auto', maxWidth: 340, font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Rateio proporcional ao valor de cada parcela. Parcela em atraso não libera; parcela devolvida ou estornada gera lançamento negativo.</span>
                  </div>
                </>}
            </div>
          );
        })()}

        {comeMargem && <Alert tone="warn" title="A comissão come mais da metade da margem">Total de {brl(soma)} sobre margem estimada de {brl(tot.margem)} ({num(sobreMargem, 1)}%). Em ERP maduro isso dispara alçada de aprovação, não bloqueio.</Alert>}

        {itensServico > 0 && <Alert tone="info" title={itensServico === 1 ? '1 item de serviço tem comissão própria' : itensServico + ' itens de serviço têm comissão própria'}>
          A comissão por item (funcionário vinculado + % no lançamento) é <b>somada</b> à da venda e apurada para quem executou, não para quem vendeu. Ver na coluna <b>Funcionário</b> do grid ou no detalhe do item.
        </Alert>}

        <div>
          <Lbl>Ciclo de apuração</Lbl>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, alignItems: 'center' }}>
            <Pill c="var(--accent)" s="color-mix(in oklch, var(--accent) 12%, var(--surface))">provisionada</Pill>
            <Icon name="ChevronRight" size={13} />
            <Pill>aprovada</Pill>
            <Icon name="ChevronRight" size={13} />
            <Pill>paga</Pill>
            <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>a apuração e o pagamento acontecem no Financeiro/RH; aqui só nasce a provisão.</span>
          </div>
          <p style={{ margin: '10px 0 0', font: '11.5px/1.5 var(--font-sans)', color: 'var(--text-dim)' }}>
            <b>Estorno:</b> devolução e cancelamento estornam a comissão na proporção devolvida — a provisão não some, ganha um lançamento negativo, para a apuração do mês fechar.
            <b> Snapshot:</b> a regra vigente é copiada para a venda no momento em que ela é finalizada; mudar a política de comissão depois <b>não</b> reescreve venda passada.
          </p>
        </div>
      </div>
    </Modal>
  );
}

Object.assign(window, { COM_TIPOS, comOpcoes, comPrimeira, COM_BASES, COM_GATILHOS, ComissaoResumo, ComissaoModal, comValor, comBase, comTipo, comFaixa });
