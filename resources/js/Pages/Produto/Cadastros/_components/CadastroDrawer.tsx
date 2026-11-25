// Drawer de criar/editar da tela Cadastros (playbook Produto · thread 10: PR-a Unidades e Marcas ·
// PR-b Categorias, Variações e Garantias). Copy e campos de produto-cadastros.jsx (AbaVariacoes :219-232 ·
// AbaUnidades :315-334 · AbaCategorias :379-393 · AbaMarcas :430-436 · AbaGarantias :468-478). O protótipo
// também abre o `Drawer` do DS de 760px (`FormModal`, decisão [W] 2026-10-05) — forma e conteúdo vêm dele.
//
// Grava nas MESMAS rotas do modal clássico e com os mesmos nomes de campo (POST/PUT de /units, /brands,
// /taxonomies, /variation-templates, /warranties e /selling-price-group). Grupos de preço (AbaGrupos :281-287): nome e
// descrição; o "Grupo ativo" do protótipo fica fora — o store/update do grupo não gravam a situação. Unidade: o múltiplo vai como digitado e o num_uf do
// servidor lê; desmarcar na edição manda `define_base_unit=0` explícito (UC-PCADAP-12) — é ESTOQUE.

import { useState, type FormEvent, type ReactNode } from 'react';
import { Plus } from 'lucide-react';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import { GHOST, MD, PRIMARIO_BTN, ROTULO_CAIXA_ALTA, SM, SUB } from './estilo';

// Moldura do `Drawer` do DS (PT-02, 760px), medida no protótipo servido em 2026-11-25: o × numa faixa
// própria de 14×18 com borda embaixo; título 600 17px/1.3 e subtítulo 12,5px em --text-dim num bloco
// de 16×18; corpo em --bg-2 com 16×20 de respiro; rodapé com borda em cima, 12×18. Antes saía a
// moldura do shadcn (título 16px, descrição 14px, sem bordas, fundo --background).
const PAINEL = 'w-full gap-0 border-[var(--border)] bg-[var(--surface)] shadow-[-16px_0_40px_-12px_rgb(0_0_0/0.3)] sm:max-w-[760px]';
const FAIXA_FECHAR = 'flex items-center border-b border-[var(--border)] px-[18px] py-[14px]';
const FECHAR = 'ml-auto size-7 rounded-[6px] text-[16px] font-normal text-[var(--text-mute)] hover:bg-[var(--bg-2)] hover:text-[var(--text)] dark:hover:bg-[var(--bg-2)]';
const CABECALHO = 'gap-1 px-[18px] py-4';
const TITULO = 'text-[17px] leading-[1.3] font-semibold tracking-[-0.01em] text-[var(--text)]';
const SUBTITULO = 'text-[12.5px] text-[var(--text-dim)]';
const CORPO = 'flex-1 overflow-y-auto bg-[var(--bg-2)] px-5 py-4';
const RODAPE = 'mt-0 flex-row justify-end gap-2 border-t border-[var(--border)] px-[18px] py-3';
const KBD = 'ml-1 rounded-[3px] bg-[color-mix(in_oklch,currentColor_14%,transparent)] px-[5px] py-px font-mono text-[10.5px]';

type Opcao = { id: number; rotulo: string };
export type Pedido =
  | { tipo: 'unidade'; id?: number; bases: Opcao[]; v: { nome: string; simbolo: string; decimal: boolean; base_id: number | null; multiplicador: string } }
  | { tipo: 'marca'; id?: number; oficina: boolean; v: { nome: string; descricao: string; oficina: boolean } }
  | { tipo: 'categoria'; id?: number; pais: Opcao[]; temFilhas: boolean; v: { nome: string; codigo: string; descricao: string; pai_id: number | null } }
  | { tipo: 'variacao'; id?: number; v: { nome: string; valores: { id?: number; nome: string }[] } }
  | { tipo: 'garantia'; id?: number; v: { nome: string; descricao: string; duracao: string; tipo_prazo: string } }
  | { tipo: 'grupo'; id?: number; v: { nome: string; descricao: string } };

