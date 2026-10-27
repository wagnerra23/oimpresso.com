// @memcofre
//   tela: /subscription/{id}/pay · /subscription/{id}/register-pay
//   module: Superadmin
//   stories: US-SUPER-003 · decisão [W] 2026-10-27 (só Pix automático e offline)
//   permissao: superadmin.access_package_subscriptions
//
// O NEGÓCIO pagando um pacote. Responde: "quanto custa e como eu pago?".
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Protótipo: https://claude.ai/artifact/N96PHZscAcmPfWYeTeNJjJ
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-pagar.md
//
// Valor: o preço chega como TEXTO pronto do servidor (`moedaComoBlade`). Esta tela não formata nem
// calcula dinheiro (Tier 0, memory/proibicoes.md §CÁLCULO DE VALOR). O valor cobrado é lido de novo
// pelo servidor em `confirm()`; daqui só sai a forma escolhida.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { plural } from '../_components/assinatura';

interface Pacote {
  id: number; nome: string; descricao: string; preco: string; intervalo: string; intervalo_count: number;
  trial_dias: number; locais: number; usuarios: number; produtos: number; faturas: number;
}
type Chave = 'paymentgateway_pix_automatico' | 'offline';
interface Dados { pacote: Pacote; formas: { chave: Chave }[]; dados_offline: string | null }

const INTERVALO: Record<string, [string, string]> = { days: ['dia', 'dias'], months: ['mês', 'meses'], years: ['ano', 'anos'] };
const ciclo = (n: number, i: string) => (n === 1 ? INTERVALO[i]?.[0] ?? i : `${n} ${INTERVALO[i]?.[1] ?? i}`);
/** `0` = ilimitado (COMMENT da coluna no UltimatePOS), nunca "zero permitido". */
const limite = (n: number, s: string, p: string, ilim: string) => (n === 0 ? ilim : plural(n, s, p));

function PagarIndex({ pagamento }: { pagamento?: Dados }) {
  return (
    <div className="pb-8">
      <PageHeader title="Pagar assinatura" subtitle="Confira o pacote e escolha como pagar" />
      <Deferred data="pagamento" fallback={<div className="px-6 pt-4"><Skeleton className="h-64 w-full" /></div>}>
        <Conteudo dados={pagamento} />
      </Deferred>
    </div>
  );
}

function Conteudo({ dados }: { dados?: Dados }) {
  const [enviando, setEnviando] = useState<Chave | null>(null);
  if (!dados) return null;
  const p = dados.pacote;

  const confirmar = (chave: Chave) =>
    router.post(`/subscription/${p.id}/confirm`, { gateway: chave }, {
      onStart: () => setEnviando(chave),
      onFinish: () => setEnviando(null),
    });

  return (
    <div className="flex flex-wrap gap-4 px-6 pt-4">
      <Card className="w-full max-w-sm" data-contract="superadmin.pagar.pacote">
        <CardContent className="flex flex-col gap-3 p-4">
          <h2 className="font-medium">{p.nome}</h2>
          <div className="flex items-baseline gap-1.5">
            <span className="text-xl font-semibold tabular-nums">{p.preco}</span>
            <span className="text-xs text-muted-foreground">/ {ciclo(p.intervalo_count, p.intervalo)}</span>
          </div>
          <ul className="flex flex-col gap-0.5 text-xs text-muted-foreground">
            <li>{limite(p.locais, 'local', 'locais', 'locais ilimitados')}</li>
            <li>{limite(p.usuarios, 'usuário', 'usuários', 'usuários ilimitados')}</li>
            <li>{limite(p.produtos, 'produto', 'produtos', 'produtos ilimitados')}</li>
            <li>{limite(p.faturas, 'fatura', 'faturas', 'faturas ilimitadas')}</li>
            {p.trial_dias > 0 && <li>{plural(p.trial_dias, 'dia de teste', 'dias de teste')}</li>}
          </ul>
          {p.descricao && <p className="border-t pt-3 text-[11px] text-muted-foreground">{p.descricao}</p>}
        </CardContent>
      </Card>

      <section className="flex min-w-0 flex-[999_1_420px] flex-col gap-3" data-contract="superadmin.pagar.formas">
        <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Como pagar</h2>
        {dados.formas.length === 0 && (
          <Card>
            <CardContent className="p-0">
              <EmptyState title="Nenhuma forma de pagamento ligada" description="Fale com o suporte para assinar este pacote." />
            </CardContent>
          </Card>
        )}
        {dados.formas.map(({ chave }) =>
          chave === 'paymentgateway_pix_automatico' ? (
            <Forma key={chave} titulo="Pix automático" selo="recomendado" acao="Pagar com Pix automático"
              primaria enviando={enviando} chave={chave} onConfirmar={confirmar}>
              Você autoriza uma vez no app do seu banco e a mensalidade é debitada todo mês. Depois de
              confirmar, tem 7 dias para autorizar.
            </Forma>
          ) : (
            <Forma key={chave} titulo="Pagamento offline" acao="Avisar pagamento offline"
              enviando={enviando} chave={chave} onConfirmar={confirmar}>
              Pague por transferência ou depósito e avise aqui. A assinatura fica aguardando até o
              pagamento ser aprovado.
              {dados.dados_offline && (
                <span className="mt-2 block whitespace-pre-wrap rounded-md bg-muted px-3 py-2 text-xs text-foreground">
                  {dados.dados_offline}
                </span>
              )}
            </Forma>
          ),
        )}
      </section>
    </div>
  );
}

function Forma({ titulo, selo, acao, primaria, chave, enviando, onConfirmar, children }: {
  titulo: string; selo?: string; acao: string; primaria?: boolean; chave: Chave;
  enviando: Chave | null; onConfirmar: (c: Chave) => void; children: ReactNode;
}) {
  return (
    <Card data-contract={`superadmin.pagar.forma.${chave}`}>
      <CardContent className="flex flex-col gap-2 p-4">
        <div className="flex items-center gap-2">
          <h3 className="text-sm font-semibold">{titulo}</h3>
          {selo && <Badge variant="secondary" className="font-normal">{selo}</Badge>}
        </div>
        <p className="text-sm text-muted-foreground">{children}</p>
        <div>
          <Button variant={primaria ? 'default' : 'outline'} disabled={enviando !== null} onClick={() => onConfirmar(chave)}>
            {enviando === chave ? 'Enviando…' : acao}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

PagarIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PagarIndex;
