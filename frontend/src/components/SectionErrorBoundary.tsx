import React from 'react';
import { AlertCircle } from 'lucide-react';

interface Props {
  /** Shown in the fallback card so a reviewer knows which item failed to render. */
  label: string;
  children: React.ReactNode;
}

interface State {
  error: Error | null;
}

/**
 * Try/catch only guards synchronous data-prep code — it cannot catch an
 * error thrown while React is rendering JSX (a child component crashing on
 * an unexpected prop shape, for example). This is the render-time
 * counterpart: used per-photo in the Photos Evidence section so one
 * malformed media record shows an inline error card instead of taking the
 * whole submission review page down with it.
 */
export class SectionErrorBoundary extends React.Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    console.error(`SectionErrorBoundary (${this.props.label}) caught a render error:`, error, info);
  }

  render() {
    if (this.state.error) {
      return (
        <div className="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 text-[11px] text-rose-800 dark:text-rose-300 flex items-start gap-2">
          <AlertCircle className="w-3.5 h-3.5 shrink-0 mt-0.5" />
          <span>
            Unable to render {this.props.label} — {this.state.error.message || 'unexpected rendering error'}. This
            item could not be displayed; other items are unaffected.
          </span>
        </div>
      );
    }

    return this.props.children;
  }
}
