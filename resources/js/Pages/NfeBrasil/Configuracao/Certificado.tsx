// @nfebrasil tela=/nfe-brasil/configuracao module=NfeBrasil us=NFE-041

import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, type FormEvent, type ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { toast } from 'sonner';
import { ShieldCheck, AlertTriangle, XCircle, Upload, KeyRound } from 'lucide-react';

interface CertStatus {
  cnpj_titular: string;
  valido_ate: string;
  dias_ate_vencimento: number;
  alerta: 'ok' | 'proximo_vencimento' | 'vencido';
}

interface Props {
  cert: CertStatus | null;
  upload_url: string;
}

function AlertaBadge({ alerta, dias }: { alerta: CertStatus['alerta']; dias: number }) {
  if (alerta === 'vencido') {
    return (
      <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
        <XCircle className="h-3 w-3" /> Vencido
      </span>
    );
  }
  if (alerta === 'proximo_vencimento') {
    return (
      <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
        <AlertTriangle className="h-3 w-3" /> Vence em {dias}d
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1 text-xs px-2 py-1 rounded bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-200">
      <ShieldCheck className="h-3 w-3" /> Válido · {dias}d restantes
    </span>
  );
}

function Certificado({ cert, upload_url }: Props) {
  const flash = (usePage().props as any)?.flash?.status;
  const fileRef = useRef<HTMLInputElement>(null);

  const form = useForm<{ certificado: File | null; senha: string }>({
    certificado: null,
    senha: '',
  });

  useEffect(() => {
    if (flash) toast.success(flash);
  }, [flash]);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post(upload_url, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        form.reset('senha');
        if (fileRef.current) fileRef.current.value = '';
      },
      onError: () => toast.error('Verifique os campos destacados'),
    });
  };

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Certificado Digital A1</h1>
        <p className="text-sm text-muted-foreground mt-1">
          Certificado e-CNPJ A1 (.pfx) utilizado para assinar e transmitir NF-e / NFC-e à SEFAZ.
        </p>
      </div>

      {/* Status atual */}
      {cert ? (
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <KeyRound className="h-4 w-4 text-muted-foreground" />
              Certificado atual
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div className="flex justify-between items-center">
              <span className="text-muted-foreground">CNPJ titular</span>
              <span className="font-mono font-medium">{cert.cnpj_titular}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-muted-foreground">Validade</span>
              <span>{cert.valido_ate}</span>
            </div>
            <div className="flex justify-between items-center">
              <span className="text-muted-foreground">Status</span>
              <AlertaBadge alerta={cert.alerta} dias={cert.dias_ate_vencimento} />
            </div>
          </CardContent>
        </Card>
      ) : (
        <Card className="border-amber-200 dark:border-amber-800">
          <CardContent className="pt-6 flex items-center gap-3 text-sm text-amber-700 dark:text-amber-300">
            <AlertTriangle className="h-5 w-5 shrink-0" />
            <span>Nenhum certificado ativo. Faça upload para começar a emitir NF-e.</span>
          </CardContent>
        </Card>
      )}

      {/* Upload form */}
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-sm font-medium flex items-center gap-2">
            <Upload className="h-4 w-4 text-muted-foreground" />
            {cert ? 'Renovar / substituir certificado' : 'Upload do certificado'}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label htmlFor="certificado">Arquivo .pfx / .p12 *</Label>
              <Input
                ref={fileRef}
                id="certificado"
                type="file"
                accept=".pfx,.p12"
                className="mt-1"
                onChange={(e) => form.setData('certificado', e.target.files?.[0] ?? null)}
              />
              {form.errors.certificado && (
                <p className="text-xs text-destructive mt-1">{form.errors.certificado}</p>
              )}
              <p className="text-xs text-muted-foreground mt-1">
                Certificado A1 emitido por AC credenciada (Certisign, Serasa, Soluti etc.). Máx 100 KB.
              </p>
            </div>

            <div>
              <Label htmlFor="senha">Senha do certificado *</Label>
              <Input
                id="senha"
                type="password"
                autoComplete="new-password"
                value={form.data.senha}
                onChange={(e) => form.setData('senha', e.target.value)}
                className="mt-1"
                placeholder="Senha definida ao emitir o certificado"
              />
              {form.errors.senha && (
                <p className="text-xs text-destructive mt-1">{form.errors.senha}</p>
              )}
              <p className="text-xs text-muted-foreground mt-1">
                A senha não é armazenada — apenas o certificado cifrado é guardado.
              </p>
            </div>

            <div className="flex justify-end pt-2">
              <Button type="submit" disabled={form.processing || !form.data.certificado}>
                {form.processing ? 'Enviando…' : cert ? 'Substituir certificado' : 'Fazer upload'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

Certificado.layout = (page: ReactNode) => (
  <AppShellV2
    title="Certificado A1 · NF-e Brasil"
    breadcrumbItems={[{ label: 'NF-e Brasil' }, { label: 'Certificado A1' }]}
  >
    {page}
  </AppShellV2>
);

export default Certificado;
