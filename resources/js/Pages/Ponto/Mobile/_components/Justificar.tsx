// Justificar — sub-tela do REP-P (ponto-mobile.jsx · Justificar). Envia a intercorrência que o
// servidor cria E submete (nasce PENDENTE na fila de Aprovações). A marcação original não muda.
import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { FormGrid, FormSection } from '@/Components/ui/form-section';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

function getCsrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
}

export default function Justificar({ tipos, hoje }: { tipos: Array<{ value: string; label: string }>; hoje: string }) {
  const vazio = { tipo: '', data: hoje, dia_todo: false, intervalo_inicio: '', intervalo_fim: '', justificativa: '' };
  const form = useForm(vazio);
  const { data: f, setData, errors, setError, clearErrors } = form;
  const [enviando, setEnviando] = useState(false);

  // Mesmas regras do StoreIntercorrenciaRequest — o servidor decide; aqui só evita a viagem.
  const erro = !f.tipo ? 'Escolha o motivo.'
    : !f.dia_todo && (!f.intervalo_inicio || !f.intervalo_fim) ? 'Informe o horário (das/às) ou marque Dia todo — o gestor decide pela janela.'
    : !f.dia_todo && f.intervalo_fim <= f.intervalo_inicio ? 'O fim precisa ser depois do início.'
    : f.justificativa.trim().length < 10 ? 'Descreva com pelo menos 10 caracteres.'
    : null;

  async function enviar(e: FormEvent) {
    e.preventDefault();
    if (erro || enviando) return;
    setEnviando(true);
    clearErrors();
    try {
      const r = await fetch('/ponto/mobile/intercorrencias', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json', Accept: 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          ...f,
          intervalo_inicio: f.dia_todo ? null : f.intervalo_inicio,
          intervalo_fim: f.dia_todo ? null : f.intervalo_fim,
        }),
      });
      const j = await r.json().catch(() => ({}));
      if (r.status === 422 && j.errors) {
        Object.entries(j.errors as Record<string, string[]>).forEach(([k, v]) => setError(k as keyof typeof vazio, v[0] ?? ''));
        return;
      }
      if (r.status !== 201) {
        toast.error(j.mensagem ?? 'Não foi possível enviar a justificativa.');
        return;
      }
      toast.success(`Justificativa ${j.intercorrencia?.codigo ?? ''} enviada — está na fila de Aprovações como pendente.`);
      form.setData(vazio);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <form onSubmit={enviar} className="mx-auto flex w-full max-w-md flex-col gap-4">
      <FormSection title="O que aconteceu">
        <FormGrid>
          <div data-contract="repp-motivos" className="flex flex-wrap gap-2">
            {tipos.map((t) => (
              <Button key={t.value} type="button" size="sm" className="min-h-11" aria-pressed={f.tipo === t.value}
                variant={f.tipo === t.value ? 'default' : 'outline'} onClick={() => setData('tipo', t.value)}>
                {t.label}
              </Button>
            ))}
          </div>
          {errors.tipo && <p role="alert" className="text-sm text-destructive">{errors.tipo}</p>}
        </FormGrid>
      </FormSection>

      <FormSection title="Quando">
        <FormGrid>
          <Label htmlFor="repp-dia">Dia</Label>
          <Input id="repp-dia" type="date" max={hoje} value={f.data} onChange={(e) => setData('data', e.target.value)} />
          {errors.data && <p role="alert" className="text-sm text-destructive">{errors.data}</p>}
          <Label className="flex min-h-11 items-center gap-2">
            <Checkbox checked={f.dia_todo} onCheckedChange={(v) => setData('dia_todo', v === true)} />
            Dia todo
          </Label>
          {!f.dia_todo && (
            <div className="grid grid-cols-2 gap-2">
              <span>
                <Label htmlFor="repp-das">Das</Label>
                <Input id="repp-das" type="time" value={f.intervalo_inicio} onChange={(e) => setData('intervalo_inicio', e.target.value)} />
              </span>
              <span>
                <Label htmlFor="repp-as">Às</Label>
                <Input id="repp-as" type="time" value={f.intervalo_fim} onChange={(e) => setData('intervalo_fim', e.target.value)} />
              </span>
            </div>
          )}
          {(errors.intervalo_inicio || errors.intervalo_fim) && (
            <p role="alert" className="text-sm text-destructive">{errors.intervalo_inicio ?? errors.intervalo_fim}</p>
          )}
        </FormGrid>
      </FormSection>

      <FormSection title="Justificativa">
        <FormGrid>
          <Textarea aria-label="Justificativa" rows={4} maxLength={2000} value={f.justificativa}
            onChange={(e) => setData('justificativa', e.target.value)} />
          {errors.justificativa && <p role="alert" className="text-sm text-destructive">{errors.justificativa}</p>}
        </FormGrid>
      </FormSection>

      <Button type="submit" className="min-h-11" disabled={!!erro || enviando} title={erro ?? ''}>Enviar para aprovação</Button>
      {erro && <p role="status" className="text-sm text-warning">{erro}</p>}
      <p className="text-xs text-muted-foreground">Vai para a fila do gestor como pendente. A marcação original não muda.</p>
    </form>
  );
}
