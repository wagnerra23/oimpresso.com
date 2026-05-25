import * as React from 'react';
import { Plus } from 'lucide-react';

/**
 * PageHeaderPrimary — botão primary canon ÚNICO universal pras Index do oimpresso.
 *
 * ADR 0190 (2026-05-25): primary INTERNO das telas é SEMPRE roxo médio universal
 * `oklch(0.55 0.15 295)`, independente do grupo do módulo. Supersede pattern v3
 * hue-per-grupo (FinanceiroPrimaryButton verde 145, JanaPrimaryButton azul 215,
 * PontoPrimaryButton limão 88 — todos DEPRECATED por esta ADR).
 *
 * Hue per grupo (SIDEBAR_GROUP_HUE em `cockpit/shared.ts`) continua existindo
 * APENAS pra agrupamento visual do sidebar (header de grupo, ícones), NÃO se
 * aplica mais ao primary das telas.
 *
 * Token canon ADR 0189 v3.1 + ADR 0190:
 *   bg:     oklch(0.55 0.15 295)  roxo médio
 *   border: oklch(0.45 0.15 295)  roxo escuro
 *   text:   oklch(0.99 0 0)       branco
 *   height: 32px (h-8) density compact
 *   radius: 6px (rounded-md)
 *   font:   system ui-sans-serif forçado inline (AP16 LEARNINGS)
 *
 * Uso canon nas Index do projeto (Zona R do PageHeader):
 *
 *   <PageHeaderPrimary label="Novo cliente" href="/contacts/create?type=customer" />
 *   <PageHeaderPrimary label="Nova venda"   href="/sells/create" />
 *   <PageHeaderPrimary label="Pagar"        onClick={handlePagar} icon={Send} />
 *   <PageHeaderPrimary label="Novo título"  href="/financeiro/titulos/create" disabled={!online} />
 *
 * Hierarquia visual no header (ADR 0189 §4.5 + §4.6):
 *   Zona R = `⋮` (overflow ghost · ações secundárias) + <PageHeaderPrimary />
 *   APENAS 1 PageHeaderPrimary por header (no máximo).
 */
interface PageHeaderPrimaryProps {
  /** Texto do botão · 1-3 palavras · verbo de ação. Ex: "Novo cliente", "Pagar", "Importar". */
  label: string;
  /** Rota de destino (criação/ação). Render como `<a>` se setado. */
  href?: string;
  /** Handler de click quando não é navegação (ex: abrir modal). Render como `<button>`. */
  onClick?: () => void;
  /** Override do ícone default `<Plus />`. Use pra ações não-create (Upload, Send, Save, etc). */
  icon?: React.ComponentType<{ className?: string }>;
  /** Desabilita botão visual + funcionalmente. Use quando offline ou sem permissão. */
  disabled?: boolean;
  /** Atributos HTML adicionais — title, aria-label, data-testid. */
  title?: string;
  'aria-label'?: string;
  'data-testid'?: string;
}

export function PageHeaderPrimary({
  label,
  href,
  onClick,
  icon: Icon = Plus,
  disabled = false,
  title,
  'aria-label': ariaLabel,
  'data-testid': dataTestId,
}: PageHeaderPrimaryProps) {
  const className =
    'inline-flex items-center gap-1.5 h-8 px-3 rounded-md text-[12.5px] font-medium ' +
    'transition-all duration-150 ' +
    'hover:opacity-90 active:scale-[0.97] ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 ' +
    'disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 disabled:hover:opacity-50';

  const style: React.CSSProperties = {
    backgroundColor: 'oklch(0.55 0.15 295)',
    borderColor: 'oklch(0.45 0.15 295)',
    border: '1px solid oklch(0.45 0.15 295)',
    color: 'oklch(0.99 0 0)',
    fontFamily: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
  };

  const content = (
    <>
      <Icon className="h-3.5 w-3.5" aria-hidden="true" />
      {label}
    </>
  );

  // Render como <a> quando tem href e não desabilitado
  if (href && !disabled) {
    return (
      <a
        href={href}
        className={className}
        style={style}
        title={title}
        aria-label={ariaLabel}
        data-testid={dataTestId}
      >
        {content}
      </a>
    );
  }

  // Render como <button> pra onClick / disabled / fallback
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      className={className}
      style={style}
      title={title}
      aria-label={ariaLabel}
      data-testid={dataTestId}
    >
      {content}
    </button>
  );
}

export default PageHeaderPrimary;
