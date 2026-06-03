// @memcofre
//   tela: /showcase/norte
//   module: _DesignSystem
//   status: showcase
//   stories: North Star — fluxo do caminhão de ponta a ponta (7 cenas + costuras)
//
// Peça de visão ("Norte — Fluxo do Caminhão"). Recriação fiel do protótipo de
// design do Claude Design (handoff bundle: Norte - Fluxo do Caminhão.html +
// norte-data.jsx + norte-app.jsx). Conta a jornada de um caminhão atravessando
// OS → Aprovação → Execução → Venda → Nota → Financeiro → volta pro CRM, com a
// COSTURA (a passagem entre módulos) como herói de cada cena.
//
// Autocontida e dark por design. Os tokens vivem ESCOPADOS em `.nx-root` (espelho
// do ds-v5 dark) pra não contradizer o DS canônico (que é light por default) nem
// vazar pro resto do app. Regras consomem tudo via var() (DS-GUARD / L-23).
//
// ⚠️ Dependência de token: `--stage-*` (paleta de etapas) ainda não está no DS
// canônico do main. Mantidos escopados aqui temporariamente — trocar por
// var(--stage-*) global assim que o PR de tokens (Oficina dark/stage) entrar.

import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback, Fragment, type CSSProperties, type ReactNode } from 'react';

// ── tipos ──
type Widget = { t: string; [k: string]: unknown };

interface Scene {
  id: string;
  stage: string;
  mod: string;
  persona: [string, string];
  title: string;
  felt: string;
  screen: { bar: string; body: Widget[] };
  seam: { tag: string; from: string; to: string; h2: string; body: string; auto: [string, string] };
}