const META = {
  unidade: { rota: '/units', novo: 'Nova unidade', editar: 'Editar unidade',
    ajuda: 'Unidade decimal aceita quantidade fracionada (m², kg). Múltiplo de unidade base converte compra em caixa para venda em peça.' },
  marca: { rota: '/brands', novo: 'Nova marca', editar: 'Editar marca',
    ajuda: 'Marca do produto — filtro do índice, relatório por marca e, quando marcada, lista de marcas de aparelho da Oficina.' },
  categoria: { rota: '/taxonomies', novo: 'Nova categoria', editar: 'Editar categoria',
    ajuda: 'Categoria e subcategoria do produto — as mesmas do filtro do índice e do relatório de lucro por categoria. Código curto entra no SKU automático.' },
  variacao: { rota: '/variation-templates', novo: 'Nova variação', editar: 'Editar variação',
    ajuda: 'Modelo reaproveitado no cadastro de produto variável: escolher “Cor” já traz os valores abaixo como variações.' },
  grupo: { rota: '/selling-price-group', novo: 'Novo grupo de preço', editar: 'Editar grupo de preço',
    ajuda: 'Cada grupo ativo vira uma coluna em Preços por grupo e uma opção de preço no PDV e no orçamento.' },
  garantia: { rota: '/warranties', novo: 'Nova garantia', editar: 'Editar garantia',
    ajuda: 'Prazo de garantia do produto — imprime na OS e na nota, e serve de base pro atendimento aceitar ou recusar retorno.' },
} as const;

function Campo({ id, label, ajuda, children }: { id: string; label?: string; ajuda?: string; children: ReactNode }) {
  return (
    <Stack gap={1}>
      {label && <Label htmlFor={id} className={ROTULO_CAIXA_ALTA}>{label}</Label>}
      {children}
      {ajuda && <p className={SUB}>{ajuda}</p>}
    </Stack>
  );
}

function Chave({ id, checked, onChange, label, sub, disabled }: { id: string; checked: boolean; onChange: (v: boolean) => void; label: string; sub?: string; disabled?: boolean }) {
  return (
    <Inline gap={3} align="start">
      <Switch id={id} variant="cowork" checked={checked} onCheckedChange={onChange} disabled={disabled} className="mt-0.5" />
      <Stack gap={0}>
        <Label htmlFor={id} className="text-[13px] font-medium text-[var(--text)]">{label}</Label>
        {sub && <span className={SUB}>{sub}</span>}
      </Stack>
    </Inline>
  );
}

function Lista({ id, value, opcoes, onChange }: { id: string; value: string; opcoes: { value: string; rotulo: string }[]; onChange: (v: string) => void }) {
  return (
    <Select value={value} onValueChange={onChange}>
      <SelectTrigger id={id} className="w-full"><SelectValue /></SelectTrigger>
      <SelectContent>{opcoes.map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.rotulo}</SafeSelectItem>)}</SelectContent>
    </Select>
  );
}

