// Planos — Editar plano. Charter: ./Edit.charter.md
// Refs: ADR 0104 MWART · 0093 multi-tenant Tier 0 · US-RB-001 · ADR UI-0029
//
// Desde 2026-11-23 "Editar" é um drawer sobre a lista, como no protótipo
// (`cobranca-recorrente-page.jsx`, ROTAS["rb-planos"].drawers.editar). Esta página continua
// existindo para a URL /recurring-billing/planos/{id}/editar não quebrar: ela é a lista com o
// drawer de edição já aberto. O plano vem do servidor, escopado ao business da sessão (404 fora).

import AppShellV2 from '@/Layouts/AppShellV2';
import type { ComponentProps, ReactNode } from 'react';
import PlanosIndex from './Index';
import type { PlanoFormData } from '../_components/PlanoFormDrawer';

type Props = ComponentProps<typeof PlanosIndex> & { plan: PlanoFormData };

export default function PlanosEdit(props: Props) {
  return <PlanosIndex {...props} drawerInicial={{ modo: 'editar', plan: props.plan }} />;
}

PlanosEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
