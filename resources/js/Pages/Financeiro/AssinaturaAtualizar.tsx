// FIN-004 — Atualizar cobranca recorrente
// HITL pending Wagner. Cuidado biz=4 prod ROTA LIVRE.
// Charter: ./AssinaturaAtualizar.charter.md (draft).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertTitle, AlertDescription } from '@/Components/ui/alert';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import { toast } from 'sonner';

interface Assinatura {
  id: number;
  plano: string | null;
  status: string;
  next_due_date: string | null;
  valor_atual: number | string | null;
  ciclo_atual: string | null;
  forma_pagamento_atual: string;
}

interface Props {
  assinaturas: Assinatura[];
}

const CICLOS = ['mensal', 'trimestral', 'semestral', 'anual'] as const;
const FORMAS = ['boleto', 'pix', 'cartao'] as const;

const fmtBRL = (v: number | string | null) =>
  v === null || v === '' || v === undefined
    ? '—'
    : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v));

const statusVariant = (status: string): 'default' | 'secondary' | 'destructive' => {
  const s = status.toLowerCase();
  if (['ativa', 'ativo', 'active'].includes(s)) return 'default';
  if (['cancelada', 'cancelado', 'canceled', 'inadimplente', 'overdue', 'suspensa'].includes(s)) {
    return 'destructive';
  }
  return 'secondary';
};

interface DiffRow {
  label: string;
  from: string;
  to: string;
}

