/**
 * Pages/ComunicacaoVisual/Index — Hub + Calculadora de m² do vertical gráfica/comunicação visual.
 *
 * VALOR REAL na v1 (board SCREEN-GRADE 2026-05-30 elevou de stub nota 54):
 *  - Calculadora de orçamento por m² (US-COMVIS-001) — largura × altura × qtd × preço/m².
 *    Cálculo instantâneo no cliente pra feedback imediato da Larissa no balcão +
 *    botão "Conferir no servidor" que bate no endpoint authoritative
 *    POST /comunicacao-visual/api/calcular (server-side é a fonte de verdade,
 *    respeita business_id Tier 0 — ADR 0093).
 *  - Seletor de material puxa o catálogo do business (preço/m² preenche sozinho).
 *  - Salvar e reabrir (US-COMVIS-021): "Salvar orçamento" depois de conferir; a URL ganha
 *    ?orcamento={id} e "Orçamentos salvos" (prop deferida) reabre qualquer um.
 *  - Áreas ainda não migradas (OS/PCP, Materiais, Apontamentos) aparecem como
 *    "em breve" honesto, em PT-BR caloroso (sem jargão técnico/api_hint).
 *
 * Persona: Larissa — dona/operadora de gráfica pequena, não-técnica, balcão,
 * monitor 1280px. Precisa fechar um orçamento em <2min sem abrir Excel.
 *
 * Layout canon: AppShellV2 + PageHeader (ADR 0110 / UI-0013). Tokens DS v4 — sem
 * cor crua (zinc/amber). Primary roxo via classe `bg-primary`.
 *
 * @see Modules/ComunicacaoVisual/Http/Controllers/OrcamentoController@calcular (API authoritative)
 * @see Modules/ComunicacaoVisual/Services/OrcamentoCalculator (fórmula canônica)
 * @see resources/js/Pages/ComunicacaoVisual/Index.charter.md
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-001
 *
 * TODO (Sprint 2, quando cliente piloto CV ativar — ADR 0105):
 *  - Enviar o PDF do orçamento salvo no WhatsApp.
 *  - Telas próprias: PCP Kanban (US-COMVIS-003), CRUD Materiais (US-COMVIS-002),
 *    Apontamento de máquina (US-COMVIS-004). Por ora as APIs já existem.
 *  - Acabamentos/instalação/entrega como linhas extras do total (hoje só campo manual).
 */
import { useMemo, useState, type ReactNode } from 'react';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { Plus, Trash2, Calculator, ServerCog, Ruler, Clock, Printer, Save, FolderOpen, CheckCircle2, Paperclip, X } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';

interface Material {
  id: number;
  nome: string;
  categoria: string;
  unidade: string;
  preco_venda_m2: number;
}

/** Orçamento gravado, no formato que a calculadora reabre (OrcamentoController::paraTela). */
interface OrcamentoTela {
  id: number;
  numero: string;
  status: string;
  desconto: number;
  extras: number;
  custo_instalacao: number;
  custo_entrega: number;
  total: number;
  /** OS que o orçamento gerou ao ser aprovado (UC-FLX-01). */
  os: { id: number; numero: string } | null;
  /** Arquivos anexados (media do core) — passam para a OS ao aprovar. */
  arquivos: ArquivoTela[];
  itens: Array<{
    material_id: number | null;
    descricao: string;
    largura_m: number;
    altura_m: number;
    quantidade: number;
    preco_unitario_m2: number;
  }>;
}

interface ArquivoTela {
  id: number;
  nome: string;
  url: string;
}

/** Extensões aceitas (espelha OrcamentoArquivoController::EXTENSOES). */
const EXTENSOES_ARQUIVO = '.pdf,.jpg,.jpeg,.png,.svg,.ai,.eps,.cdr,.tif,.tiff,.psd';

/** Linha da lista "Orçamentos salvos" (OrcamentoController::recentes). */
interface OrcamentoResumo {
  id: number;
  numero: string;
  status: string;
  total: number;
  data_emissao: string | null;
  itens: number;
  os: string | null;
}

interface Props {
  bizName?: string;
  materiais?: Material[];
  podeCriar?: boolean;
  podeVerCusto?: boolean;
  orcamento?: OrcamentoTela | null;
  orcamentoAusente?: boolean;
  recentes?: OrcamentoResumo[];
}

/** Status em que o orçamento reaberto ainda grava (espelha OrcamentoController::EDITAVEIS). */
const EDITAVEIS = ['rascunho', 'enviado'];
/** Status que viram OS (espelha OrcamentoViraOs::APROVAVEIS). */
const APROVAVEIS = ['rascunho', 'enviado', 'aprovado'];
const STATUS_LABEL: Record<string, string> = {
  rascunho: 'Rascunho',
  enviado: 'Enviado',
  aprovado: 'Aprovado',
  recusado: 'Recusado',
  expirado: 'Expirado',
};

