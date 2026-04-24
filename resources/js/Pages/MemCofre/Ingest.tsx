// @memcofre
//   tela: /docs/ingest
//   module: Cofre de Memórias
//   status: implementada
//   stories: US-DOCVAULT-002
//   rules: R-DOCVAULT-002, R-DOCVAULT-003
//   adrs: 0004
//   tests: Modules/MemCofre/Tests/Feature/IngestTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
  ArrowLeft,
  Bug,
  FileText,
  Link as LinkIcon,
  MessageCircle,
  ScrollText,
  Upload,
  Image as ImageIcon,
} from 'lucide-react';
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
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

interface Option { value: string; label: string; }

interface Props {
  source_types: Option[];
  modules: Option[];
  evidence_kinds: Option[];
}

type FormData = {
  type: string;
  module_target: string;
  title: string;
  description: string;
  body_text: string;
  source_url: string;
  upload: File | null;
  create_evidence: boolean;
  evidence_kind: string;
  evidence_content: string;
};

const typeIcon = (t: string) => {
  switch (t) {
    case 'screenshot': return <ImageIcon size={14} />;
    case 'chat': return <MessageCircle size={14} />;
    case 'error': return <Bug size={14} />;
    case 'file': return <FileText size={14} />;
    case 'text': return <ScrollText size={14} />;
    case 'url': return <LinkIcon size={14} />;
    default: return null;
  }
};

export default function MemCofreIngest({ source_types, modules, evidence_kinds }: Props) {
  const form = useForm<FormData>({
    type: 'screenshot',
    module_target: '',
    title: '',
    description: '',
    body_text: '',
    source_url: '',
    upload: null,
    create_evidence: false,
    evidence_kind: 'quote',
    evidence_content: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/memcofre/ingest', {
      forceFormData: true,
      onSuccess: () => toast.success('Evidência registrada.'),
      onError: () => toast.error('Verifique os campos.'),
    });
  };

  const needsUpload = ['screenshot', 'file'].includes(form.data.type);
  const needsUrl = form.data.type === 'url';
  const needsText = ['chat', 'text', 'error'].includes(form.data.type);

  return (
    <>
      <Head title="Nova evidência" />
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <Upload size={22} /> Nova evidência
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Material bruto vira fonte da verdade funcional.
            </p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href="/docs">
              <ArrowLeft size={14} className="mr-1.5" /> Voltar
            </Link>
          </Button>
        </header>

        <Card>
          <CardHeader>
            <CardTitle className="text-base">Detalhes da fonte</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="type">Tipo *</Label>
                  <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                    <SelectTrigger id="type">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {source_types.map((t) => (
                        <SelectItem key={t.value} value={t.value}>
                          <span className="flex items-center gap-2">
                            {typeIcon(t.value)} {t.label}
                          </span>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <Label htmlFor="module_target">Módulo-alvo</Label>
                  <Select
                    value={form.data.module_target || 'NONE'}
                    onValueChange={(v) => form.setData('module_target', v === 'NONE' ? '' : v)}
                  >
                    <SelectTrigger id="module_target">
                      <SelectValue placeholder="Sem módulo" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="NONE">Sem módulo</SelectItem>
                      {modules.map((m) => (
                        <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="title">Título *</Label>
                <Input
                  id="title"
                  value={form.data.title}
                  onChange={(e) => form.setData('title', e.target.value)}
                  placeholder="Ex: Bug no cálculo de HE em feriado"
                  required
                  autoFocus
                />
                {form.errors.title && <p className="text-xs text-destructive">{form.errors.title}</p>}
              </div>

              <div className="space-y-1">
                <Label htmlFor="description">Contexto / descrição (opcional)</Label>
                <Textarea
                  id="description"
                  rows={3}
                  value={form.data.description}
                  onChange={(e) => form.setData('description', e.target.value)}
                  placeholder="O que você quer documentar com isso? Quem reportou? Quando aconteceu?"
                />
              </div>

              {needsUpload && (
                <div className="space-y-1">
                  <Label htmlFor="upload">Arquivo</Label>
                  <Input
                    id="upload"
                    type="file"
                    onChange={(e) => form.setData('upload', e.target.files?.[0] ?? null)}
                    accept={form.data.type === 'screenshot' ? 'image/*' : '*/*'}
                  />
                  {form.progress && (
                    <div className="h-1.5 bg-muted rounded overflow-hidden mt-1">
                      <div
                        className="h-full bg-primary transition-all"
                        style={{ width: `${form.progress.percentage ?? 0}%` }}
                      />
                    </div>
                  )}
                  {form.errors.upload && <p className="text-xs text-destructive">{form.errors.upload}</p>}
                </div>
              )}

              {needsUrl && (
                <div className="space-y-1">
                  <Label htmlFor="source_url">URL</Label>
                  <Input
                    id="source_url"
                    type="url"
                    value={form.data.source_url}
                    onChange={(e) => form.setData('source_url', e.target.value)}
                    placeholder="https://..."
                  />
                </div>
              )}

              {needsText && (
                <div className="space-y-1">
                  <Label htmlFor="body_text">
                    {form.data.type === 'chat' ? 'Conteúdo do chat' :
                     form.data.type === 'error' ? 'Stack trace / log do erro' :
                     'Texto'}
                  </Label>
                  <Textarea
                    id="body_text"
                    rows={8}
                    value={form.data.body_text}
                    onChange={(e) => form.setData('body_text', e.target.value)}
                    placeholder={form.data.type === 'chat' ? 'Cole o chat aqui…' : '...'}
                    className="font-mono text-xs"
                  />
                </div>
              )}

              {/* Evidência inicial (opt-in) */}
              <div className="border-t border-border pt-4 space-y-3">
                <div className="flex items-start gap-3">
                  <Switch
                    id="create-evidence"
                    checked={form.data.create_evidence}
                    onCheckedChange={(v) => form.setData('create_evidence', v)}
                  />
                  <div>
                    <Label htmlFor="create-evidence" className="cursor-pointer font-medium">
                      Criar evidência inicial
                    </Label>
                    <p className="text-xs text-muted-foreground">
                      Se você já sabe o que tirar desta fonte, anote aqui. Vai pro Inbox pra triagem.
                    </p>
                  </div>
                </div>

                {form.data.create_evidence && (
                  <div className="space-y-3 ml-10">
                    <div className="space-y-1">
                      <Label htmlFor="evidence_kind">Tipo da evidência</Label>
                      <Select
                        value={form.data.evidence_kind}
                        onValueChange={(v) => form.setData('evidence_kind', v)}
                      >
                        <SelectTrigger id="evidence_kind">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {evidence_kinds.map((k) => (
                            <SelectItem key={k.value} value={k.value}>{k.label}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-1">
                      <Label htmlFor="evidence_content">O que você observou?</Label>
                      <Textarea
                        id="evidence_content"
                        rows={3}
                        value={form.data.evidence_content}
                        onChange={(e) => form.setData('evidence_content', e.target.value)}
                        placeholder="Ex: Usuário não consegue salvar tarefa quando o campo descrição tem caracteres especiais."
                      />
                    </div>
                  </div>
                )}
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" asChild>
                  <Link href="/docs">Cancelar</Link>
                </Button>
                <Button type="submit" disabled={form.processing} className="gap-1.5">
                  <Upload size={14} /> Registrar
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

MemCofreIngest.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[
    { label: 'Cofre de Memórias', href: '/memcofre' },
    { label: 'Nova evidência' },
  ]}>
    {page}
  </AppShell>
);
