// @memcofre
//   tela: /nfse/:id
//   module: NFSe
//   stories: US-NFSE-006
//   permissao: nfse.view + nfse.cancel

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Download, XCircle, AlertTriangle, RefreshCw } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';
import PageHeader from '@/Components/shared/PageHeader';
import StatusBadge from '@/Components/shared/StatusBadge';

interface NfseDetail {
  id: number;
  numero: string | null;
  status: string;
  status_label: string;
  tomador_nome: string;
  tomador_cnpj: string | null;
  tomador_cpf: string | null;
  tomador_email: string | null;
  valor_servicos: number;
  valor_iss: number | null;
  competencia: string | null;
  lc116_codigo: string | null;
  descricao: string | null;
  pdf_url: string | null;
  erro_mensagem: string | null;
  created_at: string;
}

interface Props {
  nfse: NfseDetail;
  flash?: { success: boolean; msg: string } | null;
}

const brl = (v: number | null) =>
  v != null
    ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v)
    : '—';

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-2 border-b border-border last:border-0">
      <span className="text-xs text-muted-foreground">{label}</span>
      <span className="text-sm">{value ?? '—'}</span>
    </div>
  );
}

export default function NfseShow({ nfse, flash }: Props) {
  const [cancelMotivo, setCancelMotivo] = useState('');

  const { post: cancelPost, processing: canceling } = useForm({ motivo: '' });

  function handleCancelar() {
    router.post(`/nfse/${nfse.id}/cancelar`, { motivo: cancelMotivo });
  }

  const podeReemitir = nfse.status === 'erro';

  return (
    <AppShellV2 title={`NFSe ${nfse.numero ?? '#' + nfse.id}`}>
      <Head title={`NFSe ${nfse.numero ?? '#' + nfse.id}`} />
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
          icon="file-text"
          title={`NFSe ${nfse.numero ? '#' + nfse.numero : 'em processamento'}`}
          description={`Criada em ${nfse.created_at} · Competência ${nfse.competencia ?? '—'}`}
          action={
            <Button variant="outline" size="sm" onClick={() => router.visit('/nfse')}>
              <ArrowLeft size={14} className="mr-1" />
              Listagem
            </Button>
          }
        />

        {/* Status em destaque */}
        <div className="flex items-center gap-3 rounded-lg border border-border bg-card px-4 py-3">
          <StatusBadge kind="nfse" value={nfse.status} className="text-sm" />
          {nfse.status === 'processando' && (
            <span className="text-xs text-muted-foreground flex items-center gap-1">
              <RefreshCw size={12} className="animate-spin" />
              Processando na prefeitura — atualize a página em instantes.
            </span>
          )}
          {nfse.status === 'erro' && nfse.erro_mensagem && (
            <span className="text-xs text-destructive flex items-center gap-1">
              <AlertTriangle size={12} />
              {nfse.erro_mensagem}
            </span>
          )}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {/* Tomador */}
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Tomador</CardTitle>
            </CardHeader>
            <CardContent className="space-y-0 divide-y divide-border">
              <InfoRow label="Nome" value={nfse.tomador_nome} />
              {nfse.tomador_cnpj && <InfoRow label="CNPJ" value={nfse.tomador_cnpj} />}
              {nfse.tomador_cpf && <InfoRow label="CPF" value={nfse.tomador_cpf} />}
              {nfse.tomador_email && <InfoRow label="E-mail" value={nfse.tomador_email} />}
            </CardContent>
          </Card>

          {/* Valores */}
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Valores</CardTitle>
            </CardHeader>
            <CardContent className="space-y-0 divide-y divide-border">
              <InfoRow label="Valor serviços" value={brl(nfse.valor_servicos)} />
              <InfoRow label="ISS" value={brl(nfse.valor_iss)} />
              <InfoRow label="Código LC 116" value={nfse.lc116_codigo} />
            </CardContent>
          </Card>
        </div>

        {/* Descrição */}
        {nfse.descricao && (
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Descrição do serviço</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm whitespace-pre-wrap">{nfse.descricao}</p>
            </CardContent>
          </Card>
        )}

        {/* Ações */}
        <div className="flex flex-wrap items-center gap-3 pt-2">
          {nfse.pdf_url && (
            <Button
              variant="outline"
              onClick={() => window.open(`/nfse/${nfse.id}/pdf`, '_blank')}
            >
              <Download size={15} className="mr-1.5" />
              Baixar DANFSE
            </Button>
          )}

          {podeReemitir && (
            <Button
              variant="outline"
              onClick={() => router.visit('/nfse/emitir')}
            >
              <RefreshCw size={15} className="mr-1.5" />
              Nova emissão
            </Button>
          )}

          {nfse.status === 'emitida' && (
            <AlertDialog>
              <AlertDialogTrigger asChild>
                <Button variant="destructive">
                  <XCircle size={15} className="mr-1.5" />
                  Cancelar nota
                </Button>
              </AlertDialogTrigger>
              <AlertDialogContent>
                <AlertDialogHeader>
                  <AlertDialogTitle>Cancelar NFSe?</AlertDialogTitle>
                  <AlertDialogDescription>
                    Esta ação não pode ser desfeita. A nota será cancelada na prefeitura.
                    Informe o motivo (mínimo 15 caracteres).
                  </AlertDialogDescription>
                </AlertDialogHeader>
                <div className="mt-2">
                  <Label className="text-xs">Motivo do cancelamento *</Label>
                  <Textarea
                    value={cancelMotivo}
                    onChange={(e) => setCancelMotivo(e.target.value)}
                    placeholder="Descreva o motivo do cancelamento..."
                    rows={3}
                    minLength={15}
                    maxLength={255}
                    className="mt-1 resize-none"
                  />
                  <p className="text-xs text-muted-foreground mt-0.5 text-right">
                    {cancelMotivo.length}/255
                  </p>
                </div>
                <AlertDialogFooter>
                  <AlertDialogCancel>Voltar</AlertDialogCancel>
                  <AlertDialogAction
                    onClick={handleCancelar}
                    disabled={cancelMotivo.length < 15 || canceling}
                    className="bg-destructive hover:bg-destructive/90"
                  >
                    {canceling ? 'Cancelando...' : 'Confirmar cancelamento'}
                  </AlertDialogAction>
                </AlertDialogFooter>
              </AlertDialogContent>
            </AlertDialog>
          )}
        </div>
      </div>
    </AppShellV2>
  );
}
