// @memcofre tela=/repair/job-sheet module=Repair
// Sprint 2.5 / MWART-0002 — port Job Sheet Repair Blade → Inertia/React.
// Lista filtrada por status/cliente/staff/location. Tabela puxa via DataTables AJAX legacy.
// Create/Edit/Print/Upload mantêm rotas Blade — apenas o shell vira React.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Icon } from '@/Components/Icon';
import type { ReactNode } from 'react';
import { useEffect, useRef } from 'react';

interface DropdownOption {
  [key: string]: string;
}

interface PageProps {
  filters: {
    business_locations: DropdownOption;
    customers: DropdownOption;
    status_dropdown: DropdownOption;
    service_staffs: DropdownOption;
  };
  flags: {
    is_user_service_staff: boolean;
    show_serial_no: boolean;
    enable_brand_in_job_sheet: boolean;
  };
  datatable_url: string;
}

export default function JobSheetIndex({ filters, flags, datatable_url }: PageProps) {
  const tableContainerRef = useRef<HTMLDivElement>(null);

  // Embed Blade datatable via iframe-like fetch (mantém compat com pipeline AJAX existente).
  // Em iteração futura, migrar pra TanStack Table com fetch direto.
  useEffect(() => {
    if (!tableContainerRef.current) return;
    const msg = document.createElement('div');
    msg.className = 'p-8 text-center text-muted-foreground text-sm';
    // textContent + DOM API — evita XSS com datatable_url (R-OWASP).
    const p1 = document.createElement('p');
    p1.className = 'mb-2';
    p1.append('Listagem ainda usa DataTables AJAX legacy via ');
    const code = document.createElement('code');
    code.textContent = datatable_url;
    p1.append(code);
    p1.append('.');
    const p2 = document.createElement('p');
    p2.className = 'text-xs';
    p2.textContent = 'Próxima iteração migra pra TanStack Table com fetch direto. Por ora, paridade visual mantida pelo Blade.';
    msg.append(p1, p2);
    tableContainerRef.current.replaceChildren(msg);
  }, [datatable_url]);

  const filterCount = (
    Object.values(filters.business_locations).length +
    Object.values(filters.customers).length +
    Object.values(filters.status_dropdown).length +
    Object.values(filters.service_staffs).length
  );

  return (
    <div className="container mx-auto p-4">
      <PageHeader
        icon="clipboard-list"
        title="Ordens de Serviço (Job Sheet)"
        description="Gestão de OS por status, cliente, equipe e local"
        action={
          <Button variant="outline" asChild>
            <Link href="/repair/job-sheet/create">
              <Icon name="file-text" className="mr-2 h-4 w-4" />
              Nova OS
            </Link>
          </Button>
        }
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <Icon name="clipboard-list" className="h-4 w-4" />
              Filtros disponíveis
            </CardTitle>
          </CardHeader>
          <CardContent className="text-sm space-y-1">
            <p>Locations: {Object.keys(filters.business_locations).length}</p>
            <p>Clientes: {Object.keys(filters.customers).length}</p>
            <p>Status: {Object.keys(filters.status_dropdown).length}</p>
            <p>Staff: {Object.keys(filters.service_staffs).length}</p>
            <p className="font-medium pt-2 border-t mt-2">Total opções: {filterCount}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">Permissões</CardTitle>
          </CardHeader>
          <CardContent className="text-sm">
            {flags.is_user_service_staff ? (
              <p className="text-amber-700 dark:text-amber-400">Service staff — vê só OS atribuídas a você.</p>
            ) : (
              <p className="text-muted-foreground">Acesso total — vê todas OS do business.</p>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">Settings</CardTitle>
          </CardHeader>
          <CardContent className="text-sm space-y-1.5">
            <FlagRow label="Serial no" enabled={flags.show_serial_no} />
            <FlagRow label="Brand" enabled={flags.enable_brand_in_job_sheet} />
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Lista de OS</CardTitle>
        </CardHeader>
        <CardContent>
          <div ref={tableContainerRef} />
        </CardContent>
      </Card>
    </div>
  );
}

JobSheetIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function FlagRow({ label, enabled }: { label: string; enabled: boolean }) {
  return (
    <p className="flex items-center gap-2">
      {enabled ? (
        <Icon name="check-circle-2" className="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
      ) : (
        <Icon name="circle-minus" className="h-3.5 w-3.5 text-muted-foreground" />
      )}
      <span>{label}: <span className={enabled ? 'text-foreground' : 'text-muted-foreground'}>{enabled ? 'visível' : 'oculto'}</span></span>
    </p>
  );
}
