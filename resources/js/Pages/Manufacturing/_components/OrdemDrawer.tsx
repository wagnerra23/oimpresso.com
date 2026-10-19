// Painel lateral de uma ordem de produção — o `MfgProducaoDrawer` do protótipo
// (prototipo-ui/cowork/Wagner/manufacturing-producao.jsx).
//
// Os números vêm prontos do servidor (`ProductionService::detalheOrdem`, UC-OP-07), com as
// contas do detalhe da tela antiga. Aqui só se formata; a única conta é a variação percentual
// entre dois números que vieram do servidor (gravado × hoje).
//
// Diferenças de propósito em relação ao protótipo, porque a cópia literal afirmaria algo falso:
//  · "Mesma ordem a preço de hoje" no lugar de "Mesma receita hoje": a conta usa a quantidade
//    GRAVADA na ordem, não a receita atual;
//  · "Editar ordem" só em rascunho: a tela antiga recusa editar ordem finalizada;
//  · sem os links "Fila de produção · ficha do produto": essas telas não existem no sistema.
//
// O conteúdo vai num portal (fora do `.mfg-root` da página), por isso o `mfg-root` é
// reaplicado no `SheetContent` para as classes `mfg-*` do bundle valerem (§5 2026-07-10).
// Isso só faz os seletores alcançarem o painel: os VALORES que eles usam (`--border`, `--bg-2`,
// `--text-dim`, a fonte IBM Plex) moram no `.cockpit`. Medido em produção em 2026-10-19, com o
// portal no <body>: sem caixa nos ingredientes, sem quadro no custo, linhas divisórias com 0px,
// cinzas brancos e fonte do sistema. Por isso o portal monta DENTRO do `.cockpit` (prop
// `container`), o mesmo caminho do painel do produto (Produto/_components/lista/PainelDetalhe.tsx).
//
// A moldura é a do `Drawer`/`DrawerSection`/`Button` do DS (prototipo-ui/design-system/components/)
// mais as duas camadas que o shell do protótipo põe por cima: o corpo em `--bg-2` com cada seção em
// cartão (venda-v3.css, "CAMADA 3 · overlays", item 1) e o × no canto com a borda abaixo do título
// (manufacturing-page.css L166-167). Medida em 2026-10-19 — Index-visual-comparison.md.

import { useLayoutEffect, useState } from 'react';
import { Pencil } from 'lucide-react';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack } from '@/Components/layout/stack';
import { fmt, num } from '../_lib/formato';

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
/** Variante `ghost` do DS (fundo de superfície, texto esmaecido). O par `dark:` vence o `dark:bg-input/30` do outline. */
const BOTAO_FECHAR = `${BOTAO} border-[var(--border)] bg-[var(--surface)] text-[var(--text-dim)] shadow-none hover:border-[var(--text-mute)] hover:bg-[var(--bg-2)] hover:text-[var(--text)] dark:border-[var(--border)] dark:bg-[var(--surface)] dark:hover:bg-[var(--bg-2)]`;
/** Variante `primary`: o fundo e o hover ficam com o Button (`--color-primary`); o DS usa `--accent`,
 *  que o AppShell reescreve pelo matiz salvo (nota no topo do bundle da Fabricação). */
const BOTAO_EDITAR = `${BOTAO} font-semibold text-[var(--accent-fg)]`;

export interface OrdemDetalhe {
  id: number;
  ref_no: string | null;
  finalizada: boolean;
  quantidade: number;
  unidade: string;
  linhas: Array<{
    nome: string;
    sku: string;
    grupo: string | null;
    quantidade: number;
    unidade: string;
    custo_unitario: number;
    subtotal: number;
  }>;
  custo: {
    ingredientes: number;
    extra: number;
    total_hoje: number;
    gravado: number;
    por_unidade: number;
  };
}

/** O que a linha da lista já sabe da ordem — o cabeçalho abre na hora, antes do detalhe chegar. */
export interface OrdemResumo {
  id: number;
  ref_no: string | null;
  transaction_date: string | null;
  location_name: string | null;
  produto: string;
  unidade: string;
  /** Líquida: o que entrou no estoque (o legado grava produzida − perdidas). */
  quantidade: number;
  /** `mfg_wasted_units` — perdidas na produção, fora da quantidade acima. */
  perdidas: number;
  criado_por: string;
  mfg_is_final: number;
}

interface Props {
  ordem: OrdemResumo | null;
  /** undefined = carregando · null = ordem não encontrada nesta empresa. */
  detalhe: OrdemDetalhe | null | undefined;
  onClose: () => void;
}

