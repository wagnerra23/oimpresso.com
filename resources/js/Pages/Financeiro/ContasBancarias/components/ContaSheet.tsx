import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { toast } from 'sonner';
import { Plus, X } from 'lucide-react';

export interface TipoConta {
  id: number;
  nome: string;
  subtipos: { id: number; nome: string }[];
}

export interface ContaCadastro {
  id: number;
  name: string;
  account_number: string | null;
  account_type_id: number | null;
  note: string | null;
  account_details: { label?: string; value?: string }[];
}

interface Props {
  /** null = nova conta. */
  conta: ContaCadastro | null;
  tiposConta: TipoConta[];
  onClose: () => void;
}

// Radix Select não aceita value vazio (§5 2026-06-29): "sem tipo" vira sentinela.
const SEM_TIPO = 'sem-tipo';

/**
 * Cadastro da conta do núcleo (`accounts`) — tela única de contas desde 2026-10-27, decisão [W].
 * O saldo de abertura vai como TEXTO digitado: o servidor converte com o mesmo `num_uf` do cadastro antigo
 * (regra mestre de valor — a tela não interpreta número).
 */
export function ContaSheet({ conta, tiposConta, onClose }: Props) {
  const nova = conta === null;
  const form = useForm({
    name: conta?.name ?? '',
    account_number: conta?.account_number ?? '',
    account_type_id: conta?.account_type_id ? String(conta.account_type_id) : SEM_TIPO,
    opening_balance: '',
    note: conta?.note ?? '',
    account_details: (conta?.account_details?.length ? conta.account_details : [{ label: '', value: '' }]).map((d) => ({
      label: d.label ?? '',
      value: d.value ?? '',
    })),
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.transform((d) => ({
      ...d,
      account_type_id: d.account_type_id === SEM_TIPO ? null : Number(d.account_type_id),
      ...(nova ? {} : { opening_balance: undefined }),
    }));
    const opcoes = {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(nova ? 'Conta criada.' : 'Conta atualizada.');
        onClose();
      },
      onError: () => toast.error('Confira os campos destacados.'),
    };
    if (nova) form.post('/financeiro/contas-bancarias', opcoes);
    else form.put(`/financeiro/contas-bancarias/${conta.id}/cadastro`, opcoes);
  };

  const detalhe = (i: number, campo: 'label' | 'value', valor: string) =>
    form.setData('account_details', form.data.account_details.map((d, j) => (j === i ? { ...d, [campo]: valor } : d)));

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full sm:max-w-[480px] overflow-y-auto p-0" data-contract="conta-sheet">
        <form onSubmit={submit} className="flex min-h-full flex-col">
          <div className="border-b px-5 py-4">
            <SheetTitle asChild><h2 className="m-0 text-base font-semibold">{nova ? 'Nova conta' : 'Editar conta'}</h2></SheetTitle>
            <SheetDescription className="text-xs">
              {nova ? 'A conta passa a aparecer nos pagamentos e no extrato.' : 'Editar não muda o saldo da conta.'}
            </SheetDescription>
          </div>

          <div className="flex flex-1 flex-col gap-4 px-5 py-4">
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="conta-nome">Nome <span className="text-destructive">*</span></Label>
              <Input id="conta-nome" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required maxLength={191} />
              {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="conta-tipo">Tipo de conta</Label>
              <Select value={form.data.account_type_id} onValueChange={(v) => form.setData('account_type_id', v)}>
                <SelectTrigger id="conta-tipo"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={SEM_TIPO}>Sem tipo</SelectItem>
                  {tiposConta.map((t) => (
                    <SelectGroup key={t.id}>
                      <SelectLabel>{t.nome}</SelectLabel>
                      <SelectItem value={String(t.id)}>{t.nome}</SelectItem>
                      {t.subtipos.map((s) => (
                        <SelectItem key={s.id} value={String(s.id)}>{t.nome} · {s.nome}</SelectItem>
                      ))}
                    </SelectGroup>
                  ))}
                </SelectContent>
              </Select>
              {form.errors.account_type_id && <p className="text-xs text-destructive">{form.errors.account_type_id}</p>}
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="conta-numero">Número da conta</Label>
              <Input id="conta-numero" className="font-mono" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} maxLength={191} />
            </div>

            {nova && (
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="conta-abertura">Saldo de abertura</Label>
                <Input id="conta-abertura" inputMode="decimal" className="font-mono text-right" placeholder="0,00" value={form.data.opening_balance} onChange={(e) => form.setData('opening_balance', e.target.value)} maxLength={30} />
                <p className="text-xs text-muted-foreground">Só na criação. Entra como crédito "saldo de abertura" na data de hoje.</p>
                {form.errors.opening_balance && <p className="text-xs text-destructive">{form.errors.opening_balance}</p>}
              </div>
            )}

            <fieldset className="flex flex-col gap-2">
              <legend className="mb-1.5 text-sm font-medium">Detalhes da conta</legend>
              {form.data.account_details.map((d, i) => (
                <div key={i} className="grid grid-cols-[1fr_1fr_auto] gap-2">
                  <Input aria-label={`Rótulo ${i + 1}`} placeholder="Rótulo (ex.: Agência)" value={d.label} onChange={(e) => detalhe(i, 'label', e.target.value)} maxLength={191} />
                  <Input aria-label={`Valor ${i + 1}`} placeholder="Valor" value={d.value} onChange={(e) => detalhe(i, 'value', e.target.value)} maxLength={191} />
                  <Button type="button" variant="ghost" size="icon" aria-label={`Remover detalhe ${i + 1}`} onClick={() => form.setData('account_details', form.data.account_details.filter((_, j) => j !== i))}>
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              ))}
              <Button type="button" variant="outline" size="sm" className="self-start" disabled={form.data.account_details.length >= 20} onClick={() => form.setData('account_details', [...form.data.account_details, { label: '', value: '' }])}>
                <Plus className="h-3.5 w-3.5" /> Adicionar detalhe
              </Button>
            </fieldset>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="conta-obs">Observação</Label>
              <Textarea id="conta-obs" rows={3} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={1000} />
            </div>
          </div>

          <div className="flex justify-end gap-2 border-t px-5 py-3">
            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={form.processing}>{nova ? 'Salvar conta' : 'Salvar alterações'}</Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
