// Campos do perfil clínico, usados no cadastro (Pacientes/Index) e na edição (Pacientes/Show).
// A validação é do servidor (UC-FPI-03); aqui só os campos e a mensagem de erro de cada um.
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { rotuloCondicao } from './condicoes';

export interface PerfilClinico {
  main_complaint: string;
  goals: string[];
  sessions_per_week: number | '';
  condition_flags: string[];
}

interface Props {
  dados: PerfilClinico;
  condicoes: string[];
  erros: Partial<Record<string, string>>;
  onChange: <K extends keyof PerfilClinico>(campo: K, valor: PerfilClinico[K]) => void;
}

const Erro = ({ msg }: { msg?: string }) => (msg ? <p className="text-xs text-destructive">{msg}</p> : null);

export default function PerfilClinicoCampos({ dados, condicoes, erros, onChange }: Props) {
  const erroFlag = Object.entries(erros).find(([k]) => k.startsWith('condition_flags'))?.[1];
  const alternar = (codigo: string, marcado: boolean) =>
    onChange('condition_flags', marcado ? [...dados.condition_flags, codigo] : dados.condition_flags.filter((c) => c !== codigo));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="fisio-queixa">Queixa principal</Label>
        <Textarea id="fisio-queixa" rows={2} value={dados.main_complaint} onChange={(e) => onChange('main_complaint', e.target.value)} />
        <Erro msg={erros.main_complaint} />
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="fisio-objetivos">Objetivos do paciente (um por linha)</Label>
        <Textarea
          id="fisio-objetivos"
          rows={3}
          placeholder="Pentear o cabelo sem dor"
          value={dados.goals.join('\n')}
          onChange={(e) => onChange('goals', e.target.value.split('\n'))}
        />
        <Erro msg={erros.goals} />
      </div>
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="fisio-sessoes">Dias de exercício por semana</Label>
        <Input
          id="fisio-sessoes"
          type="number"
          min={1}
          max={7}
          className="w-24"
          value={dados.sessions_per_week}
          onChange={(e) => onChange('sessions_per_week', e.target.value === '' ? '' : Number(e.target.value))}
        />
        <p className="text-xs text-muted-foreground">De 1 a 7. É a meta usada no cálculo de adesão.</p>
        <Erro msg={erros.sessions_per_week} />
      </div>
      <fieldset className="flex flex-col gap-2">
        <legend className="mb-1 text-sm font-medium">Condições que pedem cuidado na prescrição</legend>
        {condicoes.map((codigo) => (
          <label key={codigo} className="flex items-center gap-2 text-sm">
            <Checkbox checked={dados.condition_flags.includes(codigo)} onCheckedChange={(v) => alternar(codigo, v === true)} />
            {rotuloCondicao(codigo)}
          </label>
        ))}
        <Erro msg={erroFlag} />
      </fieldset>
    </div>
  );
}
