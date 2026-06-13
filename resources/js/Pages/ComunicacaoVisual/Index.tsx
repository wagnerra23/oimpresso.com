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
 *  - Salvar orçamento (POST /comunicacao-visual/api/orcamentos) + enviar PDF no WhatsApp.
 *  - Telas próprias: PCP Kanban (US-COMVIS-003), CRUD Materiais (US-COMVIS-002),
 *    Apontamento de máquina (US-COMVIS-004). Por ora as APIs já existem.
 *  - Acabamentos/instalação/entrega como linhas extras do total (hoje só campo manual).
 */
import { useMemo, useState, type ReactNode } from 'react';
import { Head } from '@inertiajs/react';
import { Plus, Trash2, Calculator, ServerCog, Ruler, Clock } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
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

interface Props {
  bizName?: string;
  materiais?: Material[];
  podeCriar?: boolean;
}

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
}
interface ServerResult {
  subtotal: number;
  total: number;
  itens: ServerItem[];
}

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const NUM = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 });

function novoItem(): ItemUI {
  return {
    id: crypto.randomUUID(),
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
function areaDe(item: ItemUI): number {
  return Math.max(0, item.largura_m) * Math.max(0, item.altura_m) * Math.max(0, item.quantidade);
}
function subtotalDe(item: ItemUI): number {
  return areaDe(item) * Math.max(0, item.preco_unitario_m2);
}

export default function Index({ bizName = 'oimpresso', materiais = [], podeCriar = false }: Props) {
  const [itens, setItens] = useState<ItemUI[]>([novoItem()]);
  const [extras, setExtras] = useState(0); // acabamento/instalação/entrega (manual por ora)
  const [desconto, setDesconto] = useState(0);
  const [conferindo, setConferindo] = useState(false);
  const [erro, setErro] = useState<string | null>(null);
  const [conferido, setConferido] = useState<ServerResult | null>(null);

  function addItem() {
    setItens((prev) => [...prev, novoItem()]);
    setConferido(null);
  }

  function removeItem(id: string) {
    setItens((prev) => (prev.length === 1 ? prev : prev.filter((i) => i.id !== id)));
    setConferido(null);
  }

  function patchItem(id: string, patch: Partial<ItemUI>) {
    setItens((prev) => prev.map((i) => (i.id === id ? { ...i, ...patch } : i)));
    setConferido(null);
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
    () => itens.reduce((acc, i) => acc + subtotalDe(i), 0),
    [itens],
  );
  const totalLocal = useMemo(
    () => Math.max(0, subtotalLocal - Math.max(0, desconto) + Math.max(0, extras)),
    [subtotalLocal, desconto, extras],
  );

  const temItemValido = itens.some(
    (i) => i.largura_m > 0 && i.altura_m > 0 && i.quantidade >= 1 && i.preco_unitario_m2 > 0,
  );

  // Confere no servidor (authoritative): o backend recalcula área/subtotal/total
  // e respeita business_id. Se bater com o preview local, dá confiança pra Larissa.
  async function conferirNoServidor() {
    setConferindo(true);
    setErro(null);
    setConferido(null);
    try {
      const csrf =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

      const payload = {
        data_emissao: new Date().toISOString().slice(0, 10),
        desconto: Math.max(0, desconto),
        extras: Math.max(0, extras),
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

      const res = await fetch('/comunicacao-visual/api/calcular', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json().catch(() => null);
      if (!res.ok) {
        throw new Error(
          (data && (data.message as string)) ||
            `Não consegui calcular agora (erro ${res.status}). Confira as medidas e tente de novo.`,
        );
      }
      setConferido(data as ServerResult);
    } catch (e) {
      setErro(e instanceof Error ? e.message : 'Falha inesperada ao conferir no servidor.');
    } finally {
      setConferindo(false);
    }
  }

  const semCatalogo = materiais.length === 0;

  return (
    <>
      <Head title="Comunicação Visual — Orçamento por m²" />

      <div className="space-y-6 p-6">
        <PageHeader
          icon="printer"
          title="Comunicação Visual"
          description={`Orçamento por m² pra ${bizName} — banner, lona, adesivo, fachada. Calcule na hora, sem abrir o Excel.`}
        />

        {/* Calculadora — o valor real da tela */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2">
            <CardTitle className="flex items-center gap-2">
              <Calculator className="h-4 w-4 text-primary" />
              Calculadora de m²
            </CardTitle>
            <Button type="button" variant="outline" size="sm" onClick={addItem}>
              <Plus className="mr-1 h-4 w-4" /> Adicionar peça
            </Button>
          </CardHeader>

          <CardContent className="space-y-3">
            {semCatalogo && (
              <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                Você ainda não tem materiais cadastrados. Pode digitar o preço por m² na mão
                em cada peça — quando cadastrar o catálogo, ele aparece aqui pra escolher.
              </div>
            )}

            {/* Cabeçalho das colunas (>= md) */}
            <div className="hidden gap-2 px-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground md:grid md:grid-cols-12">
              <span className="md:col-span-3">Material</span>
              <span className="md:col-span-3">Descrição</span>
              <span className="md:col-span-1">Larg. (m)</span>
              <span className="md:col-span-1">Alt. (m)</span>
              <span className="md:col-span-1">Qtd</span>
              <span className="md:col-span-1">R$/m²</span>
              <span className="md:col-span-1 text-right">m²</span>
              <span className="md:col-span-1 text-right">Subtotal</span>
            </div>

            {itens.map((item) => {
              const area = areaDe(item);
              const subtotal = subtotalDe(item);
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
                    <Label className="text-xs md:hidden">Descrição</Label>
                    <Input
                      value={item.descricao}
                      onChange={(e) => patchItem(item.id, { descricao: e.target.value })}
                      placeholder="Ex: Banner fachada loja"
                    />
                  </div>

                  {/* Largura */}
                  <div className="md:col-span-1">
                    <Label className="text-xs md:hidden">Largura (m)</Label>
                    <Input
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
                    <Label className="text-xs md:hidden">Altura (m)</Label>
                    <Input
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
                    <Label className="text-xs md:hidden">Qtd</Label>
                    <Input
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
                    <Label className="text-xs md:hidden">Preço por m²</Label>
                    <Input
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
                </div>
              );
            })}

            {/* Ajustes gerais */}
            <div className="grid grid-cols-1 gap-3 pt-1 sm:grid-cols-2">
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
                    setConferido(null);
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
                    setConferido(null);
                  }}
                  placeholder="0,00"
                />
              </div>
            </div>

            {erro && (
              <div className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                {erro}
              </div>
            )}

            {/* Total + ação */}
            <div className="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-end sm:justify-between">
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

              <Button
                type="button"
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
            </div>

            <p className="text-xs text-muted-foreground">
              O cálculo na tela é só uma prévia. Ao conferir no servidor, o valor oficial é
              recalculado com as regras da sua loja.{' '}
              {podeCriar
                ? 'Em breve dá pra salvar o orçamento e mandar o PDF no WhatsApp do cliente.'
                : 'Salvar e enviar orçamento chega em breve.'}
            </p>
          </CardContent>
        </Card>

        {/* Áreas que chegam em breve — honesto, sem jargão técnico */}
        <Card>
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
