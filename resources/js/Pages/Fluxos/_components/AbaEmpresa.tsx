// Aba Fluxos da empresa — modelo em uso e as etapas/regras de cada processo dele.
// Fonte visual: aba "empresa" de prototipo-ui/cowork/Wagner/fluxos-page.jsx.
// Só leitura aqui: trocar o modelo é pela aba Modelos (gaveta → "Usar este modelo"). Ligar e
// desligar regra e a Compra editável do protótipo dependem de regras configuráveis que o ERP
// ainda não tem (charter §Lacunas).

import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import EmptyState from '@/Components/shared/EmptyState';
import { Cadeia } from './Cadeia';
import type { EmpresaModelo, Modelo, Opcao } from './tipos';

interface Props {
  empresa: EmpresaModelo | null;
  emUso: Modelo | null;
  portes: Opcao[];
  atividades: Opcao[];
}

const rot = (l: Opcao[], v: string | null) => (v ? l.find((o) => o.value === v)?.label ?? v : null);

export default function AbaEmpresa({ empresa, emUso, portes, atividades }: Props) {
  const verModelos = (
    <Button size="sm" asChild><Link href="/fluxos?aba=modelos">Ver modelos</Link></Button>
  );

  if (!emUso) {
    const legado = empresa?.modelo === 'legado';
    return (
      <EmptyState
        title={legado ? 'A empresa usa os processos de antes dos modelos' : 'Nenhum modelo escolhido'}
        description={legado
          ? 'As vendas seguem o processo atual do sistema. Escolha um modelo pelo porte e pela atividade para passar a usar as etapas e regras dele.'
          : 'Escolha um modelo pelo porte e pela atividade da empresa. As etapas e regras de cada processo vêm dele.'}
        action={verModelos}
      />
    );
  }

  const quando = empresa?.aplicado_em ? new Date(empresa.aplicado_em).toLocaleDateString('pt-BR') : null;
  const perfil = [rot(portes, empresa?.porte ?? null), rot(atividades, empresa?.atividade ?? null)]
    .filter(Boolean).join(' · ').toLowerCase();

  return (
    <>
      <div className="flx-uso">
        <div>
          <span className="flx-lbl">Modelo em uso</span>
          <b>{emUso.nome}</b>
          {(quando || perfil) && (
            <span className="flx-dim">
              {quando ? `Escolhido em ${quando}` : 'Escolhido'}{perfil ? ` para ${perfil}` : ''}
            </span>
          )}
        </div>
        <Button size="sm" variant="outline" asChild><Link href="/fluxos?aba=modelos">Trocar modelo</Link></Button>
      </div>
      <div className="flx-procs">
        {emUso.processos.map((p) => (
          <section key={p.dominio} className="flx-card" aria-label={p.nome}>
            <div className="flx-card-h">
              <b>{p.nome}</b>
              <span className="flx-dim">
                {p.etapas.length} etapas · {p.regras.length} {p.regras.length === 1 ? 'regra' : 'regras'}
              </span>
            </div>
            <Cadeia etapas={p.etapas} marcadas={new Set(p.regras.map((r) => r.etapa))} />
            {p.regras.length > 0 && (
              <ul className="flx-regras">
                {p.regras.map((r, i) => (
                  <li key={i}>
                    <span className="em">Ao entrar em <b>{r.etapa}</b></span>
                    <span>{r.efeito}</span>
                    {r.uc ? <code>{r.uc}</code> : <span />}
                  </li>
                ))}
              </ul>
            )}
          </section>
        ))}
      </div>
    </>
  );
}
