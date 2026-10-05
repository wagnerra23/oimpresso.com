// Drawer de pacote (criar e editar) — thread Superadmin 03. Substitui as Blades
// `packages.create` e `packages.edit`.
//
// Layout do protótipo (`PacoteForm` em superadmin-page.jsx: Identidade e preço · Limites · Módulos
// liberados · Visibilidade). Os campos são os que o `store()`/`update()` já aceitam, incluindo os
// da Blade que o protótipo não desenha (ordem na vitrine, link personalizado e "atualizar
// assinaturas vigentes" ao editar) — nada que a Blade fazia some.
//
// PREÇO (regra mestre de valor): a pessoa digita em pt-BR ("49,90"); o envio vai SEMPRE com ponto
// e 2 casas ("49.90"), arredondado a centavo. O servidor lê pelo `num_uf` nos dois caminhos
// (decisão [W] 2026-10-05, RUNBOOK-pacotes §5.1), e "49.90" é lido como 49.9 pelos dois.

import { useState, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { formatDecimalPtBR, parseDecimalPtBR } from '@/Lib/numberPtBR';

export interface PacoteValores {
  id: number;
  name: string;
  description: string;
  price: number;
  interval: string;
  interval_count: number;
  trial_days: number;
  location_count: number;
  user_count: number;
  product_count: number;
  invoice_count: number;
  sort_order: number;
  is_active: boolean;
  is_private: boolean;
  is_one_time: boolean;
  enable_custom_link: boolean;
  custom_link: string;
  custom_link_text: string;
  custom_permissions: Record<string, string | number | boolean | null>;
  assinantes: number;
}

export interface ModuloLiberavel {
  modulo: string;
  nome: string;
  rotulo: string;
  padrao: boolean;
  tipo: 'liga' | 'texto';
}

export interface FormPacote {
  pacote: PacoteValores | null;
  modulos: ModuloLiberavel[];
}

const INTERVALOS = [
  { v: 'days', label: 'Dia' },
  { v: 'months', label: 'Mês' },
  { v: 'years', label: 'Ano' },
];

const soDigitos = (v: string) => v.replace(/\D/g, '').slice(0, 6);

/** Preço digitado em pt-BR → texto canônico com ponto e 2 casas, arredondado a centavo. */
function precoParaEnvio(digitado: string): string {
  const n = parseDecimalPtBR(digitado);
  if (!Number.isFinite(n) || n < 0) return '';
  return (Math.round(n * 100) / 100).toFixed(2);
}

function Casca({ titulo, children, onFechar }: { titulo: string; children: ReactNode; onFechar: () => void }) {
  return (
    <>
      <div className="fixed inset-0 z-40 bg-black/40" onClick={onFechar} aria-hidden="true" />
      <aside
        role="dialog"
        aria-modal="true"
        aria-label={titulo}
        className="fixed inset-y-0 right-0 z-50 flex w-[min(640px,92vw)] flex-col border-l bg-background shadow-2xl"
      >
        {children}
      </aside>
    </>
  );
}

function Campo({ id, rotulo, erro, ajuda, children }: { id: string; rotulo: string; erro?: string; ajuda?: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id} className="text-xs">
        {rotulo}
      </Label>
      {children}
      {erro ? <span className="text-[11px] text-destructive">{erro}</span> : ajuda ? <span className="text-[11px] text-muted-foreground">{ajuda}</span> : null}
    </div>
  );
}

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="border-b px-5 py-4">
      <h3 className="mb-3 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">{titulo}</h3>
      <div className="flex flex-col gap-3">{children}</div>
    </section>
  );
}

function Liga({ id, rotulo, ajuda, ligado, onChange }: { id: string; rotulo: string; ajuda: string; ligado: boolean; onChange: (v: boolean) => void }) {
  return (
    <div className="flex items-start justify-between gap-4">
      <div className="flex flex-col">
        <Label htmlFor={id} className="text-xs">
          {rotulo}
        </Label>
        <span className="text-[11px] text-muted-foreground">{ajuda}</span>
      </div>
      <Switch id={id} checked={ligado} onCheckedChange={onChange} />
    </div>
  );
}

export function PacoteFormEsqueleto({ onFechar }: { onFechar: () => void }) {
  return (
    <Casca titulo="Pacote" onFechar={onFechar}>
      <div className="flex flex-col gap-3 p-5">
        <Skeleton className="h-5 w-40" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    </Casca>
  );
}

