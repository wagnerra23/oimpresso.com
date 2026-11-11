// Laudo de avaliação biopsicossocial para fibromialgia (US-FISIO-060), no modelo da SES-SC
// (Deliberação 500/CIB/2026, versão 1.0 de 26/10/2026). Protótipo:
// prototipo-ui/cowork/Wagner/prototipos/fisio/LaudoBiopsicossocial.dc.html.
// O sistema calcula a pontuação da matriz (menor valor de cada domínio); a conclusão é marcada só
// pela equipe. Contrato: memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md.
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { FileText } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import { Textarea } from '@/Components/ui/textarea';

type SimNao = boolean | null | undefined;
interface Dados {
  identificacao?: { nascimento?: string; diagnostico_acr_data?: string; unidade_aps?: string; impedimentos?: string; cid?: string; natureza?: string[] };
  matriz?: Record<string, number>;
  fatores?: { ambientais?: string; pessoais?: string };
  conclusao?: { impedimento_longo_prazo?: SimNao; barreiras?: SimNao; equiparacao?: SimNao; obs?: string };
  local?: string;
  data?: string;
}
interface Membro { id: number; area: string; nome: string; registro: string; assinatura: 'sistema' | 'externa' }
export interface LaudoBps {
  id: number;
  criado_em: string;
  finalizado_em: string | null;
  corrige: number | null;
  dados: Dados;
  pontuacao: { por_dominio: Record<string, number | null>; faltando: string[]; completo: boolean; total: number | null };
  equipe: Membro[];
}
export interface Dominio { titulo: string; itens: Record<string, string> }
interface Apoio { texto: string; avisos: string[] }

const AREAS: Record<string, string> = {
  medicina: 'Medicina', enfermagem: 'Enfermagem', psicologia: 'Psicologia', servico_social: 'Serviço Social', fisioterapia_to: 'Fisioterapia/TO',
};
const NATUREZAS: [string, string][] = [['fisica', 'Física'], ['mental', 'Mental'], ['intelectual', 'Intelectual'], ['sensorial', 'Sensorial']];
const PERGUNTAS: [keyof NonNullable<Dados['conclusao']>, string][] = [
  ['impedimento_longo_prazo', 'Há impedimento de longo prazo?'],
  ['barreiras', 'Há barreiras que, na interação com o impedimento, geram limitação de atividades e restrição de participação?'],
  ['equiparacao', 'Equiparação à pessoa com deficiência (para fins da CIPF)?'],
];
const opcoes = { preserveScroll: true, only: ['laudos_bps'] };
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function Equipe({ laudo, editavel }: { laudo: LaudoBps; editavel: boolean }) {
  const [novo, setNovo] = useState({ area: 'medicina', nome: '', registro: '' });
  const [erros, setErros] = useState<Record<string, string>>({});
  const url = `/fisio/laudos-bps/${laudo.id}/equipe`;
  const incluir = (dados: Record<string, string>) =>
    router.post(url, dados, { ...opcoes, onError: setErros, onSuccess: () => { setErros({}); setNovo({ area: 'medicina', nome: '', registro: '' }); } });

  return (
    <fieldset className="flex flex-col gap-2 rounded-lg border border-border p-3">
      <legend className="px-1 font-semibold">Equipe multiprofissional (mínimo de dois profissionais de áreas distintas, um deles médico)</legend>
      {laudo.equipe.length === 0 && <p className="text-sm text-muted-foreground">Nenhum profissional incluído.</p>}
      {laudo.equipe.map((m) => (
        <div key={m.id} className="flex flex-wrap items-center gap-2 text-sm">
          <span className="flex-1">{AREAS[m.area]} · {m.nome} · {m.registro}{m.assinatura === 'sistema' ? ' · assina pelo sistema' : ' · assina no papel'}</span>
          {editavel && <Button size="sm" variant="ghost" onClick={() => router.delete(`${url}/${m.id}`, opcoes)}>Tirar</Button>}
        </div>
      ))}
      {editavel && (
        <>
          <Button size="sm" variant="outline" className="self-start" onClick={() => incluir({ area: 'fisioterapia_to', assinatura: 'sistema' })}>Incluir-me (Fisioterapia, CREFITO do perfil)</Button>
          <div className="flex flex-wrap items-end gap-2">
            <div className="flex flex-col gap-1.5">
              <span className="text-sm font-medium" id={`bps-area-${laudo.id}`}>Área</span>
              <Segmented aria-labelledby={`bps-area-${laudo.id}`} value={novo.area} onValueChange={(v) => v && setNovo({ ...novo, area: v })}
                options={Object.entries(AREAS).map(([value, label]) => ({ value, label }))} />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor={`bps-nome-${laudo.id}`}>Nome</Label>
              <Input id={`bps-nome-${laudo.id}`} value={novo.nome} onChange={(e) => setNovo({ ...novo, nome: e.target.value })} />
            </div>
            <div className="flex flex-col gap-1.5">
              <Label htmlFor={`bps-registro-${laudo.id}`}>Conselho/registro nº</Label>
              <Input id={`bps-registro-${laudo.id}`} value={novo.registro} onChange={(e) => setNovo({ ...novo, registro: e.target.value })} />
            </div>
            <Button variant="outline" onClick={() => incluir({ ...novo, assinatura: 'externa' })}>Incluir</Button>
          </div>
          <p className="text-xs text-muted-foreground">Profissional de fora da clínica não precisa de usuário: o PDF sai com espaço para a assinatura e o carimbo dele.</p>
          {Object.values(erros).map((m) => <p key={m} className="text-xs text-destructive">{m}</p>)}
        </>
      )}
    </fieldset>
  );
}

