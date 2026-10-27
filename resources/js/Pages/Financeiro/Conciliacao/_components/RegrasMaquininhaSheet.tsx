// Painel "Regras e maquininha" da Conciliação (US-FIN-080 + US-FIN-083).
//
// Cadastra as regras de conciliação e a maquininha padrão. Não concilia nada: quem aplica é o
// servidor no upload ou no botão "Aplicar regras", e só com a flag ligada (o painel avisa).
// Os dados vêm da prop opcional `config`, pedida quando o painel abre.

import { router, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Trash2 } from 'lucide-react';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

interface Regra {
  id: number;
  nome: string;
  ativo: boolean;
  prioridade: number;
  descricao_contem: string | null;
  sentido: 'C' | 'D' | null;
  valor_min: number | null;
  valor_max: number | null;
  plano_conta_id: number | null;
  categoria_id: number | null;
  acao: 'sugerir' | 'conciliar';
}

interface Opcao { id: number; nome: string; codigo?: string }

export interface ConfigConciliacao {
  regras_ligadas: boolean;
  cartao_ligado: boolean;
  regras: Regra[];
  planos: Opcao[];
  categorias: Opcao[];
  contas: Opcao[];
  maquininha: {
    nome: string; descricao_extrato: string; conta_bancaria_id: number | null;
    taxa_debito: string | number; prazo_debito_dias: number; taxa_credito: string | number; prazo_credito_dias: number;
  } | null;
  recebiveis: { previstos: number; previsto_liquido: number; recebidos_30d: number; taxa_30d: number };
}

const NADA = '__nada__';
const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
// "3,19" → 3.19 (o servidor valida número com ponto); arredonda a 3 casas antes de enviar.
const numero = (v: string) => {
  const n = Number(String(v).replace(/\./g, '').replace(',', '.'));
  return Number.isFinite(n) ? Math.round(n * 1000) / 1000 : null;
};
const pct = (v: string | number) => String(v ?? '').replace('.', ',');

function Aviso({ ligado, flag }: { ligado: boolean; flag: string }) {
  if (ligado) return null;
  return (
    <p className="text-[length:var(--fs-2)] text-[var(--warn)] bg-[var(--warn-soft)] rounded px-2 py-1.5">
      Desligado neste ambiente ({flag}). Dá para cadastrar; nada é aplicado até ligar.
    </p>
  );
}

function Campo({ id, rotulo, erro, children }: { id: string; rotulo: string; erro?: string; children: React.ReactNode }) {
  return (
    <div>
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro && <p className="text-xs text-destructive mt-1">{erro}</p>}
    </div>
  );
}

