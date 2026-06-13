// @memcofre
//   tela: /nfse/emitir
//   module: NFSe
//   stories: US-NFSE-009
//   permissao: nfse.emit

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Send, ShoppingCart, User, Calendar, DollarSign } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import PageHeader from '@/Components/shared/PageHeader';

interface Config {
  lc116_codigo_default: string | null;
  aliquota_iss: number | null;
  ambiente: string;
  cert_valido: boolean;
  cert_expira: string | null;
}

interface Venda {
  transaction_id: number;
  invoice_no: string | null;
  transaction_date: string | null;
  contact_nome: string;
  contact_cnpj: string | null;
  contact_cpf: string | null;
  contact_email: string | null;
  final_total: number;
}

interface Props {
  config: Config | null;
  venda?: Venda | null;
  flash?: { success: boolean; msg: string } | null;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);

export default function NfseEmitir({ config, venda, flash }: Props) {
  const today = new Date();
  const mesAtual = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;

  const { data, setData, post, processing, errors } = useForm({
    competencia:    venda?.transaction_date ?? mesAtual,
    tomador_nome:   venda?.contact_nome ?? '',
    tomador_cnpj:   venda?.contact_cnpj ?? '',
    tomador_cpf:    venda?.contact_cpf ?? '',
    tomador_email:  venda?.contact_email ?? '',
    descricao:      venda ? `Serviços referentes à venda ${venda.invoice_no ?? '#' + venda.transaction_id}` : '',
    lc116_codigo:   config?.lc116_codigo_default ?? '1.05',
    valor_servicos: venda ? String(venda.final_total) : '',
    aliquota_iss:   config?.aliquota_iss != null ? String(config.aliquota_iss) : '0.05',
    iss_retido:     false as boolean,
    transaction_id: venda?.transaction_id ?? null as number | null,
  });

  const valorNum  = parseFloat(data.valor_servicos) || 0;
  const aliqNum   = parseFloat(data.aliquota_iss)   || 0;
  const valorIss  = valorNum * aliqNum;
  const valorLiq  = data.iss_retido ? valorNum - valorIss : valorNum;

  const certAlerta = config && !config.cert_valido;
  const semConfig  = !config;

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    post('/nfse/emitir');
  }

  return (
    <AppShellV2 title="Emitir NFSe">
      <Head title="Emitir Nota Fiscal de Serviço" />
      <div className="p-6 space-y-5">

        {flash && (
          <div className={`rounded-lg px-4 py-3 text-sm border ${
            flash.success
              ? 'bg-[color:var(--accent-soft)] border-[color:var(--accent-2)] text-[color:var(--text)]'
              : 'bg-destructive/10 border-destructive/30 text-destructive'
          }`}>
            {flash.msg}
          </div>
        )}

        <PageHeader
          icon="file-plus"
          title="Emitir NFSe"
          description={`Ambiente: ${config?.ambiente === 'producao' ? 'Produção' : 'Homologação (testes)'}`}
          action={
            <Button variant="outline" size="sm" onClick={() => router.visit('/nfse')}>
              <ArrowLeft size={14} className="mr-1" />
              Voltar
            </Button>
          }
        />

        {(semConfig || certAlerta) && (
          <div className="flex items-start gap-3 rounded-lg border border-warning/20 bg-warning-soft px-4 py-3 text-sm text-warning-fg">
            <AlertCircle size={16} className="shrink-0 mt-0.5" />
            <div>
              {semConfig
                ? 'Módulo NFSe não configurado para esta empresa. Entre em contato com o administrador.'
                : 'Certificado digital expirado ou ausente. Importe o cert A1 via: php artisan nfse:importar-cert'}
            </div>
          </div>
        )}

        {config?.cert_expira && config.cert_valido && (
          <div className="flex items-center gap-2 text-xs text-[color:var(--text-mute)]">
            <span className="inline-block h-2 w-2 rounded-full bg-success" />
            Certificado válido até {config.cert_expira}
          </div>
        )}

        {/* Layout de 2 colunas: formulário + painel venda vinculada */}
        <div className="flex gap-5 items-start">

          {/* Formulário principal */}
          <form onSubmit={handleSubmit} className="flex-1 min-w-0 space-y-5">

            {/* Período */}
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">Competência</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="max-w-xs">
                  <Input
                    type="month"
                    value={data.competencia}
                    onChange={(e) => setData('competencia', e.target.value)}
                    required
                  />
                  {errors.competencia && <p className="text-xs text-destructive mt-1">{errors.competencia}</p>}
                </div>
              </CardContent>
            </Card>

            {/* Tomador */}
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">Tomador do serviço</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div className="sm:col-span-2">
                    <Label className="text-xs">Nome / Razão Social *</Label>
                    <Input
                      value={data.tomador_nome}
                      onChange={(e) => setData('tomador_nome', e.target.value)}
                      placeholder="Ex.: Empresa ABC Ltda"
                      required
                      maxLength={150}
                      className="mt-1"
                    />
                    {errors.tomador_nome && <p className="text-xs text-destructive mt-1">{errors.tomador_nome}</p>}
                  </div>

                  <div>
                    <Label className="text-xs">CNPJ</Label>
                    <Input
                      value={data.tomador_cnpj}
                      onChange={(e) => setData('tomador_cnpj', e.target.value)}
                      placeholder="00.000.000/0000-00" // pii-allowlist máscara visual de input
                      className="mt-1"
                    />
                    {errors.tomador_cnpj && <p className="text-xs text-destructive mt-1">{errors.tomador_cnpj}</p>}
                  </div>

                  <div>
                    <Label className="text-xs">CPF</Label>
                    <Input
                      value={data.tomador_cpf}
                      onChange={(e) => setData('tomador_cpf', e.target.value)}
                      placeholder="000.000.000-00" // pii-allowlist máscara visual de input
                      className="mt-1"
                    />
                    {errors.tomador_cpf && <p className="text-xs text-destructive mt-1">{errors.tomador_cpf}</p>}
                  </div>

                  <div className="sm:col-span-2">
                    <Label className="text-xs">E-mail</Label>
                    <Input
                      type="email"
                      value={data.tomador_email}
                      onChange={(e) => setData('tomador_email', e.target.value)}
                      placeholder="cliente@empresa.com"
                      className="mt-1"
                    />
                    {errors.tomador_email && <p className="text-xs text-destructive mt-1">{errors.tomador_email}</p>}
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Serviço */}
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">Serviço prestado</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div>
                  <Label className="text-xs">Descrição *</Label>
                  <Textarea
                    value={data.descricao}
                    onChange={(e) => setData('descricao', e.target.value)}
                    placeholder="Descreva o serviço prestado..."
                    required
                    maxLength={2000}
                    rows={3}
                    className="mt-1 resize-none"
                  />
                  <p className="text-xs text-[color:var(--text-mute)] mt-0.5 text-right">
                    {data.descricao.length}/2000
                  </p>
                  {errors.descricao && <p className="text-xs text-destructive">{errors.descricao}</p>}
                </div>

                <div>
                  <Label className="text-xs">Código LC 116/2003 *</Label>
                  <Input
                    value={data.lc116_codigo}
                    onChange={(e) => setData('lc116_codigo', e.target.value)}
                    placeholder="1.05"
                    maxLength={5}
                    className="mt-1 max-w-xs"
                    required
                  />
                  <p className="text-xs text-[color:var(--text-mute)] mt-0.5">
                    Ex.: 1.05 licenciamento software · 1.07 suporte técnico
                  </p>
                  {errors.lc116_codigo && <p className="text-xs text-destructive mt-1">{errors.lc116_codigo}</p>}
                </div>
              </CardContent>
            </Card>

            {/* Valores */}
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">Valores e tributação</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div>
                    <Label className="text-xs">Valor dos serviços (R$) *</Label>
                    <Input
                      type="number"
                      step="0.01"
                      min="0.01"
                      value={data.valor_servicos}
                      onChange={(e) => setData('valor_servicos', e.target.value)}
                      placeholder="0,00"
                      required
                      className="mt-1"
                    />
                    {errors.valor_servicos && <p className="text-xs text-destructive mt-1">{errors.valor_servicos}</p>}
                  </div>

                  <div>
                    <Label className="text-xs">Alíquota ISS *</Label>
                    <div className="flex items-center gap-2 mt-1">
                      <Input
                        type="number"
                        step="0.0001"
                        min="0"
                        max="1"
                        value={data.aliquota_iss}
                        onChange={(e) => setData('aliquota_iss', e.target.value)}
                        className="w-28"
                        required
                      />
                      <span className="text-sm text-[color:var(--text-mute)]">
                        = {(aliqNum * 100).toFixed(2)}%
                      </span>
                    </div>
                    {errors.aliquota_iss && <p className="text-xs text-destructive mt-1">{errors.aliquota_iss}</p>}
                  </div>
                </div>

                <div className="flex items-center gap-3 pt-1">
                  <Switch
                    id="iss_retido"
                    checked={data.iss_retido}
                    onCheckedChange={(v) => setData('iss_retido', v)}
                  />
                  <Label htmlFor="iss_retido" className="text-sm cursor-pointer">
                    ISS retido na fonte pelo tomador
                  </Label>
                </div>

                {/* Resumo financeiro */}
                {valorNum > 0 && (
                  <div className="rounded-lg bg-[color:var(--bg-2)] border border-[color:var(--border)] p-4 grid grid-cols-3 gap-3 text-sm mt-2">
                    <div>
                      <p className="text-xs text-[color:var(--text-mute)]">Valor serviços</p>
                      <p className="font-semibold tabular-nums">{brl(valorNum)}</p>
                    </div>
                    <div>
                      <p className="text-xs text-[color:var(--text-mute)]">ISS ({(aliqNum * 100).toFixed(2)}%)</p>
                      <p className="font-semibold tabular-nums text-amber-600 dark:text-amber-400">
                        {brl(valorIss)}
                        {data.iss_retido && <span className="text-xs font-normal ml-1">(retido)</span>}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-[color:var(--text-mute)]">Valor líquido</p>
                      <p className="font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{brl(valorLiq)}</p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Vínculo oculto */}
            {data.transaction_id && (
              <input type="hidden" name="transaction_id" value={data.transaction_id} />
            )}

            <div className="flex justify-end gap-3 pt-2">
              <Button type="button" variant="outline" onClick={() => router.visit('/nfse')}>
                Cancelar
              </Button>
              <Button
                type="submit"
                disabled={processing || semConfig || !!certAlerta}
              >
                <Send size={15} className="mr-1.5" />
                {processing ? 'Enviando...' : 'Emitir NFSe'}
              </Button>
            </div>
          </form>

          {/* Painel "Venda Vinculada" — só renderiza se veio venda */}
          {venda && (
            <aside className="w-72 shrink-0 space-y-3">
              <div className="rounded-lg border border-[color:var(--border)] bg-[color:var(--panel)] overflow-hidden">
                <div className="flex items-center gap-2 px-4 py-3 border-b border-[color:var(--border)] bg-[color:var(--panel-2)]">
                  <ShoppingCart size={14} className="text-[color:var(--accent)]" />
                  <span className="text-xs font-semibold uppercase tracking-wide text-[color:var(--text-mute)]">
                    Venda vinculada
                  </span>
                </div>
                <div className="p-4 space-y-3 text-sm">

                  <div className="flex items-start gap-2">
                    <User size={13} className="text-[color:var(--text-mute)] mt-0.5 shrink-0" />
                    <div className="min-w-0">
                      <p className="text-xs text-[color:var(--text-mute)]">Cliente</p>
                      <p className="font-medium truncate">{venda.contact_nome || '—'}</p>
                      {venda.contact_cnpj && (
                        <p className="text-xs text-[color:var(--text-mute)]">{venda.contact_cnpj}</p>
                      )}
                      {venda.contact_cpf && (
                        <p className="text-xs text-[color:var(--text-mute)]">{venda.contact_cpf}</p>
                      )}
                    </div>
                  </div>

                  <div className="flex items-start gap-2">
                    <Calendar size={13} className="text-[color:var(--text-mute)] mt-0.5 shrink-0" />
                    <div>
                      <p className="text-xs text-[color:var(--text-mute)]">Nota fiscal / Mês</p>
                      <p className="font-medium">
                        {venda.invoice_no ?? '#' + venda.transaction_id}
                      </p>
                      {venda.transaction_date && (
                        <p className="text-xs text-[color:var(--text-mute)]">{venda.transaction_date}</p>
                      )}
                    </div>
                  </div>

                  <div className="flex items-start gap-2">
                    <DollarSign size={13} className="text-[color:var(--text-mute)] mt-0.5 shrink-0" />
                    <div>
                      <p className="text-xs text-[color:var(--text-mute)]">Total da venda</p>
                      <p className="font-semibold tabular-nums text-[color:var(--accent)]">
                        {brl(venda.final_total)}
                      </p>
                    </div>
                  </div>

                  <p className="text-xs text-[color:var(--text-mute)] pt-1 border-t border-[color:var(--border)]">
                    Dados pré-preenchidos a partir desta venda. Você pode editar antes de emitir.
                  </p>
                </div>
              </div>
            </aside>
          )}
        </div>
      </div>
    </AppShellV2>
  );
}
