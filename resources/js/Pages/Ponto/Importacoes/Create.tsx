// @docvault
//   tela: /ponto/importacoes/create
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-010
//   rules: R-PONT-001
//   adrs: tech/0001
//   tests: Modules/PontoWr2/Tests/Feature/ImportacoesCreateTest

import AppShell from '@/Layouts/AppShell';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, FileUp, Info, Upload } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

export default function ImportacoesCreate() {
  const form = useForm({
    tipo: 'AFD',
    arquivo: null as File | null,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (!form.data.arquivo) {
      toast.error('Selecione um arquivo.');
      return;
    }
    form.post('/ponto/importacoes', {
      forceFormData: true,
      onSuccess: () => toast.success('Arquivo enviado para processamento.'),
      onError: (e) => toast.error(Object.values(e)[0] as string),
    });
  };

  return (
    <AppShell
      title="Nova importação"
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Importações', href: '/ponto/importacoes' },
        { label: 'Nova' },
      ]}
    >
      <div className="mx-auto max-w-2xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <FileUp size={22} /> Nova importação
            </h1>
          </div>
          <Button variant="outline" size="sm" asChild>
            <a href="/ponto/importacoes"><ArrowLeft size={14} className="mr-1.5" /> Voltar</a>
          </Button>
        </header>

        <Alert>
          <Info size={14} />
          <AlertTitle>Como funciona</AlertTitle>
          <AlertDescription className="text-xs space-y-1">
            <p>1. Envie um arquivo AFD (Portaria MTP 671/2021) ou AFDT gerado pelo REP.</p>
            <p>2. Sistema calcula SHA-256 e bloqueia arquivos duplicados (mesmo hash).</p>
            <p>3. Job assíncrono processa linha a linha → cria <code>Marcacao</code> com hash encadeado.</p>
            <p>4. Você pode acompanhar o progresso na tela do item.</p>
          </AlertDescription>
        </Alert>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Upload do arquivo</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div>
                <Label htmlFor="tipo">Tipo *</Label>
                <Select value={form.data.tipo} onValueChange={(v) => form.setData('tipo', v)}>
                  <SelectTrigger id="tipo"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="AFD">AFD (Arquivo Fonte de Dados)</SelectItem>
                    <SelectItem value="AFDT">AFDT (Arquivo Fonte de Dados Tratados)</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div>
                <Label htmlFor="arquivo">Arquivo .txt *</Label>
                <Input
                  id="arquivo"
                  type="file"
                  accept=".txt"
                  onChange={(e) => form.setData('arquivo', e.target.files?.[0] ?? null)}
                />
                {form.data.arquivo && (
                  <p className="text-xs text-muted-foreground mt-1">
                    {form.data.arquivo.name} ({(form.data.arquivo.size / 1024).toFixed(1)} KB)
                  </p>
                )}
                {form.errors.arquivo && <p className="text-xs text-destructive mt-1">{form.errors.arquivo}</p>}

                {form.progress && (
                  <div className="mt-2">
                    <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                      <div className="h-full bg-primary transition-all" style={{ width: `${form.progress.percentage}%` }} />
                    </div>
                    <p className="text-[10px] text-muted-foreground mt-1">
                      Enviando {form.progress.percentage}%
                    </p>
                  </div>
                )}
              </div>

              <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" asChild>
                  <a href="/ponto/importacoes">Cancelar</a>
                </Button>
                <Button type="submit" disabled={form.processing || !form.data.arquivo} className="gap-1.5">
                  <Upload size={14} />
                  {form.processing ? 'Enviando…' : 'Enviar e processar'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
