// oficina-app.jsx — Oficina Auto (board Kanban + drawer DVI)
// Portado do prototipo cowork erp-shell-v2. Tweaks: foco/densidade/pressao.
// Exporta window.OficinaApp para <x-import>.

/* ===== tweaks-panel (inline) ===== */

// tweaks-panel.jsx
// Reusable Tweaks shell + form-control helpers.
//
// Owns the host protocol (listens for __activate_edit_mode / __deactivate_edit_mode,
// posts __edit_mode_available / __edit_mode_set_keys / __edit_mode_dismissed) so
// individual prototypes don't re-roll it. Ships a consistent set of controls so you
// don't hand-draw <input type="range">, segmented radios, steppers, etc.
//
// Usage (in an HTML file that loads React + Babel):
//
//   const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
//     "primaryColor": "#D97757",
//     "palette": ["#D97757", "#29261b", "#f6f4ef"],
//     "fontSize": 16,
//     "density": "regular",
//     "dark": false
//   }/*EDITMODE-END*/;
//
//   function App() {
//     const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
//     return (
//       <div style={{ fontSize: t.fontSize, color: t.primaryColor }}>
//         Hello
//         <TweaksPanel>
//           <TweakSection label="Typography" />
//           <TweakSlider label="Font size" value={t.fontSize} min={10} max={32} unit="px"
//                        onChange={(v) => setTweak('fontSize', v)} />
//           <TweakRadio  label="Density" value={t.density}
//                        options={['compact', 'regular', 'comfy']}
//                        onChange={(v) => setTweak('density', v)} />
//           <TweakSection label="Theme" />
//           <TweakColor  label="Primary" value={t.primaryColor}
//                        options={['#D97757', '#2A6FDB', '#1F8A5B', '#7A5AE0']}
//                        onChange={(v) => setTweak('primaryColor', v)} />
//           <TweakColor  label="Palette" value={t.palette}
//                        options={[['#D97757', '#29261b', '#f6f4ef'],
//                                  ['#475569', '#0f172a', '#f1f5f9']]}
//                        onChange={(v) => setTweak('palette', v)} />
//           <TweakToggle label="Dark mode" value={t.dark}
//                        onChange={(v) => setTweak('dark', v)} />
//         </TweaksPanel>
//       </div>
//     );
//   }
//
// ─────────────────────────────────────────────────────────────────────────────

const __TWEAKS_STYLE = `
  .twk-panel{position:fixed;right:16px;bottom:16px;z-index:2147483646;width:280px;
    max-height:calc(100vh - 32px);display:flex;flex-direction:column;
    transform:scale(var(--dc-inv-zoom,1));transform-origin:bottom right;
    background:rgba(250,249,247,.78);color:#29261b;
    -webkit-backdrop-filter:blur(24px) saturate(160%);backdrop-filter:blur(24px) saturate(160%);
    border:.5px solid rgba(255,255,255,.6);border-radius:14px;
    box-shadow:0 1px 0 rgba(255,255,255,.5) inset,0 12px 40px rgba(0,0,0,.18);
    font:11.5px/1.4 ui-sans-serif,system-ui,-apple-system,sans-serif;overflow:hidden}
  .twk-hd{display:flex;align-items:center;justify-content:space-between;
    padding:10px 8px 10px 14px;cursor:move;user-select:none}
  .twk-hd b{font-size:12px;font-weight:600;letter-spacing:.01em}
  .twk-x{appearance:none;border:0;background:transparent;color:rgba(41,38,27,.55);
    width:22px;height:22px;border-radius:6px;cursor:default;font-size:13px;line-height:1}
  .twk-x:hover{background:rgba(0,0,0,.06);color:#29261b}
  .twk-body{padding:2px 14px 14px;display:flex;flex-direction:column;gap:10px;
    overflow-y:auto;overflow-x:hidden;min-height:0;
    scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.15) transparent}
  .twk-body::-webkit-scrollbar{width:8px}
  .twk-body::-webkit-scrollbar-track{background:transparent;margin:2px}
  .twk-body::-webkit-scrollbar-thumb{background:rgba(0,0,0,.15);border-radius:4px;
    border:2px solid transparent;background-clip:content-box}
  .twk-body::-webkit-scrollbar-thumb:hover{background:rgba(0,0,0,.25);
    border:2px solid transparent;background-clip:content-box}
  .twk-row{display:flex;flex-direction:column;gap:5px}
  .twk-row-h{flex-direction:row;align-items:center;justify-content:space-between;gap:10px}
  .twk-lbl{display:flex;justify-content:space-between;align-items:baseline;
    color:rgba(41,38,27,.72)}
  .twk-lbl>span:first-child{font-weight:500}
  .twk-val{color:rgba(41,38,27,.5);font-variant-numeric:tabular-nums}

  .twk-sect{font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;
    color:rgba(41,38,27,.45);padding:10px 0 0}
  .twk-sect:first-child{padding-top:0}

  .twk-field{appearance:none;width:100%;height:26px;padding:0 8px;
    border:.5px solid rgba(0,0,0,.1);border-radius:7px;
    background:rgba(255,255,255,.6);color:inherit;font:inherit;outline:none}
  .twk-field:focus{border-color:rgba(0,0,0,.25);background:rgba(255,255,255,.85)}
  select.twk-field{padding-right:22px;
    background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'><path fill='rgba(0,0,0,.5)' d='M0 0h10L5 6z'/></svg>");
    background-repeat:no-repeat;background-position:right 8px center}

  .twk-slider{appearance:none;-webkit-appearance:none;width:100%;height:4px;margin:6px 0;
    border-radius:999px;background:rgba(0,0,0,.12);outline:none}
  .twk-slider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;
    width:14px;height:14px;border-radius:50%;background:#fff;
    border:.5px solid rgba(0,0,0,.12);box-shadow:0 1px 3px rgba(0,0,0,.2);cursor:default}
  .twk-slider::-moz-range-thumb{width:14px;height:14px;border-radius:50%;
    background:#fff;border:.5px solid rgba(0,0,0,.12);box-shadow:0 1px 3px rgba(0,0,0,.2);cursor:default}

  .twk-seg{position:relative;display:flex;padding:2px;border-radius:8px;
    background:rgba(0,0,0,.06);user-select:none}
  .twk-seg-thumb{position:absolute;top:2px;bottom:2px;border-radius:6px;
    background:rgba(255,255,255,.9);box-shadow:0 1px 2px rgba(0,0,0,.12);
    transition:left .15s cubic-bezier(.3,.7,.4,1),width .15s}
  .twk-seg.dragging .twk-seg-thumb{transition:none}
  .twk-seg button{appearance:none;position:relative;z-index:1;flex:1;border:0;
    background:transparent;color:inherit;font:inherit;font-weight:500;min-height:22px;
    border-radius:6px;cursor:default;padding:4px 6px;line-height:1.2;
    overflow-wrap:anywhere}

  .twk-toggle{position:relative;width:32px;height:18px;border:0;border-radius:999px;
    background:rgba(0,0,0,.15);transition:background .15s;cursor:default;padding:0}
  .twk-toggle[data-on="1"]{background:#34c759}
  .twk-toggle i{position:absolute;top:2px;left:2px;width:14px;height:14px;border-radius:50%;
    background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
  .twk-toggle[data-on="1"] i{transform:translateX(14px)}

  .twk-num{display:flex;align-items:center;height:26px;padding:0 0 0 8px;
    border:.5px solid rgba(0,0,0,.1);border-radius:7px;background:rgba(255,255,255,.6)}
  .twk-num-lbl{font-weight:500;color:rgba(41,38,27,.6);cursor:ew-resize;
    user-select:none;padding-right:8px}
  .twk-num input{flex:1;min-width:0;height:100%;border:0;background:transparent;
    font:inherit;font-variant-numeric:tabular-nums;text-align:right;padding:0 8px 0 0;
    outline:none;color:inherit;-moz-appearance:textfield}
  .twk-num input::-webkit-inner-spin-button,.twk-num input::-webkit-outer-spin-button{
    -webkit-appearance:none;margin:0}
  .twk-num-unit{padding-right:8px;color:rgba(41,38,27,.45)}

  .twk-btn{appearance:none;height:26px;padding:0 12px;border:0;border-radius:7px;
    background:rgba(0,0,0,.78);color:#fff;font:inherit;font-weight:500;cursor:default}
  .twk-btn:hover{background:rgba(0,0,0,.88)}
  .twk-btn.secondary{background:rgba(0,0,0,.06);color:inherit}
  .twk-btn.secondary:hover{background:rgba(0,0,0,.1)}

  .twk-swatch{appearance:none;-webkit-appearance:none;width:56px;height:22px;
    border:.5px solid rgba(0,0,0,.1);border-radius:6px;padding:0;cursor:default;
    background:transparent;flex-shrink:0}
  .twk-swatch::-webkit-color-swatch-wrapper{padding:0}
  .twk-swatch::-webkit-color-swatch{border:0;border-radius:5.5px}
  .twk-swatch::-moz-color-swatch{border:0;border-radius:5.5px}

  .twk-chips{display:flex;gap:6px}
  .twk-chip{position:relative;appearance:none;flex:1;min-width:0;height:46px;
    padding:0;border:0;border-radius:6px;overflow:hidden;cursor:default;
    box-shadow:0 0 0 .5px rgba(0,0,0,.12),0 1px 2px rgba(0,0,0,.06);
    transition:transform .12s cubic-bezier(.3,.7,.4,1),box-shadow .12s}
  .twk-chip:hover{transform:translateY(-1px);
    box-shadow:0 0 0 .5px rgba(0,0,0,.18),0 4px 10px rgba(0,0,0,.12)}
  .twk-chip[data-on="1"]{box-shadow:0 0 0 1.5px rgba(0,0,0,.85),
    0 2px 6px rgba(0,0,0,.15)}
  .twk-chip>span{position:absolute;top:0;bottom:0;right:0;width:34%;
    display:flex;flex-direction:column;box-shadow:-1px 0 0 rgba(0,0,0,.1)}
  .twk-chip>span>i{flex:1;box-shadow:0 -1px 0 rgba(0,0,0,.1)}
  .twk-chip>span>i:first-child{box-shadow:none}
  .twk-chip svg{position:absolute;top:6px;left:6px;width:13px;height:13px;
    filter:drop-shadow(0 1px 1px rgba(0,0,0,.3))}
`;

// ── useTweaks ───────────────────────────────────────────────────────────────
// Single source of truth for tweak values. setTweak persists via the host
// (__edit_mode_set_keys → host rewrites the EDITMODE block on disk).
function useTweaks(defaults) {
  const [values, setValues] = React.useState(defaults);
  // Accepts either setTweak('key', value) or setTweak({ key: value, ... }) so a
  // useState-style call doesn't write a "[object Object]" key into the persisted
  // JSON block.
  const setTweak = React.useCallback((keyOrEdits, val) => {
    const edits = typeof keyOrEdits === 'object' && keyOrEdits !== null
      ? keyOrEdits : { [keyOrEdits]: val };
    setValues((prev) => ({ ...prev, ...edits }));
    window.parent.postMessage({ type: '__edit_mode_set_keys', edits }, '*');
    // Same-window signal so in-page listeners (deck-stage rail thumbnails)
    // can react — the parent message only reaches the host, not peers.
    window.dispatchEvent(new CustomEvent('tweakchange', { detail: edits }));
  }, []);
  return [values, setTweak];
}

