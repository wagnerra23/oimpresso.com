// Aba Fluxos da empresa — modelo em uso e as etapas/regras de cada processo dele.
// Fonte visual: aba "empresa", `RegrasLiga` e `CompraRegras` de prototipo-ui/cowork/Wagner/fluxos-page.jsx.
// Trocar o modelo é pela aba Modelos (gaveta → "Usar este modelo"). Cada regra liga e desliga
// (PATCH /fluxos/regra) e a Compra é configurável (PATCH /fluxos/compra, regras de fsm-regras.jsx);
// os dois exigem fluxos.configurar — sem ela os switches ficam só para leitura.

import { Link, router, usePage } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import EmptyState from '@/Components/shared/EmptyState';
import { Cadeia } from './Cadeia';
import type { EmpresaModelo, MapaOpcoesCompra, Modelo, Opcao, ProcessoDoModelo, RegraCompra, RegrasLigadas } from './tipos';

interface Props {
  empresa: EmpresaModelo | null;
  emUso: Modelo | null;
  portes: Opcao[];
  atividades: Opcao[];
  ligadas: RegrasLigadas;
  compra: RegraCompra[];
  opcoesCompra: MapaOpcoesCompra;
  podeConfigurar: boolean;
}

const rot = (l: Opcao[], v: string | null) => (v ? l.find((o) => o.value === v)?.label ?? v : null);
const salvar = { preserveScroll: true, preserveState: true } as const;

/** Quais parâmetros aparecem dependem dos outros (fsm-regras.jsx `visivel`). */
const visivel = (k: string, p: Record<string, string | number>) =>
  k === 'aprovacao' ? p.preco === 'propor' : k === 'regraEm' ? p.preco !== 'so_custo' : true;

function RegrasLiga({ p, ligadas, pode }: { p: ProcessoDoModelo; ligadas: boolean[]; pode: boolean }) {
  return (
    <div className="flx-cmp">
      {p.regras.map((r, i) => {
        const id = `flx-r-${p.dominio}-${i}`;
        return (
          <div key={i} className="flx-liga-r">
            <label htmlFor={id} className="flex flex-col gap-0.5">
              <span className="text-sm">{r.efeito}</span>
              <span className="flx-dim">Ao entrar em {r.etapa}</span>
            </label>
            <span className="flex items-center gap-2">
              {r.uc ? <code>{r.uc}</code> : null}
              <Switch
                id={id}
                variant="cowork"
                checked={ligadas[i] ?? true}
                disabled={!pode}
                onCheckedChange={(v) => router.patch('/fluxos/regra', { dominio: p.dominio, indice: i, ligada: v }, salvar)}
              />
            </span>
          </div>
        );
      })}
      <p className="flx-nota">Ligar ou desligar aqui aparece no painel de etapa das telas deste processo.</p>
    </div>
  );
}

function CompraRegras({ regras, opcoes, pode }: { regras: RegraCompra[]; opcoes: MapaOpcoesCompra; pode: boolean }) {
  return (
    <div className="flx-cmp">
      {regras.map((r) => {
        const id = `flx-c-${r.id}`;
        return (
          <div key={r.id} className="flx-cmp-r">
            <div className="flx-liga-r">
              <label htmlFor={id} className="flex flex-col gap-0.5">
                <span className="text-sm">{r.titulo}</span>
                <span className="flx-dim">Ao entrar em {r.etapa_efetiva}{r.trava ? ' · obrigatória' : ''}</span>
              </label>
              <Switch
                id={id}
                variant="cowork"
                checked={r.ativo}
                disabled={!pode || r.trava}
                onCheckedChange={(v) => router.patch('/fluxos/compra', { regra: r.id, ativo: v }, salvar)}
              />
            </div>
            {r.ativo && r.params && r.id === 'insumo.custo' && (
              <div className="flx-cmp-p">
                {Object.keys(r.params).filter((k) => visivel(k, r.params!)).map((k) => {
                  const op = opcoes[k];
                  return op && (
                  <label key={k} className="flx-busca" htmlFor={`${id}-${k}`}>
                    <span className="flx-lbl">{op.rotulo}</span>
                    <Select
                      value={String(r.params![k])}
                      disabled={!pode}
                      onValueChange={(v) => router.patch('/fluxos/compra', { regra: r.id, params: { [k]: v } }, salvar)}
                    >
                      <SelectTrigger id={`${id}-${k}`}><SelectValue /></SelectTrigger>
                      <SelectContent>
                        {op.opcoes.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                      </SelectContent>
                    </Select>
                  </label>
                  );
                })}
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}

export default function AbaEmpresa({ empresa, emUso, portes, atividades, ligadas, compra, opcoesCompra, podeConfigurar }: Props) {
  const erro = (usePage().props as { errors?: Record<string, string> }).errors?.regra ?? null;
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
      {erro && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{erro}</AlertDescription>
        </Alert>
      )}
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
        {emUso.processos.map((p) => {
          const daCompra = p.dominio === 'compra' && compra.length > 0;
          return (
            <section key={p.dominio} className="flx-card" aria-label={p.nome}>
              <div className="flx-card-h">
                <b>{p.nome}</b>
                <span className="flex items-center gap-2">
                  {daCompra && <Badge variant="secondary">configurável</Badge>}
                  <span className="flx-dim">
                    {p.etapas.length} etapas · {p.regras.length} {p.regras.length === 1 ? 'regra' : 'regras'}
                  </span>
                </span>
              </div>
              <Cadeia etapas={p.etapas} marcadas={new Set(p.regras.map((r) => r.etapa))} />
              {daCompra
                ? <CompraRegras regras={compra} opcoes={opcoesCompra} pode={podeConfigurar} />
                : p.regras.length > 0 && <RegrasLiga p={p} ligadas={ligadas[p.dominio] ?? []} pode={podeConfigurar} />}
            </section>
          );
        })}
      </div>
    </>
  );
}
