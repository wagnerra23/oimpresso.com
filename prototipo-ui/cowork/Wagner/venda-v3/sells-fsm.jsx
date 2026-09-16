/* FSM da venda na tela — ExecuteStageActionService no plano do operador (ADR 0143 · CU-SELL-20..26).
   Regras que a tela obedece:
   · o estágio só muda por AÇÃO NOMEADA (não há select de estágio);
   · ação com role que o perfil não tem NEGA (fail-secure · CU-SELL-21);
   · efeito colateral é declarado antes de executar (ReservarEstoque, ConsumirEstoque…);
   · de 'producao' em diante a venda TRAVA para edição — reabrir é outra ação nomeada;
   · toda transição escreve uma linha append-only no histórico (sale_stage_history · CU-SELL-26). */

const PRE = { key: 'rascunho', l: 'Rascunho', acao: 'Finalizar venda', role: 'vendas.criar', efeitos: [] };
const ETAPAS = () => [PRE, ...window.SD.fsm.filter((f) => f.key !== 'cancelada')];
const TRAVA_A_PARTIR_DE = ['producao', 'faturada', 'entregue', 'cancelada'];
const estagioTrava = (k) => TRAVA_A_PARTIR_DE.includes(k);

function fsmDe(k) { return k === 'rascunho' ? PRE : window.SD.fsm.find((f) => f.key === k) || PRE; }
function proximoDe(k) {
  const seq = ETAPAS().map((f) => f.key);
  const i = seq.indexOf(k);
  return i >= 0 && i < seq.length - 1 ? fsmDe(seq[i + 1]) : null;
}
function podeRole(role) { return !role || window.SD.permissoes.roles.includes(role); }

/* Barra de estágio — pipeline + ação disponível + o que a ação dispara */
/* Situação da venda — pipeline + histórico num só bloco, para a coluna do fechamento.
   Etapa cumprida mostra quem fez e quando (era o "Histórico de estágios"). */
function SituacaoVenda({ estagio, historico, onExecutar, onCancelar, onReabrir, onOpen, salvando }) {
  const meta = useMeta();
  const [aberto, setAberto] = React.useState(false);
  const atual = fsmDe(estagio);
  const prox = proximoDe(estagio);
  const seq = ETAPAS();
  const cancelada = estagio === 'cancelada';
  const ultima = historico[historico.length - 1];
  const morreuEm = cancelada && ultima ? ultima.de : null;
  const iAtual = cancelada ? seq.findIndex((x) => x.key === morreuEm) : seq.findIndex((f) => f.key === estagio);
  const permitido = podeRole(atual.role);
  const travada = estagioTrava(estagio);
  const feitoPor = (k) => historico.find((h) => h.para === k);
  const rascunho = estagio === 'rascunho' && !cancelada;
  const tom = cancelada ? 'var(--neg)' : travada ? 'var(--warn)' : 'var(--pos)';

  return (
    <div style={{ background: 'var(--surface)', border: '1px solid ' + (cancelada ? 'color-mix(in oklch, var(--neg) 30%, var(--border))' : 'var(--border)'), borderRadius: 12, boxShadow: 'var(--shadow-soft)', overflow: 'hidden' }}>
      <button type="button" onClick={() => setAberto(!aberto)} aria-expanded={aberto}
        style={{ display: 'flex', alignItems: 'center', gap: 8, width: '100%', padding: '9px 12px', border: 0, background: 'transparent', cursor: 'pointer', textAlign: 'left', color: 'var(--text)' }}>
        <span aria-hidden="true" style={{ width: 6, height: 6, borderRadius: 999, flex: 'none', background: tom }}></span>
        <span style={{ font: '600 11px/1 var(--font-sans)', letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)' }}>Situação</span>
        <b style={{ font: '600 12.5px/1 var(--font-sans)' }}>{cancelada ? 'Cancelada' : atual.l}</b>
        <span style={{ marginLeft: 'auto', display: 'inline-flex', alignItems: 'center', gap: 6, font: '11.5px/1 var(--font-sans)', color: 'var(--text-dim)' }}>
          {historico.length > 0 && <span>{iAtual + 1}/{seq.length}</span>}
          <Icon name="ChevronDown" size={14} style={{ transform: aberto ? 'rotate(180deg)' : 'none', transition: 'transform .15s' }} />
        </span>
      </button>

      {aberto && <div style={{ padding: '0 12px 10px' }}>
        <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column' }}>
          {seq.map((e, i) => {
            const morte = cancelada && i === iAtual;
            const feito = cancelada ? i < iAtual : i < iAtual;
            const agora = !cancelada && i === iAtual;
            const h = feitoPor(e.key);
            const cor = morte ? 'var(--neg)' : (feito || agora) ? 'var(--accent)' : 'var(--border)';
            return (
              <li key={e.key} style={{ display: 'flex', gap: 9, minHeight: 26 }}>
                <span aria-hidden="true" style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 'none', width: 12 }}>
                  <span style={{ width: 9, height: 9, marginTop: 5, borderRadius: 999, flex: 'none', background: (feito || agora || morte) ? cor : 'var(--surface)', border: '2px solid ' + cor, boxShadow: agora ? '0 0 0 3px color-mix(in oklch, var(--accent) 20%, transparent)' : 'none' }}></span>
                  {i < seq.length - 1 && <span style={{ flex: 1, width: 2, background: feito ? 'var(--accent)' : 'var(--border)' }}></span>}
                </span>
                <span style={{ paddingBottom: 8, minWidth: 0 }}>
                  <span style={{ display: 'block', font: (agora || morte ? '600 ' : '') + '12px/1.35 var(--font-sans)', color: morte ? tomFg('var(--neg)') : agora ? 'var(--text)' : feito ? 'var(--text-dim)' : 'var(--text-dim)', textDecoration: morte ? 'line-through' : 'none' }}>{e.l}</span>
                  {h && <span style={{ display: 'block', font: '11px/1.3 var(--font-mono)', color: 'var(--text-dim)' }}>{h.em.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })} · {h.por}</span>}
                  {morte && <span style={{ display: 'block', font: '11px/1.3 var(--font-sans)', color: tomFg('var(--neg)') }}>cancelada aqui</span>}
                </span>
              </li>
            );
          })}
        </ol>
        {meta && <div style={{ paddingTop: 2 }}><CuRow ids={['CU-SELL-20', 'CU-SELL-21', 'CU-SELL-22', 'CU-SELL-26']} onOpen={onOpen} /></div>}
      </div>}

      {!cancelada && prox && <div style={{ padding: '9px 12px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)', display: 'flex', flexDirection: 'column', gap: 8 }}>
        <div style={{ display: 'flex', flexWrap: 'wrap', alignItems: 'center', gap: 6 }}>
          <span style={{ font: '11.5px/1.35 var(--font-sans)', color: 'var(--text-dim)' }}>Próxima: <b style={{ color: 'var(--text)' }}>{atual.acao}</b></span>
          {atual.role && <Pill mono c={permitido ? 'var(--pos)' : 'var(--neg)'} s={permitido ? 'color-mix(in oklch, var(--pos) 12%, var(--surface))' : 'color-mix(in oklch, var(--neg) 12%, var(--surface))'}>{atual.role}</Pill>}
          {prox.efeitos.map((e) => <Pill key={e} mono c="var(--warn)" s="color-mix(in oklch, var(--warn) 12%, var(--surface))">{e}</Pill>)}
        </div>
        {!permitido && <span style={{ font: '11px/1.35 var(--font-sans)', color: tomFg('var(--neg)') }}>Seu perfil não tem <b>{atual.role}</b> — a ação fica negada.</span>}
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
          {travada && <Button size="sm" onClick={onReabrir}>Reabrir para correção</Button>}
          <Button size="sm" onClick={onCancelar}>{rascunho ? 'Descartar' : 'Cancelar venda'}</Button>
        </div>
      </div>}
      {cancelada && <div style={{ padding: '9px 12px', borderTop: '1px solid var(--border)', background: 'var(--bg-2)' }}>
        <span style={{ font: '11.5px/1.4 var(--font-sans)', color: 'var(--text-dim)' }}>Estoque liberado por <code>LiberarReserva</code>. Para retomar, duplique a venda.</span>
      </div>}
    </div>
  );
}

