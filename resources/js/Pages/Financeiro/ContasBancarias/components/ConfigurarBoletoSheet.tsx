import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Badge } from '@/Components/ui/badge';
import { toast } from 'sonner';
import { Building2, KeyRound, Landmark, Wifi } from 'lucide-react';

interface Account {
  id: number;
  name: string;
  account_number: string;
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
  gateway_banco: string | null;
  gateway_ambiente: string | null;
  gateway_ativo: boolean | null;
  gateway_client_id: string | null;
}

interface Props {
  account: Account;
  bancosSuportados: string[];
  onClose: () => void;
}

const BANCO_NOMES: Record<string, string> = {
  '001': 'Banco do Brasil',
  '004': 'Banco do Nordeste',
  '033': 'Santander',
  '041': 'Banrisul',
  '077': 'Banco Inter',
  '085': 'Ailos',
  '104': 'Caixa Econômica',
  '133': 'Cresol',
  '136': 'Unicred',
  '208': 'BTG Pactual',
  '224': 'Banco Fibra',
  '237': 'Bradesco',
  '274': 'Asaas (virtual PJ)',
  '336': 'C6 Bank',
  '341': 'Itaú',
  '362': 'HSBC',
  '405': 'Delbank',
  '633': 'Banco Rendimento',
  '643': 'Banco Pine',
  '712': 'Ourinvest',
  '748': 'Sicredi',
  '756': 'Sicoob (Bancoob)',
};

const GATEWAY_BANKS = ['077', '274'];
const GATEWAY_ONLY  = ['274']; // sem agência/carteira/CNAB

// Asterisco vermelho dedicado pra campos obrigatórios. aria-hidden pq o
// input usa aria-required (a11y via attribute, não texto do label).
const RequiredMark = () => (
  <span aria-hidden="true" className="text-destructive ml-0.5">*</span>
);

// Cabeçalho de seção do form — ícone lucide + título + eyebrow descritivo.
// Alinha com PageHeader.tsx do Cockpit V2 (tracking-tight, font-semibold).
function SectionHeader({
  icon: Icon,
  title,
  eyebrow,
  action,
}: {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  eyebrow?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex items-center gap-2">
      <Icon className="h-4 w-4 text-muted-foreground" />
      <h3 className="text-sm font-semibold tracking-tight">{title}</h3>
      {eyebrow && <span className="text-xs text-muted-foreground">· {eyebrow}</span>}
      {action && <div className="ml-auto">{action}</div>}
    </div>
  );
}

