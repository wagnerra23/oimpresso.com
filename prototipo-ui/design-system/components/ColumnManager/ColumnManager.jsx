/**
 * ColumnManager — escolher e reordenar as colunas de uma grade (DS).
 *
 * Origem: `prototipo-ui/cowork/venda-v3/sells-colunas.jsx` (layout de duas listas,
 * arrastar + ↑↓, grupos, coluna fixa) fundido com o porte de produção
 * `resources/js/Pages/Sells/_components/v3/colunas-dominio.ts` (saneamento defensivo
 * do localStorage, regra de coluna fixa no mover/alternar).
 *
 * O que veio: o MECANISMO. O catálogo de colunas é domínio da tela — quem usa passa
 * `columns`/`groups`. Nada de NCM/CFOP/m² aqui dentro.
 *
 * Arrastar existe, mas nunca sozinho: cada linha tem ↑ ↓ operáveis por teclado e
 * leitor de tela (o porte de produção tinha removido o drag inteiro por causa disso).
 *
 * Puro, sem dependências. Controlado: active + onChange(next).
 */

const CM_FONT = 'var(--font-sans)';

/** Helpers puros da preferência de coluna. Expostos no namespace como ColumnPrefs. */
export const ColumnPrefs = {
  /** Configuração de quem nunca mexeu: fixas + marcadas como default, na ordem do catálogo. */
  defaults(columns) {
    return columns.filter((c) => c.fixed || c.default).map((c) => c.key);
  },
  find(columns, key) {
    return columns.find((c) => c.key === key);
  },
  /**
   * Sanea lista vinda de fora (localStorage, prop, URL). Quatro defesas, cada uma
   * um jeito real de o dado chegar podre: não-array, chave que não existe mais,
   * chave repetida, coluna fixa ausente.
   */
  sanitize(columns, input) {
    if (!Array.isArray(input)) return ColumnPrefs.defaults(columns);
    const seen = new Set();
    const valid = input.filter((k) => {
      if (typeof k !== 'string' || seen.has(k) || !ColumnPrefs.find(columns, k)) return false;
      seen.add(k);
      return true;
    });
    if (!valid.length) return ColumnPrefs.defaults(columns);
    const missing = columns.filter((c) => c.fixed && !seen.has(c.key)).map((c) => c.key);
    return [...missing, ...valid];
  },
  /**
   * Move uma coluna. Coluna fixa é ÂNCORA: ela mesma não se move e não muda de
   * índice, mas as outras passam por cima dela em vez de esbarrar. A regra antiga
   * (recusar destino fixo) criava beco sem saída — com `total` fixa e última,
   * toda coluna nova ficava presa embaixo dela sem nenhum destino possível.
   * Índice fora da lista, ou movimento sem efeito, devolve a lista intacta.
   */
  move(columns, active, from, to) {
    const fixed = (k) => !!ColumnPrefs.find(columns, k)?.fixed;
    if (from === to || from < 0 || from >= active.length || to < 0 || to >= active.length) return active;
    if (fixed(active[from])) return active;
    const dir = to > from ? 1 : -1;
    let dest = to;
    while (dest >= 0 && dest < active.length && fixed(active[dest])) dest += dir;
    if (dest < 0 || dest >= active.length) return active;
    const free = active.map((k, i) => ({ k, i })).filter((x) => !fixed(x.k));
    const fi = free.findIndex((x) => x.i === from);
    const ti = free.findIndex((x) => x.i === dest);
    if (fi < 0 || ti < 0 || fi === ti) return active;
    const order = free.map((x) => x.k);
    order.splice(ti, 0, order.splice(fi, 1)[0]);
    const out = active.slice();
    let n = 0;
    for (let i = 0; i < out.length; i++) if (!fixed(out[i])) out[i] = order[n++];
    return out;
  },
  /** Liga/desliga uma coluna. Fixa não desliga — é o que impede a grade ficar sem total. */
  toggle(columns, active, key) {
    const def = ColumnPrefs.find(columns, key);
    if (!def) return active;
    if (active.includes(key)) return def.fixed ? active : active.filter((x) => x !== key);
    return [...active, key];
  },
  /** Lê a preferência salva. Nunca lança: localStorage pode estar bloqueado ou com lixo. */
  load(columns, storageKey, storage) {
    try {
      const raw = (storage || window.localStorage).getItem(storageKey);
      if (!raw) return ColumnPrefs.defaults(columns);
      return ColumnPrefs.sanitize(columns, JSON.parse(raw));
    } catch (e) {
      return ColumnPrefs.defaults(columns);
    }
  },
  /** Grava. Silencioso em erro — modo anônimo e cota cheia não podem derrubar a tela. */
  save(active, storageKey, storage) {
    try {
      (storage || window.localStorage).setItem(storageKey, JSON.stringify(active));
    } catch (e) { /* preferência de coluna não vale uma tela quebrada */ }
  },
  /** Resolve a preferência em definições, na ordem escolhida. Chave morta é descartada. */
  resolve(columns, active) {
    return active.map((k) => ColumnPrefs.find(columns, k)).filter(Boolean);
  },
};

