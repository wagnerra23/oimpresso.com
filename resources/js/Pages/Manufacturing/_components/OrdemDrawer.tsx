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

import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack } from '@/Components/layout/stack';
import { fmt, num } from '../_lib/formato';

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

const SECAO = 'mx-3 my-2.5 rounded-[12px] border bg-card px-[18px] py-3.5';
const TITULO_SECAO = 'mb-2 text-[10.5px] font-semibold uppercase tracking-[.05em] text-muted-foreground';

export default function OrdemDrawer({ ordem, detalhe, onClose }: Props) {
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
      <SheetContent side="right" className="mfg-root w-full gap-0 sm:max-w-[680px]" data-contract="drawer">
        <SheetHeader className="gap-1 border-b px-[18px] py-4">
          <SheetTitle className="text-[17px] leading-[1.3]">{ordem?.ref_no ?? 'Ordem de produção'}</SheetTitle>
          <SheetDescription className="text-[12.5px] leading-[1.45]">{subtitulo}</SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto">
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

        <SheetFooter className="flex-row justify-end gap-2 border-t">
          <Button variant="outline" onClick={onClose}>
            Fechar
          </Button>
          {ordem && !ordem.mfg_is_final ? (
            <Button asChild>
              <a href={`/manufacturing/production/${ordem.id}/edit`}>Editar ordem</a>
            </Button>
          ) : null}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
