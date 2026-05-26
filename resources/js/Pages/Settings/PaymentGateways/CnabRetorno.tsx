// Settings/PaymentGateways/CnabRetorno.tsx — Onda 4f.0 fundação CNAB
//
// Upload manual de arquivo de RETORNO CNAB (240/400) + histórico.
//
// Refs:
//  - ADR 0170-bancos-nativos-top5-drivers-separados — Onda 4f.0
//  - Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysCnabRetornoController.php
//  - Modules/PaymentGateway/Jobs/CnabRetornoProcessor.php
//
// Persona: Wagner / superadmin / owner. Sem charter F1 nesta onda (fundação
// técnica — Cowork F1 fica pra Onda 4f.cnab quando drivers concretos existirem).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, Deferred, useForm } from '@inertiajs/react';
import { useRef, type ChangeEvent, type FormEvent } from 'react';
import { ArrowLeft, Upload, FileText, CheckCircle2, XCircle, RefreshCw, Clock } from 'lucide-react';
import { Btn, PageHeader } from '../../Financeiro/Cobranca/_components/atoms';

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
  uploads: UploadRow[];
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
  // Hotfix Inertia::defer first paint.
  uploads = uploads ?? [];

  const fileRef = useRef<HTMLInputElement>(null);
  const { data, setData, post, processing, errors, reset } = useForm<{ arquivo: File | null }>({
    arquivo: null,
  });

  const onFile = (e: ChangeEvent<HTMLInputElement>) => {
    setData('arquivo', e.target.files?.[0] ?? null);
  };

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (!data.arquivo) return;
    post(`/settings/payment-gateways/${credential.id}/cnab-retorno`, {
      forceFormData: true,
      onSuccess: () => {
        reset('arquivo');
        if (fileRef.current) fileRef.current.value = '';
        router.reload({ only: ['uploads'] });
      },
    });
  };

  return (
    <div className="h-full bg-stone-50 flex flex-col font-sans pg-shell-scope">
      <PageHeader
        title="Retorno CNAB"
        breadcrumb={`Configurações · Pagamento · ${credential.gateway_key}`}
        right={
          <>
            <Btn
              variant="ghost"
              onClick={() => router.visit('/settings/payment-gateways')}
              title="Voltar"
            >
              <ArrowLeft className="h-3 w-3" />
              Voltar
            </Btn>
            <Btn
              variant="outline"
              onClick={() => router.reload({ only: ['uploads'] })}
            >
              <RefreshCw className="h-3 w-3" />
              Atualizar
            </Btn>
          </>
        }
      />

      <div className="px-6 py-5 space-y-5 max-w-5xl">
        <div className="rounded-md border border-stone-200 bg-white p-4">
          <h2 className="text-sm font-medium text-stone-900 mb-1">
            {credential.nome_display ?? credential.gateway_key} ·{' '}
            <span className="text-stone-500">{credential.ambiente}</span>
          </h2>
          <p className="text-xs text-stone-600">
            Faça upload do arquivo de retorno CNAB (240 ou 400) enviado pelo banco. O processamento
            roda em background e atualiza as cobranças (pagas, baixadas, vencidas).
          </p>
        </div>

        <form
          onSubmit={onSubmit}
          className="rounded-md border border-stone-200 bg-white p-4 space-y-3"
        >
          <label className="block text-xs font-medium text-stone-800">
            Arquivo de retorno
          </label>
          <input
            ref={fileRef}
            type="file"
            accept={limites.extensoes.map((e) => '.' + e).join(',')}
            onChange={onFile}
            className="block text-xs"
          />
          <p className="text-[11px] text-stone-500">
            Extensões aceitas: {limites.extensoes.join(', ')} · Tamanho máx:{' '}
            {Math.round(limites.tamanho_max_kb / 1024)} MB
          </p>
          {errors.arquivo ? (
            <p className="text-xs text-rose-600">{errors.arquivo}</p>
          ) : null}
          <Btn variant="primary" type="submit" disabled={!data.arquivo || processing}>
            <Upload className="h-3 w-3" />
            {processing ? 'Enviando…' : 'Enviar arquivo'}
          </Btn>
        </form>

        <div className="rounded-md border border-stone-200 bg-white overflow-hidden">
          <div className="px-4 py-2 border-b border-stone-200 bg-stone-50">
            <h3 className="text-xs font-medium text-stone-800">Uploads recentes</h3>
          </div>
          <Deferred
            data="uploads"
            fallback={
              <div className="p-6 text-center text-xs text-stone-500">
                <RefreshCw className="h-4 w-4 inline-block animate-spin" /> Carregando…
              </div>
            }
          >
            {uploads.length === 0 ? (
              <div className="p-6 text-center text-xs text-stone-500">
                <FileText className="h-4 w-4 inline-block" /> Nenhum upload ainda.
              </div>
            ) : (
              <table className="w-full text-xs">
                <thead className="bg-stone-50 border-b border-stone-200">
                  <tr>
                    <th className="text-left px-3 py-2 font-medium text-stone-700">Arquivo</th>
                    <th className="text-left px-3 py-2 font-medium text-stone-700">Tamanho</th>
                    <th className="text-left px-3 py-2 font-medium text-stone-700">Enviado</th>
                    <th className="text-left px-3 py-2 font-medium text-stone-700">Processado</th>
                    <th className="text-right px-3 py-2 font-medium text-stone-700">Pagas</th>
                    <th className="text-right px-3 py-2 font-medium text-stone-700">Canceladas</th>
                    <th className="text-right px-3 py-2 font-medium text-stone-700">Vencidas</th>
                    <th className="text-right px-3 py-2 font-medium text-stone-700">Registradas</th>
                    <th className="text-left px-3 py-2 font-medium text-stone-700">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {uploads.map((u) => (
                    <tr key={u.id} className="border-b border-stone-100 last:border-0">
                      <td className="px-3 py-2 text-stone-800">{u.arquivo_nome_original}</td>
                      <td className="px-3 py-2 text-stone-600">{fmtBytes(u.arquivo_tamanho_bytes)}</td>
                      <td className="px-3 py-2 text-stone-600">{fmtDate(u.created_at)}</td>
                      <td className="px-3 py-2 text-stone-600">{fmtDate(u.processado_em)}</td>
                      <td className="px-3 py-2 text-right text-emerald-700">{u.qtd_paga}</td>
                      <td className="px-3 py-2 text-right text-rose-600">{u.qtd_cancelada}</td>
                      <td className="px-3 py-2 text-right text-amber-600">{u.qtd_vencida}</td>
                      <td className="px-3 py-2 text-right text-stone-700">{u.qtd_registrada}</td>
                      <td className="px-3 py-2">
                        {u.processado_em ? (
                          u.erros.length > 0 ? (
                            <span className="inline-flex items-center gap-1 text-rose-600" title={u.erros.join('\n')}>
                              <XCircle className="h-3 w-3" /> {u.erros.length} erro(s)
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-emerald-700">
                              <CheckCircle2 className="h-3 w-3" /> OK
                            </span>
                          )
                        ) : (
                          <span className="inline-flex items-center gap-1 text-stone-500">
                            <Clock className="h-3 w-3" /> Pendente
                          </span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Deferred>
        </div>
      </div>
    </div>
  );
}

CnabRetornoPage.layout = (page: React.ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default CnabRetornoPage;
