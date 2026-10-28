// @memcofre
//   tela: /contact/contact-dashboard
//   module: Crm
//   stories: thread Crm/18 (DashboardController@index · Blade → Inertia)
//   permissao: contato logado (CheckContactLogin) — não é tela de operador
//
// Início do portal do contato. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: alvo crm--portal-painel--index (A3) · prototipo-ui/cowork/Wagner/crm-portal.jsx → view `painel`
//
// Esta onda é LEITURA, atrás da flag useV2CrmPortalPainel. Valores só são MOSTRADOS: chegam prontos e
// formatados do servidor (ContactUtil::getContactInfo, a mesma conta da Blade).

import { usePage } from '@inertiajs/react';
import { CalendarClock, Package, Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import KpiCard from '@/Components/shared/KpiCard';
import PortalContatoLayout, { type CascaPortal } from '../_layout/PortalContatoLayout';

interface Bloco { total: string; pago: string; a_pagar: string; total_f: string; pago_f: string; a_pagar_f: string }
interface Props {
  saudacao: string;
  vendas?: Bloco;
  compras?: Bloco;
  abertura?: Bloco;
}

export default function PortalPainel({ saudacao, vendas, compras, abertura }: Props) {
  const portal = (usePage().props as { shell?: { portal?: CascaPortal } }).shell?.portal;
  const menu = portal?.menu ?? [];
  const tem = (chave: string) => menu.some((m) => m.chave === chave);
  const href = (chave: string) => menu.find((m) => m.chave === chave)?.href ?? '#';
  const ir = (url: string) => window.location.assign(url);
  const primeiroNome = saudacao.split(/\s+/)[0] ?? saudacao;

  return (
    <PortalContatoLayout ativa="painel" titulo="Início">
      <div>
        <h1 className="cp-h1">Olá, {primeiroNome}.</h1>
        <p className="cp-sub">{portal?.contato.nome} · conta de cliente em {portal?.empresa}</p>
      </div>

      {vendas && (
        <div className="cp-saldo" data-contract="portal-saldo">
          <div>
            <div className="cp-saldo-l">Você tem a pagar</div>
            <div className="cp-saldo-v">{vendas.a_pagar_f}</div>
            <div className="cp-saldo-s">de {vendas.total_f} em vendas · {vendas.pago_f} já pagos</div>
          </div>
          <div className="sp" />
          <Button onClick={() => ir(href('extrato'))}>Ver extrato</Button>
        </div>
      )}

      {(compras || abertura) && (
        <div className="cp-kpis" data-contract="portal-kpis">
          {compras && <KpiCard label="Total em compras" value={compras.total_f} tone="info" />}
          {compras && <KpiCard label="Compras a pagar" value={compras.a_pagar_f} tone="warning" />}
          {abertura && <KpiCard label="Saldo de abertura" value={abertura.total_f} description={`${abertura.a_pagar_f} em aberto`} />}
        </div>
      )}

      {(tem('pedidos') || tem('agendamentos')) && portal && (
        <div className="cp-acoes" data-contract="portal-atalhos">
          {tem('pedidos') && (
            <button type="button" className="cp-acao" onClick={() => ir(portal.novo_pedido)}>
              <span className="cp-acao-i"><Plus size={16} aria-hidden="true" /></span>
              <span>Pedir de novo<small>solicitação com o preço da sua tabela</small></span>
            </button>
          )}
          {tem('agendamentos') && (
            <button type="button" className="cp-acao" onClick={() => ir(portal.agendar)}>
              <span className="cp-acao-i"><CalendarClock size={16} aria-hidden="true" /></span>
              <span>Agendar retirada<small>o balcão confirma por WhatsApp</small></span>
            </button>
          )}
          {tem('pedidos') && (
            <button type="button" className="cp-acao" onClick={() => ir(href('pedidos'))}>
              <span className="cp-acao-i"><Package size={16} aria-hidden="true" /></span>
              <span>Acompanhar pedidos</span>
            </button>
          )}
        </div>
      )}
    </PortalContatoLayout>
  );
}
