// Components/clientes/BrunaGreeting.tsx
//
// PTDP Onda 1 do Cliente · saudação personificada do operador (Cowork chat1 ref).
// Persona âncora: Bruna (vendedora 20x/dia · biz=4 ROTA LIVRE).
//
// **Modo:** dados mockados nesta versão. Backend real (TaskProvider/Crm agenda)
// fica pra próxima onda quando os endpoints existirem.
//
// Refs:
//   - prototipo-ui/prototipos/clientes/clientes-ptdp.jsx::BrunaGreeting (Cowork canon)
//   - PT-01 Slot 1 estende (não substitui PageHeader)
//   - Constituição UI v2 · ADR UI-0013 (camada 4-Módulo)
//   - AP1-AP8 PRE-MERGE-UI respeitados (tokens semânticos · lucide-only · PT-BR)

import { useMemo } from 'react';
import { Avatar } from './Avatar';

export interface BrunaGreetingProps {
  /** Nome do operador. Default 'Bruna' · pega de auth().user no futuro. */
  name?: string;
  /** Agenda mockada (próxima onda: vem de TaskProvider/Crm). */
  stats?: {
    ligacoes?: number;
    retornos?: number;
    vipsSemCompra?: number;
  };
}

function periodoSaudacao(): 'Bom dia' | 'Boa tarde' | 'Boa noite' {
  const h = new Date().getHours();
  if (h < 12) return 'Bom dia';
  if (h < 18) return 'Boa tarde';
  return 'Boa noite';
}

/**
 * Saudação do operador no topo da listagem · mockup PTDP.
 *
 * Exemplo:
 *   <BrunaGreeting />
 *   <BrunaGreeting name="Larissa" stats={{ ligacoes: 8, retornos: 2 }} />
 */
export function BrunaGreeting({
  name = 'Bruna',
  stats = { ligacoes: 12, retornos: 3, vipsSemCompra: 1 },
}: BrunaGreetingProps) {
  const periodo = useMemo(() => periodoSaudacao(), []);
  const partes = [
    stats.ligacoes !== undefined ? `${stats.ligacoes} ligações na agenda` : null,
    stats.retornos !== undefined ? `${stats.retornos} retornos pendentes` : null,
    stats.vipsSemCompra !== undefined && stats.vipsSemCompra > 0
      ? `${stats.vipsSemCompra} VIP${stats.vipsSemCompra > 1 ? 's' : ''} sem compra há 60d`
      : null,
  ].filter(Boolean);

  return (
    <div className="flex items-center gap-3 px-3 py-2 mt-3 rounded-md bg-muted/40 border border-border">
      <Avatar name={name} size={28} />
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-foreground leading-none">
          {periodo}, {name}
        </p>
        {partes.length > 0 && (
          <p className="text-xs text-muted-foreground mt-1 leading-tight tabular-nums">
            {partes.join(' · ')}
          </p>
        )}
      </div>
    </div>
  );
}

export default BrunaGreeting;
