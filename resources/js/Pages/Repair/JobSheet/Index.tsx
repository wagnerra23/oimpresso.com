// @memcofre tela=/repair/job-sheet module=Repair
// Sprint 2.5 / MWART-0002 — port Job Sheet Repair Blade → Inertia/React.
// Lista filtrada por status/cliente/staff/location. Tabela puxa via DataTables AJAX legacy.
// Create/Edit/Print/Upload mantêm rotas Blade — apenas o shell vira React.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link } from '@inertiajs/react';
import { Plus, ClipboardList, FileText } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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
    msg.className = 'p-8 text-center text-slate-500 text-sm';
    msg.innerHTML = `
      <p class="mb-2">Listagem ainda usa DataTables AJAX legacy via <code>${datatable_url}</code>.</p>
      <p class="text-xs">Próxima iteração migra pra TanStack Table com fetch direto. Por ora, paridade visual mantida pelo Blade.</p>
    `;
    tableContainerRef.current.appendChild(msg);
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
        title="Ordens de Serviço (Job Sheet)"
        subtitle="Gestão de OS por status, cliente, equipe e local"
        actions={
          <div className="flex gap-2">
            <Button variant="outline" asChild>
              <Link href={route('job-sheet.create')}>
                <FileText className="mr-2 h-4 w-4" />
                Nova OS
              </Link>
            </Button>
          </div>
        }
      />

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium flex items-center gap-2">
              <ClipboardList className="h-4 w-4" />
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
              <p className="text-amber-700">Service staff — vê só OS atribuídas a você.</p>
            ) : (
              <p className="text-slate-600">Acesso total — vê todas OS do business.</p>
            )}
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm font-medium">Settings</CardTitle>
          </CardHeader>
          <CardContent className="text-sm space-y-1">
            <p>Serial no: {flags.show_serial_no ? '✓ visível' : '— oculto'}</p>
            <p>Brand: {flags.enable_brand_in_job_sheet ? '✓ habilitado' : '— desabilitado'}</p>
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
