// Venda V3 — PREVIEW DE DESIGN, paralelo e isolado.
//
// POR QUE ESTA TELA EXISTE (Luiz [L], 2026-08-06)
// `Sells/Create.tsx` (rota /pos/create) NÃO pode mudar: ROTA LIVRE (biz=4 —
// Larissa/Guilherme) opera nela e alteração quebra contrato comercial. Então o
// redesenho nasce em arquivo NOVO + rota NOVA (/sells/create-v3), sem tocar em
// nada que a tela viva consome. Racional completo: memory/requisitos/Sells/RUNBOOK-create-v3.md
//
// REGRA DURA DESTE ARQUIVO
// Não importar nem alterar componente compartilhado que `Sells/Create.tsx`
// consome. Se precisar de variação de um componente existente, nasce cópia
// local em _components/ — nunca edição do original (a edição vazaria pra tela deles).
//
// O QUE ESTA TELA DELIBERADAMENTE NÃO FAZ
// - não calcula: total, subtotal, desconto e imposto vêm PRONTOS do controller.
//   Cálculo de valor/estoque é [V0] (REGRA MESTRE em memory/proibicoes.md) e não
//   entra em tela de preview — foi assim que nasceu o incidente num_uf de
//   2026-06-05 (final_total inflado ~×100.000 em 16 vendas do biz=4);
// - não grava: não há submit, não há POST, não há rota de escrita.
//
// Âncora de design: prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx

import AppShellV2 from '@/Layouts/AppShellV2';
import type { ReactNode } from 'react';
import { FlaskConical, Package, Receipt, User } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
// Layout é COMPOSIÇÃO destes primitivos — `<div className="flex …">` solto é
// barrado pelo gate `Layout primitives · ratchet` (ADR 0253).
import { Grid, Inline, Stack } from '@/Components/layout';

type Cliente = {
  codigo: string;
  nome: string;
  tipo: 'pf' | 'pj';
  grupo: string;
  prazo: string;
  tabela: string;
  endereco: string;
};

type Item = {
  sku: string;
  nome: string;
  un: string;
  medidas: string | null;
  qtd: string;
  valor: string;
  desc: string;
  acr: string;
  total: string;
};

type Fechamento = {
  subtotal: string;
  desconto: string;
  imposto: string;
  acrescimo: string;
  frete: string;
  total: string;
  situacao: string;
  falta: string;
};

type Props = {
  businessId: number;
  cena: { cliente: Cliente; itens: Item[]; fechamento: Fechamento };
};

/** Passo numerado — o "1 Cliente / 2 Itens / 3 Fechamento" do protótipo. */
function Passo({ n, titulo, icone }: { n: number; titulo: string; icone: ReactNode }) {
  return (
    <Inline gap={3} align="center">
      <span className="inline-flex size-6 flex-none items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground">
        {n}
      </span>
      <span className="inline-flex items-center gap-2 text-sm font-semibold">
        {icone}
        {titulo}
      </span>
    </Inline>
  );
}

/** Linha do resumo de fechamento — rótulo, régua pontilhada, valor. */
function Linha({ label, valor, forte }: { label: string; valor: string; forte?: boolean }) {
  return (
    <Inline gap={3} align="baseline" className="py-1">
      <span className={`text-xs ${forte ? 'font-semibold text-foreground' : 'text-muted-foreground'}`}>
        {label}
      </span>
      <span className="min-w-0 flex-1 border-b border-dotted border-border" />
      <span className="font-mono text-sm tabular-nums">{valor}</span>
    </Inline>
  );
}

