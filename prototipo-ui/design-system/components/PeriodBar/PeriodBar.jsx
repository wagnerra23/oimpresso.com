/**
 * PeriodBar — barra de período para telas de consulta. Segmented de presets
 * (Dia / Semana / Mês) + dois campos De/Até (DatePicker PT-BR) sempre visíveis.
 * Clicar um preset preenche o intervalo; editar um campo comuta para
 * "Personalizado" (nenhum preset ativo). Pure, dependency-free (global React +
 * inline tokens); reutiliza o DatePicker do DS via namespace global.
 *
 * value ({from,to,preset}|null) · onChange(next) · presets? · label? · disabled?
 *
 * Presets default = janelas ROLANTES relativas a hoje:
 *   dia    → hoje → hoje
 *   semana → hoje-6 → hoje  (últimos 7 dias)
 *   mes    → hoje-29 → hoje (últimos 30 dias)
 */
const PB_MS_DAY = 86400000;
const pbAtMidnight = (d) => { const x = new Date(d); x.setHours(0, 0, 0, 0); return x; };
const pbShift = (d, days) => pbAtMidnight(new Date(d.getTime() + days * PB_MS_DAY));

const PB_DEFAULT_PRESETS = [
  { id: 'dia', label: 'Dia', range: () => { const t = pbAtMidnight(new Date()); return { from: t, to: t }; } },
  { id: 'semana', label: 'Semana', range: () => { const t = pbAtMidnight(new Date()); return { from: pbShift(t, -6), to: t }; } },
  { id: 'mes', label: 'Mês', range: () => { const t = pbAtMidnight(new Date()); return { from: pbShift(t, -29), to: t }; } },
];

const pbParse = (v) => { if (!v) return null; const d = v instanceof Date ? v : new Date(v); return isNaN(d) ? null : pbAtMidnight(d); };
const pbKey = (d) => d ? d.getFullYear() * 10000 + d.getMonth() * 100 + d.getDate() : 0;

export function PeriodBar({ value, onChange, presets = PB_DEFAULT_PRESETS, label = 'Período', disabled }) {
  const h = React.createElement;
  const NS = (typeof window !== 'undefined' && window.OfficeImpressoPontoWR2DesignSystem_019dd0) || {};
  const DatePicker = NS.DatePicker;

  // uncontrolled fallback
  const [internal, setInternal] = React.useState(() => value || { from: null, to: null, preset: null });
  const state = value || internal;
  const from = pbParse(state.from);
  const to = pbParse(state.to);
  const preset = state.preset || null;

  const emit = (next) => { setInternal(next); if (onChange) onChange(next); };

  const pickPreset = (p) => { if (disabled) return; const r = p.range(); emit({ from: r.from, to: r.to, preset: p.id }); };
  const setFrom = (d) => { const nf = pbParse(d); const nt = to && nf && pbKey(nf) > pbKey(to) ? nf : to; emit({ from: nf, to: nt, preset: 'custom' }); };
  const setTo = (d) => { const nt = pbParse(d); const nf = from && nt && pbKey(nt) < pbKey(from) ? nt : from; emit({ from: nf, to: nt, preset: 'custom' }); };

  const capLabel = (t) => h('label', { style: { font: '600 10.5px/1 var(--font-sans)', letterSpacing: '.06em', textTransform: 'uppercase', color: 'var(--text-mute)' } }, t);

  // ---- segmented (reusa o Segmented do DS; fallback nativo) ----
  const segBtn = (p) => {
    const active = preset === p.id;
    return h('button', {
      key: p.id, type: 'button', disabled, onClick: () => pickPreset(p), 'aria-pressed': active,
      style: {
        height: 30, padding: '0 15px', border: 0, cursor: disabled ? 'default' : 'pointer', borderRadius: 7,
        font: (active ? '600' : '500') + ' 12.5px/1 var(--font-sans)',
        background: active ? 'var(--surface)' : 'transparent',
        color: active ? 'var(--text)' : 'var(--text-dim)',
        boxShadow: active ? '0 1px 2px rgba(0,0,0,.16)' : 'none',
        transition: 'color .12s, background .12s',
      },
      onMouseEnter: (e) => { if (!active && !disabled) e.currentTarget.style.color = 'var(--text)'; },
      onMouseLeave: (e) => { if (!active) e.currentTarget.style.color = 'var(--text-dim)'; },
    }, p.label);
  };

  const Segmented = NS.Segmented;
  const segmented = h('div', { style: { display: 'flex', flexDirection: 'column', gap: 6 } },
    capLabel(label),
    Segmented
      ? h(Segmented, {
          ariaLabel: label, value: preset || '',
          onChange: (id) => { const p = presets.find((x) => x.id === id); if (p) pickPreset(p); },
          options: presets.map((p) => ({ value: p.id, label: p.label, disabled })),
        })
      : h('div', { role: 'group', 'aria-label': label, style: {
          display: 'inline-flex', gap: 2, padding: 3, height: 36, boxSizing: 'border-box',
          background: 'var(--bg-2)', border: '1px solid var(--border)', borderRadius: 9,
          boxShadow: 'inset 0 1px 2px rgba(0,0,0,.05)',
        } }, presets.map(segBtn)));

  // ---- fields (reuse DatePicker; fallback to native input) ----
  const nativeField = (lbl, val, cb) => h('div', { style: { display: 'flex', flexDirection: 'column', gap: 6 } },
    capLabel(lbl),
    h('input', {
      type: 'date', disabled,
      value: val ? val.getFullYear() + '-' + String(val.getMonth() + 1).padStart(2, '0') + '-' + String(val.getDate()).padStart(2, '0') : '',
      onChange: (e) => cb(e.target.value ? new Date(e.target.value + 'T00:00:00') : null),
      style: { height: 36, padding: '0 11px', border: '1px solid var(--border)', borderRadius: 'var(--radius, 8px)', background: 'var(--surface)', color: 'var(--text)', font: '13.5px var(--font-sans)' },
    }));

  const field = (lbl, val, cb) => DatePicker
    ? h(DatePicker, { label: lbl, value: val, onChange: cb, disabled, placeholder: 'dd/mm/aaaa' })
    : nativeField(lbl, val, cb);

  return h('div', { style: {
    display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: 14,
    font: 'var(--font-sans)', opacity: disabled ? 0.6 : 1,
  } },
    segmented,
    h('div', { style: { width: 152 } }, field('De', from, setFrom)),
    h('div', { style: { width: 152 } }, field('Até', to, setTo)));
}
