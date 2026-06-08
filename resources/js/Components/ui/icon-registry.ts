/**
 * Icon Registry — re-exporta `lucide-react` com nomes canônicos do domínio
 * oimpresso (ERP gráfico de comunicação visual).
 *
 * **Por que existe (recomendação P2 #8 da auditoria 2026-05-07):**
 * Pages Inertia hoje misturam 3 convenções de ícones:
 * - PascalCase direto: `import { Wrench } from 'lucide-react'`
 * - kebab-case via `<Icon name="wrench"/>` (resources/js/Components/Icon.tsx)
 * - Emojis em alguns mocks legacy (proibido por R-DS-003)
 *
 * Bug histórico (PR #185/#186): kebab-case não fazia lookup correto e
 * crashava quando `name` chegava não-string. Esse registry elimina o
 * problema na origem — você importa o componente real, não passa string.
 *
 * **Convenção:**
 *  - Nomes começam com `Icon<Conceito>` no domínio do ERP
 *  - O alias mapeia pra ícone Lucide preciso
 *  - Pra trocar família (ex: lucide → tabler), basta editar este arquivo
 *
 * @see memory/requisitos/_DesignSystem/SPEC.md R-DS-003
 * @see PR #185/#186 (bug Icon.tsx kebab-case)
 *
 * @example
 *   // Antes (frágil — string lookup, kebab-case):
 *   <Icon name="message-circle" />
 *
 *   // Depois (type-safe, refactor via IDE):
 *   import { IconConversa } from '@/Components/ui/icon-registry';
 *   <IconConversa size={16} />
 */

import {
  // Operacional / OS
  Briefcase,
  ClipboardList,
  CheckCircle2,
  CircleCheck,
  Clock,
  Hourglass,
  RotateCcw,
  Wrench,

  // Comunicação / Whatsapp / Chat
  MessageCircle,
  MessageSquare,
  Bot,
  Send,
  Search,

  // Cliente / Pessoas
  User,
  UserCheck,
  Users,
  Building2,

  // Financeiro
  DollarSign,
  CreditCard,
  Receipt,
  PiggyBank,
  TrendingUp,
  TrendingDown,

  // Documentos / Templates
  FileText,
  File,
  FileSearch,
  FileCheck,
  Files,

  // Estoque / Produtos
  Package,
  Smartphone,

  // Status / Feedback
  Check,
  CheckCheck,
  X,
  AlertTriangle,
  AlertCircle,
  Info,
  Circle,

  // Navegação
  ArrowLeft,
  ArrowRight,
  ArrowUp,
  ArrowDown,
  ArrowUpRight,
  ChevronLeft,
  ChevronRight,
  ChevronUp,
  ChevronDown,
  ExternalLink,

  // Dashboard / Métricas
  LayoutDashboard,
  BarChart3,
  Activity,
  Flag,

  // Sistema / Admin
  Settings,
  Cog,
  Bell,
  Inbox,
  Plus,
  Minus,
  Pencil,
  Trash2,
  Copy,
  Download,
  Upload,
  RefreshCw,
} from 'lucide-react';

// ──────────────────────────────────────────────────────────────────────
// Operacional / Ordens de Serviço (Repair / Office Impresso)
// ──────────────────────────────────────────────────────────────────────
export {
  Briefcase as IconOS,            // Ordem de Serviço (genérica)
  ClipboardList as IconJobSheet,  // Folha de OS (Repair)
  Wrench as IconRepair,           // Reparo / módulo Repair
  CheckCircle2 as IconConcluido,  // OS concluída
  CircleCheck as IconStatusDone,  // Status de conclusão
  Clock as IconPrazo,             // Prazo / SLA
  Hourglass as IconAguardando,    // Aguardando humano / pendente
  RotateCcw as IconReabrir,       // Reabrir item / undo
  Flag as IconStatus,             // Status / etapa
};