// Os mesmos campos que o form da Blade manda para cada rota.
function corpo(p: Pedido): Record<string, unknown> {
  switch (p.tipo) {
    case 'unidade': {
      const v = p.v;
      return { actual_name: v.nome.trim(), short_name: v.simbolo.trim(), allow_decimal: v.decimal ? 1 : 0,
        // Na criação sem múltiplo o campo nem vai (igual ao create.blade); na edição desmarcar é explícito.
        ...(v.base_id ? { define_base_unit: '1', base_unit_id: v.base_id, base_unit_multiplier: v.multiplicador.trim() }
          : p.id ? { define_base_unit: '0' } : {}) };
    }
    case 'marca':
      return { name: p.v.nome.trim(), description: p.v.descricao.trim(), use_for_repair: p.v.oficina ? 1 : 0 };
    case 'categoria':
      return { name: p.v.nome.trim(), short_code: p.v.codigo.trim(), description: p.v.descricao.trim(), category_type: 'product',
        ...(p.v.pai_id ? { add_as_sub_cat: 1, parent_id: p.v.pai_id } : {}) };
    case 'variacao': {
      const valores = p.v.valores.map((x) => ({ ...x, nome: x.nome.trim() }));
      return { name: p.v.nome.trim(),
        variation_values: valores.filter((x) => !x.id && x.nome).map((x) => x.nome),
        ...(p.id ? { edit_variation_values: Object.fromEntries(valores.filter((x) => x.id).map((x) => [x.id, x.nome])) } : {}) };
    }
    case 'garantia':
      return { name: p.v.nome.trim(), description: p.v.descricao.trim(), duration: p.v.duracao.trim(), duration_type: p.v.tipo_prazo };
    case 'grupo':
      return { name: p.v.nome.trim(), description: p.v.descricao.trim() };
  }
}

function completo(p: Pedido): boolean {
  switch (p.tipo) {
    case 'unidade': return !!p.v.nome.trim() && !!p.v.simbolo.trim() && (!p.v.base_id || !!p.v.multiplicador.trim());
    case 'variacao': return !!p.v.nome.trim() && p.v.valores.some((x) => x.nome.trim());
    case 'garantia': return !!p.v.nome.trim() && !!p.v.duracao.trim() && !!p.v.tipo_prazo;
    default: return !!p.v.nome.trim();
  }
}

