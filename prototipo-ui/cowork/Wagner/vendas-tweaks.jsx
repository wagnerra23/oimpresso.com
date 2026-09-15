// vendas-tweaks.jsx — Refino #4 polish · TweaksPanel pra Vendas (2026-05-15)
// 4 dimensões: Densidade · Largura drawer · SLA visual · Paleta accent
// Aplica via data-attrs + CSS vars no .vendas-aplus

(function() {
  const { useEffect } = React;

  const TWEAK_DEFAULTS = {
    density: "cozy",        // compact | cozy | spacious
    drawerWidth: "padrao",  // padrao | largo
    slaVisual: "pill",      // pill | dot | bar
    palette: "green",       // green | indigo | slate | amber
  };

  function VendasTweaksPanel() {
    if (!window.useTweaks || !window.TweaksPanel) return null;
    const [t, setTweak] = window.useTweaks(TWEAK_DEFAULTS);

    // Aplica em .vendas-aplus
    useEffect(() => {
      const el = document.querySelector(".vendas-aplus");
      if (!el) return;
      el.setAttribute("data-vd-density", t.density);
      el.setAttribute("data-vd-drawer", t.drawerWidth);
      el.setAttribute("data-vd-sla", t.slaVisual);
      el.setAttribute("data-vd-palette", t.palette);
    });

    const {
      TweaksPanel, TweakSection, TweakRadio, TweakSelect,
    } = window;

    return (
      <TweaksPanel title="Tweaks · Vendas">
        <TweakSection label="Densidade da tabela">
          <TweakRadio
            label="Espaçamento"
            value={t.density}
            options={[
              { value: "compact",  label: "Compacta" },
              { value: "cozy",     label: "Confortável" },
              { value: "spacious", label: "Espaçosa" },
            ]}
            onChange={v => setTweak("density", v)}/>
        </TweakSection>

        <TweakSection label="Drawer detalhe">
          <TweakRadio
            label="Largura"
            value={t.drawerWidth}
            options={[
              { value: "padrao", label: "Padrão" },
              { value: "largo",  label: "Largo" },
            ]}
            onChange={v => setTweak("drawerWidth", v)}/>
        </TweakSection>

        <TweakSection label="SLA visual">
          <TweakRadio
            label="Como mostrar"
            value={t.slaVisual}
            options={[
              { value: "pill", label: "Pill" },
              { value: "dot",  label: "Dot+txt" },
              { value: "bar",  label: "Barra" },
            ]}
            onChange={v => setTweak("slaVisual", v)}/>
        </TweakSection>

        <TweakSection label="Paleta accent">
          <TweakSelect
            label="Cor do módulo"
            value={t.palette}
            options={[
              { value: "green",  label: "Forest green (padrão)" },
              { value: "indigo", label: "Indigo" },
              { value: "slate",  label: "Slate" },
              { value: "amber",  label: "Amber" },
            ]}
            onChange={v => setTweak("palette", v)}/>
        </TweakSection>
      </TweaksPanel>
    );
  }

  // Auto-mount num portal dedicado ao final do body
  window.VendasTweaksPanel = VendasTweaksPanel;

  // Helper: monta numa div dedicada (só quando vendas-aplus está na tela)
  function mountIfVendas() {
    if (!document.querySelector(".vendas-aplus")) return;
    if (document.getElementById("__vd_tweaks_root")) return;
    const host = document.createElement("div");
    host.id = "__vd_tweaks_root";
    document.body.appendChild(host);
    ReactDOM.createRoot(host).render(<VendasTweaksPanel/>);
  }
  function unmountIfNotVendas() {
    if (document.querySelector(".vendas-aplus")) return;
    const host = document.getElementById("__vd_tweaks_root");
    if (host) host.remove();
  }
  // Observer pra montar/desmontar conforme navega
  const obs = new MutationObserver(() => {
    mountIfVendas();
    unmountIfNotVendas();
  });
  if (document.body) obs.observe(document.body, { childList: true, subtree: true });
  else document.addEventListener("DOMContentLoaded", () => obs.observe(document.body, { childList: true, subtree: true }));
})();
