// CliPageHead — adaptador FINO do PageHeader do Design System.
//
// ── 2026-09-01: deixou de ser dono do desenho ─────────────────────────────────────
// Este arquivo nasceu porque o `PageHeader` do DS era header de ÍNDICE (título + stats
// + ações) e não tinha glyph, linha de contexto nem chip de frescor — que é justamente
// o que estas telas usam. Era a pendência que eu havia registrado. **O DS atendeu**: o
// componente agora declara, no próprio docblock, que "absorve o antigo cli-pagehead",
// com `leading` (marca na linha de base do título), `context` (linha acima do título) e
// `freshness`/`freshnessRel` (pílula via StatusBadge kind="frescor").
//
// Então o desenho é do DS, e aqui só resta tradução de vocabulário:
//   · contexto: []        → `context` do DS (uma linha, ellipsis) OU, com
//     `contextoWrap`, um `<p>` próprio que ENVOLVE. A diferença é por PÁGINA, medida
//     no CSSOM legado, não uma preferência:
//       · `.jc-page .jc-id h1, .jc-page .jc-id p{nowrap;ellipsis}`  → Jana truncava os DOIS
//       · `.mp-header .jc-id h1{white-space:nowrap}`                → módulo truncava SÓ o h1;
//         o `<p>` de contexto não tinha regra, logo ENVOLVIA e mostrava o texto inteiro.
//     Eu havia generalizado a regra da Jana pro app todo e concluto "truncar não é
//     regressão". Era, nos 12 módulos: em `repair` "REPAIR · Matriz + 1 filial · 14
//     folhas · 11 pendentes" perdia 133px e "14 folhas · 11 pendentes" desaparecia —
//     contagem operacional, conteúdo de domínio, não decoração.
//     O DS não tem slot que envolva (`context` e a linha de `stats` são ambos nowrap +
//     ellipsis), então quando `contextoWrap` é pedido o `<p>` é renderizado aqui, como
//     IRMÃO do PageHeader — não é override do DS. Registrado como lacuna do DS.
//   · avatar (iniciais)     → `leading` como <Avatar> do DS. O slot `leading` do DS é
//     um dot/ícone renderizado inline no h1 (aria-hidden, cor accent, margin-right 8px);
//     passar a inicial CRUA ali dá uma letra solta de 22px em roxo, que não é avatar.
//     O DS publica `Avatar` (name/initials/size) — é esse o componente certo.
//   · glyph               → `leading` direto (já é ícone)
//   · atualizadoAs+onRefresh → primeiro item de `actions`, NÃO o slot `freshness`.
//     O `freshness` do DS é renderizado DENTRO da flex row do h1, com `flex:0 0 auto`
//     (bundle ~5343) — desenhado pra uma pílula compacta de StatusBadge ("recente",
//     "fresc"), não pra um botão de 115px com "Atualizado 09:42". Pô-lo ali roubava
//     largura do título permanentemente: em `repair` o h1 "Assistência técnica" truncava
//     (218×173) porque o chip levava 115px+8px da coluna de 296px. No markup legado o
//     chip sempre viveu em `.jc-header-r`, ao lado das ações — e é pra lá que ele volta,
//     onde o wrapper com `flexWrap` absorve.
//   · papel               → `subtitle`, NÃO concatenado no título. O `title` do DS é
//     `nowrap` + `ellipsis`; concatenar "· Ponto eletrônico · Portaria MTP 671/2021" ali
//     truncava o h1 em TODAS as telas (medido: scrollWidth 495 × clientWidth 142).
//     `subtitle` é o slot que existe pra texto secundário.
//
// ── O QUE SE PERDE, DE PROPÓSITO ──────────────────────────────────────────────────
// As classes `.jc-header/.jc-avatar/.jc-id/.mp-header` NÃO são mais emitidas. A lição
// completa levou duas rodadas: repassar classe legada faz herdar regras que ninguém
// auditou (as que ADICIONAM vencem o inline e quebram o DS; as que ESCONDEM perdem e o
// elemento reaparece) — MAS deixar de emitir também remove o trabalho ÚTIL que a classe
// fazia (dimensionar o SVG do glyph, dar o respiro lateral de 28px). Auditar nas DUAS
// direções; o que era necessário vira código aqui ou prop no DS, nunca folha.
(() => {
function CliPageHead({ glyph, avatar, titulo, papel, contexto = [], atualizadoAs, onRefresh, refreshTitle = "Atualizar agora", acoes, pad = 0, contextoWrap = false }) {
  const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
  const PageHeader = DS.PageHeader;
  if (!PageHeader) return null;

  // Degrau responsivo de `modulo-padrao.css:8` (@media ≤900px → 16px). O padding aqui é
  // inline, então media query não alcança — precisa ser estado, como no CliTabs.
  const [estreito, setEstreito] = React.useState(() => window.matchMedia("(max-width: 900px)").matches);
  React.useEffect(() => {
    const mq = window.matchMedia("(max-width: 900px)");
    const on = (e) => setEstreito(e.matches);
    mq.addEventListener("change", on);
    return () => mq.removeEventListener("change", on);
  }, []);
  // RESPIRO LATERAL É DE QUEM CONHECE A PÁGINA, NÃO DO ADAPTADOR — default 0.
  // Duas estruturas incompatíveis convivem no app:
  //   · módulo (`.mp-page`): a página NÃO tem padding e cada bloco carrega os seus 28px
  //     (`modulo-padrao.css:5/:6/:7`) → ModuloPadrao passa `pad={28}`.
  //   · Jana (`.jc-page`): a PÁGINA paga 24px e os irmãos usam 0 → não passa nada.
  // Eu havia cravado 28 como default "convenção do shell": na Jana virou padding DUPLO,
  // com o header em 312 e abas/corpo em 284. Número absoluto não prova alinhamento —
  // só a comparação entre irmãos da MESMA tela prova.
  // O degrau de ≤900px só se aplica quando há padding a reduzir.
  const padX = pad ? (estreito ? 16 : pad) : 0;

  // Tentei também descer o excedente pro `stats` e saiu pior: o DS não põe separador
  // entre `subtitle` e `stats[0]` ("Portaria MTP 671/2021Agosto/2026"), e a linha de
  // `stats` também é nowrap+ellipsis — truncava igual, com formatação pior.
  const linha = contexto.filter((c) => c != null && c !== "").join(" · ");

  // Eyebrow que ENVOLVE, para as páginas cujo legado envolvia. Mesmos tokens do slot
  // `context` do DS (bundle:5306): mono 11px, uppercase, letter-spacing .04em, text-dim.
  //
  // `marginBottom` NEGATIVO não é gambiarra — é compensação de um valor não configurável.
  // Como irmão ACIMA do PageHeader, este `<p>` fica separado do título pelos 14px de
  // `padding-top` internos do `<header>` do DS, que não são prop. Medido antes: vão de
  // 17px entre eyebrow e título, contra os 2px do legado (`chat-jana.css:45`
  // `.jc-id p{margin:2px 0 0}`). Os -11px devolvem ~3px.
  const eyebrowQueEnvolve = contextoWrap && linha
    ? <p style={{ margin: "0 0 -11px", font: "500 11px/1.2 var(--font-mono)",
        letterSpacing: ".04em", textTransform: "uppercase", color: "var(--text-dim)",
        textWrap: "pretty" }}>{linha}</p>
    : null;

  const Avatar = DS.Avatar;
  // O SVG do JcIcon NÃO tem width/height nem classe próprios: dependia INTEIRAMENTE de
  // `modulo-padrao.css:40` → `.mp-header .mp-glyph svg{width:20px;height:20px}`. Ao
  // parar de emitir `.mp-header`/`.mp-glyph`, o ícone colapsou pra 0×0 e a identidade do
  // header desapareceu nos 12 módulos (medido: w:0 h:0).
  // Mesma raiz da rodada anterior, na direção OPOSTA: lá a classe legada ADICIONAVA algo
  // que quebrava o DS; aqui ela fazia trabalho NECESSÁRIO, e remover removeu o trabalho.
  // Regra que fecha as duas: ao deixar de emitir uma classe, auditar o que ela FAZIA —
  // nas duas direções.
  // A placa 40×40 tintada de `modulo-padrao.css:39` NÃO volta, de propósito: o docblock
  // do PageHeader do DS diz que o canon flat a aposentou (é do shared/PageHeader.tsx,
  // CONGELADO). Perder a placa é correto; perder o ícone não era.
  const marca = avatar != null
    ? (Avatar ? <Avatar initials={String(avatar)} size="sm" /> : avatar)
    : (glyph
        ? <span style={{ display: "inline-flex", alignItems: "center", justifyContent: "center",
            width: 20, height: 20 }}>{glyph}</span>
        : undefined);

  // Frescor clicável. Vai em `actions`, no início — a posição que tinha no legado
  // (`.jc-header-r` começava com `.jc-updated`, antes do plano e dos botões).
  const frescor = atualizadoAs
    ? (onRefresh
        ? <button type="button" onClick={onRefresh} title={refreshTitle}
            style={{ font: "inherit", fontSize: "11px", display: "inline-flex", alignItems: "center", gap: "5px",
              padding: "2px 8px", borderRadius: "99px", cursor: "pointer",
              border: "1px solid var(--border)", background: "var(--bg-2)", color: "var(--text-dim)" }}>
            <span style={{ width: 5, height: 5, borderRadius: "50%", background: "var(--pos, var(--accent))" }} />
            Atualizado {atualizadoAs}
          </button>
        : <span style={{ fontSize: "11px", color: "var(--text-mute)" }}>Atualizado {atualizadoAs}</span>)
    : undefined;

  // AS AÇÕES PRECISAM CEDER LARGURA. O container de ações do PageHeader do DS é
  // `flex: 0 0 auto` (bundle:5369) — largura de conteúdo, nunca encolhe: com 510px de
  // botões num header de 608px o h1 ficava com 86px. Não dá pra mudar o DS daqui, mas
  // dá pra limitar o que EU entrego — o max-content deste wrapper é o que define a
  // largura do container dele.
  // Pendência do DS: o slot de ações deveria poder encolher, ou virar Kebab em largura
  // apertada — aí este cálculo morre.
  // Derivação (medida em TODAS as rotas, não estimada): sidebar 260px + respiro ~56px
  // ⇒ o header mede `100vw - 316px` (conferido: vw 924 → header 608).
  //
  // A RESERVA É PELA LINHA MAIS LONGA DA COLUNA, NÃO PELO TÍTULO. Minha primeira reserva
  // (240px) veio do h1 mais longo — "Etiquetas TAG vestuário", 236px — e ignorou que a
  // coluna tem TRÊS linhas. Medi as rotas que passam por aqui:
  //   Ponto    h1 86  · subtitle 244  ← maior
  //   repair   h1 218 · subtitle 240
  //   Compras  h1 117 · Patrimônio h1 137 · Estoque h1 109
  // Com 240 de reserva, o subtitle do Ponto (244) faltava 4px e a ellipsis cortava
  // "Portaria MTP 671/20…" — texto normativo, que o guia do DS proíbe parafrasear ou
  // cortar ("Never paraphrase the article number"). Reserva vai a 264px: cobre 244 com
  // 20px de folga pra fallback de fonte.
  //   cap = calc(100vw - 592px)   →  924px: 332 · 1280px: 688 · 1440px: 848
  // Com 561px de ações: 2 linhas a 924 (onde não cabem mesmo, e a coluna fica com 264
  // ≥ 244) e UMA linha a partir de ~1100px.
  // `vw` e não `%`: porcentagem resolveria contra o container de ações do DS, que tem
  // largura automática — circular, não binda.
  // ESTA CONTA SÓ EXISTE porque o slot de ações do DS não encolhe. Enquanto for assim,
  // a reserva é um número a revisitar se aparecer subtitle mais longo — fragilidade
  // conhecida, e a razão de a pendência do DS estar registrada.
  const capAcoes = "max(240px, calc(100vw - 592px))";
  const acoesFlex = (frescor || acoes)
    ? <div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap",
        justifyContent: "flex-end", maxWidth: capAcoes }}>{frescor}{acoes}</div>
    : undefined;

  // Respiro vertical do canon (`modulo-padrao.css:5`: 20px no topo do bloco inteiro;
  // `:8`: 16px em ≤900px). SEM eyebrow irmão, os 14px internos do `<header>` do DS já
  // cobrem quase tudo e 6px aqui completam. COM eyebrow irmão, o `<p>` vem ANTES do
  // header — os 14px do DS deixam de contar pro topo e o wrapper tem que pagar os 20px
  // inteiros, senão o eyebrow encosta no topo (medido: top 6px, contra 20px do canon).
  const padTop = contextoWrap ? (estreito ? 16 : 20) : 6;
  return (
    <div style={{ padding: padTop + "px " + (padX || 0) + "px 0", marginBottom: 14 }}>
      {eyebrowQueEnvolve}
      <PageHeader
        title={titulo}
        subtitle={papel || undefined}
        context={contextoWrap ? undefined : (linha || undefined)}
        leading={marca}
        actions={acoesFlex} />
    </div>);
}
window.CliPageHead = CliPageHead;
})();
