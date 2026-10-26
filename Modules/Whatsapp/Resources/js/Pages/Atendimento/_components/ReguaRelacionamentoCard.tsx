// Régua de relacionamento por WhatsApp (US-WA-328) — liga e escreve as mensagens de aniversário e
// de recompra da empresa. O motor é o US-WA-326: manda 1×/dia, só para quem deu opt-in de
// marketing, espaçado. `{nome}` vira o primeiro nome do cliente.

import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

interface ReguaTipo { ativo: boolean; texto: string; dias_sem_compra: number | null }
export interface ReguasPayload { tipos: Record<'aniversario' | 'recompra', ReguaTipo>; ligado_no_sistema: boolean }

const TITULO = { aniversario: 'Aniversário', recompra: 'Recompra' } as const;
const DESCRICAO = {
  aniversario: 'No dia do aniversário do cliente (data de aniversário do cadastro ou, sem ela, a data de nascimento).',
  recompra: 'Quando a última compra do cliente completa o número de dias abaixo.',
} as const;

function Regua({ tipo, inicial }: { tipo: 'aniversario' | 'recompra'; inicial: ReguaTipo }) {
  const [ativo, setAtivo] = useState(inicial.ativo);
  const [texto, setTexto] = useState(inicial.texto);
  const [dias, setDias] = useState(String(inicial.dias_sem_compra ?? 30));
  const [erros, setErros] = useState<Record<string, string>>({});
  const [salvando, setSalvando] = useState(false);

  const salvar = () => {
    setSalvando(true);
    router.put('/atendimento/canais/regua-relacionamento', {
      tipo, ativo, texto, dias_sem_compra: tipo === 'recompra' ? Number(dias) : null,
    }, {
      preserveScroll: true, preserveState: true, only: ['reguas'],
      onError: (e) => setErros(e), onSuccess: () => setErros({}), onFinish: () => setSalvando(false),
    });
  };

  return (
    <div className="flex flex-col gap-3 rounded-md border p-3" data-testid={`regua-${tipo}`}>
      <div className="flex items-center justify-between gap-4">
        <div>
          <Label htmlFor={`regua-${tipo}-ativo`}>{TITULO[tipo]}</Label>
          <p className="text-xs text-muted-foreground">{DESCRICAO[tipo]}</p>
        </div>
        <Switch id={`regua-${tipo}-ativo`} checked={ativo} onCheckedChange={setAtivo} />
      </div>
      <div className="flex flex-col gap-1">
        <Label htmlFor={`regua-${tipo}-texto`}>Mensagem</Label>
        <Textarea id={`regua-${tipo}-texto`} rows={3} value={texto} onChange={(e) => setTexto(e.target.value)} />
        {erros.texto && <small className="text-xs text-destructive">{erros.texto}</small>}
      </div>
      {tipo === 'recompra' && (
        <div className="flex flex-col gap-1">
          <Label htmlFor="regua-recompra-dias">Dias depois da última compra</Label>
          <Input id="regua-recompra-dias" type="number" min={7} max={365} className="w-32" value={dias} onChange={(e) => setDias(e.target.value)} />
          {erros.dias_sem_compra && <small className="text-xs text-destructive">{erros.dias_sem_compra}</small>}
        </div>
      )}
      <Button className="self-end" size="sm" onClick={salvar} disabled={salvando}>{salvando ? 'Salvando…' : 'Salvar'}</Button>
    </div>
  );
}

export default function ReguaRelacionamentoCard({ dados }: { dados: ReguasPayload }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Régua de relacionamento</CardTitle>
        <CardDescription>
          Mensagens automáticas de aniversário e de recompra, só para clientes que aceitaram receber ofertas.
          Use {'{nome}'} para o primeiro nome do cliente.
        </CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        {!dados.ligado_no_sistema && (
          <p className="rounded-md border border-warning/20 bg-warning-soft p-3 text-sm text-warning-fg">
            A régua está desligada no sistema. Você pode deixar as mensagens prontas; elas só saem depois de ligada.
          </p>
        )}
        <Regua tipo="aniversario" inicial={dados.tipos.aniversario} />
        <Regua tipo="recompra" inicial={dados.tipos.recompra} />
      </CardContent>
    </Card>
  );
}
