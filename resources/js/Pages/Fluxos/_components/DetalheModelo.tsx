// Gaveta do modelo + confirmação de "Usar este modelo".
// Fonte visual: `ModeloDetalhe`, o <Drawer> do modelo e o <Modal> de confirmação em
// prototipo-ui/cowork/Wagner/fluxos-page.jsx. A ação grava pelo backend (POST /fluxos/modelo →
// AplicarModelo, #9492); documentos em andamento não mudam de etapa (ADR 0435).

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
  Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle,
} from '@/Components/ui/sheet';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Cadeia, TemaDoCockpit } from './Cadeia';
import type { Encaixe, MapaCasos, Modelo, Opcao, Perfil } from './tipos';

interface Props {
  modelo: Modelo | null;
  emUso: Modelo | null;
  encaixe: Encaixe | null;
  perfil: Perfil;
  portes: Opcao[];
  atividades: Opcao[];
  casos: MapaCasos;
  podeConfigurar: boolean;
  onFechar: () => void;
}

const rot = (l: Opcao[], v: string) => l.find((o) => o.value === v)?.label ?? v;

/** O que muda trocando `de` por `para` (domínios e casos de uso), como no protótipo. */
function diferenca(de: Modelo | null, para: Modelo) {
  if (!de || de.id === para.id) return null;
  const pd = de.processos.map((p) => p.nome);
  const pp = para.processos.map((p) => p.nome);
  return {
    entram: pp.filter((x) => !pd.includes(x)),
    saem: pd.filter((x) => !pp.includes(x)),
    casosMais: para.casos.filter((c) => !de.casos.includes(c)).length,
    casosMenos: de.casos.filter((c) => !para.casos.includes(c)).length,
  };
}

export default function DetalheModelo({
  modelo, emUso, encaixe, perfil, portes, atividades, casos, podeConfigurar, onFechar,
}: Props) {
  const [confirmar, setConfirmar] = useState(false);
  const [enviando, setEnviando] = useState(false);
  const jaEmUso = !!modelo && emUso?.id === modelo.id;
  const dif = modelo ? diferenca(emUso, modelo) : null;

  const usar = () => {
    if (!modelo) return;
    setEnviando(true);
    router.post('/fluxos/modelo', { modelo: modelo.id, porte: perfil.porte, atividade: perfil.ativ }, {
      onFinish: () => { setEnviando(false); setConfirmar(false); onFechar(); },
    });
  };

  return (
    <>
      <Sheet open={modelo !== null} onOpenChange={(a) => !a && onFechar()}>
        <SheetContent className="w-full sm:max-w-[620px] overflow-y-auto">
          <TemaDoCockpit>
          {modelo && (
            <>
              <SheetHeader>
                <SheetTitle>{modelo.nome}</SheetTitle>
                <SheetDescription>
                  {encaixe?.rotulo ?? ''} para {rot(portes, perfil.porte).toLowerCase()} · {rot(atividades, perfil.ativ).toLowerCase()}
                </SheetDescription>
              </SheetHeader>
              <div className="flx-det">
                <p className="flx-det-para">{modelo.para}</p>
                <div className="flx-det-meta">
                  <span>Porte: {modelo.portes.map((p) => rot(portes, p)).join(', ')}</span>
                  <span>Atividades: {modelo.atividades.map((a) => rot(atividades, a)).join(', ')}</span>
                </div>
                {dif && emUso && (
                  <section className="flx-dif" aria-label={`Diferença para ${emUso.nome}`}>
                    <h4>Comparado ao modelo em uso ({emUso.nome})</h4>
                    <ul>
                      {dif.entram.length > 0 && <li><b>Passa a ter:</b> {dif.entram.join(', ')}</li>}
                      {dif.saem.length > 0 && <li><b>Deixa de ter:</b> {dif.saem.join(', ')}</li>}
                      <li><b>Casos de uso:</b> +{dif.casosMais} · −{dif.casosMenos}</li>
                    </ul>
                  </section>
                )}
                {modelo.processos.map((p) => (
                  <section key={p.dominio} className="flx-proc">
                    <h4>{p.nome}</h4>
                    <Cadeia etapas={p.etapas} marcadas={new Set(p.regras.map((r) => r.etapa))} />
                    <ul className="flx-regras">
                      {p.regras.map((r, i) => (
                        <li key={i}>
                          <span className="em">Ao entrar em <b>{r.etapa}</b></span>
                          <span>{r.efeito}</span>
                          {r.uc ? <code>{r.uc}</code> : <span />}
                        </li>
                      ))}
                    </ul>
                  </section>
                ))}
                <section className="flx-proc">
                  <h4>Casos de uso cobertos <span className="flx-dim">{modelo.casos.length}</span></h4>
                  <ul className="flx-casos">
                    {modelo.casos.map((id) => (
                      <li key={id}><code>{id}</code><span>{casos[id]?.titulo ?? id}</span></li>
                    ))}
                  </ul>
                </section>
              </div>
              <SheetFooter className="flex-row justify-end gap-2">
                <Button variant="ghost" onClick={onFechar}>Fechar</Button>
                {podeConfigurar && (
                  <Button disabled={jaEmUso} onClick={() => setConfirmar(true)}>
                    {jaEmUso ? 'Modelo em uso' : 'Usar este modelo'}
                  </Button>
                )}
              </SheetFooter>
            </>
          )}
          </TemaDoCockpit>
        </SheetContent>
      </Sheet>

      <AlertDialog open={confirmar && modelo !== null} onOpenChange={setConfirmar}>
        <AlertDialogContent>
          <TemaDoCockpit>
          <AlertDialogHeader>
            <AlertDialogTitle>Usar {modelo?.nome}?</AlertDialogTitle>
            <AlertDialogDescription asChild>
              <div className="flx-conf">
                <p>As etapas e regras de {modelo?.processos.map((p) => p.nome).join(', ')} passam a seguir este modelo.</p>
                <p>Documentos em andamento continuam na etapa em que estão.</p>
                {emUso && dif && (
                  <p className="flx-dim">
                    Substitui {emUso.nome} · casos de uso +{dif.casosMais} / −{dif.casosMenos}
                    {dif.saem.length > 0 ? ` · deixa de ter ${dif.saem.join(', ')}` : ''}.
                  </p>
                )}
              </div>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={enviando}>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={enviando} onClick={(e) => { e.preventDefault(); usar(); }}>
              {enviando ? 'Aplicando…' : 'Usar modelo'}
            </AlertDialogAction>
          </AlertDialogFooter>
          </TemaDoCockpit>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
