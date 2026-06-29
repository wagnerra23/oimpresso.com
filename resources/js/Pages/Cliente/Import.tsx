// W1-B3 Cliente/Import — wizard upload XLSX Inertia/React (MWART F3).
// Divergence ADR 0149: wizard upload com preview — layout não derivado do Index.
// Backend: ContactController::getImportContacts() — Inertia::render dual via config('mwart.cliente_import.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { useRef, useState, type ChangeEvent, type FormEvent, type ReactNode } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  ChevronLeft,
  Download,
  FileSpreadsheet,
  Upload,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface ClienteImportPageProps {
  zip_available: boolean;
  notification?: {
    success: number;
    msg: string;
  } | null;
}

type ImportFormData = {
  contacts_csv: File | null;
};

export default function ClienteImport(props: ClienteImportPageProps) {
  const { data, setData, post, processing, errors, progress } = useForm<ImportFormData>({
    contacts_csv: null,
  });
  const [filename, setFilename] = useState<string>('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setData('contacts_csv', file);
    setFilename(file?.name ?? '');
  };

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!data.contacts_csv) return;
    post('/contacts/import', {
      forceFormData: true,
    });
  };

  if (!props.zip_available) {
    return (
      <div className="flex-1 bg-muted/30">
        <div className="container mx-auto px-8 py-12 max-w-2xl">
          <div className="rounded-lg border border-destructive/20 bg-destructive-soft p-6 text-destructive-fg">
            <h2 className="font-semibold flex items-center gap-2">
              <AlertTriangle size={18} />
              Extensão PHP Zip indisponível
            </h2>
            <p className="text-sm mt-2">
              A importação de clientes exige a extensão PHP <code className="text-xs bg-rose-100 px-1 py-0.5 rounded">zip</code>.
              Contate o administrador do servidor pra habilitar.
            </p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="flex-1 bg-muted/30">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-3xl">
          <div className="flex items-center gap-3 mb-2">
            <a
              href="/contacts/customer"
              className="inline-flex items-center text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <ChevronLeft size={14} className="mr-1" />
              Voltar para clientes
            </a>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Importar clientes</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Carregue um arquivo XLSX com a planilha de clientes preenchida.
          </p>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-3xl">
        {props.notification && (
          <div
            className={
              'mb-6 rounded-lg border p-4 flex items-start gap-3 ' +
              (props.notification.success
                ? 'border-success/20 bg-success-soft text-success-fg'
                : 'border-destructive/20 bg-destructive-soft text-destructive-fg')
            }
          >
            {props.notification.success ? <CheckCircle2 size={18} /> : <AlertTriangle size={18} />}
            <div>
              <div className="text-sm font-medium">
                {props.notification.success ? 'Importação concluída' : 'Falha na importação'}
              </div>
              <p className="text-xs mt-1">{props.notification.msg}</p>
            </div>
          </div>
        )}

        <div className="rounded-lg border border-border bg-background p-6 mb-6">
          <div className="flex items-start gap-3">
            <Download size={20} className="text-primary flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <h3 className="text-sm font-semibold text-foreground">Passo 1 — Baixe o template</h3>
              <p className="text-xs text-muted-foreground mt-1">
                Use o modelo XLSX oficial pra garantir as 27 colunas corretas.
              </p>
              <Button asChild variant="outline" className="mt-3" size="sm">
                <a href="/uploads/sample_files/sample_contact.xlsx" download>
                  <Download className="mr-1.5 h-4 w-4" />
                  Baixar template
                </a>
              </Button>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="rounded-lg border border-border bg-background p-6">
          <div className="flex items-start gap-3 mb-4">
            <FileSpreadsheet size={20} className="text-primary flex-shrink-0 mt-0.5" />
            <div>
              <h3 className="text-sm font-semibold text-foreground">Passo 2 — Envie o arquivo</h3>
              <p className="text-xs text-muted-foreground mt-1">
                Arquivos aceitos: .xlsx, .csv. Tamanho máximo: 10 MB.
              </p>
            </div>
          </div>

          <div
            onClick={() => fileInputRef.current?.click()}
            className="border-2 border-dashed border-border rounded-lg p-8 text-center cursor-pointer hover:border-primary/40 hover:bg-primary/5 transition-colors"
          >
            <Upload size={28} className="mx-auto text-muted-foreground mb-2" />
            <p className="text-sm text-foreground">
              {filename ? (
                <span className="font-medium">{filename}</span>
              ) : (
                'Clique ou arraste o arquivo aqui'
              )}
            </p>
            <p className="text-xs text-muted-foreground mt-1">
              {filename ? 'Clique pra escolher outro' : '.xlsx ou .csv até 10 MB'}
            </p>
            <input
              ref={fileInputRef}
              type="file"
              accept=".xlsx,.xls,.csv"
              onChange={handleFileChange}
              className="hidden"
            />
          </div>

          {errors.contacts_csv && (
            <p className="text-xs text-destructive mt-2">{errors.contacts_csv}</p>
          )}

          {progress && (
            <div className="mt-4">
              <div className="h-2 bg-muted rounded-full overflow-hidden">
                <div
                  className="h-full bg-primary transition-all"
                  style={{ width: `${progress.percentage ?? 0}%` }}
                />
              </div>
              <p className="text-xs text-muted-foreground mt-1">Enviando… {progress.percentage ?? 0}%</p>
            </div>
          )}

          <div className="flex items-center justify-end gap-2 mt-6 pt-4 border-t border-border">
            <Button type="button" variant="cowork-ghost" asChild>
              <a href="/contacts/customer">Cancelar</a>
            </Button>
            <Button type="submit" variant="cowork-primary" disabled={!data.contacts_csv || processing}>
              <Upload className="mr-1.5 h-4 w-4" />
              {processing ? 'Importando…' : 'Importar'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}

ClienteImport.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
