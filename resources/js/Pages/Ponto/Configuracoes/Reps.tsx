// @docvault
//   tela: /ponto/configuracoes/reps
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-005
//   rules: R-PONT-001, R-PONT-006
//   tests: Modules/PontoWr2/Tests/Feature/ConfiguracoesRepsTest

import AppShell from '@/Layouts/AppShell';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, Plus, Server } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

interface Rep {
  id: number;
  tipo: string;
  identificador: string;
  descricao: string | null;
  local: string | null;
  cnpj: string | null;
  ativo: boolean;
}

interface Props {
  reps: {
    data: Rep[];
    total: number;
    current_page: number;
    last_page: number;
  };
}

export default function ReposIndex({ reps }: Props) {
  const form = useForm({
    tipo: 'REP_P',
    identificador: '',
    descricao: '',
    local: '',
    cnpj: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/ponto/configuracoes/reps', {
      onSuccess: () => {
        toast.success('REP cadastrado.');
        form.reset();
      },
      onError: () => toast.error('Verifique os campos.'),
    });
  };

  return (
    <>
      <Head title="REPs" />
      <div className="mx-auto max-w-5xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <Server size={22} /> REPs — Registradores Eletrônicos
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Cadastro dos dispositivos (REP-P, REP-C, REP-A) conforme Portaria MTP 671/2021.
              Identificador de 17 caracteres.
            </p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <Link href="/ponto/configuracoes"><ArrowLeft size={14} className="mr-1.5" /> Configurações</Link>
          </Button>
        </header>

        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
          {/* Form de cadastro — coluna 2 (menor) */}
          <Card className="md:col-span-2">
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Plus size={16} /> Cadastrar REP
              </CardTitle>
              <CardDescription className="text-xs">
                Identificador = CNPJ (14) + sequencial (3) conforme Anexo I da Portaria.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} className="space-y-3">
                <div>
                  <Label htmlFor="tipo">Tipo *</Label>
                  <Select value={form.data.tipo} onValueChange={(v) => form.setData('tipo', v)}>
                    <SelectTrigger id="tipo"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="REP_P">REP-P (Programa)</SelectItem>
                      <SelectItem value="REP_C">REP-C (Convencional)</SelectItem>
                      <SelectItem value="REP_A">REP-A (Alternativo)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="identificador">Identificador (17 chars) *</Label>
                  <Input id="identificador" value={form.data.identificador}
                         onChange={(e) => form.setData('identificador', e.target.value.toUpperCase())}
                         maxLength={17} minLength={17}
                         placeholder="12345678000100001"
                         className="font-mono" />
                  {form.errors.identificador && <p className="text-xs text-destructive mt-1">{form.errors.identificador}</p>}
                </div>
                <div>
                  <Label htmlFor="descricao">Descrição *</Label>
                  <Input id="descricao" value={form.data.descricao}
                         onChange={(e) => form.setData('descricao', e.target.value)} maxLength={120} />
                </div>
                <div>
                  <Label htmlFor="local">Local</Label>
                  <Input id="local" value={form.data.local}
                         onChange={(e) => form.setData('local', e.target.value)} maxLength={120} />
                </div>
                <div>
                  <Label htmlFor="cnpj">CNPJ</Label>
                  <Input id="cnpj" value={form.data.cnpj}
                         onChange={(e) => form.setData('cnpj', e.target.value.replace(/\D/g, ''))}
                         maxLength={14} className="font-mono" />
                </div>
                <Button type="submit" disabled={form.processing} className="w-full">
                  {form.processing ? 'Cadastrando…' : 'Cadastrar REP'}
                </Button>
              </form>
            </CardContent>
          </Card>

          {/* Lista — coluna 3 (maior) */}
          <Card className="md:col-span-3">
            <CardHeader>
              <CardTitle className="text-base">REPs cadastrados</CardTitle>
              <CardDescription className="text-xs">
                {reps.total} dispositivo(s)
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              {reps.data.length === 0 ? (
                <div className="p-8 text-center text-sm text-muted-foreground">
                  Nenhum REP cadastrado ainda.
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-xs">
                    <thead className="border-b border-border bg-muted/30 text-muted-foreground">
                      <tr>
                        <th className="text-left p-2 font-medium">Tipo</th>
                        <th className="text-left p-2 font-medium">Identificador</th>
                        <th className="text-left p-2 font-medium">Descrição</th>
                        <th className="text-left p-2 font-medium">Local</th>
                        <th className="text-center p-2 font-medium">Ativo</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {reps.data.map((r) => (
                        <tr key={r.id} className="hover:bg-accent/30">
                          <td className="p-2">
                            <Badge variant="outline" className="text-[10px]">{r.tipo}</Badge>
                          </td>
                          <td className="p-2 font-mono">{r.identificador}</td>
                          <td className="p-2">{r.descricao ?? '—'}</td>
                          <td className="p-2 text-muted-foreground">{r.local ?? '—'}</td>
                          <td className="p-2 text-center">
                            {r.ativo ? <Badge className="text-[10px]">Ativo</Badge> : <span className="text-muted-foreground">—</span>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

ReposIndex.layout = (page: ReactNode) => (
  <AppShell breadcrumb={[
    { label: 'Ponto WR2' },
    { label: 'Configurações', href: '/ponto/configuracoes' },
    { label: 'REPs' },
  ]}>
    {page}
  </AppShell>
);
