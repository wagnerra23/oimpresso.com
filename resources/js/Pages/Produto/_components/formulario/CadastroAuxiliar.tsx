// "+" de unidade e de marca no cadastro de produto (UC-PCAD-28 · UC-PEDIT-28), como no protótipo
// (produto-blade-forms.jsx: "Cadastro rápido de unidade/marca abre em modal") e no Blade
// (create.blade.php: botão "+" desabilitado sem `unit.create` / `brand.create`).
//
// Grava pelas rotas de sempre (UnitController@store, BrandController@store), que já respondem JSON e
// checam a permissão e a empresa. A tela não guarda lista própria: depois de gravar, recarrega só a
// lista de unidades ou de marcas e já escolhe o item novo no produto.

import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Grid, Inline, Stack } from '@/Components/layout';

export type TipoAuxiliar = 'unidade' | 'marca';

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** O pedido de cada tipo, no formato que UnitController@store e BrandController@store leem. */
export function pedidoDoAuxiliar(
  tipo: TipoAuxiliar,
  campos: { nome: string; abreviacao: string; decimal: boolean; descricao: string },
): { url: string; corpo: Record<string, unknown> } {
  return tipo === 'unidade'
    ? {
        url: '/units',
        corpo: { actual_name: campos.nome.trim(), short_name: campos.abreviacao.trim(), allow_decimal: campos.decimal ? 1 : 0 },
      }
    : { url: '/brands', corpo: { name: campos.nome.trim(), description: campos.descricao.trim() } };
}

export async function enviarAuxiliar(url: string, corpo: Record<string, unknown>): Promise<{ id: number } | { erro: string }> {
  const falha = 'Não foi possível gravar.';
  try {
    const r = await fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(corpo),
    });
    const j = (await r.json().catch(() => null)) as { success?: boolean; msg?: string; data?: { id?: number } } | null;
    if (r.ok && j?.success === true && typeof j.data?.id === 'number') return { id: j.data.id };
    return { erro: j?.msg ?? falha };
  } catch {
    return { erro: falha };
  }
}

export function CadastroAuxiliar({
  tipo,
  aoFechar,
  aoCriar,
  container,
}: {
  tipo: TipoAuxiliar | null;
  aoFechar: () => void;
  aoCriar: (tipo: TipoAuxiliar, id: number) => void;
  container?: HTMLElement | null;
}) {
  const [nome, setNome] = useState('');
  const [abreviacao, setAbreviacao] = useState('');
  const [decimal, setDecimal] = useState(false);
  const [descricao, setDescricao] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [gravando, setGravando] = useState(false);

  const limpar = () => {
    setNome('');
    setAbreviacao('');
    setDecimal(false);
    setDescricao('');
    setErro(null);
  };
  const unidade = tipo === 'unidade';
  const falta = nome.trim() === '' || (unidade && abreviacao.trim() === '');

  const gravar = async () => {
    if (!tipo || falta) return;
    setGravando(true);
    setErro(null);
    const { url, corpo } = pedidoDoAuxiliar(tipo, { nome, abreviacao, decimal, descricao });
    const r = await enviarAuxiliar(url, corpo);
    setGravando(false);
    if ('erro' in r) {
      setErro(r.erro);
      return;
    }
    aoCriar(tipo, r.id);
    limpar();
  };

  return (
    <Dialog
      open={tipo !== null}
      onOpenChange={(o) => {
        if (!o) {
          limpar();
          aoFechar();
        }
      }}
    >
      <DialogContent container={container}>
        <DialogHeader>
          <DialogTitle>{unidade ? 'Nova unidade' : 'Nova marca'}</DialogTitle>
          <DialogDescription>
            {unidade
              ? 'A unidade entra no cadastro de unidades da empresa e já fica escolhida neste produto.'
              : 'A marca entra no cadastro de marcas da empresa e já fica escolhida neste produto.'}
          </DialogDescription>
        </DialogHeader>
        <Stack gap={3}>
          <Grid cols={2} gap={3}>
            <div>
              <Label htmlFor="auxiliar_nome">Nome *</Label>
              <Input id="auxiliar_nome" value={nome} onChange={(e) => setNome(e.target.value)} autoFocus />
            </div>
            {unidade ? (
              <div>
                <Label htmlFor="auxiliar_abreviacao">Abreviação *</Label>
                <Input id="auxiliar_abreviacao" value={abreviacao} onChange={(e) => setAbreviacao(e.target.value)} placeholder="Ex.: CX" />
              </div>
            ) : (
              <div>
                <Label htmlFor="auxiliar_descricao">Descrição</Label>
                <Input id="auxiliar_descricao" value={descricao} onChange={(e) => setDescricao(e.target.value)} />
              </div>
            )}
          </Grid>
          {unidade && (
            <Inline gap={2}>
              <Checkbox id="auxiliar_decimal" checked={decimal} onCheckedChange={(v) => setDecimal(v === true)} />
              <Label htmlFor="auxiliar_decimal" className="text-[13px] font-normal">Aceita quantidade com casas decimais</Label>
            </Inline>
          )}
          {erro && (
            <p role="alert" className="text-[12px] text-destructive">
              {erro}
            </p>
          )}
        </Stack>
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            onClick={() => {
              limpar();
              aoFechar();
            }}
          >
            Cancelar
          </Button>
          <Button type="button" onClick={gravar} disabled={falta || gravando}>
            {gravando ? 'Gravando…' : 'Gravar'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