const iconBtn = (disabled) => ({
  width: 24, height: 24, flex: 'none', display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
  borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)',
  cursor: disabled ? 'default' : 'pointer', opacity: disabled ? 0.4 : 1, padding: 0,
});

const Chevron = ({ up }) => React.createElement('svg', {
  width: 13, height: 13, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
  strokeWidth: 2.4, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': 'true',
}, React.createElement('polyline', { points: up ? '18 15 12 9 6 15' : '6 9 12 15 18 9' }));

const Cross = () => React.createElement('svg', {
  width: 13, height: 13, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor',
  strokeWidth: 2.4, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': 'true',
}, React.createElement('path', { d: 'M18 6 6 18M6 6l12 12' }));

const Grip = () => React.createElement('svg', {
  width: 13, height: 13, viewBox: '0 0 24 24', fill: 'currentColor', 'aria-hidden': 'true',
}, [[9, 5], [15, 5], [9, 12], [15, 12], [9, 19], [15, 19]].map(([cx, cy], i) =>
  React.createElement('circle', { key: i, cx, cy, r: 1.6 })));

export function ColumnManager({
  columns = [], groups = [], active = [], onChange,
  storageKey, labelActive = 'Na grade — de cima para baixo é a ordem das colunas',
  labelAvailable = 'Disponíveis', showReset = true, maxHeight = 320,
}) {
  const drag = React.useRef(null);
  const [over, setOver] = React.useState(null);

  const commit = (next) => {
    if (storageKey) ColumnPrefs.save(next, storageKey);
    onChange && onChange(next);
  };
  /* o botão só fica vivo quando o movimento tem efeito — mesma regra da mutação,
     senão sobra controle clicável que não faz nada */
  const canMove = (from, to) => ColumnPrefs.move(columns, active, from, to) !== active;
  const move = (i, d) => commit(ColumnPrefs.move(columns, active, i, i + d));
  const drop = (i) => {
    const from = drag.current;
    drag.current = null; setOver(null);
    if (from === null || from === i) return;
    commit(ColumnPrefs.move(columns, active, from, i));
  };

  const shown = ColumnPrefs.resolve(columns, active);
  const out = columns.filter((c) => !active.includes(c.key));
  const grouped = (groups.length ? groups : [{ key: null, label: '' }])
    .map((g) => ({ g, items: g.key === null ? out : out.filter((c) => c.group === g.key) }))
    .filter((x) => x.items.length);

  const lbl = (text) => React.createElement('span', { style: {
    display: 'block', marginBottom: 6, font: '600 var(--fs-1, 10.5px)/1.6 ' + CM_FONT,
    letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-dim)',
  } }, text);

  const pill = (text) => React.createElement('span', { style: {
    flex: 'none', padding: '1px 6px', borderRadius: 99, border: '1px solid var(--border)',
    background: 'var(--bg-2)', font: '600 var(--fs-1, 10.5px)/1.5 ' + CM_FONT, color: 'var(--text-dim)',
  } }, text);

  return (
    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) minmax(0,1fr)', gap: 18, font: 'var(--fs-3, 12.5px)/1.4 ' + CM_FONT, color: 'var(--text)' }}>
      <div style={{ minWidth: 0 }}>
        {lbl(labelActive)}
        <ol style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 4, maxHeight, overflow: 'auto' }}>
          {shown.map((c, i) => (
            <li key={c.key} draggable={!c.fixed}
              onDragStart={() => { drag.current = i; }}
              onDragOver={(e) => { if (drag.current === null || !canMove(drag.current, i)) return; e.preventDefault(); setOver(i); }}
              onDragLeave={() => setOver((v) => (v === i ? null : v))}
              onDrop={() => drop(i)}
              style={{
                display: 'flex', alignItems: 'center', gap: 8, padding: '5px 8px', borderRadius: 8,
                background: 'var(--bg-2)', border: '1px solid ' + (over === i ? 'var(--accent)' : 'var(--border)'),
                cursor: c.fixed ? 'default' : 'grab',
              }}>
              <span aria-hidden="true" style={{ color: 'var(--text-mute)', display: 'inline-flex', opacity: c.fixed ? 0.35 : 1 }}><Grip /></span>
              <span style={{ width: 18, flex: 'none', font: 'var(--fs-2, 11.5px)/1 var(--font-mono)', color: 'var(--text-mute)', fontVariantNumeric: 'tabular-nums' }}>{i + 1}</span>
              <span style={{ flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.label}</span>
              {c.fixed && pill('fixa')}
              <button type="button" aria-label={'Subir ' + c.label} disabled={!canMove(i, i - 1)} onClick={() => move(i, -1)} style={iconBtn(!canMove(i, i - 1))}><Chevron up /></button>
              <button type="button" aria-label={'Descer ' + c.label} disabled={!canMove(i, i + 1)} onClick={() => move(i, 1)} style={iconBtn(!canMove(i, i + 1))}><Chevron /></button>
              {!c.fixed && (
                <button type="button" aria-label={'Tirar ' + c.label + ' da grade'} onClick={() => commit(ColumnPrefs.toggle(columns, active, c.key))} style={iconBtn(false)}><Cross /></button>
              )}
            </li>
          ))}
        </ol>
        {showReset && (
          <button type="button" onClick={() => commit(ColumnPrefs.defaults(columns))}
            style={{ marginTop: 10, height: 28, padding: '0 10px', borderRadius: 'var(--radius-md, 8px)', border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--text-dim)', font: '600 var(--fs-2, 11.5px)/1 ' + CM_FONT, cursor: 'pointer' }}>
            Restaurar padrão
          </button>
        )}
      </div>

      <div style={{ minWidth: 0, maxHeight, overflow: 'auto' }}>
        {lbl(labelAvailable)}
        {!grouped.length ? (
          <p style={{ margin: 0, color: 'var(--text-dim)' }}>Todas as colunas estão na grade.</p>
        ) : grouped.map(({ g, items }) => (
          <div key={g.key || '_'} style={{ marginBottom: 10 }}>
            {g.label && (
              <span style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4, font: '600 var(--fs-1, 10.5px)/1.6 ' + CM_FONT, letterSpacing: '.05em', textTransform: 'uppercase', color: 'var(--text-mute)' }}>
                {g.label}{pill(items.length)}
              </span>
            )}
            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
              {items.map((c) => (
                <li key={c.key} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '5px 8px', borderRadius: 8, background: 'var(--surface)', border: '1px dashed var(--border)' }}>
                  <span style={{ flex: 1, minWidth: 0, color: 'var(--text-dim)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.label}</span>
                  <button type="button" aria-label={'Colocar ' + c.label + ' na grade'} onClick={() => commit(ColumnPrefs.toggle(columns, active, c.key))}
                    style={{ display: 'inline-flex', alignItems: 'center', gap: 4, height: 24, padding: '0 8px', flex: 'none', borderRadius: 6, border: '1px solid var(--border)', background: 'var(--surface)', color: 'var(--accent)', font: '600 var(--fs-2, 11.5px)/1 ' + CM_FONT, cursor: 'pointer' }}>
                    + usar
                  </button>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </div>
  );
}
