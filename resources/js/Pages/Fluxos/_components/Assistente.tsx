// Assistente de configuração — descrição livre → modelo sugerido.
// Fonte visual: `Assistente` em prototipo-ui/cowork/Wagner/fluxos-page.jsx (exemplos, texto,
// Ctrl+Enter, sugestão com "Ver o modelo" e "Ajustar perfil"). A IA é a do ERP
// (POST /fluxos/assistente → RecomendarModelo); sem IA o servidor cai na regra de encaixe e
// devolve `origem = regra`, e a tela diz "Sugestão pelo perfil".

import { useState, type KeyboardEvent } from 'react';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import type { MapaCasos, Modelo, Opcao, Perfil } from './tipos';

interface Sugestao {
  modelo: string;
  porque: string;
  ligar: string[];
  desligar: string[];
  porte: string;
  atividade: string;
  origem: 'ia' | 'regra';
}

interface Props {
  perfil: Perfil;
  modelos: Modelo[];
  casos: MapaCasos;
  portes: Opcao[];
  atividades: Opcao[];
  onVer: (id: string) => void;
  onPerfil: (p: Perfil) => void;
}

const EXEMPLOS = [
  { l: 'Gráfica rápida, 3 pessoas', t: 'Somos 3 pessoas, gráfica rápida no centro. Cartão, panfleto e adesivo pequeno, quase tudo entregue no mesmo dia, pagamento no balcão.' },
  { l: 'Comunicação visual com instalação', t: '12 pessoas, banner, adesivo e fachada. Duas equipes de instalação. O cliente aprova a arte pelo celular e pagamos 50% de sinal antes de produzir.' },
  { l: 'Indústria gráfica com PCP', t: '60 pessoas em dois turnos, offset e digital. O gargalo é decidir o que entra na máquina; papel muda de preço todo mês.' },
  { l: 'Sublimação e camisetas', t: 'Brindes, canecas e camisetas DTF. Compramos por grade de cor e tamanho e vendemos muito pela internet.' },
];

const rot = (l: Opcao[], v: string) => l.find((o) => o.value === v)?.label ?? v;

export default function Assistente({ perfil, modelos, casos, portes, atividades, onVer, onPerfil }: Props) {
  const [texto, setTexto] = useState('');
  const [fase, setFase] = useState<'livre' | 'pensando' | 'pronto' | 'erro'>('livre');
  const [sug, setSug] = useState<Sugestao | null>(null);

  const pedir = async () => {
    if (fase === 'pensando') return;
    setFase('pensando');
    try {
      const token = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const r = await fetch('/fluxos/assistente', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ texto, porte: perfil.porte, atividade: perfil.ativ }),
      });
      if (!r.ok) throw new Error(String(r.status));
      setSug((await r.json()) as Sugestao);
      setFase('pronto');
    } catch {
      setFase('erro');
    }
  };
  const atalho = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); void pedir(); }
  };
  const m = sug ? modelos.find((x) => x.id === sug.modelo) : undefined;

  return (
    <section className="flx-card" aria-label="Assistente de configuração" style={{ cursor: 'default' }}>
      <div className="flx-card-h">
        <b>Assistente de configuração</b>
      </div>
      <p>Conte como a empresa trabalha. A IA sugere um modelo; quem decide é você.</p>
      <div className="flx-ia">
        <div className="flx-ex">
          <span className="flx-lbl">Começar de um exemplo</span>
          {EXEMPLOS.map((e) => (
            <button key={e.l} type="button" className={`flp-chip${texto === e.t ? ' on' : ''}`} aria-pressed={texto === e.t} onClick={() => setTexto(e.t)}>
              {e.l}
            </button>
          ))}
        </div>
        <label className="flx-lbl" htmlFor="flx-ia-texto">Como a empresa trabalha</label>
        <Textarea
          id="flx-ia-texto"
          value={texto}
          maxLength={2000}
          onChange={(e) => setTexto(e.target.value)}
          onKeyDown={atalho}
          placeholder="Ex.: 12 pessoas, banner e adesivo, duas equipes de instalação, o cliente aprova a arte pelo WhatsApp e a lona sobe de preço todo mês."
        />
        <div className="flx-ia-acoes">
          <Button size="sm" onClick={() => void pedir()} disabled={fase === 'pensando'}>
            {fase === 'pensando' ? 'Analisando…' : 'Recomendar modelo'}
          </Button>
          <span className="flx-dim">
            Usa o perfil acima: {rot(portes, perfil.porte)} · {rot(atividades, perfil.ativ)}
            {texto ? ' · Ctrl+Enter envia' : ' · sem descrição, decide só pelo perfil'}
          </span>
        </div>
        {fase === 'erro' && <p className="flx-dim" role="alert">Não foi possível pedir a sugestão agora. Tente de novo.</p>}
        {fase === 'pronto' && sug && m && (
          <div className="flx-rec" role="status">
            <div className="flx-rec-h">
              <span className="flx-dim">{sug.origem === 'ia' ? 'Sugestão da IA' : 'Sugestão pelo perfil'}</span>
              <b>{m.nome}</b>
            </div>
            {sug.porque && <p>{sug.porque}</p>}
            {sug.ligar.length > 0 && (
              <div className="flx-rec-l"><span>Ligar também</span>{sug.ligar.map((id) => <span key={id} className="flx-pill ok">{casos[id]?.titulo ?? id}</span>)}</div>
            )}
            {sug.desligar.length > 0 && (
              <div className="flx-rec-l"><span>Pode desligar</span>{sug.desligar.map((id) => <span key={id} className="flx-pill">{casos[id]?.titulo ?? id}</span>)}</div>
            )}
            <div className="flx-ia-acoes">
              <Button size="sm" variant="outline" onClick={() => onVer(m.id)}>Ver o modelo</Button>
              {(sug.porte !== perfil.porte || sug.atividade !== perfil.ativ) && (
                <Button size="sm" variant="ghost" onClick={() => onPerfil({ porte: sug.porte, ativ: sug.atividade })}>
                  Ajustar perfil para {rot(portes, sug.porte)} · {rot(atividades, sug.atividade)}
                </Button>
              )}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
