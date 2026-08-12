// Settings/PaymentGateways/CnabRetorno.tsx — Onda 4f.0 fundação CNAB (DS v4)
//
// Upload manual de arquivo de RETORNO CNAB (240/400) + histórico de uploads.
// Migrado pra Design System v4: PageHeader/EmptyState canon (@/Components/shared),
// primitivas Card/Button/Badge (@/Components/ui), tokens semânticos (sem cor crua),
// dropzone drag&drop com preview de nome/tamanho e erros expansíveis por linha.
//
// Contrato backend (NÃO chutar — fonte de verdade):
//   Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysCnabRetornoController.php
//   props: credential{id,gateway_key,nome_display,ambiente,ativo} ·
//          uploads[] (deferred) · limites{tamanho_max_kb,extensoes}
//   rota POST: /settings/payment-gateways/{credential.id}/cnab-retorno (campo "arquivo")
//
// Refs:
//  - ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
//  - Modules/PaymentGateway/Jobs/CnabRetornoProcessor.php
//  - CnabRetorno.charter.md (draft — ao lado)
//
// Persona: Wagner / superadmin / owner.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, Deferred, useForm } from '@inertiajs/react';
import {
  useCallback, useMemo, useRef, useState,
  type ChangeEvent, type DragEvent, type FormEvent, type ReactNode,
} from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';

interface CredentialInfo {
  id: number;
  gateway_key: string;
  nome_display: string | null;
  ambiente: string;
  ativo: boolean;
}

interface UploadRow {
  id: number;
  arquivo_nome_original: string;
  arquivo_tamanho_bytes: number;
  processado_em: string | null;
  qtd_paga: number;
  qtd_cancelada: number;
  qtd_vencida: number;
  qtd_registrada: number;
  erros: string[];
  created_at: string;
}

interface Limites {
  tamanho_max_kb: number;
  extensoes: string[];
}

interface Props {
  credential: CredentialInfo;
  uploads?: UploadRow[];
  limites: Limites;
}

