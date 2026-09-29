// Minimal SVG icon set — phosphor-ish, stroke 1.6, 24px viewBox
const Ic = {};
const _ic = (name, paths, viewBox = "0 0 24 24", fill = "none") =>
  (Ic[name] = ({ size = 22, color = "currentColor", strokeWidth = 1.6, style }) => (
    <svg width={size} height={size} viewBox={viewBox} fill={fill} stroke={color}
         strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round" style={style}>
      {paths}
    </svg>
  ));

_ic("home", <><path d="M3 11l9-7 9 7" /><path d="M5 10v10h14V10" /><path d="M10 20v-6h4v6" /></>);
_ic("inbox", <><path d="M3 5h18v9h-6l-2 3h-2l-2-3H3z" /><path d="M3 14v5h18v-5" /></>);
_ic("box", <><path d="M3 7l9-4 9 4-9 4-9-4z" /><path d="M3 7v10l9 4 9-4V7" /><path d="M12 11v10" /></>);
_ic("tag", <><path d="M3 12l9-9h8v8l-9 9z" /><circle cx="15.5" cy="8.5" r="1.5" fill="currentColor" stroke="none" /></>);
_ic("dollar", <><path d="M12 3v18" /><path d="M16 7H10a2 2 0 100 4h4a2 2 0 110 4H8" /></>);
_ic("dots", <><circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none" /><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" /><circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none" /></>);
_ic("dots-v", <><circle cx="12" cy="5" r="1.5" fill="currentColor" stroke="none" /><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none" /><circle cx="12" cy="19" r="1.5" fill="currentColor" stroke="none" /></>);
_ic("bell", <><path d="M6 9a6 6 0 1112 0v4l2 3H4l2-3V9z" /><path d="M10 19a2 2 0 004 0" /></>);
_ic("user", <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0116 0" /></>);
_ic("search", <><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></>);
_ic("filter", <><path d="M4 5h16l-6 8v5l-4 2v-7z" /></>);
_ic("plus", <><path d="M12 5v14" /><path d="M5 12h14" /></>);
_ic("check", <><path d="M5 12l5 5L20 7" /></>);
_ic("x", <><path d="M6 6l12 12" /><path d="M18 6L6 18" /></>);
_ic("chev-r", <><path d="M9 6l6 6-6 6" /></>);
_ic("chev-l", <><path d="M15 6l-6 6 6 6" /></>);
_ic("chev-d", <><path d="M6 9l6 6 6-6" /></>);
_ic("chev-u", <><path d="M6 15l6-6 6 6" /></>);
_ic("clock", <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>);
_ic("calendar", <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 9h18" /><path d="M8 3v4" /><path d="M16 3v4" /></>);
_ic("flame", <><path d="M12 3c2 4 6 5 6 11a6 6 0 11-12 0c0-3 2-4 3-6 1 2 3 1 3-5z" /></>);
_ic("zap", <><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" /></>);
_ic("phone", <><path d="M22 16.92v3a2 2 0 01-2.18 2 19.86 19.86 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.86 19.86 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z" /></>);
_ic("whatsapp", <><path d="M3 21l1.65-4A8 8 0 1112 20a8 8 0 01-4-1.05L3 21z" /><path d="M9 10c.3 1.2 1.5 2.7 3 4 1.2.8 2 1 3 1l1-1c0-.5 0-1-1-1.5l-1 .5c-.7-.3-1.5-1-2-2l.5-1c-.5-1-1-1-1.5-1l-1 1z" fill="currentColor" stroke="none" /></>);
_ic("mail", <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="M3 7l9 6 9-6" /></>);
_ic("location", <><path d="M21 10c0 6-9 12-9 12s-9-6-9-12a9 9 0 0118 0z" /><circle cx="12" cy="10" r="3" /></>);
_ic("truck", <><rect x="2" y="7" width="12" height="9" rx="1" /><path d="M14 10h4l3 3v3h-7" /><circle cx="6" cy="18" r="2" /><circle cx="18" cy="18" r="2" /></>);
_ic("printer", <><rect x="6" y="3" width="12" height="6" /><rect x="6" y="14" width="12" height="6" /><path d="M6 9H4a2 2 0 00-2 2v4a2 2 0 002 2h2M18 9h2a2 2 0 012 2v4a2 2 0 01-2 2h-2" /></>);
_ic("file", <><path d="M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9z" /><path d="M14 3v6h6" /></>);
_ic("image", <><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="M21 17l-6-6-9 9" /></>);
_ic("logout", <><path d="M15 4h3a2 2 0 012 2v12a2 2 0 01-2 2h-3" /><path d="M10 17l-5-5 5-5" /><path d="M5 12h12" /></>);
_ic("settings", <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 01-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 01-4 0v-.1A1.7 1.7 0 008 19.4a1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 01-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H2a2 2 0 010-4h.1A1.7 1.7 0 003.6 8a1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 012.8-2.8l.1.1a1.7 1.7 0 001.8.3H8a1.7 1.7 0 001-1.5V2a2 2 0 014 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 012.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V8a1.7 1.7 0 001.5 1H22a2 2 0 010 4h-.1a1.7 1.7 0 00-1.5 1z" /></>);
_ic("shield", <><path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z" /></>);
_ic("scan", <><path d="M3 7V5a2 2 0 012-2h2" /><path d="M21 7V5a2 2 0 00-2-2h-2" /><path d="M3 17v2a2 2 0 002 2h2" /><path d="M21 17v2a2 2 0 01-2 2h-2" /><path d="M7 12h10" /></>);
_ic("send", <><path d="M22 2L11 13" /><path d="M22 2l-7 20-4-9-9-4z" /></>);
_ic("paperclip", <><path d="M21 11l-9 9a5.5 5.5 0 11-8-8l9-9a3.5 3.5 0 115 5L9 17a1.5 1.5 0 11-2-2l8-8" /></>);
_ic("trash", <><path d="M3 6h18" /><path d="M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2" /><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6" /></>);
_ic("edit", <><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 113 3L7 19l-4 1 1-4z" /></>);
_ic("check-circle", <><circle cx="12" cy="12" r="9" /><path d="M8 12l3 3 5-6" /></>);
_ic("alert", <><circle cx="12" cy="12" r="9" /><path d="M12 8v5" /><path d="M12 16h.01" /></>);
_ic("arrow-up", <><path d="M12 19V5" /><path d="M5 12l7-7 7 7" /></>);
_ic("arrow-down", <><path d="M12 5v14" /><path d="M19 12l-7 7-7-7" /></>);
_ic("eye", <><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" /><circle cx="12" cy="12" r="3" /></>);
_ic("qr", <><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><path d="M14 14h3v3h-3z" /><path d="M21 14v3M21 21h-4" /></>);
_ic("more", <><circle cx="12" cy="12" r="9" /><path d="M8 12h.01M12 12h.01M16 12h.01" /></>);
_ic("refresh", <><path d="M3 12a9 9 0 0115-6.7L21 8" /><path d="M21 3v5h-5" /><path d="M21 12a9 9 0 01-15 6.7L3 16" /><path d="M3 21v-5h5" /></>);
_ic("layers", <><path d="M12 2l10 6-10 6L2 8z" /><path d="M2 16l10 6 10-6" /><path d="M2 12l10 6 10-6" /></>);
_ic("chart", <><path d="M3 3v18h18" /><path d="M7 14l3-3 4 4 5-7" /></>);
_ic("trending-up", <><path d="M23 6l-9.5 9.5-5-5L1 18" /><path d="M17 6h6v6" /></>);
_ic("trending-down", <><path d="M23 18l-9.5-9.5-5 5L1 6" /><path d="M17 18h6v-6" /></>);
_ic("paint", <><path d="M19 11h-1V7a3 3 0 00-3-3H5a3 3 0 00-3 3v4a3 3 0 003 3h7v3a4 4 0 004 4h0a4 4 0 004-4v-2a3 3 0 00-1-2.2z" /></>);
_ic("sun", <><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" /></>);
_ic("moon", <><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" /></>);
_ic("logo", <><rect x="3" y="3" width="18" height="18" rx="4" fill="currentColor" stroke="none" /><path d="M8 8h4a3 3 0 010 6H8z" stroke="#fff" fill="none" strokeWidth="2" /><path d="M14 16l3-3" stroke="#fff" fill="none" strokeWidth="2" /></>, "0 0 24 24", "none");


// camelCase aliases (for JSX tag form: <Ic.chevR />)
Ic.chevL = Ic["chev-l"];
Ic.chevR = Ic["chev-r"];
Ic.chevD = Ic["chev-d"];
Ic.chevU = Ic["chev-u"];
Ic.dotsV = Ic["dots-v"];
Ic.trendingUp = Ic["trending-up"];
Ic.trendingDown = Ic["trending-down"];
Ic.arrowUp = Ic["arrow-up"];
Ic.arrowDown = Ic["arrow-down"];
Ic.checkCircle = Ic["check-circle"];

window.Ic = Ic;
