// Drawer de cadastrar/editar função (thread sistema/playbook/02, F3-2). Forma do PT-02 (Sheet 760);
// conteúdo = os controles que a Blade `role/create|edit` renderiza para o negócio, lidos no servidor por
// App\Utils\FormularioDeFuncao — mesmos blocos, rótulos, condições por módulo e marcados.
//
// Grava nas MESMAS rotas e com os MESMOS campos do formulário da Blade: `name`, `permissions[]`,
// `spg_permissions[]`, `radio_option[<grupo>]` e `is_service_staff`. O `update()` faz `syncPermissions`
// destrutivo: o que não for mandado aqui é revogado (fora o que ele preserva por não ser ofertado).

import { useMemo, useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Inline, Stack } from '@/Components/layout';

export interface ItemDoEditor { campo: string; tipo: 'checkbox' | 'radio'; valor: string; rotulo: string; marcado: boolean }
export interface Editor { id: number | null; nome: string; grupos: { titulo: string; itens: ItemDoEditor[] }[] }

const chave = (i: ItemDoEditor) => `${i.campo}|${i.valor}`;

/** O corpo que o `<form>` da Blade mandaria com estes controles marcados. */
export function corpoDoFormulario(nome: string, itens: ItemDoEditor[], marcados: Set<string>): Record<string, unknown> {
  const permissions: string[] = [];
  const spg: string[] = [];
  const radio: Record<string, string> = {};
  let garcom = false;
  for (const i of itens) {
    if (!marcados.has(chave(i))) continue;
    if (i.campo === 'permissions[]') permissions.push(i.valor);
    else if (i.campo === 'spg_permissions[]') spg.push(i.valor);
    else if (i.campo === 'is_service_staff') garcom = true;
    else {
      const g = /^radio_option\[(.+)\]$/.exec(i.campo);
      if (g) radio[g[1]] = i.valor;
    }
  }
  return { name: nome.trim(), permissions, spg_permissions: spg, radio_option: radio, ...(garcom ? { is_service_staff: 1 } : {}) };
}

export default function FuncaoDrawer({ editor, onClose }: { editor: Editor; onClose: () => void }) {
  const itens = useMemo(() => editor.grupos.flatMap((g) => g.itens), [editor]);
  const [nome, setNome] = useState(editor.nome);
  const [marcados, setMarcados] = useState(() => new Set(itens.filter((i) => i.marcado).map(chave)));
  const [q, setQ] = useState('');
  const [salvando, setSalvando] = useState(false);
  const termo = q.trim().toLowerCase();

  const alternar = (i: ItemDoEditor, ligado: boolean) => setMarcados((m) => {
    const n = new Set(m);
    if (i.tipo === 'radio') itens.filter((x) => x.campo === i.campo).forEach((x) => n.delete(chave(x)));
    if (ligado) n.add(chave(i)); else n.delete(chave(i));
    return n;
  });
  const marcarBloco = (lista: ItemDoEditor[], ligado: boolean) => setMarcados((m) => {
    const n = new Set(m);
    lista.filter((i) => i.tipo === 'checkbox').forEach((i) => (ligado ? n.add(chave(i)) : n.delete(chave(i))));
    return n;
  });

  const salvar = (e?: FormEvent) => {
    e?.preventDefault();
    if (salvando || !nome.trim()) return;
    setSalvando(true);
    const corpo = corpoDoFormulario(nome, itens, marcados);
    const opcoes = { onSuccess: onClose, onFinish: () => setSalvando(false) };
    // store()/update() respondem redirect('roles') com status na sessão: o Inertia segue e a lista recarrega.
    if (editor.id) router.put(`/roles/${editor.id}`, corpo as never, opcoes);
    else router.post('/roles', corpo as never, opcoes);
  };

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-[760px]" data-contract="funcao-drawer">
        <SheetHeader>
          <SheetTitle>{editor.id ? 'Editar função' : 'Nova função'}</SheetTitle>
          <SheetDescription>O que quem tem esta função pode fazer no sistema. Desmarcar tira a permissão de todos os usuários da função ao salvar.</SheetDescription>
        </SheetHeader>
        <Stack asChild gap={4} className="px-4"><form id="funcao-drawer-form" onSubmit={salvar}>
          <Stack gap={1}>
            <Label htmlFor="fd-nome">Nome da função *</Label>
            <Input id="fd-nome" autoFocus value={nome} onChange={(e) => setNome(e.target.value)} placeholder="Vendedor balcão" />
          </Stack>
          <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Filtrar permissões…" aria-label="Filtrar permissões" />
          {editor.grupos.map((g, gi) => {
            const visiveis = g.itens.filter((i) => !termo || i.rotulo.toLowerCase().includes(termo) || g.titulo.toLowerCase().includes(termo));
            if (!visiveis.length) return null;
            const caixas = visiveis.filter((i) => i.tipo === 'checkbox');
            const todas = caixas.length > 0 && caixas.every((i) => marcados.has(chave(i)));
            const radios = [...new Set(visiveis.filter((i) => i.tipo === 'radio').map((i) => i.campo))];
            return (
              <section key={gi} aria-labelledby={`fd-bloco-${gi}`} className="rounded-md border p-3" data-contract="funcao-bloco">
                <Inline justify="between" gap={2}>
                  <h3 id={`fd-bloco-${gi}`} className="text-sm font-semibold">{g.titulo || 'Geral'}</h3>
                  {caixas.length > 1 && (
                    <Button type="button" variant="ghost" size="sm" onClick={() => marcarBloco(caixas, !todas)}>{todas ? 'Desmarcar todas' : 'Marcar todas'}</Button>
                  )}
                </Inline>
                <Stack gap={2} className="pt-2">
                  {caixas.map((i) => (
                    <Inline key={chave(i)} gap={2}>
                      <Checkbox id={`fd-${chave(i)}`} checked={marcados.has(chave(i))} onCheckedChange={(v) => alternar(i, v === true)} />
                      <Label htmlFor={`fd-${chave(i)}`} className="font-normal">{i.rotulo}</Label>
                    </Inline>
                  ))}
                  {radios.map((campo) => {
                    const opcoes = visiveis.filter((i) => i.campo === campo);
                    const atual = opcoes.find((i) => marcados.has(chave(i)));
                    return (
                      <RadioGroup key={campo} value={atual?.valor ?? ''} aria-label={g.titulo}
                        onValueChange={(v) => { const i = opcoes.find((x) => x.valor === v); if (i) alternar(i, true); }}>
                        {opcoes.map((i) => (
                          <Inline key={chave(i)} gap={2}>
                            <RadioGroupItem id={`fd-${chave(i)}`} value={i.valor} />
                            <Label htmlFor={`fd-${chave(i)}`} className="font-normal">{i.rotulo}</Label>
                          </Inline>
                        ))}
                      </RadioGroup>
                    );
                  })}
                </Stack>
              </section>
            );
          })}
        </form></Stack>
        <SheetFooter className="flex-row justify-end">
          <Button type="button" variant="outline" onClick={onClose}>Cancelar</Button>
          <Button type="submit" form="funcao-drawer-form" disabled={salvando || !nome.trim()}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