export default function OrdemDrawer({ ordem, detalhe, onClose }: Props) {
  // O `.cockpit` é o contêiner que entrega os tokens e a fonte do Design System. Layout effect
  // (e não effect): com `?ordem=` na URL o painel abre na 1ª renderização, e assim o portal já
  // nasce no `.cockpit` em vez de montar no <body> e mudar de lugar depois.
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);

  const subtitulo = ordem
    ? [
        ordem.transaction_date,
        ordem.location_name,
        ordem.produto,
        // A quantidade gravada já é líquida. Com perda, o rótulo diz isso (a tela antiga mostra
        // a bruta, "Quantidade 2,00 · desperdiçada 1,00"; aqui: "1,00 UN em estoque · 1,00 perdida").
        `${num(ordem.quantidade, 2)}${ordem.unidade ? ` ${ordem.unidade}` : ''}` +
          (ordem.perdidas > 0
            ? ` em estoque · ${num(ordem.perdidas, 2)} perdida${ordem.perdidas === 1 ? '' : 's'}`
            : ''),
        ordem.criado_por ? `por ${ordem.criado_por}` : null,
      ]
        .filter(Boolean)
        .join(' · ')
    : '';

  const variacao =
    detalhe && detalhe.custo.gravado > 0
      ? ((detalhe.custo.total_hoje - detalhe.custo.gravado) / detalhe.custo.gravado) * 100
      : 0;

  return (
    <Sheet open={!!ordem} onOpenChange={(o) => { if (!o) onClose(); }}>
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
          <SheetTitle className={TITULO}>{ordem?.ref_no ?? 'Ordem de produção'}</SheetTitle>
          <SheetDescription className={SUBTITULO}>{subtitulo}</SheetDescription>
        </SheetHeader>

        <div className={CORPO}>
          {detalhe ? (
            <>
              <section className={SECAO}>
                <h4 className={TITULO_SECAO}>Ingredientes consumidos</h4>
                {detalhe.linhas.length === 0 ? (
                  <p className="mfg-note">Nenhum ingrediente gravado nesta ordem.</p>
                ) : (
                  <div className="mfg-grp">
                    {detalhe.linhas.map((l, i) => (
                      <div className="mfg-ing" key={`${l.sku}-${i}`}>
                        <span className="n">
                          {l.nome}
                          <small>{[l.sku, l.grupo].filter(Boolean).join(' · ')}</small>
                        </span>
                        <span className="m">
                          {num(l.quantidade, 3)} {l.unidade}
                        </span>
                        <span className="m">{fmt(l.custo_unitario)}</span>
                        <span className="m tot">{fmt(l.subtotal)}</span>
                      </div>
                    ))}
                  </div>
                )}
              </section>

              <section className={SECAO}>
                <h4 className={TITULO_SECAO}>Custo</h4>
                <dl className="mfg-tot">
                  <dt>Ingredientes (preço de hoje)</dt>
                  <dd>{fmt(detalhe.custo.ingredientes)}</dd>
                  <dt>Custo extra</dt>
                  <dd>{fmt(detalhe.custo.extra)}</dd>
                  <hr />
                  <dt className="mfg-tot-big-dt">{detalhe.finalizada ? 'Total hoje' : 'Total'}</dt>
                  <dd className="mfg-tot-big-dd">{fmt(detalhe.custo.total_hoje)}</dd>
                  <dt>Custo por unidade</dt>
                  <dd>{fmt(detalhe.custo.por_unidade)}</dd>
                  <dt>Situação</dt>
                  <dd>{detalhe.finalizada ? 'Finalizada · estoque movimentado' : 'Rascunho · sem movimento de estoque'}</dd>
                  {detalhe.finalizada ? (
                    <>
                      <hr />
                      <dt>Custo congelado na produção</dt>
                      <dd>{fmt(detalhe.custo.gravado)}</dd>
                      <dt>Mesma ordem a preço de hoje</dt>
                      <dd>
                        {fmt(detalhe.custo.total_hoje)} ({variacao > 0 ? '+' : ''}
                        {num(variacao, 1)}%)
                      </dd>
                    </>
                  ) : null}
                </dl>
              </section>
            </>
          ) : detalhe === null ? (
            <p className="mfg-note px-[18px]">Esta ordem não existe nesta empresa.</p>
          ) : (
            <Stack gap={2} className="p-4">
              <Skeleton className="h-24 w-full" />
              <Skeleton className="h-32 w-full" />
            </Stack>
          )}
        </div>

        <SheetFooter className={RODAPE}>
          <Button variant="outline" className={BOTAO_FECHAR} onClick={onClose}>
            Fechar
          </Button>
          {ordem && !ordem.mfg_is_final ? (
            <Button asChild className={BOTAO_EDITAR}>
              <a href={`/manufacturing/production/${ordem.id}/edit`}>
                <Pencil size={13} className="size-[13px]" aria-hidden />
                Editar ordem
              </a>
            </Button>
          ) : null}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
