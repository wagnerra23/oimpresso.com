// Manufacturing/Settings — Configurações do módulo, em `/manufacturing/settings`.
//
// FONTE DE DESIGN: `prototipo-ui/cowork/Wagner/manufacturing-producao.jsx::MfgConfig` — mesmo
// bundle já aplicado inteiro pela Onda 1 (Recipes.tsx); nenhuma classe CSS nova aqui.
// F1 PLAN: memory/requisitos/Manufacturing/RUNBOOK-settings.md.
//
// US-MANU-003 (SPEC.md). Rota ADITIVA — `/manufacturing/settings` (Blade) segue intocado.
//
// PRIMEIRA tela da família que ESCREVE. O POST vai pro endpoint EXISTENTE
// (`SettingsController@store`, sem alteração) — Inertia segue o `redirect()->back()` dele
// e re-busca as props desta página. Não há cálculo de valor/estoque aqui (só string + 2
// bool), então a REGRA MESTRE de valor/estoque não se aplica — mas ainda é escrita real,
// então o botão "Atualizar" só habilita quando algo mudou (R-24) e o disparo é explícito
// (clique), nunca on-change silencioso.
//
// O cartão "Permissões (simulação)" do protótipo NÃO entra — é ferramenta do próprio
// protótipo, sem equivalente real na app (SPEC.md DoD exclui explicitamente).

import { Link, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Button } from '@/Components/ui/button';
import { Switch } from '@/Components/ui/switch';
import FabricacaoAbas from './_components/FabricacaoAbas';
import '../../../css/cowork-manufacturing-bundle.css';

interface SettingsShape {
  ref_no_prefix: string;
  disable_editing_ingredient_qty: boolean;
  enable_updating_product_price: boolean;
}

interface Props {
  settings: SettingsShape;
  version: string | null;
  permissions: { prod: boolean };
  producao: { total: number; rascunhos: number };
  recipes_count: number;
}

export default function Settings({
  settings,
  version,
  permissions,
  producao,
  recipes_count,
}: Props) {
  const [s, setS] = useState<SettingsShape>(settings);
  const [salvando, setSalvando] = useState(false);
  const dirty = JSON.stringify(s) !== JSON.stringify(settings);

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    if (!dirty || salvando) return;
    setSalvando(true);
    router.post(
      '/manufacturing/settings',
      {
        ref_no_prefix: s.ref_no_prefix,
        disable_editing_ingredient_qty: s.disable_editing_ingredient_qty ? 1 : 0,
        enable_updating_product_price: s.enable_updating_product_price ? 1 : 0,
      },
      {
        preserveScroll: true,
        onFinish: () => setSalvando(false),
      },
    );
  };

  return (
    <div className="mfg-root" data-screen-label="Fabricação · Configurações">
      <div className="os-page-h" data-contract="cabecalho">
        <div className="os-page-h-l">
          <h1>Fabricação</h1>
          <p>Configurações do módulo</p>
        </div>
      </div>

      <FabricacaoAbas ativa="config" receitas={recipes_count} producao={producao} podeProduzir={permissions.prod} />

      <form className="mfg-cfg" data-contract="form" onSubmit={salvar}>
        <div className="mfg-card">
          <div className="mfg-sec">
            <span>Configurações do módulo</span>
            <span className="ln" />
          </div>

          <label className="mfg-fld" style={{ width: 260 }}>
            <span>Prefixo da referência</span>
            <input
              className="mfg-inp"
              value={s.ref_no_prefix}
              onChange={(e) => setS({ ...s, ref_no_prefix: e.target.value })}
            />
            <small>usado na numeração das ordens de produção</small>
          </label>

          {/* Interruptores, como o protótipo (`manufacturing-producao.jsx`: `<Switch label sublabel>` dentro
              de `.mfg-switch-host`). Antes eram caixas de marcação — o mesmo controle do filtro "Só
              finalizadas" das outras abas, mas aqui é ligar/desligar uma opção do módulo ([M] 2026-10-06).
              O campo de prefixo ganha a largura do protótipo (260). */}
          <label className="mfg-check big" htmlFor="mfg-cfg-travar-qtd">
            <Switch
              id="mfg-cfg-travar-qtd"
              checked={s.disable_editing_ingredient_qty}
              onCheckedChange={(v) =>
                setS({ ...s, disable_editing_ingredient_qty: v === true })
              }
            />
            Bloquear edição da quantidade de ingrediente
            <small>
              quando ligado, a receita manda: nem editor nem ordem de produção permitem
              ajustar consumo.
            </small>
          </label>

          <label className="mfg-check big" htmlFor="mfg-cfg-preco-produto">
            <Switch
              id="mfg-cfg-preco-produto"
              checked={s.enable_updating_product_price}
              onCheckedChange={(v) =>
                setS({ ...s, enable_updating_product_price: v === true })
              }
            />
            Atualizar preço do produto ao finalizar produção
            <small>propaga o custo unitário calculado para a ficha do produto.</small>
          </label>

          <div className="mfg-ed-f mfg-inline">
            <span className="mfg-crumb-meta">
              Fabricação{version ? ` v${version}` : ''}
            </span>
            <span className="sp" />
            <Button type="submit" size="sm" disabled={!dirty || salvando}>
              Atualizar
            </Button>
          </div>
        </div>

        <div className="mfg-card">
          <div className="mfg-sec">
            <span>Integrações</span>
            <span className="ln" />
          </div>
          <ul className="mfg-int">
            <li>
              <b>Produtos</b> — a receita pertence a uma variação; o custo calculado alimenta
              a composição.{' '}
              <a className="mfg-link" href="/products">
                abrir Produtos
              </a>
            </li>
            <li>
              <b>Compras</b> — salvar nota de insumo recalcula todas as receitas que usam o
              item.{' '}
              <a className="mfg-link" href="/purchases">
                abrir Compras
              </a>
            </li>
            <li>
              <b>Fila de produção</b> — a ordem finalizada entra na fila do chão de fábrica.{' '}
              <Link className="mfg-link" href="/manufacturing/production">
                abrir Fila
              </Link>
            </li>
          </ul>
        </div>
      </form>
    </div>
  );
}

Settings.layout = (page: ReactNode) => (
  <AppShellV2
    title="Configurações · Fabricação"
    breadcrumbItems={[{ label: 'Fabricação' }, { label: 'Configurações' }]}
  >
    {page}
  </AppShellV2>
);
