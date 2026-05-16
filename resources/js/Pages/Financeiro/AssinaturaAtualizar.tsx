// FIN-004 — Atualizar cobranca recorrente
// HITL pending Wagner. Cuidado biz=4 prod ROTA LIVRE.
// UI minimal — Wagner aprova antes de evoluir layout estado-da-arte.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
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

  const onSubmit = () => {
    if (!selected) {
      toast.error('Selecione uma assinatura.');
      return;
    }

    const payload: Record<string, unknown> = {};
    if (valor.trim() !== '') payload.valor = Number(valor);
    if (ciclo) payload.ciclo = ciclo;
    if (forma) payload.forma_pagamento = forma;

    if (Object.keys(payload).length === 0) {
      toast.warning('Preencha ao menos um campo.');
      return;
    }

    setSaving(true);
    router.patch(`/financeiro/assinaturas/${selected.id}`, payload, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Cobranca atualizada.');
        setValor('');
        setCiclo('');
        setForma('');
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
      <div className="container mx-auto max-w-2xl py-6">
        <Card>
          <CardHeader>
            <CardTitle>Atualizar cobranca de assinatura</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="assinatura">Assinatura</Label>
              <Select value={selectedId} onValueChange={setSelectedId}>
                <SelectTrigger id="assinatura">
                  <SelectValue placeholder="Selecione" />
                </SelectTrigger>
                <SelectContent>
                  {assinaturas.map((a) => (
                    <SelectItem key={a.id} value={String(a.id)}>
                      #{a.id} {a.plano ?? 'Sem plano'} ({a.status})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {selected && (
                <p className="text-xs text-muted-foreground">
                  Atual: R$ {selected.valor_atual ?? '?'} / {selected.ciclo_atual ?? '?'} /{' '}
                  {selected.forma_pagamento_atual}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="valor">Novo valor (R$)</Label>
              <Input
                id="valor"
                type="number"
                min="0.01"
                step="0.01"
                value={valor}
                onChange={(e) => setValor(e.target.value)}
                placeholder="ex: 250.00"
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

            <div className="flex justify-end gap-2 pt-2">
              <Button
                variant="outline"
                onClick={() => {
                  setValor('');
                  setCiclo('');
                  setForma('');
                }}
                disabled={saving}
              >
                Limpar
              </Button>
              <Button onClick={onSubmit} disabled={saving || !selected}>
                {saving ? 'Atualizando...' : 'Atualizar'}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppShellV2>
  );
}