// ── TweaksPanel ─────────────────────────────────────────────────────────────
// Floating shell. Registers the protocol listener BEFORE announcing
// availability — if the announce ran first, the host's activate could land
// before our handler exists and the toolbar toggle would silently no-op.
// The close button posts __edit_mode_dismissed so the host's toolbar toggle
// flips off in lockstep; the host echoes __deactivate_edit_mode back which
// is what actually hides the panel.
function TweaksPanel({ title = 'Tweaks', noDeckControls = false, children }) {
  const [open, setOpen] = React.useState(false);
  const dragRef = React.useRef(null);
  // Auto-inject a rail toggle when a <deck-stage> is on the page. The
  // toggle drives the deck's per-viewer _railVisible via window message;
  // state is mirrored from the same localStorage key the deck reads so
  // the control reflects reality across reloads. The mechanism is the
  // message — authors who want custom placement can post it directly
  // and pass noDeckControls to suppress this one.
  const hasDeckStage = React.useMemo(
    () => typeof document !== 'undefined' && !!document.querySelector('deck-stage'),
    [],
  );
  // Hide the toggle until the host has actually enabled the rail (the
  // __omelette_rail_enabled window message, posted only when the
  // omelette_deck_rail_enabled flag is on for this user). The initial read
  // covers TweaksPanel mounting after the message already arrived; the
  // listener covers the common case of mounting first.
  const [railEnabled, setRailEnabled] = React.useState(
    () => hasDeckStage && !!document.querySelector('deck-stage')?._railEnabled,
  );
  React.useEffect(() => {
    if (!hasDeckStage || railEnabled) return undefined;
    const onMsg = (e) => {
      if (e.data && e.data.type === '__omelette_rail_enabled') setRailEnabled(true);
    };
    window.addEventListener('message', onMsg);
    return () => window.removeEventListener('message', onMsg);
  }, [hasDeckStage, railEnabled]);
  const [railVisible, setRailVisible] = React.useState(() => {
    try { return localStorage.getItem('deck-stage.railVisible') !== '0'; } catch (e) { return true; }
  });
  const toggleRail = (on) => {
    setRailVisible(on);
    window.postMessage({ type: '__deck_rail_visible', on }, '*');
  };
  const offsetRef = React.useRef({ x: 16, y: 16 });
  const PAD = 16;

  const clampToViewport = React.useCallback(() => {
    const panel = dragRef.current;
    if (!panel) return;
    const w = panel.offsetWidth, h = panel.offsetHeight;
    const maxRight = Math.max(PAD, window.innerWidth - w - PAD);
    const maxBottom = Math.max(PAD, window.innerHeight - h - PAD);
    offsetRef.current = {
      x: Math.min(maxRight, Math.max(PAD, offsetRef.current.x)),
      y: Math.min(maxBottom, Math.max(PAD, offsetRef.current.y)),
    };
    panel.style.right = offsetRef.current.x + 'px';
    panel.style.bottom = offsetRef.current.y + 'px';
  }, []);

  React.useEffect(() => {
    if (!open) return;
    clampToViewport();
    if (typeof ResizeObserver === 'undefined') {
      window.addEventListener('resize', clampToViewport);
      return () => window.removeEventListener('resize', clampToViewport);
    }
    const ro = new ResizeObserver(clampToViewport);
    ro.observe(document.documentElement);
    return () => ro.disconnect();
  }, [open, clampToViewport]);

  React.useEffect(() => {
    const onMsg = (e) => {
      const t = e?.data?.type;
      if (t === '__activate_edit_mode') setOpen(true);
      else if (t === '__deactivate_edit_mode') setOpen(false);
    };
    window.addEventListener('message', onMsg);
    window.parent.postMessage({ type: '__edit_mode_available' }, '*');
    return () => window.removeEventListener('message', onMsg);
  }, []);

  const dismiss = () => {
    setOpen(false);
    window.parent.postMessage({ type: '__edit_mode_dismissed' }, '*');
  };

  const onDragStart = (e) => {
    const panel = dragRef.current;
    if (!panel) return;
    const r = panel.getBoundingClientRect();
    const sx = e.clientX, sy = e.clientY;
    const startRight = window.innerWidth - r.right;
    const startBottom = window.innerHeight - r.bottom;
    const move = (ev) => {
      offsetRef.current = {
        x: startRight - (ev.clientX - sx),
        y: startBottom - (ev.clientY - sy),
      };
      clampToViewport();
    };
    const up = () => {
      window.removeEventListener('mousemove', move);
      window.removeEventListener('mouseup', up);
    };
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', up);
  };

  if (!open) return null;
  return (
    <>
      <style>{__TWEAKS_STYLE}</style>
      <div ref={dragRef} className="twk-panel" data-noncommentable=""
           style={{ right: offsetRef.current.x, bottom: offsetRef.current.y }}>
        <div className="twk-hd" onMouseDown={onDragStart}>
          <b>{title}</b>
          <button className="twk-x" aria-label="Close tweaks"
                  onMouseDown={(e) => e.stopPropagation()}
                  onClick={dismiss}>✕</button>
        </div>
        <div className="twk-body">
          {children}
          {hasDeckStage && railEnabled && !noDeckControls && (
            <TweakSection label="Deck">
              <TweakToggle label="Thumbnail rail" value={railVisible} onChange={toggleRail} />
            </TweakSection>
          )}
        </div>
      </div>
    </>
  );
}

// ── Layout helpers ──────────────────────────────────────────────────────────

function TweakSection({ label, children }) {
  return (
    <>
      <div className="twk-sect">{label}</div>
      {children}
    </>
  );
}

function TweakRow({ label, value, children, inline = false }) {
  return (
    <div className={inline ? 'twk-row twk-row-h' : 'twk-row'}>
      <div className="twk-lbl">
        <span>{label}</span>
        {value != null && <span className="twk-val">{value}</span>}
      </div>
      {children}
    </div>
  );
}

// ── Controls ────────────────────────────────────────────────────────────────

function TweakSlider({ label, value, min = 0, max = 100, step = 1, unit = '', onChange }) {
  return (
    <TweakRow label={label} value={`${value}${unit}`}>
      <input type="range" className="twk-slider" min={min} max={max} step={step}
             value={value} onChange={(e) => onChange(Number(e.target.value))} />
    </TweakRow>
  );
}

function TweakToggle({ label, value, onChange }) {
  return (
    <div className="twk-row twk-row-h">
      <div className="twk-lbl"><span>{label}</span></div>
      <button type="button" className="twk-toggle" data-on={value ? '1' : '0'}
              role="switch" aria-checked={!!value}
              onClick={() => onChange(!value)}><i /></button>
    </div>
  );
}

