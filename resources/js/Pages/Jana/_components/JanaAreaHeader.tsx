// JanaAreaHeader — header sticky compartilhado entre as telas Jana
// (Index/Painel · Chat/Conversa · Memoria · Pro).
//
// Onda de fusão (2026-08-07, US-COPI-148) — UMA barra, no shape do canon.
// Antes eram DUAS, e nenhuma usava o `<PageHeader>` shared: este componente em
// cima (dot JANA + abas + Conversar) e um `<header>` próprio dentro do
// `JanaCockpit` embaixo (Jana · Analista IA + biz + Atualizado + ações).
// As duas fontes concordam e discordavam da produção:
//   - protótipo  `prototipo-ui/cowork/jana-merge.jsx` → `<JanaHeader/>` e SÓ
//     DEPOIS `{tabs}`: header em cima, abas abaixo (produção tinha invertido);
//   - canon repo `Pages/Financeiro/Caixa/Index.tsx:95-112` → o SubNav vive
//     DENTRO do `<PageHeader>`, numa barra só;
//   - PT-04-Dashboard §Anatomia slot 1 + R6 → "header é `<PageHeader>` shared".
// Ganho: a altura de uma barra inteira volta pro conteúdo.
//
// ONDA 1 da paridade com o protótipo (2026-09-03, UI-0029 "protótipo soberano"):
// a fusão acima acertou a barra ÚNICA e errou a POSIÇÃO das abas. Medido com a
// mesma sonda nos dois lados (design-diff, dark × dark, viewport 2560):
//   · âncora `jana-merge.jsx` §JanaPage: `<JanaHeader/>` (41px) → 14px → `<nav>` das
//     abas em FAIXA PRÓPRIA, largura toda (`left=284 w=2237 h=36`), 13px/500,
//     ativa 600 + underline accent + pill accent-soft, ícone 14px por aba;
//   · produção (antes): tablist INLINE na Zona C do header, `left=1654 w=451`, no
//     mesmo `top` do h1 — abas espremidas à direita do título.
// O canon do repo já é o do protótipo: `Pages/Cliente/Index.tsx` renderiza o
// `<PageHeaderTabs>` como "faixa própria abaixo do título" ([W] 2026-07-14: "mesma
// posição do Clientes/protótipo em todas"). A Jana era a divergente. O `Caixa/Index`
// citado acima, que inspirou o inline, é o que diverge do protótipo — não este.
// Outras duas coisas que a âncora tem no header e a produção não tinha:
//   · "Atualizado HH:MM" vive na ZONA DIREITA (`.jc-header-r`: `.jc-updated` com dot
//     verde, ANTES do selo de plano), não no subtítulo;
//   · NÃO existe primary "Conversar" no header — a Conversa é uma ABA; o botão
//     "Nova conversa" só aparece na própria aba Conversa (`isChat`). O primary que
//     existia aqui vinha do `DataController` (sidebar) e duplicava a aba.
// Subtítulo da âncora é MONO (`.jc-id p`: IBM Plex Mono 11.5px) — `font-mono` aqui.
//
// ADR 0182 + GUIA-SIDEBAR-V3 Wagner 2026-05-21: refatorado pra usar JanaSubNav
// canon (ghosts ARIA tablist com auto-promoção do ativo + overflow ⋯ Mais)
// em vez dos 2 tabs hardcoded antigos (Dashboard/Chat). Hue OKLCH 220 (grupo `ia`).
//
// Pattern canon (espelha FinanceiroSubNav usado em /financeiro/*).
//
// Map de retrocompat:
//   active="chat"      → ghost `copiloto` (canon)
//   active="dashboard" → ghost `dashboard`
//   active="memoria"   → ghost `memorias`
//   (qualquer string) → passada direta como activeGhostKey
//
// Refs:
// - ADR 0180/0182 (sidebar v3 + pageheader canon)
// - GUIA-SIDEBAR-V3-PASSO-A-PASSO (Wagner 2026-05-21)
// - JanaSubNav.tsx (componente filho que lê shell.menu)
// - PR #1053 (Fase 1 anterior, este supersede)

import { useState, type ReactNode } from 'react';
import JanaSubNav from '@/Pages/Jana/_shared/JanaSubNav';
import { PageHeader } from '@/Components/PageHeader';
import { router } from '@inertiajs/react';