function fmtBytes(b: number): string {
  if (b < 1024) return `${b} B`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / (1024 * 1024)).toFixed(2)} MB`;
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  try {
    return new Date(iso).toLocaleString('pt-BR');
  } catch {
    return iso;
  }
}

function CnabRetornoPage({ credential, uploads, limites }: Props) {
  const fileRef = useRef<HTMLInputElement>(null);
  const [dragOver, setDragOver] = useState(false);
  const { data, setData, post, processing, errors, reset } = useForm<{ arquivo: File | null }>({
    arquivo: null,
  });

  const acceptExt = useMemo(
    () => limites.extensoes.map((e) => '.' + e).join(','),
    [limites.extensoes],
  );

  const clearFile = useCallback(() => {
    reset('arquivo');
    if (fileRef.current) fileRef.current.value = '';
  }, [reset]);

  const onFile = useCallback(
    (e: ChangeEvent<HTMLInputElement>) => setData('arquivo', e.target.files?.[0] ?? null),
    [setData],
  );

  const onDrop = useCallback(
    (e: DragEvent<HTMLLabelElement>) => {
      e.preventDefault();
      setDragOver(false);
      const file = e.dataTransfer.files?.[0];
      if (file) setData('arquivo', file);
    },
    [setData],
  );

  const onSubmit = useCallback(
    (e: FormEvent) => {
      e.preventDefault();
      if (!data.arquivo) return;
      post(`/settings/payment-gateways/${credential.id}/cnab-retorno`, {
        forceFormData: true,
        onSuccess: () => {
          clearFile();
          router.reload({ only: ['uploads'] });
        },
      });
    },
    [data.arquivo, post, credential.id, clearFile],
  );

  return (
    <div className="mx-auto max-w-5xl px-6 py-6">
      <PageHeader
        icon="upload"
        title="Retorno CNAB"
        description={`Configurações · Pagamento · ${credential.gateway_key}`}
        action={
          <div className="flex items-center gap-2">
            <Button variant="ghost" size="sm" onClick={() => router.visit('/settings/payment-gateways')}>
              Voltar
            </Button>
            <Button variant="outline" size="sm" onClick={() => router.reload({ only: ['uploads'] })}>
              Atualizar
            </Button>
          </div>
        }
      />

      <div className="mt-6 space-y-5">
        <Card className="p-4">
          <h2 className="text-sm font-medium text-foreground">
            {credential.nome_display ?? credential.gateway_key}{' '}
            <span className="text-muted-foreground">· {credential.ambiente}</span>
          </h2>
          <p className="mt-1 text-xs text-muted-foreground">
            Faça upload do arquivo de retorno CNAB (240 ou 400) enviado pelo banco. O processamento
            roda em background e atualiza as cobranças (pagas, baixadas, vencidas).
          </p>
        </Card>

        <Card className="p-4">
          <form onSubmit={onSubmit}>
            <label
              onDragOver={(e) => {
                e.preventDefault();
                setDragOver(true);
              }}
              onDragLeave={() => setDragOver(false)}
              onDrop={onDrop}
              className={`flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-10 text-center transition-colors ${
                dragOver
                  ? 'border-primary bg-primary/5'
                  : 'border-border bg-muted/40 hover:border-primary/60'
              }`}
            >
              <input
                ref={fileRef}
                type="file"
                accept={acceptExt}
                onChange={onFile}
                className="sr-only"
              />
              <span className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                <UploadGlyph />
              </span>
              <p className="text-sm font-medium text-foreground">
                Arraste o arquivo aqui ou <span className="text-primary">clique para selecionar</span>
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                Extensões: {limites.extensoes.join(', ')} · até {Math.round(limites.tamanho_max_kb / 1024)} MB
              </p>
            </label>

            {data.arquivo && (
              <div className="mt-4 flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-4 py-3">
                <div className="flex min-w-0 items-center gap-3 text-muted-foreground">
                  <FileGlyph />
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-foreground">{data.arquivo.name}</p>
                    <p className="text-xs text-muted-foreground">{fmtBytes(data.arquivo.size)}</p>
                  </div>
                </div>
                <Button type="button" variant="ghost" size="sm" onClick={clearFile}>
                  Remover
                </Button>
              </div>
            )}

            {errors.arquivo && (
              <p className="mt-2 text-sm text-destructive">{errors.arquivo}</p>
            )}

            <Button type="submit" disabled={!data.arquivo || processing} className="mt-4">
              {processing ? 'Enviando…' : 'Enviar arquivo'}
            </Button>
          </form>
        </Card>

        <div>
          <h3 className="mb-3 text-sm font-semibold text-foreground">Uploads recentes</h3>
          <Deferred data="uploads" fallback={<UploadsSkeleton />}>
            <UploadsTable uploads={uploads} />
          </Deferred>
        </div>
      </div>
    </div>
  );
}

function UploadsTable({ uploads }: { uploads?: UploadRow[] }) {
  const rows = useMemo(() => uploads ?? [], [uploads]);

  if (rows.length === 0) {
    return (
      <EmptyState
        icon="file-text"
        title="Nenhum upload ainda"
        description="Envie um arquivo de retorno acima para ver o histórico de conciliação aqui."
      />
    );
  }

  return (
    <Card className="overflow-hidden p-0">
      <div className="overflow-x-auto">
        <table className="w-full text-xs tabular-nums">
          <thead className="border-b border-border bg-muted/40 text-left text-[10px] uppercase tracking-widest text-muted-foreground">
            <tr>
              <th className="px-3 py-2 font-medium">Arquivo</th>
              <th className="px-3 py-2 font-medium">Tamanho</th>
              <th className="px-3 py-2 font-medium">Enviado</th>
              <th className="px-3 py-2 font-medium">Processado</th>
              <th className="px-3 py-2 text-right font-medium">Pagas</th>
              <th className="px-3 py-2 text-right font-medium">Canceladas</th>
              <th className="px-3 py-2 text-right font-medium">Vencidas</th>
              <th className="px-3 py-2 text-right font-medium">Registradas</th>
              <th className="px-3 py-2 font-medium">Status</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {rows.map((u) => (
              <UploadRowItem key={u.id} upload={u} />
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}

function UploadRowItem({ upload }: { upload: UploadRow }) {
  const [open, setOpen] = useState(false);
  const errorCount = upload.erros?.length ?? 0;
  const done = !!upload.processado_em;

  return (
    <>
      <tr className="hover:bg-muted/40">
        <td className="px-3 py-2 font-medium text-foreground">{upload.arquivo_nome_original}</td>
        <td className="px-3 py-2 text-muted-foreground">{fmtBytes(upload.arquivo_tamanho_bytes)}</td>
        <td className="px-3 py-2 text-muted-foreground">{fmtDate(upload.created_at)}</td>
        <td className="px-3 py-2 text-muted-foreground">{fmtDate(upload.processado_em)}</td>
        <td className="px-3 py-2 text-right text-foreground">{upload.qtd_paga}</td>
        <td className="px-3 py-2 text-right text-foreground">{upload.qtd_cancelada}</td>
        <td className="px-3 py-2 text-right text-foreground">{upload.qtd_vencida}</td>
        <td className="px-3 py-2 text-right text-foreground">{upload.qtd_registrada}</td>
        <td className="px-3 py-2">
          {!done ? (
            <Badge variant="secondary">Pendente</Badge>
          ) : errorCount > 0 ? (
            <div className="flex items-center gap-2">
              <Badge variant="destructive">{errorCount} erro(s)</Badge>
              <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="text-[11px] font-medium text-destructive underline-offset-2 hover:underline"
                aria-expanded={open}
              >
                {open ? 'Ocultar' : 'Ver'}
              </button>
            </div>
          ) : (
            <Badge variant="outline">
              <CheckGlyph />
              OK
            </Badge>
          )}
        </td>
      </tr>
      {open && errorCount > 0 && (
        <tr>
          <td colSpan={9} className="bg-destructive/5 px-3 py-3">
            <ul className="list-inside list-disc space-y-1 text-[11px] text-destructive">
              {upload.erros.map((err, i) => (
                <li key={i}>{err}</li>
              ))}
            </ul>
          </td>
        </tr>
      )}
    </>
  );
}

function UploadsSkeleton() {
  return (
    <Card className="p-4">
      <div className="space-y-3" aria-hidden="true">
        {[0, 1, 2].map((i) => (
          <div key={i} className="h-9 animate-pulse rounded-md bg-muted" />
        ))}
      </div>
    </Card>
  );
}

function UploadGlyph() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
      <path d="M12 16V4m0 0L7 9m5-5 5 5" />
      <path d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" />
    </svg>
  );
}

function FileGlyph() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.6} strokeLinecap="round" strokeLinejoin="round" className="h-9 w-9" aria-hidden="true">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
      <path d="M14 2v6h6" />
    </svg>
  );
}

function CheckGlyph() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="m20 6-11 11-5-5" />
    </svg>
  );
}

CnabRetornoPage.layout = (page: ReactNode) => (
  <AppShellV2
    title="Configurações — Retorno CNAB"
    breadcrumbItems={[
      { label: 'Configurações', href: '/settings' },
      { label: 'Gateways', href: '/settings/payment-gateways' },
      { label: 'Retorno CNAB' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default CnabRetornoPage;
