// Aba Modelos — perfil porte × atividade e cards de modelo com o selo de encaixe.
// Fonte visual: `FluxosConteudo` (aba "modelos") e `ModeloCard` em
// prototipo-ui/cowork/Wagner/fluxos-page.jsx. Só leitura neste PR: o detalhe do modelo e
// "Usar modelo" entram com a aba Fluxos da empresa; o assistente de IA, no PR 5.

import { useMemo } from 'react';
import { Segmented } from '@/Components/ui/segmented';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import EmptyState from '@/Components/shared/EmptyState';
import Assistente from './Assistente';
import { Cadeia, SeloEncaixe } from './Cadeia';
import type { Encaixe, MapaCasos, MapaEncaixe, Modelo, Opcao, Perfil } from './tipos';

interface Props {
  modelos: Modelo[];
  encaixe: MapaEncaixe;
  portes: Opcao[];
  atividades: Opcao[];
  perfil: Perfil;
  onPerfil: (p: Perfil) => void;
  emUsoId: string | null;
  onAbrir: (id: string) => void;
  casos: MapaCasos;
}

/** Combinação ausente no mapa (não deve acontecer: o servidor manda todas). */
const SEM_ENCAIXE: Encaixe = { n: 0, rotulo: 'Outro perfil', motivo: 'Outro porte e outra atividade' };

const rotulo = (lista: Opcao[], v: string) => lista.find((o) => o.value === v)?.label ?? v;

function ModeloCard({ m, e, emUso, onAbrir }: { m: Modelo; e: Encaixe; emUso: boolean; onAbrir: () => void }) {
  // Processo de destaque: Produção, senão Entrega, senão o primeiro (igual ao protótipo).
  const principal = m.processos.find((p) => p.dominio === 'producao')
    ?? m.processos.find((p) => p.dominio === 'entrega')
    ?? m.processos[0];
  const regras = m.processos.reduce((s, p) => s + p.regras.length, 0);

  return (
    <button type="button" className={`flx-card${e.n < 2 ? ' fraco' : ''}`} data-modelo={m.id} onClick={onAbrir}>
      <div className="flx-card-h">
        <b>{m.nome}</b>
        {emUso ? <span className="flx-pill accent">Em uso</span> : <SeloEncaixe n={e.n} rotulo={e.rotulo} />}
      </div>
      <span className={`flx-mot${e.n >= 4 ? ' ok' : ''}`}>{e.motivo}</span>
      <p>{m.para}</p>
      {principal && (
        <div className="flx-card-p">
          <span>{principal.nome}</span>
          <Cadeia etapas={principal.etapas} marcadas={new Set(principal.regras.map((r) => r.etapa))} />
        </div>
      )}
      <div className="flx-card-f">
        <span>
          {m.processos.length} processos · {regras} regras · {m.casos.length} casos de uso
        </span>
      </div>
    </button>
  );
}

export default function AbaModelos({ modelos, encaixe, portes, atividades, perfil, onPerfil, emUsoId, onAbrir, casos }: Props) {
  const nota = (m: Modelo): Encaixe => encaixe[m.id]?.[perfil.porte]?.[perfil.ativ] ?? SEM_ENCAIXE;
  // Ordem do protótipo: maior encaixe primeiro; empate mantém a ordem do catálogo (sort estável).
  const ordenados = useMemo(
    () => [...modelos].sort((a, b) => nota(b).n - nota(a).n),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [modelos, perfil.porte, perfil.ativ],
  );
  const bons = ordenados.filter((m) => nota(m).n >= 2);
  const outros = ordenados.filter((m) => nota(m).n < 2);
  const perfilTexto = `${rotulo(portes, perfil.porte).toLowerCase()} · ${rotulo(atividades, perfil.ativ).toLowerCase()}`;

  return (
    <>
      <div className="flx-perfil">
        <div className="flx-perfil-c">
          <span className="flx-lbl" id="flx-porte">Porte da empresa</span>
          <Segmented
            aria-labelledby="flx-porte"
            value={perfil.porte}
            onValueChange={(v) => onPerfil({ ...perfil, porte: v })}
            options={portes}
          />
        </div>
        <div className="flx-perfil-c flx-perfil-sel">
          <span className="flx-lbl" id="flx-ativ">Atividade principal</span>
          <Select value={perfil.ativ} onValueChange={(v) => onPerfil({ ...perfil, ativ: v })}>
            <SelectTrigger aria-labelledby="flx-ativ"><SelectValue /></SelectTrigger>
            <SelectContent>
              {atividades.map((a) => <SelectItem key={a.value} value={a.value}>{a.label}</SelectItem>)}
            </SelectContent>
          </Select>
        </div>
      </div>

      <Assistente
        perfil={perfil}
        modelos={modelos}
        casos={casos}
        portes={portes}
        atividades={atividades}
        onVer={onAbrir}
        onPerfil={onPerfil}
      />

      <div className="flx-sec"><span>Para {perfilTexto}</span><i /></div>
      {bons.length > 0 ? (
        <div className="flx-grid">
          {bons.map((m) => <ModeloCard key={m.id} m={m} e={nota(m)} emUso={m.id === emUsoId} onAbrir={() => onAbrir(m.id)} />)}
        </div>
      ) : (
        <EmptyState
          title="Nenhum modelo feito para este perfil"
          description="Os modelos abaixo servem de ponto de partida; ajuste as etapas depois de escolher."
        />
      )}
      {outros.length > 0 && (
        <>
          <div className="flx-sec"><span>Outros modelos</span><i /></div>
          <div className="flx-grid">
            {outros.map((m) => <ModeloCard key={m.id} m={m} e={nota(m)} emUso={m.id === emUsoId} onAbrir={() => onAbrir(m.id)} />)}
          </div>
        </>
      )}
    </>
  );
}
