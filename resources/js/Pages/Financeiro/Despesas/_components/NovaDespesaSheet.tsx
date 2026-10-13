// Nova despesa (US-MWART-018) — grava pelo MESMO `ExpenseController@store` da Blade (POST ajax → {success, msg}).
//
// VALOR (regra mestre): o total sai com 2 casas e vírgula decimal ("1234,56"), nunca um float cru: o
// `num_uf` lê "." como milhar quando vêm 3+ dígitos depois dele (incidente 2026-06-05). A data sai em ISO
// (`datetime-local`, com 'T'); o `store` a converte para o formato da empresa antes do `createExpense`.
//
// Fora daqui, na tela clássica: documento anexo, recorrência e a linha de pagamento (escrever pagamento
// na tela nova é decisão [W]).

import { useMemo, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Checkbox } from '@/Components/ui/checkbox';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Grid, Inline, Stack } from '@/Components/layout';

type Lista = Record<string, string>;
export interface OpcoesNova {
  locais: Lista; usuarios: Lista; contatos: Lista; categorias: Lista; subcategorias: Lista;
  subcategoria_pai: Record<string, string | number>; impostos: Lista;
}

const NENHUM = '__nenhum';

/** O que vai no POST. Exportado para o teste de contrato do formato do total. */
export function totalParaEnvio(v: number): string {
  return (Math.round(v * 100) / 100).toFixed(2).replace('.', ',');
}

function agoraLocal(): string {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
}

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

function Escolha({ id, rotulo, valor, opcoes, muda, obrigatorio }: {
  id: string; rotulo: string; valor: string; opcoes: Lista; muda: (v: string) => void; obrigatorio?: boolean;
}) {
  return (
    <Stack gap={1}>
      <label htmlFor={id}>{rotulo}{obrigatorio ? ' *' : ''}</label>
      <Select value={valor || NENHUM} onValueChange={(v) => muda(v === NENHUM ? '' : v)}>
        <SelectTrigger id={id}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={NENHUM}>{obrigatorio ? 'Escolha…' : 'Nenhum'}</SelectItem>
          {Object.entries(opcoes).filter(([k]) => k).map(([k, l]) => <SelectItem key={k} value={k}>{l}</SelectItem>)}
        </SelectContent>
      </Select>
    </Stack>
  );
}

export default function NovaDespesaSheet({ aberta, fechar, opcoes, gravou }: {
  aberta: boolean; fechar: () => void; opcoes: OpcoesNova; gravou: (msg: string) => void;
}) {
  const vazio = { location_id: '', expense_category_id: '', expense_sub_category_id: '', ref_no: '', expense_for: '', contact_id: '', tax_id: '', additional_notes: '' };
  const [f, setF] = useState(vazio);
  const [data, setData] = useState(agoraLocal());
  const [total, setTotal] = useState(0);
  const [estorno, setEstorno] = useState(false);
  const [erro, setErro] = useState('');
  const [salvando, setSalvando] = useState(false);
  const muda = (k: keyof typeof vazio) => (v: string) => setF((x) => ({ ...x, [k]: v, ...(k === 'expense_category_id' ? { expense_sub_category_id: '' } : {}) }));

  const subcategorias = useMemo(() => Object.fromEntries(Object.entries(opcoes.subcategorias)
    .filter(([id]) => String(opcoes.subcategoria_pai[id]) === f.expense_category_id)), [opcoes, f.expense_category_id]);
  const pode = !!f.location_id && total > 0 && !!data;

  async function salvar() {
    setSalvando(true);
    setErro('');
    const corpo = new URLSearchParams({ ...f, transaction_date: data, final_total: totalParaEnvio(total) });
    if (estorno) corpo.append('is_refund', '1');
    try {
      const r = await fetch('/expenses', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: corpo,
      });
      const j = r.ok ? await r.json() : { success: 0 };
      if (j.success) {
        setF(vazio); setTotal(0); setEstorno(false); setData(agoraLocal());
        gravou(j.msg ?? 'Despesa cadastrada.');
      } else {
        setErro(j.msg ?? 'Não foi possível cadastrar a despesa.');
      }
    } catch {
      setErro('Não foi possível cadastrar a despesa.');
    }
    setSalvando(false);
  }

  return (
    <Sheet open={aberta} onOpenChange={(v) => !v && fechar()}>
      <SheetContent data-contract="nova-despesa" className="w-full overflow-y-auto p-0 sm:max-w-[760px]">
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <SheetTitle asChild><h2 className="m-0">Nova despesa</h2></SheetTitle>
            <SheetDescription asChild><p>Sem pagamento: ela entra como devida e vira título a pagar no Financeiro.</p></SheetDescription>
          </div>
        </header>
        <Stack gap={4} className="p-5 text-sm">
          <Grid cols={2} gap={3}>
            <Escolha id="nd-local" rotulo="Local" valor={f.location_id} opcoes={opcoes.locais} muda={muda('location_id')} obrigatorio />
            <Stack gap={1}>
              <label htmlFor="nd-data">Data *</label>
              <Input id="nd-data" type="datetime-local" value={data} onChange={(e) => setData(e.target.value)} />
            </Stack>
            <Escolha id="nd-cat" rotulo="Categoria" valor={f.expense_category_id} opcoes={opcoes.categorias} muda={muda('expense_category_id')} />
            <Escolha id="nd-sub" rotulo="Subcategoria" valor={f.expense_sub_category_id} opcoes={subcategorias} muda={muda('expense_sub_category_id')} />
            <Stack gap={1}>
              <label htmlFor="nd-ref">Referência</label>
              <Input id="nd-ref" value={f.ref_no} onChange={(e) => muda('ref_no')(e.target.value)} placeholder="Vazio: o sistema gera" />
            </Stack>
            <Escolha id="nd-para" rotulo="Despesa para" valor={f.expense_for} opcoes={opcoes.usuarios} muda={muda('expense_for')} />
            <Escolha id="nd-contato" rotulo="Contato" valor={f.contact_id} opcoes={opcoes.contatos} muda={muda('contact_id')} />
            <Escolha id="nd-imposto" rotulo="Imposto" valor={f.tax_id} opcoes={opcoes.impostos} muda={muda('tax_id')} />
            <Stack gap={1}>
              <label htmlFor="nd-total">Total *</label>
              <NumericInputPtBR id="nd-total" value={total} onChange={setTotal} />
            </Stack>
          </Grid>
          <Stack gap={1}>
            <label htmlFor="nd-nota">Nota</label>
            <Input id="nd-nota" value={f.additional_notes} onChange={(e) => muda('additional_notes')(e.target.value)} />
          </Stack>
          <Inline gap={2}>
            <Checkbox id="nd-estorno" checked={estorno} onCheckedChange={(v) => setEstorno(v === true)} />
            <label htmlFor="nd-estorno">É estorno de despesa</label>
          </Inline>
          <p className="text-xs text-muted-foreground">Documento anexo, recorrência e pagamento: use a tela clássica.</p>
          {erro && <p role="alert" className="text-destructive">{erro}</p>}
          <Inline gap={2} justify="end" className="border-t pt-4">
            <Button variant="ghost" onClick={fechar}>Cancelar</Button>
            <Button disabled={!pode || salvando} onClick={salvar}>Cadastrar</Button>
          </Inline>
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
