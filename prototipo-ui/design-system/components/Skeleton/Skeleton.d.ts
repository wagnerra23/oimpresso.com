export interface SkeletonProps {
  /** Shape preset. */
  variant?: 'text' | 'title' | 'caption' | 'avatar' | 'avatar-md' | 'row' | 'card';
  /** Override width (CSS value or px number). */
  width?: string | number;
  /** Render N stacked copies (for list/text blocks). */
  count?: number;
}

/** Shimmer placeholder shown while content loads (DS v4). */
export declare function Skeleton(props: SkeletonProps): JSX.Element;