// Onda 3 da fusão (US-COPI-148, 2026-08-07) — o type tinha 15 membros e MEDIDOS
// só 4 eram passados por alguém (`chat` · `dashboard` · `memoria` · `cockpit`,
// mais `copiloto` num uso direto de JanaSubNav). Os 9 removidos são ghosts que já
// não existem no DataController: cada um sobreviveu à remoção do próprio ghost e
// virou membro que o TypeScript aceita alegremente para produzir uma aba que não
// renderiza.
//
//   painel                            onda 1 desta fusão (2026-08-06)
//   brief                             2026-06-15
//   metas                             2026-05-23 (MetasController ainda é Blade)
//   regras                            2026-08-04
//   kb · custos · roadmap
//   governanca-mcp · qualidade-jana   2026-08-05 (ADR 0366)
//
// A US nomeava só o `painel` (resíduo da onda 1); os outros 8 são o mesmo defeito
// com a mesma prova — ghost removido + zero consumidor —, então saem juntos.
export type JanaAreaTab =
  | 'chat'      // = copiloto
  | 'dashboard' // = o Painel; a key vira 'painel' quando o ghost for rekeyado
  | 'memoria'   // = memorias
  | 'alertas'   // = ghost `alertas` (2026-09-02, 3ª aba da âncora `JmTabs`) — key = ghost key
  | 'acoes'     // = ghost `acoes` (2026-09-02, 4ª aba — a fila HITL)
  | 'plataforma' // = ghost `plataforma` (2026-09-02, 6ª aba — só jana.superadmin real)
  // Ghost keys canon — ninguém passa hoje, mas são os alvos do map abaixo e
  // valem como valor direto (o PageHeaderTabs casa por key).
  | 'copiloto'
  | 'memorias';

// Map retrocompat — telas antigas passam 'chat'/'memoria'; convertemos pro
// ghost key canon do DataController Jana. O 'cockpit' saiu do type na onda 4
// (US-COPI-148) junto com a Page e o ghost do menu.
function mapActiveToGhostKey(active: JanaAreaTab): string {
  switch (active) {
    case 'chat':
      return 'copiloto';
    case 'memoria':
      return 'memorias';
    default:
      return active;
  }
}

const horaCurta = (): string =>
  new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

export interface JanaAreaHeaderProps {
  active: JanaAreaTab;
  /**
   * Nome do business — vai pro subtitle. Onda de fusão (2026-08-07): SUBIU do
   * header próprio do `JanaCockpit`, que era a segunda barra. Opcional porque
   * Conversa/Memória não recebem `janaContext` do controller delas.
   */
  businessName?: string;
  /** id do business — escopo Tier 0 visível ao operador. Ver `businessName`. */
  businessId?: number;
  /**
   * Ações da tela, à ESQUERDA do primary "Conversar". Injetadas pelo consumidor
   * de propósito: o Painel tem Configurar/Exportar, Conversa/Memória não têm —
   * declarar aqui inventaria botão em tela que nunca teve.
   */
  actions?: ReactNode;
}

