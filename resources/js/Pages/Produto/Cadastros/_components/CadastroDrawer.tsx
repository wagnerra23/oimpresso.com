// Drawer de criar/editar da tela Cadastros (playbook Produto · thread 10, PR-a: Unidades e Marcas).
// Copy e campos de produto-cadastros.jsx (AbaUnidades :315-334 · AbaMarcas :430-436). O protótipo
// abre um modal central; a ficha da thread pede o drawer PT-02 — a forma é a do PT-02, o conteúdo é o do protótipo.
//
// Grava nas MESMAS rotas do modal clássico (POST /units · PUT /units/{id} · POST /brands · PUT /brands/{id}),
// com os mesmos nomes de campo. Unidade: o múltiplo vai como digitado (`1000`, `0,5`) e o num_uf do
// servidor lê; desmarcar na edição manda `define_base_unit=0` explícito (UC-PCADAP-12) — é ESTOQUE.

import { useState, type FormEvent, type ReactNode } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface UnidadeEdit { id?: number; nome: string; simbolo: string; decimal: boolean; base_id: number | null; multiplicador: string }
export interface MarcaEdit { id?: number; nome: string; descricao: string; oficina: boolean }
export type Pedido =
  | { tipo: 'unidade'; valor: UnidadeEdit; bases: { id: number; nome: string; simbolo: string }[] }
  | { tipo: 'marca'; valor: MarcaEdit; oficina: boolean };

type Resposta = { success?: boolean; msg?: string };

function Campo({ id, label, ajuda, children }: { id: string; label: string; ajuda?: string; children: ReactNode }) {
  return (
    <Stack gap={1}>
      <Label htmlFor={id}>{label}</Label>
      {children}
      {ajuda && <p className="text-xs text-muted-foreground">{ajuda}</p>}
    </Stack>
  );
}

function Chave({ id, checked, onChange, label, sub }: { id: string; checked: boolean; onChange: (v: boolean) => void; label: string; sub?: string }) {
  return (
    <Inline gap={3} align="start">
      <Switch id={id} variant="cowork" checked={checked} onCheckedChange={onChange} className="mt-0.5" />
      <Stack gap={0}>
        <Label htmlFor={id}>{label}</Label>
        {sub && <span className="text-xs text-muted-foreground">{sub}</span>}
      </Stack>
    </Inline>
  );
}

