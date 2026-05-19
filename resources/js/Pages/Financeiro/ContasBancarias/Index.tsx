// @memcofre tela=/financeiro/contas-bancarias module=Financeiro

import AppShellV2 from '@/Layouts/AppShellV2';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Settings, Plus, AlertTriangle, CheckCircle2, MinusCircle } from 'lucide-react';
import { ConfigurarBoletoSheet } from './components/ConfigurarBoletoSheet';

interface Account {
  id: number;
  name: string;
  account_number: string;
  account_type_id: number | null;
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
}

interface Props {
  accounts: Account[];
  bancos_suportados: string[];
}

function StatusBadge({ account }: { account: Account }) {
  if (!account.complemento_id) {
    return (
      <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-200">
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
    <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200">
      <CheckCircle2 className="h-3 w-3" /> Ativo{account.carteira ? ` · Cart. ${account.carteira}` : ''}
    </span>
  );
}

function Index({ accounts, bancos_suportados }: Props) {
  const [editing, setEditing] = useState<Account | null>(null);

  return (
    <>
      {/* Onda 12.8 (2026-05-19) — header canon paridade Unificado */}
      <div className="fin-curadoria vendas-aplus p-6 max-w-6xl mx-auto space-y-6">
        <header className="os-page-h fin-page-h">
          <div className="os-page-h-l fin-page-h-l">
            <h1>Contas Bancárias <span className="fin-hero-title-sub">· Dados de emissão de boleto</span></h1>
            <p>
              Banco, agência, beneficiário. Cadastro principal continua em "Contas de pagamento" do POS;
              credenciais API (Inter, Asaas, C6) ficam em <a href="/settings/payment-gateways" className="underline underline-offset-2">Gateways de Pagamento</a>.
            </p>
          </div>
          <div className="os-page-h-r fin-page-h-r">
            <a href="/settings/payment-gateways" className="os-btn ghost">Gateways</a>
            <a href="/account/account/create" className="os-btn primary">
              <Plus size={13} /> Nova conta no POS
            </a>
          </div>
        </header>

        <div className="rounded-md border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">Conta</th>
                <th className="px-4 py-2 font-medium">Banco</th>
                <th className="px-4 py-2 font-medium">Boleto</th>
                <th className="px-4 py-2 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              {accounts.length === 0 && (
                <tr>
                  <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">
                    Nenhuma conta cadastrada. Cadastre primeiro no POS.
                  </td>
                </tr>
              )}
              {accounts.map((a) => (
                <tr key={a.id} className="border-t hover:bg-muted/30">
                  <td className="px-4 py-3">
                    <div className="font-medium">{a.name}</div>
                    <div className="text-xs text-muted-foreground">
                      {a.agencia
                        ? `Ag ${a.agencia}${a.agencia_dv ? '-' + a.agencia_dv : ''} · Cc ${a.account_number}${a.conta_dv ? '-' + a.conta_dv : ''}`
                        : `Cc ${a.account_number}`}
                    </div>
                  </td>
                  <td className="px-4 py-3">
                    {a.banco_codigo ? (
                      <span className="font-mono">{a.banco_codigo}</span>
                    ) : (
                      <span className="text-muted-foreground">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <StatusBadge account={a} />
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button size="sm" variant="ghost" onClick={() => setEditing(a)}>
                      <Settings className="h-4 w-4 mr-1" />
                      {a.complemento_id ? 'Editar' : 'Configurar'}
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
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

      {editing && (
        <ConfigurarBoletoSheet
          account={editing}
          bancosSuportados={bancos_suportados}
          onClose={() => setEditing(null)}
        />
      )}
    </>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Contas Bancárias · Boleto" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Contas Bancárias' }]}>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);
export default Index;
