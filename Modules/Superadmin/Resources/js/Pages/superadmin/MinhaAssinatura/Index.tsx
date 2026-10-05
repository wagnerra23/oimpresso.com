// @memcofre
//   tela: /subscription
//   module: Superadmin
//   stories: US-SUPER-003 · thread Superadmin/07 (Blade/AdminLTE → Inertia)
//   permissao: superadmin.access_package_subscriptions
//
// O NEGÓCIO vendo o próprio plano. Responde: "qual é o meu plano, até quando vale, e o que mais
// posso contratar?". Charter: ./Index.charter.md · Casos: ./Index.casos.md
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-minha-assinatura.md
//
// Valor: todo preço chega como TEXTO pronto do servidor (`moedaComoBlade`), no mesmo formato
// que a Blade mostrava. Esta tela não formata nem calcula dinheiro — não há literal monetário
// aqui (Tier 0, memory/proibicoes.md §CÁLCULO DE VALOR).
//
// Tela de cliente: sem CTA chamativo. Os pacotes levam ao pagamento, que segue em Blade
// (`/subscription/{id}/pay`), por isso o link é <a> comum e não navegação Inertia.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { plural, tomDaAssinatura } from '../_components/assinatura';

interface Cartao { id: number; pacote: string; inicio: string | null; fim: string | null; offline: boolean }
interface Linha {
  id: number; pacote: string; inicio: string | null; trial_fim: string | null; fim: string | null;
  preco: string; pago_via: string; transacao: string; status: string; criado_em: string | null; criado_por: string;
}
interface Pacote {
  id: number; nome: string; descricao: string; gratis: boolean; preco: string; intervalo: string;
  intervalo_count: number; trial_dias: number; locais: number; usuarios: number; produtos: number;
  faturas: number; modulos: string[]; link: { url: string; texto: string } | null;
}
interface Dados {
  emissor: Record<string, string | null>;
  negocio: { nome: string; impostos: { rotulo: string; numero: string }[] };
  ativa: (Cartao & { dias_restantes: number }) | null;
  proximas: Cartao[]; aguardando: Cartao[]; historico: Linha[]; pacotes: Pacote[];
}

const INTERVALO: Record<string, [string, string]> = { days: ['dia', 'dias'], months: ['mês', 'meses'], years: ['ano', 'anos'] };
const ciclo = (n: number, i: string) => (n === 1 ? INTERVALO[i]?.[0] ?? i : `${n} ${INTERVALO[i]?.[1] ?? i}`);
/** `0` = ilimitado (COMMENT da coluna no UltimatePOS), nunca "zero permitido". */
const limite = (n: number, s: string, p: string, ilim: string) => (n === 0 ? ilim : plural(n, s, p));

function MinhaAssinaturaIndex({ assinatura }: { assinatura?: Dados }) {
  return (
    <div className="pb-8">
      <PageHeader title="Minha assinatura" moduleNav description="O plano do seu negócio e os pacotes disponíveis" />
      <Deferred data="assinatura" fallback={<div className="px-6 pt-4"><Skeleton className="h-64 w-full" /></div>}>
        <Conteudo dados={assinatura} />
      </Deferred>
    </div>
  );
}

