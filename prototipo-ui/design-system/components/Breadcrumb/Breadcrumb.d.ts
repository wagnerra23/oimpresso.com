export interface Crumb {
  label: string;
  /** Link target; omit on the last (current) crumb. */
  href?: string;
}

export interface BreadcrumbProps {
  /** Ordered hierarchy; the last item renders as current (bold). */
  items: Crumb[];
}

/** Current-page hierarchy trail (DS v4). */
export declare function Breadcrumb(props: BreadcrumbProps): JSX.Element;
