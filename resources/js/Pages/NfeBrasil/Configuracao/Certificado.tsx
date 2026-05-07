// @memcofre tela=/nfe-brasil/configuracao/certificado module=NfeBrasil
//   us: US-NFE-041 (CertificadoService + storage encrypted + UI admin)
//   adrs: NfeBrasil/adr/arq/0003-cert-a1-storage-criptografado, ADR 0029 (Inertia + UPos)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useRef, useState } from 'react';
import { AlertTriangle, CheckCircle2, KeyRound, Loader2, PlugZap, ShieldAlert, ShieldCheck, Upload, XCircle } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { toast } from 'sonner';

type SefazTesteResultado = {
  ok: boolean;
  cstat: string;
  xMotivo: string;
  tempoResposta: number;
  ambiente: number;
  uf: string;
  versao?: string | null;
  error?: string;
};

type Alerta = 'ok' | 'proximo_vencimento' | 'vencido';

interface PageProps {
  tem_certificado: boolean;
  cnpj_business: string | null;
  cnpj_titular?: string;
  valido_ate?: string;          // YYYY-MM-DD
  dias_ate_vencimento?: number;
  alerta?: Alerta;
}

interface FlashProps {
  flash?: { success?: string };
}

function formatCnpj(raw: string): string {
  const digits = raw.replace(/\D/g, '').padStart(14, '0').slice(-14);
  return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
}

function formatDateBr(iso: string): string {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

function StatusBadge({ alerta, dias }: { alerta: Alerta; dias: number }) {
  if (alerta === 'vencido') {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-red-100 text-red-900 dark:bg-red-900/30 dark:text-red-300 font-medium">
        <ShieldAlert className="h-3.5 w-3.5" />
        Vencido há {Math.abs(dias)} dia{Math.abs(dias) === 1 ? '' : 's'}
      </span>
    );
  }
  if (alerta === 'proximo_vencimento') {
    return (
      <span className="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-amber-100 text-amber-900 dark:bg-amber-900/30 dark:text-amber-300 font-medium">
        <AlertTriangle className="h-3.5 w-3.5" />
        Vence em {dias} dia{dias === 1 ? '' : 's'}
      </span>
    );
  }
  return (
    <span className="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-900 dark:bg-emerald-900/30 dark:text-emerald-300 font-medium">
      <ShieldCheck className="h-3.5 w-3.5" />
      Ativo · {dias} dias até vencimento
    </span>
  );
}

