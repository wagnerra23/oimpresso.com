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
// A moldura (portal dentro do `.cockpit`, cabeçalho, corpo em `--bg-2`, seções em cartão,
// rodapé e botões do DS) mora em `Gaveta.tsx`, a mesma das gavetas de Receitas e Insumos.

import { Pencil } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack } from '@/Components/layout/stack';
import { fmt, num } from '../_lib/formato';
import Gaveta, { BOTAO_PRIMARIO, BOTAO_SECUNDARIO, SecaoGaveta } from './Gaveta';

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
    <Gaveta
      aberta={!!ordem}
      onClose={onClose}
      titulo={ordem?.ref_no ?? 'Ordem de produção'}
      subtitulo={subtitulo}
      rodape={
        <>
          <Button variant="outline" className={BOTAO_SECUNDARIO} onClick={onClose}>
            Fechar
          </Button>
          {ordem && !ordem.mfg_is_final ? (
            <Button asChild className={BOTAO_PRIMARIO}>
              <a href={`/manufacturing/production/${ordem.id}/edit`}>
                <Pencil size={13} className="size-[13px]" aria-hidden />
                Editar ordem
              </a>
            </Button>
          ) : null}
        </>
      }
    >
      {detalhe ? (
        <>
          <SecaoGaveta titulo="Ingredientes consumidos">
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
          </SecaoGaveta>

          <SecaoGaveta titulo="Custo">
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
          </SecaoGaveta>
        </>
      ) : detalhe === null ? (
        <p className="mfg-note px-[18px]">Esta ordem não existe nesta empresa.</p>
      ) : (
        <Stack gap={2} className="p-4">
          <Skeleton className="h-24 w-full" />
          <Skeleton className="h-32 w-full" />
        </Stack>
      )}
    </Gaveta>
  );
}
