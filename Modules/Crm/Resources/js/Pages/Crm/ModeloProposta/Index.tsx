// @memcofre
//   tela: /crm/proposal-template
//   module: Crm
//   stories: Modelo de proposta (ProposalTemplateController@index · Blade → Inertia, Onda 1)
//   permissao: crm_module (assinatura); Editar/Adicionar exigem crm.add_proposal_template;
//              Exibir/Mandar exigem crm.access_proposal
//
// O modelo único de proposta da empresa. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// RUNBOOK: memory/requisitos/Crm/RUNBOOK-modelo-proposta.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Modelo()
//
// Atrás da flag useV2CrmModeloProposta (default ligado, decisão [F] 2026-11-27). Editar, Exibir,
// Mandar e Adicionar ainda abrem as telas Blade do mesmo controller (Ondas 2 e 3 do RUNBOOK);
// `?classico=1` devolve a Blade desta tela.

import AppShellV2 from '@/Layouts/AppShellV2';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import CrmCabecalho from '../_components/CrmCabecalho';

const EDITAR = '/crm/edit-proposal-template';
const EXIBIR = '/crm/view-proposal-template';
const MANDAR = '/crm/send-proposal';
const ADICIONAR = '/crm/proposal-template/create';

interface Modelo {
  assunto: string;
  cc: string | null;
  bcc: string | null;
  corpo: string;
  atualizado_em: string | null;
  anexos: { nome: string; url: string }[];
}
interface Props {
  modelo: Modelo | null;
  pode_editar: boolean;
  pode_mandar: boolean;
}

export default function ModeloPropostaIndex({ modelo, pode_editar, pode_mandar }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <CrmCabecalho aba="Modelo de proposta" />

      <Alert>
        <AlertTitle>Um modelo por empresa</AlertTitle>
        <AlertDescription>O módulo aceita um único modelo de proposta. É ele que alimenta o envio em “Propostas”.</AlertDescription>
      </Alert>

      <Card data-contract="crm-modelo">
        <CardContent className="flex flex-col gap-4 p-4">
          <div className="flex flex-wrap items-baseline gap-2">
            <h3 className="text-sm font-medium">Modelo de proposta</h3>
            {modelo?.atualizado_em ? <span className="text-xs text-muted-foreground">atualizado em {modelo.atualizado_em}</span> : null}
          </div>

          {modelo ? (
            <>
              <dl className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                <div>
                  <dt className="mb-1 text-xs font-semibold text-muted-foreground">Assunto</dt>
                  <dd>{modelo.assunto || '—'}</dd>
                </div>
                <div>
                  <dt className="mb-1 text-xs font-semibold text-muted-foreground">Anexos</dt>
                  <dd>
                    {modelo.anexos.length ? (
                      <ul className="flex flex-wrap gap-2">
                        {modelo.anexos.map((a) => (
                          <li key={a.url}>
                            <a className="rounded-sm border px-2 py-0.5 text-xs underline-offset-2 hover:underline" href={a.url} target="_blank" rel="noreferrer">{a.nome}</a>
                          </li>
                        ))}
                      </ul>
                    ) : <span className="text-muted-foreground">Sem anexos</span>}
                  </dd>
                </div>
                <div>
                  <dt className="mb-1 text-xs font-semibold text-muted-foreground">Cc</dt>
                  <dd>{modelo.cc || '—'}</dd>
                </div>
                <div>
                  <dt className="mb-1 text-xs font-semibold text-muted-foreground">Bcc</dt>
                  <dd>{modelo.bcc || '—'}</dd>
                </div>
              </dl>
              <section className="flex flex-col gap-1">
                <h4 className="text-xs font-semibold text-muted-foreground">Corpo da proposta</h4>
                <p className="whitespace-pre-line text-sm">{modelo.corpo || '—'}</p>
              </section>
            </>
          ) : (
            <p className="text-sm text-muted-foreground">Nenhum modelo de proposta cadastrado.</p>
          )}

          <div className="flex flex-wrap justify-end gap-2">
            {modelo && pode_editar ? <Button asChild variant="outline" size="sm"><a href={EDITAR}>Editar</a></Button> : null}
            {modelo && pode_mandar ? <Button asChild variant="outline" size="sm"><a href={EXIBIR}>Exibir</a></Button> : null}
            {modelo && pode_mandar ? <Button asChild size="sm"><a href={MANDAR}>Mandar</a></Button> : null}
            {!modelo && pode_editar ? <Button asChild size="sm"><a href={ADICIONAR}>Adicionar</a></Button> : null}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

ModeloPropostaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
