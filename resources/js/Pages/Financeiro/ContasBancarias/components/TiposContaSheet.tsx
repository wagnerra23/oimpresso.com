import { router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { toast } from 'sonner';
import { Pencil, Trash2 } from 'lucide-react';
import type { TipoConta } from './ContaSheet';

interface Props {
  tiposConta: TipoConta[];
  onClose: () => void;
}

// Radix Select não aceita value vazio (§5 2026-06-29).
const RAIZ = 'raiz';

/**
 * Tipos e subtipos de conta (`account_types`) — antes era a aba "Tipos de conta" da Blade `/account/account`.
 * Escreve pelo `AccountTypeController` (store/update/destroy), que valida o tipo pai na mesma empresa.
 */
export function TiposContaSheet({ tiposConta, onClose }: Props) {
  const [editandoId, setEditandoId] = useState<number | null>(null);
  const form = useForm({ name: '', parent_account_type_id: RAIZ });

  const limpar = () => {
    setEditandoId(null);
    form.reset();
    form.clearErrors();
  };

  const editar = (id: number, nome: string, pai: number | null) => {
    setEditandoId(id);
    form.setData({ name: nome, parent_account_type_id: pai ? String(pai) : RAIZ });
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({
      ...d,
      parent_account_type_id: d.parent_account_type_id === RAIZ ? null : Number(d.parent_account_type_id),
    }));
    const opcoes = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(editandoId ? 'Tipo atualizado.' : 'Tipo criado.');
        limpar();
      },
    };
    if (editandoId) form.put(`/account-types/${editandoId}`, opcoes);
    else form.post('/account-types', opcoes);
  };

  const remover = (id: number, nome: string) => {
    if (!window.confirm(`Remover o tipo "${nome}"? Os subtipos dele viram tipos sem pai.`)) return;
    router.delete(`/account-types/${id}`, { preserveScroll: true, onSuccess: () => toast.success('Tipo removido.') });
  };

  const linha = (id: number, nome: string, pai: number | null, sub: boolean) => (
    <li key={id} className={`flex items-center gap-2 py-1.5 ${sub ? 'pl-5 text-muted-foreground' : 'font-medium'}`}>
      <span className="flex-1">{nome}</span>
      <Button type="button" size="icon" variant="ghost" aria-label={`Editar tipo ${nome}`} onClick={() => editar(id, nome, pai)}>
        <Pencil className="h-4 w-4" />
      </Button>
      <Button type="button" size="icon" variant="ghost" aria-label={`Remover tipo ${nome}`} onClick={() => remover(id, nome)}>
        <Trash2 className="h-4 w-4" />
      </Button>
    </li>
  );

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-[480px] overflow-y-auto p-0" data-contract="tipos-conta-sheet">
        <div className="border-b px-5 py-4">
          <SheetTitle asChild><h2 className="m-0 text-base font-semibold">Tipos de conta</h2></SheetTitle>
          <SheetDescription className="text-xs">Agrupam as contas na lista. Um subtipo fica dentro de um tipo.</SheetDescription>
        </div>

        <div className="flex flex-col gap-4 px-5 py-4">
          {tiposConta.length === 0 ? (
            <p className="text-sm text-muted-foreground">Nenhum tipo cadastrado.</p>
          ) : (
            <ul className="divide-y text-sm">
              {tiposConta.flatMap((t) => [linha(t.id, t.nome, null, false), ...t.subtipos.map((s) => linha(s.id, s.nome, t.id, true))])}
            </ul>
          )}

          <form onSubmit={submit} className="flex flex-col gap-3 rounded-md border p-3">
            <p className="m-0 text-sm font-medium">{editandoId ? 'Editar tipo' : 'Novo tipo'}</p>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="tipo-nome">Nome <span className="text-destructive">*</span></Label>
              <Input id="tipo-nome" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required maxLength={191} />
              {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="tipo-pai">Dentro de</Label>
              <Select value={form.data.parent_account_type_id} onValueChange={(v) => form.setData('parent_account_type_id', v)}>
                <SelectTrigger id="tipo-pai"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={RAIZ}>Nenhum (é um tipo)</SelectItem>
                  {tiposConta.filter((t) => t.id !== editandoId).map((t) => (
                    <SelectItem key={t.id} value={String(t.id)}>{t.nome}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {form.errors.parent_account_type_id && <p className="text-xs text-destructive">{form.errors.parent_account_type_id}</p>}
            </div>
            <div className="flex justify-end gap-2">
              {editandoId && <Button type="button" variant="outline" onClick={limpar}>Cancelar</Button>}
              <Button type="submit" disabled={form.processing}>{editandoId ? 'Salvar tipo' : 'Adicionar tipo'}</Button>
            </div>
          </form>
        </div>
      </SheetContent>
    </Sheet>
  );
}