// ── dados: as 7 cenas (entrada → retirada) ──
const SCENES: Scene[] = [
  {
    id: 'recepcao', stage: 'var(--stage-slate)', mod: 'OFICINA', persona: ['Balcão · ', 'Larissa'],
    title: 'O caminhão chega ao balcão.',
    felt: 'A Larissa olha a placa. Em 2 segundos conhece o caminhão e a frota inteira — sem recadastrar nada.',
    screen: { bar: 'oimpresso · oficina / recepção', body: [
      { t: 'head', plate: 'RBA-2H78', veh: 'VW Constellation 24.280', sub: '412.500 km · Frota Boa Esperança', os: 'OS #8821 · nova' },
      { t: 'sec', v: 'Sintoma reportado' },
      { t: 'text', v: 'Perda de força e fumaça preta na subida da serra. Motorista relata cheiro de queimado.' },
      { t: 'fields', rows: [['Frota', 'Boa Esperança (8 veículos)'], ['Contato', 'Anderson · motorista'], ['Telefone', '(34) 9 9988-7766', 'mono'], ['Box', 'a alocar']] },
      { t: 'note', tone: 'accent', text: 'Cliente já no CRM — frota, contatos e histórico dos 8 caminhões vieram juntos com a placa.' },
    ] },
    seam: { tag: 'Costura · Entrada', from: 'CRM', to: 'Oficina',
      h2: 'O cliente nunca é recadastrado.',
      body: 'A Larissa digita a placa e o ERP puxa a frota inteira do CRM: contato, telefone, histórico. A OS nasce com contexto — não em branco.',
      auto: ['Zero retrabalho', 'placa → ficha completa'] },
  },
  {
    id: 'diagnostico', stage: 'var(--stage-indigo)', mod: 'OFICINA · DVI', persona: ['Oficina · ', 'Técnico'],
    title: 'O diagnóstico vira orçamento sozinho.',
    felt: 'O técnico fotografa, marca o semáforo da vistoria — e o orçamento se escreve, já com peça e serviço separados.',
    screen: { bar: 'oimpresso · oficina / diagnóstico · DVI', body: [
      { t: 'head', plate: 'RBA-2H78', veh: 'VW Constellation 24.280', sub: 'Box 2 · em diagnóstico', os: 'OS #8821' },
      { t: 'sec', v: 'Vistoria digital · DVI' },
      { t: 'dvi', rows: [
        { s: 'ok', b: 'Motor · óleo + filtro', n: 'nível ok · troca em 8.000 km', tag: ['ok', 'ok'] },
        { s: 'bad', b: 'Turbina · pressão', n: 'vazamento de pressão · troca imediata', tag: ['bad', 'crítico'] },
        { s: 'warn', b: 'Freios · lonas traseiras', n: '40% de vida · trocar agora', tag: ['warn', 'atenção'] },
        { s: 'warn', b: 'Injeção · bico 3', n: 'spray irregular · limpeza', tag: ['warn', 'atenção'] },
      ] },
      { t: 'sec', v: 'Orçamento gerado' },
      { t: 'kpis', items: [['Peças', 'R$ 4.180', 'accent'], ['Mão de obra', 'R$ 1.260', 'pos'], ['Total', 'R$ 5.440', '']] },
    ] },
    seam: { tag: 'Costura · Diagnóstico → Orçamento', from: 'DVI', to: 'Orçamento',
      h2: 'Peça e serviço já nascem separados.',
      body: 'Cada item do DVI vira linha do orçamento já classificado: peça (futura NF-e) ou serviço (NFS-e). A separação fiscal acontece aqui, no diagnóstico — não na emissão.',
      auto: ['Fiscal certo desde a origem', 'NF-e ⟂ NFS-e'] },
  },
  {
    id: 'aprovacao', stage: 'var(--stage-rose)', mod: 'INBOX', persona: ['Cliente · ', 'gestor da frota'],
    title: 'O cliente aprova pelo WhatsApp.',
    felt: 'O gestor da frota responde na conversa. A oficina nem precisou ligar — e a OS destrava sozinha.',
    screen: { bar: 'oimpresso · inbox / caixa unificada', body: [
      { t: 'note', tone: 'lock', text: 'Execução travada — aguardando o cliente aprovar o orçamento.' },
      { t: 'msg', side: 'them', text: 'Boa tarde! Orçamento do Constellation: turbina + freios + injeção = R$ 5.440. Posso liberar?', when: '14:02' },
      { t: 'msg', side: 'me', text: 'Aprovado 👍 pode tocar, preciso dele sexta.', when: '14:09' },
      { t: 'note', tone: 'pos', text: 'Cliente aprovou → a OS #8821 destravou sozinha. Gate de execução liberado.' },
    ] },
    seam: { tag: 'Costura crítica · OS ↔ Cliente', from: 'Oficina', to: 'Inbox',
      h2: 'A regra que protege a oficina.',
      body: 'O orçamento sai pelo mesmo Inbox do WhatsApp. O cliente aprova na conversa e a OS destrava sozinha — execução não começa sem o sim. A costura mais importante do negócio.',
      auto: ['O gate é a regra', 'sem aprovação, sem execução'] },
  },
  {
    id: 'execucao', stage: 'var(--stage-emerald)', mod: 'OFICINA', persona: ['Oficina · ', 'Técnico'],
    title: 'A execução e o estoque andam juntos.',
    felt: 'Box 2, turbina na bancada. Cada peça que entra no caminhão sai do estoque na mesma hora.',
    screen: { bar: 'oimpresso · oficina / execução', body: [
      { t: 'head', plate: 'RBA-2H78', veh: 'VW Constellation 24.280', sub: 'Box 2 · em execução', os: 'OS #8821' },
      { t: 'progress', pct: 65, label: '65% · resta ~2h40' },
      { t: 'sec', v: 'Peças & mão de obra aplicadas' },
      { t: 'items', rows: [
        { ic: 'peca', b: 'Turbina Garrett GT2256', q: '1 un', v: 'R$ 3.200' },
        { ic: 'peca', b: 'Kit lonas de freio traseiro', q: '1 jg', v: 'R$ 980' },
        { ic: 'serv', b: 'Mão de obra · turbina + freios', q: '6 h', v: 'R$ 1.260' },
      ] },
      { t: 'note', tone: 'accent', text: 'Cada peça aplicada baixa do estoque (Compras) na hora — e dispara reposição se bater o mínimo.' },
    ] },
    seam: { tag: 'Costura · Execução → Estoque', from: 'Oficina', to: 'Compras',
      h2: 'O estoque anda junto com a chave de fenda.',
      body: 'Quando o mecânico aplica a peça na OS, ela baixa do estoque imediatamente. O Compras já sabe que o turbo saiu — sem inventário paralelo, sem conferência de fim de dia.',
      auto: ['Estoque sempre real', 'aplicou = baixou'] },
  },
  {
    id: 'venda', stage: 'var(--stage-green)', mod: 'VENDAS', persona: ['Sistema · ', 'Jana'],
    title: 'Pronto. E a venda já está lá.',
    felt: 'O caminhão fica pronto — e a venda aparece sozinha, com tudo da OS. O balcão só confere e fatura.',
    screen: { bar: 'oimpresso · vendas', body: [
      { t: 'note', tone: 'accent', text: '✨ OS #8821 chegou em \'Pronto p/ retirar\' → venda criada automaticamente.' },
      { t: 'head', plate: 'RBA-2H78', veh: 'VW Constellation 24.280', sub: 'Frota Boa Esperança', os: 'Venda #4471 · origin: oficina' },
      { t: 'kpis', items: [['Peças (NF-e)', 'R$ 4.180', 'accent'], ['Serviço (NFS-e)', 'R$ 1.260', 'pos'], ['Total', 'R$ 5.440', '']] },
      { t: 'note', tone: 'pos', text: 'Itens, valores, cliente e separação fiscal vieram da OS. Ninguém digitou a venda.' },
    ] },
    seam: { tag: 'Costura · OS → Vendas', from: 'Oficina', to: 'Vendas',
      h2: 'Ninguém digita a venda duas vezes.',
      body: 'OS pronta = venda criada. Peças, mão de obra, cliente e a classificação fiscal já vêm da OS. A \'digitação dupla\' clássica do ERP simplesmente não existe.',
      auto: ['Venda automática', 'OS pronta → venda pronta'] },
  },
  {
    id: 'nota', stage: 'var(--accent)', mod: 'FISCAL', persona: ['Financeiro · ', 'Eliana'],
    title: 'Duas notas certas, num clique.',
    felt: 'A Eliana clica emitir. Peça vira NF-e, serviço vira NFS-e — o ERP já sabia o que era o quê.',
    screen: { bar: 'oimpresso · fiscal', body: [
      { t: 'head', plate: 'RBA-2H78', veh: 'VW Constellation 24.280', sub: 'Frota Boa Esperança', os: 'Venda #4471' },
      { t: 'sec', v: 'Notas emitidas' },
      { t: 'fiscal', rows: [
        { tipo: 'NF-e modelo 55 · peças', num: 'Nº 001.214 · R$ 4.180,00' },
        { tipo: 'NFS-e · mão de obra', num: 'Nº 000.087 · R$ 1.260,00' },
      ] },
      { t: 'note', tone: 'pos', text: 'Uma venda, duas notas certas — porque a separação fiscal veio lá do DVI, no diagnóstico.' },
    ] },
    seam: { tag: 'Costura · Vendas → Fiscal', from: 'Vendas', to: 'Fiscal',
      h2: 'Duas notas, zero decisão na hora.',
      body: 'Peça emite NF-e 55, serviço emite NFS-e — e o ERP já sabia o que era o quê desde o DVI. A emissão é um clique, não uma planilha de classificação fiscal.',
      auto: ['SEFAZ num clique', 'classificação herdada do DVI'] },
  },
  {
    id: 'financeiro', stage: 'var(--stage-green)', mod: 'FINANCEIRO', persona: ['Financeiro · ', 'Eliana + Balcão'],
    title: 'O ciclo volta pro começo.',
    felt: 'PIX caiu, caixa baixou. E a frota já ganha data pra voltar — que vira o próximo check-in.',
    screen: { bar: 'oimpresso · financeiro / caixa', body: [
      { t: 'head', plate: 'RBA-2H78', veh: 'VW Constellation 24.280', sub: 'Frota Boa Esperança', os: 'Venda #4471 · paga' },
      { t: 'kpis', items: [['Recebido', 'R$ 5.440', 'pos'], ['Forma', 'PIX', ''], ['Margem', '38%', 'pos']] },
      { t: 'sec', v: 'E o ciclo volta pro CRM' },
      { t: 'note', tone: 'pos', text: 'Frota Boa Esperança: +1 serviço no histórico do Constellation. Próxima revisão agendada p/ 432.000 km.' },
    ] },
    seam: { tag: 'Costura de fechamento · Financeiro → CRM', from: 'Financeiro', to: 'CRM',
      h2: 'O fim alimenta o começo.',
      body: 'Pago e baixado no caixa, o serviço entra no histórico da frota no CRM. E o ERP já agenda a próxima revisão — que vira o próximo check-in. O fluxo é um círculo, não uma linha.',
      auto: ['O ciclo se fecha', 'financeiro → CRM → próxima OS'] },
  },
];

