// @memcofre tela=/repair/repair-settings module=Repair
// Onda 1 do export Repair (2026-09-04) — configurações da folha de OS + etiqueta.
//
// DOIS formulários porque são DOIS endpoints de escrita, com colunas disjuntas:
//   store()                  -> business.repair_settings
//   updateJobsheetSettings() -> business.repair_jobsheet_settings
// Mandar o segundo conjunto pro primeiro endpoint salva sem erro e NÃO persiste
// (tela inerte, classe LC-30). Ver RUNBOOK-repair-settings.md §2.
//
// Os dois métodos substituem o JSON INTEIRO (`$request->only` + `json_encode`),
// então cada form envia SEU CONJUNTO COMPLETO a cada submit. Campo que a UI não
// mandar é campo que o salvamento apaga — travado por UC-RSET-03.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, useForm } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import type { ReactNode } from 'react';

type Dicionario = Record<string, string | number | null>;

interface RepairSettings {
  job_sheet_prefix?: string | null;
  default_status?: string | number | null;
  barcode_id?: string | number | null;
  barcode_type?: string | null;
  repair_tc_condition?: string | null;
  problem_reported_by_customer?: string | null;
  product_condition?: string | null;
  product_configuration?: string | null;
  default_repair_checklist?: string | null;
  job_sheet_custom_field_1?: string | null;
  job_sheet_custom_field_2?: string | null;
  job_sheet_custom_field_3?: string | null;
  job_sheet_custom_field_4?: string | null;
  job_sheet_custom_field_5?: string | null;
}

interface JobsheetPdfSettings {
  customer_label?: string | null;
  client_id_label?: string | null;
  client_tax_label?: string | null;
  label_width?: string | number | null;
  label_height?: string | number | null;
  [chave: string]: unknown;
}

interface PageProps {
  barcodeSettings: Dicionario;
  repairSettings: RepairSettings;
  defaultProductName: string;
  barcodeTypes: Dicionario;
  repairStatuses: Dicionario;
  jobsheetPdfSettings: JobsheetPdfSettings;
  contactCustomFields: string[];
  customLabels: Record<string, Record<string, string>>;
}

/**
 * As 17 chaves `show_*` que `updateJobsheetSettings()` conhece, agrupadas como
 * no Blade legado. A 18ª entrada do array do controller é `contact_custom_fields`,
 * que é lista, não booleano — por isso vive à parte.
 */
const GRUPOS_IMPRESSAO: { titulo: string; chaves: [string, string][] }[] = [
  {
    titulo: 'Dados do cliente',
    chaves: [
      ['show_customer', 'Mostrar cliente'],
      ['show_client_id', 'Mostrar código do cliente'],
    ],
  },
  {
    titulo: 'Cliente na etiqueta',
    chaves: [
      ['show_customer_name_in_label', 'Nome do cliente'],
      ['show_customer_address_in_label', 'Endereço do cliente'],
      ['show_customer_phone_in_label', 'Telefone do cliente'],
      ['show_customer_alt_phone_in_label', 'Telefone alternativo'],
      ['show_customer_email_in_label', 'E-mail do cliente'],
    ],
  },
  {
    titulo: 'Detalhes da etiqueta',
    chaves: [
      ['show_sales_person_in_label', 'Vendedor'],
      ['show_barcode_in_label', 'Código de barras'],
      ['show_status_in_label', 'Status'],
      ['show_due_date_in_label', 'Prazo de entrega'],
    ],
  },
  {
    titulo: 'Atendimento',
    chaves: [
      ['show_technician_in_label', 'Técnico'],
      ['show_problem_in_label', 'Problema relatado'],
    ],
  },
  {
    titulo: 'Dispositivo',
    chaves: [
      ['show_sr_no_in_label', 'IMEI / número de série'],
      ['show_brand_in_label', 'Marca e modelo'],
      ['show_location_in_label', 'Local'],
      ['show_password_in_label', 'Senha do aparelho'],
    ],
  },
];

