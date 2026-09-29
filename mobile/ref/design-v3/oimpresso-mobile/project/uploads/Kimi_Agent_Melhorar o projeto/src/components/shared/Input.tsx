import { cn } from '@/lib/utils';
import { type InputHTMLAttributes, forwardRef } from 'react';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  icon?: React.ReactNode;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, icon, className, ...props }, ref) => (
    <div className="w-full">
      {label && (
        <label className="block text-[13px] font-medium text-[var(--text-secondary)] mb-1.5">
          {label}
        </label>
      )}
      <div className="relative">
        {icon && (
          <div className="absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-tertiary)]">
            {icon}
          </div>
        )}
        <input
          ref={ref}
          className={cn(
            'w-full h-12 bg-[var(--input-bg)] border border-[var(--border-color)] rounded-xl px-4 text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none transition-all focus:border-[var(--primary)] focus:ring-[3px] focus:ring-[var(--primary)]/15',
            icon && 'pl-10',
            className
          )}
          {...props}
        />
      </div>
      {error && <p className="text-[var(--danger)] text-xs mt-1">{error}</p>}
    </div>
  )
);
Input.displayName = 'Input';
