import { useState } from 'react';
import { Wrench, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Grid, Inline } from '@/Components/layout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { dropdownEntries } from './dropdownEntries';
import PadraoDesbloqueio from './PadraoDesbloqueio';
import {
  adicionarDefeitos,
  itensDoChecklist,
  modelosFiltrados,
  type ChecklistValor,
  type ModeloAparelho,
  type PecaDaOs,
  type ReparoForm,
} from './reparoVenda';

export type RepairPosProps = {
  statuses: Array<{ id: number; name: string; color: string | null }>;
  defaultStatusId: number | null;
  brands: Record<string, string>;
  devices: Record<string, string>;
  modelos: ModeloAparelho[];
  checklistPadrao: string[];
  /** UC-S07 — venda aberta a partir de uma OS (`job_sheet_id`); null na venda de reparo avulsa. */
  osOrigem: {
    job_sheet_id: number;
    job_sheet_no: string;
    location_id: number | null;
    cliente: {
      id: number;
      text: string;
      pay_term_number: number | string | null;
      pay_term_type: string | null;
      shipping_address: string | null;
      selling_price_group_id: number | null;
    } | null;
    reparo: Omit<ReparoForm, 'repair_warranty_id' | 'repair_completed_on' | 'repair_job_sheet_id'>;
    pecas: PecaDaOs[];
    pecasNaoEncontradas: string[];
  } | null;
  warranties: Record<string, string>;
  defeitosSugeridos: string[];
};

const RESPOSTAS: Array<{ valor: ChecklistValor; rotulo: string }> = [
  { valor: 'yes', rotulo: 'Sim' },
  { valor: 'no', rotulo: 'Não' },
  { valor: 'not_applicable', rotulo: 'N/A' },
];

type Props = {
  opcoes: RepairPosProps;
  valor: ReparoForm;
  onChange: (proximo: ReparoForm) => void;
};

const SEM = '__nenhum__';

/**
 * Seção "Reparo" do Sells/Create (UC-S05) — só aparece na venda aberta como reparo.
 * Paridade de campos com o POS Blade de reparo (`repair_pos.blade.php`), incluindo o
 * checklist pré-reparo e a senha/padrão do aparelho (UC-S06).
 */
