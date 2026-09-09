export type FsmState = 'done' | 'current' | 'todo' | 'term';

export interface FsmStep {
  /** Phase label (shown for current in inline; for all in full). */
  label: string;
  /** Phase state — 'term' marks a terminal/cancelled stop (red). */
  state: FsmState;
  /** Optional meta shown under the label in `full` (e.g. "2h 10m"). */
  meta?: string;
}

export interface FsmStepperProps {
  /** Ordered pipeline phases. */
  steps: FsmStep[];
  /** 'inline' (dots + current label) or 'full' (numbered circles + lines). */
  variant?: 'inline' | 'full';
  /** OKLCH hue for the pipeline color (220 = blue default, 295 = roxo brand). */
  hue?: number;
}

/** Canonical pipeline indicator — inline (table) or full (drawer). DS v4. */
export declare function FsmStepper(props: FsmStepperProps): JSX.Element;
