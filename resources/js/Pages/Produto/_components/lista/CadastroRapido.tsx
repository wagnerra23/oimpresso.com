// Cadastro rápido de produto — `ModalCadastroRapido` do protótipo (produto-blade.jsx): nome, SKU,
// unidade, categoria e os dois preços sem imposto. "Gerenciar estoque" (marcado) e os locais
// (quando a empresa tem mais de um) entraram por decisão [F] em 2026-10-12.
//
// A tela NÃO calcula preço nenhum. Manda os dois preços digitados e o servidor
// (ProductController::cadastroRapido) monta o resto como o formulário Blade montava: preço com
// imposto, margem e os padrões. Os preços vão como texto com vírgula decimal e sem separador de
// milhar (`paraNumUf`) — o único formato que o servidor aceita; "12.125" não passa.

import { useEffect, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Grid, Inline } from '@/Components/layout';
import { paraNumUf } from '@/Lib/numberPtBR';
import type { Opcao } from './tipos';

/** Casas dos preços na janela — as mesmas que vão ao servidor (o que a tela mostra é o que grava). */
const CASAS = 2;
// O Radix Select não aceita `value=""` (lápide §5 2026-06-29): "sem categoria" usa uma sentinela.
const SEM_CATEGORIA = '__sem__';

export interface FormCadastroRapido {
  nome: string;
  sku: string;
  unidade: string;
  categoria: string;
  compra: number;
  venda: number;
  controlaEstoque: boolean;
  locais: string[];
}

export interface PedidoCadastroRapido {
  name: string;
  sku: string;
  unit_id: number;
  category_id: number | null;
  compra: string;
  venda: string;
  enable_stock: boolean;
  product_locations: number[];
}

export type ErrosCadastroRapido = Partial<Record<'name' | 'sku' | 'unit_id' | 'category_id' | 'compra' | 'venda' | 'product_locations', string>>;

/** Formulário em branco: a 1ª unidade já escolhida (como o formulário Blade) e estoque gerenciado. */
export function formInicial(unidades: Opcao[]): FormCadastroRapido {
  return { nome: '', sku: '', unidade: unidades[0]?.id ?? '', categoria: '', compra: 0, venda: 0, controlaEstoque: true, locais: [] };
}

/** O que falta para poder salvar. Com um local só, a janela não pergunta o local. */
export function errosDoForm(f: FormCadastroRapido, locais: Opcao[]): ErrosCadastroRapido {
  const e: ErrosCadastroRapido = {};
  if (f.nome.trim() === '') e.name = 'Informe o nome do produto.';
  if (f.unidade === '') e.unit_id = 'Escolha a unidade.';
  if (!Number.isFinite(f.compra) || f.compra < 0) e.compra = 'O preço de compra não pode ser negativo.';
  if (!Number.isFinite(f.venda) || f.venda < 0) e.venda = 'O preço de venda não pode ser negativo.';
  if (locais.length > 1 && f.locais.length === 0) e.product_locations = 'Escolha ao menos um local do negócio.';
  return e;
}

/** O pedido que vai ao servidor. Nenhum preço é calculado aqui. */
export function pedidoDoForm(f: FormCadastroRapido): PedidoCadastroRapido {
  return {
    name: f.nome.trim(),
    sku: f.sku.trim(),
    unit_id: Number(f.unidade),
    category_id: f.categoria === '' ? null : Number(f.categoria),
    compra: paraNumUf(f.compra, CASAS),
    venda: paraNumUf(f.venda, CASAS),
    enable_stock: f.controlaEstoque,
    product_locations: f.locais.map(Number),
  };
}

const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

type Resposta = { ok: true; msg: string } | { ok: false; msg: string; erros: ErrosCadastroRapido };

/** POST /products/cadastro-rapido. Erro de validação (422) volta campo a campo. */
export async function enviarCadastroRapido(pedido: PedidoCadastroRapido): Promise<Resposta> {
  const falha = 'Não foi possível criar o produto.';
  try {
    const r = await fetch('/products/cadastro-rapido', {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(pedido),
    });
    const j = (await r.json().catch(() => null)) as { success?: boolean; msg?: string; message?: string; errors?: Record<string, string[]> } | null;
    if (r.ok && j?.success === true) return { ok: true, msg: j.msg ?? 'Produto criado.' };
    const erros: ErrosCadastroRapido = {};
    for (const [campo, mensagens] of Object.entries(j?.errors ?? {})) {
      const primeira = mensagens[0];
      if (primeira) erros[campo as keyof ErrosCadastroRapido] = primeira;
    }
    return { ok: false, msg: Object.keys(erros).length > 0 ? 'Confira os campos marcados.' : (j?.msg ?? falha), erros };
  } catch {
    return { ok: false, msg: falha, erros: {} };
  }
}

interface Props {
  aberto: boolean;
  aoFechar: () => void;
  unidades: Opcao[];
  categorias: Opcao[];
  /** Locais reais que quem cadastra pode acessar (sem o "Nenhum" do filtro). */
  locais: Opcao[];
  /** Chamado depois de criar, com a mensagem do servidor. */
  aoCriar: (msg: string) => void;
  /** Só para teste: troca a chamada ao servidor. */
  enviar?: (pedido: PedidoCadastroRapido) => Promise<Resposta>;
}

function Erro({ texto }: { texto?: string }) {
  return texto ? (
    <p role="alert" className="text-xs text-destructive">
      {texto}
    </p>
  ) : null;
}

