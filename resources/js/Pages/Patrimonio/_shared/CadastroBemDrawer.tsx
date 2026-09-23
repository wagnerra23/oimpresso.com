// Drawer "Adicionar recurso" — cadastro de bem sem sair da lista.
//
// FONTE DE DESIGN: `prototipo-ui/cowork/Wagner/patrimonio-forms.jsx` → `BemForm` (modo novo).
// Mesma largura (640), mesmo título/subtítulo, as mesmas 4 seções na mesma ordem e o mesmo
// rodapé (Cancelar · Cadastrar bem). Desvios DECLARADOS, cada um com o motivo:
//   • sem o badge do código: o protótipo inventa o próximo código no cliente; aqui ele é
//     gerado pelo SERVIDOR no `store()` (prefixo + sequência por empresa) e prever no cliente
//     mostraria um número que pode não ser o gravado. O subtítulo já diz de onde ele vem.
//   • "Depreciação" SEM a unidade "(anos)": o protótipo rotula anos, o README do módulo diz
//     "% ao ano" e o Blade não diz nada. A coluna já tem dado gravado pelo Blade — escolher
//     uma unidade no rótulo mudaria o SIGNIFICADO desse dado. Fica como o Blade: sem unidade.
//     (Também sem o default por categoria: `DEP_PADRAO` é dado de mock, não regra do módulo.)
//   • "Fornecedor / contrato" grava em `asset_warranties.additional_note` — é a única coluna
//     de texto da garantia. O custo adicional (que o protótipo não desenha) vai 0, como o
//     Blade fazia com o campo em branco.
//
// O ENVIO é `router.post` do Inertia com `forceFormData` (por causa da imagem). O `store()`
// já responde com redirect pro índice + flash `status` — o mesmo contrato que o Blade usava,
// sem uma linha de backend nova. O que sai no corpo é montado por `cadastroBem.ts`, testado
// à parte (REGRA MESTRE: valor e quantidade).

import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Stack } from '@/Components/layout';
import { montarPayloadCadastro, validarCadastroBem, type FormCadastroBem } from './cadastroBem';

interface Props {
  aberto: boolean;
  onClose: () => void;
  locais: Record<string, string>;
  categorias: Record<string, string>;
  tiposCompra: Record<string, string>;
  formatoData: string;
}

const hojeIso = () => {
  const d = new Date();
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
};

/** Uma opção só = não há o que escolher. Mais de uma = o usuário escolhe (o Blade também nascia sem seleção). */
const unica = (opcoes: Record<string, string>) => {
  const ids = Object.keys(opcoes);
  return ids.length === 1 ? (ids[0] ?? '') : '';
};

function vazio(locais: Record<string, string>, categorias: Record<string, string>): FormCadastroBem {
  return {
    nome: '',
    categoriaId: unica(categorias),
    localId: unica(locais),
    modelo: '',
    serie: '',
    compraEm: hojeIso(),
    tipoCompra: 'owned',
    valorUnitario: 0,
    quantidade: 1,
    depreciacao: null,
    alocavel: false,
    garantiaMeses: '',
    garantiaInicio: hojeIso(),
    garantiaNota: '',
    descricao: '',
    imagem: null,
  };
}

function Campo({ id, rotulo, erro, ajuda, children }: {
  id: string;
  rotulo: string;
  erro?: string;
  ajuda?: string;
  children: React.ReactNode;
}) {
  return (
    <Stack gap={1}>
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro ? (
        <small id={`${id}-erro`} role="alert" className="text-destructive">{erro}</small>
      ) : ajuda ? (
        <small className="text-muted-foreground">{ajuda}</small>
      ) : null}
    </Stack>
  );
}

function Secao({ titulo, children }: { titulo: string; children: React.ReactNode }) {
  return (
    <section aria-label={titulo}>
      <Stack gap={3}>
        <h3 className="text-sm font-semibold text-foreground">{titulo}</h3>
        {children}
      </Stack>
    </section>
  );
}

