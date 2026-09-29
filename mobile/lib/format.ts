/**
 * Helpers de formatação (Bloco 9.9).
 *
 * `BRL` e `BRLcompact` espelham `window.BRL` / `window.BRLcompact` do design.
 * `buildTimeline` deriva timestamps de andamento para a timeline da OS
 * (Bloco 3.18). `formatPrazo` produz selos coloridos como "Atrasado 40min" /
 * "Vence em 1h10".
 */

/** "R$ 1.234,56" */
export function BRL(n: number | null | undefined): string {
  const v = typeof n === "number" && Number.isFinite(n) ? n : 0;
  return v.toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/** "R$ 1,2k" / "R$ 1,3M" — compactado para KPIs e cards. */
export function BRLcompact(n: number | null | undefined): string {
  const v = typeof n === "number" && Number.isFinite(n) ? n : 0;
  const abs = Math.abs(v);
  if (abs >= 1_000_000)
    return `R$ ${(v / 1_000_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}M`;
  if (abs >= 1_000)
    return `R$ ${(v / 1_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 })}k`;
  return BRL(v);
}

/** "1h10", "40min", "3d". */
export function formatDuration(minutes: number): string {
  const m = Math.max(0, Math.round(minutes));
  if (m < 60) return `${m}min`;
  const h = Math.floor(m / 60);
  const mm = m % 60;
  if (h < 24) return mm ? `${h}h${String(mm).padStart(2, "0")}` : `${h}h`;
  const d = Math.floor(h / 24);
  return `${d}d`;
}

export type PrazoStatus = {
  tone: "ok" | "warn" | "danger" | "neutral";
  label: string;
  /** Minutos absolutos até o prazo (positivo = futuro, negativo = atrasado). */
  diffMinutes: number;
};

/** Compara um prazo (ISO) ao agora e devolve etiqueta colorida. */
export function formatPrazo(
  prazoISO: string | Date | null | undefined,
  now: Date = new Date(),
): PrazoStatus {
  if (!prazoISO) return { tone: "neutral", label: "Sem prazo", diffMinutes: 0 };
  const due = typeof prazoISO === "string" ? new Date(prazoISO) : prazoISO;
  const diff = (due.getTime() - now.getTime()) / 60000;
  if (diff < 0) {
    return {
      tone: "danger",
      label: `Atrasado ${formatDuration(-diff)}`,
      diffMinutes: diff,
    };
  }
  if (diff < 60) {
    return {
      tone: "danger",
      label: `Vence em ${formatDuration(diff)}`,
      diffMinutes: diff,
    };
  }
  if (diff < 60 * 24) {
    return {
      tone: "warn",
      label: `Vence em ${formatDuration(diff)}`,
      diffMinutes: diff,
    };
  }
  return {
    tone: "ok",
    label: `Em ${formatDuration(diff)}`,
    diffMinutes: diff,
  };
}

export type TimelineEvent = {
  stage: string;
  done: boolean;
  current: boolean;
  paused?: boolean;
  /** ISO. */
  at?: string;
  operator?: string;
  note?: string;
};

/**
 * Deriva uma timeline a partir do pipeline + estado atual.
 *
 * `openedAt` é a referência (ISO). Cada etapa concluída ganha um timestamp
 * espaçado a partir dela; a etapa atual fica "Em andamento" (sem `at`).
 */
export function buildTimeline(args: {
  pipeline: readonly string[];
  currentStage: string;
  openedAt: string | Date;
  paused?: boolean;
  operatorPerStage?: Partial<Record<string, string>>;
  spacingMinutes?: number;
}): TimelineEvent[] {
  const {
    pipeline,
    currentStage,
    openedAt,
    paused = false,
    operatorPerStage = {},
    spacingMinutes = 90,
  } = args;
  const start = typeof openedAt === "string" ? new Date(openedAt) : openedAt;
  const currentIdx = Math.max(0, pipeline.indexOf(currentStage));

  return pipeline.map((stage, i) => {
    if (i < currentIdx) {
      const at = new Date(start.getTime() + i * spacingMinutes * 60_000);
      return {
        stage,
        done: true,
        current: false,
        at: at.toISOString(),
        operator: operatorPerStage[stage],
      };
    }
    if (i === currentIdx) {
      return {
        stage,
        done: false,
        current: true,
        paused,
        operator: operatorPerStage[stage],
      };
    }
    return { stage, done: false, current: false };
  });
}

/** "Hoje 08:10" / "Ontem 14:30" / "21/05 09:00". */
export function formatTimestamp(
  iso: string | Date | undefined,
  now: Date = new Date(),
): string {
  if (!iso) return "—";
  const d = typeof iso === "string" ? new Date(iso) : iso;
  const sameDay =
    d.getFullYear() === now.getFullYear() &&
    d.getMonth() === now.getMonth() &&
    d.getDate() === now.getDate();
  const yesterday = new Date(now);
  yesterday.setDate(now.getDate() - 1);
  const isYesterday =
    d.getFullYear() === yesterday.getFullYear() &&
    d.getMonth() === yesterday.getMonth() &&
    d.getDate() === yesterday.getDate();
  const hm = `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
  if (sameDay) return `Hoje ${hm}`;
  if (isYesterday) return `Ontem ${hm}`;
  const dm = `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}`;
  return `${dm} ${hm}`;
}
