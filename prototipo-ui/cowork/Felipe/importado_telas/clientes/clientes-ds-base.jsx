// clientes-ds-base.jsx — substitui o chat-icons.jsx, que o import desta tela não trouxe
// (24/09/2026: não existe neste projeto nem em oimpresso.com/prototipo-ui/cowork).
//
// Duas fontes, nenhum desenho novo:
// 1. o Icon do UI kit do DS (ui_kits/app/Icon.jsx, publicado pelo _ds_bundle.js), para os nomes que ele tem;
// 2. o icons.jsx da raiz do projeto (o mesmo conjunto da Fabricação, handoff §14), para 5 nomes que o
//    DS não tem. Os traços foram TRANSCRITOS de lá, com a linha. Não carregamos o arquivo porque ele
//    declara `const Icon` no escopo global, e isso esconderia o window.Icon que esta tela usa.
// Os 4 nomes que nenhuma das duas fontes tem (Sparkles, History, PanelRight, Target) ficam como espaço
// vazio do tamanho pedido: é o fallback do próprio Icon do DS (L30). Contorno declarado; o produto usa Lucide.
// clientes-icons.jsx estende este objeto depois (Object.assign(window.Icon, …)).
(() => {
  const DSIcon = window.Icon;
  const Svg = ({ size = 14, d }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.6}
      strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{d}</svg>
  );
  const vazio = (p) => <span style={{ display: "inline-block", width: (p && p.size) || 14, height: (p && p.size) || 14 }} />;
  const doDS = (nome) => (p) => DSIcon ? <DSIcon name={nome} size={(p && p.size) || 14} /> : vazio(p);
  window.Icon = {
    Search: doDS("search"), Plus: doDS("plus"), Check: doDS("check"), ChevronRight: doDS("chevron-right"),
    ChevronDown: doDS("chevron-down"), DollarSign: doDS("dollar-sign"), Users: doDS("users"), Clock: doDS("clock"),
    Settings: (p) => <Svg size={p && p.size} d={<><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.7 1 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></>} />, // icons.jsx L24 (cog)
    Phone: (p) => <Svg size={p && p.size} d={<><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.6 2.7a2 2 0 0 1-.5 2.1l-1.3 1.3a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.5 2.7.6a2 2 0 0 1 1.7 2Z"/></>} />, // icons.jsx L36 (phone)
    MessageSquare: (p) => <Svg size={p && p.size} d={<><path d="M21 12a8 8 0 0 1-11.7 7.1L4 21l1.9-5.3A8 8 0 1 1 21 12Z"/></>} />, // icons.jsx L58 (message)
    X: (p) => <Svg size={p && p.size} d={<><path d="M18 6 6 18M6 6l12 12"/></>} />, // icons.jsx L59 (x)
    Briefcase: (p) => <Svg size={p && p.size} d={<><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/></>} />, // icons.jsx L81 (briefcase)
    Sparkles: vazio, History: vazio, PanelRight: vazio, Target: vazio,
  };
})();
