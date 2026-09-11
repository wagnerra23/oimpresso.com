export interface AvatarProps {
  /** Full name — drives both the initials and the deterministic color. */
  name?: string;
  /** Override the derived initials (max 2 chars looks best). */
  initials?: string;
  /** Force a palette color 1..8 instead of hashing the name. */
  c?: 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8;
  /** 'sm' = 24px (default) · 'md' = 32px · 'lg' = 40px. */
  size?: 'sm' | 'md' | 'lg';
  /** Tooltip text (defaults to `name`). */
  title?: string;
  /** Presence dot at the corner. */
  status?: 'online' | 'busy' | 'away' | 'offline';
}

/** Initials avatar on a deterministic color (DS v4 canon — no photos). */
export declare function Avatar(props: AvatarProps): JSX.Element;