/** Linha de item da calculadora (estado local de UI). */
interface ItemUI {
  id: string; // id local React
  material_id: number | null;
  descricao: string;
  largura_m: number;
  altura_m: number;
  quantidade: number;
  preco_unitario_m2: number;
}

/** Resultado authoritative devolvido pela API /calcular. */
interface ServerItem {
  area_m2: number;
  subtotal: number;
  preco_unitario_m2: number;
  /** UC-FLX-03 — só vêm para quem vê custo (MargemDoOrcamento). Nulo = material sem custo. */
  custo?: number | null;
  margem?: number | null;
  margem_pct?: number | null;
}
interface ServerResult {
  subtotal: number;
  total: number;
  itens: ServerItem[];
  margem?: {
    itens_com_custo: number;
    itens: number;
    custo: number | null;
    receita: number;
    margem: number | null;
    margem_pct: number | null;
  };
}

const PCT = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const NUM = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 });

// Sem crypto.randomUUID: fora de secure context (http:// no IP da rede do balcão)
// ele é undefined e derrubava o render inicial — tela branca, sem mensagem.
let seqItem = 0;
function itemDoOrcamento(i: OrcamentoTela['itens'][number]): ItemUI {
  return { ...novoItem(), ...i, descricao: i.descricao ?? '' };
}

/** Chamada às APIs do módulo; erro vira Error com a mensagem do servidor em PT-BR.
 *  FormData vai como multipart (o navegador põe o boundary); o resto, JSON. */
async function chamarApi(method: 'POST' | 'PUT' | 'DELETE', url: string, body?: unknown): Promise<unknown> {
  const csrf =
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
  const multipart = body instanceof FormData;
  const res = await fetch(url, {
    method,
    headers: {
      ...(multipart ? {} : { 'Content-Type': 'application/json' }),
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrf,
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: body === undefined ? undefined : multipart ? (body as FormData) : JSON.stringify(body),
  });
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    throw new Error(
      (data && (data.message as string)) ||
        `Não consegui falar com o servidor agora (erro ${res.status}). Confira as medidas e tente de novo.`,
    );
  }
  return data;
}

function novoItem(): ItemUI {
  return {
    id: `i${++seqItem}`,
    material_id: null,
    descricao: '',
    largura_m: 1,
    altura_m: 1,
    quantidade: 1,
    preco_unitario_m2: 0,
  };
}

/** Fórmula canônica espelhada do OrcamentoCalculator (preview no cliente).
 *  area_m2 = largura × altura × qtd ; subtotal = area × preço/m². */
function itemOk(i: ItemUI): boolean {
  return i.largura_m > 0 && i.altura_m > 0 && i.quantidade >= 1 && i.preco_unitario_m2 > 0;
}
function areaDe(item: ItemUI): number {
  // Área arredondada a 3 casas e subtotal a 2, POR ITEM — é o que o servidor faz. Somar o
  // float bruto dava diferença de centavo em quantidade alta e jogava a conferência no
  // "vale este" sem motivo.
  return arred(Math.max(0, item.largura_m) * Math.max(0, item.altura_m) * Math.max(0, item.quantidade), 3);
}
function subtotalDe(item: ItemUI): number {
  return arred(areaDe(item) * arred(Math.max(0, item.preco_unitario_m2), 2), 2);
}

/** Espelha round($n, $casas, PHP_ROUND_HALF_UP) do OrcamentoCalculator: meio pra longe do zero.
 *  O toPrecision(15) tira o resíduo binário antes de arredondar — sem ele 1,005 × 100 dá
 *  100,4999… e o centavo sai pra baixo, enquanto o PHP (que faz o mesmo pré-arredondamento)
 *  sobe. */
function arred(n: number, casas: number): number {
  const fator = 10 ** casas;
  const deslocado = Number((Math.abs(n) * fator).toPrecision(15));
  return (Math.sign(n) * Math.round(deslocado)) / fator;
}

