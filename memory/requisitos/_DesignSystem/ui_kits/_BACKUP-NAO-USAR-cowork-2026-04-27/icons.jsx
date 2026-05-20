// Icon set — outline 1.6px stroke, 16/14px, lucide-flavored but hand-rolled.
const Icon = ({ d, size = 16, stroke = 1.6, className = "ic", style }) => (
  <svg className={className} width={size} height={size} viewBox="0 0 24 24"
       fill="none" stroke="currentColor" strokeWidth={stroke}
       strokeLinecap="round" strokeLinejoin="round" style={style}>
    {d}
  </svg>
);

const I = {
  chat:    (p) => <Icon {...p} d={<><path d="M21 12a8 8 0 0 1-11.7 7.1L4 21l1.9-5.3A8 8 0 1 1 21 12Z"/></>}/>,
  orders:  (p) => <Icon {...p} d={<><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></>}/>,
  clients: (p) => <Icon {...p} d={<><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.6"/><path d="M21 19c0-2.5-1.7-4.5-4-5"/></>}/>,
  product: (p) => <Icon {...p} d={<><path d="M12 3 4 7v10l8 4 8-4V7l-8-4Z"/><path d="m4 7 8 4 8-4M12 11v10"/></>}/>,
  quote:   (p) => <Icon {...p} d={<><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/><path d="M9 14h6M9 17h4"/></>}/>,
  print:   (p) => <Icon {...p} d={<><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></>}/>,
  scissor: (p) => <Icon {...p} d={<><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="m20 4-12 12M14 14l6 6M8 8l4 4"/></>}/>,
  truck:   (p) => <Icon {...p} d={<><path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7.5" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></>}/>,
  chart:   (p) => <Icon {...p} d={<><path d="M4 19V5M4 19h16"/><path d="M8 15v-4M12 15V9M16 15v-7"/></>}/>,
  cash:    (p) => <Icon {...p} d={<><rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 10v4M18 10v4"/></>}/>,
  folder:  (p) => <Icon {...p} d={<><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></>}/>,
  cog:     (p) => <Icon {...p} d={<><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.7 1 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></>}/>,
  shield:  (p) => <Icon {...p} d={<><path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></>}/>,
  search:  (p) => <Icon {...p} d={<><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></>}/>,
  plus:    (p) => <Icon {...p} d={<><path d="M12 5v14M5 12h14"/></>}/>,
  pencil:  (p) => <Icon {...p} d={<><path d="M16.9 3.6a2.1 2.1 0 0 1 3 3L8 18.5 3 20l1.5-5L16.9 3.6Z"/></>}/>,
  chev:    (p) => <Icon {...p} d={<><path d="m6 9 6 6 6-6"/></>}/>,
  chevR:   (p) => <Icon {...p} d={<><path d="m9 6 6 6-6 6"/></>}/>,
  chevUd:  (p) => <Icon {...p} d={<><path d="m7 9 5-5 5 5M7 15l5 5 5-5"/></>}/>,
  check:   (p) => <Icon {...p} d={<><path d="m5 12 5 5L20 7"/></>}/>,
  paperclip:(p)=> <Icon {...p} d={<><path d="m21 11-9 9a5.5 5.5 0 1 1-7.8-7.8L13 3.4a3.7 3.7 0 1 1 5.2 5.2l-9 9a1.8 1.8 0 1 1-2.6-2.6L14.5 7"/></>}/>,
  smile:   (p) => <Icon {...p} d={<><circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><path d="M9 9h.01M15 9h.01"/></>}/>,
  send:    (p) => <Icon {...p} d={<><path d="m22 2-7 20-4-9-9-4 20-7Z"/><path d="m22 2-11 11"/></>}/>,
  phone:   (p) => <Icon {...p} d={<><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1l-1.3 1.3a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2Z"/></>}/>,
  info:    (p) => <Icon {...p} d={<><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></>}/>,
  more:    (p) => <Icon {...p} d={<><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></>}/>,
  bell:    (p) => <Icon {...p} d={<><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a2 2 0 0 0 3.4 0"/></>}/>,
  moon:    (p) => <Icon {...p} d={<><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></>}/>,
  user:    (p) => <Icon {...p} d={<><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></>}/>,
  keyboard:(p) => <Icon {...p} d={<><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M6 14h12"/></>}/>,
  help:    (p) => <Icon {...p} d={<><circle cx="12" cy="12" r="9"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></>}/>,
  exit:    (p) => <Icon {...p} d={<><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></>}/>,
  pin:     (p) => <Icon {...p} d={<><path d="M12 17v5M9 3h6l-1 5 4 4H6l4-4-1-5Z"/></>}/>,
  inbox:   (p) => <Icon {...p} d={<><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13l3.5 7v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6L5.5 5Z"/></>}/>,
  hash:    (p) => <Icon {...p} d={<><path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/></>}/>,
  close:   (p) => <Icon {...p} d={<><path d="M18 6 6 18M6 6l12 12"/></>}/>,
  filter:  (p) => <Icon {...p} d={<><path d="M3 5h18l-7 9v6l-4-2v-4L3 5Z"/></>}/>,
};

window.I = I;