export function RegrasMaquininhaSheet({ config, onClose }: { config: ConfigConciliacao | undefined; onClose: () => void }) {
  const regra = useForm({
    nome: '', descricao_contem: '', sentido: 'C', valor_min: '', valor_max: '',
    plano_conta_id: NADA, categoria_id: NADA, acao: 'sugerir',
  });
  const m = config?.maquininha;
  const maq = useForm({
    nome: m?.nome ?? '', descricao_extrato: m?.descricao_extrato ?? '',
    conta_bancaria_id: m?.conta_bancaria_id ? String(m.conta_bancaria_id) : NADA,
    taxa_debito: pct(m?.taxa_debito ?? ''), prazo_debito_dias: String(m?.prazo_debito_dias ?? 1),
    taxa_credito: pct(m?.taxa_credito ?? ''), prazo_credito_dias: String(m?.prazo_credito_dias ?? 30),
  });

  const recarregar = { preserveScroll: true, onSuccess: () => router.reload({ only: ['config'] }) };

  const criarRegra = (e: FormEvent) => {
    e.preventDefault();
    regra.transform((d) => ({
      ...d,
      sentido: d.sentido === NADA ? null : d.sentido,
      valor_min: d.valor_min ? numero(d.valor_min) : null,
      valor_max: d.valor_max ? numero(d.valor_max) : null,
      plano_conta_id: d.plano_conta_id === NADA ? null : Number(d.plano_conta_id),
      categoria_id: d.categoria_id === NADA ? null : Number(d.categoria_id),
    }));
    regra.post('/financeiro/conciliacao/regras', { ...recarregar, onSuccess: () => { regra.reset(); router.reload({ only: ['config'] }); } });
  };

  const salvarMaquininha = (e: FormEvent) => {
    e.preventDefault();
    maq.transform((d) => ({
      ...d,
      conta_bancaria_id: d.conta_bancaria_id === NADA ? null : Number(d.conta_bancaria_id),
      taxa_debito: numero(d.taxa_debito), taxa_credito: numero(d.taxa_credito),
      prazo_debito_dias: Number(d.prazo_debito_dias), prazo_credito_dias: Number(d.prazo_credito_dias),
    }));
    maq.post('/financeiro/conciliacao/maquininha', recarregar);
  };

  const alvo = (r: Regra) => {
    const plano = config?.planos.find((p) => p.id === r.plano_conta_id);
    const cat = config?.categorias.find((c) => c.id === r.categoria_id);
    return [plano && `plano ${plano.codigo ?? ''} ${plano.nome}`, cat && `categoria ${cat.nome}`].filter(Boolean).join(' · ') || 'qualquer título';
  };

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent
        container={typeof document !== 'undefined' ? (document.querySelector('.cockpit') as HTMLElement | null) : null}
        className="w-full sm:max-w-xl overflow-y-auto p-0"
      >
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <SheetTitle asChild><h2 className="m-0">Regras e maquininha</h2></SheetTitle>
            <SheetDescription asChild>
              <p>Regras casam o extrato com o título sozinhas; a maquininha transforma venda no cartão em recebível e confere o repasse.</p>
            </SheetDescription>
          </div>
        </header>

        {!config ? (
          <p className="p-5 text-[var(--text-dim)]">Carregando…</p>
        ) : (
          <div className="os-drawer-body p-5 space-y-6">
            <section className="space-y-3" aria-labelledby="sec-regras">
              <h3 id="sec-regras" className="text-[length:var(--fs-4)] font-semibold">Regras de conciliação</h3>
              <Aviso ligado={config.regras_ligadas} flag="FINANCEIRO_CONCILIACAO_REGRAS_ENABLED" />
              <p className="text-[length:var(--fs-2)] text-[var(--text-dim)]">
                Uma regra só casa quando sobra um título em aberto do mesmo valor, até 5 dias da data. O resto fica para você revisar.
              </p>
              {config.regras.length === 0 ? (
                <p className="text-[length:var(--fs-2)] text-[var(--text-mute)]">Nenhuma regra ainda.</p>
              ) : (
                <ul className="divide-y divide-[var(--border)] border border-[var(--border)] rounded-[8px]">
                  {config.regras.map((r) => (
                    <li key={r.id} className="flex items-start gap-3 px-3 py-2">
                      <Switch
                        checked={r.ativo}
                        aria-label={`Regra ${r.nome} ${r.ativo ? 'ligada' : 'desligada'}`}
                        onCheckedChange={(v) => router.put(`/financeiro/conciliacao/regras/${r.id}`, { ativo: v }, recarregar)}
                      />
                      <div className="flex-1 min-w-0">
                        <div className="font-medium text-[var(--text)]">{r.nome}</div>
                        <div className="text-[length:var(--fs-2)] text-[var(--text-dim)]">
                          {r.sentido === 'D' ? 'saída' : r.sentido === 'C' ? 'entrada' : 'entrada ou saída'}
                          {r.descricao_contem ? ` com "${r.descricao_contem}"` : ''}
                          {r.valor_min != null || r.valor_max != null ? ` · ${brl(r.valor_min ?? 0)} a ${r.valor_max != null ? brl(r.valor_max) : '∞'}` : ''}
                          {' → '}{alvo(r)} · {r.acao === 'conciliar' ? 'concilia sozinho' : 'sugere'}
                        </div>
                      </div>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon-xs"
                        className="text-[var(--neg)]"
                        title="Remover regra"
                        aria-label={`Remover regra ${r.nome}`}
                        onClick={() => router.delete(`/financeiro/conciliacao/regras/${r.id}`, recarregar)}
                      >
                        <Trash2 size={12} />
                      </Button>
                    </li>
                  ))}
                </ul>
              )}

              <form onSubmit={criarRegra} className="grid grid-cols-2 gap-3 border border-[var(--border)] rounded-[8px] p-3">
                <div className="col-span-2">
                  <Campo id="r-nome" rotulo="Nome da regra" erro={regra.errors.nome}>
                    <Input id="r-nome" value={regra.data.nome} onChange={(e) => regra.setData('nome', e.target.value)} placeholder="Mensalidades" />
                  </Campo>
                </div>
                <Campo id="r-desc" rotulo="Descrição contém" erro={regra.errors.descricao_contem}>
                  <Input id="r-desc" value={regra.data.descricao_contem} onChange={(e) => regra.setData('descricao_contem', e.target.value)} placeholder="MENSALIDADE" />
                </Campo>
                <Campo id="r-sentido" rotulo="Sentido">
                  <Select value={regra.data.sentido} onValueChange={(v) => regra.setData('sentido', v)}>
                    <SelectTrigger id="r-sentido"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="C">Entrada</SelectItem>
                      <SelectItem value="D">Saída</SelectItem>
                      <SelectItem value={NADA}>Qualquer</SelectItem>
                    </SelectContent>
                  </Select>
                </Campo>
                <Campo id="r-min" rotulo="Valor de (R$)" erro={regra.errors.valor_min}>
                  <Input id="r-min" inputMode="decimal" value={regra.data.valor_min} onChange={(e) => regra.setData('valor_min', e.target.value)} />
                </Campo>
                <Campo id="r-max" rotulo="Valor até (R$)" erro={regra.errors.valor_max}>
                  <Input id="r-max" inputMode="decimal" value={regra.data.valor_max} onChange={(e) => regra.setData('valor_max', e.target.value)} />
                </Campo>
                <Campo id="r-plano" rotulo="Plano de contas do título" erro={regra.errors.plano_conta_id}>
                  <Select value={regra.data.plano_conta_id} onValueChange={(v) => regra.setData('plano_conta_id', v)}>
                    <SelectTrigger id="r-plano"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value={NADA}>Qualquer</SelectItem>
                      {config.planos.map((p) => <SelectItem key={p.id} value={String(p.id)}>{p.codigo} {p.nome}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Campo>
                <Campo id="r-cat" rotulo="Categoria do título" erro={regra.errors.categoria_id}>
                  <Select value={regra.data.categoria_id} onValueChange={(v) => regra.setData('categoria_id', v)}>
                    <SelectTrigger id="r-cat"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value={NADA}>Qualquer</SelectItem>
                      {config.categorias.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Campo>
                <Campo id="r-acao" rotulo="Quando casar">
                  <Select value={regra.data.acao} onValueChange={(v) => regra.setData('acao', v)}>
                    <SelectTrigger id="r-acao"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="sugerir">Sugerir para eu confirmar</SelectItem>
                      <SelectItem value="conciliar">Conciliar sozinho</SelectItem>
                    </SelectContent>
                  </Select>
                </Campo>
                <div className="flex items-end justify-end">
                  <Button type="submit" disabled={regra.processing || !regra.data.nome}>Adicionar regra</Button>
                </div>
              </form>
            </section>

            <section className="space-y-3" aria-labelledby="sec-maq">
              <h3 id="sec-maq" className="text-[length:var(--fs-4)] font-semibold">Maquininha de cartão</h3>
              <Aviso ligado={config.cartao_ligado} flag="FINANCEIRO_CARTAO_RECEBIVEIS_ENABLED" />
              <dl className="grid grid-cols-3 gap-2 text-center">
                <div className="border border-[var(--border)] rounded-[8px] p-2">
                  <dt className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)]">A receber</dt>
                  <dd className="font-semibold tabular-nums">{brl(config.recebiveis.previsto_liquido)}</dd>
                  <dd className="text-[length:var(--fs-1)] text-[var(--text-mute)]">{config.recebiveis.previstos} venda(s)</dd>
                </div>
                <div className="border border-[var(--border)] rounded-[8px] p-2">
                  <dt className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)]">Repasses 30 dias</dt>
                  <dd className="font-semibold tabular-nums">{config.recebiveis.recebidos_30d}</dd>
                </div>
                <div className="border border-[var(--border)] rounded-[8px] p-2">
                  <dt className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)]">Taxa 30 dias</dt>
                  <dd className="font-semibold tabular-nums text-[var(--neg)]">{brl(config.recebiveis.taxa_30d)}</dd>
                </div>
              </dl>

              <form onSubmit={salvarMaquininha} className="grid grid-cols-2 gap-3 border border-[var(--border)] rounded-[8px] p-3">
                <Campo id="m-nome" rotulo="Adquirente" erro={maq.errors.nome}>
                  <Input id="m-nome" value={maq.data.nome} onChange={(e) => maq.setData('nome', e.target.value)} placeholder="Stone" />
                </Campo>
                <Campo id="m-ext" rotulo="Como aparece no extrato" erro={maq.errors.descricao_extrato}>
                  <Input id="m-ext" value={maq.data.descricao_extrato} onChange={(e) => maq.setData('descricao_extrato', e.target.value)} placeholder="STONE" />
                </Campo>
                <Campo id="m-tdeb" rotulo="Taxa débito (%)" erro={maq.errors.taxa_debito}>
                  <Input id="m-tdeb" inputMode="decimal" value={maq.data.taxa_debito} onChange={(e) => maq.setData('taxa_debito', e.target.value)} placeholder="1,99" />
                </Campo>
                <Campo id="m-pdeb" rotulo="Prazo débito (dias)" erro={maq.errors.prazo_debito_dias}>
                  <Input id="m-pdeb" inputMode="numeric" value={maq.data.prazo_debito_dias} onChange={(e) => maq.setData('prazo_debito_dias', e.target.value)} />
                </Campo>
                <Campo id="m-tcre" rotulo="Taxa crédito (%)" erro={maq.errors.taxa_credito}>
                  <Input id="m-tcre" inputMode="decimal" value={maq.data.taxa_credito} onChange={(e) => maq.setData('taxa_credito', e.target.value)} placeholder="3,19" />
                </Campo>
                <Campo id="m-pcre" rotulo="Prazo crédito (dias)" erro={maq.errors.prazo_credito_dias}>
                  <Input id="m-pcre" inputMode="numeric" value={maq.data.prazo_credito_dias} onChange={(e) => maq.setData('prazo_credito_dias', e.target.value)} />
                </Campo>
                <Campo id="m-conta" rotulo="Conta do repasse" erro={maq.errors.conta_bancaria_id}>
                  <Select value={maq.data.conta_bancaria_id} onValueChange={(v) => maq.setData('conta_bancaria_id', v)}>
                    <SelectTrigger id="m-conta"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value={NADA}>Qualquer conta</SelectItem>
                      {config.contas.map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Campo>
                <div className="flex items-end justify-end">
                  <Button type="submit" disabled={maq.processing}>Salvar maquininha</Button>
                </div>
              </form>
            </section>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