export default function Index({
  bizName = 'oimpresso',
  materiais = [],
  podeCriar = false,
  podeVerCusto = false,
  orcamento = null,
  orcamentoAusente = false,
  recentes,
}: Props) {
  const [itens, setItens] = useState<ItemUI[]>(() =>
    orcamento && orcamento.itens.length > 0 ? orcamento.itens.map(itemDoOrcamento) : [novoItem()],
  );
  const [extras, setExtras] = useState(orcamento?.extras ?? 0); // acabamento/instalação/entrega (manual por ora)
  const [desconto, setDesconto] = useState(orcamento?.desconto ?? 0);
  // Instalação e entrega não têm campo na tela, mas o orçamento pode tê-los (app das lojas):
  // reabrir e gravar de novo não pode zerá-los em silêncio.
  const instalacao = orcamento?.custo_instalacao ?? 0;
  const entrega = orcamento?.custo_entrega ?? 0;
  const [conferindo, setConferindo] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [conferido, setConferido] = useState<ServerResult | null>(null);
  // Orçamento gravado que está na tela (reaberto ou recém-salvo) e se mudou desde a gravação.
  const [salvo, setSalvo] = useState<{ id: number; numero: string; status: string } | null>(
    orcamento ? { id: orcamento.id, numero: orcamento.numero, status: orcamento.status } : null,
  );
  const [alterado, setAlterado] = useState(false);
  const [salvando, setSalvando] = useState(false);
  // UC-FLX-01: aprovar cria a OS. Dois passos na mesma linha (sem diálogo): aprovar não volta atrás.
  const [osCriada, setOsCriada] = useState<string | null>(orcamento?.os?.numero ?? null);
  const [confirmandoAprovar, setConfirmandoAprovar] = useState(false);
  const [aprovando, setAprovando] = useState(false);
  const [arquivos, setArquivos] = useState<ArquivoTela[]>(orcamento?.arquivos ?? []);
  const [enviandoArquivo, setEnviandoArquivo] = useState(false);

  function mexeu() {
    setConferido(null);
    setAlterado(true);
  }

  function addItem() {
    setItens((prev) => [...prev, novoItem()]);
    mexeu();
  }

  function removeItem(id: string) {
    setItens((prev) => (prev.length === 1 ? prev : prev.filter((i) => i.id !== id)));
    mexeu();
  }

  function patchItem(id: string, patch: Partial<ItemUI>) {
    setItens((prev) => prev.map((i) => (i.id === id ? { ...i, ...patch } : i)));
    mexeu();
  }

  function escolherMaterial(id: string, materialId: number | null) {
    const mat = materiais.find((m) => m.id === materialId) ?? null;
    patchItem(id, {
      material_id: materialId,
      // Preenche preço/m² do catálogo e sugere descrição se vazia.
      preco_unitario_m2: mat ? mat.preco_venda_m2 : 0,
    });
  }

  // Totais do preview client-side (feedback instantâneo enquanto digita).
  const subtotalLocal = useMemo(
    () => arred(itens.reduce((acc, i) => acc + subtotalDe(i), 0), 2),
    [itens],
  );
  // SEM clamp em zero: o servidor não clampa (total = subtotal − desconto + extras), então
  // desconto maior que o orçamento mostrava R$ 0,00 aqui e um negativo no "vale este".
  // Melhor mostrar o negativo e dizer por quê.
  const totalLocal = useMemo(
    () =>
      arred(
        subtotalLocal - arred(Math.max(0, desconto), 2) + arred(Math.max(0, extras), 2) + instalacao + entrega,
        2,
      ),
    [subtotalLocal, desconto, extras, instalacao, entrega],
  );

  // TODAS as linhas: o servidor recusa o orçamento inteiro por causa de uma só
  // (com `some`, uma peça boa liberava o botão e a linha vazia voltava 422).
  const temItemValido = itens.length > 0 && itens.every(itemOk);
  // Marca a linha que está segurando o botão — só quando há outra linha pronta.
  const algumOk = itens.some(itemOk);

  /** Payload de /calcular e /orcamentos — o mesmo contrato (OrcamentoController::validarPayload). */
  function montarPayload() {
    return {
      data_emissao: new Date().toISOString().slice(0, 10),
      desconto: Math.max(0, desconto),
      extras: Math.max(0, extras),
      custo_instalacao: instalacao,
      custo_entrega: entrega,
      itens: itens.map((i) => ({
        material_id: i.material_id ?? undefined,
        descricao: i.descricao.trim() || 'Item sem descrição',
        largura_m: i.largura_m,
        altura_m: i.altura_m,
        quantidade: i.quantidade,
        // Preço sempre enviado (override do operador). O server resolve
        // material->preco_venda_m2 como fallback caso venha vazio.
        preco_unitario_m2: i.preco_unitario_m2 > 0 ? i.preco_unitario_m2 : undefined,
      })),
    };
  }

  // Confere no servidor (authoritative): o backend recalcula área/subtotal/total
  // e respeita business_id. Se bater com o preview local, dá confiança pra Larissa.
  async function conferirNoServidor() {
    setConferindo(true);
    setErro(null);
    setConferido(null);
    try {
      setConferido((await chamarApi('POST', '/comunicacao-visual/api/calcular', montarPayload())) as ServerResult);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Falha inesperada ao conferir no servidor.');
    } finally {
      setConferindo(false);
    }
  }

  // Aprova o orçamento gravado e cria a OS com os itens, medidas e arquivos dele (UC-FLX-01).
  // O servidor não recalcula preço: a OS vale o total gravado. Reenviar devolve a mesma OS.
  async function aprovarOrcamento() {
    if (!salvo) return;
    setAprovando(true);
    setErro(null);
    try {
      const os = (await chamarApi('POST', `/comunicacao-visual/api/orcamentos/${salvo.id}/aprovar`, {})) as {
        numero: string;
      };
      setOsCriada(os.numero);
      setSalvo({ ...salvo, status: 'aprovado' });
      router.reload({ only: ['recentes'] });
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Falha inesperada ao aprovar o orçamento.');
    } finally {
      setAprovando(false);
      setConfirmandoAprovar(false);
    }
  }

  // Anexa a arte/foto ao orçamento gravado. Ao aprovar, os arquivos passam para a OS.
  async function enviarArquivos(files: FileList | null) {
    if (!salvo || !files || files.length === 0) return;
    setEnviandoArquivo(true);
    setErro(null);
    try {
      const fd = new FormData();
      Array.from(files).forEach((f) => fd.append('arquivos[]', f));
      setArquivos((await chamarApi('POST', `/comunicacao-visual/api/orcamentos/${salvo.id}/arquivos`, fd)) as ArquivoTela[]);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Falha inesperada ao enviar o arquivo.');
    } finally {
      setEnviandoArquivo(false);
    }
  }

  async function removerArquivo(id: number) {
    if (!salvo) return;
    setErro(null);
    try {
      setArquivos((await chamarApi('DELETE', `/comunicacao-visual/api/orcamentos/${salvo.id}/arquivos/${id}`)) as ArquivoTela[]);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Falha inesperada ao remover o arquivo.');
    }
  }

  // Grava o orçamento: POST na 1ª vez, PUT no reaberto. O servidor recalcula tudo de novo e o
  // número fica o mesmo. A URL passa a ter ?orcamento={id}, então recarregar reabre o mesmo.
  async function salvarOrcamento() {
    setSalvando(true);
    setErro(null);
    try {
      const data = (await chamarApi(
        salvo ? 'PUT' : 'POST',
        salvo ? `/comunicacao-visual/api/orcamentos/${salvo.id}` : '/comunicacao-visual/api/orcamentos',
        montarPayload(),
      )) as { id: number; numero: string; status: string };
      setSalvo({ id: data.id, numero: data.numero, status: data.status });
      setAlterado(false);
      window.history.replaceState(window.history.state, '', `/comunicacao-visual?orcamento=${data.id}`);
      router.reload({ only: ['recentes'] });
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Falha inesperada ao salvar o orçamento.');
    } finally {
      setSalvando(false);
    }
  }

  const semCatalogo = materiais.length === 0;
  const editavel = !salvo || EDITAVEIS.includes(salvo.status);
  // Salvar só depois de conferir (protótipo): orçamento salvo com número errado vira retrabalho.
  const podeSalvar = podeCriar && editavel && temItemValido && !!conferido && (!salvo || alterado);
  // Aprovar só o que está gravado igual à tela: mudança não salva não pode virar OS.
  // `aprovado` sem OS (gravado antes desta tela) também aprova: o servidor cria a OS que falta.
  const podeAprovar =
    podeCriar && !!salvo && APROVAVEIS.includes(salvo.status) && !alterado && !osCriada;

  return (
    <>
      <Head title="Comunicação Visual — Orçamento por m²" />

      {/* Header canon (ADR 0189/0190): traz o próprio padding, por isso fica FORA do bloco p-6. */}
      <div data-contract="cabecalho">
      <PageHeader
        leading={<Printer className="h-5 w-5 text-primary" aria-hidden="true" />}
        title="Comunicação Visual"
        subtitle={`Orçamento por m² pra ${bizName} — banner, lona, adesivo, fachada. Calcule na hora, sem abrir o Excel.`}
      />
      </div>

      <div className="space-y-6 p-6">

        {/* Calculadora — o valor real da tela */}
        <Card data-contract="calculadora">
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <CardTitle className="flex items-center gap-2">
              <Calculator className="h-4 w-4 text-primary" />
              Calculadora de m²
              {salvo && (
                <span data-contract="orcamento-aberto" className="flex items-center gap-1.5 text-sm font-normal text-muted-foreground">
                  · <span className="font-mono text-foreground">{salvo.numero}</span>
                  <Badge variant="outline">{STATUS_LABEL[salvo.status] ?? salvo.status}</Badge>
                </span>
              )}
            </CardTitle>
            <div className="flex items-center gap-2">
              {salvo && (
                <Button type="button" variant="ghost" size="sm" asChild>
                  <Link href="/comunicacao-visual">Novo orçamento</Link>
                </Button>
              )}
              <Button type="button" variant="outline" size="sm" onClick={addItem} disabled={!editavel}>
                <Plus className="mr-1 h-4 w-4" /> Adicionar peça
              </Button>
            </div>
          </CardHeader>

          <CardContent className="space-y-3">
            {orcamentoAusente && (
              <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                Não achei esse orçamento nesta empresa. A calculadora abriu vazia.
              </div>
            )}

            {salvo && !editavel && (
              <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                O {salvo.numero} está {(STATUS_LABEL[salvo.status] ?? salvo.status).toLowerCase()} e não muda mais.
                Dá pra conferir as contas; para outro preço, comece um orçamento novo.
              </div>
            )}

            {semCatalogo && (
              <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                Você ainda não tem materiais cadastrados. Pode digitar o preço por m² na mão
                em cada peça — quando cadastrar o catálogo, ele aparece aqui pra escolher.
              </div>
            )}

            {/* Cabeçalho das colunas (>= md) */}
            <div aria-hidden="true" className="hidden gap-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground md:grid md:grid-cols-12">
              <span className="md:col-span-3">Material</span>
              <span className="md:col-span-3">Descrição</span>
              <span className="md:col-span-1">Larg. (m)</span>
              <span className="md:col-span-1">Alt. (m)</span>
              <span className="md:col-span-1">Qtd</span>
              <span className="md:col-span-1">R$/m²</span>
              <span className="md:col-span-1 text-right">m²</span>
              <span className="md:col-span-1 text-right">Subtotal</span>
            </div>

            {itens.map((item, idx) => {
              const area = areaDe(item);
              const subtotal = subtotalDe(item);
              const doServidor = conferido?.margem ? conferido.itens[idx] : undefined;
              return (
                <div
                  key={item.id}
                  className="grid grid-cols-2 items-end gap-2 rounded-md border border-border bg-muted/30 p-3 md:grid-cols-12"
                >
                  {/* Material */}
                  <div className="col-span-2 md:col-span-3">
                    <Label className="text-xs md:hidden">Material</Label>
                    {/* eslint-disable-next-line no-restricted-syntax -- select nativo: linha de grid densa multi-item (calculadora m²), estilizado com tokens DS; migração p/ <Select> shadcn é Wave 2 */}
                    <select
                      className="h-9 w-full rounded-md border border-input bg-background px-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                      value={item.material_id ?? ''}
                      onChange={(e) =>
                        escolherMaterial(item.id, e.target.value ? Number(e.target.value) : null)
                      }
                      disabled={semCatalogo}
                      aria-label="Material"
                    >
                      <option value="">{semCatalogo ? 'Sem catálogo' : 'Preço avulso'}</option>
                      {materiais.map((m) => (
                        <option key={m.id} value={m.id}>
                          {m.nome} · {BRL.format(m.preco_venda_m2)}/m²
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Descrição */}
                  <div className="col-span-2 md:col-span-3">
                    <Label htmlFor={`desc-${item.id}`} className="text-xs md:hidden">Descrição</Label>
                    <Input
                      id={`desc-${item.id}`}
                      aria-label="Descrição da peça"
                      value={item.descricao}
                      onChange={(e) => patchItem(item.id, { descricao: e.target.value })}
                      placeholder="Ex: Banner fachada loja"
                    />
                  </div>

                  {/* Largura */}
                  <div className="md:col-span-1">
                    <Label htmlFor={`larg-${item.id}`} className="text-xs md:hidden">Largura (m)</Label>
                    <Input
                      id={`larg-${item.id}`}
                      aria-label="Largura em metros"
                      type="number"
                      inputMode="decimal"
                      min={0}
                      step="0.01"
                      value={item.largura_m}
                      onChange={(e) => patchItem(item.id, { largura_m: Number(e.target.value) || 0 })}
                    />
                  </div>

                  {/* Altura */}
                  <div className="md:col-span-1">
                    <Label htmlFor={`alt-${item.id}`} className="text-xs md:hidden">Altura (m)</Label>
                    <Input
                      id={`alt-${item.id}`}
                      aria-label="Altura em metros"
                      type="number"
                      inputMode="decimal"
                      min={0}
                      step="0.01"
                      value={item.altura_m}
                      onChange={(e) => patchItem(item.id, { altura_m: Number(e.target.value) || 0 })}
                    />
                  </div>

                  {/* Quantidade */}
                  <div className="md:col-span-1">
                    <Label htmlFor={`qtd-${item.id}`} className="text-xs md:hidden">Qtd</Label>
                    <Input
                      id={`qtd-${item.id}`}
                      aria-label="Quantidade de peças"
                      type="number"
                      inputMode="numeric"
                      min={1}
                      step="1"
                      value={item.quantidade}
                      onChange={(e) =>
                        patchItem(item.id, { quantidade: Math.max(1, Math.floor(Number(e.target.value) || 1)) })
                      }
                    />
                  </div>

                  {/* Preço/m² */}
                  <div className="md:col-span-1">
                    <Label htmlFor={`preco-${item.id}`} className="text-xs md:hidden">Preço por m²</Label>
                    <Input
                      id={`preco-${item.id}`}
                      aria-label="Preço por metro quadrado"
                      aria-invalid={algumOk && !itemOk(item) ? true : undefined}
                      type="number"
                      inputMode="decimal"
                      min={0}
                      step="0.01"
                      value={item.preco_unitario_m2}
                      onChange={(e) =>
                        patchItem(item.id, { preco_unitario_m2: Number(e.target.value) || 0 })
                      }
                    />
                  </div>

                  {/* m² calculado */}
                  <div className="text-right md:col-span-1">
                    <Label className="text-xs text-muted-foreground md:hidden">Área</Label>
                    <p className="text-sm font-medium tabular-nums text-foreground">
                      {NUM.format(area)} m²
                    </p>
                  </div>

                  {/* Subtotal + remover */}
                  <div className="flex items-center justify-end gap-1 md:col-span-1">
                    <div className="text-right">
                      <Label className="text-xs text-muted-foreground md:hidden">Subtotal</Label>
                      <p className="text-sm font-semibold tabular-nums text-foreground">
                        {BRL.format(subtotal)}
                      </p>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      onClick={() => removeItem(item.id)}
                      disabled={itens.length === 1}
                      aria-label="Remover peça"
                      className="text-muted-foreground hover:text-destructive"
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>

                  {/* UC-FLX-03: custo e margem da peça, do servidor (mesma regra que o orçamento grava) */}
                  {doServidor && (
                    <p data-contract="margem-item" className="col-span-2 text-xs text-muted-foreground md:col-span-12 md:text-right">
                      {doServidor.custo == null ? (
                        'Sem custo cadastrado para este material — a margem desta peça não aparece.'
                      ) : (
                        <>
                          Custo <span className="tabular-nums text-foreground">{BRL.format(doServidor.custo)}</span>
                          {' · '}Margem{' '}
                          <span className={`tabular-nums font-medium ${(doServidor.margem ?? 0) < 0 ? 'text-destructive' : 'text-foreground'}`}>
                            {BRL.format(doServidor.margem ?? 0)}
                            {doServidor.margem_pct != null && ` (${PCT.format(doServidor.margem_pct)}%)`}
                          </span>
                        </>
                      )}
                    </p>
                  )}
                </div>
              );
            })}

            {/* Ajustes gerais */}
            <div data-contract="ajustes" className="grid grid-cols-1 gap-3 pt-1 sm:grid-cols-2">
              <div>
                <Label htmlFor="extras" className="text-xs">
                  Acabamento / instalação / entrega (R$)
                </Label>
                <Input
                  id="extras"
                  type="number"
                  inputMode="decimal"
                  min={0}
                  step="0.01"
                  value={extras}
                  onChange={(e) => {
                    setExtras(Number(e.target.value) || 0);
                    mexeu();
                  }}
                  placeholder="0,00"
                />
              </div>
              <div>
                <Label htmlFor="desconto" className="text-xs">
                  Desconto (R$)
                </Label>
                <Input
                  id="desconto"
                  type="number"
                  inputMode="decimal"
                  min={0}
                  step="0.01"
                  value={desconto}
                  onChange={(e) => {
                    setDesconto(Number(e.target.value) || 0);
                    mexeu();
                  }}
                  placeholder="0,00"
                />
              </div>
            </div>

            {/* Arquivos do orçamento (UC-FLX-01) — a arte vai para a OS junto com as peças */}
            <div data-contract="arquivos" className="space-y-2 rounded-md border border-dashed border-border p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="flex items-center gap-2 text-sm font-medium text-foreground">
                  <Paperclip className="h-4 w-4 text-primary" aria-hidden="true" />
                  Arquivos do orçamento
                </span>
                {salvo && podeCriar && !osCriada && APROVAVEIS.includes(salvo.status) && (
                  <label className="inline-flex cursor-pointer items-center gap-1 rounded-md border border-input bg-background px-3 py-1.5 text-sm hover:bg-muted focus-within:ring-2 focus-within:ring-ring">
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    {enviandoArquivo ? 'Enviando…' : 'Anexar arquivo'}
                    <input
                      type="file"
                      multiple
                      accept={EXTENSOES_ARQUIVO}
                      className="sr-only"
                      disabled={enviandoArquivo}
                      onChange={(e) => {
                        void enviarArquivos(e.target.files);
                        e.target.value = '';
                      }}
                    />
                  </label>
                )}
              </div>
              {!salvo ? (
                <p className="text-xs text-muted-foreground">
                  Salve o orçamento para anexar a arte ou a foto do local. Ao aprovar, os arquivos vão para a OS.
                </p>
              ) : arquivos.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                  Nenhum arquivo. PDF, imagem, AI, EPS, CDR, TIF ou PSD, até 5 MB cada.
                </p>
              ) : (
                <ul className="space-y-1">
                  {arquivos.map((a) => (
                    <li key={a.id} className="flex items-center gap-2 text-sm">
                      <a href={a.url} target="_blank" rel="noreferrer" className="truncate text-primary underline-offset-2 hover:underline">
                        {a.nome}
                      </a>
                      {podeCriar && !osCriada && (
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="ml-auto h-7 w-7 text-muted-foreground hover:text-destructive"
                          aria-label={`Remover ${a.nome}`}
                          onClick={() => void removerArquivo(a.id)}
                        >
                          <X className="h-4 w-4" />
                        </Button>
                      )}
                    </li>
                  ))}
                </ul>
              )}
              {osCriada && arquivos.length > 0 && (
                <p className="text-xs text-muted-foreground">Estes arquivos foram para a OS {osCriada}.</p>
              )}
            </div>

            {erro && (
              <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {erro}
              </div>
            )}

            {/* Total + ação */}
            <div data-contract="total" className="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-end sm:justify-between">
              <div className="space-y-1">
                <div className="flex items-baseline gap-2 text-sm text-muted-foreground">
                  <span>Subtotal</span>
                  <span className="tabular-nums">{BRL.format(subtotalLocal)}</span>
                </div>
                <div className="flex items-baseline gap-2">
                  <span className="text-sm text-muted-foreground">Total estimado</span>
                  <span className="text-2xl font-semibold tabular-nums text-foreground">
                    {BRL.format(totalLocal)}
                  </span>
                </div>
                {conferido?.margem && (
                  <p data-contract="margem" className="text-xs text-muted-foreground">
                    {conferido.margem.margem == null ? (
                      `Custo cadastrado em ${conferido.margem.itens_com_custo} de ${conferido.margem.itens} ${conferido.margem.itens === 1 ? 'peça' : 'peças'} — a margem do orçamento aparece quando todas tiverem custo.`
                    ) : (
                      <>
                        Custo das peças <span className="tabular-nums text-foreground">{BRL.format(conferido.margem.custo ?? 0)}</span>
                        {' · '}Margem{' '}
                        <span className={`tabular-nums font-semibold ${conferido.margem.margem < 0 ? 'text-destructive' : 'text-foreground'}`}>
                          {BRL.format(conferido.margem.margem)}
                          {conferido.margem.margem_pct != null && ` (${PCT.format(conferido.margem.margem_pct)}%)`}
                        </span>
                        <span> sobre as peças menos o desconto; acabamento, instalação e entrega ficam fora.</span>
                      </>
                    )}
                  </p>
                )}
                {totalLocal < 0 && (
                  <p className="text-xs text-destructive">
                    O desconto está maior que o valor do orçamento — o total fica negativo.
                  </p>
                )}
                {osCriada && (
                  <p data-contract="os-criada" className="flex items-center gap-1.5 text-xs">
                    <Badge className="bg-success-soft text-success-fg border-success/20">
                      Aprovado · OS {osCriada} criada
                    </Badge>
                    <span className="text-muted-foreground">vai para a arte com as peças, medidas e arquivos.</span>
                  </p>
                )}
                {conferido && (
                  <p className="flex items-center gap-1.5 text-xs">
                    {Math.abs(conferido.total - totalLocal) < 0.01 ? (
                      <Badge className="bg-success-soft text-success-fg border-success/20">
                        Conferido: {BRL.format(conferido.total)}
                      </Badge>
                    ) : (
                      <Badge variant="outline">
                        Servidor: {BRL.format(conferido.total)} (vale este)
                      </Badge>
                    )}
                  </p>
                )}
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={conferirNoServidor}
                  disabled={!temItemValido || conferindo}
                >
                  {conferindo ? (
                    <>Conferindo…</>
                  ) : (
                    <>
                      <ServerCog className="mr-2 h-4 w-4" />
                      Conferir no servidor
                    </>
                  )}
                </Button>
                {podeCriar && !podeAprovar && !osCriada && (
                  <Button type="button" onClick={salvarOrcamento} disabled={!podeSalvar || salvando}>
                    <Save className="mr-2 h-4 w-4" />
                    {salvando ? 'Salvando…' : salvo ? 'Salvar alterações' : 'Salvar orçamento'}
                  </Button>
                )}
                {podeAprovar && !confirmandoAprovar && (
                  <Button type="button" data-contract="aprovar" onClick={() => setConfirmandoAprovar(true)}>
                    <CheckCircle2 className="mr-2 h-4 w-4" />
                    Aprovar e criar OS
                  </Button>
                )}
                {podeAprovar && confirmandoAprovar && (
                  <>
                    <Button type="button" variant="ghost" onClick={() => setConfirmandoAprovar(false)} disabled={aprovando}>
                      Cancelar
                    </Button>
                    <Button type="button" data-contract="aprovar" onClick={aprovarOrcamento} disabled={aprovando}>
                      <CheckCircle2 className="mr-2 h-4 w-4" />
                      {aprovando ? 'Aprovando…' : `Confirmar: aprovar ${salvo?.numero}`}
                    </Button>
                  </>
                )}
              </div>
            </div>

            <p className="text-xs text-muted-foreground">
              O cálculo na tela é só uma prévia. Ao conferir no servidor, o valor oficial é
              recalculado com as regras da sua loja.
              {podeVerCusto && !conferido && ' Ao conferir, aparecem o custo e a margem de cada peça.'}
              {podeCriar && editavel && !conferido && ' Salvar só libera depois de conferir: orçamento salvo com número errado vira retrabalho no balcão.'}
              {salvo && !alterado && !osCriada && ` Salvo como ${salvo.numero} — reabra quando quiser pela lista de orçamentos salvos.`}
              {podeAprovar && ' Aprovar cria a OS com o total gravado e não volta atrás.'}
              {salvo && alterado && editavel && ` Há mudanças que ainda não estão no ${salvo.numero}.`}
            </p>
          </CardContent>
        </Card>

        {/* Orçamentos salvos — reabrir na calculadora (deferido: lista não entra no 1º paint) */}
        <Card data-contract="orcamentos-salvos">
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-sm font-medium">
              <FolderOpen className="h-4 w-4 text-primary" />
              Orçamentos salvos
            </CardTitle>
          </CardHeader>
          <CardContent>
            <Deferred data="recentes" fallback={<p className="text-sm text-muted-foreground">Carregando orçamentos…</p>}>
              <ListaOrcamentos itens={recentes ?? []} abertoId={salvo?.id ?? null} />
            </Deferred>
          </CardContent>
        </Card>

        {/* Áreas que chegam em breve — honesto, sem jargão técnico */}
        <Card data-contract="em-breve">
          <CardHeader>
            <CardTitle className="text-sm font-medium text-muted-foreground">
              Em breve nesta tela
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <ProximaArea
                icon={<Ruler className="h-4 w-4" />}
                titulo="Ordens de serviço"
                texto="Acompanhar cada trabalho da arte até a entrega, num quadro simples."
              />
              <ProximaArea
                icon={<Plus className="h-4 w-4" />}
                titulo="Materiais"
                texto="Cadastrar lona, vinil e ACM com o preço por m² pra calcular ainda mais rápido."
              />
              <ProximaArea
                icon={<Clock className="h-4 w-4" />}
                titulo="Apontamentos"
                texto="Registrar início e fim de cada impressão pra saber quanto cada peça custou de verdade."
              />
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

function ListaOrcamentos({ itens, abertoId }: { itens: OrcamentoResumo[]; abertoId: number | null }) {
  if (itens.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        Nenhum orçamento salvo ainda. Monte as peças acima, confira no servidor e salve.
      </p>
    );
  }
  return (
    <ul className="divide-y divide-border">
      {itens.map((o) => (
        <li key={o.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 py-2 text-sm">
          <span className="font-mono text-foreground">{o.numero}</span>
          <Badge variant="outline">{STATUS_LABEL[o.status] ?? o.status}</Badge>
          {o.os && <span className="font-mono text-xs text-muted-foreground">{o.os}</span>}
          <span className="text-muted-foreground">
            {o.data_emissao ? o.data_emissao.split('-').reverse().join('/') : '—'} · {o.itens}{' '}
            {o.itens === 1 ? 'peça' : 'peças'}
          </span>
          <span className="ml-auto tabular-nums font-medium text-foreground">{BRL.format(o.total)}</span>
          {o.id === abertoId ? (
            <span className="text-xs text-muted-foreground">aberto</span>
          ) : (
            <Button type="button" variant="ghost" size="sm" asChild>
              <Link href={`/comunicacao-visual?orcamento=${o.id}`}>Abrir</Link>
            </Button>
          )}
        </li>
      ))}
    </ul>
  );
}

function ProximaArea({ icon, titulo, texto }: { icon: ReactNode; titulo: string; texto: string }) {
  return (
    <div className="rounded-lg border border-border bg-card p-4">
      <div className="flex items-center gap-2">
        <span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10 text-primary">
          {icon}
        </span>
        <h3 className="text-sm font-medium text-foreground">{titulo}</h3>
        <Badge variant="secondary" className="ml-auto text-[10px]">
          em breve
        </Badge>
      </div>
      <p className="mt-2 text-xs leading-relaxed text-muted-foreground">{texto}</p>
    </div>
  );
}

Index.layout = (page: ReactNode) => (
  <AppShellV2
    title="Comunicação Visual"
    breadcrumbItems={[{ label: 'Comunicação Visual' }, { label: 'Orçamento por m²' }]}
  >
    {page}
  </AppShellV2>
);