function Opcoes({ id, valor, onChange, opcoes, placeholder, invalido }: {
  id: string;
  valor: string;
  onChange: (v: string) => void;
  opcoes: Record<string, string>;
  placeholder: string;
  invalido?: boolean;
}) {
  return (
    <Select value={valor} onValueChange={onChange}>
      <SelectTrigger id={id} aria-invalid={invalido || undefined}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {Object.entries(opcoes).map(([k, rotulo]) => (
          <SafeSelectItem key={k} value={k}>{rotulo}</SafeSelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

export default function CadastroBemDrawer({ aberto, onClose, locais, categorias, tiposCompra, formatoData }: Props) {
  const [f, setF] = useState<FormCadastroBem>(() => vazio(locais, categorias));
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);

  // Reabrir começa do zero — um cadastro não herda o rascunho do anterior.
  useEffect(() => {
    if (aberto) {
      setF(vazio(locais, categorias));
      setErros({});
    }
  }, [aberto, locais, categorias]);

  const set = <K extends keyof FormCadastroBem>(k: K, v: FormCadastroBem[K]) =>
    setF((o) => ({ ...o, [k]: v }));

  const salvar = () => {
    if (enviando) return;
    const e = validarCadastroBem(f);
    setErros(e);
    if (Object.keys(e).length) return;

    setEnviando(true);
    router.post('/asset/assets', montarPayloadCadastro(f, formatoData) as never, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: (page) => {
        // O `store()` também redireciona quando FALHA (flash `status.success = false`). Nesse
        // caso o drawer fica aberto com o que o usuário digitou — fechar perderia o form.
        const flash = (page.props as { flash?: { error?: string | null } }).flash;
        if (flash?.error) {
          setErros({ geral: flash.error });
          return;
        }
        onClose();
      },
      onError: (serverErros) => {
        // Validação do `StoreAssetRequest` (422) — chaves do backend → campos do form.
        const mapa: Record<string, keyof FormCadastroBem> = {
          name: 'nome', category_id: 'categoriaId', location_id: 'localId',
          purchase_date: 'compraEm', unit_price: 'valorUnitario', quantity: 'quantidade',
          image: 'imagem',
        };
        const out: Record<string, string> = {};
        for (const [k, msg] of Object.entries(serverErros)) out[mapa[k] ?? 'geral'] = msg;
        setErros(out);
      },
      onFinish: () => setEnviando(false),
    });
  };

  return (
    <Sheet open={aberto} onOpenChange={(o) => { if (!o && !enviando) onClose(); }}>
      <SheetContent side="right" className="w-full gap-0 sm:max-w-[640px]" data-testid="cadastro-bem">
        <SheetHeader className="border-b">
          <SheetTitle>Adicionar recurso</SheetTitle>
          <SheetDescription>Código gerado pelo prefixo do módulo — sequência por empresa.</SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto p-4">
          <Stack gap={6}>
            {erros.geral ? (
              <p role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm">
                {erros.geral}
              </p>
            ) : null}

            <Secao titulo="Identificação">
              <Grid cols={2} gap={3}>
                <div className="col-span-2">
                  <Campo id="cb-nome" rotulo="Nome do recurso" erro={erros.nome}>
                    <Input id="cb-nome" value={f.nome} placeholder="Ex.: Plotter de corte Summa D60"
                      aria-invalid={!!erros.nome || undefined} onChange={(e) => set('nome', e.target.value)} />
                  </Campo>
                </div>
                <Campo id="cb-categoria" rotulo="Categoria" erro={erros.categoriaId}>
                  <Opcoes id="cb-categoria" valor={f.categoriaId} onChange={(v) => set('categoriaId', v)}
                    opcoes={categorias} placeholder="Escolha a categoria" invalido={!!erros.categoriaId} />
                </Campo>
                <Campo id="cb-local" rotulo="Local" erro={erros.localId}>
                  <Opcoes id="cb-local" valor={f.localId} onChange={(v) => set('localId', v)}
                    opcoes={locais} placeholder="Escolha o local" invalido={!!erros.localId} />
                </Campo>
                <Campo id="cb-modelo" rotulo="Série/Modelo">
                  <Input id="cb-modelo" value={f.modelo} onChange={(e) => set('modelo', e.target.value)} />
                </Campo>
                <Campo id="cb-serie" rotulo="Número de série">
                  <Input id="cb-serie" value={f.serie} onChange={(e) => set('serie', e.target.value)} />
                </Campo>
              </Grid>
            </Secao>

            <Secao titulo="Compra e valores">
              <Grid cols={2} gap={3}>
                <Campo id="cb-compra" rotulo="Data da compra" erro={erros.compraEm}>
                  <Input id="cb-compra" type="date" value={f.compraEm}
                    aria-invalid={!!erros.compraEm || undefined} onChange={(e) => set('compraEm', e.target.value)} />
                </Campo>
                <Campo id="cb-tipo" rotulo="Tipo de compra">
                  <Opcoes id="cb-tipo" valor={f.tipoCompra} onChange={(v) => set('tipoCompra', v)}
                    opcoes={tiposCompra} placeholder="Tipo de compra" />
                </Campo>
                <Campo id="cb-valor" rotulo="Valor unitário (R$)" erro={erros.valorUnitario}>
                  <NumericInputPtBR id="cb-valor" value={f.valorUnitario} precision={2}
                    aria-invalid={!!erros.valorUnitario || undefined} onChange={(n) => set('valorUnitario', n)} />
                </Campo>
                <Campo id="cb-qtd" rotulo="Quantidade" erro={erros.quantidade}>
                  <NumericInputPtBR id="cb-qtd" value={f.quantidade} precision={2}
                    aria-invalid={!!erros.quantidade || undefined} onChange={(n) => set('quantidade', n)} />
                </Campo>
                <Campo id="cb-dep" rotulo="Depreciação">
                  <NumericInputPtBR id="cb-dep" value={f.depreciacao ?? 0} precision={2}
                    onChange={(n) => set('depreciacao', n === 0 ? null : n)} />
                </Campo>
                <div className="flex items-end gap-3 pb-1">
                  <Switch id="cb-alocavel" checked={f.alocavel} onCheckedChange={(v) => set('alocavel', v)} />
                  <Stack gap={0}>
                    <Label htmlFor="cb-alocavel">É atribuível?</Label>
                    <small className="text-muted-foreground">Se atribuível, o bem pode ser alocado a um colaborador.</small>
                  </Stack>
                </div>
              </Grid>
            </Secao>

            <Secao titulo="Garantia">
              <Grid cols={2} gap={3}>
                <Campo id="cb-gar-meses" rotulo="Período de garantia (meses)" erro={erros.garantiaMeses}
                  ajuda="Vazio = bem sem garantia registrada.">
                  <Input id="cb-gar-meses" inputMode="numeric" value={f.garantiaMeses}
                    aria-invalid={!!erros.garantiaMeses || undefined}
                    onChange={(e) => set('garantiaMeses', e.target.value.replace(/\D/g, ''))} />
                </Campo>
                <Campo id="cb-gar-inicio" rotulo="Início da garantia" erro={erros.garantiaInicio}>
                  <Input id="cb-gar-inicio" type="date" value={f.garantiaInicio}
                    aria-invalid={!!erros.garantiaInicio || undefined} onChange={(e) => set('garantiaInicio', e.target.value)} />
                </Campo>
                <div className="col-span-2">
                  <Campo id="cb-gar-nota" rotulo="Fornecedor / contrato">
                    <Input id="cb-gar-nota" value={f.garantiaNota} placeholder="Ex.: Roland Care · contrato RC-8842"
                      onChange={(e) => set('garantiaNota', e.target.value)} />
                  </Campo>
                </div>
              </Grid>
            </Secao>

            <Secao titulo="Imagem e descrição">
              <Campo id="cb-imagem" rotulo="Imagem" erro={erros.imagem} ajuda="Até 5 MB.">
                <Input id="cb-imagem" type="file" accept="image/*"
                  onChange={(e) => set('imagem', e.target.files?.[0] ?? null)} />
              </Campo>
              <Campo id="cb-descricao" rotulo="Descrição" ajuda="Campo não auditado — não registre dado pessoal aqui (LGPD).">
                <Textarea id="cb-descricao" rows={3} value={f.descricao} onChange={(e) => set('descricao', e.target.value)} />
              </Campo>
            </Secao>
          </Stack>
        </div>

        <SheetFooter className="flex-row justify-end border-t">
          <Button variant="ghost" onClick={onClose} disabled={enviando}>Cancelar</Button>
          <Button onClick={salvar} disabled={enviando}>{enviando ? 'Cadastrando…' : 'Cadastrar bem'}</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
