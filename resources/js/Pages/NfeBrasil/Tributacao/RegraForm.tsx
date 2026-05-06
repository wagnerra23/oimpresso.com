// @memcofre tela=/nfe-brasil/tributacao/regras/(create|edit) module=NfeBrasil
//   us: US-NFE-010 fase 2 (Form criar/editar regra NCM)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { toast } from 'sonner';

interface Regra {
  id: number;
  ncm: string;
  uf_origem: string;
  uf_destino: string | null;
  cfop: string;
  csosn: string | null;
  cst: string | null;
  aliquota_icms: number;
  aliquota_pis: number;
  aliquota_cofins: number;
  aliquota_ipi: number;
  mva: number | null;
  fcp: number | null;
}

interface Props {
  regra: Regra | null;
}

const UFS = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

function RegraForm({ regra }: Props) {
  const editing = regra !== null;
  const [tipoCodigoTributario, setTipoCodigoTributario] = useState<'csosn' | 'cst'>(
    regra?.cst ? 'cst' : 'csosn',
  );

  const form = useForm({
    ncm:             regra?.ncm ?? '',
    uf_origem:       regra?.uf_origem ?? 'SP',
    uf_destino:      regra?.uf_destino ?? '',
    cfop:            regra?.cfop ?? '5102',
    csosn:           regra?.csosn ?? (regra?.cst ? '' : '102'),
    cst:             regra?.cst ?? '',
    aliquota_icms:   regra?.aliquota_icms ?? 0,
    aliquota_pis:    regra?.aliquota_pis ?? 0,
    aliquota_cofins: regra?.aliquota_cofins ?? 0,
    aliquota_ipi:    regra?.aliquota_ipi ?? 0,
    mva:             regra?.mva ?? null,
    fcp:             regra?.fcp ?? null,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    const url = editing ? `/nfe-brasil/tributacao/regras/${regra!.id}` : '/nfe-brasil/tributacao/regras';
    const method = editing ? 'put' : 'post';

    // Limpa o campo não usado dependendo do regime
    if (tipoCodigoTributario === 'csosn') form.setData('cst', '');
    else form.setData('csosn', '');

    form[method](url, {
      preserveScroll: true,
      onSuccess: () => toast.success(editing ? 'Regra atualizada.' : 'Regra criada.'),
      onError: () => toast.error('Verifique os campos destacados.'),
    });
  };

  return (
    <>
      <Head title={editing ? 'Editar regra · Tributação' : 'Nova regra · Tributação'} />

      <div className="p-6 max-w-3xl mx-auto space-y-6">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight">
              {editing ? 'Editar regra tributária' : 'Nova regra tributária'}
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              {editing ? 'Atualize a regra existente.' : 'Cadastre uma regra (Nível 2 ou 3 do cascade ARQ-0006).'}
            </p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href="/nfe-brasil/tributacao">
              <ArrowLeft className="h-4 w-4 mr-1.5" /> Voltar
            </Link>
          </Button>
        </header>

        <form onSubmit={submit}>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Identificação</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="space-y-1.5 sm:col-span-1">
                  <Label htmlFor="ncm">NCM (8 dígitos) *</Label>
                  <Input
                    id="ncm"
                    value={form.data.ncm}
                    onChange={(e) => form.setData('ncm', e.target.value.replace(/\D/g, '').slice(0, 8))}
                    placeholder="49019900"
                    className="font-mono"
                  />
                  {form.errors.ncm && <p className="text-xs text-destructive">{form.errors.ncm}</p>}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="uf_origem">UF Origem *</Label>
                  <Select value={form.data.uf_origem} onValueChange={(v) => form.setData('uf_origem', v)}>
                    <SelectTrigger id="uf_origem"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {UFS.map((uf) => <SelectItem key={uf} value={uf}>{uf}</SelectItem>)}
                    </SelectContent>
                  </Select>
                  {form.errors.uf_origem && <p className="text-xs text-destructive">{form.errors.uf_origem}</p>}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="uf_destino">UF Destino (vazio = todas)</Label>
                  <Select
                    value={form.data.uf_destino ?? ''}
                    onValueChange={(v) => form.setData('uf_destino', v === 'TODAS' ? '' : v)}
                  >
                    <SelectTrigger id="uf_destino">
                      <SelectValue placeholder="Todas (Nível 3)" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="TODAS">Todas (Nível 3)</SelectItem>
                      {UFS.map((uf) => <SelectItem key={uf} value={uf}>{uf} (Nível 2)</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div className="space-y-1.5">
                  <Label htmlFor="cfop">CFOP (4 dígitos) *</Label>
                  <Input
                    id="cfop"
                    value={form.data.cfop}
                    onChange={(e) => form.setData('cfop', e.target.value.replace(/\D/g, '').slice(0, 4))}
                    placeholder="5102"
                    className="font-mono"
                  />
                  {form.errors.cfop && <p className="text-xs text-destructive">{form.errors.cfop}</p>}
                </div>

                <div className="space-y-1.5">
                  <Label>Regime</Label>
                  <Select value={tipoCodigoTributario} onValueChange={(v) => setTipoCodigoTributario(v as 'csosn' | 'cst')}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="csosn">Simples (CSOSN)</SelectItem>
                      <SelectItem value="cst">Normal (CST)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="codigo">{tipoCodigoTributario === 'csosn' ? 'CSOSN' : 'CST'} *</Label>
                  <Input
                    id="codigo"
                    value={tipoCodigoTributario === 'csosn' ? form.data.csosn : form.data.cst}
                    onChange={(e) => form.setData(
                      tipoCodigoTributario,
                      e.target.value.replace(/\D/g, '').slice(0, 3),
                    )}
                    placeholder={tipoCodigoTributario === 'csosn' ? '102' : '000'}
                    className="font-mono"
                  />
                  {form.errors[tipoCodigoTributario] && (
                    <p className="text-xs text-destructive">{form.errors[tipoCodigoTributario]}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className="mt-4">
            <CardHeader>
              <CardTitle className="text-base">Alíquotas</CardTitle>
              <p className="text-xs text-muted-foreground">Em decimal: 0.18 = 18%, 0.0065 = 0,65%.</p>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <FieldDecimal
                  id="aliquota_icms" label="ICMS"
                  value={form.data.aliquota_icms}
                  onChange={(v) => form.setData('aliquota_icms', v)}
                  error={form.errors.aliquota_icms}
                />
                <FieldDecimal
                  id="aliquota_pis" label="PIS"
                  value={form.data.aliquota_pis}
                  onChange={(v) => form.setData('aliquota_pis', v)}
                  error={form.errors.aliquota_pis}
                />
                <FieldDecimal
                  id="aliquota_cofins" label="COFINS"
                  value={form.data.aliquota_cofins}
                  onChange={(v) => form.setData('aliquota_cofins', v)}
                  error={form.errors.aliquota_cofins}
                />
                <FieldDecimal
                  id="aliquota_ipi" label="IPI"
                  value={form.data.aliquota_ipi}
                  onChange={(v) => form.setData('aliquota_ipi', v)}
                  error={form.errors.aliquota_ipi}
                />
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <FieldDecimal
                  id="mva" label="MVA (opcional)"
                  value={form.data.mva ?? 0}
                  onChange={(v) => form.setData('mva', v)}
                  error={form.errors.mva}
                  optional
                />
                <FieldDecimal
                  id="fcp" label="FCP (opcional)"
                  value={form.data.fcp ?? 0}
                  onChange={(v) => form.setData('fcp', v)}
                  error={form.errors.fcp}
                  optional
                />
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end gap-2 mt-4">
            <Button asChild type="button" variant="outline">
              <Link href="/nfe-brasil/tributacao">Cancelar</Link>
            </Button>
            <Button type="submit" disabled={form.processing}>
              <Save className="h-4 w-4 mr-1.5" />
              {form.processing ? 'Salvando…' : (editing ? 'Atualizar' : 'Criar')}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

function FieldDecimal({
  id, label, value, onChange, error, optional,
}: {
  id: string; label: string; value: number;
  onChange: (v: number) => void; error?: string; optional?: boolean;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}{!optional && ' *'}</Label>
      <Input
        id={id}
        type="number"
        step="0.0001"
        min={0}
        max={1}
        value={value}
        onChange={(e) => onChange(parseFloat(e.target.value) || 0)}
        className="font-mono"
      />
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}

RegraForm.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Regra tributária · NF-e Brasil"
    breadcrumbItems={[{ label: 'NF-e Brasil' }, { label: 'Tributação' }, { label: 'Regra' }]}
  >
    {page}
  </AppShellV2>
);

export default RegraForm;
