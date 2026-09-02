// CliTabs — adaptador FINO do TabBar do Design System.
//
// ── 2026-09-01: o adaptador encolheu de 151 pra ~60 linhas ─────────────────────────
// O DS passou a aceitar o contrato NO PRÓPRIO <nav> (`...rest` espalhado na raiz +
// `className`, `ariaLabel`, `pad`, `size`, `off`, `icon`, `inset`). Tudo que este arquivo
// fazia por fora — wrapper `display:contents`, escrita de atributo/estilo por render,
// setAttr/setStyle comparando antes de gravar — virou desnecessário e FOI REMOVIDO.
// Era o pedido registrado como "10ª pendência do DS"; foi atendido no bundle de hoje.
//
// Junto com o wrapper caiu o bloqueio das telas de Produto: o travamento da rota vinha
// de embrulhar num <div> um <nav> que é item direto do grid do page-head
// (produto-catalogo.css `.pd-abas{grid-column:1/-1}`). Sem wrapper, não há o que travar.
//
// O que este arquivo AINDA faz (e por que não é desenho):
//   · `n` → `count` — alias do legado `.cli-moduletopnav-n`
//   · `icon:"nome"` → nó, via window.JcIcon / window.I (o DS espera um nó pronto)
//   · classe legada no nav — contratos de tela, regras de posição (.cb-nav, .vb-nav,
//     .jm-tabs, .fx-page>…) e o @media print casam por ela; foram escritas pro <nav>
//   · pad responsivo — as folhas legadas caíam pra 16px em ≤900px; `pad` é prop, não CSS
//   · `warn`/`off` por aba — estados que o TabBar não tem (pendência 11 do DS)
//   · `itensFull` — itens dividindo a largura em partes iguais. Vinha de `flex:1` na
//     classe dos BOTÕES legados (ex. `.om-mobile-tab`), que o TabBar do DS não emite:
//     medido `flexGrow: 0` e dois botões de 115px+70px numa barra de 664px, com 72% dela
//     vazia. `justifyContent` NÃO resolve — os botões continuariam com largura de
//     conteúdo; é preciso escrever `flex` nos filhos.
//     Eu havia usado a existência dessa regra como ARGUMENTO ("as abas esticam porque
//     `.om-mobile-tab{flex:1}`") sem medir que a classe deixou de ser emitida — terceira
//     vez que deixar de emitir removeu o trabalho ÚTIL da classe legada, e a primeira em
//     que o erro foi de raciocínio, não de execução.
// Quando `warn`/`off` existirem por aba no TabBar, este arquivo morre de vez.
// ── 2026-09-01 (2ª correção): a classe legada NÃO entra mais sozinha ──────────────
// Eu vinha acrescentando `cli-moduletopnav` a todo nav, com o argumento de que as regras
// legadas eram "só de LAYOUT". Medi: NÃO são. Elas se dividem em duas famílias, e o
// TabBar do DS escreve geometria INLINE (inline vence folha):
//   · regras que ADICIONAM (padding, flex-wrap) → vencem em silêncio e QUEBRAM o DS.
//     `flex-wrap:wrap` em .os-tabs/.cb-nav/.cd-subnav/.ptr-subtabs/.arq-tabs fazia a
//     fileira empilhar em 2 linhas onde o DS quer rolar com fade — e o syncOverflow do
//     DS nunca disparava, porque com wrap scrollWidth == clientWidth sempre.
//   · regras que ESCONDEM (display:none) → PERDEM em silêncio. `@media print` que
//     suprimia as abas no papel deixou de valer, e `.om-mobile-tabs{display:none}`
//     (barra exclusiva de mobile) passou a aparecer no desktop.
// Herdei as duas famílias sem auditar nenhuma — supor em vez de medir, de novo.
//
// Agora: o gancho estável é `.ds-tabbar`, a classe do próprio DS (honesto: o nav É um
// TabBar do DS). O @media print mira `.ds-tabbar` com !important, que vence inline.
// `className` do chamador continua passando — mas é responsabilidade de quem passa, e
// esconder virou PROP (`hidden`), não efeito colateral de folha.
(() => {
const { useEffect, useRef, useState } = React;

function resolverIcone(icon) {
  if (!icon) return null;
  if (typeof icon === "string") {
    const JcIcon = window.JcIcon;
    if (JcIcon) return <JcIcon name={icon} className="cli-tabs-ic" />;
    const F = (window.I || {})[icon];
    return F ? <F size={14} /> : null;
  }
  if (typeof icon === "function") { const F = icon; return <F size={14} />; }
  return icon;
}

let seq = 0;

// ── `pad` NÃO TEM DEFAULT (2026-09-01) ────────────────────────────────────────────
// Este adaptador escreve `paddingInline` INLINE, e inline vence a folha. Eu vinha
// passando `pad` por hábito (14 / 12 / 0) em vez do padding lateral que cada fileira
// TINHA na folha — e aí toda fileira migrada deslocou horizontalmente em relação ao
// conteúdo da própria tela (Conector, Governança, Manufatura, Boletos: aba em 274,
// título em 284). É exatamente a lição que eu já tinha escrito pro header — "o respiro
// lateral é de quem conhece a página, não do adaptador" — e não apliquei aqui.
// Agora: sem `pad`, NADA é escrito e a classe legada (ou o pai) paga o respiro dela.
// Passar `pad` é declaração explícita, com o valor medido na folha daquela fileira.
function CliTabs({ tabs = [], active, onChange, ariaLabel, pad = null, fullBleed = false, wrap = false, hidden = false, itensFull = false, size, dataContract, style, className }) {
  const TabBar = (window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {}).TabBar;
  const marca = useRef("clitabs-" + (++seq)).current;
  const [estreito, setEstreito] = useState(() => window.matchMedia("(max-width: 900px)").matches);
  useEffect(() => {
    const mq = window.matchMedia("(max-width: 900px)");
    const onMq = (e) => setEstreito(e.matches);
    mq.addEventListener("change", onMq);
    return () => mq.removeEventListener("change", onMq);
  }, []);
  // `null` = não escrever padding nenhum. O degrau de ≤900px só vale quando há valor.
  const padAtual = pad == null ? null : (estreito && pad >= 24 ? 16 : pad);
  const itens = tabs.filter(Boolean);

  // warn/off por aba: o TabBar ainda não tem. Casa por índice — o TabBar renderiza um
  // botão por entrada, na ordem. Sem ref disponível (o DS usa o próprio), o nav é
  // encontrado pela marca única passada via `...rest`.
  useEffect(() => {
    const nav = document.querySelector('[data-cli-tabs="' + marca + '"]');
    if (!nav) return;
    itens.forEach((t, k) => {
      const b = nav.children[k];
      if (!b) return;
      b.style.opacity = t.off ? "0.45" : "";
      if (t.off) b.setAttribute("aria-disabled", "true"); else b.removeAttribute("aria-disabled");
      // NUNCA gravar "" aqui. O TabBar do DS escreve `color` INLINE em cada botão
      // (accent na ativa, text-dim nas outras); apagar com string vazia deleta esse
      // valor, e o React não o reescreve — a prop dele não mudou. Resultado medido em
      // 2026-09-01: toda aba não-warn nascia PRETA sobre fundo escuro (contraste 2,26:1),
      // e só voltava ao cinza depois de um hover, porque os handlers do DS regravam a cor.
      // Foi por isso que passou por screenshot: a captura pega o estado pós-hover.
      // Regra: efeito que pisa em estilo de terceiro RESTAURA o valor dele, não limpa.
      const corDS = active === t.key ? "var(--accent)" : "var(--text-dim)";
      b.style.color = t.warn && active !== t.key ? "var(--warn)" : corDS;
      // `itensFull`: divide a largura em partes iguais. `justifyContent` no nav não serve
      // — tem que ser `flex` nos próprios botões. `minWidth:0` pra o texto poder encolher.
      if (itensFull) {
        b.style.flex = "1 1 0";
        b.style.minWidth = "0";
        b.style.justifyContent = "center";
      }
    });
  });

  if (!TabBar) return null;
  return (
    <TabBar
      data-cli-tabs={marca}
      data-contract={dataContract}
      className={className || undefined}
      ariaLabel={ariaLabel}
      inset={padAtual == null ? undefined : padAtual}
      size={size === "sm" ? "sm" : undefined}
      active={active}
      onChange={onChange}
      tabs={itens.map((t) => ({
        key: t.key,
        label: t.label,
        icon: resolverIcone(t.icon),
        count: t.count != null ? t.count : t.n,
        disabled: t.disabled }))}
      style={{
        background: "var(--bg)",
        flexShrink: 0,
        // SEMPRE explícito: sem isto, `flex-wrap:wrap` de folha legada vazava e anulava
        // o scroll horizontal + máscara de fade do TabBar (medido em Orçamentos:
        // 36px de altura viravam 71px em duas linhas a 220px de largura).
        flexWrap: wrap ? "wrap" : "nowrap",
        ...(wrap ? { rowGap: 4, overflowX: "visible" } : null),
        ...(fullBleed && padAtual != null ? { marginInline: -padAtual } : null),
        ...style,
        // `hidden` por último: é a porta de entrada explícita pro que antes vinha de
        // `display:none` em folha e passou a perder pro inline do DS.
        ...(hidden ? { display: "none" } : null) }} />);
}

window.CliTabs = CliTabs;
})();
