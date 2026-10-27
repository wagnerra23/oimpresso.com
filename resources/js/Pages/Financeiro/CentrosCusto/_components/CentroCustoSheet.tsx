import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { toast } from 'sonner';

export interface CentroCusto {
  id: number;
  codigo: string | null;
  nome: string;
}

interface Props {
  centro: CentroCusto | null;
  onClose: () => void;
}

/** Criar/editar centro de custo (US-FIN-075). Só código e nome; situação muda na lista. */
export function CentroCustoSheet({ centro, onClose }: Props) {
  const editando = centro !== null;
  const form = useForm({ codigo: centro?.codigo ?? '', nome: centro?.nome ?? '' });

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    const opts = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(editando ? 'Centro de custo atualizado' : 'Centro de custo criado');
        onClose();
      },
      onError: () => toast.error('Confira os campos destacados'),
    };
    if (editando) form.put(`/financeiro/centros-custo/${centro.id}`, opts);
    else form.post('/financeiro/centros-custo', opts);
  };

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-md overflow-y-auto p-0">
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <SheetTitle asChild>
              <h2 className="m-0">{editando ? 'Editar centro de custo' : 'Novo centro de custo'}</h2>
            </SheetTitle>
            <SheetDescription asChild>
              <p>O código é opcional e aparece nos relatórios ao lado do nome.</p>
            </SheetDescription>
          </div>
        </header>

        <form onSubmit={salvar} className="os-drawer-body p-5 space-y-5">
          <div>
            <Label htmlFor="cc-codigo">Código</Label>
            <Input
              id="cc-codigo"
              value={form.data.codigo}
              onChange={(e) => form.setData('codigo', e.target.value.toUpperCase())}
              maxLength={20}
              placeholder="ADM"
              className="font-mono"
            />
            {form.errors.codigo && <p className="text-xs text-destructive mt-1">{form.errors.codigo}</p>}
          </div>
          <div>
            <Label htmlFor="cc-nome">Nome *</Label>
            <Input
              id="cc-nome"
              value={form.data.nome}
              onChange={(e) => form.setData('nome', e.target.value)}
              maxLength={100}
              placeholder="Administrativo"
              autoFocus
            />
            {form.errors.nome && <p className="text-xs text-destructive mt-1">{form.errors.nome}</p>}
          </div>
          <div className="flex justify-end gap-2 pt-4 border-t sticky bottom-0 bg-background">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" disabled={form.processing}>
              {form.processing ? 'Salvando…' : 'Salvar'}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