// rótulos curtos pro spine
const SPINE = ['Recepção', 'Diagnóstico', 'Aprovação', 'Execução', 'Venda', 'Nota', 'Financeiro'];

// ── ícones ──
const Arrow = ({ s = 18 }: { s?: number }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>;
const Bolt = ({ s = 15 }: { s?: number }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M13 2L4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z" /></svg>;
const Doc = ({ s = 15 }: { s?: number }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z" /><path d="M14 3v6h6" /><path d="M9 14l2 2 4-4" /></svg>;
const Lock = ({ s = 15 }: { s?: number }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" /></svg>;
const Check = ({ s = 13 }: { s?: number }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M5 13l4 4 10-10" /></svg>;

// ── widget renderer ──
function W({ w }: { w: Widget }): ReactNode {
  switch (w.t) {
    case 'head': return (
      <div className="w-head">
        <div className="w-plate"><div className="pt">BR · MERCOSUL</div><div className="pn">{w.plate as string}</div></div>
        <div className="w-head-meta"><b>{w.veh as string}</b><small>{w.sub as string}</small></div>
        <span className="w-os">{w.os as string}</span>
      </div>
    );
    case 'sec': return <div className="w-sec">{w.v as string}</div>;
    case 'text': return <p style={{ margin: '0 0 6px', fontSize: 13, lineHeight: 1.5, color: 'var(--text-2)' }}>{w.v as string}</p>;
    case 'fields': return (
      <dl className="w-fields">{(w.rows as string[][]).map((r, i) => <Fragment key={i}><dt>{r[0]}</dt><dd className={r[2] || ''}>{r[1]}</dd></Fragment>)}</dl>
    );
    case 'dvi': return (
      <div className="w-dvi">{(w.rows as { s: string; b: string; n: string; tag: [string, string] }[]).map((r, i) => (
        <div key={i} className={'w-dvi-row ' + r.s}><span className="w-dvi-dot" /><div><b>{r.b}</b><small>{r.n}</small></div><span className={'w-tag ' + r.tag[0]}>{r.tag[1]}</span></div>
      ))}</div>
    );
    case 'kpis': return (
      <div className="w-kpis">{(w.items as string[][]).map((k, i) => <div key={i} className={'w-kpi ' + (k[2] || '')}><small>{k[0]}</small><b>{k[1]}</b></div>)}</div>
    );
    case 'items': return (
      <div className="w-items">{(w.rows as { ic: string; b: string; q: string; v: string }[]).map((r, i) => (
        <div key={i} className="w-item"><span className={'w-item-ic ' + r.ic}>{r.ic === 'serv' ? '🔧' : '⬡'}</span><b>{r.b}</b><span className="q">{r.q}</span><span className="v">{r.v}</span></div>
      ))}</div>
    );
    case 'msg': return (
      <div className={'w-msg ' + (w.side as string)}><div className="w-bub">{w.text as string}<small>{w.when as string}{w.side === 'me' ? ' ✓✓' : ''}</small></div></div>
    );
    case 'fiscal': return (
      <div className="w-fiscal">{(w.rows as { tipo: string; num: string }[]).map((r, i) => (
        <div key={i} className="w-fiscal-row"><span className="w-fiscal-ic"><Doc /></span><div><b>{r.tipo}</b><small>{r.num}</small></div><span className="w-fiscal-ok"><Check /> autorizada</span></div>
      ))}</div>
    );
    case 'progress': return (
      <div style={{ margin: '2px 0 4px' }}>
        <div style={{ height: 8, borderRadius: 6, background: 'var(--sunken)', overflow: 'hidden', border: '1px solid var(--border-2)' }}>
          <div style={{ height: '100%', width: (w.pct as number) + '%', borderRadius: 6, background: 'linear-gradient(90deg, var(--stage-emerald), var(--stage-green))' }} />
        </div>
        <div style={{ fontSize: 11, color: 'var(--text-3)', marginTop: 6, fontFamily: 'var(--mono)' }}>{w.label as string}</div>
      </div>
    );
    case 'note': {
      const tone = w.tone as string;
      const ic = tone === 'lock' ? <Lock s={16} /> : tone === 'pos' ? <Check s={15} /> : <Bolt s={15} />;
      const cls = tone === 'lock' ? 'w-note' : 'w-note ' + tone;
      const style: CSSProperties | undefined = tone === 'lock' ? { background: 'var(--neg-soft)', border: '1px solid color-mix(in oklch,var(--neg) 40%,var(--border))' } : undefined;
      const icColor = tone === 'lock' ? 'var(--neg)' : tone === 'pos' ? 'var(--pos)' : 'var(--accent)';
      return <div className={cls} style={style}><span style={{ color: icColor, flex: '0 0 auto', marginTop: 1 }}>{ic}</span><span>{w.text as string}</span></div>;
    }
    default: return null;
  }
}

export default function Norte() {
  const [i, setI] = useState<number>(() => {
    const s = parseInt(localStorage.getItem('norte.step') || '0', 10);
    return isNaN(s) ? 0 : Math.max(0, Math.min(SCENES.length - 1, s));
  });
  useEffect(() => { localStorage.setItem('norte.step', String(i)); }, [i]);

  const go = useCallback((n: number) => setI(() => Math.max(0, Math.min(SCENES.length - 1, n))), []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => { if (e.key === 'ArrowRight') go(i + 1); else if (e.key === 'ArrowLeft') go(i - 1); };
    window.addEventListener('keydown', k);
    return () => window.removeEventListener('keydown', k);
  }, [i, go]);

  const sc = SCENES[i];

  return (
    <>
      <Head title="Norte — Fluxo do Caminhão">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet" />
      </Head>

      <style>{NORTE_CSS}</style>

      <div className="nx-root" data-theme="dark">
        <div className="nx-app">
          <div className="nx-top">
            <div className="nx-brand">
              <div className="nx-logo">Oi</div>
              <div><b>Oimpresso ERP</b><small>Norte — o caminhão de ponta a ponta</small></div>
            </div>
            <div className="nx-kicker">Fluxo &gt; Módulo</div>
          </div>

          <div className="nx-spine">
            {SCENES.map((s, idx) => (
              <button key={s.id} className={'nx-node' + (idx === i ? ' active' : '') + (idx < i ? ' done' : '')}
                style={{ '--stage': s.stage } as CSSProperties} onClick={() => go(idx)}>
                <span className={'nx-node-line' + (idx < i ? ' done' : '')} />
                <span className="nx-dot" />
                <span className="nx-node-n">{String(idx + 1).padStart(2, '0')}</span>
                <span className="nx-node-lbl">{SPINE[idx]}</span>
              </button>
            ))}
          </div>

          <div className="nx-stage" style={{ '--stage': sc.stage } as CSSProperties}>
            <div className="nx-scene" key={sc.id}>
              <div className="nx-scene-head">
                <div className="nx-eyebrow">
                  <span className="nx-mod">{sc.mod}</span>
                  <span className="nx-persona">{sc.persona[0]}<b>{sc.persona[1]}</b></span>
                </div>
                <h1>{sc.title}</h1>
                <p className="nx-felt">{sc.felt}</p>
              </div>
              <div className="nx-screen">
                <div className="nx-screen-bar"><span className="nx-tl" /><span className="nx-tl" /><span className="nx-tl" /><span>{sc.screen.bar}</span></div>
                <div className="nx-screen-body">{sc.screen.body.map((w, k) => <W key={k} w={w} />)}</div>
              </div>
            </div>

            <div className="nx-seam" key={sc.id + '-seam'}>
              <div className="nx-seam-card">
                <span className="nx-seam-tag"><Bolt s={12} />{sc.seam.tag}</span>
                <div className="nx-seam-flow">
                  <span className="nx-seam-chip">{sc.seam.from}</span>
                  <span className="nx-seam-arrow"><Arrow s={20} /></span>
                  <span className="nx-seam-chip">{sc.seam.to}</span>
                </div>
                <h2>{sc.seam.h2}</h2>
                <p>{sc.seam.body}</p>
                <div className="nx-seam-auto">
                  <span className="nx-seam-auto-ic"><Bolt s={15} /></span>
                  <div><b>{sc.seam.auto[0]}</b><small>{sc.seam.auto[1]}</small></div>
                </div>
              </div>
            </div>
          </div>

          <div className="nx-foot">
            <button className="nx-btn" onClick={() => go(i - 1)} disabled={i === 0}><span style={{ display: 'inline-flex', transform: 'scaleX(-1)' }}><Arrow s={16} /></span>Anterior</button>
            <span className="nx-count">{String(i + 1).padStart(2, '0')} / {String(SCENES.length).padStart(2, '0')}</span>
            <div className="nx-progress"><div className="nx-progress-fill" style={{ width: ((i + 1) / SCENES.length * 100) + '%' }} /></div>
            <span className="nx-hint">navegue <kbd>←</kbd> <kbd>→</kbd></span>
            {i < SCENES.length - 1
              ? <button className="nx-btn primary" onClick={() => go(i + 1)}>Próxima costura <Arrow s={16} /></button>
              : <button className="nx-btn primary" onClick={() => go(0)}>Recomeçar o ciclo <Arrow s={16} /></button>}
          </div>
        </div>
      </div>
    </>
  );
}

// ── CSS escopado em .nx-root (espelho ds-v5 dark · DS-GUARD: cor só via token) ──
const NORTE_CSS = `
.nx-root{
  /* DS v5 — dark (espelho dos tokens canônicos + paleta de etapas temperada).
     Escopado pra não contradizer o DS canônico (light default) nem vazar global.
     TODO: trocar --stage-* por var(--stage-*) global quando o PR de tokens entrar. */
  --bg:        oklch(0.165 0.008 282);
  --sunken:    oklch(0.142 0.008 282);
  --surface:   oklch(0.205 0.009 282);
  --raised:    oklch(0.235 0.010 282);
  --border:    oklch(0.30 0.012 282);
  --border-2:  oklch(0.25 0.010 282);
  --text:      oklch(0.965 0.004 282);
  --text-2:    oklch(0.74 0.008 282);
  --text-3:    oklch(0.58 0.009 282);
  --text-4:    oklch(0.47 0.009 282);

  --accent:      oklch(0.72 0.15 295);
  --accent-soft: oklch(0.30 0.07 295);
  --accent-line: oklch(0.42 0.10 295);

  --pos:  oklch(0.74 0.14 150); --pos-soft:  oklch(0.30 0.085 150);
  --neg:  oklch(0.72 0.16 25);  --neg-soft:  oklch(0.32 0.09 25);
  --warn: oklch(0.80 0.13 75);  --warn-soft: oklch(0.32 0.085 70);

  /* paleta de etapas (tempero desta sessão) */
  --stage-slate:   oklch(0.66 0.03 250);
  --stage-indigo:  oklch(0.66 0.16 270);
  --stage-rose:    oklch(0.68 0.16 20);
  --stage-emerald: oklch(0.68 0.14 155);
  --stage-green:   oklch(0.74 0.15 145);

  --sans: "IBM Plex Sans", ui-sans-serif, system-ui, sans-serif;
  --mono: "IBM Plex Mono", ui-monospace, Menlo, monospace;
  --r: 14px; --r-sm: 9px;
  --sh: 0 18px 50px -18px rgba(0,0,0,.6), 0 4px 14px -6px rgba(0,0,0,.4);

  position: fixed; inset: 0; z-index: 1;
  background:
    radial-gradient(1100px 600px at 82% -8%, oklch(0.26 0.07 295 / .28), transparent 60%),
    radial-gradient(900px 520px at 8% 108%, oklch(0.24 0.05 250 / .22), transparent 62%),
    var(--bg);
  color: var(--text);
  font-family: var(--sans);
  -webkit-font-smoothing: antialiased;
  overflow: hidden;
}
.nx-root *{ box-sizing: border-box; }
.nx-app{ height:100vh; display:flex; flex-direction:column; }

/* ── topbar ── */
.nx-root .nx-top{ display:flex; align-items:center; gap:14px; padding:18px 30px 14px; }
.nx-root .nx-brand{ display:flex; align-items:center; gap:10px; }
.nx-root .nx-logo{ width:30px; height:30px; border-radius:8px; background:linear-gradient(135deg,var(--accent),oklch(0.55 0.15 295)); display:grid; place-items:center; color:#fff; font-weight:700; font-size:13px; box-shadow:0 0 0 1px var(--accent-line), 0 6px 18px -6px oklch(0.55 0.15 295 / .6); }
.nx-root .nx-brand b{ font-size:14.5px; font-weight:600; letter-spacing:-0.01em; }
.nx-root .nx-brand small{ display:block; font-size:11px; color:var(--text-3); margin-top:1px; }
.nx-root .nx-kicker{ margin-left:auto; font-size:11px; letter-spacing:.14em; text-transform:uppercase; color:var(--text-4); font-weight:600; }

/* ── spine (hero) ── */
.nx-root .nx-spine{ display:flex; align-items:flex-start; gap:0; padding:6px 30px 4px; }
.nx-root .nx-node{ flex:1 1 0; min-width:0; background:none; border:0; cursor:pointer; color:inherit; font:inherit; padding:8px 4px 0; position:relative; display:flex; flex-direction:column; align-items:center; gap:8px; }
.nx-root .nx-node-line{ position:absolute; top:15px; left:50%; right:-50%; height:2px; background:var(--border); z-index:0; }
.nx-root .nx-node:last-child .nx-node-line{ display:none; }
.nx-root .nx-node-line.done{ background:var(--stage); }
.nx-root .nx-dot{ position:relative; z-index:1; width:14px; height:14px; border-radius:50%; background:var(--sunken); border:2px solid var(--border); transition:all .3s cubic-bezier(.22,1,.36,1); }
.nx-root .nx-node.done .nx-dot{ background:color-mix(in oklch, var(--stage) 62%, var(--bg)); border-color:color-mix(in oklch, var(--stage) 62%, var(--bg)); }
.nx-root .nx-node.active .nx-dot{ background:var(--stage); border-color:var(--stage); box-shadow:0 0 0 5px color-mix(in oklch, var(--stage) 22%, transparent); transform:scale(1.18); }
.nx-root .nx-node-lbl{ font-size:10.5px; color:var(--text-4); text-align:center; line-height:1.25; transition:color .2s; font-weight:500; }
.nx-root .nx-node.active .nx-node-lbl{ color:var(--text); font-weight:600; }
.nx-root .nx-node.done .nx-node-lbl{ color:var(--text-3); }
.nx-root .nx-node-n{ font-family:var(--mono); font-size:9px; color:var(--text-4); }
.nx-root .nx-node.active .nx-node-n{ color:var(--stage); }

/* ── stage area ── */
.nx-root .nx-stage{ flex:1 1 auto; min-height:0; display:grid; grid-template-columns: 1.35fr 1fr; gap:22px; padding:14px 30px 26px; }
.nx-root .nx-scene{ min-width:0; min-height:0; display:flex; flex-direction:column; }
@media (prefers-reduced-motion: no-preference){
  .nx-root .nx-scene{ animation:nxIn .5s cubic-bezier(.22,1,.36,1); }
}
@keyframes nxIn{ from{ transform:translateY(14px); } to{ transform:translateY(0); } }

.nx-root .nx-scene-head{ margin-bottom:12px; }
.nx-root .nx-eyebrow{ display:flex; align-items:center; gap:9px; font-size:11px; }
.nx-root .nx-mod{ font-family:var(--mono); font-weight:600; letter-spacing:.04em; padding:2px 8px; border-radius:5px; background:color-mix(in oklch, var(--stage) 22%, var(--surface)); color:var(--stage); }
.nx-root .nx-persona{ color:var(--text-3); }
.nx-root .nx-persona b{ color:var(--text-2); font-weight:600; }
.nx-root .nx-scene-head h1{ margin:9px 0 0; font-size:25px; font-weight:600; letter-spacing:-0.025em; line-height:1.1; max-width:18ch; text-wrap:balance; }
.nx-root .nx-felt{ margin:9px 0 0; font-size:13px; color:var(--text-3); line-height:1.5; max-width:46ch; }

/* mock screen */
.nx-root .nx-screen{ flex:1 1 auto; min-height:0; overflow:hidden; background:var(--surface); border:1px solid var(--border); border-radius:var(--r); box-shadow:var(--sh); display:flex; flex-direction:column; }
.nx-root .nx-screen-bar{ height:34px; display:flex; align-items:center; gap:7px; padding:0 13px; border-bottom:1px solid var(--border-2); background:var(--sunken); flex:0 0 auto; }
.nx-root .nx-tl{ width:9px; height:9px; border-radius:50%; background:var(--border); }
.nx-root .nx-screen-bar span{ margin-left:8px; font-size:10.5px; color:var(--text-4); font-family:var(--mono); }
.nx-root .nx-screen-body{ flex:1 1 auto; min-height:0; overflow-y:auto; padding:16px 18px; scrollbar-width:thin; }
.nx-root .nx-screen-body::-webkit-scrollbar{ width:7px; } .nx-root .nx-screen-body::-webkit-scrollbar-thumb{ background:var(--border); border-radius:4px; }

/* widgets */
.nx-root .w-head{ display:flex; align-items:center; gap:12px; margin-bottom:14px; }
.nx-root .w-plate{ flex:0 0 auto; width:96px; border:1.5px solid var(--text-3); border-radius:6px; overflow:hidden; background:var(--surface); }
.nx-root .w-plate .pt{ background:oklch(0.42 0.13 250); color:#fff; font-size:7px; text-align:center; padding:1px; letter-spacing:.05em; font-weight:600; }
.nx-root .w-plate .pn{ font-family:var(--mono); font-weight:700; font-size:15px; text-align:center; padding:3px 0; letter-spacing:.04em; }
.nx-root .w-head-meta b{ display:block; font-size:15px; font-weight:600; letter-spacing:-0.01em; }
.nx-root .w-head-meta small{ display:block; font-size:11.5px; color:var(--text-3); margin-top:2px; }
.nx-root .w-os{ margin-left:auto; font-family:var(--mono); font-size:11px; color:var(--text-4); align-self:flex-start; }

.nx-root .w-fields{ display:grid; grid-template-columns:auto 1fr; gap:7px 14px; font-size:12.5px; margin-bottom:14px; }
.nx-root .w-fields dt{ color:var(--text-4); } .nx-root .w-fields dd{ margin:0; color:var(--text-2); text-align:right; }
.nx-root .w-fields dd.mono{ font-family:var(--mono); }

.nx-root .w-sec{ font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--text-4); margin:16px 0 9px; }
.nx-root .w-sec:first-child{ margin-top:0; }

.nx-root .w-dvi{ display:flex; flex-direction:column; gap:6px; }
.nx-root .w-dvi-row{ display:grid; grid-template-columns:14px 1fr auto; gap:10px; align-items:center; padding:8px 11px; border-radius:8px; background:var(--sunken); border:1px solid var(--border-2); }
.nx-root .w-dvi-dot{ width:9px; height:9px; border-radius:50%; }
.nx-root .w-dvi-row.ok .w-dvi-dot{ background:var(--pos); } .nx-root .w-dvi-row.warn .w-dvi-dot{ background:var(--warn); } .nx-root .w-dvi-row.bad .w-dvi-dot{ background:var(--neg); }
.nx-root .w-dvi-row.bad{ border-color:color-mix(in oklch,var(--neg) 40%,var(--border)); background:var(--neg-soft); }
.nx-root .w-dvi-row b{ font-size:12.5px; font-weight:600; } .nx-root .w-dvi-row small{ display:block; font-size:10.5px; color:var(--text-3); margin-top:1px; }
.nx-root .w-tag{ font-size:10px; font-weight:600; padding:2px 7px; border-radius:999px; font-family:var(--mono); }
.nx-root .w-tag.ok{ background:var(--pos-soft); color:var(--pos); } .nx-root .w-tag.warn{ background:var(--warn-soft); color:var(--warn); } .nx-root .w-tag.bad{ background:var(--neg-soft); color:var(--neg); }

.nx-root .w-kpis{ display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:6px; }
.nx-root .w-kpi{ background:var(--sunken); border:1px solid var(--border-2); border-radius:9px; padding:11px 12px; }
.nx-root .w-kpi small{ display:block; font-size:9.5px; letter-spacing:.06em; text-transform:uppercase; color:var(--text-4); }
.nx-root .w-kpi b{ display:block; font-size:19px; font-weight:600; font-family:var(--mono); margin-top:5px; letter-spacing:-0.01em; }
.nx-root .w-kpi.pos b{ color:var(--pos); } .nx-root .w-kpi.accent b{ color:var(--accent); } .nx-root .w-kpi.neg b{ color:var(--neg); }

.nx-root .w-items{ display:flex; flex-direction:column; gap:5px; }
.nx-root .w-item{ display:flex; align-items:center; gap:10px; padding:8px 10px; border-radius:8px; background:var(--sunken); border:1px solid var(--border-2); font-size:12px; }
.nx-root .w-item-ic{ width:24px; height:24px; border-radius:6px; display:grid; place-items:center; flex:0 0 auto; font-size:11px; }
.nx-root .w-item-ic.peca{ background:var(--accent-soft); color:var(--accent); } .nx-root .w-item-ic.serv{ background:var(--pos-soft); color:var(--pos); }
.nx-root .w-item b{ font-weight:600; } .nx-root .w-item .q{ color:var(--text-4); font-family:var(--mono); font-size:10.5px; margin-left:auto; }
.nx-root .w-item .v{ font-family:var(--mono); font-weight:600; min-width:74px; text-align:right; }

.nx-root .w-msg{ display:flex; margin:7px 0; }
.nx-root .w-msg.them{ justify-content:flex-start; } .nx-root .w-msg.me{ justify-content:flex-end; }
.nx-root .w-bub{ max-width:78%; padding:9px 12px; border-radius:14px; font-size:12.5px; line-height:1.4; }
.nx-root .w-msg.them .w-bub{ background:var(--raised); border-top-left-radius:4px; }
.nx-root .w-msg.me .w-bub{ background:var(--accent); color:oklch(0.16 0.02 295); font-weight:500; border-top-right-radius:4px; }
.nx-root .w-bub small{ display:block; font-size:9.5px; opacity:.7; margin-top:3px; text-align:right; }

.nx-root .w-fiscal{ display:flex; flex-direction:column; gap:8px; }
.nx-root .w-fiscal-row{ display:flex; align-items:center; gap:11px; padding:11px 13px; border-radius:9px; background:var(--sunken); border:1px solid var(--border-2); }
.nx-root .w-fiscal-ic{ width:30px; height:30px; border-radius:7px; background:var(--pos-soft); color:var(--pos); display:grid; place-items:center; flex:0 0 auto; }
.nx-root .w-fiscal-row b{ display:block; font-size:12.5px; } .nx-root .w-fiscal-row small{ display:block; font-size:11px; color:var(--text-3); font-family:var(--mono); margin-top:1px; }
.nx-root .w-fiscal-ok{ margin-left:auto; font-size:11px; color:var(--pos); font-weight:600; display:inline-flex; align-items:center; gap:4px; }

.nx-root .w-note{ display:flex; gap:9px; padding:11px 13px; border-radius:10px; font-size:12px; line-height:1.45; margin-top:6px; }
.nx-root .w-note.accent{ background:var(--accent-soft); border:1px solid var(--accent-line); }
.nx-root .w-note.pos{ background:var(--pos-soft); border:1px solid color-mix(in oklch,var(--pos) 40%,var(--border)); }
.nx-root .w-note b{ color:var(--text); }
.nx-root .w-bigtotal{ display:flex; align-items:baseline; justify-content:space-between; padding:13px 4px 2px; border-top:1px solid var(--border-2); margin-top:10px; }
.nx-root .w-bigtotal span{ font-size:12px; color:var(--text-3); } .nx-root .w-bigtotal b{ font-size:23px; font-weight:600; font-family:var(--mono); letter-spacing:-0.02em; }

/* ── costura (right rail) ── */
.nx-root .nx-seam{ min-width:0; display:flex; flex-direction:column; }
.nx-root .nx-seam-card{ flex:1 1 auto; background:linear-gradient(180deg, color-mix(in oklch,var(--stage) 12%,var(--surface)), var(--surface)); border:1px solid color-mix(in oklch,var(--stage) 30%,var(--border)); border-radius:var(--r); padding:20px; display:flex; flex-direction:column; box-shadow:var(--sh); }
.nx-root .nx-seam-tag{ display:inline-flex; align-items:center; gap:7px; align-self:flex-start; font-size:10px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--stage); background:color-mix(in oklch,var(--stage) 18%,transparent); padding:4px 10px; border-radius:999px; }
.nx-root .nx-seam-flow{ display:flex; align-items:center; gap:9px; margin:18px 0 4px; }
.nx-root .nx-seam-chip{ font-size:11.5px; font-weight:600; padding:6px 11px; border-radius:8px; background:var(--sunken); border:1px solid var(--border-2); color:var(--text-2); }
.nx-root .nx-seam-arrow{ color:var(--stage); flex:0 0 auto; }
.nx-root .nx-seam-card h2{ margin:18px 0 0; font-size:18px; font-weight:600; letter-spacing:-0.02em; line-height:1.25; text-wrap:balance; }
.nx-root .nx-seam-card p{ margin:11px 0 0; font-size:13px; line-height:1.6; color:var(--text-2); }
.nx-root .nx-seam-auto{ margin-top:auto; display:flex; align-items:center; gap:9px; padding-top:18px; }
.nx-root .nx-seam-auto-ic{ width:30px; height:30px; border-radius:8px; background:color-mix(in oklch,var(--stage) 22%,transparent); color:var(--stage); display:grid; place-items:center; flex:0 0 auto; }
.nx-root .nx-seam-auto b{ font-size:12px; color:var(--text); display:block; }
.nx-root .nx-seam-auto small{ font-size:11px; color:var(--text-3); }

/* ── footer nav ── */
.nx-root .nx-foot{ display:flex; align-items:center; gap:14px; padding:0 30px 22px; }
.nx-root .nx-btn{ appearance:none; cursor:pointer; font:inherit; display:inline-flex; align-items:center; gap:8px; height:40px; padding:0 18px; border-radius:10px; border:1px solid var(--border); background:var(--surface); color:var(--text); font-size:13px; font-weight:500; transition:all .15s; }
.nx-root .nx-btn:hover{ background:var(--raised); border-color:var(--text-4); }
.nx-root .nx-btn:disabled{ opacity:.4; cursor:not-allowed; }
.nx-root .nx-btn.primary{ background:var(--accent); border-color:var(--accent); color:oklch(0.16 0.02 295); font-weight:600; }
.nx-root .nx-btn.primary:hover{ background:oklch(0.78 0.15 295); }
.nx-root .nx-count{ font-family:var(--mono); font-size:12px; color:var(--text-4); }
.nx-root .nx-progress{ flex:1 1 auto; height:3px; border-radius:2px; background:var(--border-2); overflow:hidden; }
.nx-root .nx-progress-fill{ height:100%; background:linear-gradient(90deg,var(--accent),var(--stage-green)); border-radius:2px; transition:width .4s cubic-bezier(.22,1,.36,1); }
.nx-root .nx-hint{ font-size:11px; color:var(--text-4); white-space:nowrap; } .nx-root .nx-hint kbd{ font-family:var(--mono); background:var(--sunken); border:1px solid var(--border); border-radius:4px; padding:1px 5px; font-size:10px; }

@media (max-width:1080px){ .nx-root .nx-stage{ grid-template-columns:1fr; } .nx-root .nx-seam{ display:none; } .nx-root .nx-node-lbl{ display:none; } }
`;
