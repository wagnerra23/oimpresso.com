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
import { PageHeaderPrimary } from '@/Components/PageHeader';
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
      // Dot da área (hue 220 = SIDEBAR_GROUP_HUE.ia). Estava no header antigo,
      // sumiu na 1ª versão desta onda porque o PageHeader canon não tinha slot
      // — [W] pediu de volta ao ver a tela em prod (2026-08-08). Voltou pelo
      // slot `leading` opt-in, não por header hand-rolado fora do padrão.
      leading={
        <span
          aria-hidden
          className="mr-2 inline-block size-2 shrink-0 rounded-full align-middle"
          style={{ background: 'oklch(0.62 0.13 220)' }}
        />
      }
      title="Jana"
      suffix=" · Analista IA"
      subtitle={
        <>
          {businessName && (
            <span className="font-semibold">{businessName.toUpperCase()}</span>
          )}
          {businessId != null && (
            <>
              <span className="mx-1.5 opacity-40">·</span>biz={businessId}
            </>
          )}
          {(businessName || businessId != null) && (
            <span className="mx-1.5 opacity-40">·</span>
          )}
          <button
            type="button"
            onClick={reapurar}
            disabled={reapurando}
            title="Reapurar agora"
            className="inline-flex items-center gap-1.5 hover:text-foreground disabled:opacity-60"
          >
            <span aria-hidden className="size-1.5 rounded-full bg-success" />
            {reapurando ? 'Reapurando…' : `Atualizado ${atualizadoEm}`}
          </button>
        </>
      }
      subnav={<JanaSubNav active={ghostKey} hidePrimary />}
      actions={
        <>
          {actions}
          {/* primary "Conversar" roxo 295 universal (ADR 0190, supersede o hue 220
              azul da ADR 0182). Onda 3: destino passou de `/ia` (que virou o Painel)
              pra `/ia/conversa`.
              2026-08-08: migrado do shim JanaPrimaryButton pro canon <PageHeaderPrimary>.
              O shim emitia `.os-btn primary`, mas no CSS servido a única família de
              regras `.os-btn` é escopada `.sells-cowork` → nenhuma casava e o botão
              rendia nu (medido em /ia/memoria: padding 0, radius 0, texto em 2 linhas). */}
          <PageHeaderPrimary label="Conversar" onClick={() => router.visit('/ia/conversa')} />
        </>
      }
    />
  );
}
