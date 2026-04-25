import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { toast } from 'sonner';

interface Categoria {
  id: number;
  nome: string;
  cor: string | null;
  plano_conta_id: number | null;
  tipo: 'receita' | 'despesa' | 'ambos';
  ativo: boolean;
}

interface PlanoConta {
  id: number;
  codigo: string;
  nome: string;
  natureza: string;
}

interface Props {
  categoria: Categoria | null;
  planosConta: PlanoConta[];
  onClose: () => void;
}

const COR_PRESETS = [
  '#EF4444', '#F97316', '#F59E0B', '#84CC16', '#22C55E', '#10B981',
  '#06B6D4', '#3B82F6', '#6366F1', '#8B5CF6', '#A855F7', '#EC4899',
];

export function CategoriaSheet({ categoria, planosConta, onClose }: Props) {
  const isEdit = categoria !== null;

  const form = useForm({
    nome: categoria?.nome ?? '',
    cor: categoria?.cor ?? '#3B82F6',
    plano_conta_id: categoria?.plano_conta_id?.toString() ?? '',
    tipo: categoria?.tipo ?? 'ambos',
    ativo: categoria?.ativo ?? true,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();

    const opts = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(isEdit ? 'Categoria atualizada' : 'Categoria criada');
        onClose();
      },
      onError: () => toast.error('Verifique os campos destacados'),
    };

    if (isEdit) {
      form.put(`/financeiro/categorias/${categoria.id}`, opts);
    } else {
      form.post('/financeiro/categorias', opts);
    }
  };

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-md overflow-y-auto">
        <SheetHeader className="pb-4 border-b">
          <SheetTitle>{isEdit ? 'Editar categoria' : 'Nova categoria'}</SheetTitle>
          <SheetDescription>
            Tags livres pra organizar lançamentos. Vínculo ao plano de contas é opcional.
          </SheetDescription>
        </SheetHeader>

        <form onSubmit={submit} className="space-y-5 mt-4 pb-6">
          <div>
            <Label htmlFor="nome">Nome *</Label>
            <Input
              id="nome"
              value={form.data.nome}
              onChange={(e) => form.setData('nome', e.target.value)}
              maxLength={100}
              autoFocus
            />
            {form.errors.nome && (
              <p className="text-xs text-destructive mt-1">{form.errors.nome}</p>
            )}
          </div>

          <div>
            <Label htmlFor="cor">Cor</Label>
            <div className="flex items-center gap-3 mt-1">
              <input
                id="cor"
                type="color"
                value={form.data.cor ?? '#3B82F6'}
                onChange={(e) => form.setData('cor', e.target.value.toUpperCase())}
                className="h-10 w-14 rounded border cursor-pointer"
              />
              <Input
                value={form.data.cor ?? ''}
                onChange={(e) => form.setData('cor', e.target.value.toUpperCase())}
                placeholder="#3B82F6"
                maxLength={7}
                className="font-mono"
              />
            </div>
            <div className="flex flex-wrap gap-1 mt-2">
              {COR_PRESETS.map((c) => (
                <button
                  key={c}
                  type="button"
                  onClick={() => form.setData('cor', c)}
                  className="w-6 h-6 rounded border hover:scale-110 transition-transform"
                  style={{ backgroundColor: c }}
                  title={c}
                />
              ))}
            </div>
            {form.errors.cor && (
              <p className="text-xs text-destructive mt-1">{form.errors.cor}</p>
            )}
          </div>

          <div>
            <Label htmlFor="tipo">Tipo *</Label>
            <Select
              value={form.data.tipo}
              onValueChange={(v) => form.setData('tipo', v as 'receita' | 'despesa' | 'ambos')}
            >
              <SelectTrigger id="tipo">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="receita">Receita</SelectItem>
                <SelectItem value="despesa">Despesa</SelectItem>
                <SelectItem value="ambos">Ambos</SelectItem>
              </SelectContent>
            </Select>
            {form.errors.tipo && (
              <p className="text-xs text-destructive mt-1">{form.errors.tipo}</p>
            )}
          </div>

          <div>
            <Label htmlFor="plano_conta_id">Plano de contas (opcional)</Label>
            <Select
              value={form.data.plano_conta_id || 'none'}
              onValueChange={(v) => form.setData('plano_conta_id', v === 'none' ? '' : v)}
            >
              <SelectTrigger id="plano_conta_id">
                <SelectValue placeholder="Sem vínculo" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">Sem vínculo</SelectItem>
                {planosConta.map((p) => (
                  <SelectItem key={p.id} value={p.id.toString()}>
                    {p.codigo} — {p.nome}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {form.errors.plano_conta_id && (
              <p className="text-xs text-destructive mt-1">{form.errors.plano_conta_id}</p>
            )}
          </div>

          <div className="border-t pt-4 flex items-center gap-3">
            <Switch
              id="ativo"
              checked={form.data.ativo}
              onCheckedChange={(c) => form.setData('ativo', c)}
            />
            <Label htmlFor="ativo" className="cursor-pointer">
              Categoria ativa
            </Label>
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