export default function CadastroRapidoProduto({ aberto, aoFechar, unidades, categorias, locais, aoCriar, enviar = enviarCadastroRapido }: Props) {
  const [f, setF] = useState<FormCadastroRapido>(() => formInicial(unidades));
  const [erros, setErros] = useState<ErrosCadastroRapido>({});
  const [geral, setGeral] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);

  // Cada abertura começa em branco.
  useEffect(() => {
    if (aberto) {
      setF(formInicial(unidades));
      setErros({});
      setGeral(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [aberto]);

  const campo = <K extends keyof FormCadastroRapido>(k: K, v: FormCadastroRapido[K]) => setF((a) => ({ ...a, [k]: v }));
  const unidadesValidas = unidades.filter((o) => o.id !== '');
  const categoriasValidas = categorias.filter((o) => o.id !== '');

  const salvar = async () => {
    const faltas = errosDoForm(f, locais);
    setErros(faltas);
    setGeral(null);
    if (Object.keys(faltas).length > 0) return;
    setSalvando(true);
    const r = await enviar(pedidoDoForm(f));
    setSalvando(false);
    if (r.ok) {
      aoCriar(r.msg);
      aoFechar();
      return;
    }
    setErros(r.erros);
    setGeral(r.msg);
  };

  return (
    <Dialog open={aberto} onOpenChange={(o) => !o && !salvando && aoFechar()}>
      <DialogContent className="sm:max-w-[720px]">
        <DialogHeader>
          <DialogTitle>Cadastro rápido de produto</DialogTitle>
          <DialogDescription>O mínimo para vender agora; o resto do cadastro se completa depois, em Editar.</DialogDescription>
        </DialogHeader>

        <form
          className="space-y-4"
          noValidate
          onSubmit={(e) => {
            e.preventDefault();
            void salvar();
          }}
        >
          <Grid cols={2} gap={4}>
            <div className="space-y-1.5">
              <Label htmlFor="cr-nome">Nome do produto *</Label>
              <Input id="cr-nome" value={f.nome} onChange={(e) => campo('nome', e.target.value)} placeholder="Nome do produto" aria-invalid={erros.name ? true : undefined} autoFocus />
              <Erro texto={erros.name} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="cr-sku">SKU</Label>
              <Input id="cr-sku" value={f.sku} onChange={(e) => campo('sku', e.target.value)} placeholder="Gerado automaticamente" aria-invalid={erros.sku ? true : undefined} />
              {erros.sku ? <Erro texto={erros.sku} /> : <p className="text-xs text-muted-foreground">Em branco, o sistema gera.</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="cr-unidade">Unidade *</Label>
              <Select value={f.unidade === '' ? undefined : f.unidade} onValueChange={(v) => campo('unidade', v)}>
                <SelectTrigger id="cr-unidade" aria-label="Unidade" aria-invalid={erros.unit_id ? true : undefined}>
                  <SelectValue placeholder="Selecione…" />
                </SelectTrigger>
                <SelectContent>
                  {unidadesValidas.map((o) => (
                    <SelectItem key={o.id} value={o.id}>
                      {o.nome}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Erro texto={erros.unit_id} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="cr-categoria">Categoria</Label>
              <Select value={f.categoria === '' ? SEM_CATEGORIA : f.categoria} onValueChange={(v) => campo('categoria', v === SEM_CATEGORIA ? '' : v)}>
                <SelectTrigger id="cr-categoria" aria-label="Categoria">
                  <SelectValue placeholder="Selecione…" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={SEM_CATEGORIA}>Sem categoria</SelectItem>
                  {categoriasValidas.map((o) => (
                    <SelectItem key={o.id} value={o.id}>
                      {o.nome}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Erro texto={erros.category_id} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="cr-compra">Preço de compra sem imposto *</Label>
              <NumericInputPtBR id="cr-compra" value={f.compra} onChange={(n) => campo('compra', n)} precision={CASAS} className="text-right" aria-invalid={erros.compra ? true : undefined} />
              <Erro texto={erros.compra} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="cr-venda">Preço de venda sem imposto *</Label>
              <NumericInputPtBR id="cr-venda" value={f.venda} onChange={(n) => campo('venda', n)} precision={CASAS} className="text-right" aria-invalid={erros.venda ? true : undefined} />
              <Erro texto={erros.venda} />
            </div>
          </Grid>

          <Inline gap={2}>
            <Checkbox id="cr-estoque" checked={f.controlaEstoque} onCheckedChange={(v) => campo('controlaEstoque', v === true)} />
            <Label htmlFor="cr-estoque">Gerenciar estoque</Label>
            <span className="text-xs text-muted-foreground">O produto nasce com saldo zero.</span>
          </Inline>

          {locais.length > 1 && (
            <fieldset className="space-y-1.5">
              <legend className="text-sm font-medium">Locais do negócio *</legend>
              <Inline wrap gap={4}>
                {locais.map((l) => (
                  <Inline key={l.id} gap={2}>
                    <Checkbox
                      id={`cr-local-${l.id}`}
                      checked={f.locais.includes(l.id)}
                      onCheckedChange={(v) => campo('locais', v === true ? [...f.locais, l.id] : f.locais.filter((x) => x !== l.id))}
                    />
                    <Label htmlFor={`cr-local-${l.id}`}>{l.nome}</Label>
                  </Inline>
                ))}
              </Inline>
              <Erro texto={erros.product_locations} />
            </fieldset>
          )}

          {geral && (
            <p role="alert" className="text-sm text-destructive">
              {geral}
            </p>
          )}

          <DialogFooter>
            <Button type="button" variant="ghost" onClick={aoFechar} disabled={salvando}>
              Cancelar
            </Button>
            <Button type="submit" disabled={salvando}>
              {salvando ? 'Salvando…' : 'Salvar'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
