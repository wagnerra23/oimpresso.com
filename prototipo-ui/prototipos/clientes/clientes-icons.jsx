// Additional icons for the Clientes screen (extends window.Icon from
// chat-icons.jsx) + Brazilian masks and validators.

(function () {
  const I = (size, children, style) =>
    React.createElement('svg', {
      width: size, height: size, viewBox: '0 0 24 24', fill: 'none',
      stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', style,
    }, ...children);
  const path = (d) => React.createElement('path', { d });
  const line = (x1, y1, x2, y2) => React.createElement('line', { x1, y1, x2, y2 });
  const circle = (cx, cy, r) => React.createElement('circle', { cx, cy, r });
  const rect = (x, y, w, h, rx) => React.createElement('rect', { x, y, width: w, height: h, rx });
  const poly = (points) => React.createElement('polyline', { points });

  Object.assign(window.Icon, {
    Filter: (p) => I(p.size || 14, [poly('22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3')]),
    Edit:   (p) => I(p.size || 14, [path('M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7'), path('M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z')]),
    Trash:  (p) => I(p.size || 14, [poly('3 6 5 6 21 6'), path('M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2')]),
    User:   (p) => I(p.size || 14, [path('M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'), circle(12, 7, 4)]),
    Building:(p) => I(p.size || 14, [rect(4, 2, 16, 20, 1), line(9, 6, 9, 6.01), line(15, 6, 15, 6.01), line(9, 10, 9, 10.01), line(15, 10, 15, 10.01), line(9, 14, 9, 14.01), line(15, 14, 15, 14.01), line(10, 22, 10, 18), line(14, 22, 14, 18)]),
    MapPin: (p) => I(p.size || 14, [path('M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z'), circle(12, 10, 3)]),
    Mail:   (p) => I(p.size || 14, [path('M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z'), poly('22,6 12,13 2,6')]),
    Tag:    (p) => I(p.size || 14, [path('M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z'), line(7, 7, 7.01, 7)]),
    AlertCircle:(p) => I(p.size || 14, [circle(12, 12, 10), line(12, 8, 12, 12), line(12, 16, 12.01, 16)]),
    CheckCircle:(p) => I(p.size || 14, [path('M22 11.08V12a10 10 0 1 1-5.93-9.14'), poly('22 4 12 14.01 9 11.01')]),
    Calendar:(p) => I(p.size || 14, [rect(3, 4, 18, 18, 2), line(16, 2, 16, 6), line(8, 2, 8, 6), line(3, 10, 21, 10)]),
    Download:(p) => I(p.size || 14, [path('M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'), poly('7 10 12 15 17 10'), line(12, 15, 12, 3)]),
    Upload: (p) => I(p.size || 14, [path('M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'), poly('17 8 12 3 7 8'), line(12, 3, 12, 15)]),
    ArrowUpDown:(p) => I(p.size || 14, [path('M21 16V4'), poly('17 12 21 16 17 20'), path('M3 8v12'), poly('7 4 3 8 7 12')]),
    ArrowUp:(p) => I(p.size || 14, [line(12, 19, 12, 5), poly('5 12 12 5 19 12')]),
    ArrowDown:(p) => I(p.size || 14, [line(12, 5, 12, 19), poly('19 12 12 19 5 12')]),
    Eye:    (p) => I(p.size || 14, [path('M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z'), circle(12, 12, 3)]),
    Star:   (p) => I(p.size || 14, [path('M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z')]),
    Refresh:(p) => I(p.size || 14, [poly('23 4 23 10 17 10'), poly('1 20 1 14 7 14'), path('M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15')]),
    Loader: (p) => I(p.size || 14, [line(12, 2, 12, 6), line(12, 18, 12, 22), line(4.93, 4.93, 7.76, 7.76), line(16.24, 16.24, 19.07, 19.07), line(2, 12, 6, 12), line(18, 12, 22, 12), line(4.93, 19.07, 7.76, 16.24), line(16.24, 7.76, 19.07, 4.93)]),
    UserPlus:(p) => I(p.size || 14, [path('M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'), circle(8.5, 7, 4), line(20, 8, 20, 14), line(23, 11, 17, 11)]),
    Sliders:(p) => I(p.size || 14, [line(4, 21, 4, 14), line(4, 10, 4, 3), line(12, 21, 12, 12), line(12, 8, 12, 3), line(20, 21, 20, 16), line(20, 12, 20, 3), line(1, 14, 7, 14), line(9, 8, 15, 8), line(17, 16, 23, 16)]),
    Copy:   (p) => I(p.size || 14, [rect(9, 9, 13, 13, 2), path('M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1')]),
  });

  // ── Brazilian masks ────────────────────────────────────────────────
  const onlyDigits = (s) => (s || '').replace(/\D/g, '');

  window.BRMask = {
    cpf: (v) => {
      const d = onlyDigits(v).slice(0, 11);
      return d
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    },
    cnpj: (v) => {
      const d = onlyDigits(v).slice(0, 14);
      return d
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    },
    tel: (v) => {
      const d = onlyDigits(v).slice(0, 11);
      if (d.length <= 10) {
        return d.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d{1,4})$/, '$1-$2');
      }
      return d.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{1})(\d{4})(\d{1,4})$/, '$1 $2-$3');
    },
    cep: (v) => onlyDigits(v).slice(0, 8).replace(/(\d{5})(\d)/, '$1-$2'),
    onlyDigits,
  };

  // ── Validators (return true/false; partial-typed values pass true) ──
  window.BRValidate = {
    cpf: (v) => {
      const d = onlyDigits(v);
      if (d.length < 11) return null; // incomplete: don't yell yet
      if (d.length !== 11 || /^(\d)\1+$/.test(d)) return false;
      const calc = (slice, factor) => {
        let s = 0;
        for (let i = 0; i < slice; i++) s += parseInt(d[i]) * (factor - i);
        const m = (s * 10) % 11;
        return m === 10 ? 0 : m;
      };
      return calc(9, 10) === parseInt(d[9]) && calc(10, 11) === parseInt(d[10]);
    },
    cnpj: (v) => {
      const d = onlyDigits(v);
      if (d.length < 14) return null;
      if (d.length !== 14 || /^(\d)\1+$/.test(d)) return false;
      const calc = (slice) => {
        const weights = slice === 12 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2];
        let s = 0;
        for (let i = 0; i < slice; i++) s += parseInt(d[i]) * weights[i];
        const m = s % 11;
        return m < 2 ? 0 : 11 - m;
      };
      return calc(12) === parseInt(d[12]) && calc(13) === parseInt(d[13]);
    },
    email: (v) => {
      if (!v) return null;
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
    },
    cep: (v) => {
      const d = onlyDigits(v);
      if (d.length < 8) return null;
      return d.length === 8;
    },
  };

  // ── BRL currency
  window.BRL = (n) =>
    typeof n === 'number'
      ? n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
      : 'R$ [redacted Tier 0]';

  // ── Date relative (pt-BR)
  window.relDate = (ts) => {
    if (!ts) return '—';
    const diff = (Date.now() - ts) / 1000;
    if (diff < 60) return 'agora';
    if (diff < 3600) return `há ${Math.floor(diff / 60)}min`;
    if (diff < 86400) return `há ${Math.floor(diff / 3600)}h`;
    const d = Math.floor(diff / 86400);
    if (d < 7) return `há ${d}d`;
    if (d < 30) return `há ${Math.floor(d / 7)}sem`;
    if (d < 365) return `há ${Math.floor(d / 30)}m`;
    return `há ${Math.floor(d / 365)}a`;
  };

  // Avatar color palette (deterministic by string hash)
  const AV_GRADS = [
    'linear-gradient(135deg, oklch(0.62 0.12 30),  oklch(0.50 0.15 10))',
    'linear-gradient(135deg, oklch(0.62 0.12 220), oklch(0.50 0.15 260))',
    'linear-gradient(135deg, oklch(0.62 0.12 145), oklch(0.50 0.15 175))',
    'linear-gradient(135deg, oklch(0.62 0.12 80),  oklch(0.50 0.15 50))',
    'linear-gradient(135deg, oklch(0.62 0.12 300), oklch(0.50 0.15 270))',
    'linear-gradient(135deg, oklch(0.62 0.12 180), oklch(0.50 0.15 200))',
    'linear-gradient(135deg, oklch(0.55 0.15 47),  oklch(0.65 0.15 107))',
    'linear-gradient(135deg, oklch(0.55 0.15 280), oklch(0.65 0.15 340))',
  ];
  window.avatarFor = (name) => {
    if (!name) return AV_GRADS[0];
    let h = 0;
    for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
    return AV_GRADS[h % AV_GRADS.length];
  };
  window.initialsFor = (name) => {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  };
})();
