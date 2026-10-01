// @patrimonio · thread 18 — drawers de Alocações: ALOCAR (novo e editar) e DEVOLVER.
//
// fonte visual: prototipo-ui/cowork/Wagner/patrimonio-forms.jsx (`AlocarForm` :120,
// `RevogarForm` :184) — Drawer PT-02 do DS, aqui montado no `Sheet` da casa (ADR 0414).
//
// Um drawer nasce ABERTO porque o servidor mandou a prop `formulario` (rotas `create`/`edit`
// dos dois controllers renderizam a Page Alocações — mesmo desenho da tela Bens, thread 17).
// Fechar volta pra `/asset/allocation`. O `Sheet` (Radix Dialog) dá `aria-modal`, prende e
// devolve o foco; os erros saem em `role="alert"`, que é anúncio assertivo.
//
// Divergências conscientes do protótipo, cada uma com motivo:
//   • "Alocar para" lista USUÁRIOS do business (`User::forDropdown`, o que o Blade listava) —
//     o protótipo desenha colaborador com papel, campo que o modelo não tem;
//   • "Razão" é opcional: o Blade e o servidor aceitam vazio. Obrigar no cliente seria regra
//     de negócio que só existe de um lado;
//   • o drawer de devolução LISTA as devoluções já feitas, com EXCLUIR — o protótipo modela
//     1 : 1, o nosso modelo é 1 : N (`_saida-16`), e excluir é o que a 16 precisa antes de
//     redirecionar `/asset/revocation` (decisão [W] em `_saida-16b`).

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline, Stack } from '@/Components/layout';
import {
  CASAS_QUANTIDADE,
  agoraLocal,
  montarEnvioAlocacao,
  montarEnvioDevolucao,
  validarAlocacao,
  validarDevolucao,
  type FormAlocacao,
  type FormDevolucao,
} from './envio';

/* ─── Contrato com o backend (prop `formulario`) ─────────────────────────────── */

export interface BemParaAlocar { id: number; nome: string; saldo: number }
export interface Pessoa { id: number; nome: string }

export interface FormularioAlocar {
  modo: 'alocar' | 'editar';
  bens: BemParaAlocar[];
  pessoas: Pessoa[];
  asset_id: number | null;
  alocacao?: {
    id: number; ref_no: string; receiver: number; quantidade: number;
    alocado_em: string; prazo: string; motivo: string;
  };
}

export interface Devolucao {
  id: number; ref_no: string; quantidade: number; data: string | null; autor: string; motivo: string | null;
}

export interface FormularioDevolver {
  modo: 'devolver';
  alocacao: {
    id: number; ref_no: string; bem: string; recebido_por: string;
    quantidade: number; devolvido: number; restante: number;
  };
  devolucoes: Devolucao[];
}

export type Formulario = FormularioAlocar | FormularioDevolver;

interface Formato { formatoData: string; hora12: boolean; onClose: () => void }

/* ─── Peças ───────────────────────────────────────────────────────────────────── */

const qtd = (n: number) => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(n ?? 0);

function Campo({ id, rotulo, erro, ajuda, children }: {
  id: string; rotulo: string; erro?: string; ajuda?: string; children: React.ReactNode;
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

function Opcoes({ id, valor, onChange, opcoes, placeholder, invalido }: {
  id: string; valor: string; onChange: (v: string) => void;
  opcoes: Array<{ value: string; label: string }>; placeholder: string; invalido?: boolean;
}) {
  return (
    <Select value={valor} onValueChange={onChange}>
      <SelectTrigger id={id} aria-invalid={invalido || undefined}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {opcoes.map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
      </SelectContent>
    </Select>
  );
}

function ErroGeral({ msg }: { msg?: string }) {
  return msg ? (
    <p role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm">{msg}</p>
  ) : null;
}

/** Erros do servidor (chaves do POST) → campos do form. `quantity` é onde a trava fala. */
function mapearErros(server: Record<string, string>, mapa: Record<string, string>): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, msg] of Object.entries(server)) out[mapa[k] ?? 'geral'] = msg;
  return out;
}

