// @memcofre tela=/financeiro/contas-bancarias module=Financeiro

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Settings, Plus, AlertTriangle, CheckCircle2, MinusCircle, MoreVertical } from 'lucide-react';
import { toast } from 'sonner';
import { ConfigurarBoletoSheet } from './components/ConfigurarBoletoSheet';
import { ContaSheet, type ContaCadastro, type TipoConta } from './components/ContaSheet';
import { TiposContaSheet } from './components/TiposContaSheet';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader } from '@/Components/PageHeader';

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
  /** Texto já formatado pelo num_f do servidor — a tela não formata nem soma saldo. */
  saldo_texto: string;
  encerrada: boolean;
}

interface Props {
  accounts: Account[];
  bancos_suportados: string[];
  situacao: 'ativa' | 'encerrada';
  tipos_conta: TipoConta[];
}

function StatusBadge({ account }: { account: Account }) {
  if (!account.complemento_id) {
    return (
      <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-warning-soft text-warning-fg">
        <AlertTriangle className="h-3 w-3" /> Faltam dados
      </span>
    );
  }
  if (!account.ativo_para_boleto) {
    return (
      <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-muted text-muted-foreground">
        <MinusCircle className="h-3 w-3" /> Inativo
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-success-soft text-success-fg">
      <CheckCircle2 className="h-3 w-3" /> Ativo{account.carteira ? ` · Cart. ${account.carteira}` : ''}
    </span>
  );
}

function Index({ accounts, bancos_suportados, situacao, tipos_conta }: Props) {
  const [boleto, setBoleto] = useState<Account | null>(null);
  // undefined = fechado · null = nova conta · conta = editar.
  const [cadastro, setCadastro] = useState<Account | null | undefined>(undefined);
  const [tipos, setTipos] = useState(false);

  const trocarSituacao = (s: 'ativa' | 'encerrada') =>
    router.get('/financeiro/contas-bancarias', s === 'ativa' ? {} : { situacao: s }, { preserveScroll: true, preserveState: true });

  const mudarSituacaoDaConta = (a: Account) => {
    const acao = a.encerrada ? 'reativar' : 'encerrar';
    if (!a.encerrada && !window.confirm(`Encerrar "${a.name}"? Ela sai das contas ativas; o movimento continua guardado.`)) return;
    router.post(`/financeiro/contas-bancarias/${a.id}/${acao}`, {}, {
      preserveScroll: true,
      onSuccess: () => toast.success(a.encerrada ? 'Conta reativada.' : 'Conta encerrada.'),
    });
  };

  return (
    <>
      {/* PT-04/US-COPI-146: fora da ilha .fin-cowork — layout via tokens do tema global (.cockpit) */}
      <div className="p-6 max-w-6xl mx-auto space-y-6">
        <PageHeader
          title="Contas Bancárias"
          suffix=" · Cadastro, saldo e boleto"
          subtitle={
            <>
              Cadastro, saldo e dados de boleto de cada conta. Credenciais API (Inter, Asaas, C6) ficam em{' '}
              <a href="/settings/payment-gateways" className="underline underline-offset-2">Gateways de Pagamento</a>.
            </>
          }
        >
          <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
            <FinanceiroSubNav
              active="contas-bancarias"
              hidePrimary
              extraOverflowItems={[
                { key: 'tipos-conta', label: 'Tipos de conta', icon: <Settings size={13} />, onClick: () => setTipos(true), title: 'Tipos e subtipos de conta' },
                { key: 'gateways', label: 'Gateways', icon: <Settings size={13} />, onClick: () => { window.location.href = '/settings/payment-gateways'; }, title: 'Credenciais API gateways de pagamento' },
              ]}
            />
            <Button variant="default" size="sm" onClick={() => setCadastro(null)}>
              <Plus size={13} /> Nova conta
            </Button>
          </div>
        </PageHeader>

        <div className="rounded-md border">
          <div className="flex flex-wrap items-center gap-3 border-b px-3 py-2">
            <div role="group" aria-label="Situação da conta" data-contract="filtro-situacao" className="inline-flex overflow-hidden rounded-md border">
              {(['ativa', 'encerrada'] as const).map((s) => (
                <button
                  key={s}
                  type="button"
                  aria-pressed={situacao === s}
                  onClick={() => trocarSituacao(s)}
                  className={`h-8 px-3 text-sm ${situacao === s ? 'bg-accent text-accent-foreground font-medium' : 'bg-background text-muted-foreground'}`}
                >
                  {s === 'ativa' ? 'Ativa' : 'Encerrada'}
                </button>
              ))}
            </div>
            <span className="ml-auto text-sm text-muted-foreground">
              {accounts.length} conta(s) {situacao === 'ativa' ? 'ativa(s)' : 'encerrada(s)'}
            </span>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm" data-contract="contas-table">
              <thead className="bg-muted/50">
                <tr className="text-left">
                  <th className="px-4 py-2 font-medium">Conta</th>
                  <th className="px-4 py-2 font-medium">Tipo · subtipo</th>
                  <th className="px-4 py-2 font-medium">Banco</th>
                  <th className="px-4 py-2 font-medium">Boleto</th>
                  <th className="px-4 py-2 font-medium text-right">Saldo</th>
                  <th className="px-4 py-2 font-medium text-right"><span className="sr-only">Ações</span></th>
                </tr>
              </thead>
              <tbody>
                {accounts.length === 0 && (
                  <tr>
                    <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                      {situacao === 'ativa' ? 'Nenhuma conta ativa. Use "Nova conta".' : 'Nenhuma conta encerrada.'}
                    </td>
                  </tr>
                )}
                {accounts.map((a) => (
                  <tr key={a.id} className="border-t hover:bg-muted/30">
                    <td className="px-4 py-3">
                      <div className="font-medium">{a.name}</div>
                      <div className="text-xs text-muted-foreground">
                        {a.agencia
                          ? `Ag ${a.agencia}${a.agencia_dv ? '-' + a.agencia_dv : ''} · Cc ${a.account_number ?? ''}${a.conta_dv ? '-' + a.conta_dv : ''}`
                          : a.account_number ? `Cc ${a.account_number}` : '—'}
                      </div>
                    </td>
                    <td className="px-4 py-3">{a.tipo ? `${a.tipo}${a.subtipo ? ' · ' + a.subtipo : ''}` : <span className="text-muted-foreground">—</span>}</td>
                    <td className="px-4 py-3">
                      {a.banco_codigo ? <span className="font-mono">{a.banco_codigo}</span> : <span className="text-muted-foreground">—</span>}
                    </td>
                    <td className="px-4 py-3"><StatusBadge account={a} /></td>
                    <td className="px-4 py-3 text-right font-mono tabular-nums">{a.saldo_texto}</td>
                    <td className="px-4 py-3 text-right">
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button size="icon" variant="ghost" aria-label={`Ações da conta ${a.name}`}>
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onSelect={() => setCadastro(a)}>Editar conta</DropdownMenuItem>
                          <DropdownMenuItem onSelect={() => setBoleto(a)}>{a.complemento_id ? 'Editar boleto' : 'Configurar boleto'}</DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem onSelect={() => mudarSituacaoDaConta(a)} className={a.encerrada ? '' : 'text-destructive'}>
                            {a.encerrada ? 'Reativar conta' : 'Encerrar conta'}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <Card>
          <CardContent className="pt-6 text-sm text-muted-foreground space-y-2">
            <p>
              <strong>Bancos suportados ({bancos_suportados.length}):</strong>{' '}
              <span className="font-mono text-xs">{bancos_suportados.join(', ')}</span>
            </p>
            <p>
              Para emitir boleto via Sicoob (banco da ROTA LIVRE), configure carteira <code>1</code>{' '}
              e preencha agência + conta + beneficiário (CNPJ + razão social).
            </p>
          </CardContent>
        </Card>
      </div>

      {boleto && (
        <ConfigurarBoletoSheet account={boleto} bancosSuportados={bancos_suportados} onClose={() => setBoleto(null)} />
      )}
      {tipos && <TiposContaSheet tiposConta={tipos_conta} onClose={() => setTipos(false)} />}
      {cadastro !== undefined && (
        <ContaSheet conta={cadastro} tiposConta={tipos_conta} onClose={() => setCadastro(undefined)} />
      )}
    </>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Contas Bancárias" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Contas Bancárias' }]}>
    {page}
  </AppShellV2>
);
export default Index;
