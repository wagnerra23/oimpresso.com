// US-CRM-CONT-003 — Editar contato Inertia/React.
// Reusa Create.tsx com mode='edit' + initial values pré-populados.
// Refs: ADR 0104, RUNBOOK-contacts.md.

import type { ReactNode } from 'react';
import ContactsCreate, { type ContactsCreatePageProps } from './Create';
import AppShellV2 from '@/Layouts/AppShellV2';

export interface ContactsEditPageProps extends Omit<ContactsCreatePageProps, 'mode' | 'prefillName'> {
  // Backend garante contact preenchido em edit.
  contact: NonNullable<ContactsCreatePageProps['contact']>;
}

export default function ContactsEdit(props: ContactsEditPageProps) {
  return (
    <ContactsCreate
      {...props}
      mode="edit"
      prefillName=""
    />
  );
}

ContactsEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
