import * as React from 'react';

export interface ColumnDef {
  key: string;
  label: string;
  /** Chave do grupo em `groups` — organiza a lista de disponíveis. */
  group?: string;
  /** Fixa = a linha não existe sem ela: não desliga e não sai do lugar. */
  fixed?: boolean;
  /** Entra na configuração inicial de quem nunca mexeu. */
  default?: boolean;
  width?: number | string;
}

export interface ColumnGroup {
  key: string;
  label: string;
}

export interface ColumnManagerProps {
  /** Catálogo completo. A ordem deste array é a ordem de leitura da grade. */
  columns: ColumnDef[];
  groups?: ColumnGroup[];
  /** Chaves ativas, na ordem escolhida (controlado). */
  active: string[];
  onChange?: (next: string[]) => void;
  /** Se informado, grava a preferência no localStorage a cada mudança. */
  storageKey?: string;
  labelActive?: string;
  labelAvailable?: string;
  showReset?: boolean;
  maxHeight?: number | string;
}

/** Escolher e reordenar colunas de uma grade — arrastar + ↑↓, grupos, coluna fixa (DS). */
export declare function ColumnManager(props: ColumnManagerProps): JSX.Element;

/** Helpers puros da preferência de coluna — saneamento defensivo, mover, alternar, persistir. */
export declare const ColumnPrefs: {
  defaults(columns: ColumnDef[]): string[];
  find(columns: ColumnDef[], key: string): ColumnDef | undefined;
  sanitize(columns: ColumnDef[], input: unknown): string[];
  /** Move uma coluna. Fixa é âncora: não se move nem muda de índice, e as outras passam por cima dela. */
  move(columns: ColumnDef[], active: string[], from: number, to: number): string[];
  toggle(columns: ColumnDef[], active: string[], key: string): string[];
  load(columns: ColumnDef[], storageKey: string, storage?: Pick<Storage, 'getItem'>): string[];
  save(active: string[], storageKey: string, storage?: Pick<Storage, 'setItem'>): void;
  resolve(columns: ColumnDef[], active: string[]): ColumnDef[];
};
