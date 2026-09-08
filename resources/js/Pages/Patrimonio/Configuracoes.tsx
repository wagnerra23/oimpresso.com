// @patrimonio
//   tela: /asset/settings  (rota `asset.settings.index` — a URL NÃO mudou na migração)
//   modulo: Modules/AssetManagement  (NÃO existe Modules/Patrimonio — ADR 0394)
//   adrs: 0394 (endereço de UI do Patrimônio), 0104 (MWART), 0093 (multi-tenant Tier 0),
//         0180 (sidebar v3 · ghosts), 0253 (primitivos de layout)
//   runbook: memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md
//   charter: ./Configuracoes.charter.md · casos: ./Configuracoes.casos.md
//   fonte visual: prototipo-ui/cowork/patrimonio-page.jsx (aba `config`, :589) — ALVO, não
//                 decisão de produto (`06-ui-bloqueada.md`)
//
// Sexta tela da frente, e a menor. Herda o `_shared/` fundado por Bens (PR #7035).
//
// ─── A armadilha desta migração, e por que o `transform` abaixo existe ───────────────
//
// O `store()` decide os interruptores por `$request->has(...)`, não `boolean(...)`
// (`AssetSettingsController.php:117-123`). Checkbox HTML desmarcado NÃO envia a chave, e é
// assim que ela some do JSON. Um cliente Inertia que mandasse `enable_...: false` faria
// `has()` devolver TRUE e gravaria 1 — e DESLIGAR DEIXARIA DE FUNCIONAR, em silêncio, sem
// erro em lugar nenhum. Por isso o payload OMITE a chave quando desmarcada, em vez de mandar
// `false`: preserva o contrato do Blade sem tocar no controller. É o UC-CFG-04.
//
// ─── O que a tela recusa, e por quê (detalhe no §5 do RUNBOOK e nos Non-Goals) ───────
//
//   • sem WYSIWYG no corpo do e-mail — o Blade usa TinyMCE; editor rich-text no React é
//     dependência nova, que exige ADR. Aqui o corpo é textarea monoespaçada, e o rótulo diz
//     que o conteúdo é HTML;
//   • sem os 3 interruptores do protótipo ("garantia expirando", "alocação registrada") —
//     não têm backend: nenhuma coluna, nenhum job, nenhuma Notification;
//   • sem a linha "Retenção: 5 anos" do rodapé — aponta `Config/retention.php`, da thread 05
//     BARRADA, cujas tabelas não existem.
//
// Nada disso é regressão vs. o Blade: os 4 prefixos, os destinatários, os 2 interruptores e
// os 4 campos de template estão todos aqui. O protótipo desenha 3 prefixos; o backend grava 4.
//
// Layout por PRIMITIVOS (ADR 0253) — `Stack`/`Inline`, nunca `<div className="flex gap-4">`
// solto; o `layout-primitives-guard` é catraca e reprova adotante novo.

