import * as React from 'react';

export type Status = 'backlog' | 'todo' | 'doing' | 'review' | 'done' | 'blocked' | 'cancelled';

export interface BoardColumnProps {
  /** Column status — colors the top border and the default label. */
  status: Status;
  /** Override the default PT-BR label. */
  label?: string;
  /** Override the auto card count. */
  count?: number;
  /** TaskCard nodes. */
  children?: React.ReactNode;
  /** Extra slot above the cards (buttons, add-card, etc). */
  header?: React.ReactNode;
  /** Drop handler — makes the column a drop target (fires with status). */
  onDrop?: (status: Status) => void;
}

/** A Kanban column (DS, ProjectMgmt canon ADR 0070). */
export declare function BoardColumn(props: BoardColumnProps): JSX.Element;
