// Conferência da transcrição sugerida pela IA (US-FISIO-063, parte 3). Protótipo: ExamesReceitas.dc.html (bloco 3).
// Casos: memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md (UC-FER-12..16).
// A IA só copiou o que está escrito; cada campo é sugestão (destacado) até o profissional confirmar ou
// corrigir. Não há campo de interpretação: o sistema não diz se um resultado é normal nem sugere conduta
// (ADR 0437 §5). Quem confirma fica registrado no servidor.
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

export interface MedicamentoTranscrito { nome: string; dose: string | null; frequencia: string | null }
export interface CamposTranscricao {
  data: string | null;
  medico?: string | null;
  crm?: string | null;
  medicamentos?: MedicamentoTranscrito[];
  tipo_exame?: string | null;
  conclusao?: string | null;
}
export interface Transcricao {
  id: number;
  status: 'pendente' | 'conferida';
  sugestao: CamposTranscricao;
  confirmado: CamposTranscricao | null;
  corrigidos: string[];
  conferido_por: string | null;
  conferido_em: string | null;
}

// Campo ainda igual à sugestão = destacado (amarelo do protótipo); corrigido = borda normal.
const SUGESTAO = 'border-warning bg-warning-soft';

function Campo({ id, rotulo, valor, sugerido, tipo = 'text', onChange }: {
  id: string; rotulo: string; valor: string; sugerido: string; tipo?: string; onChange: (v: string) => void;
}) {
  return (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={id}>{rotulo}</Label>
      <Input id={id} type={tipo} value={valor} onChange={(e) => onChange(e.target.value)} className={valor !== '' && valor === sugerido ? SUGESTAO : ''} />
    </div>
  );
}