import { Deferred, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import { Stack, Inline, Grid, Box } from '@/Components/layout';
import PatrimonioSubNav from './_shared/PatrimonioSubNav';

/* ─── Contrato com o backend ──────────────────────────────────────────────────── */

interface Modelo {
  subject: string;
  email_body: string;
}

interface Usuario {
  id: string;
  nome: string;
}

interface Settings {
  asset_code_prefix: string;
  allocation_code_prefix: string;
  revoke_code_prefix: string;
  asset_maintenance_prefix: string;
  /** ⚠️ O typo `maintenence` é CONTRATO GRAVADO na coluna `business.asset_settings` — não se
   *  conserta aqui (RUNBOOK §11). Lista de ids de `users`, como string. */
  send_for_maintenence_recipients: string[];
  enable_asset_send_for_maintenance_email: boolean;
  enable_asset_assigned_for_maintenance_email: boolean;
}

interface Props {
  settings: Settings;
  templates: {
    send_for_maintenance: Modelo;
    assigned_for_maintenance: Modelo;
  };
  /** DEFERIDA (Inertia::defer) — nao vem no primeiro render. */
  usuarios?: Usuario[];
  /** Contrato de `AssetUtil::replaceEmailTags()`. NÃO coincide entre os dois blocos — por
   *  isso vem do backend, em vez de virar lista mantida à mão aqui. */
  tags: {
    send_for_maintenance: string[];
    assigned_for_maintenance: string[];
  };
}

interface Formulario extends Settings {
  send_for_maintenance: Modelo;
  assigned_for_maintenance: Modelo;
}

/* ─── Peças ───────────────────────────────────────────────────────────────────── */

function CampoPrefixo({
  id,
  rotulo,
  ajuda,
  valor,
  onChange,
}: {
  id: string;
  rotulo: string;
  ajuda: string;
  valor: string;
  onChange: (v: string) => void;
}) {
  return (
    <Stack gap={1}>
      <Label htmlFor={id}>{rotulo}</Label>
      <Input
        id={id}
        value={valor}
        onChange={(e) => onChange(e.target.value)}
        placeholder={rotulo}
        autoComplete="off"
      />
      <p className="text-xs text-muted-foreground">{ajuda}</p>
    </Stack>
  );
}

function ListaDeTags({ tags }: { tags: string[] }) {
  return (
    <p className="text-xs text-muted-foreground">
      Tags disponíveis:{' '}
      {tags.map((tag, i) => (
        <span key={tag}>
          <code className="rounded bg-muted px-1 py-0.5 font-mono">{tag}</code>
          {i < tags.length - 1 ? ' ' : ''}
        </span>
      ))}
    </p>
  );
}

function EsqueletoDestinatarios() {
  return (
    <Stack gap={2}>
      {Array.from({ length: 4 }).map((_, i) => (
        <Skeleton key={i} className="h-5 w-48" />
      ))}
    </Stack>
  );
}

/** Lista de destinatários. Recebe `usuarios` já resolvida pelo `<Deferred>` — por isso é
 *  componente próprio: o `<Deferred>` só renderiza os filhos quando a prop chegou. */
function ListaDeDestinatarios({
  usuarios,
  selecionados,
  onToggle,
}: {
  usuarios: Usuario[];
  selecionados: string[];
  onToggle: (id: string, marcado: boolean) => void;
}) {
  if (usuarios.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">Nenhum usuário disponível nesta empresa.</p>
    );
  }

  return (
    <Box className="max-h-48 overflow-y-auto rounded-md border p-3">
      <Stack gap={2}>
        {usuarios.map((u) => (
          <Inline key={u.id} gap={2} align="center">
            <Checkbox
              id={`destinatario-${u.id}`}
              checked={selecionados.includes(u.id)}
              onCheckedChange={(m) => onToggle(u.id, m === true)}
            />
            <label htmlFor={`destinatario-${u.id}`} className="cursor-pointer text-sm">
              {u.nome}
            </label>
          </Inline>
        ))}
      </Stack>
    </Box>
  );
}

/** Assunto + corpo de um template. Só aparece com o interruptor ligado — como no Blade, que
 *  fazia o mesmo por jQuery (`index.blade.php:52-64`). */
function CamposDoModelo({
  prefixo,
  modelo,
  onChange,
}: {
  prefixo: string;
  modelo: Modelo;
  onChange: (m: Modelo) => void;
}) {
  return (
    <Stack gap={3}>
      <Stack gap={1}>
        <Label htmlFor={`${prefixo}-assunto`}>Assunto do e-mail</Label>
        <Input
          id={`${prefixo}-assunto`}
          value={modelo.subject}
          onChange={(e) => onChange({ ...modelo, subject: e.target.value })}
        />
      </Stack>
      <Stack gap={1}>
        <Label htmlFor={`${prefixo}-corpo`}>Corpo do e-mail (HTML)</Label>
        <Textarea
          id={`${prefixo}-corpo`}
          value={modelo.email_body}
          onChange={(e) => onChange({ ...modelo, email_body: e.target.value })}
          rows={6}
          className="font-mono text-xs"
        />
        <p className="text-xs text-muted-foreground">
          O conteúdo é HTML e sai assim no e-mail. Esta onda não traz editor visual — ver
          Non-Goals do charter.
        </p>
      </Stack>
    </Stack>
  );
}

