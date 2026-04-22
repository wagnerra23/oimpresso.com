import AppShell from '@/Layouts/AppShell';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, Save, UserCog } from 'lucide-react';
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
import { Switch } from '@/Components/ui/switch';

interface Colab {
  id: number;
  matricula: string | null;
  cpf: string | null;
  pis: string | null;
  nome: string;
  email: string | null;
  controla_ponto: boolean;
  usa_banco_horas: boolean;
  admissao: string | null;
  desligamento: string | null;
  escala_atual_id: number | null;
}

interface Props {
  colaborador: Colab;
  escalas: Array<{ id: number; nome: string; tipo: string }>;
}

export default function ColaboradorEdit({ colaborador, escalas }: Props) {
  const form = useForm({
    matricula:        colaborador.matricula ?? '',
    cpf:              colaborador.cpf ?? '',
    pis:              colaborador.pis ?? '',
    escala_atual_id:  colaborador.escala_atual_id ?? null,
    controla_ponto:   colaborador.controla_ponto,
    usa_banco_horas:  colaborador.usa_banco_horas,
    admissao:         colaborador.admissao ?? '',
    desligamento:     colaborador.desligamento ?? '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.put(`/ponto/colaboradores/${colaborador.id}`, {
      onSuccess: () => toast.success('Configuração atualizada.'),
      onError:   () => toast.error('Verifique os campos.'),
    });
  };

  return (
    <AppShell
      title={`Config ${colaborador.nome}`}
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Colaboradores', href: '/ponto/colaboradores' },
        { label: colaborador.nome },
      ]}
    >
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <UserCog size={22} /> Configuração de Ponto
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              <strong>{colaborador.nome}</strong> · {colaborador.email ?? '—'}
            </p>
          </div>
          <Button variant="outline" size="sm" asChild>
            <a href="/ponto/colaboradores"><ArrowLeft size={14} className="mr-1.5" /> Voltar</a>
          </Button>
        </header>

        <form onSubmit={submit}>
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Identificação</CardTitle>
              <CardDescription className="text-xs">
                Nome e email vêm do HRM (UltimatePOS). Aqui só os campos específicos de ponto.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <Label htmlFor="matricula">Matrícula</Label>
                  <Input id="matricula" value={form.data.matricula} onChange={(e) => form.setData('matricula', e.target.value)} maxLength={30} />
                </div>
                <div>
                  <Label htmlFor="cpf">CPF</Label>
                  <Input id="cpf" value={form.data.cpf} onChange={(e) => form.setData('cpf', e.target.value)} maxLength={14} />
                </div>
                <div>
                  <Label htmlFor="pis">PIS</Label>
                  <Input id="pis" value={form.data.pis} onChange={(e) => form.setData('pis', e.target.value)} maxLength={14} />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <Label htmlFor="admissao">Admissão *</Label>
                  <Input id="admissao" type="date" value={form.data.admissao} onChange={(e) => form.setData('admissao', e.target.value)} />
                  {form.errors.admissao && <p className="text-xs text-destructive mt-1">{form.errors.admissao}</p>}
                </div>
                <div>
                  <Label htmlFor="desligamento">Desligamento</Label>
                  <Input id="desligamento" type="date" value={form.data.desligamento ?? ''} onChange={(e) => form.setData('desligamento', e.target.value)} />
                </div>
                <div>
                  <Label htmlFor="escala">Escala</Label>
                  <Select value={String(form.data.escala_atual_id ?? '')} onValueChange={(v) => form.setData('escala_atual_id', v ? parseInt(v, 10) : null)}>
                    <SelectTrigger id="escala"><SelectValue placeholder="— sem escala —" /></SelectTrigger>
                    <SelectContent>
                      {escalas.map((e) => (
                        <SelectItem key={e.id} value={String(e.id)}>
                          {e.nome} ({e.tipo})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>

              <div className="flex flex-col gap-3 pt-2 border-t border-border">
                <label className="flex items-center justify-between cursor-pointer">
                  <div>
                    <p className="text-sm font-medium">Controla ponto</p>
                    <p className="text-xs text-muted-foreground">Colaborador bate ponto e participa da apuração CLT</p>
                  </div>
                  <Switch checked={form.data.controla_ponto} onCheckedChange={(v) => form.setData('controla_ponto', v)} />
                </label>
                <label className="flex items-center justify-between cursor-pointer">
                  <div>
                    <p className="text-sm font-medium">Usa banco de horas</p>
                    <p className="text-xs text-muted-foreground">HE e débitos acumulam em ledger. Exige escala com BH ativo</p>
                  </div>
                  <Switch checked={form.data.usa_banco_horas} onCheckedChange={(v) => form.setData('usa_banco_horas', v)} />
                </label>
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end gap-2 mt-4">
            <Button type="button" variant="outline" asChild>
              <a href="/ponto/colaboradores">Cancelar</a>
            </Button>
            <Button type="submit" disabled={form.processing} className="gap-1.5">
              <Save size={14} />
              {form.processing ? 'Salvando…' : 'Salvar configuração'}
            </Button>
          </div>
        </form>
      </div>
    </AppShell>
  );
}