export default function ConferirTranscricao({ t, receita }: { t: Transcricao; receita: boolean }) {
  const s = t.sugestao;
  const [f, setF] = useState({
    data: s.data ?? '', medico: s.medico ?? '', crm: s.crm ?? '', tipo_exame: s.tipo_exame ?? '', conclusao: s.conclusao ?? '',
  });
  const [meds, setMeds] = useState<MedicamentoTranscrito[]>(s.medicamentos ?? []);
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);
  const sugMed = (i: number, k: keyof MedicamentoTranscrito) => (s.medicamentos?.[i]?.[k] ?? '') as string;

  const confirmar = () => {
    setEnviando(true);
    const url = `/fisio/transcricoes/${t.id}/confirmar`;
    const opcoes = { preserveScroll: true, onError: (e: Record<string, string>) => setErros(e), onFinish: () => setEnviando(false) };
    if (receita) {
      router.post(url, { data: f.data || null, medico: f.medico || null, crm: f.crm || null, medicamentos: meds.map((m) => ({ ...m })) }, opcoes);
    } else {
      router.post(url, { data: f.data || null, tipo_exame: f.tipo_exame || null, conclusao: f.conclusao || null }, opcoes);
    }
  };
  const descartar = () => router.post(`/fisio/transcricoes/${t.id}/descartar`, {}, { preserveScroll: true });

  return (
    <fieldset className="flex flex-col gap-3 rounded-lg border border-border p-3" data-contract="fisio-transcricao-conferir">
      <legend className="px-1 text-sm font-semibold">Conferir transcrição</legend>
      <p role="note" className="rounded-md bg-muted px-3 py-2 text-xs">
        A IA só copiou o que está escrito no documento. Campo em destaque é sugestão: confira com o documento e corrija antes de gravar. Quem confirma fica registrado.
      </p>
      <div className="grid gap-3 sm:grid-cols-3">
        <Campo id={`tr-data-${t.id}`} rotulo={receita ? 'Data da receita' : 'Data do exame'} tipo="date" valor={f.data} sugerido={s.data ?? ''} onChange={(v) => setF({ ...f, data: v })} />
        {receita ? (
          <>
            <Campo id={`tr-medico-${t.id}`} rotulo="Médico" valor={f.medico} sugerido={s.medico ?? ''} onChange={(v) => setF({ ...f, medico: v })} />
            <Campo id={`tr-crm-${t.id}`} rotulo="CRM" valor={f.crm} sugerido={s.crm ?? ''} onChange={(v) => setF({ ...f, crm: v })} />
          </>
        ) : (
          <Campo id={`tr-tipo-${t.id}`} rotulo="Exame" valor={f.tipo_exame} sugerido={s.tipo_exame ?? ''} onChange={(v) => setF({ ...f, tipo_exame: v })} />
        )}
      </div>
      {receita ? (
        <div className="flex flex-col gap-2">
          {meds.length === 0 && <p className="text-sm text-muted-foreground">A IA não encontrou medicamento legível. Inclua à mão, se houver.</p>}
          {meds.map((m, i) => (
            <div key={i} className="grid items-end gap-2 sm:grid-cols-[2fr_1fr_2fr_auto]">
              <Campo id={`tr-m${i}-nome-${t.id}`} rotulo="Medicamento" valor={m.nome} sugerido={sugMed(i, 'nome')} onChange={(v) => setMeds(meds.map((x, j) => (j === i ? { ...x, nome: v } : x)))} />
              <Campo id={`tr-m${i}-dose-${t.id}`} rotulo="Dose" valor={m.dose ?? ''} sugerido={sugMed(i, 'dose')} onChange={(v) => setMeds(meds.map((x, j) => (j === i ? { ...x, dose: v || null } : x)))} />
              <Campo id={`tr-m${i}-freq-${t.id}`} rotulo="Frequência" valor={m.frequencia ?? ''} sugerido={sugMed(i, 'frequencia')} onChange={(v) => setMeds(meds.map((x, j) => (j === i ? { ...x, frequencia: v || null } : x)))} />
              <Button type="button" variant="outline" size="sm" onClick={() => setMeds(meds.filter((_, j) => j !== i))}>Tirar</Button>
              {erros[`medicamentos.${i}.nome`] && <p className="text-xs text-destructive sm:col-span-4">{erros[`medicamentos.${i}.nome`]}</p>}
            </div>
          ))}
          <Button type="button" variant="ghost" size="sm" className="self-start" onClick={() => setMeds([...meds, { nome: '', dose: null, frequencia: null }])}>Incluir medicamento</Button>
        </div>
      ) : (
        <div className="flex flex-col gap-1.5">
          <Label htmlFor={`tr-conclusao-${t.id}`}>Conclusão, como está escrita</Label>
          <Textarea id={`tr-conclusao-${t.id}`} rows={3} value={f.conclusao} onChange={(e) => setF({ ...f, conclusao: e.target.value })}
            className={f.conclusao !== '' && f.conclusao === (s.conclusao ?? '') ? SUGESTAO : ''} />
        </div>
      )}
      {(erros.transcricao || erros.data) && <p className="text-xs text-destructive">{erros.transcricao ?? erros.data}</p>}
      <div className="flex gap-2">
        <Button type="button" disabled={enviando} onClick={confirmar}>Confirmar e gravar</Button>
        <Button type="button" variant="ghost" onClick={descartar}>Descartar sugestão</Button>
      </div>
    </fieldset>
  );
}

/** O que foi conferido: só o texto gravado e quem conferiu. */
export function TranscricaoConferida({ t, receita }: { t: Transcricao; receita: boolean }) {
  const c: CamposTranscricao = t.confirmado ?? { data: null };
  const quando = t.conferido_em ? new Date(t.conferido_em).toLocaleDateString('pt-BR') : '';
  return (
    <div className="flex flex-col gap-1 text-sm" data-contract="fisio-transcricao-conferida">
      {receita ? (
        <span>{[c.medico, c.crm].filter(Boolean).join(', ') || 'Receita'} · {(c.medicamentos ?? []).length} medicamento(s) na lista</span>
      ) : (
        <span>{c.tipo_exame ? `${c.tipo_exame}: ` : ''}{c.conclusao ? `"${c.conclusao}"` : 'sem conclusão transcrita'}</span>
      )}
      <span className="text-xs text-muted-foreground">Transcrição conferida{t.conferido_por ? ` por ${t.conferido_por}` : ''}{quando ? ` em ${quando}` : ''}{t.corrigidos.length ? ` · ${t.corrigidos.length} campo(s) corrigido(s)` : ''}</span>
    </div>
  );
}