export function JanaAreaHeader({
  active,
  businessName,
  businessId,
  actions,
}: JanaAreaHeaderProps): ReactNode {
  const ghostKey = mapActiveToGhostKey(active);

  // "Atualizado HH:MM" é BOTÃO (protótipo `.jc-updated-b` + `onRefresh`), não
  // texto: no `JanaCockpit` ele era um `<span>` que exibia a hora do RENDER e
  // não reapurava nada. A hora só avança quando o reload de fato volta.
  const [atualizadoEm, setAtualizadoEm] = useState(horaCurta);
  const [reapurando, setReapurando] = useState(false);

  const reapurar = () => {
    setReapurando(true);
    router.reload({
      onSuccess: () => setAtualizadoEm(horaCurta()),
      onFinish: () => setReapurando(false),
    });
  };

  return (
    <PageHeader
      // sticky: PT-04 §Anatomia slot 1. O header antigo já era sticky — o canon
      // não traz isso por default, então preservamos via className pra não
      // regredir o comportamento de scroll das 3 telas.
      className="sticky top-0 z-10 bg-card/95 backdrop-blur"
      // AVATAR da área — `JanaAvatar` quadrado mono "J", o que a âncora tem
      // (`jana-merge.jsx` §`JanaHeader` → `.jc-avatar`, medido no preview:
      // 40×40, radius 8px, `bg` accent, peso 700) e o que o `SPEC.md`
      // (US-COPI-105) descreve como *"JanaAvatar quadrado mono 'J' bg-primary"*.
      //
      // SUBSTITUI o dot de 8px que estava aqui desde 2026-08-08. O dot nasceu
      // como o possível na época: [W] pediu a identidade de volta, o `leading`
      // acabava de ganhar o slot, e um ponto era o que cabia dentro do `<h1>`.
      // Medido em prod em 2026-08-18, com [W] apontando o header: um dot de 8px
      // não é a marca de identidade que a âncora desenha — o avatar é.
      //
      // POR QUE cabe no `leading` (e por que isso importa): o slot vive DENTRO
      // do `<h1>`, então trocar 8px por 40px cresce a linha do título (medido:
      // header 88 → 98px). Testado ao vivo antes de escrever este código, e o
      // resultado é fiel. A alternativa — slot novo no `PageHeader` pra pôr o
      // avatar ao LADO do bloco título+subtítulo, como a âncora faz — tornaria
      // o diff `frontend-compartilhado` → 37 telas no visreg, 29 sem baseline.
      // Foi o mesmo preço que tirou o `titleBadge` da onda 1.
      //
      // DIVERGÊNCIA declarada: na âncora o subtítulo alinha com o TÍTULO (à
      // direita do avatar); aqui ele alinha com o AVATAR, porque o `<p>` do
      // canon é irmão do `<h1>`, não filho. É a única diferença de posição.
      //
      // Cor por TOKEN, nunca crua: `bg-primary` é o que o SPEC pede, e cor crua
      // em `style` inline reprova no `ds/no-inline-raw-color` — foi o que pegou
      // a 1ª versão da onda 1 (`Pro.tsx` 3 → 4 no ratchet).
      leading={
        <span
          aria-hidden
          className="mr-3 inline-grid size-10 shrink-0 place-items-center rounded-lg bg-primary align-middle text-[17px] font-bold text-primary-foreground"
        >
          J
        </span>
      }
      title="Jana"
      suffix=" · Analista IA"
      // Subtítulo = identidade do tenant, em MONO como a âncora (`.jc-id p`). O
      // "Atualizado" que morava aqui foi pra Zona R (ver `actions` abaixo).
      subtitle={
        (businessName || businessId != null) && (
          <span className="font-mono tracking-wide">
            {businessName && (
              <span className="font-semibold">{businessName.toUpperCase()}</span>
            )}
            {businessName && businessId != null && <span className="mx-1.5 opacity-40">·</span>}
            {businessId != null && <>biz={businessId}</>}
          </span>
        )
      }
      actions={
        <>
          {/* "Atualizado HH:MM" — 1º item da Zona R, ANTES do selo de plano, como na
              âncora (`.jc-header-r`: `jc-updated` → `{plano}` → botões). É BOTÃO de
              reapuração (protótipo `.jc-updated-b` + `onRefresh`), não texto. */}
          <button
            type="button"
            onClick={reapurar}
            disabled={reapurando}
            title="Reapurar agora"
            className="mr-1.5 inline-flex items-center gap-1.5 text-[11.5px] text-muted-foreground hover:text-foreground disabled:opacity-60"
          >
            <span aria-hidden className="size-[7px] rounded-full bg-success" />
            {reapurando ? 'Reapurando…' : `Atualizado ${atualizadoEm}`}
          </button>
          {actions}
        </>
      }
      // Barra de abas em FAIXA PRÓPRIA abaixo do título — posição do protótipo
      // (`{tabs}` depois de `<JanaHeader/>`) e do canon `Cliente/Index.tsx`. O `px-6`
      // alinha o início da barra com o título; a `border-b` do `<header>` é a linha
      // de base em que o underline da aba ativa cola (`-mb-px` do PageHeaderTabs).
      below={
        <div className="px-6">
          <JanaSubNav active={ghostKey} hidePrimary />
        </div>
      }
    />
  );
}
