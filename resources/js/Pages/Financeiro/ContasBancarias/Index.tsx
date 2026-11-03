// @memcofre tela=/financeiro/contas-bancarias module=Financeiro
//
// Tela única de contas (decisão [W] 2026-10-27, opção A): cadastro, saldo e dados de boleto de cada conta.
// Fonte de forma: prototipo-ui/cowork/Wagner/financeiro-contas-bancarias-page.jsx.
// O saldo chega formatado do servidor (`saldo_texto`, mesmo SQL e mesmo num_f do núcleo): a tela não soma
// nem formata valor — total e KPIs ficam para decisão [W].

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { MoreVertical, Plus, Settings } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Segmented } from '@/Components/ui/segmented';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { ConfigurarBoletoSheet } from './components/ConfigurarBoletoSheet';
import { ContaSheet, type ContaCadastro, type TipoConta } from './components/ContaSheet';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader } from '@/Components/PageHeader';
import { Inline } from '@/Components/layout';

interface Account extends ContaCadastro {
  account_number: string;
  complemento_id: number | null;
  banco_codigo: string | null;
  agencia: string | null;
  agencia_dv: string | null;
  conta_dv: string | null;
  carteira: string | null;
  convenio: string | null;
  codigo_cedente: string | null;
  beneficiario_documento: string | null;
  beneficiario_razao_social: string | null;
  beneficiario_logradouro: string | null;
  beneficiario_bairro: string | null;
  beneficiario_cidade: string | null;
  beneficiario_uf: string | null;
  beneficiario_cep: string | null;
  ativo_para_boleto: boolean | null;
  tipo_conta: string | null;
  metadata: Record<string, unknown> | null;
  tipo: string | null;
  subtipo: string | null;
  saldo_texto: string;
  encerrada: boolean;
}

interface Props {
  accounts: Account[];
  bancos_suportados: string[];
  situacao: 'ativa' | 'encerrada';
  tipos_conta: TipoConta[];
}

function StatusBoleto({ account }: { account: Account }) {
  if (!account.complemento_id) {
    return <span className="inline-block text-xs px-2 py-0.5 rounded bg-warning-soft text-warning-fg">Faltam dados</span>;
  }
  if (!account.ativo_para_boleto) {
    return <span className="inline-block text-xs px-2 py-0.5 rounded bg-muted text-muted-foreground">Sem boleto</span>;
  }
  return (
    <span className="inline-block text-xs px-2 py-0.5 rounded bg-success-soft text-success-fg">
      Ativo{account.carteira ? ` · Cart. ${account.carteira}` : ''}
    </span>
  );
}

function agenciaConta(a: Account): string {
  if (a.agencia) {
    return `Ag ${a.agencia}${a.agencia_dv ? '-' + a.agencia_dv : ''} · Cc ${a.account_number}${a.conta_dv ? '-' + a.conta_dv : ''}`;
  }
  return a.account_number || '—';
}

