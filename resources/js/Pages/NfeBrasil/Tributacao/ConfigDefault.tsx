// @memcofre tela=/nfe-brasil/tributacao/config-default module=NfeBrasil
//   us: US-NFE-010 fase 2 (Form Nivel 4 - defaults business)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { ArrowLeft, Save, Settings, Wand2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { toast } from 'sonner';

/**
 * Defaults pré-populados por regime — alinhados com hints visíveis no select.
 * Aplicados via botão "Aplicar pelo regime" (wizard de onboarding).
 *
 * Valores conservadores que cobrem o caso típico — tenant pode ajustar
 * antes de salvar. ICMS de Lucro Presumido/Real é 18% (alíquota interna SP);
 * outros estados mudam por regra estadual (configurar em regras NCM Nível 2/3).
 */
const REGIME_DEFAULTS: Record<string, {
  csosn: string; cst: string;
  icms: number; pis: number; cofins: number; ipi: number;
  toggle: 'csosn' | 'cst';
}> = {
  mei:             { csosn: '102', cst: '',    icms: 0,    pis: 0,      cofins: 0,    ipi: 0, toggle: 'csosn' },
  simples:         { csosn: '102', cst: '',    icms: 0,    pis: 0,      cofins: 0,    ipi: 0, toggle: 'csosn' },
  lucro_presumido: { csosn: '',    cst: '000', icms: 0.18, pis: 0.0065, cofins: 0.03, ipi: 0, toggle: 'cst'   },
  lucro_real:      { csosn: '',    cst: '000', icms: 0.18, pis: 0.0165, cofins: 0.076, ipi: 0, toggle: 'cst'   },
};

interface Config {
  regime: string;
  tributacao_default: Record<string, unknown>;
}

interface Props {
  config: Config;
}

const REGIMES: Array<{ value: string; label: string; hint: string }> = [
  { value: 'mei', label: 'MEI', hint: 'CSOSN 102, ICMS 0%, PIS/COFINS isentos' },
  { value: 'simples', label: 'Simples Nacional', hint: 'CSOSN 102, ICMS conforme anexo, PIS/COFINS pelo DAS' },
  { value: 'lucro_presumido', label: 'Lucro Presumido', hint: 'CST 000, ICMS 18% UF, PIS 0,65%, COFINS 3%' },
  { value: 'lucro_real', label: 'Lucro Real', hint: 'CST 000, ICMS 18% UF, PIS 1,65%, COFINS 7,6%' },
];

