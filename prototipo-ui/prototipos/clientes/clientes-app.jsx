// Root Clientes app — composes sidebar + clientes page + linked apps + drawer + tweaks.

const { useState: useSCA, useEffect: useECA } = React;

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "light",
  "density": "normal",
  "layoutMode": "table",
  "accent": [
    "oklch(0.58 0.09 220)",
    "oklch(0.66 0.09 220)",
    "oklch(0.94 0.025 220)"
  ],
  "listState": "cheia"
}/*EDITMODE-END*/;

const ACCENT_OPTS = [
  ['oklch(0.58 0.09 220)', 'oklch(0.66 0.09 220)', 'oklch(0.94 0.025 220)'],
  ['oklch(0.58 0.16 150)', 'oklch(0.66 0.14 150)', 'oklch(0.94 0.03 150)'],
  ['oklch(0.62 0.18 30)',  'oklch(0.68 0.16 30)',  'oklch(0.94 0.04 35)'],
  ['oklch(0.55 0.18 290)', 'oklch(0.64 0.16 290)', 'oklch(0.94 0.04 290)'],
];

function ClientesApp() {
  const [tweaks, setTweak] = window.useTweaks
    ? window.useTweaks(TWEAK_DEFAULTS)
    : [TWEAK_DEFAULTS, () => {}];

  const [sbTab, setSbTab] = useSCA('menu');
  const [focused, setFocused] = useSCA(null);   // cliente id selected/previewed
  const [drawerFor, setDrawerFor] = useSCA(null); // cliente id or 'new'
  const [cmdkOpen, setCmdkOpen] = useSCA(false);
  const [cheatOpen, setCheatOpen] = useSCA(false);
  const [favoritos, toggleFav] = window.KB.useFavoritos();

  // Esc closes drawer / cmdk / cheatsheet
  useECA(() => {
    const k = (e) => {
      if (e.key === 'Escape') {
        if (cmdkOpen) setCmdkOpen(false);
        else if (cheatOpen) setCheatOpen(false);
        else if (drawerFor) setDrawerFor(null);
      }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [cmdkOpen, cheatOpen, drawerFor]);

  // J/K row navigation when no drawer open
  useECA(() => {
    if (drawerFor || cmdkOpen || cheatOpen) return;
    const onKey = (e) => {
      const inField = e.target.matches('input, textarea, select') || e.target.isContentEditable;
      if (inField) return;
      const ids = window.CLIENTES.map(c => c.id);
      const idx = focused ? ids.indexOf(focused) : -1;
      if (e.key === 'j') { e.preventDefault(); setFocused(ids[Math.min(ids.length - 1, idx + 1)] || ids[0]); }
      if (e.key === 'k') { e.preventDefault(); setFocused(ids[Math.max(0, idx - 1)] || ids[0]); }
      if (e.key === 'Enter' && focused) { e.preventDefault(); setDrawerFor(focused); }
      if (e.key === 'n') { e.preventDefault(); setDrawerFor('new'); }
      if (e.key === 'f' && focused) { e.preventDefault(); toggleFav(focused); }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [focused, drawerFor, cmdkOpen, cheatOpen, toggleFav]);

  // ⌘K / / / ?
  window.KB.useGlobalKeys({
    onCmdK: () => setCmdkOpen(true),
    onSlash: () => {
      const el = document.querySelector('.cl-search input');
      if (el) { el.focus(); }
    },
    onHelp: () => setCheatOpen(true),
  });

  // Reset focused if cliente disappears from filtered set — handled by table
  const focusedCliente = focused ? window.CLIENTES.find(c => c.id === focused) : null;

  const onPickCliente = (id) => {
    if (id === 'new') {
      setDrawerFor('new');
      return;
    }
    if (focused === id) {
      setDrawerFor(id);
    } else {
      setFocused(id);
    }
  };

  const [a, a2, soft] = tweaks.accent || ACCENT_OPTS[0];
  const accentStyle = {
    '--accent': a,
    '--accent-2': a2,
    '--accent-soft': soft,
    '--bubble-me': a,
  };

  return (
    <div className="cockpit"
      data-linked="off"
      data-density={tweaks.density}
      data-theme={tweaks.theme}
      data-screen-label="Clientes"
      style={accentStyle}
    >
      <Sidebar
        tab={sbTab} setTab={setSbTab}
        conversations={window.CONVERSATIONS || []}
        activeId={null}
        onPick={() => {}}
        menuActive="vendas.clientes"
      />

      <div className="main" style={{ gridTemplateRows: '1fr' }}>
        <div className="main-body" style={{ position: 'relative' }}>
          <ClientesPage
            onPickCliente={onPickCliente}
            focused={focused}
            density={tweaks.density}
            layoutMode={tweaks.layoutMode}
            listState={tweaks.listState}
            favoritos={favoritos}
            onToggleFav={toggleFav}
            onOpenCmdK={() => setCmdkOpen(true)}
            onOpenCheat={() => setCheatOpen(true)}
          />
          {drawerFor && (
            <ClienteDrawer
              cliente={drawerFor === 'new' ? 'new' : window.CLIENTES.find(c => c.id === drawerFor)}
              onClose={() => setDrawerFor(null)}
              onSaved={() => setDrawerFor(null)}
            />
          )}
        </div>
      </div>

      {/* Apps Vinculados removida da tela de Clientes (decisão #133). */}

      {/* KB-9.75 Refino #1 surfaces */}
      <window.KB.CommandPalette
        open={cmdkOpen}
        onClose={() => setCmdkOpen(false)}
        onPick={(id) => { setFocused(id); setDrawerFor(id); }}
        onNewCliente={() => setDrawerFor('new')}
        onSearchAsk={(q) => {
          const el = document.querySelector('.cl-search input');
          if (el) { el.focus(); el.value = q; el.dispatchEvent(new Event('input', { bubbles: true })); }
        }}
      />
      <window.KB.CheatSheet open={cheatOpen} onClose={() => setCheatOpen(false)} />
      <window.KB.ShortcutHint onOpen={() => setCheatOpen(true)} />

      {window.TweaksPanel && (
        <window.TweaksPanel title="Tweaks">
          <window.TweakSection label="Aparência">
            <window.TweakRadio label="Tema" value={tweaks.theme}
              onChange={v => setTweak('theme', v)}
              options={[{ value: 'light', label: 'Claro' }, { value: 'dark', label: 'Escuro' }]} />
            <window.TweakRadio label="Densidade" value={tweaks.density}
              onChange={v => setTweak('density', v)}
              options={[
                { value: 'compact', label: 'Compacta' },
                { value: 'normal', label: 'Normal' },
                { value: 'comfy', label: 'Confort.' },
              ]} />
            <window.TweakColor label="Accent" value={tweaks.accent}
              onChange={v => setTweak('accent', v)} options={ACCENT_OPTS} />
          </window.TweakSection>
          <window.TweakSection label="Listagem">
            <window.TweakRadio label="Layout" value={tweaks.layoutMode}
              onChange={v => setTweak('layoutMode', v)}
              options={[
                { value: 'table', label: 'Tabela' },
                { value: 'cards', label: 'Cards' },
                { value: 'split', label: 'Split' },
              ]} />
            <window.TweakSelect label="Estado" value={tweaks.listState}
              onChange={v => setTweak('listState', v)}
              options={[
                { value: 'cheia', label: 'Lista cheia (32 clientes)' },
                { value: 'vazio', label: 'Vazio (primeiro acesso)' },
                { value: 'loading', label: 'Loading (skeleton)' },
              ]} />
          </window.TweakSection>
        </window.TweaksPanel>
      )}
    </div>
  );
}

const rootCl = ReactDOM.createRoot(document.getElementById('root'));
rootCl.render(<ClientesApp />);