export function ConfigurarBoletoSheet({ account, bancosSuportados, onClose }: Props) {
  const form = useForm({
    banco_codigo: account.banco_codigo ?? '',
    agencia: account.agencia ?? '',
    agencia_dv: account.agencia_dv ?? '',
    conta_dv: account.conta_dv ?? '',
    carteira: account.carteira ?? '',
    convenio: account.convenio ?? '',
    codigo_cedente: account.codigo_cedente ?? '',
    variacao_carteira: '',
    beneficiario_documento: account.beneficiario_documento ?? '',
    beneficiario_razao_social: account.beneficiario_razao_social ?? '',
    beneficiario_logradouro: account.beneficiario_logradouro ?? '',
    beneficiario_bairro: account.beneficiario_bairro ?? '',
    beneficiario_cidade: account.beneficiario_cidade ?? '',
    beneficiario_uf: account.beneficiario_uf ?? 'SP',
    beneficiario_cep: account.beneficiario_cep ?? '',
    ativo_para_boleto: account.ativo_para_boleto ?? true,
    metadata: {} as Record<string, string>,
    gateway_ambiente: (account.gateway_ambiente ?? 'production') as 'production' | 'sandbox',
    gateway_client_id: account.gateway_client_id ?? '',
    gateway_client_secret: '',
    gateway_certificado_crt: '',
    gateway_certificado_key: '',
    gateway_api_key: '',
  });

  const isGatewayBank = GATEWAY_BANKS.includes(form.data.banco_codigo);
  const isGatewayOnly = GATEWAY_ONLY.includes(form.data.banco_codigo);
  const isInter = form.data.banco_codigo === '077';
  const isAsaas = form.data.banco_codigo === '274';
  const hasExistingCredential = Boolean(account.gateway_banco);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(`/financeiro/contas-bancarias/${account.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Configuração salva');
        onClose();
      },
      onError: () => toast.error('Verifique os campos destacados'),
    });
  };

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader className="pb-5 border-b">
          <SheetTitle className="text-xl font-semibold tracking-tight">
            Configurar boleto
          </SheetTitle>
          <SheetDescription className="text-sm text-muted-foreground">
            {account.name} · Conta {account.account_number}
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={submit} className="space-y-6 mt-5 pb-6">
          {/* Conta bancária */}
          <div className="space-y-4">
            <SectionHeader icon={Landmark} title="Conta bancária" eyebrow="dados pra emissão" />

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="banco_codigo">
                  Banco<RequiredMark />
                </Label>
                <Select
                  value={form.data.banco_codigo}
                  onValueChange={(v) => form.setData('banco_codigo', v)}
                >
                  <SelectTrigger id="banco_codigo" aria-required="true">
                    <SelectValue placeholder="Selecione" />
                  </SelectTrigger>
                  <SelectContent>
                    {bancosSuportados.map((codigo) => (
                      <SelectItem key={codigo} value={codigo}>
                        {codigo} — {BANCO_NOMES[codigo] ?? codigo}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {form.errors.banco_codigo && (
                  <p className="text-[13px] text-destructive mt-1.5">{form.errors.banco_codigo}</p>
                )}
              </div>
              {!isGatewayOnly && (
                <div>
                  <Label htmlFor="carteira">
                    Carteira<RequiredMark />
                  </Label>
                  <Input
                    id="carteira"
                    aria-required="true"
                    value={form.data.carteira}
                    onChange={(e) => form.setData('carteira', e.target.value)}
                  />
                  {form.errors.carteira && (
                    <p className="text-[13px] text-destructive mt-1.5">{form.errors.carteira}</p>
                  )}
                </div>
              )}
            </div>

            {!isGatewayOnly && (
              <div className="grid grid-cols-3 gap-4">
                <div>
                  <Label htmlFor="agencia">
                    Agência<RequiredMark />
                  </Label>
                  <Input
                    id="agencia"
                    aria-required="true"
                    value={form.data.agencia}
                    onChange={(e) => form.setData('agencia', e.target.value)}
                  />
                  {form.errors.agencia && (
                    <p className="text-[13px] text-destructive mt-1.5">{form.errors.agencia}</p>
                  )}
                </div>
                <div>
                  <Label htmlFor="agencia_dv">Dígito Ag.</Label>
                  <Input
                    id="agencia_dv"
                    value={form.data.agencia_dv}
                    onChange={(e) => form.setData('agencia_dv', e.target.value)}
                    maxLength={2}
                  />
                </div>
                <div>
                  <Label htmlFor="conta_dv">Dígito Conta</Label>
                  <Input
                    id="conta_dv"
                    value={form.data.conta_dv}
                    onChange={(e) => form.setData('conta_dv', e.target.value)}
                    maxLength={2}
                  />
                </div>
              </div>
            )}

            {!isGatewayOnly && (
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="convenio">Convênio</Label>
                  <Input
                    id="convenio"
                    value={form.data.convenio}
                    onChange={(e) => form.setData('convenio', e.target.value)}
                  />
                  <p className="text-[13px] text-muted-foreground mt-1.5 leading-relaxed">
                    BB / Sicoob / Caixa pedem; outros não.
                  </p>
                </div>
                <div>
                  <Label htmlFor="codigo_cedente">Código Cedente</Label>
                  <Input
                    id="codigo_cedente"
                    value={form.data.codigo_cedente}
                    onChange={(e) => form.setData('codigo_cedente', e.target.value)}
                  />
                </div>
              </div>
            )}
          </div>

          {/* Beneficiário */}
          <div className="border-t pt-6 space-y-4">
            <SectionHeader icon={Building2} title="Beneficiário" eyebrow="PJ que emite o boleto" />

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="beneficiario_documento">
                  CNPJ<RequiredMark />
                </Label>
                <Input
                  id="beneficiario_documento"
                  aria-required="true"
                  placeholder="12.345.678/0001-99"
                  value={form.data.beneficiario_documento}
                  onChange={(e) => form.setData('beneficiario_documento', e.target.value)}
                />
                {form.errors.beneficiario_documento && (
                  <p className="text-[13px] text-destructive mt-1.5">{form.errors.beneficiario_documento}</p>
                )}
              </div>
              <div>
                <Label htmlFor="beneficiario_razao_social">
                  Razão Social<RequiredMark />
                </Label>
                <Input
                  id="beneficiario_razao_social"
                  aria-required="true"
                  value={form.data.beneficiario_razao_social}
                  onChange={(e) => form.setData('beneficiario_razao_social', e.target.value)}
                />
                {form.errors.beneficiario_razao_social && (
                  <p className="text-[13px] text-destructive mt-1.5">{form.errors.beneficiario_razao_social}</p>
                )}
              </div>
            </div>

            <div>
              <Label htmlFor="beneficiario_logradouro">Logradouro</Label>
              <Input
                id="beneficiario_logradouro"
                value={form.data.beneficiario_logradouro}
                onChange={(e) => form.setData('beneficiario_logradouro', e.target.value)}
              />
            </div>

            <div className="grid grid-cols-4 gap-4">
              <div className="col-span-2">
                <Label htmlFor="beneficiario_bairro">Bairro</Label>
                <Input
                  id="beneficiario_bairro"
                  value={form.data.beneficiario_bairro}
                  onChange={(e) => form.setData('beneficiario_bairro', e.target.value)}
                />
              </div>
              <div>
                <Label htmlFor="beneficiario_cidade">Cidade</Label>
                <Input
                  id="beneficiario_cidade"
                  value={form.data.beneficiario_cidade}
                  onChange={(e) => form.setData('beneficiario_cidade', e.target.value)}
                />
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <Label htmlFor="beneficiario_uf">UF</Label>
                  <Input
                    id="beneficiario_uf"
                    maxLength={2}
                    className="text-center uppercase"
                    value={form.data.beneficiario_uf}
                    onChange={(e) => form.setData('beneficiario_uf', e.target.value.toUpperCase())}
                  />
                </div>
                <div>
                  <Label htmlFor="beneficiario_cep">CEP</Label>
                  <Input
                    id="beneficiario_cep"
                    placeholder="00000-000"
                    value={form.data.beneficiario_cep}
                    onChange={(e) => form.setData('beneficiario_cep', e.target.value)}
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Credenciais API — gateway banks (Inter 077 / Asaas 274) */}
          {isGatewayBank && (
            <div className="border-t pt-6 space-y-4">
              <SectionHeader
                icon={KeyRound}
                title="Credenciais API"
                eyebrow={isInter ? 'OAuth2 + mTLS' : 'token API'}
                action={
                  hasExistingCredential && (
                    <Badge variant="outline" className="border-emerald-500/30 text-emerald-700 dark:text-emerald-300 gap-1">
                      <Wifi className="h-3 w-3" /> Configurado
                    </Badge>
                  )
                }
              />

              <div>
                <Label htmlFor="gateway_ambiente">Ambiente</Label>
                <Select
                  value={form.data.gateway_ambiente}
                  onValueChange={(v) => form.setData('gateway_ambiente', v as 'production' | 'sandbox')}
                >
                  <SelectTrigger id="gateway_ambiente">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="sandbox">Sandbox (testes)</SelectItem>
                    <SelectItem value="production">Produção</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-[13px] text-muted-foreground mt-1.5 leading-relaxed">
                  Comece em Sandbox pra validar o fluxo; troque pra Produção depois do primeiro boleto OK.
                </p>
              </div>

              {isInter && (
                <>
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <Label htmlFor="gateway_client_id">Client ID</Label>
                      <Input
                        id="gateway_client_id"
                        value={form.data.gateway_client_id}
                        onChange={(e) => form.setData('gateway_client_id', e.target.value)}
                        placeholder="ex: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        className="font-mono text-[13px]"
                      />
                    </div>
                    <div>
                      <Label htmlFor="gateway_client_secret">
                        Client Secret
                        {hasExistingCredential && (
                          <span className="text-muted-foreground font-normal ml-1">(deixe em branco para manter)</span>
                        )}
                      </Label>
                      <Input
                        id="gateway_client_secret"
                        type="password"
                        value={form.data.gateway_client_secret}
                        onChange={(e) => form.setData('gateway_client_secret', e.target.value)}
                        placeholder={hasExistingCredential ? '••••••••' : 'client_secret'}
                        autoComplete="new-password"
                      />
                    </div>
                  </div>

                  <div>
                    <Label htmlFor="gateway_certificado_crt">
                      Certificado mTLS (.crt — PEM)
                      {hasExistingCredential && (
                        <span className="text-muted-foreground font-normal ml-1">(deixe em branco para manter)</span>
                      )}
                    </Label>
                    <Textarea
                      id="gateway_certificado_crt"
                      rows={5}
                      value={form.data.gateway_certificado_crt}
                      onChange={(e) => form.setData('gateway_certificado_crt', e.target.value)}
                      placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"
                      className="font-mono text-[12px]"
                    />
                    <p className="text-[13px] text-muted-foreground mt-1.5 leading-relaxed">
                      Certificado emitido pelo Banco Inter pra autenticação OAuth2 mTLS.
                    </p>
                  </div>

                  <div>
                    <Label htmlFor="gateway_certificado_key">
                      Chave privada (.key — PEM)
                      {hasExistingCredential && (
                        <span className="text-muted-foreground font-normal ml-1">(deixe em branco para manter)</span>
                      )}
                    </Label>
                    <Textarea
                      id="gateway_certificado_key"
                      rows={5}
                      value={form.data.gateway_certificado_key}
                      onChange={(e) => form.setData('gateway_certificado_key', e.target.value)}
                      placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"
                      className="font-mono text-[12px]"
                    />
                  </div>
                </>
              )}

              {isAsaas && (
                <div>
                  <Label htmlFor="gateway_api_key">
                    API Key
                    {hasExistingCredential && (
                      <span className="text-muted-foreground font-normal ml-1">(deixe em branco para manter)</span>
                    )}
                  </Label>
                  <Input
                    id="gateway_api_key"
                    type="password"
                    value={form.data.gateway_api_key}
                    onChange={(e) => form.setData('gateway_api_key', e.target.value)}
                    placeholder={hasExistingCredential ? '••••••••' : '$aact_...'}
                    autoComplete="new-password"
                  />
                  <p className="text-[13px] text-muted-foreground mt-1.5 leading-relaxed">
                    Token API gerado em sua conta Asaas → Configurações → Integrações.
                  </p>
                </div>
              )}
            </div>
          )}

          {/* Toggle ativo — card destacado com hint */}
          <div className="border-t pt-6">
            <div className="rounded-lg border border-border bg-muted/30 p-4 flex items-start gap-3">
              <Switch
                id="ativo_para_boleto"
                checked={!!form.data.ativo_para_boleto}
                onCheckedChange={(c) => form.setData('ativo_para_boleto', c)}
                className="mt-0.5"
              />
              <div className="flex-1">
                <Label htmlFor="ativo_para_boleto" className="cursor-pointer text-sm font-medium">
                  Ativo para emissão de boleto
                </Label>
                <p className="text-[13px] text-muted-foreground mt-0.5 leading-relaxed">
                  Desligue pra impedir nova emissão sem precisar deletar a config.
                </p>
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t sticky bottom-0 bg-background">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Salvando…' : 'Salvar'}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