function TweakRadio({ label, value, options, onChange }) {
  const trackRef = React.useRef(null);
  const [dragging, setDragging] = React.useState(false);
  // The active value is read by pointer-move handlers attached for the lifetime
  // of a drag — ref it so a stale closure doesn't fire onChange for every move.
  const valueRef = React.useRef(value);
  valueRef.current = value;

  // Segments wrap mid-word once per-segment width runs out. The track is
  // ~248px (280 panel − 28 body pad − 4 seg pad), each button loses 12px
  // to its own padding, and 11.5px system-ui averages ~6.3px/char — so 2
  // options fit ~16 chars each, 3 fit ~10. Past that (or >3 options), fall
  // back to a dropdown rather than wrap.
  const labelLen = (o) => String(typeof o === 'object' ? o.label : o).length;
  const maxLen = options.reduce((m, o) => Math.max(m, labelLen(o)), 0);
  const fitsAsSegments = maxLen <= ({ 2: 16, 3: 10 }[options.length] ?? 0);
  if (!fitsAsSegments) {
    // <select> emits strings — map back to the original option value so the
    // fallback stays type-preserving (numbers, booleans) like the segment path.
    const resolve = (s) => {
      const m = options.find((o) => String(typeof o === 'object' ? o.value : o) === s);
      return m === undefined ? s : typeof m === 'object' ? m.value : m;
    };
    return <TweakSelect label={label} value={value} options={options}
                        onChange={(s) => onChange(resolve(s))} />;
  }
  const opts = options.map((o) => (typeof o === 'object' ? o : { value: o, label: o }));
  const idx = Math.max(0, opts.findIndex((o) => o.value === value));
  const n = opts.length;

  const segAt = (clientX) => {
    const r = trackRef.current.getBoundingClientRect();
    const inner = r.width - 4;
    const i = Math.floor(((clientX - r.left - 2) / inner) * n);
    return opts[Math.max(0, Math.min(n - 1, i))].value;
  };

  const onPointerDown = (e) => {
    setDragging(true);
    const v0 = segAt(e.clientX);
    if (v0 !== valueRef.current) onChange(v0);
    const move = (ev) => {
      if (!trackRef.current) return;
      const v = segAt(ev.clientX);
      if (v !== valueRef.current) onChange(v);
    };
    const up = () => {
      setDragging(false);
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };

  return (
    <TweakRow label={label}>
      <div ref={trackRef} role="radiogroup" onPointerDown={onPointerDown}
           className={dragging ? 'twk-seg dragging' : 'twk-seg'}>
        <div className="twk-seg-thumb"
             style={{ left: `calc(2px + ${idx} * (100% - 4px) / ${n})`,
                      width: `calc((100% - 4px) / ${n})` }} />
        {opts.map((o) => (
          <button key={o.value} type="button" role="radio" aria-checked={o.value === value}>
            {o.label}
          </button>
        ))}
      </div>
    </TweakRow>
  );
}

function TweakSelect({ label, value, options, onChange }) {
  return (
    <TweakRow label={label}>
      <select className="twk-field" value={value} onChange={(e) => onChange(e.target.value)}>
        {options.map((o) => {
          const v = typeof o === 'object' ? o.value : o;
          const l = typeof o === 'object' ? o.label : o;
          return <option key={v} value={v}>{l}</option>;
        })}
      </select>
    </TweakRow>
  );
}

function TweakText({ label, value, placeholder, onChange }) {
  return (
    <TweakRow label={label}>
      <input className="twk-field" type="text" value={value} placeholder={placeholder}
             onChange={(e) => onChange(e.target.value)} />
    </TweakRow>
  );
}

function TweakNumber({ label, value, min, max, step = 1, unit = '', onChange }) {
  const clamp = (n) => {
    if (min != null && n < min) return min;
    if (max != null && n > max) return max;
    return n;
  };
  const startRef = React.useRef({ x: 0, val: 0 });
  const onScrubStart = (e) => {
    e.preventDefault();
    startRef.current = { x: e.clientX, val: value };
    const decimals = (String(step).split('.')[1] || '').length;
    const move = (ev) => {
      const dx = ev.clientX - startRef.current.x;
      const raw = startRef.current.val + dx * step;
      const snapped = Math.round(raw / step) * step;
      onChange(clamp(Number(snapped.toFixed(decimals))));
    };
    const up = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
  };
  return (
    <div className="twk-num">
      <span className="twk-num-lbl" onPointerDown={onScrubStart}>{label}</span>
      <input type="number" value={value} min={min} max={max} step={step}
             onChange={(e) => onChange(clamp(Number(e.target.value)))} />
      {unit && <span className="twk-num-unit">{unit}</span>}
    </div>
  );
}

// Relative-luminance contrast pick — checkmarks drawn over a swatch need to
// read on both #111 and #fafafa without per-option configuration. Hex input
// only (#rgb / #rrggbb); named or rgb()/hsl() colors fall through to "light".
function __twkIsLight(hex) {
  const h = String(hex).replace('#', '');
  const x = h.length === 3 ? h.replace(/./g, (c) => c + c) : h.padEnd(6, '0');
  const n = parseInt(x.slice(0, 6), 16);
  if (Number.isNaN(n)) return true;
  const r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
  return r * 299 + g * 587 + b * 114 > 148000;
}

const __TwkCheck = ({ light }) => (
  <svg viewBox="0 0 14 14" aria-hidden="true">
    <path d="M3 7.2 5.8 10 11 4.2" fill="none" strokeWidth="2.2"
          strokeLinecap="round" strokeLinejoin="round"
          stroke={light ? 'rgba(0,0,0,.78)' : '#fff'} />
  </svg>
);

// TweakColor — curated color/palette picker. Each option is either a single
// hex string or an array of 1-5 hex strings; the card adapts — a lone color
// renders solid, a palette renders colors[0] as the hero (left ~2/3) with the
// rest stacked in a sharp column on the right. onChange emits the
// option in the shape it was passed (string stays string, array stays array).
// Without options it falls back to the native color input for back-compat.
function TweakColor({ label, value, options, onChange }) {
  if (!options || !options.length) {
    return (
      <div className="twk-row twk-row-h">
        <div className="twk-lbl"><span>{label}</span></div>
        <input type="color" className="twk-swatch" value={value}
               onChange={(e) => onChange(e.target.value)} />
      </div>
    );
  }
  // Native <input type=color> emits lowercase hex per the HTML spec, so
  // compare case-insensitively. String() guards JSON.stringify(undefined),
  // which returns the primitive undefined (no .toLowerCase).
  const key = (o) => String(JSON.stringify(o)).toLowerCase();
  const cur = key(value);
  return (
    <TweakRow label={label}>
      <div className="twk-chips" role="radiogroup">
        {options.map((o, i) => {
          const colors = Array.isArray(o) ? o : [o];
          const [hero, ...rest] = colors;
          const sup = rest.slice(0, 4);
          const on = key(o) === cur;
          return (
            <button key={i} type="button" className="twk-chip" role="radio"
                    aria-checked={on} data-on={on ? '1' : '0'}
                    aria-label={colors.join(', ')} title={colors.join(' · ')}
                    style={{ background: hero }}
                    onClick={() => onChange(o)}>
              {sup.length > 0 && (
                <span>
                  {sup.map((c, j) => <i key={j} style={{ background: c }} />)}
                </span>
              )}
              {on && <__TwkCheck light={__twkIsLight(hero)} />}
            </button>
          );
        })}
      </div>
    </TweakRow>
  );
}

function TweakButton({ label, onClick, secondary = false }) {
  return (
    <button type="button" className={secondary ? 'twk-btn secondary' : 'twk-btn'}
            onClick={onClick}>{label}</button>
  );
}

Object.assign(window, {
  useTweaks, TweaksPanel, TweakSection, TweakRow,
  TweakSlider, TweakToggle, TweakRadio, TweakSelect,
  TweakText, TweakNumber, TweakColor, TweakButton,
});


/* ===== app ===== */
const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
      "visao": "board",
      "linkEstado": "valido",
      "foco": "etapa",
      "densidade": "padrao",
      "pressao": "padrao",
      "tema": "claro",
      "acabamento": "premium"
    }/*EDITMODE-END*/;
    const { useState, useMemo } = React;

    // ── Inline icons (subset, sized for cards) ──
    const Ico = {
      car: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 17h14M3 13l2-5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2l2 5"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>,
      gauge: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 13l4-3"/><path d="M12 5V3"/></svg>,
      wrench: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14.7 6.3a4 4 0 0 0 5.4 5.4L21 12l-9 9-2-2 9-9-1.7-1.7a4 4 0 0 0-5.4-5.4L13 5l-9 9 2 2 9-9-.3-.7Z"/></svg>,
      box: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 7l9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/></svg>,
      clock: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>,
      check: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"><path d="M5 13l4 4 10-10"/></svg>,
      x: (p) => <svg width={p.size||14} height={p.size||14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 6l12 12M6 18L18 6"/></svg>,
      plus: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14"/></svg>,
      grid: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>,
      list: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>,
      print: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="9" rx="2"/><path d="M6 14h12v7H6z"/></svg>,
      camera: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M3 8h4l2-3h6l2 3h4v11H3z"/><circle cx="12" cy="13" r="3.5"/></svg>,
      alert: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3l10 18H2L12 3Z"/><path d="M12 10v5"/><circle cx="12" cy="18" r="0.5" fill="currentColor"/></svg>,
      msg: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>,
      file: (p) => <svg width={p.size||12} height={p.size||12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/></svg>,
    };

    // ── Mock: Boxes/Elevadores ──
    const RECURSOS = [
      { id: "box1",  label: "Vala 1",          kind: "Vala",     cls: "box" },
      { id: "box2",  label: "Vala 2",          kind: "Vala",     cls: "box" },
      { id: "box3",  label: "Box pesado",      kind: "Box",      cls: "box" },
      { id: "elev1", label: "Elevador pesado", kind: "Elevador", cls: "elev" },
      { id: "elev2", label: "Pátio manobra",   kind: "Pátio",    cls: "elev" },
    ];

    // ── Mock: Mecânicos ──
    const MECANICOS = [
      { id: "m1", nome: "João Lima",     ini: "JL" },
      { id: "m2", nome: "Pedro Souza",   ini: "PS" },
      { id: "m3", nome: "Carlos Rocha",  ini: "CR" },
      { id: "m4", nome: "Diego Alves",   ini: "DA" },
    ];

    // ── Mock: OSes Oficina ──
    const OS_LIST = [
      // RECEPÇÃO
      { id: "9012", stage: "recepcao",   plate: "RBA-2H78", veh: "Mercedes-Benz Atego 2426", km: "412.350",
        client: "Martinho Caçambas",      symptom: "Vazamento no cilindro hidráulico da caçamba basculante",
        recurso: null, mech: null, deadline: "Hoje 17h",   urgent: false, value: "R$ 1.480,00", arrived: "07:48" },
      { id: "9015", stage: "recepcao",   plate: "FZJ-4F12", veh: "VW Constellation 24.280", km: "398.120",
        client: "Transportes Vale Verde", symptom: "Revisão preventiva 400 mil km + freio a ar",
        recurso: null, mech: null, deadline: "Amanhã",     urgent: false, value: "R$ 2.300,00", arrived: "08:30" },

      // DIAGNÓSTICO
      { id: "9008", stage: "diagnostico", plate: "QXM-1B33", veh: "Volvo FH 460", km: "540.700",
        client: "Frota Martinho",         symptom: "Perda de potência e fumaça preta — suspeita de turbo",
        recurso: "elev1", mech: "m2", deadline: "Hoje 18h", urgent: true,  value: "R$ 3.200,00",
        progress: 45, etaDiag: "40 min", dviOk: 12, dviWarn: 4, dviBad: 2, dviTotal: 3200 },
      { id: "9005", stage: "diagnostico", plate: "GHS-8E22", veh: "Scania R450", km: "286.400",
        client: "Pedreira Santa Rita",    symptom: "Ruído no diferencial traseiro sob carga",
        recurso: "box2", mech: "m4", deadline: "Hoje 16h", urgent: false, value: "—",
        progress: 70, etaDiag: "20 min" },

      // AGUARDANDO APROVAÇÃO
      { id: "8998", stage: "aprovacao",  plate: "OWD-5R09", veh: "Ford Cargo 2429", km: "612.900",
        client: "Martinho Caçambas",      symptom: "Reforma completa do sistema hidráulico de basculamento",
        recurso: null, mech: "m1", deadline: "Hoje 19h",  urgent: true,  value: "R$ 6.850,00",
        sentVia: "WhatsApp", sentAgo: "2h", dviOk: 14, dviWarn: 5, dviBad: 3, dviTotal: 6850 },
      { id: "8994", stage: "aprovacao",  plate: "MNT-3T55", veh: "Iveco Tector 240E", km: "354.200",
        client: "Construtora Lince",      symptom: "Troca de feixe de molas dianteiro (par) + buchas",
        recurso: null, mech: "m3", deadline: "Amanhã",    urgent: false, value: "R$ 4.120,00",
        sentVia: "WhatsApp", sentAgo: "ontem 17h", dviOk: 18, dviWarn: 4, dviBad: 2, dviTotal: 4120 },

      // AGUARDANDO PEÇAS
      { id: "8990", stage: "pecas",      plate: "KKQ-7H44", veh: "MAN TGX 29.480", km: "471.300",
        client: "Frota Martinho",         symptom: "Kit de embreagem 430mm + atuador",
        recurso: null, mech: "m1", deadline: "Sex 14h",   urgent: false, value: "R$ 5.480,00",
        partsStatus: "encomendado", partsLabel: "Kit embreagem 430mm chega 28/06 manhã" },
      { id: "8986", stage: "pecas",      plate: "PLT-2C18", veh: "Mercedes-Benz Axor 2544", km: "588.100",
        client: "Mineração Boa Vista",    symptom: "Bomba injetora + jogo de bicos",
        recurso: null, mech: "m2", deadline: "Hoje 16h",  urgent: true,  value: "R$ 7.900,00",
        partsStatus: "ok",          partsLabel: "Peças no balcão · pronto p/ executar" },

      // EM EXECUÇÃO
      { id: "8980", stage: "execucao",   plate: "BCY-9G07", veh: "Scania P310", km: "433.600",
        client: "Martinho Caçambas",      symptom: "Substituição da cremalheira de basculamento",
        recurso: "elev1", mech: "m1", deadline: "Hoje 19h", urgent: true,  value: "R$ 4.300,00",
        progress: 35, etaDone: "3h 20min" },
      { id: "8975", stage: "execucao",   plate: "ZTH-6L91", veh: "Volvo VM 270", km: "322.700",
        client: "Transportes Vale Verde", symptom: "Retífica de freios + tambores traseiros",
        recurso: "box1", mech: "m3", deadline: "Hoje 18h", urgent: false, value: "R$ 2.150,00",
        progress: 60, etaDone: "1h 30min" },

      // PRONTO P/ RETIRAR
      { id: "8968", stage: "pronto",     plate: "VRP-5K27", veh: "VW Worker 17.230", km: "502.400",
        client: "Pedreira Santa Rita",    symptom: "Manutenção da suspensão traseira",
        recurso: null, mech: "m2", deadline: "Aguarda retirada", urgent: false, value: "R$ 3.640,00",
        finishedAt: "Hoje 11:20", paid: false },

      // ENTREGUE
      { id: "8960", stage: "entregue",   plate: "WBL-3D88", veh: "DAF XF 105", km: "615.900",
        client: "Frota Martinho",         symptom: "Troca de óleo + filtros + revisão geral",
        recurso: null, mech: "m1", deadline: "—",          urgent: false, value: "R$ 1.890,00",
        deliveredAt: "Hoje 09:05", paid: true },

      // CANCELADO
      { id: "8955", stage: "cancelado",  plate: "JKL-7G31", veh: "Mercedes-Benz Atego 1719", km: "470.200",
        client: "Auto Posto Norte",       symptom: "Orçamento de retífica de motor",
        recurso: null, mech: "m3", deadline: "—",          urgent: false, value: "R$ 12.400,00",
        canceledReason: "Cliente recusou orçamento", canceledAt: "Ontem 16:40" },

      // GARANTIA ACIONADA
      { id: "8948", stage: "garantia",   plate: "TUV-9H52", veh: "Volvo FH 440", km: "559.300",
        client: "Martinho Caçambas",      symptom: "Vibração retornou após troca de cardã",
        recurso: null, mech: "m4", deadline: "Hoje",       urgent: true,  value: "R$ 0,00",
        warrantyReason: "Retorno em 8 dias — revisão do cardã", openedAt: "Hoje 08:15" },
    ];

    // Pipeline FSM real (processo oficina_mecanica_os · OficinaAutoFsmSeeder) —
    // 7 etapas de board + 2 laterais terminais (cancelado / garantia).
    const STAGES = [
      { id: "recepcao",    label: "Recepção",             dot: "slate"   },
      { id: "diagnostico", label: "Diagnóstico",          dot: "blue"    },
      { id: "aprovacao",   label: "Aguardando aprovação", dot: "amber"   },
      { id: "pecas",       label: "Aguardando peças",     dot: "violet"  },
      { id: "execucao",    label: "Em execução",          dot: "indigo"  },
      { id: "pronto",      label: "Pronto p/ retirar",    dot: "emerald" },
      { id: "entregue",    label: "Entregue",             dot: "green"   },
    ];
    const LATERAIS = [
      { id: "cancelado", label: "Cancelado",         dot: "rose"   },
      { id: "garantia",  label: "Garantia acionada", dot: "orange" },
    ];
    const BOARD = [...STAGES, ...LATERAIS];

    // Ações por etapa (labels das transições FSM reais — OficinaAutoFsmSeeder).
    const STAGE_ACTIONS = {
      recepcao:    [{ variant: "primary", label: "Iniciar diagnóstico →" }],
      diagnostico: [{ variant: "primary", label: "Enviar orçamento →" }],
      aprovacao:   [{ variant: "ghost", label: "Cliente recusou" }, { variant: "ghost", label: "Aprovou — pedir peças" }, { variant: "primary", label: "Aprovou — já executar →" }],
      pecas:       [{ variant: "primary", label: "Peças chegaram — iniciar →" }],
      execucao:    [{ variant: "primary", label: "Concluir serviço →" }],
      pronto:      [{ variant: "ghost", label: "Acionar garantia" }, { variant: "primary", label: "Entregar ao cliente →" }],
      entregue:    [{ variant: "ghost", label: "Reimprimir comprovante" }],
      cancelado:   [{ variant: "ghost", label: "Reabrir OS" }],
      garantia:    [{ variant: "primary", label: "Iniciar reparo de garantia →" }],
    };

    // ── Helpers ──
    const recursoOf = (id) => RECURSOS.find(r => r.id === id);
    const mechOf = (id) => MECANICOS.find(m => m.id === id);
    const valorBR = (n) => "R$ " + n.toLocaleString("pt-BR", {minimumFractionDigits:2, maximumFractionDigits:2});
    const valorNumOf = (s) => parseFloat((s||"").replace(/[^\d,]/g,"").replace(",",".")) || 0;

    // ── Subcomponents ──
    function Plate({ value }) {
      return (
        <div className="ofc-plate">
          <div className="top">BR · MERCOSUL</div>
          <div className="num">{value}</div>
        </div>
      );
    }

    // DSPlate — usa o componente PlacaVeiculo do design system (drawer), com
    // fallback pro Plate nativo enquanto o bundle do DS não carregou.
    function DSPlate({ value, size = "sm" }) {
      const ns = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
      const P = ns.PlacaVeiculo;
      return P ? <P placa={value} size={size} uf="SP" /> : <Plate value={value} />;
    }

    // ── Wrappers de componentes do Design System (com fallback nativo) ──
    function DSBtn({ variant = "ghost", size = "sm", onClick, style, children }) {
      const B = (window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}).Button;
      if (B) return <B variant={variant} size={size} onClick={onClick} style={style}>{children}</B>;
      return <button className={"os-btn " + variant} style={style} onClick={onClick}>{children}</button>;
    }
    function DSAvatar({ name, ini }) {
      const A = (window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}).Avatar;
      return A ? <A name={name} size="sm" /> : <span className="ofc-mech-av">{ini}</span>;
    }
    function DSBadge({ label, tone }) {
      const S = (window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}).StatusBadge;
      if (S) return <S label={label} tone={tone} />;
      return <span className="ofc-stage-chip">{label}</span>;
    }

    function MechAv({ id }) {
      const m = mechOf(id);
      if (!m) return <span className="ofc-row dim"><Ico.wrench size={11}/>Sem mecânico</span>;
      return (
        <span className="ofc-row">
          <DSAvatar name={m.nome} ini={m.ini}/>
          <span className="ofc-mech-name">{m.nome}</span>
        </span>
      );
    }

    function StageChip({ stage }) {
      const s = BOARD.find(x => x.id === stage);
      if (!s) return null;
      const tone = {recepcao:'neutral', diagnostico:'info', aprovacao:'warning', pecas:'warning', execucao:'info', pronto:'success', entregue:'success', cancelado:'danger', garantia:'danger'}[stage] || 'outline';
      return (
        <span className="ofc-stage-chip" style={{background:'transparent', border:0, padding:0}}>
          <DSBadge label={s.label} tone={tone}/>
        </span>
      );
    }

    // Mock "last activity" per OS for density=detalhe mode
    const LAST_ACTIVITY = {
      "9012": "Chave entregue · caçamba cheia no pátio",
      "9015": "Aguarda triagem · frota agendada",
      "9008": "Scanner conectado · lendo turbo",
      "9005": "Test drive carregado · ruído confirmado",
      "8998": "Orçamento hidráulico enviado 10:40",
      "8994": "WhatsApp ao cliente 17:12 ontem",
      "8990": "Kit embreagem 430mm a caminho",
      "8986": "Bicos no balcão · pronto p/ montar",
      "8980": "Cremalheira removida · soldando base",
      "8975": "2º tambor sendo retificado",
      "8968": "Cliente avisado · aguarda guincho",
      "8960": "Entregue · NF emitida 09:05",
      "8955": "Cliente recusou orçamento de motor",
      "8948": "Retorno garantia · reavaliando cardã",
    };
    const PHOTO_TAG = {
      "9012": "hidrául.", "9015": "frota", "9008": "turbo",
      "9005": "difer.", "8998": "cilindro", "8994": "molas",
      "8990": "embr.", "8986": "bicos", "8980": "cremalh.",
      "8975": "freio", "8968": "susp.", "8960": "revis.",
      "8955": "motor", "8948": "cardã",
    };

    function CardExtra({ os }) {
      return (
        <div className="ofc-card-extra">
          <div className="ofc-card-extra-thumb">{PHOTO_TAG[os.id] || "foto"}</div>
          <div className="ofc-card-extra-last"><b>últ.</b> {LAST_ACTIVITY[os.id] || "sem registro recente"}</div>
        </div>
      );
    }

    function Countdown({ deadline, urgent }) {
      if (!urgent) return null;
      // Mock countdown derived from deadline label
      const m = (deadline||"").match(/(\d{1,2})h/);
      const txt = m ? `T-${Math.max(1, 24 - parseInt(m[1]))}h` : "T-?";
      return <span className="ofc-countdown">⏱ {txt}</span>;
    }

    function BoxPill({ id }) {
      const r = recursoOf(id);
      if (!r) return null;
      return <span className={"ofc-box-pill " + r.cls}><Ico.box size={9}/>{r.label}</span>;
    }

    // ── Cards por etapa ──
    function CardRecepcao({ os, onOpen }) {
      return (
        <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
          {os.urgent && <span className="ofc-card-urgent-strip"/>}
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <span className="dim t-mute" style={{marginLeft:"auto", fontSize:"10px"}}>chegou {os.arrived}</span>
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
            </div>
          </div>
          <p className="ofc-symptom">{os.symptom}</p>
          <div className="prod-card-foot" style={{marginTop: 8}}>
            <span className="prod-deadline"><Ico.clock size={10}/> {os.deadline}<Countdown deadline={os.deadline} urgent={os.urgent}/></span>
            <DSBtn variant="primary" onClick={(e) => e.stopPropagation()}>Triagem →</DSBtn>
          </div>
          <CardExtra os={os}/>
        </div>
      );
    }

    function CardDiagnostico({ os, onOpen }) {
      return (
        <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
          {os.urgent && <span className="ofc-card-urgent-strip"/>}
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            {os.recurso && <span style={{marginLeft:"auto"}}><BoxPill id={os.recurso}/></span>}
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
            </div>
          </div>
          <p className="ofc-symptom">{os.symptom}</p>
          <div className="prod-progress" style={{marginTop:6}}>
            <div className="prod-progress-bar" style={{ width: `${os.progress}%` }}/>
            <span className="prod-progress-label">{os.progress}%</span>
          </div>
          <MechAv id={os.mech}/>
          <div className="ofc-eta-row">
            <span><b>ETA diag.</b> {os.etaDiag}</span>
            <span>prazo {os.deadline}<Countdown deadline={os.deadline} urgent={os.urgent}/></span>
          </div>
          <CardExtra os={os}/>
        </div>
      );
    }

    function CardPecas({ os, onOpen }) {
      const sLabel = {ok:"Peças OK", encomendado:"Encomendado", approval:"Aguardando aprovação"};
      const sCls   = {ok:"ok", encomendado:"warn", approval:"await"};
      return (
        <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
          {os.urgent && <span className="ofc-card-urgent-strip"/>}
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            <span style={{marginLeft:"auto", fontSize:"10.5px", color:"var(--text-mute)"}} className="mono">{os.value}</span>
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.client}</span>
            </div>
          </div>
          <p className="ofc-symptom">{os.symptom}</p>
          <div style={{display:"flex", alignItems:"center", gap:8, marginTop:6}}>
            <DSBadge label={sLabel[os.partsStatus]} tone={{ok:"success", encomendado:"warning", approval:"danger"}[os.partsStatus]}/>
            <span style={{flex:1, textAlign:"right", fontSize:"10.5px", color:"var(--text-mute)"}} className="ofc-parts-label">{os.partsLabel}</span>
          </div>
          <MechAv id={os.mech}/>
          <div className="ofc-eta-row">
            <span>prazo <b>{os.deadline}</b><Countdown deadline={os.deadline} urgent={os.urgent}/></span>
            {os.partsStatus === "ok" && <DSBtn variant="primary" onClick={(e) => e.stopPropagation()}>Iniciar →</DSBtn>}
            {os.partsStatus === "approval" && <DSBtn variant="ghost" onClick={(e) => e.stopPropagation()}>Cobrar OK</DSBtn>}
          </div>
          <CardExtra os={os}/>
        </div>
      );
    }

    function CardExecucao({ os, onOpen }) {
      return (
        <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
          {os.urgent && <span className="ofc-card-urgent-strip"/>}
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            {os.recurso && <span style={{marginLeft:"auto"}}><BoxPill id={os.recurso}/></span>}
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
            </div>
          </div>
          <p className="ofc-symptom">{os.symptom}</p>
          <div className="prod-progress" style={{marginTop:6}}>
            <div className="prod-progress-bar" style={{ width: `${os.progress}%` }}/>
            <span className="prod-progress-label">{os.progress}%</span>
          </div>
          <MechAv id={os.mech}/>
          <div className="ofc-eta-row">
            <span><b>resta</b> {os.etaDone}</span>
            <span>prazo {os.deadline}<Countdown deadline={os.deadline} urgent={os.urgent}/></span>
          </div>
          <CardExtra os={os}/>
        </div>
      );
    }

    function CardPronto({ os, onOpen }) {
      return (
        <div className={"prod-card"} onClick={() => onOpen(os)}>
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            <span style={{marginLeft:"auto", display:"inline-flex", alignItems:"center", gap:4, fontSize:"10.5px", color:"oklch(0.36 0.10 145)"}}>
              <Ico.check size={11}/>finalizado
            </span>
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.client}</span>
            </div>
          </div>
          <div className="ofc-eta-row" style={{marginTop:4}}>
            <span><b>concluído</b> {os.finishedAt}</span>
            <span className="mono">{os.value}</span>
          </div>
          <div className="prod-card-foot" style={{marginTop:8}}>
            <span className={os.paid ? "" : "prod-deadline"} style={!os.paid ? {color: "oklch(0.42 0.13 20)"} : {}}>
              {os.paid ? <><Ico.check size={10}/> Pago</> : <><Ico.alert size={10}/> Aguarda pagamento · {os.deadline}</>}
            </span>
            <DSBtn variant="primary" onClick={(e) => e.stopPropagation()}>Entregar →</DSBtn>
          </div>
        </div>
      );
    }

    function CardAprovacao({ os, onOpen }) {
      return (
        <div className={"prod-card" + (os.urgent ? " urgent" : "")} onClick={() => onOpen(os)}>
          {os.urgent && <span className="ofc-card-urgent-strip"/>}
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            <span style={{marginLeft:"auto", fontSize:"10.5px", color:"var(--text-mute)"}} className="mono">{os.value}</span>
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
            </div>
          </div>
          <p className="ofc-symptom">{os.symptom}</p>
          <div style={{display:"flex", alignItems:"center", gap:8, marginTop:6}}>
            <DSBadge label="Orçamento enviado" tone="warning"/>
            <span style={{flex:1, textAlign:"right", fontSize:"10.5px", color:"var(--text-mute)"}} className="ofc-parts-label">{os.sentVia || "WhatsApp"} · há {os.sentAgo || "2h"}</span>
          </div>
          <MechAv id={os.mech}/>
          <div className="ofc-eta-row">
            <span>prazo <b>{os.deadline}</b><Countdown deadline={os.deadline} urgent={os.urgent}/></span>
            <DSBtn variant="primary" onClick={(e) => e.stopPropagation()}>Cobrar OK</DSBtn>
          </div>
          <CardExtra os={os}/>
        </div>
      );
    }

    function CardEntregue({ os, onOpen }) {
      return (
        <div className="prod-card" onClick={() => onOpen(os)}>
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            <span style={{marginLeft:"auto", display:"inline-flex", alignItems:"center", gap:4, fontSize:"10.5px", color:"oklch(0.40 0.12 145)"}}>
              <Ico.check size={11}/>entregue
            </span>
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.client}</span>
            </div>
          </div>
          <div className="ofc-eta-row" style={{marginTop:4}}>
            <span><b>entregue</b> {os.deliveredAt}</span>
            <span className="mono">{os.value}</span>
          </div>
          <div className="prod-card-foot" style={{marginTop:8}}>
            <span style={{display:"inline-flex", alignItems:"center", gap:4, color:"oklch(0.42 0.12 145)"}}><Ico.check size={10}/> Pago · NF emitida</span>
            <DSBtn variant="ghost" onClick={(e) => e.stopPropagation()}>Comprovante</DSBtn>
          </div>
        </div>
      );
    }

    function CardCancelado({ os, onOpen }) {
      return (
        <div className="prod-card" onClick={() => onOpen(os)} style={{opacity:.82}}>
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            <span style={{marginLeft:"auto", fontSize:"10.5px", color:"var(--text-mute)"}} className="mono">{os.value}</span>
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.client}</span>
            </div>
          </div>
          <p className="ofc-symptom">{os.symptom}</p>
          <div className="ofc-eta-row" style={{marginTop:4}}>
            <span style={{display:"inline-flex", alignItems:"center", gap:4, color:"oklch(0.50 0.14 20)"}}><Ico.x size={10}/>{os.canceledReason || "Cancelada"}</span>
            <span>{os.canceledAt}</span>
          </div>
        </div>
      );
    }

    function CardGarantia({ os, onOpen }) {
      return (
        <div className="prod-card urgent" onClick={() => onOpen(os)}>
          <span className="ofc-card-urgent-strip"/>
          <div className="prod-card-top">
            <span className="prod-os">OS #{os.id}</span>
            <StageChip stage={os.stage}/>
            <span style={{marginLeft:"auto", display:"inline-flex", alignItems:"center", gap:4, fontSize:"10.5px", color:"oklch(0.52 0.15 50)"}}>
              <Ico.alert size={11}/>garantia
            </span>
          </div>
          <div className="ofc-veh-row">
            <Plate value={os.plate}/>
            <div className="ofc-veh-meta">
              <span className="ofc-veh-name">{os.veh}</span>
              <span className="ofc-veh-sub">{os.km} km · {os.client}</span>
            </div>
          </div>
          <p className="ofc-symptom">{os.warrantyReason || os.symptom}</p>
          <MechAv id={os.mech}/>
          <div className="ofc-eta-row">
            <span><b>aberta</b> {os.openedAt}</span>
            <DSBtn variant="primary" onClick={(e) => e.stopPropagation()}>Reabrir →</DSBtn>
          </div>
        </div>
      );
    }

    function CardSwitch({ os, onOpen }) {
      switch (os.stage) {
        case "recepcao":    return <CardRecepcao os={os} onOpen={onOpen}/>;
        case "diagnostico": return <CardDiagnostico os={os} onOpen={onOpen}/>;
        case "aprovacao":   return <CardAprovacao os={os} onOpen={onOpen}/>;
        case "pecas":       return <CardPecas os={os} onOpen={onOpen}/>;
        case "execucao":    return <CardExecucao os={os} onOpen={onOpen}/>;
        case "pronto":      return <CardPronto os={os} onOpen={onOpen}/>;
        case "entregue":    return <CardEntregue os={os} onOpen={onOpen}/>;
        case "cancelado":   return <CardCancelado os={os} onOpen={onOpen}/>;
        case "garantia":    return <CardGarantia os={os} onOpen={onOpen}/>;
        default: return null;
      }
    }

    function ProdColumn({ stage, items, capacity, onOpen }) {
      return (
        <section className={"prod-col prod-col-" + stage.dot}>
          <header className="prod-col-head">
            <div className="prod-col-head-l">
              <span className={"prod-col-dot " + stage.dot}/>
              <h3>{stage.label}</h3>
              <span className="prod-col-count">{items.length}</span>
            </div>
            {capacity && <span className="prod-col-cap">{capacity}</span>}
          </header>
          <div className="prod-col-body">
            {items.length === 0 && <div className="prod-empty" style={{fontSize:"11px", color:"var(--text-mute)", padding:"14px 8px", textAlign:"center"}}>—</div>}
            {items.map(os => <CardSwitch key={os.id} os={os} onOpen={onOpen}/>)}
          </div>
        </section>
      );
    }

    // ── Drawer ──
    function Drawer({ os, onClose }) {
      if (!os) return null;
      const r = recursoOf(os.recurso);
      const m = mechOf(os.mech);

      // Mock parts list per OS
      const parts = {
        "8990": [
          { name: "Kit embreagem 430mm (platô+disco+rolamento)", qty: "1 jg", stat: "warn", statL: "encomendado" },
          { name: "Atuador hidráulico da embreagem",             qty: "1 un", stat: "ok",   statL: "estoque" },
          { name: "Óleo de câmbio 80W90",                        qty: "6 L",  stat: "ok",   statL: "estoque" },
          { name: "Mão de obra",                                 qty: "8 h",  stat: "ok",   statL: "estimado" },
        ],
        "8998": [
          { name: "Cilindro hidráulico de basculamento", qty: "1 un", stat: "wait", statL: "ag. aprovação" },
          { name: "Kit de vedação hidráulica",           qty: "1 jg", stat: "wait", statL: "ag. aprovação" },
          { name: "Mangueiras de alta pressão",          qty: "4 un", stat: "wait", statL: "ag. aprovação" },
          { name: "Óleo hidráulico ISO 68",              qty: "40 L", stat: "wait", statL: "ag. aprovação" },
          { name: "Mão de obra",                         qty: "12 h", stat: "wait", statL: "ag. aprovação" },
        ],
        "8994": [
          { name: "Feixe de molas dianteiro (par)", qty: "2 un", stat: "wait", statL: "ag. aprovação" },
          { name: "Jumelos + buchas",               qty: "1 jg", stat: "wait", statL: "ag. aprovação" },
          { name: "Mão de obra",                    qty: "6 h",  stat: "wait", statL: "ag. aprovação" },
        ],
      };
      const partsList = parts[os.id] || [
        { name: "Mão de obra",  qty: "—", stat: "ok", statL: "estimado" },
        { name: "Itens da OS",  qty: "—", stat: "ok", statL: "estoque" },
      ];

      // Mock timeline
      const isLateral = os.stage === "cancelado" || os.stage === "garantia";
      const pipeIdx = STAGES.findIndex(x => x.id === os.stage);
      const reached = (st) => !isLateral && pipeIdx >= STAGES.findIndex(x => x.id === st);
      const tl = isLateral ? [
        { when: "Hoje " + (os.arrived || "08:14"), what: "Veículo recepcionado", by: "Larissa (recep.)", status: "done" },
        { when: "Hoje 10:45", what: "Diagnóstico realizado", by: m?.nome || "—", status: "done" },
        os.stage === "cancelado"
          ? { when: os.canceledAt || "—", what: os.canceledReason || "OS cancelada", by: "Gerente", status: "now" }
          : { when: os.openedAt || "—", what: os.warrantyReason || "Garantia acionada", by: m?.nome || "—", status: "now" },
      ] : [
        { when: "Hoje " + (os.arrived || "08:14"), what: "Veículo recepcionado", by: "Larissa (recep.)", status: "done" },
        ...(reached("diagnostico") ? [
          { when: "Hoje 09:10", what: "Diagnóstico iniciado", by: m?.nome || "—", status: os.stage === "diagnostico" ? "now" : "done" },
        ] : []),
        ...(reached("aprovacao") ? [
          { when: "Hoje 10:45", what: "Orçamento enviado pra aprovação", by: m?.nome || "—", status: os.stage === "aprovacao" ? "now" : "done" },
        ] : []),
        ...(os.stage === "aprovacao" ? [
          { when: "agora", what: "Aguardando OK do cliente (WhatsApp)", by: os.client, status: "now" },
        ] : []),
        ...(reached("pecas") ? [
          { when: "Hoje 13:20", what: "Cliente aprovou orçamento", by: "WhatsApp", status: "done" },
        ] : []),
        ...(reached("pecas") ? [
          { when: os.stage === "pecas" ? "agora" : "Hoje 13:40", what: os.stage === "pecas" ? "Aguardando peças" : "Peças disponíveis", by: m?.nome || "—", status: os.stage === "pecas" ? "now" : "done" },
        ] : []),
        ...(reached("execucao") ? [
          { when: os.stage === "execucao" ? "agora" : "Hoje 14:30", what: "Execução do serviço", by: m?.nome || "—", status: os.stage === "execucao" ? "now" : "done" },
        ] : []),
        ...(reached("pronto") ? [
          { when: os.finishedAt || "Hoje 16:00", what: "Serviço concluído", by: m?.nome || "—", status: "done" },
        ] : []),
        ...(os.stage === "pronto" ? [
          { when: "—", what: "Aguardando retirada", by: os.client, status: "now" },
        ] : []),
        ...(os.stage === "entregue" ? [
          { when: os.deliveredAt || "Hoje", what: "Entregue ao cliente", by: m?.nome || "—", status: "done" },
        ] : []),
      ];

      const showApproval = os.stage === "aprovacao";

      return (
        <div className="prod-drawer-backdrop" onClick={onClose}>
          <aside className="prod-drawer" data-stage={os.stage} onClick={e => e.stopPropagation()}>
            <header className="prod-drawer-head">
              <div>
                <div className="prod-drawer-eyebrow">OS #{os.id} · {BOARD.find(s => s.id === os.stage)?.label}</div>
                <h2 style={{margin:"4px 0 2px"}}>{os.veh}</h2>
                <p style={{margin:0, fontSize:"12px", color:"var(--text-dim)"}}>{os.client}</p>
              </div>
              <button className="icon-btn" onClick={onClose}><Ico.x size={14}/></button>
            </header>

            <div className="prod-drawer-body">
              {/* Vehicle card */}
              <div className="ofc-veh-card">
                <DSPlate value={os.plate} size="sm"/>
                <dl>
                  <dt>KM</dt><dd className="mono">{os.km}</dd>
                  <dt>Box</dt><dd>{r ? r.label : "— (sem alocação)"}</dd>
                  <dt>Mecânico</dt><dd>{m ? m.nome : "—"}</dd>
                  <dt>Valor</dt><dd className="mono">{os.value}</dd>
                </dl>
              </div>

              {/* Symptom */}
              <div className="ofc-drawer-section">
                <h4>Sintoma reportado</h4>
                <p style={{margin:0, fontSize:"13px", lineHeight:1.45, color:"var(--text)"}}>{os.symptom}</p>
              </div>

              {/* Vistoria Digital · DVI */}
              <div className="ofc-drawer-section">
                <div className="ofc-dvi-head">
                  <h4>Vistoria Digital · DVI</h4>
                  <DSBtn variant="ghost"><Ico.plus size={10}/>Item</DSBtn>
                </div>
                <div className="ofc-dvi-counters">
                  {[["ok", "success", os.dviOk || 0], ["atenção", "warning", os.dviWarn || 0], ["crítico", "danger", os.dviBad || 0]].map(([lbl, tone, n]) => (
                    <DSBadge key={lbl} label={n + " " + lbl} tone={n > 0 ? tone : "outline"}/>
                  ))}
                </div>
                {((os.dviOk || 0) + (os.dviWarn || 0) + (os.dviBad || 0)) === 0
                  ? <div className="ofc-dvi-empty">— vistoria ainda não iniciada. <a>Adicionar primeiro item</a></div>
                  : <div className="ofc-dvi-empty">{(os.dviOk||0)+(os.dviWarn||0)+(os.dviBad||0)} itens vistoriados · {os.dviBad||0} crítico(s) recomendado(s) ao cliente.</div>}
              </div>

              {/* Total recomendado · cliente */}
              {(() => {
                const dviTotal = os.dviTotal || 0;
                return (
              <div className="ofc-total-card">
                <div>
                  <div className="ofc-total-label">Total recomendado · Cliente</div>
                  <div className={"ofc-total-value mono" + (dviTotal > 0 ? " is-positive" : "")}>{valorBR(dviTotal)}</div>
                </div>
                <button className="ofc-approve-btn"><Ico.msg size={12}/>Pedir aprovação</button>
              </div>
                );
              })()}

              {/* Approval banner */}
              {showApproval && (
                <div className="ofc-approval pending">
                  <Ico.alert size={14}/>
                  <div style={{flex:1}}>
                    <b>Aguardando aprovação do cliente.</b> Orçamento enviado por WhatsApp há 2h.
                  </div>
                  <DSBtn variant="ghost">Cobrar</DSBtn>
                </div>
              )}

              {/* Photos / laudo */}
              <div className="ofc-drawer-section">
                <h4>Fotos & Laudo</h4>
                <div className="ofc-photos">
                  <div className="ofc-photo">FOTO·1<br/>frente</div>
                  <div className="ofc-photo">FOTO·2<br/>painel</div>
                  <div className="ofc-photo">FOTO·3<br/>peça</div>
                </div>
                <DSBtn variant="ghost" style={{marginTop:8}}><Ico.camera size={11}/>Adicionar foto</DSBtn>
              </div>

              {/* Parts */}
              <div className="ofc-drawer-section">
                <h4>Peças & Mão de obra</h4>
                <ul className="ofc-parts-list">
                  {partsList.map((p, i) => (
                    <li key={i}>
                      <span>{p.name}</span>
                      <span className="qty">{p.qty}</span>
                      <span className={"stat " + p.stat}>{p.statL}</span>
                    </li>
                  ))}
                </ul>
              </div>

              {/* Timeline */}
              <div className="ofc-drawer-section">
                <h4>Linha do tempo</h4>
                {(window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}).Timeline
                  ? React.createElement(window.OfficeImpressoPontoWR2DesignSystem_019dd0.Timeline, { dense: true, entries: tl.map((t, i) => ({ id: i, time: t.when, actor: t.by, action: t.what, tone: t.status === "now" ? "accent" : "success" })) })
                  : (
                    <div className="ofc-timeline">
                      {tl.map((t, i) => (
                        <div key={i} className={"ofc-tl-item " + t.status}>
                          <div className="ofc-tl-when">{t.when}</div>
                          <div className="ofc-tl-what">{t.what}</div>
                          <div className="ofc-tl-by">{t.by}</div>
                        </div>
                      ))}
                    </div>
                  )}
              </div>

              {/* Actions */}
              <div className="prod-drawer-actions" style={{marginTop:14, display:"flex", gap:8, flexWrap:"wrap", alignItems:"center"}}>
                <DSBtn variant="ghost"><Ico.msg size={11}/>Conversa cliente</DSBtn>
                <DSBtn variant="ghost"><Ico.file size={11}/>Imprimir OS</DSBtn>
                <span style={{flex:1}}/>
                {(STAGE_ACTIONS[os.stage] || []).map((a, idx) => (
                  <DSBtn key={idx} variant={a.variant}>{a.label}</DSBtn>
                ))}
              </div>
            </div>
          </aside>
        </div>
      );
    }

    // ── Página principal ──
    function ProducaoOficina({ foco }) {
      const [recursoFilter, setRecursoFilter] = useState("all");
      const [stageFilter, setStageFilter] = useState("all");
      const [view, setView] = useState("kanban");
      const [open, setOpen] = useState(null);

      const filtered = useMemo(() => {
        return OS_LIST.filter(o =>
          (recursoFilter === "all" || o.recurso === recursoFilter) &&
          (stageFilter === "all" || o.stage === stageFilter)
        );
      }, [recursoFilter, stageFilter]);

      // Pivot do board: etapa / box / mecânico
      const pivot = useMemo(() => {
        if (foco === "box") {
          return [
            ...RECURSOS.map(r => ({
              id: r.id, label: r.label,
              dot: r.cls === "box" ? "rose" : "indigo",
              filter: o => o.recurso === r.id,
            })),
            { id: "_none", label: "Sem alocação", dot: "slate", filter: o => !o.recurso },
          ];
        }
        if (foco === "mecanico") {
          return [
            ...MECANICOS.map(m => ({
              id: m.id, label: m.nome,
              dot: "indigo",
              filter: o => o.mech === m.id,
            })),
            { id: "_none", label: "Sem mecânico", dot: "slate", filter: o => !o.mech },
          ];
        }
        return BOARD.map(s => ({
          id: s.id, label: s.label, dot: s.dot,
          filter: o => o.stage === s.id,
        }));
      }, [foco]);

      const byCol = useMemo(() => {
        const m = {};
        pivot.forEach(c => { m[c.id] = filtered.filter(c.filter); });
        return m;
      }, [filtered, pivot]);

      const totals = {
        recepcao:    OS_LIST.filter(o => o.stage === "recepcao").length,
        diagnostico: OS_LIST.filter(o => o.stage === "diagnostico").length,
        aprovacao:   OS_LIST.filter(o => o.stage === "aprovacao").length,
        pecas:       OS_LIST.filter(o => o.stage === "pecas").length,
        execucao:    OS_LIST.filter(o => o.stage === "execucao").length,
        pronto:      OS_LIST.filter(o => o.stage === "pronto").length,
        entregue:    OS_LIST.filter(o => o.stage === "entregue").length,
        urgent:      OS_LIST.filter(o => o.urgent).length,
        valor:       OS_LIST.reduce((a,o) => a + valorNumOf(o.value), 0),
      };

      return (
        <div className="prod-page" data-screen-label="01 Produção · Oficina">
          {/* Header */}
          <div className="prod-header">
            <div className="prod-header-l">
              <h1>Produção · Oficina</h1>
              <p>Recepção, diagnóstico, peças, execução e entrega de veículos</p>
            </div>
            <div className="prod-header-r">
              <select className="ofc-stage-filter" value={stageFilter}
                      onChange={e => setStageFilter(e.target.value)}
                      title="Filtrar por etapa">
                <option value="all">Todas as etapas</option>
                {BOARD.map(s => <option key={s.id} value={s.id}>{s.label}</option>)}
              </select>
              <div className="prod-view-toggle">
                <button className={view === "kanban" ? "active" : ""} onClick={() => setView("kanban")}>
                  <Ico.grid size={11}/>Kanban
                </button>
                <button className={view === "list" ? "active" : ""} onClick={() => setView("list")}>
                  <Ico.list size={11}/>Lista
                </button>
              </div>
              <DSBtn variant="ghost"><Ico.print size={11}/>Imprimir fila</DSBtn>
              <DSBtn variant="primary"><Ico.plus size={11}/>Nova OS</DSBtn>
            </div>
          </div>

          {/* KPIs */}
          <div className="prod-kpis">
            <div className="prod-kpi">
              <span className="prod-kpi-label">Recepção</span>
              <span className="prod-kpi-value">{totals.recepcao}</span>
              <span className="prod-kpi-sub">caminhões aguardando triagem</span>
            </div>
            <div className="prod-kpi">
              <span className="prod-kpi-label">Em diagnóstico</span>
              <span className="prod-kpi-value">{totals.diagnostico}</span>
              <span className="prod-kpi-sub">{RECURSOS.length} valas/elevadores</span>
            </div>
            <div className="prod-kpi">
              <span className="prod-kpi-label">Aguard. aprovação</span>
              <span className="prod-kpi-value">{totals.aprovacao}</span>
              <span className="prod-kpi-sub">orçamentos com o cliente</span>
            </div>
            <div className="prod-kpi">
              <span className="prod-kpi-label">Aguardando peças</span>
              <span className="prod-kpi-value">{totals.pecas}</span>
              <span className="prod-kpi-sub">aguardando suprimento</span>
            </div>
            <div className="prod-kpi">
              <span className="prod-kpi-label">Em execução</span>
              <span className="prod-kpi-value">{totals.execucao}</span>
              <span className="prod-kpi-sub">valas ocupadas agora</span>
            </div>
            <div className="prod-kpi prod-kpi-urgent">
              <span className="prod-kpi-label">Urgentes</span>
              <span className="prod-kpi-value">{totals.urgent}</span>
              <span className="prod-kpi-sub">prazo crítico</span>
            </div>
            <div className="prod-kpi">
              <span className="prod-kpi-label">Valor em curso</span>
              <span className="prod-kpi-value">{valorBR(totals.valor).replace(",00","")}</span>
              <span className="prod-kpi-sub">faturamento previsto</span>
            </div>
          </div>

          {/* Filter por recurso (box/elevador) */}
          <div className="prod-equip-filters">
            <button className={"prod-equip-tab" + (recursoFilter === "all" ? " active" : "")}
                    onClick={() => setRecursoFilter("all")}>
              Todos os boxes <span className="count">{OS_LIST.length}</span>
            </button>
            {RECURSOS.map(r => {
              const n = OS_LIST.filter(o => o.recurso === r.id).length;
              return (
                <button key={r.id}
                        className={"prod-equip-tab" + (recursoFilter === r.id ? " active" : "")}
                        onClick={() => setRecursoFilter(r.id)}>
                  <span className={"prod-equip-dot " + r.cls}/>
                  {r.label} <span className="count">{n}</span>
                </button>
              );
            })}
          </div>

          {/* Kanban */}
          {view === "kanban" && (
            <div className="prod-kanban ofc-many"
                 style={{ "--ofc-cols": pivot.length }}>
              {pivot.map(c => (
                <ProdColumn
                  key={c.id}
                  stage={{ id: c.id, label: c.label, dot: c.dot }}
                  items={byCol[c.id] || []}
                  capacity={foco === "etapa" && c.id === "execucao" ? `${byCol.execucao.length}/${RECURSOS.length} boxes` : null}
                  onOpen={setOpen}
                />
              ))}
            </div>
          )}

          {/* Lista */}
          {view === "list" && (
            <div className="prod-list">
              <table className="os-table">
                <thead>
                  <tr>
                    <th>OS</th><th>Placa</th><th>Veículo</th><th>Cliente</th>
                    <th>Etapa</th><th>Box</th><th>Mecânico</th><th>Prazo</th><th style={{textAlign:"right"}}>Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map(os => {
                    const r = recursoOf(os.recurso);
                    const m = mechOf(os.mech);
                    const s = BOARD.find(x => x.id === os.stage);
                    return (
                      <tr key={os.id} onClick={() => setOpen(os)} style={{cursor:"pointer"}}>
                        <td className="mono">#{os.id}</td>
                        <td><Plate value={os.plate}/></td>
                        <td>{os.veh} <small style={{color:"var(--text-mute)"}}>{os.km} km</small></td>
                        <td>{os.client}</td>
                        <td><span className="stage-pill" style={{display:"inline-flex", alignItems:"center", gap:5}}><span className={"prod-col-dot " + s.dot}/>{s.label}</span></td>
                        <td>{r ? r.label : "—"}</td>
                        <td>{m ? m.nome : "—"}</td>
                        <td>{os.deadline}</td>
                        <td className="mono" style={{textAlign:"right"}}>{os.value}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}

          <Drawer os={open} onClose={() => setOpen(null)}/>
        </div>
      );
    }

    // ── Tela pública de aprovação (cliente · link WhatsApp + PIN) ──
    // Espelha Public/AprovacaoOsController + AprovacaoOsService: token HMAC + PIN
    // de 4 dígitos, 5 tentativas → lockout 30min, aprovar/recusar.
    const APROV_OS = {
      numero: "8998",
      veh: "Ford Cargo 2429", plate: "OWD-5R09", tipo: "Caminhão basculante",
      entrada: "24/06 · 07:48", previsao: "26/06 · 18h",
      servico: "Reforma completa do sistema hidráulico de basculamento",
      itens: [
        { nome: "Cilindro hidráulico de basculamento", qty: "1 un", valor: 3200 },
        { nome: "Kit de vedação hidráulica",           qty: "1 jg", valor: 480 },
        { nome: "Mangueiras de alta pressão",          qty: "4 un", valor: 760 },
        { nome: "Óleo hidráulico ISO 68",              qty: "40 L", valor: 610 },
        { nome: "Mão de obra",                         qty: "12 h", valor: 1800 },
      ],
      total: 6850,
    };

    function PinBoxes({ value, onChange, disabled }) {
      const refs = React.useRef([]);
      const set = (i, ch) => {
        ch = (ch || "").replace(/\D/g, "").slice(0, 1);
        const next = value.slice(); next[i] = ch; onChange(next);
        if (ch && i < 3 && refs.current[i + 1]) refs.current[i + 1].focus();
      };
      const onKey = (i, e) => {
        if (e.key === "Backspace" && !value[i] && i > 0 && refs.current[i - 1]) refs.current[i - 1].focus();
      };
      return (
        <div className="aprov-pin">
          {[0, 1, 2, 3].map(i => (
            <input key={i} ref={el => { refs.current[i] = el; }} inputMode="numeric"
                   maxLength={1} disabled={disabled} value={value[i]} className="aprov-pin-box"
                   onChange={e => set(i, e.target.value)} onKeyDown={e => onKey(i, e)}/>
          ))}
        </div>
      );
    }

    function AprovacaoPublica({ linkEstado, tema }) {
      const [pin, setPin] = useState(["", "", "", ""]);
      const [left, setLeft] = useState(5);
      const [result, setResult] = useState(null);
      const [err, setErr] = useState("");
      const dark = tema === "escuro";
      const CORRECT = "4729";

      if (linkEstado === "expirado") {
        return (
          <div className="aprov-wrap" data-theme={dark ? "dark" : undefined}>
            <div className="aprov-card aprov-empty">
              <div className="aprov-empty-ico"><Ico.alert size={26}/></div>
              <h2>Link expirado ou inválido</h2>
              <p>Este link de aprovação expirou ou já foi utilizado. Fale com a oficina pra receber um novo link.</p>
              <div className="aprov-oficina-foot">Martinho Caçambas · Mecânica Pesada · (11) 4002-8922</div>
            </div>
          </div>
        );
      }

      if (result === "aprovado" || result === "recusado") {
        const ok = result === "aprovado";
        return (
          <div className="aprov-wrap" data-theme={dark ? "dark" : undefined}>
            <div className="aprov-card aprov-done">
              <div className={"aprov-done-ico " + (ok ? "ok" : "info")}>{ok ? <Ico.check size={28}/> : <Ico.msg size={24}/>}</div>
              <h2>{ok ? "Aprovação registrada!" : "Recusa registrada"}</h2>
              <p>{ok
                ? "A oficina foi avisada e vai iniciar o serviço. Você recebe as novidades pelo WhatsApp."
                : "Tudo bem — a oficina vai entrar em contato pra renegociar o orçamento."}</p>
              <div className="aprov-done-os">OS #{APROV_OS.numero} · {APROV_OS.veh}</div>
              <div className="aprov-oficina-foot">Martinho Caçambas · Mecânica Pesada</div>
            </div>
          </div>
        );
      }

      const locked = left === 0;
      const submit = (decisao) => {
        if (locked) return;
        if (decisao === "recusar") { setResult("recusado"); return; }
        const code = pin.join("");
        if (code.length < 4) { setErr("Digite os 4 dígitos do PIN."); return; }
        if (code === CORRECT) { setResult("aprovado"); setErr(""); return; }
        const nl = left - 1; setLeft(nl); setPin(["", "", "", ""]);
        setErr(nl === 0
          ? "Muitas tentativas inválidas. Aguarde 30 minutos pra tentar de novo."
          : "PIN inválido. Você tem " + nl + " tentativa(s) restante(s).");
      };

      return (
        <div className="aprov-wrap" data-theme={dark ? "dark" : undefined}>
          <div className="aprov-card">
            <div className="aprov-head">
              <div className="aprov-brand">
                <div className="aprov-brand-mark">M</div>
                <div>
                  <div className="aprov-brand-name">Martinho Caçambas</div>
                  <div className="aprov-brand-sub">Mecânica Pesada</div>
                </div>
              </div>
              <span className="aprov-badge">Orçamento</span>
            </div>

            <h1 className="aprov-title">Autorize o serviço do seu caminhão</h1>

            <div className="aprov-veh">
              <Plate value={APROV_OS.plate}/>
              <div className="aprov-veh-meta">
                <div className="aprov-veh-name">{APROV_OS.veh}</div>
                <div className="aprov-veh-sub">OS #{APROV_OS.numero} · {APROV_OS.tipo}</div>
              </div>
            </div>

            <div className="aprov-dates">
              <div><span>Entrada</span><b>{APROV_OS.entrada}</b></div>
              <div><span>Previsão</span><b>{APROV_OS.previsao}</b></div>
            </div>

            <div className="aprov-servico">
              <span className="aprov-lbl">Serviço</span>
              <p>{APROV_OS.servico}</p>
            </div>

            <div className="aprov-itens">
              <span className="aprov-lbl">Itens do orçamento</span>
              <ul>
                {APROV_OS.itens.map((it, i) => (
                  <li key={i}>
                    <span className="nm">{it.nome}</span>
                    <span className="qt">{it.qty}</span>
                    <span className="vl mono">{valorBR(it.valor)}</span>
                  </li>
                ))}
              </ul>
              <div className="aprov-total"><span>Total</span><b className="mono">{valorBR(APROV_OS.total)}</b></div>
            </div>

            <div className="aprov-pin-sec">
              <span className="aprov-lbl">PIN de aprovação</span>
              <p className="aprov-pin-hint">Digite o código de 4 dígitos que enviamos por SMS.</p>
              <PinBoxes value={pin} onChange={v => { setPin(v); setErr(""); }} disabled={locked}/>
              <div className="aprov-attempts">{locked ? "Bloqueado por 30 minutos" : left + " de 5 tentativas"}</div>
              {err && <div className={"aprov-err" + (locked ? " lock" : "")}>{err}</div>}
              <div className="aprov-demo">PIN de demonstração: <b>4729</b></div>
            </div>

            <div className="aprov-actions">
              <button className="aprov-btn ghost" disabled={locked} onClick={() => submit("recusar")}>Recusar</button>
              <button className="aprov-btn primary" disabled={locked} onClick={() => submit("aprovar")}>Aprovar serviço</button>
            </div>

            <div className="aprov-secure">Link seguro · expira em 7 dias</div>
          </div>
        </div>
      );
    }

    // ── Vistoria Digital (DVI) — fluxo do mecânico ──
    // Espelha OaInspectionItem + DviInspectionService: categorias, severidade
    // (ok/atencao/critico = semáforo), recomendação + valor, foto por item,
    // decisão do cliente (pending/approved/rejected), total recomendado
    // (soma valor onde severity ∈ {atencao, critico}).
    const DVI_CATS = {
      motor: "Motor", freios: "Freios", correia: "Correia", bateria: "Bateria",
      pneus: "Pneus", suspensao: "Suspensão", direcao: "Direção",
      eletrica: "Elétrica", fluidos: "Fluidos", outro: "Outro",
    };
    const DVI_OS = { numero: "8998", veh: "Ford Cargo 2429", plate: "OWD-5R09", client: "Martinho Caçambas", mech: "João Lima" };
    const DVI_SEED = [
      { id: 1, categoria: "freios",    descricao: "Pastilhas dianteiras com 20% de vida útil",       severity: "critico", recomendacao: "Trocar pastilhas e verificar disco",     valor: 680,  meta: ["vida útil 20%"], decision: "pending",  foto: "freio" },
      { id: 2, categoria: "freios",    descricao: "Vazamento leve na câmara de freio a ar (eixo 2)", severity: "critico", recomendacao: "Reparar câmara de freio",             valor: 540,  meta: [],              decision: "pending",  foto: "câmara" },
      { id: 3, categoria: "suspensao", descricao: "Lâmina do feixe de molas dianteiro trincada",     severity: "critico", recomendacao: "Substituir feixe de molas",           valor: 1850, meta: [],              decision: "approved", foto: "molas" },
      { id: 4, categoria: "pneus",     descricao: "Desgaste irregular no pneu dianteiro esquerdo",   severity: "atencao", recomendacao: "Alinhamento + rodízio",               valor: 320,  meta: ["sulco 3 mm"],   decision: "pending",  foto: "pneu" },
      { id: 5, categoria: "bateria",   descricao: "Bateria medindo 11,8 V em carga",                 severity: "atencao", recomendacao: "Testar alternador ou trocar bateria",  valor: 890,  meta: ["11,8 V"],       decision: "rejected", foto: "bateria" },
      { id: 6, categoria: "fluidos",   descricao: "Óleo hidráulico abaixo do nível mínimo",          severity: "atencao", recomendacao: "Completar e investigar vazamento",     valor: 210,  meta: [],              decision: "pending",  foto: "reservat." },
      { id: 7, categoria: "motor",     descricao: "Correia do alternador ressecada",                severity: "atencao", recomendacao: "Trocar correia do alternador",        valor: 180,  meta: [],              decision: "pending",  foto: "correia" },
      { id: 8, categoria: "direcao",   descricao: "Folga da caixa de direção dentro do limite",      severity: "ok",      recomendacao: "",                                    valor: 0,    meta: [],              decision: null,       foto: "" },
      { id: 9, categoria: "eletrica",  descricao: "Iluminação e setas — todas funcionando",          severity: "ok",      recomendacao: "",                                    valor: 0,    meta: [],              decision: null,       foto: "" },
      { id: 10, categoria: "fluidos",  descricao: "Óleo de motor trocado nesta OS",                  severity: "ok",      recomendacao: "",                                    valor: 0,    meta: [],              decision: null,       foto: "" },
      { id: 11, categoria: "correia",  descricao: "Correia dentada dentro do prazo de troca",        severity: "ok",      recomendacao: "",                                    valor: 0,    meta: [],              decision: null,       foto: "" },
    ];

    function DviSemaphore({ value, onChange }) {
      return (
        <div className="dvi-sem">
          {["ok", "atencao", "critico"].map(s => (
            <button key={s} type="button" title={s}
                    className={"dvi-sem-dot " + s + (value === s ? " on" : "")}
                    onClick={() => onChange(s)}/>
          ))}
        </div>
      );
    }

    function DviRow({ it, onSev }) {
      const decLabel = { approved: "Aprovado", rejected: "Recusado", pending: "Aguardando cliente" };
      return (
        <div className={"dvi-item sev-" + it.severity}>
          <DviSemaphore value={it.severity} onChange={s => onSev(it.id, s)}/>
          <div className="dvi-item-main">
            <div className="dvi-item-top">
              <span className="dvi-cat">{DVI_CATS[it.categoria]}</span>
              <span className="dvi-desc">{it.descricao}</span>
            </div>
            {it.severity !== "ok" && it.recomendacao && <div className="dvi-rec">→ {it.recomendacao}</div>}
            {it.meta.length > 0 && (
              <div className="dvi-meta">{it.meta.map((m, i) => <span key={i} className="dvi-chip">{m}</span>)}</div>
            )}
          </div>
          {it.foto && <div className="dvi-photo">{it.foto}</div>}
          <div className="dvi-item-right">
            {it.severity !== "ok" && <span className="dvi-val mono">{valorBR(it.valor)}</span>}
            {it.decision && <span className={"dvi-dec " + it.decision}>{decLabel[it.decision]}</span>}
          </div>
        </div>
      );
    }

    function DviInspection() {
      const [items, setItems] = useState(DVI_SEED);
      const [onlyRec, setOnlyRec] = useState(false);
      const SEV_ORDER = { critico: 0, atencao: 1, ok: 2 };
      const setSev = (id, sev) => setItems(prev => prev.map(it =>
        it.id === id ? { ...it, severity: sev, valor: sev === "ok" ? 0 : (it.valor || 0) } : it));
      const counts = { ok: 0, atencao: 0, critico: 0 };
      items.forEach(it => { counts[it.severity]++; });
      const total = items.filter(it => it.severity !== "ok").reduce((a, it) => a + (it.valor || 0), 0);
      const visible = items
        .filter(it => !onlyRec || it.severity !== "ok")
        .slice()
        .sort((a, b) => (SEV_ORDER[a.severity] - SEV_ORDER[b.severity]) || (a.id - b.id));
      return (
        <div className="dvi-page" data-screen-label="02 DVI · Vistoria Digital">
          <div className="dvi-header">
            <div>
              <div className="dvi-eyebrow">Vistoria Digital · DVI</div>
              <h1>{DVI_OS.veh}</h1>
              <div className="dvi-veh-line">
                <Plate value={DVI_OS.plate}/>
                <span>OS #{DVI_OS.numero} · {DVI_OS.client} · mec. {DVI_OS.mech}</span>
              </div>
            </div>
            <div className="dvi-header-actions">
              <DSBtn variant="ghost"><Ico.plus size={11}/>Adicionar item</DSBtn>
              <DSBtn variant="primary"><Ico.msg size={11}/>Enviar ao cliente</DSBtn>
            </div>
          </div>

          <div className="dvi-summary">
            <div className="dvi-stat ok"><span>OK</span><b>{counts.ok}</b></div>
            <div className="dvi-stat atencao"><span>Atenção</span><b>{counts.atencao}</b></div>
            <div className="dvi-stat critico"><span>Crítico</span><b>{counts.critico}</b></div>
            <div className="dvi-stat total">
              <span>Total recomendado · cliente</span>
              <b className="mono">{valorBR(total)}</b>
            </div>
          </div>

          <div className="dvi-toolbar">
            <span>{items.length} itens vistoriados · ordenados por gravidade</span>
            <label className="dvi-filter">
              <input type="checkbox" checked={onlyRec} onChange={e => setOnlyRec(e.target.checked)}/>
              Só recomendados
            </label>
          </div>

          <div className="dvi-list">
            {visible.map(it => <DviRow key={it.id} it={it} onSev={setSev}/>)}
          </div>
        </div>
      );
    }

    // ── Shell ──
    function App() {
      const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
      const shellClass = [
        "ofc-shell",
        "cockpit",
        t.acabamento === "premium" ? "ofc-premium" : "",
        "ofc-foco-" + t.foco,
        "ofc-density-" + t.densidade,
        "ofc-mood-" + t.pressao,
      ].join(" ");
      const panel = (
          <TweaksPanel title="Tweaks">
            <TweakSection label="Visão"/>
            <TweakRadio
              label="Tela"
              value={t.visao}
              options={[
                { value: "board",     label: "Board" },
                { value: "aprovacao", label: "Aprovação" },
                { value: "dvi",       label: "DVI" },
              ]}
              onChange={v => setTweak("visao", v)}
            />
            {t.visao === "aprovacao" && (
              <TweakRadio
                label="Estado do link"
                value={t.linkEstado}
                options={[
                  { value: "valido",   label: "Válido" },
                  { value: "expirado", label: "Expirado" },
                ]}
                onChange={v => setTweak("linkEstado", v)}
              />
            )}
            <TweakSection label="Tema"/>
            <TweakRadio
              label="Aparência"
              value={t.tema}
              options={[
                { value: "claro",  label: "Claro" },
                { value: "escuro", label: "Escuro" },
              ]}
              onChange={v => setTweak("tema", v)}
            />
            <TweakRadio
              label="Acabamento"
              value={t.acabamento}
              options={[
                { value: "padrao",  label: "Padrão" },
                { value: "premium", label: "Premium" },
              ]}
              onChange={v => setTweak("acabamento", v)}
            />
            <TweakSection label="Foco"/>
            <TweakRadio
              label="Pivot do board"
              value={t.foco}
              options={[
                { value: "etapa",    label: "Etapa" },
                { value: "box",      label: "Box" },
                { value: "mecanico", label: "Mecânico" },
              ]}
              onChange={v => setTweak("foco", v)}
            />
            <TweakSection label="Densidade"/>
            <TweakRadio
              label="Detalhe nos cards"
              value={t.densidade}
              options={[
                { value: "compacto", label: "Compacto" },
                { value: "padrao",   label: "Padrão" },
                { value: "detalhe",  label: "Detalhe" },
              ]}
              onChange={v => setTweak("densidade", v)}
            />
            <TweakSection label="Pressão"/>
            <TweakRadio
              label="Temperatura visual"
              value={t.pressao}
              options={[
                { value: "calmo",   label: "Calmo" },
                { value: "padrao",  label: "Padrão" },
                { value: "pressao", label: "Pressão" },
              ]}
              onChange={v => setTweak("pressao", v)}
            />
          </TweaksPanel>
      );

      if (t.visao === "aprovacao") {
        return (
          <>
            <AprovacaoPublica linkEstado={t.linkEstado} tema={t.tema}/>
            {panel}
          </>
        );
      }

      return (
        <div className={shellClass} data-theme={t.tema === "escuro" ? "dark" : undefined}>
          <aside className="ofc-sidebar">
            <div className="ofc-brand">
              <div className="ofc-brand-mark">O</div>
              <div>
                <div className="ofc-brand-name">Oimpresso ERP</div>
                <div className="ofc-brand-sub">Oficina · piloto</div>
              </div>
            </div>
            <h2>Operação</h2>
            <div className="ofc-nav-item"><span className="dot"/>Tarefas</div>
            <div className="ofc-nav-item"><span className="dot"/>Vendas</div>
            <div className="ofc-nav-item"><span className="dot"/>OS</div>
            <div className="ofc-nav-item active"><span className="dot"/>Produção</div>
            <div className="ofc-nav-item"><span className="dot"/>Estoque</div>
            <h2 style={{marginTop:14}}>Cadastro</h2>
            <div className="ofc-nav-item"><span className="dot"/>Clientes</div>
            <div className="ofc-nav-item"><span className="dot"/>Veículos</div>
            <div className="ofc-nav-item"><span className="dot"/>Mecânicos</div>
          </aside>
          <main className="ofc-main">
            {t.visao === "dvi" ? <DviInspection/> : <ProducaoOficina foco={t.foco}/>}
          </main>
          {panel}
        </div>
      );
    }

    window.OficinaApp = App;
