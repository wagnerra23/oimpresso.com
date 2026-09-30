// Drawer de DETALHE do bem — leitura pura (decisão [W] 2026-09-30, `_saida-16b.md`).
//
// Fonte visual: `prototipo-ui/cowork/Wagner/patrimonio-page.jsx`, `function BemDrawer` (:632),
// aba `alocacoes` (:714-735). Desvios DECLARADOS, cada um com o motivo:
//
//   • o protótipo modela 1 alocação : 1 revogação (`a.revoke`); o nosso modelo é 1 : N
//     (devolução parcial grava vários `revoke` com o mesmo `parent_id` — `_saida-16.md`).
//     Aqui cada alocação lista TODAS as suas devoluções, com código, quantidade, data,
//     autor e motivo — os 5 campos que a `_saida-16` mediu faltando na tela Alocações;
//   • só as abas Resumo e Alocações. Garantia, Manutenção, Depreciação e Histórico não
//     entram nesta onda — aba que não mostra dado é afordância falsa (charter, Non-Goals);
//   • sem o placar quantidade/alocada/livre do topo da aba: é agregado de QUANTIDADE sobre
//     o mesmo `Alocado` que o charter declara não-auditado (resíduo Tier 0 do índice);
//   • sem "Valor de aquisição" (unitário × quantidade): número de VALOR novo, REGRA MESTRE;
//   • sem botões no rodapé (Alocar · Revogar · Editar): escrita é de outras threads (17/18).
//
// Excluir devolução NÃO entra aqui — é escrita de saldo, thread 18.

import { useState } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Badge } from '@/Components/ui/badge';
import { Skeleton } from '@/Components/ui/skeleton';
import SubNav from '@/Components/shared/SubNav';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface Devolucao {
  id: number;
  ref_no: string;
  quantidade: number;
  em: string | null;
  por: string | null;
  motivo: string | null;
}

export interface AlocacaoDoBem {
  id: number;
  ref_no: string;
  para: string | null;
  por: string | null;
  quantidade: number;
  em: string | null;
  ate: string | null;
  motivo: string | null;
  /** Soma das devoluções LISTADAS abaixo (escopadas por business na própria devolução). */
  devolvido: number;
  devolucoes: Devolucao[];
}

export interface BemDetalhe {
  id: number;
  asset_code: string;
  nome: string;
  modelo: string | null;
  serie: string | null;
  categoria: string | null;
  local: string | null;
  tipo_compra: string | null;
  compra_em: string | null;
  alocavel: boolean;
  quantidade: number;
  valor_unitario: number;
  descricao: string | null;
  alocacoes: AlocacaoDoBem[];
}

type Aba = 'resumo' | 'alocacoes';

function qtd(valor: number): string {
  return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(valor ?? 0);
}

function Campo({ rotulo, valor, mono }: { rotulo: string; valor: React.ReactNode; mono?: boolean }) {
  return (
    <Stack gap={0}>
      <small className="mb-0.5 text-[10px] uppercase tracking-[.05em] text-muted-foreground">{rotulo}</small>
      <span className={'text-[12.5px]' + (mono ? ' font-mono tabular-nums' : '')}>{valor ?? '—'}</span>
    </Stack>
  );
}

function SeloDaAlocacao({ a }: { a: AlocacaoDoBem }) {
  if (a.devolvido <= 0) return <Badge variant="success" dot>Ativa</Badge>;
  if (a.devolvido >= a.quantidade) return <Badge variant="neutral" dot>Devolvida</Badge>;
  return <Badge variant="warning" dot>Devolvida em parte</Badge>;
}

function Alocacao({ a }: { a: AlocacaoDoBem }) {
  return (
    <li className="rounded-lg border bg-background px-[13px] py-2.5" data-testid="alocacao-do-bem">
      <Stack gap={0}>
        <Inline gap={3} align="center" justify="between">
          <b className="text-[12.5px]">{a.para ?? '—'}</b>
          <SeloDaAlocacao a={a} />
        </Inline>
        <small className="mt-0.5 text-[11px] text-muted-foreground tabular-nums">
          {qtd(a.quantidade)} un. · {a.em ?? '—'}
          {a.ate ? ` → ${a.ate}` : ' · indeterminado'}
          {a.por ? ` · entregue por ${a.por}` : ''}
        </small>
        <small className="mt-0.5 font-mono text-[10.5px] text-muted-foreground">{a.ref_no}</small>
        {a.devolucoes.length > 0 ? (
          <Stack gap={1} className="mt-2">
            <small className="text-[11px] font-semibold">
              Devoluções ({a.devolucoes.length}) · {qtd(a.devolvido)} de {qtd(a.quantidade)} un.
            </small>
            <ul className="border-l pl-3" aria-label={`Devoluções da alocação ${a.ref_no}`}>
              {a.devolucoes.map((r) => (
                <li key={r.id} className="py-0.5 text-[11px]" data-testid="devolucao">
                  <span className="font-mono">{r.ref_no}</span>
                  <span className="tabular-nums"> · {qtd(r.quantidade)} un. · {r.em ?? '—'}</span>
                  {r.por ? <span> · por {r.por}</span> : null}
                  {r.motivo ? <small className="block text-muted-foreground">{r.motivo}</small> : null}
                </li>
              ))}
            </ul>
          </Stack>
        ) : (
          <small className="mt-0.5 text-[11px] text-muted-foreground">Nenhuma devolução registrada.</small>
        )}
      </Stack>
    </li>
  );
}

