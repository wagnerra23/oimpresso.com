// @memcofre
//   tela: /nfse/emitir
//   module: NFSe
//   stories: US-NFSE-009
//   permissao: nfse.emit

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Send } from 'lucide-react';

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

interface Props {
  config: Config | null;
  flash?: { success: boolean; msg: string } | null;
}

export default function NfseEmitir({ config, flash }: Props) {
  const today = new Date();
  const mesAtual = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;

  const { data, setData, post, processing, errors } = useForm({
    competencia:    mesAtual,
    tomador_nome:   '',
    tomador_cnpj:   '',
    tomador_cpf:    '',
    tomador_email:  '',
    descricao:      '',
    lc116_codigo:   config?.lc116_codigo_default ?? '1.05',
    valor_servicos: '',
    aliquota_iss:   config?.aliquota_iss != null ? String(config.aliquota_iss) : '0.05',
    iss_retido:     false as boolean,
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
      <div className="p-6 max-w-3xl mx-auto space-y-5">

        {flash && (
          <div className={`rounded-lg px-4 py-3 text-sm border ${
            flash.success
              ? 'bg-emerald-50 border-emerald-200 text-emerald-800'
              : 'bg-red-50 border-red-200 text-red-800'
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
          <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <AlertCircle size={16} className="shrink-0 mt-0.5" />
            <div>
              {semConfig
                ? 'Módulo NFSe não configurado para esta empresa. Entre em contato com o administrador.'
                : `Certificado digital expirado ou ausente. Importe o cert A1 via: php artisan nfse:importar-cert`}
            </div>
          </div>
        )}

        {config?.cert_expira && config.cert_valido && (
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span className="inline-block h-2 w-2 rounded-full bg-emerald-500" />
            Certificado válido até {config.cert_expira}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5">

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
                    placeholder="00.000.000/0000-00"
                    className="mt-1"
                  />
                  {errors.tomador_cnpj && <p className="text-xs text-destructive mt-1">{errors.tomador_cnpj}</p>}
                </div>

                <div>
                  <Label className="text-xs">CPF</Label>
                  <Input
                    value={data.tomador_cpf}
                    onChange={(e) => setData('tomador_cpf', e.target.value)}
                    placeholder="000.000.000-00"
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
                <p className="text-xs text-muted-foreground mt-0.5 text-right">
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
                <p className="text-xs text-muted-foreground mt-0.5">
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
                    <span className="text-sm text-muted-foreground">
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
                <div className="rounded-lg bg-muted/50 border border-border p-4 grid grid-cols-3 gap-3 text-sm mt-2">
                  <div>
                    <p className="text-xs text-muted-foreground">Valor serviços</p>
                    <p className="font-semibold tabular-nums">
                      {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valorNum)}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">ISS ({(aliqNum * 100).toFixed(2)}%)</p>
                    <p className="font-semibold tabular-nums text-amber-700">
                      {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valorIss)}
                      {data.iss_retido && <span className="text-xs font-normal ml-1">(retido)</span>}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground">Valor líquido</p>
                    <p className="font-semibold tabular-nums text-emerald-700">
                      {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valorLiq)}
                    </p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          <div className="flex justify-end gap-3 pt-2">
            <Button type="button" variant="outline" onClick={() => router.visit('/nfse')}>
              Cancelar
            </Button>
            <Button
              type="submit"
              disabled={processing || semConfig || certAlerta || false}
            >
              <Send size={15} className="mr-1.5" />
              {processing ? 'Enviando...' : 'Emitir NFSe'}
            </Button>
          </div>
        </form>
      </div>
    </AppShellV2>
  );
}
