// Ícones próprios do módulo Cobrança Recorrente.
// Exposto como REC_I para coexistir com `I` global do shell.
(() => {
  const Svg = ({ size = 14, children, ...rest }) => (
    <svg xmlns="http://www.w3.org/2000/svg" width={size} height={size} viewBox="0 0 24 24"
         fill="none" stroke="currentColor" strokeWidth="2"
         strokeLinecap="round" strokeLinejoin="round" {...rest}>
      {children}
    </svg>
  );

  const I = {
    refresh:  (p) => <Svg {...p}><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></Svg>,
    retry:    (p) => <Svg {...p}><path d="M21 12a9 9 0 1 1-3.5-7.1"/><polyline points="21 3 21 9 15 9"/></Svg>,
    pause:    (p) => <Svg {...p}><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></Svg>,
    play:     (p) => <Svg {...p}><polygon points="6 4 20 12 6 20 6 4"/></Svg>,
    pix:      (p) => <Svg {...p}><path d="M12 2 22 12 12 22 2 12 12 2z"/><path d="M7 7l5 5 5-5"/><path d="M7 17l5-5 5 5"/></Svg>,
    boleto:   (p) => <Svg {...p}><rect x="3" y="6" width="18" height="12" rx="1"/><line x1="7" y1="6" x2="7" y2="18"/><line x1="11" y1="6" x2="11" y2="18"/><line x1="15" y1="6" x2="15" y2="18"/><line x1="17" y1="6" x2="17" y2="18"/></Svg>,
    card:     (p) => <Svg {...p}><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></Svg>,
    bell:     (p) => <Svg {...p}><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></Svg>,
    calendar: (p) => <Svg {...p}><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></Svg>,
    trendUp:  (p) => <Svg {...p}><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></Svg>,
    trendDn:  (p) => <Svg {...p}><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></Svg>,
    cmd:      (p) => <Svg {...p}><path d="M18 3a3 3 0 0 0-3 3v12a3 3 0 0 0 3 3 3 3 0 0 0 3-3 3 3 0 0 0-3-3H6a3 3 0 0 0-3 3 3 3 0 0 0 3 3 3 3 0 0 0 3-3V6a3 3 0 0 0-3-3 3 3 0 0 0-3 3 3 3 0 0 0 3 3h12a3 3 0 0 0 3-3 3 3 0 0 0-3-3z"/></Svg>,
    keyboard: (p) => <Svg {...p}><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10"/></Svg>,
    chevR:    (p) => <Svg {...p}><polyline points="9 18 15 12 9 6"/></Svg>,
    search:   (p) => <Svg {...p}><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></Svg>,
    plus:     (p) => <Svg {...p}><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></Svg>,
    close:    (p) => <Svg {...p}><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></Svg>,
    edit:     (p) => <Svg {...p}><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></Svg>,
    more:     (p) => <Svg {...p}><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></Svg>,
    file:     (p) => <Svg {...p}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></Svg>,
    archive:  (p) => <Svg {...p}><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></Svg>,
    user:     (p) => <Svg {...p}><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></Svg>,
  };

  window.REC_I = I;
})();
