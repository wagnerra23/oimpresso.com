// Planos — Novo plano. Charter: ./Create.charter.md
// Refs: ADR 0104 MWART · 0093 multi-tenant Tier 0 · US-RB-001 · ADR UI-0029
//
// Desde 2026-11-23 "Novo plano" é um drawer sobre a lista, como no protótipo
// (`cobranca-recorrente-page.jsx`, ROTAS["rb-planos"].drawers.novo). Esta página continua
// existindo para a URL /recurring-billing/planos/novo não quebrar: ela é a lista com o drawer
// já aberto. O formulário mora em ../_components/PlanoFormDrawer.tsx.

import AppShellV2 from '@/Layouts/AppShellV2';
import type { ComponentProps, ReactNode } from 'react';
import PlanosIndex from './Index';
import type { PlanoDefaults } from '../_components/PlanoFormDrawer';

type Props = ComponentProps<typeof PlanosIndex> & { defaults: PlanoDefaults };

export default function PlanosCreate(props: Props) {
  return <PlanosIndex {...props} drawerInicial={{ modo: 'novo', defaults: props.defaults }} />;
}

PlanosCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