export default function SellsCreateV3({ cena }: Props) {
  const { cliente, itens, fechamento } = cena;

  return (
    <Stack gap={4} className="mx-auto max-w-[1400px] p-4">
      {/* Marcador honesto: quem abrir por engano precisa saber em 1 segundo. */}
      <Inline
        gap={3}
        align="center"
        className="flex-wrap rounded-lg border border-warning/30 bg-warning-soft px-4 py-3"
      >
        <FlaskConical className="size-4 flex-none text-warning-fg" aria-hidden />
        <div className="min-w-0 text-sm">
          <b className="font-semibold">Preview de design — não é a tela de produção.</b>{' '}
          <span className="text-muted-foreground">
            Dados de cena, não do banco. Não salva, não calcula, não move estoque. A venda real
            continua em <code className="rounded bg-muted px-1 py-0.5 text-xs">/pos/create</code>.
          </span>
        </div>
      </Inline>

      <Inline gap={3} align="center" className="flex-wrap">
        <h1 className="text-xl font-semibold">Nova venda</h1>
        <Badge variant="outline">V3</Badge>
        <p className="text-sm text-muted-foreground">
          Cliente, itens, pagamento. O resto tem valor padrão.
        </p>
      </Inline>

      <Grid gap={4} className="lg:grid-cols-[minmax(0,1fr)_336px]">
        {/* ————— coluna de trabalho ————— */}
        <Stack gap={4}>
          <Card>
            <CardContent className="p-4">
              <Stack gap={4}>
                <Passo n={1} titulo="Cliente" icone={<User className="size-4" aria-hidden />} />
                <Grid gap={3} className="sm:grid-cols-[120px_minmax(0,1fr)]">
                  <Stack className="gap-1.5">
                    <Label htmlFor="v3-cod">Código</Label>
                    <Input id="v3-cod" readOnly value={cliente.codigo} className="font-mono" />
                  </Stack>
                  <Stack className="gap-1.5">
                    <Label htmlFor="v3-cli">Cliente / destinatário</Label>
                    <Input id="v3-cli" readOnly value={cliente.nome} />
                  </Stack>
                </Grid>
                <p className="text-xs text-muted-foreground">
                  {cliente.endereco} · grupo <b>{cliente.grupo}</b> · prazo <b>{cliente.prazo}</b> ·
                  tabela <b>{cliente.tabela}</b>
                </p>
              </Stack>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-4">
              <Stack gap={4}>
                <Inline gap={3} align="center" className="flex-wrap">
                  <Passo n={2} titulo="Itens" icone={<Package className="size-4" aria-hidden />} />
                  <Badge variant="secondary">{itens.length}</Badge>
                </Inline>

                <div className="overflow-x-auto">
                  <table className="w-full min-w-[640px] border-separate border-spacing-0 text-sm">
                    <thead>
                      <tr>
                        {[
                          'Produto / serviço',
                          'Quant.',
                          'R$ valor',
                          'Desc. %',
                          'Acrésc. %',
                          'R$ total',
                        ].map((h, i) => (
                          <th
                            key={h}
                            className={`border-b border-border bg-muted/50 px-3 py-2 text-[10.5px] font-semibold uppercase tracking-wide text-muted-foreground ${
                              i === 0 ? 'text-left' : 'text-right'
                            }`}
                          >
                            {h}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {itens.map((l) => (
                        <tr key={l.sku}>
                          <td className="border-b border-border/60 px-3 py-2">
                            <b className="font-semibold">{l.nome}</b>
                            <span className="block font-mono text-[11.5px] text-muted-foreground">
                              {l.sku} · {l.un}
                              {l.medidas ? ` · ${l.medidas}` : ''}
                            </span>
                          </td>
                          {[l.qtd, l.valor, l.desc, l.acr].map((v, i) => (
                            <td
                              key={i}
                              className="border-b border-border/60 px-3 py-2 text-right font-mono tabular-nums"
                            >
                              {v}
                            </td>
                          ))}
                          <td className="border-b border-border/60 px-3 py-2 text-right font-mono font-semibold tabular-nums">
                            {l.total}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                <p className="text-xs text-muted-foreground">
                  Grid somente leitura neste preview — a edição de linha mexe em valor e estoque, que
                  é território [V0].
                </p>
              </Stack>
            </CardContent>
          </Card>
        </Stack>

        {/* ————— coluna de fechamento ————— */}
        <Stack gap={4} asChild>
          <aside>
            <Card>
              <CardContent className="p-4">
                <Stack gap={4}>
                  <Inline gap={2} align="center" className="flex-wrap justify-between">
                    <Passo
                      n={3}
                      titulo="Fechamento"
                      icone={<Receipt className="size-4" aria-hidden />}
                    />
                    <Badge variant="destructive">{fechamento.situacao}</Badge>
                  </Inline>

                  {/* plate escuro — o único bloco de peso visual da tela.
                      Invertido por TOKEN (foreground/background), não por cor crua da paleta
                      Tailwind: aquela reprova no ds/no-raw-palette-color e não acompanha o tema. */}
                  <div className="rounded-lg bg-foreground p-4 text-background">
                    <span className="text-[10.5px] font-semibold uppercase tracking-wider text-background/70">
                      Total da venda
                    </span>
                    <b className="mt-1 block font-mono text-3xl tabular-nums">
                      R$ {fechamento.total}
                    </b>
                  </div>

                  <div>
                    <Linha label="Subtotal" valor={fechamento.subtotal} />
                    <Linha label="Desconto" valor={fechamento.desconto} />
                    <Linha label="Imposto" valor={fechamento.imposto} />
                    <Linha label="Acréscimo" valor={fechamento.acrescimo} />
                    <Linha label="Frete" valor={fechamento.frete} />
                    <Linha label="Falta receber" valor={fechamento.falta} forte />
                  </div>

                  <Button className="w-full" disabled>
                    Finalizar venda
                  </Button>
                  <p className="text-center text-xs text-muted-foreground">
                    Desabilitado de propósito — preview não grava.
                  </p>
                </Stack>
              </CardContent>
            </Card>
          </aside>
        </Stack>
      </Grid>
    </Stack>
  );
}

// Persistent Layout — mesma convenção de Sells/Create.tsx.
SellsCreateV3.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