export default function DetalheBemDrawer({
  aberto,
  detalhe,
  tiposCompra,
  onClose,
}: {
  aberto: boolean;
  /** `undefined` = carregando (prop deferida); `null` = não encontrado nesta empresa. */
  detalhe: BemDetalhe | null | undefined;
  tiposCompra: Record<string, string>;
  onClose: () => void;
}) {
  const [aba, setAba] = useState<Aba>('resumo');
  const abas: Array<{ key: Aba; label: string; n?: number }> = [
    { key: 'resumo', label: 'Resumo' },
    { key: 'alocacoes', label: 'Alocações', n: detalhe?.alocacoes.length },
  ];

  return (
    <Sheet open={aberto} onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent side="right" className="w-full gap-0 sm:max-w-[720px]" data-testid="detalhe-bem">
        <SheetHeader className="gap-1 border-b px-[18px] py-4">
          <SheetTitle className="text-[17px]">{detalhe ? detalhe.nome : detalhe === null ? 'Bem não encontrado' : 'Carregando bem…'}</SheetTitle>
          <SheetDescription className="text-[12.5px]">
            {detalhe
              ? [detalhe.asset_code, detalhe.categoria, detalhe.modelo, detalhe.local].filter(Boolean).join(' · ')
              : detalhe === null
                ? 'Este bem não existe nesta empresa ou está fora dos locais que você pode ver.'
                : ' '}
          </SheetDescription>
        </SheetHeader>

        {detalhe ? (
          <>
            {/* `<SubNav>` do DS (switch in-page controlado, sem URL) — `ds/no-inline-tablist`. */}
            <SubNav
              ariaLabel="Abas do bem"
              className="px-[18px]"
              value={aba}
              onChange={(v) => setAba(v as Aba)}
              items={abas.map((t) => ({ value: t.key, label: t.label, badge: t.n }))}
            />

            <div role="tabpanel" className="flex-1 overflow-y-auto">
              {/* Seção do DrawerSection do protótipo, MEDIDA em 2026-09-30 (`patrimonio-page.jsx`
                  BemDrawer, dark, 1440px): margem 10/12, raio 12, padding 14/18. Raio 12 = o teto do
                  charter de DS; `rounded-[12px]` porque em runtime `rounded-lg` mede 8px, não 12. */}
              <section className="mx-3 my-2.5 rounded-[12px] border bg-card px-[18px] py-3.5">
              <h4 className="mb-2 text-[10.5px] font-semibold uppercase tracking-[.05em] text-muted-foreground">
                {aba === 'resumo' ? 'Identificação' : 'Alocações'}
              </h4>
              {aba === 'resumo' ? (
                <Stack gap={4}>
                  <Grid className="grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-x-4 gap-y-2.5">
                    <Campo rotulo="Código do ativo" valor={detalhe.asset_code} mono />
                    <Campo rotulo="Série/Modelo" valor={detalhe.modelo} />
                    <Campo rotulo="Número de série" valor={detalhe.serie} mono />
                    <Campo rotulo="Categoria" valor={detalhe.categoria} />
                    <Campo rotulo="Local" valor={detalhe.local} />
                    <Campo rotulo="Tipo de compra" valor={detalhe.tipo_compra ? tiposCompra[detalhe.tipo_compra] ?? detalhe.tipo_compra : null} />
                    <Campo rotulo="Data da compra" valor={detalhe.compra_em} mono />
                    <Campo rotulo="É atribuível?" valor={detalhe.alocavel ? 'sim — pode ser alocado' : 'não'} />
                    <Campo rotulo="Quantidade" valor={qtd(detalhe.quantidade)} mono />
                    <Campo
                      rotulo="Valor unitário"
                      valor={new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(detalhe.valor_unitario)}
                      mono
                    />
                  </Grid>
                  {detalhe.descricao ? <Campo rotulo="Descrição" valor={detalhe.descricao} /> : null}
                </Stack>
              ) : !detalhe.alocavel ? (
                <p className="text-[12.5px] text-muted-foreground">Bem não atribuível — fica no local, não vai pra mão de colaborador.</p>
              ) : detalhe.alocacoes.length === 0 ? (
                <p className="text-[12.5px] text-muted-foreground">Nenhuma alocação registrada.</p>
              ) : (
                <ul className="space-y-[7px]">
                  {detalhe.alocacoes.map((a) => <Alocacao key={a.id} a={a} />)}
                </ul>
              )}
              </section>
            </div>
          </>
        ) : detalhe === undefined ? (
          <Stack gap={2} className="p-4">
            <Skeleton className="h-6 w-1/2" />
            <Skeleton className="h-24 w-full" />
          </Stack>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}
