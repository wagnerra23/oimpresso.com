import { Search } from 'lucide-react';
import { cn } from '@/lib/utils';

interface SearchBarProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  className?: string;
}

export function SearchBar({ value, onChange, placeholder = 'Buscar...', className }: SearchBarProps) {
  return (
    <div className={cn('relative flex items-center', className)}>
      <Search className="absolute left-3 w-[18px] h-[18px] text-[var(--text-tertiary)]" />
      <input
        type="text"
        value={value}
        onChange={e => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full h-10 bg-[var(--bg-elevated)] rounded-xl pl-10 pr-4 text-[15px] text-[var(--text-primary)] placeholder:text-[var(--text-tertiary)] outline-none border border-transparent focus:border-[var(--primary)]/50 transition-all"
      />
    </div>
  );
}