/* ─── Tela ────────────────────────────────────────────────────────────────────── */

export default function Configuracoes({ settings, templates, usuarios, tags }: Props) {
  const form = useForm<Formulario>({
    ...settings,
    send_for_maintenance: { ...templates.send_for_maintenance },
    assigned_for_maintenance: { ...templates.assigned_for_maintenance },
  });

  function alternarDestinatario(id: string, marcado: boolean) {
    const atual = form.data.send_for_maintenence_recipients;
    form.setData(
      'send_for_maintenence_recipients',
      marcado ? [...atual, id] : atual.filter((x) => x !== id),
    );
  }

  function salvar(e: React.FormEvent) {
    e.preventDefault();

    // Ver o bloco "A armadilha desta migração" no topo: a chave `enable_*` é OMITIDA quando
    // desmarcada, nunca enviada como `false`. `$request->has()` não distingue os dois.
    form.transform((dados) => {
      const payload: Record<string, unknown> = {
        asset_code_prefix: dados.asset_code_prefix,
        allocation_code_prefix: dados.allocation_code_prefix,
        revoke_code_prefix: dados.revoke_code_prefix,
        asset_maintenance_prefix: dados.asset_maintenance_prefix,
        send_for_maintenence_recipients: dados.send_for_maintenence_recipients,
        send_for_maintenance: dados.send_for_maintenance,
        assigned_for_maintenance: dados.assigned_for_maintenance,
      };

      if (dados.enable_asset_send_for_maintenance_email) {
        payload.enable_asset_send_for_maintenance_email = '1';
      }
      if (dados.enable_asset_assigned_for_maintenance_email) {
        payload.enable_asset_assigned_for_maintenance_email = '1';
      }

      return payload;
    });

    form.post('/asset/settings', { preserveScroll: true });
  }

  return (
    <AppShellV2>
      <Stack gap={4}>
        <PageHeader
          title="Configurações"
          subtitle="Prefixos de código e notificações de manutenção — valem para toda a empresa"
        />

        <PatrimonioSubNav active="settings" hidePrimary />

        <form onSubmit={salvar}>
          <Stack gap={4}>
            {/* ─── Prefixos ─────────────────────────────────────────────────── */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Prefixos de código</CardTitle>
              </CardHeader>
              <CardContent>
                <Stack gap={3}>
                  <p className="text-sm text-muted-foreground">
                    Cada sequência é por empresa. Mudar o prefixo <strong>não renumera</strong> o
                    que já existe — vale do próximo código em diante.
                  </p>
                  {/* `min` (auto-fill por token) em vez de `sm:grid-cols-2`: reflowa entre
                      1280 (Larissa) e 1440 (Wagner) sem media-query na tela — ADR 0253. */}
                  <Grid min="lg" gap={4}>
                    <CampoPrefixo
                      id="asset_code_prefix"
                      rotulo="Prefixo do código do bem"
                      ajuda="Usado ao cadastrar um bem novo."
                      valor={form.data.asset_code_prefix}
                      onChange={(v) => form.setData('asset_code_prefix', v)}
                    />
                    <CampoPrefixo
                      id="allocation_code_prefix"
                      rotulo="Prefixo do código de alocação"
                      ajuda="Usado ao alocar um bem a um colaborador."
                      valor={form.data.allocation_code_prefix}
                      onChange={(v) => form.setData('allocation_code_prefix', v)}
                    />
                    <CampoPrefixo
                      id="revoke_code_prefix"
                      rotulo="Prefixo do código de devolução"
                      ajuda="Usado ao registrar a devolução de um bem alocado."
                      valor={form.data.revoke_code_prefix}
                      onChange={(v) => form.setData('revoke_code_prefix', v)}
                    />
                    <CampoPrefixo
                      id="asset_maintenance_prefix"
                      rotulo="Prefixo do código de manutenção"
                      ajuda="Usado ao abrir uma manutenção."
                      valor={form.data.asset_maintenance_prefix}
                      onChange={(v) => form.setData('asset_maintenance_prefix', v)}
                    />
                  </Grid>
                </Stack>
              </CardContent>
            </Card>

            {/* ─── Notificação 1: enviado para manutenção ───────────────────── */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Bem enviado para manutenção</CardTitle>
              </CardHeader>
              <CardContent>
                <Stack gap={4}>
                  <ListaDeTags tags={tags.send_for_maintenance} />

                  <Stack gap={2}>
                    <Label>Destinatários</Label>
                    {/* `usuarios` é DEFERIDA — é a única prop que cresce com o tenant. */}
                    <Deferred data="usuarios" fallback={<EsqueletoDestinatarios />}>
                      <ListaDeDestinatarios
                        usuarios={usuarios ?? []}
                        selecionados={form.data.send_for_maintenence_recipients}
                        onToggle={alternarDestinatario}
                      />
                    </Deferred>
                    <p className="text-xs text-muted-foreground">
                      Sem nenhum destinatário, esta notificação não é enviada.
                    </p>
                  </Stack>

                  <Inline gap={3} align="start">
                    <Switch
                      id="enable_send"
                      checked={form.data.enable_asset_send_for_maintenance_email}
                      onCheckedChange={(v) =>
                        form.setData('enable_asset_send_for_maintenance_email', v)
                      }
                    />
                    <Stack gap={0}>
                      <Label htmlFor="enable_send" className="cursor-pointer">
                        Enviar também por e-mail
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Desligado, a notificação continua chegando no sino do sistema.
                      </p>
                    </Stack>
                  </Inline>

                  {form.data.enable_asset_send_for_maintenance_email && (
                    <CamposDoModelo
                      prefixo="send"
                      modelo={form.data.send_for_maintenance}
                      onChange={(m) => form.setData('send_for_maintenance', m)}
                    />
                  )}
                </Stack>
              </CardContent>
            </Card>

            {/* ─── Notificação 2: atribuído para manutenção ─────────────────── */}
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Manutenção atribuída a um responsável</CardTitle>
              </CardHeader>
              <CardContent>
                <Stack gap={4}>
                  <ListaDeTags tags={tags.assigned_for_maintenance} />
                  <p className="text-sm text-muted-foreground">
                    Vai para o colaborador designado na manutenção — não tem lista própria de
                    destinatários.
                  </p>

                  <Inline gap={3} align="start">
                    <Switch
                      id="enable_assigned"
                      checked={form.data.enable_asset_assigned_for_maintenance_email}
                      onCheckedChange={(v) =>
                        form.setData('enable_asset_assigned_for_maintenance_email', v)
                      }
                    />
                    <Stack gap={0}>
                      <Label htmlFor="enable_assigned" className="cursor-pointer">
                        Enviar também por e-mail
                      </Label>
                      <p className="text-xs text-muted-foreground">
                        Desligado, a notificação continua chegando no sino do sistema.
                      </p>
                    </Stack>
                  </Inline>

                  {form.data.enable_asset_assigned_for_maintenance_email && (
                    <CamposDoModelo
                      prefixo="assigned"
                      modelo={form.data.assigned_for_maintenance}
                      onChange={(m) => form.setData('assigned_for_maintenance', m)}
                    />
                  )}
                </Stack>
              </CardContent>
            </Card>

            <Inline gap={2} align="center">
              <Button type="submit" disabled={form.processing}>
                <Save size={14} />
                {form.processing ? 'Salvando…' : 'Salvar configurações'}
              </Button>
            </Inline>
          </Stack>
        </form>
      </Stack>
    </AppShellV2>
  );
}