function Formulario({ laudo, dominios, editavel }: { laudo: LaudoBps; dominios: Record<string, Dominio>; editavel: boolean }) {
  const [d, setD] = useState<Dados>(laudo.dados);
  const [aberto, setAberto] = useState<string | null>(null);
  const [apoio, setApoio] = useState<Apoio | null>(null);
  const [erros, setErros] = useState<Record<string, string>>({});
  const id = d.identificacao ?? {};
  const matriz = d.matriz ?? {};
  const setId = (k: string, v: unknown) => setD({ ...d, identificacao: { ...id, [k]: v } });
  const setMatriz = (item: string, v: string) => setD({ ...d, matriz: { ...matriz, [item]: Number(v) } });
  const salvar = () => router.put(`/fisio/laudos-bps/${laudo.id}`, d as never, { ...opcoes, onError: setErros, onSuccess: () => setErros({}) });
  const finalizar = () => router.post(`/fisio/laudos-bps/${laudo.id}/finalizar`, {}, { ...opcoes, onError: setErros });
  const verApoio = async () => {
    const res = await fetch(`/fisio/laudos-bps/${laudo.id}/apoio-fisio`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' } });
    if (res.ok) setApoio(await res.json());
  };
  const ro = !editavel;

  return (
    <div className="flex flex-col gap-3">
      <fieldset disabled={ro} className="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-2">
        <legend className="px-1 font-semibold">Identificação e condição de saúde</legend>
        {([['nascimento', 'Nascimento', 'date'], ['unidade_aps', 'Unidade (APS)', 'text'], ['diagnostico_acr_data', 'Diagnóstico — laudo ACR 2016 (data)', 'date'], ['cid', 'CID', 'text']] as const).map(([k, rotulo, tipo]) => (
          <div key={k} className="flex flex-col gap-1.5">
            <Label htmlFor={`bps-${k}-${laudo.id}`}>{rotulo}</Label>
            <Input id={`bps-${k}-${laudo.id}`} type={tipo} value={(id[k] as string) ?? ''} onChange={(e) => setId(k, e.target.value)} />
          </div>
        ))}
        <div className="flex flex-col gap-1.5 sm:col-span-2">
          <Label htmlFor={`bps-imp-${laudo.id}`}>Impedimentos nas funções e estruturas do corpo (descrição)</Label>
          <Textarea id={`bps-imp-${laudo.id}`} rows={3} value={id.impedimentos ?? ''} onChange={(e) => setId('impedimentos', e.target.value)} />
        </div>
        <div className="flex flex-wrap gap-3 text-sm sm:col-span-2" role="group" aria-label="Natureza">
          <span className="text-muted-foreground">Natureza:</span>
          {NATUREZAS.map(([v, rotulo]) => (
            <label key={v} className="flex items-center gap-1.5">
              <Checkbox checked={(id.natureza ?? []).includes(v)}
                onCheckedChange={(c) => setId('natureza', c === true ? [...(id.natureza ?? []), v] : (id.natureza ?? []).filter((x) => x !== v))} /> {rotulo}
            </label>
          ))}
        </div>
      </fieldset>

      <fieldset disabled={ro} className="flex flex-col gap-2 rounded-lg border border-border p-3" data-contract="fisio-laudo-bps-matriz">
        <legend className="px-1 font-semibold">Matriz de atividades e participação (IFBr-M / CIF)</legend>
        <p className="text-xs text-muted-foreground">100 independente · 75 dificuldade leve/moderada ou com adaptação · 50 dificuldade grave ou ajuda parcial · 25 não realiza ou totalmente dependente. O total soma o menor valor de cada domínio.</p>
        {Object.entries(dominios).map(([cod, dom]) => {
          const itens = Object.keys(dom.itens);
          const feitos = itens.filter((i) => matriz[i] !== undefined).length;
          const menor = feitos === itens.length ? Math.min(...itens.map((i) => matriz[i] ?? 100)) : null;
          return (
            <div key={cod} className={`rounded-md border p-2 ${aberto === cod ? 'border-primary' : 'border-border'}`}>
              <button type="button" className="flex w-full items-center gap-2 text-left text-sm" aria-expanded={aberto === cod} onClick={() => setAberto(aberto === cod ? null : cod)}>
                <span className="flex-1">{cod}. {dom.titulo}</span>
                <span className="text-xs text-muted-foreground">{feitos} de {itens.length}{menor !== null ? ` · menor ${menor}` : ''}</span>
              </button>
              {aberto === cod && itens.map((item) => (
                <div key={item} className="mt-2 flex flex-wrap items-center gap-2 text-sm">
                  <span className="flex-1" id={`bps-item-${laudo.id}-${item}`}>{item} {dom.itens[item]}</span>
                  <Segmented aria-labelledby={`bps-item-${laudo.id}-${item}`} value={matriz[item] ? String(matriz[item]) : ''} onValueChange={(v) => v && setMatriz(item, v)}
                    options={['100', '75', '50', '25'].map((v) => ({ value: v, label: v }))} />
                </div>
              ))}
            </div>
          );
        })}
        <p className="text-sm"><span className="font-semibold">Pontuação total dos oito componentes:</span>{' '}
          {laudo.pontuacao.total ?? '—'}{' '}
          <span className="text-xs text-muted-foreground">{laudo.pontuacao.completo ? 'Calculada pelo que foi salvo.' : `Faltam ${laudo.pontuacao.faltando.length} itens salvos.`} O formulário diz: score de até 500 pontos que ateste a deficiência.</span>
        </p>
      </fieldset>

      <fieldset disabled={ro} className="flex flex-col gap-2 rounded-lg border border-border p-3">
        <legend className="px-1 font-semibold">Fatores contextuais</legend>
        {apoio ? (
          <div className="flex flex-col gap-1 rounded-md bg-muted p-3 text-sm" data-contract="fisio-laudo-bps-apoio">
            <span className="text-xs font-semibold">Apoio da fisioterapia — o que o Fisio mediu</span>
            <p className="whitespace-pre-line">{apoio.texto}</p>
            {apoio.avisos.map((a) => <p key={a} className="text-xs text-muted-foreground">{a}</p>)}
          </div>
        ) : (
          <Button type="button" size="sm" variant="outline" className="self-start" onClick={verApoio}>Ver o que o Fisio mediu</Button>
        )}
        {([['ambientais', 'Fatores ambientais — barreiras e facilitadores (acessibilidade, tecnologia assistiva, apoio de terceiros, trabalho/estudo, transporte, domicílio)'], ['pessoais', 'Fatores pessoais e psicológicos']] as const).map(([k, rotulo]) => (
          <div key={k} className="flex flex-col gap-1.5">
            <Label htmlFor={`bps-f-${k}-${laudo.id}`}>{rotulo}</Label>
            <Textarea id={`bps-f-${k}-${laudo.id}`} rows={3} value={d.fatores?.[k] ?? ''} onChange={(e) => setD({ ...d, fatores: { ...d.fatores, [k]: e.target.value } })} />
          </div>
        ))}
      </fieldset>

      <fieldset disabled={ro} className="flex flex-col gap-2 rounded-lg border border-border p-3" data-contract="fisio-laudo-bps-conclusao">
        <legend className="px-1 font-semibold">Conclusão da equipe</legend>
        <p className="text-xs text-muted-foreground">Marcada pela equipe. O sistema não responde estas perguntas pela pontuação.</p>
        {PERGUNTAS.map(([k, texto]) => {
          const v = d.conclusao?.[k];
          return (
            <div key={k} className="flex flex-wrap items-center gap-2 text-sm">
              <span className="flex-1" id={`bps-c-${k}-${laudo.id}`}>{texto}</span>
              <Segmented aria-labelledby={`bps-c-${k}-${laudo.id}`} value={v === true ? '1' : v === false ? '0' : ''}
                onValueChange={(x) => x && setD({ ...d, conclusao: { ...d.conclusao, [k]: x === '1' } })} options={[{ value: '1', label: 'Sim' }, { value: '0', label: 'Não' }]} />
            </div>
          );
        })}
        <Label htmlFor={`bps-obs-${laudo.id}`}>Observação</Label>
        <Textarea id={`bps-obs-${laudo.id}`} rows={2} value={d.conclusao?.obs ?? ''} onChange={(e) => setD({ ...d, conclusao: { ...d.conclusao, obs: e.target.value } })} />
      </fieldset>

      <Equipe laudo={laudo} editavel={editavel} />

      <fieldset disabled={ro} className="flex flex-wrap items-end gap-2">
        <div className="flex flex-col gap-1.5">
          <Label htmlFor={`bps-local-${laudo.id}`}>Local (obrigatório)</Label>
          <Input id={`bps-local-${laudo.id}`} value={d.local ?? ''} onChange={(e) => setD({ ...d, local: e.target.value })} />
        </div>
        <div className="flex flex-col gap-1.5">
          <Label htmlFor={`bps-data-${laudo.id}`}>Data (obrigatória)</Label>
          <Input id={`bps-data-${laudo.id}`} type="date" value={d.data ?? ''} onChange={(e) => setD({ ...d, data: e.target.value })} />
        </div>
        {editavel && (
          <div className="ml-auto flex gap-2">
            <Button variant="outline" onClick={salvar}>Salvar</Button>
            <Button onClick={finalizar}>Finalizar</Button>
          </div>
        )}
      </fieldset>
      {Object.values(erros).map((m) => <p key={m} className="text-xs text-destructive">{m}</p>)}
    </div>
  );
}

export default function LaudoBiopsicossocial({ pacienteId, laudos, dominios, podeEditar }: { pacienteId: number; laudos: LaudoBps[]; dominios: Record<string, Dominio>; podeEditar: boolean }) {
  const laudo = laudos[0];
  const finalizado = !!laudo?.finalizado_em;

  return (
    <div className="flex flex-col gap-3" data-contract="fisio-laudo-bps">
      <div className="flex flex-wrap items-center gap-2">
        {laudo && (
          <span className="text-xs text-muted-foreground">
            Laudo nº {laudo.id} · {finalizado ? `finalizado em ${laudo.finalizado_em}` : 'rascunho'}{laudo.corrige ? ` · corrige o nº ${laudo.corrige}` : ''} · modelo SES-SC (Deliberação 500/CIB/2026)
          </span>
        )}
        <div className="ml-auto flex gap-2">
          {laudo && (
            <Button variant="outline" size="sm" asChild>
              <a href={`/fisio/laudos-bps/${laudo.id}/pdf`} data-contract="fisio-laudo-bps-pdf"><FileText className="size-4" aria-hidden="true" /> Baixar PDF</a>
            </Button>
          )}
          {podeEditar && (!laudo || finalizado) && (
            <Button variant="outline" size="sm" onClick={() => router.post(laudo ? `/fisio/laudos-bps/${laudo.id}/nova-versao` : `/fisio/pacientes/${pacienteId}/laudos-bps`, {}, opcoes)}>
              {laudo ? 'Nova versão' : 'Novo laudo'}
            </Button>
          )}
        </div>
      </div>
      <p role="note" className="rounded-md bg-muted p-3 text-sm">
        Em Santa Catarina o laudo é emitido pela Atenção Primária do município, e a carteira (CIPF) segue o fluxo da FCEE. Este documento organiza a avaliação da equipe; a decisão é dela e do fluxo oficial.
      </p>
      {laudo ? (
        <Formulario key={laudo.id} laudo={laudo} dominios={dominios} editavel={podeEditar && !finalizado} />
      ) : (
        <p className="text-sm text-muted-foreground">Nenhum laudo biopsicossocial para este paciente.</p>
      )}
    </div>
  );
}
