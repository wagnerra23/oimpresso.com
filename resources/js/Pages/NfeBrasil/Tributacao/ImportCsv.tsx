// @memcofre tela=/nfe-brasil/tributacao/import module=NfeBrasil
//   us: US-NFE-010 fase 3 (Import CSV regras tributárias em massa)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { AlertCircle, ArrowLeft, CheckCircle2, FileSpreadsheet, Upload } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { toast } from 'sonner';

interface PreviewLinha {
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
}

interface PreviewErro {
  linha: number;
  motivo: string;
}

interface Preview {
  total_validas: number;
  total_erros: number;
  amostras: PreviewLinha[];
  erros: PreviewErro[];
}

interface Props {
  colunas_obrigatorias: string[];
}

interface FlashProps {
  flash?: { preview?: Preview; success?: string };
}

function pct(v: number): string {
  return `${(v * 100).toFixed(2).replace('.', ',')}%`;
}

function ImportCsv({ colunas_obrigatorias }: Props) {
  const { props } = usePage<FlashProps>();
  const preview = props.flash?.preview;
  const success = props.flash?.success;

  const form = useForm<{ arquivo: File | null }>({ arquivo: null });

  const submitPreview = (e: FormEvent) => {
    e.preventDefault();
    form.post('/nfe-brasil/tributacao/import/preview', {
      forceFormData: true,
      onError: () => toast.error('Verifique o arquivo selecionado.'),
    });
  };

  const aplicar = () => {
    if (!preview || preview.total_validas === 0) return;
    if (!confirm(`Aplicar ${preview.total_validas} regra(s)? Idempotente — atualiza existentes pela chave (NCM + UF origem + UF destino).`)) {
      return;
    }
    router.post('/nfe-brasil/tributacao/import/aplicar', {}, {
      onSuccess: () => toast.success('Import aplicado.'),
      onError: () => toast.error('Falha ao aplicar — sem mudanças.'),
    });
  };

  return (
    <>
      <Head title="Import CSV · Tributação" />

      <div className="p-6 max-w-5xl mx-auto space-y-6">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
              <FileSpreadsheet className="h-6 w-6" />
              Import CSV de regras tributárias
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Bulk import de regras NCM. Idempotente: regras existentes são atualizadas pela chave
              <code className="text-xs"> (NCM + UF origem + UF destino)</code>.
            </p>
          </div>
          <Button asChild variant="outline" size="sm">
            <Link href="/nfe-brasil/tributacao">
              <ArrowLeft className="h-4 w-4 mr-1.5" /> Voltar
            </Link>
          </Button>
        </header>

        {success && (
          <div className="flex items-start gap-2 px-4 py-3 rounded-md bg-success-soft text-success-fg border border-success/20">
            <CheckCircle2 className="h-5 w-5 mt-0.5 shrink-0" />
            <p className="text-sm">{success}</p>
          </div>
        )}

        {/* Step 1: upload */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base flex items-center gap-2">
              <Upload className="h-4 w-4" />
              1. Upload do arquivo
            </CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submitPreview} className="space-y-4">
              <div className="space-y-1.5">
                <Label htmlFor="arquivo">Arquivo CSV (máx 5 MB) *</Label>
                <Input
                  id="arquivo"
                  type="file"
                  accept=".csv,.txt"
                  onChange={(e) => form.setData('arquivo', e.target.files?.[0] ?? null)}
                  className="file:mr-3 file:rounded-sm file:border-0 file:bg-muted file:px-2 file:py-1"
                />
                {form.errors.arquivo && (
                  <p className="text-xs text-destructive">{form.errors.arquivo}</p>
                )}
              </div>

              <div className="bg-muted/50 rounded-md p-3 space-y-2">
                <p className="text-xs font-medium">Cabeçalho esperado (1ª linha):</p>
                <code className="text-xs block font-mono break-all">
                  {colunas_obrigatorias.join(',')}
                </code>
                <p className="text-xs text-muted-foreground mt-2">
                  Exemplo:{' '}
                  <code className="text-xs">
                    49019900,SP,,5102,102,,0,0.0065,0.03,0
                  </code>
                </p>
                <p className="text-xs text-muted-foreground">
                  • <code>uf_destino</code> vazio = "todas" (Nível 3 cascade)<br />
                  • <code>csosn</code> OU <code>cst</code> (mutuamente exclusivos)<br />
                  • Alíquotas em decimal (<code>0.18</code> = 18%)
                </p>
              </div>

              <div className="flex justify-end">
                <Button type="submit" disabled={form.processing || !form.data.arquivo}>
                  {form.processing ? 'Processando…' : 'Pré-visualizar'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        {/* Step 2: preview + apply */}
        {preview && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center justify-between">
                <span>2. Pré-visualização</span>
                <div className="flex gap-3 text-xs font-normal">
                  <span className="text-success">✓ {preview.total_validas} válidas</span>
                  {preview.total_erros > 0 && (
                    <span className="text-destructive">✗ {preview.total_erros} com erro</span>
                  )}
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {preview.total_erros > 0 && (
                <details className="border border-destructive/30 rounded-md">
                  <summary className="cursor-pointer px-3 py-2 text-sm font-medium bg-destructive/5 flex items-center gap-2">
                    <AlertCircle className="h-4 w-4 text-destructive" />
                    {preview.total_erros} linha(s) com erro — não serão importadas
                  </summary>
                  <div className="px-3 py-2 max-h-48 overflow-auto">
                    <table className="w-full text-xs">
                      <thead>
                        <tr>
                          <th className="text-left pr-2">Linha</th>
                          <th className="text-left">Motivo</th>
                        </tr>
                      </thead>
                      <tbody>
                        {preview.erros.map((e, i) => (
                          <tr key={i} className="border-t">
                            <td className="py-1 pr-2 font-mono">{e.linha}</td>
                            <td className="py-1 text-destructive">{e.motivo}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </details>
              )}

              {preview.total_validas > 0 && (
                <>
                  <p className="text-xs text-muted-foreground">
                    Amostra das primeiras {preview.amostras.length} linhas válidas (de {preview.total_validas} total):
                  </p>
                  <div className="overflow-x-auto -mx-6 max-h-96">
                    <table className="w-full text-xs">
                      <thead className="bg-muted/50 text-left sticky top-0">
                        <tr>
                          <th className="px-6 py-2 font-medium">NCM</th>
                          <th className="px-2 py-2 font-medium">UF Orig</th>
                          <th className="px-2 py-2 font-medium">UF Dest</th>
                          <th className="px-2 py-2 font-medium">CFOP</th>
                          <th className="px-2 py-2 font-medium">CSOSN/CST</th>
                          <th className="px-2 py-2 font-medium text-right">ICMS</th>
                          <th className="px-2 py-2 font-medium text-right">PIS</th>
                          <th className="px-2 py-2 font-medium text-right">COFINS</th>
                          <th className="px-6 py-2 font-medium text-right">IPI</th>
                        </tr>
                      </thead>
                      <tbody>
                        {preview.amostras.map((l, i) => (
                          <tr key={i} className="border-t">
                            <td className="px-6 py-1 font-mono">{l.ncm}</td>
                            <td className="px-2 py-1 font-mono">{l.uf_origem}</td>
                            <td className="px-2 py-1 font-mono">
                              {l.uf_destino ?? <span className="text-muted-foreground italic">todas</span>}
                            </td>
                            <td className="px-2 py-1 font-mono">{l.cfop}</td>
                            <td className="px-2 py-1 font-mono">{l.csosn ?? l.cst}</td>
                            <td className="px-2 py-1 text-right font-mono">{pct(l.aliquota_icms)}</td>
                            <td className="px-2 py-1 text-right font-mono">{pct(l.aliquota_pis)}</td>
                            <td className="px-2 py-1 text-right font-mono">{pct(l.aliquota_cofins)}</td>
                            <td className="px-6 py-1 text-right font-mono">{pct(l.aliquota_ipi)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  <div className="flex justify-end pt-3 border-t">
                    <Button onClick={aplicar}>
                      Aplicar {preview.total_validas} regra(s)
                    </Button>
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </>
  );
}

ImportCsv.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Import CSV · Tributação · NF-e Brasil"
    breadcrumbItems={[{ label: 'NF-e Brasil' }, { label: 'Tributação' }, { label: 'Import CSV' }]}
  >
    {page}
  </AppShellV2>
);

export default ImportCsv;