function Index({ accounts, bancos_suportados, situacao, tipos_conta }: Props) {
  const [boleto, setBoleto] = useState<Account | null>(null);
  // undefined = fechado · null = Nova conta · Account = Editar conta.
  const [cadastro, setCadastro] = useState<Account | null | undefined>(undefined);
  const [encerrar, setEncerrar] = useState<Account | null>(null);

  const trocarSituacao = (v: string) =>
    router.get('/financeiro/contas-bancarias', v === 'encerrada' ? { situacao: 'encerrada' } : {}, { preserveState: true, preserveScroll: true });

  const mudarSituacao = (a: Account, acao: 'encerrar' | 'reativar') =>
    router.post(`/financeiro/contas-bancarias/${a.id}/${acao}`, {}, {
      preserveScroll: true,
      onSuccess: () => toast.success(acao === 'encerrar' ? 'Conta encerrada.' : 'Conta reativada.'),
      onFinish: () => setEncerrar(null),
    });

  const nomeSituacao = situacao === 'encerrada' ? 'encerrada' : 'ativa';

  return (
    <>
      <div className="p-6 max-w-6xl mx-auto space-y-4">
        <PageHeader
          title="Contas bancárias"
          subtitle={
            <>
              Cadastro, saldo e dados de boleto de cada conta. Credenciais de gateway ficam em{' '}
              <a href="/settings/payment-gateways" className="underline underline-offset-2">Gateways de Pagamento</a>.
            </>
          }
        >
          <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
            <FinanceiroSubNav
              active="contas-bancarias"
              hidePrimary
              extraOverflowItems={[
                { key: 'gateways', label: 'Gateways', icon: <Settings size={13} />, onClick: () => { window.location.href = '/settings/payment-gateways'; }, title: 'Credenciais API gateways de pagamento' },
              ]}
            />
            <Button variant="default" size="sm" onClick={() => setCadastro(null)}>
              <Plus size={13} /> Nova conta
            </Button>
          </div>
        </PageHeader>

        <div className="rounded-md border bg-card">
          <Inline wrap gap={3} className="px-3 py-2.5 border-b">
            <Segmented
              aria-label="Situação da conta"
              value={situacao}
              onValueChange={trocarSituacao}
              options={[{ value: 'ativa', label: 'Ativa' }, { value: 'encerrada', label: 'Encerrada' }]}
            />
            <span className="ml-auto text-sm text-muted-foreground">
              {accounts.length} {accounts.length === 1 ? `conta ${nomeSituacao}` : `contas ${nomeSituacao}s`}
            </span>
          </Inline>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted-foreground">
                  <th className="px-3 py-2 font-medium">Conta</th>
                  <th className="px-3 py-2 font-medium">Tipo · subtipo</th>
                  <th className="px-3 py-2 font-medium">Banco</th>
                  <th className="px-3 py-2 font-medium">Boleto</th>
                  <th className="px-3 py-2 font-medium text-right">Saldo</th>
                  <th className="px-3 py-2"><span className="sr-only">Ações</span></th>
                </tr>
              </thead>
              <tbody>
                {accounts.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-3 py-8 text-center text-muted-foreground">
                      {situacao === 'encerrada' ? 'Nenhuma conta encerrada.' : 'Nenhuma conta ativa. Use "Nova conta".'}
                    </td>
                  </tr>
                )}
                {accounts.map((a) => (
                  <tr key={a.id} className="border-t hover:bg-muted/30">
                    <td className="px-3 py-2.5">
                      <div className="font-medium">{a.name}</div>
                      <div className="text-xs text-muted-foreground font-mono">{agenciaConta(a)}</div>
                    </td>
                    <td className="px-3 py-2.5">{a.tipo ? (a.subtipo ? `${a.tipo} · ${a.subtipo}` : a.tipo) : <span className="text-muted-foreground">—</span>}</td>
                    <td className="px-3 py-2.5">{a.banco_codigo ? <span className="font-mono">{a.banco_codigo}</span> : <span className="text-muted-foreground">—</span>}</td>
                    <td className="px-3 py-2.5"><StatusBoleto account={a} /></td>
                    <td className="px-3 py-2.5 text-right font-mono tabular-nums whitespace-nowrap">{a.saldo_texto}</td>
                    <td className="px-3 py-2.5 text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" aria-label={`Ações da conta ${a.name}`}>
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onSelect={() => setCadastro(a)}>Editar conta</DropdownMenuItem>
                          <DropdownMenuItem onSelect={() => setBoleto(a)}>{a.complemento_id ? 'Editar boleto' : 'Configurar boleto'}</DropdownMenuItem>
                          <DropdownMenuSeparator />
                          {a.encerrada ? (
                            <DropdownMenuItem onSelect={() => mudarSituacao(a, 'reativar')}>Reativar conta</DropdownMenuItem>
                          ) : (
                            <DropdownMenuItem className="text-destructive" onSelect={() => setEncerrar(a)}>Encerrar conta</DropdownMenuItem>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="text-xs text-muted-foreground px-3 py-2.5 border-t m-0">
            O saldo vem do mesmo cálculo das contas de pagamento (crédito − débito). Bancos com boleto: {bancos_suportados.join(', ')}.
          </p>
        </div>
      </div>

      {boleto && (
        <ConfigurarBoletoSheet account={boleto} bancosSuportados={bancos_suportados} onClose={() => setBoleto(null)} />
      )}

      {cadastro !== undefined && (
        <ContaSheet conta={cadastro} tipos={tipos_conta} onClose={() => setCadastro(undefined)} />
      )}

      <AlertDialog open={encerrar !== null} onOpenChange={(o) => { if (!o) setEncerrar(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Encerrar a conta {encerrar?.name}?</AlertDialogTitle>
            <AlertDialogDescription>
              A conta sai da lista de ativas. Os lançamentos e o saldo ficam como estão; dá para reativar depois em "Encerrada".
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={() => encerrar && mudarSituacao(encerrar, 'encerrar')}>Encerrar conta</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Contas bancárias" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Contas bancárias' }]}>
    {page}
  </AppShellV2>
);
export default Index;