function Conteudo({ dados }: { dados?: Dados }) {
  const [aberta, setAberta] = useState<Linha | null>(null);
  if (!dados) return null;

  return (
    <>
      <section className="grid gap-4 px-6 pt-4 sm:grid-cols-2 xl:grid-cols-3" data-contract="superadmin.minha-assinatura.situacao">
        {dados.ativa ? (
          <CartaoSituacao c={dados.ativa} selo="Ativa">
            {dados.ativa.inicio} a {dados.ativa.fim} · {plural(dados.ativa.dias_restantes, 'dia restante', 'dias restantes')}
          </CartaoSituacao>
        ) : (
          <Card className="sm:col-span-2 xl:col-span-3">
            <CardContent className="p-0">
              <EmptyState title="Nenhuma assinatura ativa" description="Escolha um pacote abaixo para continuar usando o sistema." />
            </CardContent>
          </Card>
        )}
        {dados.proximas.map((c) => (
          <CartaoSituacao key={c.id} c={c} selo="Próxima">{c.inicio} a {c.fim}</CartaoSituacao>
        ))}
        {dados.aguardando.map((c) => (
          <CartaoSituacao key={c.id} c={c} selo="Pendente">
            {c.offline ? 'Aguardando a confirmação do pagamento' : 'Aguardando a confirmação do gateway de pagamento'}
          </CartaoSituacao>
        ))}
      </section>

      <section className="px-6 pt-6">
        <h2 className="pb-2 text-sm font-medium">Histórico</h2>
        <Card>
          <CardContent className="overflow-x-auto p-0">
            {dados.historico.length === 0 ? (
              <EmptyState title="Nenhuma assinatura registrada" />
            ) : (
              <table className="w-full text-sm" data-contract="superadmin.minha-assinatura.historico">
                <thead className="text-left text-xs text-muted-foreground">
                  <tr>
                    <th className="px-4 py-2 font-medium">Pacote</th>
                    <th className="px-4 py-2 font-medium">Vigência</th>
                    <th className="px-4 py-2 text-right font-medium">Valor</th>
                    <th className="px-4 py-2 font-medium">Status</th>
                    <th className="px-4 py-2" />
                  </tr>
                </thead>
                <tbody>
                  {dados.historico.map((l) => (
                    <tr key={l.id} className="border-t">
                      <td className="px-4 py-2.5">{l.pacote}</td>
                      <td className="px-4 py-2.5 tabular-nums">{l.inicio ?? '—'} a {l.fim ?? '—'}</td>
                      <td className="px-4 py-2.5 text-right tabular-nums">{l.preco}</td>
                      <td className="px-4 py-2.5"><Badge variant={tomDaAssinatura(l.status)}>{l.status}</Badge></td>
                      <td className="px-4 py-2.5 text-right">
                        <Button variant="ghost" size="sm" onClick={() => setAberta(l)}>Ver</Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>
      </section>

      <section className="px-6 pt-6">
        <h2 className="pb-2 text-sm font-medium">Pacotes</h2>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-contract="superadmin.minha-assinatura.pacotes">
          {dados.pacotes.map((p) => <CartaoPacote key={p.id} p={p} />)}
        </div>
      </section>

      {aberta && <Detalhe linha={aberta} dados={dados} onFechar={() => setAberta(null)} />}
    </>
  );
}

function CartaoSituacao({ c, selo, children }: { c: Cartao; selo: string; children: ReactNode }) {
  return (
    <Card>
      <CardContent className="flex flex-col gap-1 p-4">
        <div className="flex items-center justify-between gap-2">
          <h3 className="truncate font-medium">{c.pacote}</h3>
          <Badge variant={tomDaAssinatura(selo)}>{selo}</Badge>
        </div>
        <p className="text-xs text-muted-foreground tabular-nums">{children}</p>
      </CardContent>
    </Card>
  );
}

function CartaoPacote({ p }: { p: Pacote }) {
  const href = p.link ? p.link.url : `/subscription/${p.id}/pay`;
  const rotulo = p.link ? p.link.texto : p.gratis ? 'Assinar' : 'Pagar e assinar';
  return (
    <Card>
      <CardContent className="flex h-full flex-col gap-3 p-4">
        <h3 className="font-medium">{p.nome}</h3>
        <div className="flex items-baseline gap-1.5">
          <span className="text-xl font-semibold tabular-nums">{p.gratis ? 'Grátis' : p.preco}</span>
          <span className="text-xs text-muted-foreground">{p.gratis ? 'por' : '/'} {ciclo(p.intervalo_count, p.intervalo)}</span>
        </div>
        <ul className="flex flex-col gap-0.5 text-xs text-muted-foreground">
          <li>{limite(p.locais, 'local', 'locais', 'locais ilimitados')}</li>
          <li>{limite(p.usuarios, 'usuário', 'usuários', 'usuários ilimitados')}</li>
          <li>{limite(p.produtos, 'produto', 'produtos', 'produtos ilimitados')}</li>
          <li>{limite(p.faturas, 'fatura', 'faturas', 'faturas ilimitadas')}</li>
          {p.trial_dias > 0 && <li>{plural(p.trial_dias, 'dia de teste', 'dias de teste')}</li>}
        </ul>
        {p.modulos.length > 0 && (
          <div className="flex flex-wrap gap-1">
            {p.modulos.map((m) => <Badge key={m} variant="secondary" className="font-normal">{m}</Badge>)}
          </div>
        )}
        <footer className="mt-auto flex flex-col gap-2 border-t pt-3">
          {p.descricao && <p className="text-[11px] text-muted-foreground">{p.descricao}</p>}
          <Button asChild variant="outline" size="sm"><a href={href}>{rotulo}</a></Button>
        </footer>
      </CardContent>
    </Card>
  );
}

function Detalhe({ linha: l, dados, onFechar }: { linha: Linha; dados: Dados; onFechar: () => void }) {
  const e = dados.emissor;
  const campos: [string, string | null][] = [
    ['Valor', l.preco], ['Início', l.inicio], ['Fim do teste', l.trial_fim], ['Fim', l.fim],
    ['Pago via', l.pago_via], ['Transação', l.transacao], ['Criado em', l.criado_em], ['Criado por', l.criado_por],
  ];
  return (
    <Sheet open onOpenChange={(a) => !a && onFechar()}>
      <SheetContent className="w-full sm:max-w-md" data-contract="superadmin.minha-assinatura.detalhe">
        <SheetHeader>
          <SheetTitle>{l.pacote}</SheetTitle>
          <SheetDescription>{l.status}</SheetDescription>
        </SheetHeader>
        <div className="flex flex-col gap-4 px-4 text-sm">
          <div className="grid grid-cols-2 gap-3 text-xs">
            <div>
              <p className="font-medium">{e.invoice_business_name}</p>
              <p className="text-muted-foreground">{e.email}</p>
              <p className="text-muted-foreground">{[e.invoice_business_landmark, e.invoice_business_city, e.invoice_business_zip, e.invoice_business_state, e.invoice_business_country].filter(Boolean).join(', ')}</p>
            </div>
            <div>
              <p className="font-medium">{dados.negocio.nome}</p>
              {dados.negocio.impostos.map((t) => <p key={t.rotulo} className="text-muted-foreground">{t.rotulo}: {t.numero}</p>)}
            </div>
          </div>
          <dl className="grid grid-cols-2 gap-x-3 gap-y-2">
            {campos.map(([k, v]) => (
              <div key={k}>
                <dt className="text-xs text-muted-foreground">{k}</dt>
                <dd className="tabular-nums">{v || '—'}</dd>
              </div>
            ))}
          </dl>
        </div>
        <SheetFooter>
          <Button variant="outline" onClick={() => window.print()}>Imprimir</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

MinhaAssinaturaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default MinhaAssinaturaIndex;