export default function CadastroDrawer({ pedido, onClose, onSalvo }: { pedido: Pedido; onClose: () => void; onSalvo: () => void }) {
  const [u, setU] = useState<UnidadeEdit>(pedido.tipo === 'unidade' ? pedido.valor : { nome: '', simbolo: '', decimal: false, base_id: null, multiplicador: '' });
  const [m, setM] = useState<MarcaEdit>(pedido.tipo === 'marca' ? pedido.valor : { nome: '', descricao: '', oficina: false });
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);

  const unidade = pedido.tipo === 'unidade';
  const id = unidade ? u.id : m.id;
  const titulo = unidade ? (id ? 'Editar unidade' : 'Nova unidade') : (id ? 'Editar marca' : 'Nova marca');
  const bases = unidade ? pedido.bases.filter((b) => b.id !== u.id) : [];
  const podeSalvar = !salvando && (unidade
    ? !!u.nome.trim() && !!u.simbolo.trim() && (!u.base_id || !!u.multiplicador.trim())
    : !!m.nome.trim());

  // Os mesmos campos que o form da Blade manda. Na criação sem múltiplo o campo nem vai (igual ao create.blade).
  const corpo = unidade
    ? {
      actual_name: u.nome.trim(), short_name: u.simbolo.trim(), allow_decimal: u.decimal ? 1 : 0,
      ...(u.base_id ? { define_base_unit: '1', base_unit_id: u.base_id, base_unit_multiplier: u.multiplicador.trim() }
        : id ? { define_base_unit: '0' } : {}),
    }
    : { name: m.nome.trim(), description: m.descricao.trim(), use_for_repair: m.oficina ? 1 : 0 };

  const salvar = async (e?: FormEvent) => {
    e?.preventDefault();
    if (!podeSalvar) return;
    setSalvando(true);
    setErro(null);
    const rota = `${unidade ? '/units' : '/brands'}${id ? `/${id}` : ''}`;
    const csrf = document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const resposta = await fetch(rota, {
      method: id ? 'PUT' : 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(corpo),
    }).then((r) => (r.ok ? (r.json() as Promise<Resposta>) : null)).catch(() => null);
    setSalvando(false);
    if (resposta?.success) onSalvo();
    else setErro(resposta?.msg ?? 'Não foi possível salvar. Nada foi alterado.');
  };

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-[760px]" data-contract="produto-cadastros-drawer">
        <SheetHeader>
          <SheetTitle>{titulo}</SheetTitle>
          <SheetDescription>{unidade
            ? 'Unidade decimal aceita quantidade fracionada (m², kg). Múltiplo de unidade base converte compra em caixa para venda em peça.'
            : 'Marca do produto — filtro do índice, relatório por marca e, quando marcada, lista de marcas de aparelho da Oficina.'}</SheetDescription>
        </SheetHeader>

        <Stack asChild gap={4} className="px-4"><form id="cadastro-drawer" onSubmit={salvar}>
          {unidade ? (
            <>
              <Grid fit="sm" gap={4}>
                <Campo id="un-nome" label="Nome *">
                  <Input id="un-nome" autoFocus value={u.nome} placeholder="Metro quadrado" onChange={(e) => setU({ ...u, nome: e.target.value })} />
                </Campo>
                <Campo id="un-simbolo" label="Símbolo *" ajuda="Como aparece na tabela, na OS e na nota.">
                  <Input id="un-simbolo" value={u.simbolo} placeholder="m²" onChange={(e) => setU({ ...u, simbolo: e.target.value })} />
                </Campo>
              </Grid>
              <Chave id="un-decimal" checked={u.decimal} onChange={(v) => setU({ ...u, decimal: v })}
                label="Aceita quantidade decimal" sub="Ligado para m² e kg; desligado para peça e caixa." />
              <Chave id="un-base" checked={!!u.base_id} onChange={(v) => setU({ ...u, base_id: v ? (bases[0]?.id ?? null) : null })}
                label="Cadastrar como múltiplo de uma unidade base" sub="Ex.: 1 caixa = 1.000 peças. A conversão vale na compra e na venda." />
              {!!u.base_id && (
                <Grid fit="sm" gap={4}>
                  <Campo id="un-mult" label="Quantidade da base">
                    <Input id="un-mult" inputMode="decimal" value={u.multiplicador} placeholder="1000" onChange={(e) => setU({ ...u, multiplicador: e.target.value })} />
                  </Campo>
                  <Campo id="un-base-id" label="Unidade base">
                    <Select value={String(u.base_id)} onValueChange={(v) => setU({ ...u, base_id: Number(v) })}>
                      <SelectTrigger id="un-base-id"><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {bases.map((b) => <SafeSelectItem key={b.id} value={String(b.id)}>{`${b.nome} (${b.simbolo})`}</SafeSelectItem>)}
                      </SelectContent>
                    </Select>
                  </Campo>
                </Grid>
              )}
            </>
          ) : (
            <>
              <Campo id="ma-nome" label="Nome da marca *">
                <Input id="ma-nome" autoFocus value={m.nome} placeholder="Vinilcor" onChange={(e) => setM({ ...m, nome: e.target.value })} />
              </Campo>
              <Campo id="ma-desc" label="Descrição curta">
                <Input id="ma-desc" value={m.descricao} placeholder="Lonas e banners." onChange={(e) => setM({ ...m, descricao: e.target.value })} />
              </Campo>
              {pedido.oficina && (
                <Chave id="ma-oficina" checked={m.oficina} onChange={(v) => setM({ ...m, oficina: v })}
                  label="Usar como marca de aparelho na Oficina" sub="Aparece na abertura de OS de reparo, além do catálogo." />
              )}
            </>
          )}
          {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}
        </form></Stack>

        <SheetFooter className="flex-row justify-end">
          <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
          <Button type="submit" form="cadastro-drawer" disabled={!podeSalvar}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