const CAMPOS_PERSONALIZADOS = [1, 2, 3, 4, 5] as const;

function texto(valor: unknown): string {
  return valor === null || valor === undefined ? '' : String(valor);
}

function ligado(valor: unknown): boolean {
  return valor === 1 || valor === '1' || valor === true;
}

function Secao({ titulo, descricao, children }: { titulo: string; descricao: string; children: ReactNode }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-[13px] font-semibold">{titulo}</CardTitle>
        <p className="text-sm text-muted-foreground">{descricao}</p>
      </CardHeader>
      <CardContent className="space-y-4">{children}</CardContent>
    </Card>
  );
}

export default function RepairSettingsIndex({
  barcodeSettings,
  repairSettings,
  defaultProductName,
  barcodeTypes,
  repairStatuses,
  jobsheetPdfSettings,
  contactCustomFields,
}: PageProps) {
  // ── Form 1 — conjunto COMPLETO de business.repair_settings ────────────────
  const folha = useForm({
    job_sheet_prefix: texto(repairSettings.job_sheet_prefix),
    default_status: texto(repairSettings.default_status),
    barcode_id: texto(repairSettings.barcode_id),
    barcode_type: texto(repairSettings.barcode_type),
    repair_tc_condition: texto(repairSettings.repair_tc_condition),
    problem_reported_by_customer: texto(repairSettings.problem_reported_by_customer),
    product_condition: texto(repairSettings.product_condition),
    product_configuration: texto(repairSettings.product_configuration),
    default_repair_checklist: texto(repairSettings.default_repair_checklist),
    // O Blade legado renderizava os campos 2 e 4 em branco quando o campo 1
    // estava vazio (guard lendo a chave errada) e o submit seguinte os apagava.
    // Aqui cada campo lê a PRÓPRIA chave — o defeito não sobrevive à migração.
    job_sheet_custom_field_1: texto(repairSettings.job_sheet_custom_field_1),
    job_sheet_custom_field_2: texto(repairSettings.job_sheet_custom_field_2),
    job_sheet_custom_field_3: texto(repairSettings.job_sheet_custom_field_3),
    job_sheet_custom_field_4: texto(repairSettings.job_sheet_custom_field_4),
    job_sheet_custom_field_5: texto(repairSettings.job_sheet_custom_field_5),
  });

  // ── Form 2 — conjunto COMPLETO de business.repair_jobsheet_settings ───────
  const impressaoInicial: Record<string, string | number | string[]> = {
    customer_label: texto(jobsheetPdfSettings.customer_label),
    client_id_label: texto(jobsheetPdfSettings.client_id_label),
    client_tax_label: texto(jobsheetPdfSettings.client_tax_label),
    label_width: texto(jobsheetPdfSettings.label_width) || '75',
    label_height: texto(jobsheetPdfSettings.label_height) || '50',
    contact_custom_fields: contactCustomFields ?? [],
  };
  for (const grupo of GRUPOS_IMPRESSAO) {
    for (const [chave] of grupo.chaves) {
      impressaoInicial[chave] = ligado(jobsheetPdfSettings[chave]) ? 1 : 0;
    }
  }
  const impressao = useForm(impressaoInicial);

  return (
    <div className="container mx-auto space-y-6 p-4">
      <PageHeader
        icon="settings"
        title="Configurações do Repair"
        description="Padrões da folha de OS e o que sai impresso na etiqueta"
      />

      {/* ── 1 · Folha de OS ─────────────────────────────────────────────── */}
      <form
        onSubmit={(e) => {
          e.preventDefault();
          folha.post('/repair/repair-settings', { preserveScroll: true });
        }}
        className="space-y-6"
      >
        <Secao titulo="Folha de OS" descricao="Valores que toda nova folha assume por padrão.">
          <div className="grid gap-4 md:grid-cols-3">
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="job_sheet_prefix">Prefixo da folha</Label>
              <Input
                id="job_sheet_prefix"
                value={folha.data.job_sheet_prefix}
                onChange={(e) => folha.setData('job_sheet_prefix', e.target.value)}
              />
            </div>

            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="default_status">Status padrão</Label>
              <Select
                value={folha.data.default_status || undefined}
                onValueChange={(v) => folha.setData('default_status', v)}
              >
                <SelectTrigger id="default_status" aria-label="Status padrão da folha">
                  <SelectValue placeholder="Sem status padrão" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(repairStatuses).map(([id, nome]) => (
                    <SafeSelectItem key={id} value={String(id)}>
                      {String(nome)}
                    </SafeSelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="min-w-0 space-y-1.5">
              <Label>Produto padrão</Label>
              <Input value={defaultProductName} readOnly aria-label="Produto padrão da folha" />
            </div>

            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="barcode_id">Etiqueta de código de barras</Label>
              <Select
                value={folha.data.barcode_id || undefined}
                onValueChange={(v) => folha.setData('barcode_id', v)}
              >
                <SelectTrigger id="barcode_id" aria-label="Configuração de código de barras">
                  <SelectValue placeholder="Selecione" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(barcodeSettings).map(([id, nome]) => (
                    <SafeSelectItem key={id} value={String(id)}>
                      {String(nome)}
                    </SafeSelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="barcode_type">Tipo de código de barras</Label>
              <Select
                value={folha.data.barcode_type || undefined}
                onValueChange={(v) => folha.setData('barcode_type', v)}
              >
                <SelectTrigger id="barcode_type" aria-label="Tipo de código de barras">
                  <SelectValue placeholder="Selecione" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(barcodeTypes).map(([id, nome]) => (
                    <SafeSelectItem key={id} value={String(id)}>
                      {String(nome)}
                    </SafeSelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-2">
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="problem_reported_by_customer">Problema relatado pelo cliente</Label>
              <Textarea
                id="problem_reported_by_customer"
                rows={4}
                value={folha.data.problem_reported_by_customer}
                onChange={(e) => folha.setData('problem_reported_by_customer', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="product_condition">Condição do produto</Label>
              <Textarea
                id="product_condition"
                rows={4}
                value={folha.data.product_condition}
                onChange={(e) => folha.setData('product_condition', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="product_configuration">Configuração do produto</Label>
              <Textarea
                id="product_configuration"
                rows={4}
                value={folha.data.product_configuration}
                onChange={(e) => folha.setData('product_configuration', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="repair_tc_condition">Termos e condições</Label>
              <Textarea
                id="repair_tc_condition"
                rows={4}
                value={folha.data.repair_tc_condition}
                onChange={(e) => folha.setData('repair_tc_condition', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5 md:col-span-2">
              <Label htmlFor="default_repair_checklist">Checklist padrão do reparo</Label>
              <Textarea
                id="default_repair_checklist"
                rows={3}
                value={folha.data.default_repair_checklist}
                onChange={(e) => folha.setData('default_repair_checklist', e.target.value)}
              />
            </div>
          </div>
        </Secao>

        {/* ── 2 · Campos personalizados ─────────────────────────────────── */}
        <Secao
          titulo="Campos personalizados da folha"
          descricao="Sem rótulo, a coluna não aparece na listagem de folhas."
        >
          <div className="grid gap-4 md:grid-cols-3">
            {CAMPOS_PERSONALIZADOS.map((n) => {
              const chave = `job_sheet_custom_field_${n}` as keyof typeof folha.data;
              return (
                <div key={n} className="min-w-0 space-y-1.5">
                  <Label htmlFor={String(chave)}>{`Campo personalizado ${n}`}</Label>
                  <Input
                    id={String(chave)}
                    placeholder="sem rótulo — coluna oculta"
                    value={String(folha.data[chave] ?? '')}
                    onChange={(e) => folha.setData(chave, e.target.value)}
                  />
                </div>
              );
            })}
          </div>
        </Secao>

        <div className="flex flex-wrap items-center gap-3">
          <Button type="submit" disabled={folha.processing}>
            {folha.processing ? 'Salvando…' : 'Salvar configurações'}
          </Button>
          <span className="text-sm text-muted-foreground">
            Permissão: repair_module (assinatura) + admin do negócio
          </span>
        </div>
      </form>

      {/* ── 3 · O que aparece na impressão (OUTRO endpoint) ──────────────── */}
      <form
        onSubmit={(e) => {
          e.preventDefault();
          impressao.post('/repair/update-repair-jobsheet-settings', { preserveScroll: true });
        }}
        className="space-y-6"
      >
        <Secao
          titulo="O que aparece na impressão"
          descricao="Rótulos e campos da folha de OS e da etiqueta. Gravado separadamente das configurações acima."
        >
          <div className="grid gap-4 md:grid-cols-3">
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="customer_label">Rótulo do cliente</Label>
              <Input
                id="customer_label"
                value={String(impressao.data.customer_label ?? '')}
                onChange={(e) => impressao.setData('customer_label', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="client_id_label">Rótulo do código do cliente</Label>
              <Input
                id="client_id_label"
                value={String(impressao.data.client_id_label ?? '')}
                onChange={(e) => impressao.setData('client_id_label', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="client_tax_label">Rótulo do documento fiscal</Label>
              <Input
                id="client_tax_label"
                value={String(impressao.data.client_tax_label ?? '')}
                onChange={(e) => impressao.setData('client_tax_label', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="label_width">Largura da etiqueta (mm)</Label>
              <Input
                id="label_width"
                inputMode="numeric"
                value={String(impressao.data.label_width ?? '')}
                onChange={(e) => impressao.setData('label_width', e.target.value)}
              />
            </div>
            <div className="min-w-0 space-y-1.5">
              <Label htmlFor="label_height">Altura da etiqueta (mm)</Label>
              <Input
                id="label_height"
                inputMode="numeric"
                value={String(impressao.data.label_height ?? '')}
                onChange={(e) => impressao.setData('label_height', e.target.value)}
              />
            </div>
          </div>

          <div className="space-y-5">
            {GRUPOS_IMPRESSAO.map((grupo) => (
              <div key={grupo.titulo} className="space-y-3">
                <h4 className="text-[13px] font-semibold">{grupo.titulo}</h4>
                <div className="grid gap-3 md:grid-cols-2">
                  {grupo.chaves.map(([chave, rotulo]) => (
                    <div key={chave} className="flex min-w-0 items-center gap-3">
                      <Switch
                        id={chave}
                        checked={ligado(impressao.data[chave])}
                        onCheckedChange={(v) => impressao.setData(chave, v ? 1 : 0)}
                      />
                      <Label htmlFor={chave} className="min-w-0 break-words font-normal">
                        {rotulo}
                        <span className="block text-xs text-muted-foreground">{`chave ${chave}`}</span>
                      </Label>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Secao>

        <div className="flex flex-wrap items-center gap-3">
          <Button type="submit" disabled={impressao.processing}>
            {impressao.processing ? 'Salvando…' : 'Salvar impressão'}
          </Button>
        </div>
      </form>

      {/* ── 4 · O que mora em tela própria ───────────────────────────────── */}
      <Secao
        titulo="Configurações em tela própria"
        descricao="Estes cadastros já têm tela dedicada — esta página não os duplica."
      >
        <div className="flex flex-wrap gap-3">
          <Button variant="outline" asChild>
            <Link href="/repair/status">Status de OS</Link>
          </Button>
          <Button variant="outline" asChild>
            <Link href="/repair/device-models">Modelos de dispositivo</Link>
          </Button>
        </div>
      </Secao>
    </div>
  );
}

RepairSettingsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
