// @memcofre
//   tela: /subscription/{id}/pay
//   module: Superadmin
//   stories: US-SUPER-003 · protótipo superadmin-pagar-page.jsx (27/10/2026)
//   permissao: superadmin.access_package_subscriptions
//
// O NEGÓCIO pagando um pacote. Responde: "é este o pacote, quanto custa e como eu pago?".
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-pagar.md
//
// Decisão [W] 2026-10-27: só Pix automático e pagamento offline. O preço chega como TEXTO pronto
// do servidor (`moedaComoBlade`) e o POST leva só o `gateway` — o valor cobrado é calculado no
// `confirm()`, igual à Blade. Esta tela não formata nem calcula dinheiro.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { plural } from '../_components/assinatura';

interface Pacote {
  id: number; nome: string; preco: string; intervalo: string; intervalo_count: number;
  trial_dias: number; locais: number; usuarios: number; produtos: number; faturas: number;
}
interface Forma { gateway: string; tipo: 'pix_automatico' | 'offline' }
interface Pagamento {
  pacote: Pacote; formas: Forma[]; offline_detalhes: string; confirmar_url: string; voltar_url: string;
}

const INTERVALO: Record<string, [string, string]> = { days: ['dia', 'dias'], months: ['mês', 'meses'], years: ['ano', 'anos'] };
const ciclo = (n: number, i: string) => (n === 1 ? INTERVALO[i]?.[0] ?? i : `${n} ${INTERVALO[i]?.[1] ?? i}`);
/** `0` = ilimitado (COMMENT da coluna no UltimatePOS), nunca "zero permitido". */
const limite = (n: number, s: string, p: string) => (n === 0 ? `${p} ilimitados` : plural(n, s, p));

function PagarIndex({ pagamento }: { pagamento: Pagamento }) {
  const { pacote, formas } = pagamento;
  const [enviando, setEnviando] = useState(false);

  const pagar = (gateway: string) => {
    if (enviando) return;
    setEnviando(true);
    // Só o gateway: preço e negócio são do servidor. O `confirm()` redireciona para "Minha assinatura".
    router.post(pagamento.confirmar_url, { gateway }, { onError: () => setEnviando(false) });
  };

  return (
    <div className="pb-8">
      <PageHeader title="Pagar assinatura" subtitle="Confira o pacote e escolha como pagar" />
      <div className="px-6 pt-2">
        <a className="text-xs text-muted-foreground hover:text-foreground" href={pagamento.voltar_url}>← Minha assinatura</a>
      </div>

      <div className="flex flex-wrap items-start gap-4 px-6 pt-4">
        <Card className="max-w-[360px] flex-[1_1_280px]" aria-label="Pacote" data-contract="superadmin.pagar.pacote">
          <CardContent className="flex flex-col gap-2.5 p-4">
            <h2 className="text-sm font-semibold">{pacote.nome}</h2>
            <div className="flex items-baseline gap-1.5">
              <span className="text-[22px] font-semibold tabular-nums">{pacote.preco}</span>
              <span className="text-xs text-muted-foreground">/ {ciclo(pacote.intervalo_count, pacote.intervalo)}</span>
            </div>
            <ul className="grid list-disc gap-0.5 pl-4 text-xs text-muted-foreground">
              <li>{limite(pacote.locais, 'local', 'locais')}</li>
              <li>{limite(pacote.usuarios, 'usuário', 'usuários')}</li>
              <li>{limite(pacote.produtos, 'produto', 'produtos')}</li>
              <li>{pacote.faturas === 0 ? 'faturas ilimitadas' : plural(pacote.faturas, 'fatura', 'faturas')}</li>
              {pacote.trial_dias > 0 && <li>{plural(pacote.trial_dias, 'dia de teste', 'dias de teste')}</li>}
            </ul>
            <p className="border-t pt-2.5 text-[11px] text-muted-foreground">
              O valor cobrado é o do pacote, lido de novo no servidor.
            </p>
          </CardContent>
        </Card>

        <section className="grid min-w-0 flex-[999_1_420px] gap-3" aria-label="Formas de pagamento" data-contract="superadmin.pagar.formas">
          <p className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">Como pagar</p>

          {formas.length === 0 && (
            <Card><CardContent className="p-0">
              <EmptyState title="Nenhuma forma de pagamento disponível" description="Fale com o suporte para assinar este pacote." />
            </CardContent></Card>
          )}

          {formas.map((f) => f.tipo === 'pix_automatico' ? (
            <Card key={f.gateway}>
              <CardContent className="flex flex-col gap-2.5 p-4">
                <div className="flex items-center gap-2">
                  <h2 className="text-sm font-semibold">Pix automático</h2>
                  <Badge variant="success">recomendado</Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                  Você autoriza uma vez no app do seu banco e a mensalidade é debitada todo mês. Depois de confirmar, tem 7 dias para autorizar.
                </p>
                <Button className="self-start" disabled={enviando} onClick={() => pagar(f.gateway)}>
                  Pagar com Pix automático
                </Button>
              </CardContent>
            </Card>
          ) : (
            <Card key={f.gateway}>
              <CardContent className="flex flex-col gap-2.5 p-4">
                <h2 className="text-sm font-semibold">Pagamento offline</h2>
                <p className="text-sm text-muted-foreground">
                  Pague por transferência ou depósito e avise aqui. A assinatura fica aguardando até o pagamento ser aprovado.
                </p>
                {pagamento.offline_detalhes.trim() !== '' && (
                  <pre className="m-0 whitespace-pre-wrap rounded-md bg-muted px-3 py-2.5 font-sans text-xs">{pagamento.offline_detalhes}</pre>
                )}
                <Button variant="outline" className="self-start" disabled={enviando} onClick={() => pagar(f.gateway)}>
                  Avisar pagamento offline
                </Button>
              </CardContent>
            </Card>
          ))}
        </section>
      </div>
    </div>
  );
}

PagarIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PagarIndex;