export default function CadastroDrawer({ pedido, container, onClose, onSalvo }: { pedido: Pedido; container?: HTMLElement | null; onClose: () => void; onSalvo: () => void }) {
  const [p, setP] = useState<Pedido>(pedido);
  const [salvando, setSalvando] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const meta = META[p.tipo];
  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- cada ramo abaixo só escreve chaves do próprio tipo
  const set = (parcial: Record<string, unknown>) => setP((x) => ({ ...x, v: { ...x.v, ...parcial } }) as any);

  const salvar = async (e?: FormEvent) => {
    e?.preventDefault();
    if (salvando || !completo(p)) return;
    setSalvando(true);
    setErro(null);
    const csrf = document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const resposta = await fetch(`${meta.rota}${p.id ? `/${p.id}` : ''}`, {
      method: p.id ? 'PUT' : 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(corpo(p)),
    }).then((r) => (r.ok ? (r.json() as Promise<{ success?: boolean; msg?: string }>) : null)).catch(() => null);
    setSalvando(false);
    if (resposta?.success) onSalvo();
    else setErro(resposta?.msg ?? 'Não foi possível salvar. Nada foi alterado.');
  };

  let campos: ReactNode;
  if (p.tipo === 'unidade') {
    const v = p.v;
    const bases = p.bases.filter((b) => b.id !== p.id);
    campos = (
      <>
        <Grid fit="sm" gap={4}>
          <Campo id="dr-nome" label="Nome *"><Input id="dr-nome" autoFocus value={v.nome} placeholder="Metro quadrado" onChange={(e) => set({ nome: e.target.value })} /></Campo>
          <Campo id="dr-simbolo" label="Símbolo *" ajuda="Como aparece na tabela, na OS e na nota.">
            <Input id="dr-simbolo" value={v.simbolo} placeholder="m²" onChange={(e) => set({ simbolo: e.target.value })} />
          </Campo>
        </Grid>
        <Chave id="dr-decimal" checked={v.decimal} onChange={(x) => set({ decimal: x })} label="Aceita quantidade decimal" sub="Ligado para m² e kg; desligado para peça e caixa." />
        <Chave id="dr-base" checked={!!v.base_id} onChange={(x) => set({ base_id: x ? (bases[0]?.id ?? null) : null })}
          label="Cadastrar como múltiplo de uma unidade base" sub="Ex.: 1 caixa = 1.000 peças. A conversão vale na compra e na venda." />
        {!!v.base_id && (
          <Grid fit="sm" gap={4}>
            <Campo id="dr-mult" label="Quantidade da base"><Input id="dr-mult" inputMode="decimal" value={v.multiplicador} placeholder="1000" onChange={(e) => set({ multiplicador: e.target.value })} /></Campo>
            <Campo id="dr-base-id" label="Unidade base">
              <Lista id="dr-base-id" value={String(v.base_id)} onChange={(x) => set({ base_id: Number(x) })} opcoes={bases.map((b) => ({ value: String(b.id), rotulo: b.rotulo }))} />
            </Campo>
          </Grid>
        )}
      </>
    );
  } else if (p.tipo === 'marca') {
    campos = (
      <>
        <Campo id="dr-nome" label="Nome da marca *"><Input id="dr-nome" autoFocus value={p.v.nome} placeholder="Vinilcor" onChange={(e) => set({ nome: e.target.value })} /></Campo>
        <Campo id="dr-desc" label="Descrição curta"><Input id="dr-desc" value={p.v.descricao} placeholder="Lonas e banners." onChange={(e) => set({ descricao: e.target.value })} /></Campo>
        {p.oficina && <Chave id="dr-oficina" checked={p.v.oficina} onChange={(x) => set({ oficina: x })} label="Usar como marca de aparelho na Oficina" sub="Aparece na abertura de OS de reparo, além do catálogo." />}
      </>
    );
  } else if (p.tipo === 'categoria') {
    const pais = p.pais.filter((x) => x.id !== p.id);
    campos = (
      <>
        <Grid fit="sm" gap={4}>
          <Campo id="dr-nome" label="Nome da categoria *"><Input id="dr-nome" autoFocus value={p.v.nome} placeholder="Comunicação visual" onChange={(e) => set({ nome: e.target.value })} /></Campo>
          <Campo id="dr-codigo" label="Código" ajuda="Curto e único — entra no SKU gerado."><Input id="dr-codigo" value={p.v.codigo} placeholder="CV" onChange={(e) => set({ codigo: e.target.value })} /></Campo>
        </Grid>
        <Campo id="dr-desc" label="Descrição"><Textarea id="dr-desc" value={p.v.descricao} onChange={(e) => set({ descricao: e.target.value })} /></Campo>
        {/* Hierarquia de 2 níveis: categoria com subcategoria não vira subcategoria (o servidor também recusa). */}
        <Chave id="dr-sub" checked={!!p.v.pai_id} onChange={(x) => set({ pai_id: x ? (pais[0]?.id ?? null) : null })} label="Cadastrar como subcategoria"
          disabled={p.temFilhas || !pais.length} sub={p.temFilhas ? 'Tem subcategorias dentro — mova-as antes.' : undefined} />
        {!!p.v.pai_id && (
          <Campo id="dr-pai" label="Categoria pai *">
            <Lista id="dr-pai" value={String(p.v.pai_id)} onChange={(x) => set({ pai_id: Number(x) })} opcoes={pais.map((x) => ({ value: String(x.id), rotulo: x.rotulo }))} />
          </Campo>
        )}
      </>
    );
  } else if (p.tipo === 'variacao') {
    const valores = p.v.valores;
    campos = (
      <>
        <Campo id="dr-nome" label="Nome da variação *"><Input id="dr-nome" autoFocus value={p.v.nome} placeholder="Cor, Acabamento, Gramatura…" onChange={(e) => set({ nome: e.target.value })} /></Campo>
        <Stack gap={1} className="mt-3 gap-1.5">
          <Label className={ROTULO_CAIXA_ALTA}>Valores da variação *</Label>
          {valores.map((x, i) => (
            <Inline key={i} gap={2} align="end" className="gap-1.5">
              <Input aria-label={`Valor ${i + 1}`} value={x.nome} placeholder={`Valor ${i + 1}`}
                onChange={(e) => set({ valores: valores.map((y, k) => (k === i ? { ...y, nome: e.target.value } : y)) })} />
              {/* Valor já gravado não sai por aqui: o servidor não remove valor de modelo (só renomeia). */}
              <Button type="button" variant="outline" className={cn(SM, GHOST)} aria-label={`Remover valor ${i + 1}`} disabled={!!x.id || valores.length === 1}
                title={x.id ? 'Valor já gravado — renomeie; remover não é feito por aqui.' : undefined}
                onClick={() => set({ valores: valores.filter((_, k) => k !== i) })}>✕</Button>
            </Inline>
          ))}
          <Button type="button" variant="outline" className={cn(SM, GHOST, 'self-start')} disabled={!valores[valores.length - 1]?.nome.trim()}
            onClick={() => set({ valores: [...valores, { nome: '' }] })}><Plus className="size-3" aria-hidden />Adicionar valor</Button>
        </Stack>
      </>
    );
  } else if (p.tipo === 'grupo') {
    campos = (
      <>
        <Campo id="dr-nome" label="Nome *"><Input id="dr-nome" autoFocus value={p.v.nome} placeholder="Atacado" onChange={(e) => set({ nome: e.target.value })} /></Campo>
        <Campo id="dr-desc" label="Descrição"><Textarea id="dr-desc" value={p.v.descricao} placeholder="Quando este grupo se aplica." onChange={(e) => set({ descricao: e.target.value })} /></Campo>
      </>
    );
  } else {
    campos = (
      <>
        <Campo id="dr-nome" label="Nome *"><Input id="dr-nome" autoFocus value={p.v.nome} placeholder="12 meses" onChange={(e) => set({ nome: e.target.value })} /></Campo>
        <Campo id="dr-desc" label="Descrição"><Textarea id="dr-desc" value={p.v.descricao} placeholder="O que cobre e o que não cobre." onChange={(e) => set({ descricao: e.target.value })} /></Campo>
        <Grid fit="sm" gap={4}>
          <Campo id="dr-dur" label="Duração *"><Input id="dr-dur" inputMode="numeric" value={p.v.duracao} placeholder="12" onChange={(e) => set({ duracao: e.target.value })} /></Campo>
          <Campo id="dr-tipo" label="Unidade do prazo *">
            <Lista id="dr-tipo" value={p.v.tipo_prazo} onChange={(x) => set({ tipo_prazo: x })}
              opcoes={[{ value: 'days', rotulo: 'Dias' }, { value: 'months', rotulo: 'Meses' }, { value: 'years', rotulo: 'Anos' }]} />
          </Campo>
        </Grid>
      </>
    );
  }

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent container={container} showCloseButton={false} className={PAINEL} data-contract="produto-cadastros-drawer">
        <div className={FAIXA_FECHAR}>
          <SheetClose asChild>
            <Button type="button" variant="ghost" size="icon" aria-label="Fechar" className={FECHAR}>×</Button>
          </SheetClose>
        </div>
        <SheetHeader className={CABECALHO}>
          <SheetTitle className={TITULO}>{p.id ? meta.editar : meta.novo}</SheetTitle>
          <SheetDescription className={SUBTITULO}>{meta.ajuda}</SheetDescription>
        </SheetHeader>
        <div className={CORPO}>
          <Stack asChild gap={4}><form id="cadastro-drawer" onSubmit={salvar}>
            {campos}
            {erro && <p role="alert" className="text-[12.5px] text-[var(--color-destructive-fg)]">{erro}</p>}
          </form></Stack>
        </div>
        <SheetFooter className={RODAPE}>
          <Button type="button" variant="outline" className={cn(MD, GHOST)} onClick={onClose}>Cancelar</Button>
          <Button type="submit" form="cadastro-drawer" className={cn(MD, PRIMARIO_BTN)} disabled={salvando || !completo(p)}>
            {salvando ? 'Salvando…' : <>Salvar<kbd className={KBD}>↵</kbd></>}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
