import * as React from 'react';

export type Priority = 'p0' | 'p1' | 'p2' | 'p3';

export interface BoardTask {
  /** Linear-style id, e.g. "PMG-142". */
  displayId: string;
  title: string;
  priority: Priority;
  module?: string;
  owner?: string;
  estimateH?: number;
  storyPoints?: number;
  /** Short due label, e.g. "14/05". */
  due?: string;
  isBlocked?: boolean;
  isOverdue?: boolean;
  /** Ids blocking this task (shown as ↶). */
  blockedBy?: string[];
}

export interface TaskCardProps {
  task: BoardTask;
  /** Blue selection ring (keyboard nav). */
  selected?: boolean;
  /** Makes the card draggable; fires with the task. */
  onDragStart?: (task: BoardTask) => void;
  onClick?: (task: BoardTask) => void;
}

/** Single Board/Kanban task card (DS, ProjectMgmt canon ADR 0070). */
export declare function TaskCard(props: TaskCardProps): JSX.Element;