function Certificado(props: PageProps) {
  const { props: pageProps } = usePage<FlashProps>();
  const success = pageProps.flash?.success;

  const fileRef = useRef<HTMLInputElement>(null);
  const form = useForm<{ certificado: File | null; senha: string }>({
    certificado: null,
    senha: '',
  });

  // Teste SEFAZ — local state (não Inertia, evita reload da Page)
  const [testando, setTestando] = useState(false);
  const [resultadoTeste, setResultadoTeste] = useState<SefazTesteResultado | null>(null);

  const testarSefaz = async () => {
    setTestando(true);
    setResultadoTeste(null);
    try {
      const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const res = await fetch('/nfe-brasil/configuracao/certificado/testar', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const payload: SefazTesteResultado = await res.json();
      setResultadoTeste(payload);
      if (payload.ok) {
        toast.success(`SEFAZ-${payload.uf} online (cstat ${payload.cstat})`);
      } else {
        toast.error(`SEFAZ retornou cstat ${payload.cstat}: ${payload.xMotivo}`);
      }
    } catch (e) {
      const msg = e instanceof Error ? e.message : 'Erro desconhecido';
      setResultadoTeste({
        ok: false,
        cstat: '—',
        xMotivo: `Falha de rede: ${msg}`,
        tempoResposta: 0,
        ambiente: 0,
        uf: '—',
        error: 'network',
      });
      toast.error('Falha de rede ao chamar endpoint.');
    } finally {
      setTestando(false);
    }
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/nfe-brasil/configuracao/certificado', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        form.reset('certificado', 'senha');
        if (fileRef.current) fileRef.current.value = '';
        toast.success('Certificado A1 cadastrado.');
      },
      onError: () => toast.error('Verifique o arquivo e a senha.'),
    });
  };

  const cnpjBusinessFmt = props.cnpj_business ? formatCnpj(props.cnpj_business) : null;

  return (
    <>
      <Head title="Certificado A1 · NF-e Brasil" />

      <div className="p-6 max-w-3xl mx-auto space-y-6">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
            <KeyRound className="h-6 w-6" />
            Certificado A1
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Sobe o <code>.pfx</code> ou <code>.p12</code> + senha. O sistema valida CNPJ, criptografa em
            disco e usa para emissão/cancelamento de NF-e (modelo 55) e NFC-e (modelo 65).
          </p>
        </header>

        {success && (
          <div className="flex items-start gap-2 px-4 py-3 rounded-md bg-emerald-50 text-emerald-900 border border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-200 dark:border-emerald-800">
            <CheckCircle2 className="h-5 w-5 mt-0.5 shrink-0" />
            <p className="text-sm">{success}</p>
          </div>
        )}

        {/* Status atual */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base flex items-center justify-between">
              <span>Status atual</span>
              {props.tem_certificado && props.alerta && typeof props.dias_ate_vencimento === 'number' && (
                <StatusBadge alerta={props.alerta} dias={props.dias_ate_vencimento} />
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!props.tem_certificado ? (
              <p className="text-sm text-muted-foreground">
                Nenhum certificado A1 ativo para este business. Faça upload abaixo para começar a emitir.
              </p>
            ) : (
              <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                  <dt className="text-xs text-muted-foreground uppercase tracking-wide">CNPJ titular</dt>
                  <dd className="font-mono mt-0.5">{props.cnpj_titular ? formatCnpj(props.cnpj_titular) : '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground uppercase tracking-wide">Válido até</dt>
                  <dd className="mt-0.5">{props.valido_ate ? formatDateBr(props.valido_ate) : '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground uppercase tracking-wide">Dias até vencer</dt>
                  <dd className="mt-0.5">{props.dias_ate_vencimento ?? '—'}</dd>
                </div>
              </dl>
            )}
            {cnpjBusinessFmt && (
              <p className="text-xs text-muted-foreground mt-3 pt-3 border-t">
                CNPJ do business cadastrado: <span className="font-mono">{cnpjBusinessFmt}</span> — o
                certificado precisa pertencer a este CNPJ.
              </p>
            )}
          </CardContent>
        </Card>

        {/* Teste SEFAZ — só faz sentido se houver cert ativo */}
        {props.tem_certificado && (
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base flex items-center gap-2">
                <PlugZap className="h-4 w-4" />
                Testar conexão SEFAZ
              </CardTitle>
              <p className="text-xs text-muted-foreground">
                Consulta <code>NFeStatusServico</code> usando o certificado ativo. Não emite NF-e —
                só pinga o web service da SEFAZ. <code>cstat=107</code> = OK.
              </p>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                  Use antes de configurar emissão automática ou ao diagnosticar emissões travadas.
                </p>
                <Button onClick={testarSefaz} disabled={testando} variant="outline">
                  {testando ? (
                    <>
                      <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                      Testando…
                    </>
                  ) : (
                    <>
                      <PlugZap className="h-4 w-4 mr-2" />
                      Testar agora
                    </>
                  )}
                </Button>
              </div>

              {resultadoTeste && (
                <div
                  role="status"
                  aria-live="polite"
                  className={`rounded-md p-3 border text-sm ${
                    resultadoTeste.ok
                      ? 'bg-emerald-50 text-emerald-900 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-200 dark:border-emerald-800'
                      : 'bg-red-50 text-red-900 border-red-200 dark:bg-red-900/20 dark:text-red-200 dark:border-red-800'
                  }`}
                >
                  <div className="flex items-start gap-2">
                    {resultadoTeste.ok ? (
                      <CheckCircle2 className="h-5 w-5 mt-0.5 shrink-0" />
                    ) : (
                      <XCircle className="h-5 w-5 mt-0.5 shrink-0" />
                    )}
                    <div className="flex-1 min-w-0 space-y-1">
                      <div className="font-medium">
                        {resultadoTeste.ok
                          ? `SEFAZ-${resultadoTeste.uf} online`
                          : resultadoTeste.uf && resultadoTeste.uf !== '—'
                            ? `Erro consultando SEFAZ-${resultadoTeste.uf}`
                            : `Erro consultando SEFAZ`}
                        {resultadoTeste.cstat && resultadoTeste.cstat !== '—' && (
                          <>
                            {' · '}
                            <span className="font-mono text-xs">cstat {resultadoTeste.cstat}</span>
                          </>
                        )}
                      </div>
                      <div className="text-xs opacity-90">{resultadoTeste.xMotivo}</div>
                      <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs opacity-75 pt-1">
                        {resultadoTeste.uf !== '—' && (
                          <span>
                            UF: <span className="font-mono">{resultadoTeste.uf}</span>
                          </span>
                        )}
                        {resultadoTeste.ambiente > 0 && (
                          <span>
                            Ambiente:{' '}
                            <span className="font-mono">
                              {resultadoTeste.ambiente === 1 ? 'produção' : 'homologação'}
                            </span>
                          </span>
                        )}
                        {resultadoTeste.tempoResposta > 0 && (
                          <span>
                            Tempo: <span className="font-mono">{resultadoTeste.tempoResposta}s</span>
                          </span>
                        )}
                        {resultadoTeste.versao && (
                          <span>
                            verAplic: <span className="font-mono">{resultadoTeste.versao}</span>
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        )}

        {/* Upload */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base flex items-center gap-2">
              <Upload className="h-4 w-4" />
              {props.tem_certificado ? 'Substituir certificado' : 'Upload do certificado'}
            </CardTitle>
            {props.tem_certificado && (
              <p className="text-xs text-muted-foreground">
                Subir um certificado novo desativa o atual automaticamente (rotação cega).
              </p>
            )}
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="space-y-1.5">
                <Label htmlFor="certificado">Arquivo .pfx ou .p12 *</Label>
                <Input
                  id="certificado"
                  ref={fileRef}
                  type="file"
                  accept=".pfx,.p12"
                  onChange={(e) => form.setData('certificado', e.target.files?.[0] ?? null)}
                  className="file:mr-3 file:rounded-sm file:border-0 file:bg-muted file:px-2 file:py-1"
                />
                <p className="text-xs text-muted-foreground">
                  Máximo 100 KB. Certificado A3 (token físico) não é suportado.
                </p>
                {form.errors.certificado && (
                  <p className="text-xs text-destructive">{form.errors.certificado}</p>
                )}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="senha">Senha do certificado *</Label>
                <Input
                  id="senha"
                  type="password"
                  value={form.data.senha}
                  onChange={(e) => form.setData('senha', e.target.value)}
                  autoComplete="off"
                  maxLength={80}
                />
                <p className="text-xs text-muted-foreground">
                  Não fica em log nem no audit. Armazenada em DB criptografada via <code>encrypt()</code>.
                </p>
                {form.errors.senha && (
                  <p className="text-xs text-destructive">{form.errors.senha}</p>
                )}
              </div>

              <div className="flex items-center justify-end gap-2 pt-2">
                <Button
                  type="submit"
                  disabled={form.processing || !form.data.certificado || !form.data.senha}
                >
                  {form.processing ? 'Enviando…' : props.tem_certificado ? 'Substituir' : 'Enviar certificado'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <p className="text-xs text-muted-foreground">
          O certificado fica criptografado em disco (<code>encrypt-at-rest</code>) e a senha em DB
          via <code>encrypt()</code> Laravel — nunca em texto puro. CNPJ do titular é validado contra
          o CNPJ do business antes da gravação.
        </p>
      </div>
    </>
  );
}

Certificado.layout = (page: React.ReactNode) => (
  <AppShellV2
    title="Certificado A1 · NF-e Brasil"
    breadcrumbItems={[{ label: 'NF-e Brasil' }, { label: 'Configuração' }, { label: 'Certificado A1' }]}
  >
    {page}
  </AppShellV2>
);

export default Certificado;