/* Confirmação de cancelamento — a cascata é nomeada antes de rodar */
function CancelarVenda({ open, onClose, onConfirmar, estagio, itens = 0 }) {
  const f = window.SD.fsm.find((x) => x.key === 'cancelada');
  const rascunho = estagio === 'rascunho';
  return (
    <Modal open={open} onClose={onClose} width={520} title={rascunho ? 'Descartar este rascunho?' : 'Cancelar esta venda?'}
      footer={<div style={{ display: 'flex', gap: 8, width: '100%' }}>
        <Button onClick={onClose}>Voltar</Button>
        <span style={{ marginLeft: 'auto' }}><Button variant="danger" onClick={onConfirmar}>{rascunho ? 'Descartar rascunho' : 'Cancelar a venda'}</Button></span>
      </div>}>
      {rascunho ? <>
        <p style={{ margin: '0 0 12px', font: '13.5px/1.5 var(--font-sans)' }}>
          {itens > 0 ? <>Os <b>{itens === 1 ? 'itens lançados' : itens + ' itens lançados'}</b> são perdidos e a tela volta em branco.</> : <>A tela volta em branco.</>}
        </p>
        <Alert tone="info" title="Nada foi gravado">Este rascunho não gerou registro de venda, não reservou estoque e não entrou em cobrança — descartar não desfaz nada no sistema, só limpa o que está na tela.</Alert>
      </> : <>
        <p style={{ margin: '0 0 12px', font: '13.5px/1.5 var(--font-sans)' }}>A venda está em <b>{fsmDe(estagio).l}</b>. Cancelar executa a cascata abaixo e a venda deixa de contar em faturamento e cobrança.</p>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 12 }}>
          {f.efeitos.map((e) => <Pill key={e} mono c="var(--neg)" s="color-mix(in oklch, var(--neg) 12%, var(--surface))">{e}</Pill>)}
        </div>
        <Alert tone="danger" title="Não tem desfazer">Reverter um cancelamento é outra ação nomeada, com role próprio — não é este botão ao contrário.</Alert>
      </>}
    </Modal>
  );
}

Object.assign(window, { SituacaoVenda, CancelarVenda, fsmDe, proximoDe, podeRole, estagioTrava, ETAPAS, PRE });
