// @memcofre
//   tela: /showcase/onda-f
//   module: _DesignSystem
//   status: showcase
//   stories: Segmented · FormSection/FormGrid · InputGroup · FieldState
//
// Stories da Onda F (DS v4.1) — prova os 4 componentes fora da tela real e
// alimenta a regressão visual. Renderiza cada um em densidade default e compact.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useState, type ReactNode } from 'react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Segmented } from '@/Components/ui/segmented';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { InputGroup, InputGroupButton, InputGroupAddon } from '@/Components/ui/input-group';
import { FieldError, FieldSuccess, FieldValidating, RequiredMark } from '@/Components/ui/field-state';
import { Building2 } from 'lucide-react';

function Story({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-3">
      <h2 className="text-sm font-semibold text-foreground">{title}</h2>
      <div className="flex flex-wrap items-start gap-6">{children}</div>
    </section>
  );
}

export default function OndaFShowcase() {
  const [tipo, setTipo] = useState('customer');
  const [pessoa, setPessoa] = useState('person');

  return (
    <div className="container mx-auto max-w-4xl px-8 py-8 space-y-10">
      <header>
        <h1 className="text-2xl font-semibold tracking-tight">Onda F — vocabulário de formulário</h1>
        <p className="text-sm text-muted-foreground mt-1">
          DS v4.1 · Segmented · FormSection · InputGroup · FieldState. Visual canon{' '}
          <code className="text-xs">cowork-fields.css</code>.
        </p>
      </header>

      <Story title="Segmented">
        <Segmented
          value={tipo}
          onValueChange={setTipo}
          options={[
            { value: 'customer', label: 'Cliente' },
            { value: 'supplier', label: 'Fornecedor' },
            { value: 'both', label: 'Ambos' },
          ]}
        />
        <Segmented
          accent
          value={pessoa}
          onValueChange={setPessoa}
          options={[
            { value: 'person', label: 'Física' },
            { value: 'business', label: 'Jurídica' },
          ]}
        />
      </Story>

      <Story title="InputGroup">
        <div className="grid gap-3 w-full max-w-md">
          <InputGroup>
            <Input variant="cowork" defaultValue="12.345.678/0001-90" />
            <InputGroupButton done>Encontrado</InputGroupButton>
          </InputGroup>
          <InputGroup>
            <Input variant="cowork" placeholder="74000-000" />
            <InputGroupButton loading>Buscando</InputGroupButton>
          </InputGroup>
          <InputGroup>
            <InputGroupAddon>R$</InputGroupAddon>
            <Input variant="cowork" placeholder="0,00" />
          </InputGroup>
        </div>
      </Story>

      <Story title="FieldState">
        <div className="grid gap-3 w-full max-w-md">
          <div className="cw-field">
            <Label className="cw-label">
              CNPJ <RequiredMark />
            </Label>
            <Input variant="cowork" defaultValue="12.345.678/0001-90" />
            <FieldSuccess>Dados preenchidos pela BrasilAPI</FieldSuccess>
          </div>
          <div className="cw-field">
            <Label className="cw-label">CEP</Label>
            <Input variant="cowork" defaultValue="74000-000" />
            <FieldValidating>Consultando endereço…</FieldValidating>
          </div>
          <div className="cw-field has-error">
            <Label className="cw-label">E-mail</Label>
            <Input variant="cowork" aria-invalid defaultValue="invalido@" />
            <FieldError>Informe um e-mail válido.</FieldError>
          </div>
        </div>
      </Story>

      <Story title="FormSection + FormGrid">
        <FormSection title="Identificação" icon={<Building2 />} count="3 de 4 ✓" className="w-full max-w-lg !mb-0">
          <FormGrid>
            <div className="cw-field full-row">
              <Label className="cw-label">Razão social <RequiredMark /></Label>
              <Input variant="cowork" defaultValue="Acme Comércio Ltda" />
            </div>
            <div className="cw-field">
              <Label className="cw-label">Nome fantasia</Label>
              <Input variant="cowork" defaultValue="Acme Materiais" />
            </div>
            <div className="cw-field">
              <Label className="cw-label">Regime</Label>
              <Input variant="cowork" defaultValue="Simples Nacional" />
            </div>
          </FormGrid>
        </FormSection>
      </Story>
    </div>
  );
}

OndaFShowcase.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