function ConfigDefault({ config }: Props) {
  const td = config.tributacao_default;
  const [tipoCodigoTributario, setTipoCodigoTributario] = useState<'csosn' | 'cst'>(
    td.cst ? 'cst' : 'csosn',
  );

  const form = useForm({
    regime:           config.regime,
    ncm_default:      String(td.ncm_default ?? '00000000'),
    cfop_default:     String(td.cfop_default ?? td.cfop ?? '5102'),
    csosn:            String(td.csosn ?? (td.cst ? '' : '102')),
    cst:              String(td.cst ?? ''),
    aliquota_icms:    Number(td.aliquota_icms ?? 0),
    aliquota_pis:     Number(td.aliquota_pis ?? 0),
    aliquota_cofins:  Number(td.aliquota_cofins ?? 0),
    aliquota_ipi:     Number(td.aliquota_ipi ?? 0),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (tipoCodigoTributario === 'csosn') form.setData('cst', '');
    else form.setData('csosn', '');

    form.post('/nfe-brasil/tributacao/config-default', {
      preserveScroll: true,
      onSuccess: () => toast.success('Configuração salva.'),
      onError: () => toast.error('Verifique os campos.'),
    });
  };

  /**
   * Wizard inline: aplica defaults baseados no regime selecionado.
   * Não envia ao servidor — só preenche os campos. Tenant revê e clica "Salvar".
   * Preserva ncm_default e cfop_default (são escolha do business, não do regime).
   */
  const aplicarDefaultsRegime = () => {
    const defaults = REGIME_DEFAULTS[form.data.regime];
    if (!defaults) return;

    setTipoCodigoTributario(defaults.toggle);
    form.setData((data) => ({
      ...data,
      csosn:           defaults.csosn,
      cst:             defaults.cst,
      aliquota_icms:   defaults.icms,
      aliquota_pis:    defaults.pis,
      aliquota_cofins: defaults.cofins,
      aliquota_ipi:    defaults.ipi,
    }));
    toast.success(`Defaults aplicados: ${REGIMES.find((r) => r.value === form.data.regime)?.label}.`);
  };

  const regimeAtual = REGIMES.find((r) => r.value === form.data.regime);

  return (
    <>
      <Head title="Configuração padrão · Tributação" />

      <div className="p-6 max-w-3xl mx-auto space-y-6">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
              <Settings className="h-6 w-6" />
              Configuração padrão
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Cascade Nível 4 — usado quando produto não tem regra específica nem override. Pré-requisito para
              emissão automática de NFe via cobrança recorrente.
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
              <CardTitle className="text-base">Regime tributário</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1.5">
                <Label htmlFor="regime">Regime *</Label>
                <Select value={form.data.regime} onValueChange={(v) => form.setData('regime', v)}>
                  <SelectTrigger id="regime"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {REGIMES.map((r) => (
                      <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {regimeAtual && (
                  <p className="text-xs text-muted-foreground">{regimeAtual.hint}</p>
                )}
              </div>
              <div className="flex items-center justify-between gap-3 pt-2 border-t">
                <p className="text-xs text-muted-foreground">
                  Pré-popula CSOSN/CST + alíquotas conforme regime escolhido. Você pode ajustar antes de salvar.
                </p>
                <Button type="button" variant="outline" size="sm" onClick={aplicarDefaultsRegime}>
                  <Wand2 className="h-3.5 w-3.5 mr-1.5" />
                  Aplicar pelo regime
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card className="mt-4">
            <CardHeader>
              <CardTitle className="text-base">Defaults fiscais</CardTitle>
              <p className="text-xs text-muted-foreground">
                Aplicados quando NCM do produto não tem regra cadastrada. Mantenha um NCM genérico (ex: 49019900
                para impressos).
              </p>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <Label htmlFor="ncm_default">NCM padrão (8 dígitos) *</Label>
                  <Input
                    id="ncm_default"
                    value={form.data.ncm_default}
                    onChange={(e) => form.setData('ncm_default', e.target.value.replace(/\D/g, '').slice(0, 8))}
                    placeholder="49019900"
                    className="font-mono"
                  />
                  {form.errors.ncm_default && <p className="text-xs text-destructive">{form.errors.ncm_default}</p>}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="cfop_default">CFOP padrão (4 dígitos) *</Label>
                  <Input
                    id="cfop_default"
                    value={form.data.cfop_default}
                    onChange={(e) => form.setData('cfop_default', e.target.value.replace(/\D/g, '').slice(0, 4))}
                    placeholder="5102"
                    className="font-mono"
                  />
                  {form.errors.cfop_default && <p className="text-xs text-destructive">{form.errors.cfop_default}</p>}
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <Label>Código tributário</Label>
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
              <p className="text-xs text-muted-foreground">Decimal: 0.18 = 18%, 0.0065 = 0,65%.</p>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                {(['aliquota_icms', 'aliquota_pis', 'aliquota_cofins', 'aliquota_ipi'] as const).map((field) => {
                  const labels: Record<string, string> = {
                    aliquota_icms: 'ICMS',
                    aliquota_pis: 'PIS',
                    aliquota_cofins: 'COFINS',
                    aliquota_ipi: 'IPI',
                  };
                  return (
                    <div key={field} className="space-y-1.5">
                      <Label htmlFor={field}>{labels[field]} {field !== 'aliquota_ipi' && '*'}</Label>
                      <Input
                        id={field}
                        type="number"
                        step="0.0001"
                        min={0}
                        max={1}
                        value={form.data[field]}
                        onChange={(e) => form.setData(field, parseFloat(e.target.value) || 0)}
                        className="font-mono"
                      />
                      {form.errors[field] && <p className="text-xs text-destructive">{form.errors[field]}</p>}
                    </div>
                  );
                })}
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end gap-2 mt-4">
            <Button asChild type="button" variant="outline">
              <Link href="/nfe-brasil/tributacao">Cancelar</Link>
            </Button>
            <Button type="submit" disabled={form.processing}>
              <Save className="h-4 w-4 mr-1.5" />
              {form.processing ? 'Salvando…' : 'Salvar configuração'}
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

ConfigDefault.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Configuração padrão · NF-e Brasil"
    breadcrumbItems={[{ label: 'NF-e Brasil' }, { label: 'Tributação' }, { label: 'Configuração padrão' }]}
  >
    {page}
  </AppShellV2>
);

export default ConfigDefault;