export function PacoteNaoEncontrado({ onFechar }: { onFechar: () => void }) {
  return (
    <Casca titulo="Pacote não encontrado" onFechar={onFechar}>
      <div className="flex flex-col gap-3 p-5">
        <p className="text-sm">Pacote não encontrado. Ele pode ter sido excluído enquanto a grade estava aberta.</p>
        <Button variant="outline" onClick={onFechar} className="self-start">
          Fechar
        </Button>
      </div>
    </Casca>
  );
}

export function PacoteForm({ dados, onFechar }: { dados: FormPacote; onFechar: () => void }) {
  const p = dados.pacote;
  const editando = p !== null;

  // Valor inicial dos módulos: o gravado no pacote (edição) ou o `default` do catálogo (novo),
  // como cada Blade fazia.
  const permissoesIniciais: Record<string, string | number> = {};
  for (const m of dados.modulos) {
    const gravado = p?.custom_permissions?.[m.nome];
    if (m.tipo === 'texto') {
      permissoesIniciais[m.nome] = gravado === null || gravado === undefined ? '' : String(gravado);
    } else if (editando ? Boolean(gravado) : m.padrao) {
      permissoesIniciais[m.nome] = 1;
    }
  }

  const form = useForm({
    name: p?.name ?? '',
    description: p?.description ?? '',
    price: p ? formatDecimalPtBR(p.price, 2) : '',
    interval: p?.interval ?? 'months',
    interval_count: String(p?.interval_count ?? 1),
    trial_days: String(p?.trial_days ?? 0),
    location_count: String(p?.location_count ?? 0),
    user_count: String(p?.user_count ?? 0),
    product_count: String(p?.product_count ?? 0),
    invoice_count: String(p?.invoice_count ?? 0),
    sort_order: String(p?.sort_order ?? 1),
    is_active: p?.is_active ?? true,
    is_private: p?.is_private ?? false,
    is_one_time: p?.is_one_time ?? false,
    enable_custom_link: p?.enable_custom_link ?? false,
    custom_link: p?.custom_link ?? '',
    custom_link_text: p?.custom_link_text ?? '',
    custom_permissions: permissoesIniciais,
    update_subscriptions: false,
  });
  const [tocado, setTocado] = useState<Record<string, boolean>>({});

  const d = form.data;
  const setTexto = (k: 'name' | 'description' | 'price' | 'custom_link' | 'custom_link_text') => (v: string) => {
    form.setData(k, v);
    form.clearErrors(k);
    setTocado((t) => ({ ...t, [k]: true }));
  };
  const setNumero = (k: 'interval_count' | 'trial_days' | 'location_count' | 'user_count' | 'product_count' | 'invoice_count' | 'sort_order') => (v: string) => {
    form.setData(k, soDigitos(v));
    setTocado((t) => ({ ...t, [k]: true }));
  };
  const setLiga = (k: 'is_active' | 'is_private' | 'is_one_time' | 'enable_custom_link' | 'update_subscriptions') => (v: boolean) => form.setData(k, v);

  const alternarModulo = (nome: string) => {
    const atual = { ...d.custom_permissions };
    if (atual[nome]) delete atual[nome];
    else atual[nome] = 1;
    form.setData('custom_permissions', atual);
  };
  const setModuloTexto = (nome: string, v: string) => form.setData('custom_permissions', { ...d.custom_permissions, [nome]: v });

  const precoEnvio = precoParaEnvio(d.price);
  const erroLocal: Record<string, string | undefined> = {
    name: tocado.name && !d.name.trim() ? 'O nome aparece na tela de assinatura do cliente.' : undefined,
    description: tocado.description && !d.description.trim() ? 'Diga o que o pacote inclui.' : undefined,
    price: tocado.price && precoEnvio === '' ? 'Preço inválido. Use 0 para pacote gratuito.' : undefined,
    interval_count: tocado.interval_count && Number(d.interval_count) < 1 ? 'Ao menos 1.' : undefined,
  };
  const erro = (k: string) => (form.errors as Record<string, string | undefined>)[k] ?? erroLocal[k];

  const podeSalvar =
    d.name.trim() !== '' &&
    d.description.trim() !== '' &&
    precoEnvio !== '' &&
    Number(d.interval_count) >= 1 &&
    [d.trial_days, d.location_count, d.user_count, d.product_count, d.invoice_count, d.sort_order].every((v) => v !== '');

  const salvar = () => {
    if (!podeSalvar || form.processing) return;
    form.transform((x) => ({ ...x, price: precoParaEnvio(x.price) }));
    if (p) form.put(`/superadmin/packages/${p.id}`, { preserveScroll: true });
    else form.post('/superadmin/packages', { preserveScroll: true });
  };

  const titulo = editando ? 'Editar pacote' : 'Novo pacote';
  const liga = dados.modulos.filter((m) => m.tipo === 'liga');
  const texto = dados.modulos.filter((m) => m.tipo === 'texto');

  return (
    <Casca titulo={titulo} onFechar={onFechar}>
      <header className="flex items-start justify-between gap-3 border-b px-5 py-4">
        <div className="min-w-0">
          <h2 className="text-base font-semibold">{titulo}</h2>
          <p className="mt-0.5 text-[11px] text-muted-foreground">
            {p
              ? `${p.assinantes} ${p.assinantes === 1 ? 'assinatura usa' : 'assinaturas usam'} este pacote`
              : 'Limites com 0 valem como ilimitado'}
          </p>
        </div>
        <Button variant="outline" size="sm" className="h-8 shrink-0 text-xs" onClick={onFechar} title="Fechar (esc)">
          Fechar
        </Button>
      </header>

      <div className="flex-1 overflow-y-auto" data-contract="superadmin.pacotes.form">
        <Secao titulo="Identidade e preço">
          <Campo id="pc-nome" rotulo="Nome do pacote" erro={erro('name')}>
            <Input id="pc-nome" value={d.name} onChange={(e) => setTexto('name')(e.target.value)} />
          </Campo>
          <Campo id="pc-desc" rotulo="Descrição" erro={erro('description')}>
            <Input id="pc-desc" value={d.description} onChange={(e) => setTexto('description')(e.target.value)} />
          </Campo>
          <div className="grid grid-cols-2 gap-3">
            <Campo id="pc-preco" rotulo="Preço (R$)" erro={erro('price')} ajuda="0 = pacote gratuito.">
              <Input id="pc-preco" value={d.price} onChange={(e) => setTexto('price')(e.target.value)} inputMode="decimal" className="tabular-nums" />
            </Campo>
            <Campo id="pc-teste" rotulo="Teste (dias)" erro={erro('trial_days')}>
              <Input id="pc-teste" value={d.trial_days} onChange={(e) => setNumero('trial_days')(e.target.value)} inputMode="numeric" className="tabular-nums" />
            </Campo>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Campo id="pc-intervalo" rotulo="Cobrança por" erro={erro('interval')}>
              <Select value={d.interval} onValueChange={(v) => form.setData('interval', v)}>
                <SelectTrigger id="pc-intervalo" className="h-9 w-full text-xs">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {INTERVALOS.map((i) => (
                    <SelectItem key={i.v} value={i.v}>
                      {i.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Campo>
            <Campo id="pc-periodos" rotulo="Períodos por ciclo" erro={erro('interval_count')} ajuda="2 com cobrança mensal = cobra a cada 2 meses.">
              <Input id="pc-periodos" value={d.interval_count} onChange={(e) => setNumero('interval_count')(e.target.value)} inputMode="numeric" className="tabular-nums" />
            </Campo>
          </div>
        </Secao>

        <Secao titulo="Limites">
          <div className="grid grid-cols-2 gap-3">
            <Campo id="pc-locais" rotulo="Locais" erro={erro('location_count')}>
              <Input id="pc-locais" value={d.location_count} onChange={(e) => setNumero('location_count')(e.target.value)} inputMode="numeric" className="tabular-nums" />
            </Campo>
            <Campo id="pc-usuarios" rotulo="Usuários" erro={erro('user_count')}>
              <Input id="pc-usuarios" value={d.user_count} onChange={(e) => setNumero('user_count')(e.target.value)} inputMode="numeric" className="tabular-nums" />
            </Campo>
            <Campo id="pc-produtos" rotulo="Produtos" erro={erro('product_count')}>
              <Input id="pc-produtos" value={d.product_count} onChange={(e) => setNumero('product_count')(e.target.value)} inputMode="numeric" className="tabular-nums" />
            </Campo>
            <Campo id="pc-faturas" rotulo="Faturas" erro={erro('invoice_count')}>
              <Input id="pc-faturas" value={d.invoice_count} onChange={(e) => setNumero('invoice_count')(e.target.value)} inputMode="numeric" className="tabular-nums" />
            </Campo>
          </div>
          <p className="rounded border bg-muted/40 px-3 py-2 text-[11px] text-muted-foreground">
            Deixe 0 no campo que não deve ter teto. Reduzir um limite não corta quem já passou dele — só bloqueia novos.
          </p>
        </Secao>

        <Secao titulo="Módulos liberados">
          {dados.modulos.length === 0 ? (
            <p className="text-xs text-muted-foreground">Nenhum módulo instalado declara liberação por pacote.</p>
          ) : (
            <>
              <div className="flex flex-wrap gap-1.5" data-contract="superadmin.pacotes.form.modulos">
                {liga.map((m) => {
                  const on = Boolean(d.custom_permissions[m.nome]);
                  return (
                    <Button
                      key={m.nome}
                      type="button"
                      size="sm"
                      variant={on ? 'default' : 'outline'}
                      aria-pressed={on}
                      className="h-7 text-[11px]"
                      onClick={() => alternarModulo(m.nome)}
                    >
                      {m.rotulo}
                    </Button>
                  );
                })}
              </div>
              {texto.map((m) => (
                <Campo key={m.nome} id={`pc-mod-${m.nome}`} rotulo={m.rotulo}>
                  <Input id={`pc-mod-${m.nome}`} value={String(d.custom_permissions[m.nome] ?? '')} onChange={(e) => setModuloTexto(m.nome, e.target.value)} />
                </Campo>
              ))}
            </>
          )}
        </Secao>

        <Secao titulo="Visibilidade">
          <Liga id="pc-ativo" rotulo="Pacote ativo" ajuda="Inativo sai da tela de assinatura; quem já assinou continua." ligado={d.is_active} onChange={setLiga('is_active')} />
          <Liga id="pc-privado" rotulo="Privado" ajuda="Só o superadmin consegue atribuir — não aparece pro cliente." ligado={d.is_private} onChange={setLiga('is_private')} />
          <Liga id="pc-avulso" rotulo="Cobrança avulsa" ajuda="Cobra uma vez e não renova." ligado={d.is_one_time} onChange={setLiga('is_one_time')} />
          <Campo id="pc-ordem" rotulo="Ordem na vitrine" erro={erro('sort_order')}>
            <Input id="pc-ordem" value={d.sort_order} onChange={(e) => setNumero('sort_order')(e.target.value)} inputMode="numeric" className="w-24 tabular-nums" />
          </Campo>
          <Liga id="pc-link" rotulo="Link personalizado" ajuda="Troca o botão de assinar por um link seu." ligado={d.enable_custom_link} onChange={setLiga('enable_custom_link')} />
          {d.enable_custom_link && (
            <div className="grid grid-cols-2 gap-3">
              <Campo id="pc-link-url" rotulo="Endereço" erro={erro('custom_link')}>
                <Input id="pc-link-url" value={d.custom_link} onChange={(e) => setTexto('custom_link')(e.target.value)} />
              </Campo>
              <Campo id="pc-link-texto" rotulo="Texto do botão" erro={erro('custom_link_text')}>
                <Input id="pc-link-texto" value={d.custom_link_text} onChange={(e) => setTexto('custom_link_text')(e.target.value)} />
              </Campo>
            </div>
          )}
          {editando && (
            <Liga
              id="pc-atualiza"
              rotulo="Aplicar às assinaturas vigentes"
              ajuda="Copia limites e módulos deste pacote para as assinaturas que ainda não venceram."
              ligado={d.update_subscriptions}
              onChange={setLiga('update_subscriptions')}
            />
          )}
        </Secao>
      </div>

      <footer className="flex justify-end gap-2 border-t px-5 py-3">
        <Button variant="outline" onClick={onFechar} disabled={form.processing}>
          Cancelar
        </Button>
        <Button onClick={salvar} disabled={!podeSalvar || form.processing}>
          {form.processing ? 'Salvando…' : editando ? 'Salvar pacote' : 'Criar pacote'}
        </Button>
      </footer>
    </Casca>
  );
}