// ──────────────────────────────────────────────────────────────────────
// Comunicação / Whatsapp / Chat / Copiloto
// ──────────────────────────────────────────────────────────────────────
export {
  MessageCircle as IconConversa,  // Conversa Whatsapp / Chat
  MessageSquare as IconChat,      // Chat genérico (Copiloto)
  Bot as IconBot,                 // Bot Jana / IA
  Send as IconEnviar,             // Enviar mensagem
  Search as IconBusca,            // Busca / search input
  Inbox as IconInbox,             // Caixa de entrada
};

// ──────────────────────────────────────────────────────────────────────
// Cliente / Pessoas / CRM
// ──────────────────────────────────────────────────────────────────────
export {
  User as IconPessoa,
  UserCheck as IconAtribuir,      // Atribuir-me / responsável
  Users as IconEquipe,            // Equipe / staff
  Building2 as IconEmpresa,       // Empresa / business
};

// ──────────────────────────────────────────────────────────────────────
// Financeiro
// ──────────────────────────────────────────────────────────────────────
export {
  DollarSign as IconFinanceiro,
  CreditCard as IconCobranca,
  Receipt as IconFatura,
  PiggyBank as IconPoupanca,
  TrendingUp as IconAlta,
  TrendingDown as IconBaixa,
};

// ──────────────────────────────────────────────────────────────────────
// Documentos / Templates / NFe
// ──────────────────────────────────────────────────────────────────────
export {
  FileText as IconDocumento,      // Template HSM / documento genérico
  FileText as IconTemplate,       // alias semântico
  File as IconArquivo,
  FileSearch as IconBuscarDoc,
  FileCheck as IconNFe,           // NFe emitida / DANFE
  Files as IconArquivos,
};

// ──────────────────────────────────────────────────────────────────────
// Estoque / Produtos
// ──────────────────────────────────────────────────────────────────────
export {
  Package as IconProduto,
  Smartphone as IconAparelho,     // Aparelho/celular pra Repair DeviceModels
};

// ──────────────────────────────────────────────────────────────────────
// Status / Feedback / Alerta
// ──────────────────────────────────────────────────────────────────────
export {
  Check as IconCheck,             // ✓ enviada
  CheckCheck as IconLido,         // ✓✓ entregue/lida
  X as IconFechar,
  AlertTriangle as IconAlerta,    // Warning
  AlertCircle as IconErro,        // Error
  Info as IconInfo,
  Circle as IconFallback,         // Fallback genérico
};

// ──────────────────────────────────────────────────────────────────────
// Navegação
// ──────────────────────────────────────────────────────────────────────
export {
  ArrowLeft as IconVoltar,
  ArrowRight as IconAvancar,
  ArrowUp as IconSubir,
  ArrowDown as IconDescer,
  ArrowUpRight as IconEnviada,    // ↗ outbound (chat)
  ChevronLeft as IconAnterior,    // pagination
  ChevronRight as IconProximo,
  ChevronUp as IconExpandir,
  ChevronDown as IconColapsar,
  ExternalLink as IconLinkExterno,
};

// ──────────────────────────────────────────────────────────────────────
// Dashboard / Métricas / Skills (ADS)
// ──────────────────────────────────────────────────────────────────────
export {
  LayoutDashboard as IconDashboard,
  BarChart3 as IconMetricas,
  Activity as IconAtividade,
};

// ──────────────────────────────────────────────────────────────────────
// Sistema / Admin / Settings
// ──────────────────────────────────────────────────────────────────────
export {
  Settings as IconConfig,
  Cog as IconAdmin,
  Bell as IconNotificacoes,
  Plus as IconAdicionar,
  Minus as IconRemover,
  Pencil as IconEditar,
  Trash2 as IconExcluir,
  Copy as IconCopiar,
  Download as IconBaixar,
  Upload as IconCarregar,
  RefreshCw as IconAtualizar,     // Refresh / sync
};

/**
 * Re-export type pra interop com componentes que aceitam `LucideIcon` direto.
 * Use `IconType` como prop quando o componente aceita "qualquer ícone do registry".
 */
export type { LucideIcon as IconType } from 'lucide-react';