/** O `store()`/`update()` também redirecionam quando FALHAM, com flash de erro: o drawer fica aberto. */
function flashDeErro(page: unknown): string | null {
  const flash = (page as { props?: { flash?: { error?: string | null } } }).props?.flash;
  return flash?.error ?? null;
}

/* ─── Alocar / editar ─────────────────────────────────────────────────────────── */

export function AlocarDrawer({ formulario, formatoData, hora12, onClose }: Formato & { formulario: FormularioAlocar }) {
  const editar = formulario.modo === 'editar' && formulario.alocacao;
  const a = formulario.alocacao;
  const [f, setF] = useState<FormAlocacao>(() => ({
    assetId: formulario.asset_id ? String(formulario.asset_id) : '',
    receiver: a ? String(a.receiver) : '',
    quantidade: a ? a.quantidade : 1,
    alocadoEm: a?.alocado_em || agoraLocal(),
    prazo: a?.prazo ?? '',
    motivo: a?.motivo ?? '',
    refNo: '',
  }));
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);
  const set = <K extends keyof FormAlocacao>(k: K, v: FormAlocacao[K]) => setF((o) => ({ ...o, [k]: v }));
  const bem = formulario.bens.find((b) => String(b.id) === f.assetId);

  const salvar = () => {
    if (enviando) return;
    const e = validarAlocacao(f);
    setErros(e);
    if (Object.keys(e).length) return;
    setEnviando(true);
    const corpo = montarEnvioAlocacao(f, formatoData, hora12);
    const opcoes = {
      preserveScroll: true,
      onSuccess: (page: unknown) => {
        const erro = flashDeErro(page);
        if (erro) setErros({ geral: erro });
      },
      onError: (server: Record<string, string>) => setErros(mapearErros(server, {
        asset_id: 'assetId', receiver: 'receiver', quantity: 'quantidade',
        transaction_datetime: 'alocadoEm', allocated_upto: 'prazo',
      })),
      onFinish: () => setEnviando(false),
    };
    if (editar && a) router.put(`/asset/allocation/${a.id}`, corpo, opcoes);
    else router.post('/asset/allocation', corpo, opcoes);
  };

  return (
    <Sheet open onOpenChange={(o) => { if (!o && !enviando) onClose(); }}>
      <SheetContent side="right" className="w-full gap-0 sm:max-w-[560px]" data-testid="drawer-alocar">
        <SheetHeader className="border-b">
          <SheetTitle>{editar ? `Editar alocação ${a?.ref_no}` : 'Alocar recurso'}</SheetTitle>
          <SheetDescription>
            Só bem atribuível entra na lista. O saldo é conferido no servidor ao salvar.
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto p-4">
          <Stack gap={4}>
            <ErroGeral msg={erros.geral} />
            {formulario.bens.length === 0 ? (
              <p role="status" className="rounded-md border px-3 py-2 text-sm">
                Nenhum bem atribuível nesta empresa. Marque "É atribuível?" no cadastro do bem para ele aparecer aqui.
              </p>
            ) : null}
            <Grid cols={2} gap={3}>
              <div className="col-span-2">
                <Campo id="al-bem" rotulo="Bem" erro={erros.assetId}
                  ajuda={bem ? `Saldo livre agora: ${qtd(bem.saldo)}` : undefined}>
                  <Opcoes id="al-bem" valor={f.assetId} onChange={(v) => set('assetId', v)} invalido={!!erros.assetId}
                    placeholder="Escolha o bem"
                    opcoes={formulario.bens.map((b) => ({ value: String(b.id), label: `${b.nome} (${qtd(b.saldo)} livre)` }))} />
                </Campo>
              </div>
              <Campo id="al-para" rotulo="Alocar para" erro={erros.receiver}>
                <Opcoes id="al-para" valor={f.receiver} onChange={(v) => set('receiver', v)} invalido={!!erros.receiver}
                  placeholder="Escolha a pessoa"
                  opcoes={formulario.pessoas.map((p) => ({ value: String(p.id), label: p.nome }))} />
              </Campo>
              <Campo id="al-qtd" rotulo="Quantidade alocada" erro={erros.quantidade}>
                <NumericInputPtBR id="al-qtd" value={f.quantidade} precision={CASAS_QUANTIDADE}
                  aria-invalid={!!erros.quantidade || undefined} onChange={(n) => set('quantidade', n)} />
              </Campo>
              <Campo id="al-em" rotulo="Alocado de" erro={erros.alocadoEm}>
                <Input id="al-em" type="datetime-local" value={f.alocadoEm}
                  aria-invalid={!!erros.alocadoEm || undefined} onChange={(e) => set('alocadoEm', e.target.value)} />
              </Campo>
              <Campo id="al-ate" rotulo="Alocado até" erro={erros.prazo} ajuda="Vazio = indeterminado.">
                <Input id="al-ate" type="date" value={f.prazo}
                  aria-invalid={!!erros.prazo || undefined} onChange={(e) => set('prazo', e.target.value)} />
              </Campo>
              <div className="col-span-2">
                <Campo id="al-motivo" rotulo="Razão">
                  <Textarea id="al-motivo" rows={3} value={f.motivo} placeholder="Ex.: Estação de orçamento do balcão"
                    onChange={(e) => set('motivo', e.target.value)} />
                </Campo>
              </div>
              {!editar ? (
                <div className="col-span-2">
                  <Campo id="al-ref" rotulo="Código da alocação" ajuda="Vazio = gerado pelo prefixo da empresa.">
                    <Input id="al-ref" value={f.refNo} onChange={(e) => set('refNo', e.target.value)} />
                  </Campo>
                </div>
              ) : null}
            </Grid>
          </Stack>
        </div>

        <SheetFooter className="flex-row justify-end border-t">
          <Button variant="ghost" onClick={onClose} disabled={enviando}>Cancelar</Button>
          <Button onClick={salvar} disabled={enviando || formulario.bens.length === 0}>
            {enviando ? 'Salvando…' : editar ? 'Salvar alocação' : 'Alocar'}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

/* ─── Devolver ────────────────────────────────────────────────────────────────── */

export function DevolverDrawer({ formulario, formatoData, hora12, onClose }: Formato & { formulario: FormularioDevolver }) {
  const a = formulario.alocacao;
  const [f, setF] = useState<FormDevolucao>(() => ({
    quantidade: a.restante, devolvidoEm: agoraLocal(), motivo: '', refNo: '',
  }));
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);
  const set = <K extends keyof FormDevolucao>(k: K, v: FormDevolucao[K]) => setF((o) => ({ ...o, [k]: v }));

  const devolver = () => {
    if (enviando) return;
    const e = validarDevolucao(f);
    setErros(e);
    if (Object.keys(e).length) return;
    setEnviando(true);
    router.post('/asset/revocation', montarEnvioDevolucao(a.id, f, formatoData, hora12), {
      preserveScroll: true,
      onSuccess: (page) => {
        const erro = flashDeErro(page);
        if (erro) setErros({ geral: erro });
      },
      onError: (server) => setErros(mapearErros(server, { quantity: 'quantidade', transaction_datetime: 'devolvidoEm' })),
      onFinish: () => setEnviando(false),
    });
  };

  // Desfazer uma devolução errada devolve a quantidade à mão de quem recebeu: é escrita de
  // quantidade, então a confirmação diz O QUÊ e QUANTO — "tem certeza?" genérico não deixa
  // perceber que é a linha errada.
  const excluir = (d: Devolucao) => {
    if (!window.confirm(`Excluir a devolução ${d.ref_no} (${qtd(d.quantidade)} un.)? A quantidade volta a constar com ${a.recebido_por || 'quem recebeu'}.`)) {
      return;
    }
    router.delete(`/asset/revocation/${d.id}`, { preserveScroll: true });
  };

  const encerrada = a.restante <= 0;

  return (
    <Sheet open onOpenChange={(o) => { if (!o && !enviando) onClose(); }}>
      <SheetContent side="right" className="w-full gap-0 sm:max-w-[560px]" data-testid="drawer-devolver">
        <SheetHeader className="border-b">
          <SheetTitle>Devolver {a.ref_no}</SheetTitle>
          <SheetDescription>{a.bem}{a.recebido_por ? ` · alocado a ${a.recebido_por}` : ''}</SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto p-4">
          <Stack gap={5}>
            <ErroGeral msg={erros.geral} />
            <p className="rounded-md border px-3 py-2 text-sm" data-testid="saldo-devolucao">
              {qtd(a.quantidade)} un. alocadas · {qtd(a.devolvido)} já voltaram · <strong>{qtd(a.restante)} a devolver</strong>
            </p>

            {encerrada ? null : (
              <Grid cols={2} gap={3}>
                <Campo id="dv-qtd" rotulo="Quantidade devolvida" erro={erros.quantidade}>
                  <NumericInputPtBR id="dv-qtd" value={f.quantidade} precision={CASAS_QUANTIDADE}
                    aria-invalid={!!erros.quantidade || undefined} onChange={(n) => set('quantidade', n)} />
                </Campo>
                <Campo id="dv-em" rotulo="Devolvido em" erro={erros.devolvidoEm}>
                  <Input id="dv-em" type="datetime-local" value={f.devolvidoEm}
                    aria-invalid={!!erros.devolvidoEm || undefined} onChange={(e) => set('devolvidoEm', e.target.value)} />
                </Campo>
                <div className="col-span-2">
                  <Campo id="dv-motivo" rotulo="Razão">
                    <Input id="dv-motivo" value={f.motivo} placeholder="Ex.: Fim da obra do cliente"
                      onChange={(e) => set('motivo', e.target.value)} />
                  </Campo>
                </div>
              </Grid>
            )}

            <section aria-label="Devoluções desta alocação">
              <Stack gap={2}>
                <h3 className="text-sm font-semibold">Devoluções desta alocação</h3>
                {formulario.devolucoes.length === 0 ? (
                  <small className="text-muted-foreground">Nada voltou ainda.</small>
                ) : (
                  <ul className="divide-y rounded-md border" data-testid="lista-devolucoes">
                    {formulario.devolucoes.map((d) => (
                      <li key={d.id} className="px-3 py-2">
                        <Inline gap={3} align="center" justify="between">
                          <Stack gap={0}>
                            <span className="font-mono text-xs">{d.ref_no}</span>
                            <small className="text-muted-foreground">
                              {qtd(d.quantidade)} un. · {d.data ?? '—'}{d.autor ? ` · ${d.autor}` : ''}
                            </small>
                            {d.motivo ? <small className="break-words">{d.motivo}</small> : null}
                          </Stack>
                          <Button size="sm" variant="ghost" className="text-destructive"
                            aria-label={`Excluir devolução ${d.ref_no}`} onClick={() => excluir(d)}>
                            <Trash2 size={14} aria-hidden="true" /> Excluir
                          </Button>
                        </Inline>
                      </li>
                    ))}
                  </ul>
                )}
              </Stack>
            </section>
          </Stack>
        </div>

        <SheetFooter className="flex-row justify-end border-t">
          <Button variant="ghost" onClick={onClose} disabled={enviando}>{encerrada ? 'Fechar' : 'Cancelar'}</Button>
          {encerrada ? null : (
            <Button variant="destructive" onClick={devolver} disabled={enviando}>
              {enviando ? 'Registrando…' : 'Registrar devolução'}
            </Button>
          )}
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