export default function ReparoSection({ opcoes, valor, onChange }: Props) {
  const [digitando, setDigitando] = useState('');
  const set = <K extends keyof ReparoForm>(k: K, v: ReparoForm[K]) => onChange({ ...valor, [k]: v });

  const selectDeId = (
    id: string,
    rotulo: string,
    campo: 'repair_brand_id' | 'repair_device_id' | 'repair_warranty_id',
    lista: Record<string, string>,
  ) => (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{rotulo}</Label>
      <Select
        value={valor[campo] !== null ? String(valor[campo]) : SEM}
        onValueChange={(v) => mudarId(campo, v === SEM ? null : Number(v))}
      >
        <SelectTrigger id={id}>
          <SelectValue placeholder="Selecionar" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={SEM}>Nenhum</SelectItem>
          {dropdownEntries(lista).map(([k, nome]) => (
            <SelectItem key={k} value={k}>
              {nome}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );

  const confirmarDigitado = () => {
    if (digitando.trim() === '') return;
    set('defeitos', adicionarDefeitos(valor.defeitos, digitando));
    setDigitando('');
  };

  // UC-S06 — trocar marca/aparelho filtra os modelos (como o Blade); modelo que deixou de
  // caber sai, e com ele o checklist que dependia dele.
  const mudarId = (campo: 'repair_brand_id' | 'repair_device_id' | 'repair_warranty_id', v: number | null) => {
    const proximo = { ...valor, [campo]: v };
    const cabe = modelosFiltrados(opcoes.modelos, proximo.repair_brand_id, proximo.repair_device_id)
      .some((m) => m.id === proximo.repair_model_id);
    onChange(cabe ? proximo : { ...proximo, repair_model_id: null });
  };
  const modelosVisiveis = modelosFiltrados(opcoes.modelos, valor.repair_brand_id, valor.repair_device_id);
  const itensChecklist = itensDoChecklist(
    opcoes.checklistPadrao,
    opcoes.modelos.find((m) => m.id === valor.repair_model_id),
  );

  const sugestoesRestantes = opcoes.defeitosSugeridos.filter((s) => !valor.defeitos.includes(s));

  return (
    <Card id="sec-reparo" className="shadow-sm bg-background border-border scroll-mt-32">
      <CardHeader>
        <CardTitle className="text-base">
          <Inline gap={2}>
            <Wrench className="h-4 w-4 text-muted-foreground" />
            Reparo
          </Inline>
        </CardTitle>
        {opcoes.osOrigem && (
          <div className="mt-2 space-y-1 text-sm" role="status">
            <p className="text-muted-foreground">
              Venda da OS <b>{opcoes.osOrigem.job_sheet_no}</b>: cliente, aparelho e{' '}
              {opcoes.osOrigem.pecas.length} peça(s) trazidos da OS. Confira preços e quantidades antes de salvar.
            </p>
            {opcoes.osOrigem.pecasNaoEncontradas.length > 0 && (
              <p className="text-destructive">
                Não entraram no carrinho (produto fora deste local ou indisponível):{' '}
                {opcoes.osOrigem.pecasNaoEncontradas.join(', ')}.
              </p>
            )}
          </div>
        )}
      </CardHeader>
      <CardContent>
        <Grid cols={1} gap={4} className="md:grid-cols-2 lg:grid-cols-4">
        <div className="space-y-1.5">
          <Label htmlFor="repair_status_id">Status do reparo *</Label>
          <Select
            value={valor.repair_status_id !== null ? String(valor.repair_status_id) : undefined}
            onValueChange={(v) => set('repair_status_id', Number(v))}
          >
            <SelectTrigger id="repair_status_id" aria-required="true">
              <SelectValue placeholder="Selecionar status" />
            </SelectTrigger>
            <SelectContent>
              {opcoes.statuses.map((s) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {s.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {valor.repair_status_id === null && (
            <p className="text-xs text-destructive" role="alert">
              Escolha o status para salvar.
            </p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="repair_due_date">Entrega prevista</Label>
          <Input
            id="repair_due_date"
            type="datetime-local"
            value={valor.repair_due_date}
            onChange={(e) => set('repair_due_date', e.target.value)}
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="repair_completed_on">Concluído em</Label>
          <Input
            id="repair_completed_on"
            type="datetime-local"
            value={valor.repair_completed_on}
            onChange={(e) => set('repair_completed_on', e.target.value)}
          />
        </div>

        {Object.keys(opcoes.warranties).length > 0 &&
          selectDeId('repair_warranty_id', 'Garantia', 'repair_warranty_id', opcoes.warranties)}

        {selectDeId('repair_brand_id', 'Marca', 'repair_brand_id', opcoes.brands)}
        {selectDeId('repair_device_id', 'Aparelho', 'repair_device_id', opcoes.devices)}
        <div className="space-y-1.5">
          <Label htmlFor="repair_model_id">Modelo</Label>
          <Select
            value={valor.repair_model_id !== null ? String(valor.repair_model_id) : SEM}
            onValueChange={(v) => set('repair_model_id', v === SEM ? null : Number(v))}
          >
            <SelectTrigger id="repair_model_id">
              <SelectValue placeholder="Selecionar" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={SEM}>Nenhum</SelectItem>
              {modelosVisiveis.map((m) => (
                <SelectItem key={m.id} value={String(m.id)}>
                  {m.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="repair_serial_no">Nº de série</Label>
          <Input
            id="repair_serial_no"
            value={valor.repair_serial_no}
            onChange={(e) => set('repair_serial_no', e.target.value)}
          />
        </div>

        <div className="space-y-1.5 md:col-span-2 lg:col-span-4">
          <Label htmlFor="repair_defects">Problema relatado pelo cliente</Label>
          <Inline gap={2}>
            <Input
              id="repair_defects"
              value={digitando}
              placeholder="Digite e tecle Enter (vírgula separa vários)"
              onChange={(e) => setDigitando(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  confirmarDigitado();
                }
              }}
            />
            <Button type="button" variant="outline" onClick={confirmarDigitado}>
              Adicionar
            </Button>
          </Inline>
          {valor.defeitos.length > 0 && (
            <Inline wrap gap={1} className="gap-1.5" aria-label="Defeitos informados">
              {valor.defeitos.map((d) => (
                <span key={d} className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs">
                  {d}
                  <button
                    type="button"
                    aria-label={`Remover ${d}`}
                    onClick={() => set('defeitos', valor.defeitos.filter((x) => x !== d))}
                  >
                    <X className="h-3 w-3" />
                  </button>
                </span>
              ))}
            </Inline>
          )}
          {sugestoesRestantes.length > 0 && (
            <Inline wrap gap={1} className="gap-1.5">
              {sugestoesRestantes.map((s) => (
                <button
                  key={s}
                  type="button"
                  onClick={() => set('defeitos', adicionarDefeitos(valor.defeitos, s))}
                  className="rounded-full border border-dashed border-border px-2 py-0.5 text-xs text-muted-foreground hover:bg-muted/50"
                >
                  + {s}
                </button>
              ))}
            </Inline>
          )}
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="repair_security_pwd">Senha do aparelho</Label>
          <Input
            id="repair_security_pwd"
            autoComplete="off"
            value={valor.repair_security_pwd}
            onChange={(e) => set('repair_security_pwd', e.target.value)}
          />
        </div>

        <div className="space-y-1.5 md:col-span-2">
          <Label htmlFor="repair_security_pattern">Padrão de desbloqueio</Label>
          <PadraoDesbloqueio
            id="repair_security_pattern"
            valor={valor.repair_security_pattern}
            onChange={(v) => set('repair_security_pattern', v)}
          />
        </div>

        {itensChecklist.length > 0 && (
          <fieldset className="space-y-2 md:col-span-2 lg:col-span-4">
            <legend className="text-sm font-medium">Checklist pré-reparo</legend>
            <Grid cols={1} gap={2} className="md:grid-cols-2 lg:grid-cols-3">
              {itensChecklist.map((item) => {
                const atual = valor.checklist[item] ?? 'not_applicable';
                return (
                  <Inline key={item} gap={2} justify="between" className="rounded-md border border-border px-2 py-1.5">
                    <span className="text-sm">{item}</span>
                    <Inline role="radiogroup" aria-label={item} gap={1}>
                      {RESPOSTAS.map((r) => (
                        <button
                          key={r.valor}
                          type="button"
                          role="radio"
                          aria-checked={atual === r.valor}
                          onClick={() => set('checklist', { ...valor.checklist, [item]: r.valor })}
                          className={
                            'rounded px-2 py-0.5 text-xs transition-colors ' +
                            (atual === r.valor
                              ? 'bg-primary text-primary-foreground'
                              : 'border border-border text-muted-foreground hover:bg-muted/50')
                          }
                        >
                          {r.rotulo}
                        </button>
                      ))}
                    </Inline>
                  </Inline>
                );
              })}
            </Grid>
          </fieldset>
        )}
        </Grid>
      </CardContent>
    </Card>
  );
}
