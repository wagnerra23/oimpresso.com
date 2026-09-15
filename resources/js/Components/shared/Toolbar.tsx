import * as React from 'react';
import { Inline } from '@/Components/layout';
import { cn } from '@/Lib/utils';

/**
 * Toolbar — barra de ferramentas de tres zonas (left / center / right) acima de
 * listas, tabelas e editores. Substitui as barras improvisadas modulo a modulo.
 *
 * FONTE DE DESIGN (portada, nao inventada):
 *   prototipo-ui/design-system/components/Toolbar/Toolbar.jsx + Toolbar.d.ts
 * Consumidor que ja espera este contrato: `Barra` do Ponto
 *   (prototipo-ui/cowork/Wagner/ponto-ui.jsx:299) — usa `bordered={false}` dentro
 *   da propria moldura (radius 12 + border 1px), porque ali a barra e bloco solto
 *   acima dos cards, nao cabecalho de painel.
 *
 * NAO e o PageFilters. `PageFilters` e container de filtros (chips ativos + grid
 * de campos + "Limpar tudo") e desenha moldura propria; este e uma faixa de acoes
 * sem semantica de filtro. As duas peças coexistem — o Toolbar entra ONDE NAO HA
 * moldura, nao substitui.
 *
 * Alvo medido a 1280px (dark, persona Larissa): barra 1215x71px, uma faixa,
 * gap 8px, padding 9px 12px, align center, wrap. Abaixo de ~900px ela quebra
 * mantendo o gap — reflow e comportamento correto do wrap, nao defeito.
 *
 * Uso:
 *   <Toolbar label="Filtros da competencia" bordered={false}>
 *     <Select ... />
 *     <Input ... />
 *   </Toolbar>
 *
 *   <Toolbar label="Acoes da lista" left={<Busca />} right={<BotaoExportar />} />
 */

export interface ToolbarProps extends Omit<React.ComponentProps<'div'>, 'children'> {
  /** Nome acessivel da barra. Obrigatorio: `role="toolbar"` sem nome nao se distingue de outra. */
  label: string;
  /** Zona esquerda — busca, filtros, acoes primarias. */
  left?: React.ReactNode;
  /** Zona central — ocupa o espaco livre. Sem ela, o espaco vira o spacer que cola o `right` na borda. */
  center?: React.ReactNode;
  /** Zona direita — densidade, exportar, kebab. */
  right?: React.ReactNode;
  children?: React.ReactNode;
  sticky?: boolean;
  dense?: boolean;
  tone?: 'surface' | 'muted' | 'transparent';
  /**
   * Borda de baixo. Default `true` (uso em cabecalho de painel). Passe `false`
   * quando o PAI ja desenha radius + border, senao a borda sai dupla.
   */
  bordered?: boolean;
}

const TONE: Record<NonNullable<ToolbarProps['tone']>, string> = {
  surface: 'bg-card',
  muted: 'bg-muted',
  transparent: 'bg-transparent',
};

/** Espaco elastico que empurra o que vem depois para a borda. Altura 0 — nao altera a faixa. */
export function ToolbarSpacer() {
  return <span aria-hidden data-slot="toolbar-spacer" className="min-w-2 flex-1" />;
}

export default function Toolbar({
  label,
  left,
  center,
  right,
  children,
  sticky = false,
  dense = false,
  tone = 'surface',
  bordered = true,
  className,
  ...props
}: ToolbarProps) {
  // O consumidor pode passar o proprio <ToolbarSpacer/> em `children` pra escolher onde
  // o espaco abre. Nesse caso a barra NAO soma o dela — dois spacers dividiriam a folga
  // e o `right` nao colaria na borda. Fronteira honesta: so enxerga filho direto; spacer
  // dentro de Fragment ou wrapper nao e detectado.
  const spacerNoChildren = React.useMemo(
    () =>
      React.Children.toArray(children).some(
        (filho) => React.isValidElement(filho) && filho.type === ToolbarSpacer,
      ),
    [children],
  );

  return (
    <Inline
      role="toolbar"
      aria-label={label}
      data-slot="toolbar"
      gap={2}
      align="center"
      wrap
      className={cn(
        'min-w-0',
        dense ? 'px-2.5 py-1.5' : 'px-3 py-[9px]',
        TONE[tone],
        bordered ? 'border-b border-border' : 'border-b-0',
        sticky && 'sticky top-0 z-[5]',
        className,
      )}
      {...props}
    >
      {left ? (
        <Inline gap={1} align="center" className="min-w-0">
          {left}
        </Inline>
      ) : null}

      {children}

      {center ? (
        <Inline gap={1} align="center" justify="center" className="min-w-0 flex-1">
          {center}
        </Inline>
      ) : spacerNoChildren ? null : (
        <ToolbarSpacer />
      )}

      {right ? (
        <Inline gap={1} align="center" className="min-w-0">
          {right}
        </Inline>
      ) : null}
    </Inline>
  );
}

export { Toolbar };
