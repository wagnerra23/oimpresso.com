// Medicamentos em uso, estruturados (US-FISIO-063, parte 2). Protótipo: ExamesReceitas.dc.html (bloco 5).
// Casos: memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md (UC-FER-08..11).
// O texto livre "Medicamentos em uso" da avaliação continua como está; esta lista é o que está em uso
// agora. O sistema não confere interação, dose nem contraindicação (ADR 0437 §5): guarda o que foi digitado.
import { useState } from 'react';
import { Plus } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { mandar } from './ExamesReceitas';

export interface Medicamento {
  id: number;
  nome: string;
  dose: string | null;
  frequencia: string | null;
  desde: string | null;
  ate: string | null;
  em_uso: boolean;
  origem: 'manual' | 'transcricao';
  documento_id: number | null;
  conferido_por: string | null;
}

const data = (iso: string | null) => (iso ? new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR') : '—');
const hoje = () => new Date().toLocaleDateString('sv-SE');

function Novo({ pacienteId, onPronto }: { pacienteId: number; onPronto: () => void }) {
  const [f, setF] = useState({ nome: '', dose: '', frequencia: '', desde: '', ate: '' });
  const [erros, setErros] = useState<Record<string, string>>({});
  const campo = (k: keyof typeof f, rotulo: string, tipo = 'text') => (
    <div className="flex flex-col gap-1.5">
      <Label htmlFor={`med-${k}`}>{rotulo}</Label>
      <Input id={`med-${k}`} type={tipo} value={f[k]} onChange={(e) => setF({ ...f, [k]: e.target.value })} />
      {erros[k] && <p className="text-xs text-destructive">{erros[k]}</p>}
    </div>
  );
  const salvar = async () => {
    const e = await mandar('POST', `/fisio/pacientes/${pacienteId}/medicamentos`, f);
    if (e) return setErros(e);
    onPronto();
  };

  return (
    <fieldset className="flex flex-col gap-3 rounded-lg border border-border p-3" data-contract="fisio-medicamento-novo">
      <legend className="px-1 text-sm font-semibold">Novo medicamento</legend>
      <div className="grid gap-3 sm:grid-cols-3">
        {campo('nome', 'Nome')}{campo('dose', 'Dose')}{campo('frequencia', 'Frequência')}
        {campo('desde', 'Desde', 'date')}{campo('ate', 'Até (se já tem fim)', 'date')}
      </div>
      <div className="flex gap-2">
        {erros.geral && <p className="text-xs text-destructive">{erros.geral}</p>}
        <Button type="button" onClick={() => void salvar()}>Incluir</Button>
        <Button type="button" variant="ghost" onClick={onPronto}>Cancelar</Button>
      </div>
    </fieldset>
  );
}

function Linha({ m, podeEditar }: { m: Medicamento; podeEditar: boolean }) {
  const [tirando, setTirando] = useState(false);
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const encerrar = async () => {
    const e = await mandar('PUT', `/fisio/medicamentos/${m.id}`, { ate: hoje() });
    if (e) setErro(Object.values(e)[0] ?? null);
  };
  const tirar = async () => {
    const e = await mandar('POST', `/fisio/medicamentos/${m.id}/tirar`, { motivo });
    if (e) setErro(e.motivo ?? e.geral ?? 'Não foi possível tirar da lista.');
  };

  return (
    <tr className="border-t border-border align-top" data-contract="fisio-medicamento">
      <td className="py-2 pr-2 font-medium">{m.nome}{!m.em_uso && <Badge variant="outline" className="ml-2">Encerrado</Badge>}</td>
      <td className="py-2 pr-2">{m.dose || '—'}</td>
      <td className="py-2 pr-2">{m.frequencia || '—'}</td>
      <td className="py-2 pr-2">{data(m.desde)}</td>
      <td className="py-2 pr-2">{data(m.ate)}</td>
      <td className="py-2 pr-2 text-muted-foreground">
        {m.origem === 'transcricao' ? `Da receita, conferido${m.conferido_por ? ` por ${m.conferido_por}` : ''}` : 'Digitado'}
      </td>
      <td className="py-2">
        {podeEditar && (tirando ? (
          <div className="flex min-w-56 flex-col gap-2">
            <Label htmlFor={`med-motivo-${m.id}`}>Por que sai da lista?</Label>
            <Input id={`med-motivo-${m.id}`} maxLength={255} value={motivo} onChange={(e) => setMotivo(e.target.value)} />
            <div className="flex gap-2">
              <Button size="sm" variant="destructive" onClick={() => void tirar()}>Tirar</Button>
              <Button size="sm" variant="ghost" onClick={() => setTirando(false)}>Cancelar</Button>
            </div>
          </div>
        ) : (
          <div className="flex gap-1">
            {m.em_uso && <Button size="sm" variant="outline" onClick={() => void encerrar()}>Encerrar hoje</Button>}
            <Button size="sm" variant="ghost" onClick={() => setTirando(true)}>Tirar da lista</Button>
          </div>
        ))}
        {erro && <p className="text-xs text-destructive">{erro}</p>}
      </td>
    </tr>
  );
}

export default function MedicamentosEmUso({ pacienteId, medicamentos, podeEditar }: { pacienteId: number; medicamentos: Medicamento[]; podeEditar: boolean }) {
  const [novo, setNovo] = useState(false);

  return (
    <div className="flex flex-col gap-2" data-contract="fisio-medicamentos">
      <h3 className="text-sm font-semibold">Medicamentos em uso</h3>
      {medicamentos.length === 0 ? (
        <p className="text-sm text-muted-foreground">Nenhum medicamento na lista. O texto "Medicamentos em uso" da avaliação continua no prontuário.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-muted-foreground">
                <th className="py-1 pr-2 font-normal">Nome</th><th className="py-1 pr-2 font-normal">Dose</th>
                <th className="py-1 pr-2 font-normal">Frequência</th><th className="py-1 pr-2 font-normal">Desde</th>
                <th className="py-1 pr-2 font-normal">Até</th><th className="py-1 pr-2 font-normal">Origem</th><th />
              </tr>
            </thead>
            <tbody>{medicamentos.map((m) => <Linha key={m.id} m={m} podeEditar={podeEditar} />)}</tbody>
          </table>
        </div>
      )}
      {podeEditar && (novo ? <Novo pacienteId={pacienteId} onPronto={() => setNovo(false)} /> : (
        <Button type="button" variant="outline" className="self-start" onClick={() => setNovo(true)}>
          <Plus className="size-4" aria-hidden="true" /> Adicionar medicamento
        </Button>
      ))}
    </div>
  );
}
