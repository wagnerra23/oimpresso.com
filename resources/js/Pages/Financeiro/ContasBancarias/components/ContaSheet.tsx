// Nova conta / Editar conta — cadastro da conta do núcleo (`accounts`) na tela única de contas (decisão [W]
// 2026-10-27, opção A). Grava pelos endpoints do ContaBancariaController (UC-CTB-06..09):
//   POST /financeiro/contas-bancarias            → criar (com saldo de abertura opcional)
//   PUT  /financeiro/contas-bancarias/{id}/cadastro → editar (nunca toca no saldo)
//
// VALOR (regra mestre): o saldo de abertura sai com 2 casas e vírgula decimal ("1234,56"), nunca float cru;
// o servidor converte pelo mesmo `num_uf` do cadastro Blade. A tela não soma nem formata saldo.

import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { toast } from 'sonner';
import { Plus, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Inline, Stack } from '@/Components/layout';
import { totalParaEnvio } from '@/Pages/Financeiro/Despesas/_lib/total-envio';

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
  account_details: { label?: string | null; value?: string | null }[];
}

const SEM_TIPO = '__sem_tipo';

export function ContaSheet({ conta, tipos, onClose }: {
  /** null = Nova conta. */
  conta: ContaCadastro | null;
  tipos: TipoConta[];
  onClose: () => void;
}) {
  const nova = conta === null;
  const form = useForm({
    name: conta?.name ?? '',
    account_type_id: conta?.account_type_id ? String(conta.account_type_id) : '',
    account_number: conta?.account_number ?? '',
    saldo: 0,
    account_details: (conta?.account_details?.length ? conta.account_details : [{ label: '', value: '' }])
      .map((d) => ({ label: d.label ?? '', value: d.value ?? '' })),
    note: conta?.note ?? '',
  });

  // O servidor valida `opening_balance` (nome do campo enviado), não `saldo` (estado local da tela).
  const erroSaldo = (form.errors as Record<string, string | undefined>).opening_balance;

  const detalhe = (i: number, campo: 'label' | 'value', v: string) =>
    form.setData('account_details', form.data.account_details.map((d, j) => (j === i ? { ...d, [campo]: v } : d)));

  function salvar(e: FormEvent) {
    e.preventDefault();
    form.transform((d) => ({
      name: d.name,
      account_type_id: d.account_type_id || null,
      account_number: d.account_number || null,
      note: d.note || null,
      account_details: d.account_details,
      ...(nova && d.saldo ? { opening_balance: totalParaEnvio(d.saldo) } : {}),
    }));
    const opcoes = {
      preserveScroll: true,
      onSuccess: () => { toast.success(nova ? 'Conta criada.' : 'Conta atualizada.'); onClose(); },
      onError: () => toast.error('Confira os campos destacados.'),
    };
    if (nova) form.post('/financeiro/contas-bancarias', opcoes);
    else form.put(`/financeiro/contas-bancarias/${conta.id}/cadastro`, opcoes);
  }

  return (
    <Sheet open onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent side="right" className="w-full sm:max-w-[480px] gap-0 p-0">
        <Stack asChild gap={0} className="h-full">
          <form onSubmit={salvar}>
          <header className="px-5 py-4 border-b">
            <SheetTitle>{nova ? 'Nova conta' : 'Editar conta'}</SheetTitle>
            <SheetDescription>
              {nova ? 'Cadastro da conta; dados de boleto ficam em "Configurar boleto".' : 'Muda o cadastro; o saldo não é alterado.'}
            </SheetDescription>
          </header>

          <Stack gap={4} className="flex-1 overflow-auto px-5 py-4">
            <Stack gap={1}>
              <Label htmlFor="cb-nome">Nome *</Label>
              <Input id="cb-nome" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required maxLength={191} />
              {form.errors.name && <p className="text-xs text-destructive">{form.errors.name}</p>}
            </Stack>

            <Stack gap={1}>
              <Label htmlFor="cb-tipo">Tipo de conta</Label>
              <Select value={form.data.account_type_id || SEM_TIPO} onValueChange={(v) => form.setData('account_type_id', v === SEM_TIPO ? '' : v)}>
                <SelectTrigger id="cb-tipo"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={SEM_TIPO}>Sem tipo</SelectItem>
                  {tipos.flatMap((t) => [
                    <SelectItem key={t.id} value={String(t.id)}>{t.nome}</SelectItem>,
                    ...t.subtipos.map((s) => <SelectItem key={s.id} value={String(s.id)}>{`${t.nome} · ${s.nome}`}</SelectItem>),
                  ])}
                </SelectContent>
              </Select>
              {form.errors.account_type_id && <p className="text-xs text-destructive">{form.errors.account_type_id}</p>}
            </Stack>

            <Stack gap={1}>
              <Label htmlFor="cb-numero">Número da conta</Label>
              <Input id="cb-numero" className="font-mono" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} maxLength={191} />
            </Stack>

            {nova && (
              <Stack gap={1}>
                <Label htmlFor="cb-saldo">Saldo de abertura</Label>
                <NumericInputPtBR id="cb-saldo" value={form.data.saldo} onChange={(v) => form.setData('saldo', v)} />
                <p className="text-xs text-muted-foreground">Só na criação. Entra como crédito "saldo de abertura" na data de hoje.</p>
                {erroSaldo && <p className="text-xs text-destructive">{erroSaldo}</p>}
              </Stack>
            )}

            <Stack asChild gap={2}>
              <fieldset>
              <legend className="text-sm font-medium mb-1">Detalhes da conta</legend>
              {form.data.account_details.map((d, i) => (
                <Inline key={i} gap={2}>
                  <Input className="min-w-0 flex-1" aria-label={`Rótulo ${i + 1}`} placeholder="Agência" value={d.label} onChange={(e) => detalhe(i, 'label', e.target.value)} />
                  <Input className="min-w-0 flex-1" aria-label={`Valor ${i + 1}`} value={d.value} onChange={(e) => detalhe(i, 'value', e.target.value)} />
                  <Button type="button" variant="ghost" size="icon" aria-label={`Remover detalhe ${i + 1}`}
                    onClick={() => form.setData('account_details', form.data.account_details.filter((_, j) => j !== i))}>
                    <X className="h-4 w-4" />
                  </Button>
                </Inline>
              ))}
              <Button type="button" variant="outline" size="sm" className="self-start"
                onClick={() => form.setData('account_details', [...form.data.account_details, { label: '', value: '' }])}>
                <Plus className="h-4 w-4" /> Adicionar detalhe
              </Button>
              </fieldset>
            </Stack>

            <Stack gap={1}>
              <Label htmlFor="cb-obs">Observação</Label>
              <Textarea id="cb-obs" rows={3} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} maxLength={1000} />
            </Stack>
          </Stack>

          <Inline asChild gap={2} justify="end" className="px-5 py-3 border-t">
            <footer>
            <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
            <Button type="submit" disabled={form.processing}>Salvar conta</Button>
            </footer>
          </Inline>
          </form>
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