export default function AssinaturaAtualizar({ assinaturas }: Props) {
  const [selectedId, setSelectedId] = useState<string>('');
  const [valor, setValor] = useState<string>('');
  const [ciclo, setCiclo] = useState<string>('');
  const [forma, setForma] = useState<string>('');
  const [saving, setSaving] = useState(false);

  const selected = useMemo(
    () => assinaturas.find((a) => String(a.id) === selectedId) ?? null,
    [assinaturas, selectedId],
  );

  const resetCampos = () => {
    setValor('');
    setCiclo('');
    setForma('');
  };

  const selecionar = (id: number) => {
    setSelectedId(String(id));
    resetCampos();
  };

  // Preview de impacto — diff valor/ciclo/forma antigo -> novo. Só conta como
  // mudança real (evita patch cego em prod biz=4).
  const changes = useMemo<DiffRow[]>(() => {
    if (!selected) return [];
    const rows: DiffRow[] = [];
    if (valor.trim() !== '' && Number(valor) !== Number(selected.valor_atual ?? NaN)) {
      rows.push({ label: 'Valor', from: fmtBRL(selected.valor_atual), to: fmtBRL(valor) });
    }
    if (ciclo && ciclo !== selected.ciclo_atual) {
      rows.push({ label: 'Ciclo', from: selected.ciclo_atual ?? '—', to: ciclo });
    }
    if (forma && forma !== selected.forma_pagamento_atual) {
      rows.push({ label: 'Forma de pagamento', from: selected.forma_pagamento_atual, to: forma });
    }
    return rows;
  }, [selected, valor, ciclo, forma]);

  const hasChanges = changes.length > 0;

  const onSubmit = () => {
    if (!selected) {
      toast.error('Selecione uma assinatura.');
      return;
    }
    if (!hasChanges) {
      toast.warning('Nenhuma alteração para aplicar.');
      return;
    }

    const payload: Record<string, string | number> = {};
    if (valor.trim() !== '') payload.valor = Number(valor);
    if (ciclo) payload.ciclo = ciclo;
    if (forma) payload.forma_pagamento = forma;

    setSaving(true);
    router.patch(`/financeiro/assinaturas/${selected.id}`, payload, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Cobranca atualizada.');
        resetCampos();
      },
      onError: (errors) => {
        const first = Object.values(errors)[0];
        toast.error(typeof first === 'string' ? first : 'Erro ao atualizar.');
      },
      onFinish: () => setSaving(false),
    });
  };

  return (
    <AppShellV2 title="Atualizar Cobranca">
      <div className="fin-cowork">
        <div className="fin-curadoria vendas-aplus container mx-auto max-w-3xl space-y-6 py-6">
          {/* Onda Wave 4 — header legacy os-page-h/fin-page-h -> PageHeader canon */}
          <PageHeader
            title="Atualizar cobrança"
            subtitle="Altere valor, ciclo ou forma de pagamento de uma assinatura ativa. Confira o impacto antes de salvar."
            actions={
              <Badge variant="outline">
                {assinaturas.length} {assinaturas.length === 1 ? 'assinatura' : 'assinaturas'}
              </Badge>
            }
          />

          {/* Tabela de assinaturas — overview + seleção por linha */}
          <div className="rounded-md border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr className="text-left">
                  <th className="px-4 py-2 font-medium w-10"></th>
                  <th className="px-4 py-2 font-medium">Assinatura</th>
                  <th className="px-4 py-2 font-medium">Status</th>
                  <th className="px-4 py-2 font-medium text-right">Valor atual</th>
                  <th className="px-4 py-2 font-medium">Ciclo</th>
                  <th className="px-4 py-2 font-medium">Forma</th>
                  <th className="px-4 py-2 font-medium">Próx. venc.</th>
                </tr>
              </thead>
              <tbody>
                {assinaturas.length === 0 && (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">
                      Nenhuma assinatura ativa encontrada.
                    </td>
                  </tr>
                )}
                {assinaturas.map((a) => {
                  const isSelected = String(a.id) === selectedId;
                  return (
                    <tr
                      key={a.id}
                      onClick={() => selecionar(a.id)}
                      className={`cursor-pointer border-t transition-colors ${
                        isSelected ? 'bg-muted/60' : 'hover:bg-muted/30'
                      }`}
                    >
                      <td className="px-4 py-3">
                        {/* radio nativo: seleção de linha em tabela (1 assinatura/vez); RadioGroup do DS não encapsula linhas de <table> */}
                        {/* eslint-disable no-restricted-syntax */}
                        <input
                          type="radio"
                          name="assinatura"
                          checked={isSelected}
                          onChange={() => selecionar(a.id)}
                          aria-label={`Selecionar assinatura #${a.id}`}
                          className="accent-primary"
                        />
                        {/* eslint-enable no-restricted-syntax */}
                      </td>
                      <td className="px-4 py-3 font-medium">
                        #{a.id} {a.plano ?? 'Sem plano'}
                      </td>
                      <td className="px-4 py-3">
                        <Badge variant={statusVariant(a.status)}>{a.status}</Badge>
                      </td>
                      <td className="px-4 py-3 text-right tabular-nums">{fmtBRL(a.valor_atual)}</td>
                      <td className="px-4 py-3 text-muted-foreground">{a.ciclo_atual ?? '—'}</td>
                      <td className="px-4 py-3 text-muted-foreground">{a.forma_pagamento_atual}</td>
                      <td className="px-4 py-3 text-muted-foreground">{a.next_due_date ?? '—'}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {/* Formulário de edição — só após selecionar uma linha */}
          {selected && (
            <Card>
              <CardContent className="space-y-4 pt-6">
                <div className="space-y-1">
                  <p className="text-sm font-medium">
                    Editando #{selected.id} {selected.plano ?? 'Sem plano'}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    Atual: {fmtBRL(selected.valor_atual)} / {selected.ciclo_atual ?? '—'} /{' '}
                    {selected.forma_pagamento_atual}
                  </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                  <div className="space-y-2">
                    <Label htmlFor="valor">Novo valor (R$)</Label>
                    <Input
                      id="valor"
                      type="number"
                      min="0.01"
                      step="0.01"
                      value={valor}
                      onChange={(e) => setValor(e.target.value)}
                      placeholder="Manter atual"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="ciclo">Novo ciclo</Label>
                    <Select value={ciclo} onValueChange={setCiclo}>
                      <SelectTrigger id="ciclo">
                        <SelectValue placeholder="Manter atual" />
                      </SelectTrigger>
                      <SelectContent>
                        {CICLOS.map((c) => (
                          <SelectItem key={c} value={c}>
                            {c}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="forma">Forma de pagamento</Label>
                    <Select value={forma} onValueChange={setForma}>
                      <SelectTrigger id="forma">
                        <SelectValue placeholder="Manter atual" />
                      </SelectTrigger>
                      <SelectContent>
                        {FORMAS.map((f) => (
                          <SelectItem key={f} value={f}>
                            {f}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                {/* Preview de impacto — diff antes de salvar (prod biz=4) */}
                {hasChanges ? (
                  <Alert>
                    <AlertTitle>Impacto desta alteração</AlertTitle>
                    <AlertDescription>
                      <ul className="space-y-1">
                        {changes.map((c) => (
                          <li key={c.label} className="text-sm">
                            <span className="font-medium text-foreground">{c.label}:</span>{' '}
                            <span className="text-muted-foreground line-through">{c.from}</span>
                            {' → '}
                            <span className="font-medium text-foreground">{c.to}</span>
                          </li>
                        ))}
                      </ul>
                    </AlertDescription>
                  </Alert>
                ) : (
                  <p className="text-xs text-muted-foreground">
                    Altere ao menos um campo para ver o impacto e habilitar o salvamento.
                  </p>
                )}

                <div className="flex justify-end gap-2 pt-2">
                  <Button variant="outline" onClick={resetCampos} disabled={saving || !hasChanges}>
                    Limpar
                  </Button>
                  <Button onClick={onSubmit} disabled={saving || !hasChanges}>
                    {saving ? 'Atualizando...' : 'Atualizar cobrança'}
                  </Button>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </AppShellV2>
  );
}
