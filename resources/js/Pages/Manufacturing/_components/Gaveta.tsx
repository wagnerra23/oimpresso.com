// Moldura das gavetas da Fabricação (ordem, receita, insumo) — o `Drawer`/`DrawerSection`/`Button`
// do DS (prototipo-ui/design-system/components/) mais as duas camadas que o shell do protótipo põe
// por cima: o corpo em `--bg-2` com cada seção em cartão (venda-v3.css, "CAMADA 3 · overlays",
// item 1) e o × no canto com a borda abaixo do título (manufacturing-page.css L166-167).
//
// Nasceu no painel da ordem (#9582, medido em produção em 2026-10-19 — Index-visual-comparison.md).
// Receitas e Insumos ainda desenhavam a moldura antiga do bundle (`.mfg-drw`), e a diferença
// aparecia ao trocar de aba ([F] 2026-10-19). As três gavetas passam a usar esta.
//
// O conteúdo vai num portal. O `mfg-root` no `SheetContent` faz os seletores `mfg-*` do bundle
// alcançarem o painel; os VALORES que eles usam (`--border`, `--bg-2`, `--text-dim`, a IBM Plex)
// moram no `.cockpit`, por isso o portal monta DENTRO dele (prop `container`), o mesmo caminho do
// painel do produto (Produto/_components/lista/PainelDetalhe.tsx).

import { useLayoutEffect, useState, type ReactNode } from 'react';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { cn } from '@/Lib/utils';

/** Painel: `--surface`, borda e sombra só à esquerda (o Drawer do DS). */
const PAINEL =
  'mfg-root w-full gap-0 border-[var(--border)] bg-[var(--surface)] shadow-[-16px_0_40px_-12px_rgb(0_0_0/0.3)] sm:max-w-[680px]';
/** O × do DS mora no canto, por cima do cabeçalho: 28×28, raio 6, esmaecido. */
const CANTO = 'absolute top-0 right-0 z-[1] flex items-center px-[18px] py-[14px]';
const FECHAR_CANTO =
  'size-7 text-[16px] font-normal text-[var(--text-mute)] hover:bg-[var(--bg-2)] hover:text-[var(--text)] dark:hover:bg-[var(--bg-2)]';
/** O padding da direita (58px) deixa o título fora do caminho do ×. */
const CABECALHO = 'gap-1 border-b border-[var(--border)] pt-4 pr-[58px] pb-4 pl-[18px]';
const TITULO = 'text-[17px] leading-[1.3] font-semibold tracking-[-0.01em] text-[var(--text)]';
const SUBTITULO = 'text-[12.5px] leading-[1.45] text-[var(--text-dim)]';
const CORPO = 'flex-1 overflow-y-auto bg-[var(--bg-2)]';
/** A borda de cima é `--border-2`: o DrawerSection a declara inline, e inline vence o cartão. */
const SECAO =
  'mx-3 my-2.5 first:mt-3 rounded-[12px] border border-[var(--border)] border-t-[var(--border-2)] bg-[var(--surface)] px-[18px] py-3.5 text-[13px] leading-[1.5] shadow-[0_1px_2px_color-mix(in_oklch,var(--text)_5%,transparent)]';
/** O DS pinta o título da seção em `--text-mute`; aqui sai em `--text-dim` pela ADR 0410 (rótulo
 *  pequeno em `--text-mute` reprova AA — a mesma troca do `.mfg-fld>span` no bundle). */
const TITULO_SECAO = 'mb-2 text-[10.5px] leading-[1.4] font-semibold uppercase tracking-[.05em] text-[var(--text-dim)]';
const RODAPE = 'flex-row justify-end gap-2 border-t border-[var(--border)] px-[18px] py-3';
/** Tamanho `default` do Button do DS: 30px, 12,5px, raio 8. Aqui `rounded-lg` é 12px e `rounded-md`
 *  é 6px (medido em 2026-10-19), por isso o valor explícito. */
const BOTAO = 'h-[30px] gap-1.5 rounded-[8px] px-3 py-0 text-[12.5px] leading-none';

/** Variante `ghost` do DS (fundo de superfície, texto esmaecido) — "Fechar", "Ficha com custo"…
 *  O par `dark:` vence o `dark:bg-input/30` do outline. Usar com `variant="outline"`. */
export const BOTAO_SECUNDARIO = `${BOTAO} border-[var(--border)] bg-[var(--surface)] text-[var(--text-dim)] shadow-none hover:border-[var(--text-mute)] hover:bg-[var(--bg-2)] hover:text-[var(--text)] dark:border-[var(--border)] dark:bg-[var(--surface)] dark:hover:bg-[var(--bg-2)]`;
/** Variante `primary`: o fundo e o hover ficam com o Button (`--color-primary`); o DS usa `--accent`,
 *  que o AppShell reescreve pelo matiz salvo (nota no topo do bundle da Fabricação). */
export const BOTAO_PRIMARIO = `${BOTAO} border border-transparent font-semibold text-[var(--accent-fg)]`;

interface Props {
  aberta: boolean;
  onClose: () => void;
  titulo: ReactNode;
  subtitulo: ReactNode;
  /** Botões do rodapé, na ordem do protótipo (o primário por último). */
  rodape: ReactNode;
  children: ReactNode;
}

export default function Gaveta({ aberta, onClose, titulo, subtitulo, rodape, children }: Props) {
  // O `.cockpit` é o contêiner que entrega os tokens e a fonte do Design System. Layout effect
  // (e não effect): com a gaveta aberta na 1ª renderização (`?ordem=`, `?insumo=`), o portal já
  // nasce no `.cockpit` em vez de montar no <body> e mudar de lugar depois.
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);

  return (
    <Sheet open={aberta} onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent
        side="right"
        container={cockpit}
        showCloseButton={false}
        className={PAINEL}
        data-contract="drawer"
      >
        <div className={CANTO}>
          <SheetClose asChild>
            <Button variant="ghost" size="icon" aria-label="Fechar" className={FECHAR_CANTO}>
              ×
            </Button>
          </SheetClose>
        </div>
        <SheetHeader className={CABECALHO}>
          <SheetTitle className={TITULO}>{titulo}</SheetTitle>
          <SheetDescription className={SUBTITULO}>{subtitulo}</SheetDescription>
        </SheetHeader>

        <div className={CORPO}>{children}</div>

        <SheetFooter className={RODAPE}>{rodape}</SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

/** `DrawerSection` do DS: título em caixa alta + conteúdo, num cartão sobre o corpo. */
export function SecaoGaveta({ titulo, children, className }: { titulo?: string; children: ReactNode; className?: string }) {
  return (
    <section className={cn(SECAO, className)}>
      {titulo ? <h4 className={TITULO_SECAO}>{titulo}</h4> : null}
      {children}
    </section>
  );
}
