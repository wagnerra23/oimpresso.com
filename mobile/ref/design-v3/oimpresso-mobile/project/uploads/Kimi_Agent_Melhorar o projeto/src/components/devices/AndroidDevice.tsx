import { Wifi, Battery } from 'lucide-react';
import { cn } from '@/lib/utils';

interface AndroidDeviceProps {
  children: React.ReactNode;
  className?: string;
}

export function AndroidDevice({ children, className }: AndroidDeviceProps) {
  return (
    <div
      className={cn('relative flex-shrink-0 overflow-hidden', className)}
      style={{
        width: 393,
        height: 852,
        borderRadius: 36,
        border: '10px solid #1a1a1c',
        boxShadow: '0 0 0 1px #2a2a2c, 0 25px 80px rgba(0,0,0,0.6), 0 10px 30px rgba(0,0,0,0.4)',
        background: '#000',
      }}
    >
      <div className="absolute inset-0 overflow-hidden" style={{ borderRadius: 28 }}>
        {/* Status bar */}
        <div className="absolute top-0 left-0 right-0 z-50 flex items-center justify-between px-5" style={{ height: 44 }}>
          <span className="text-white font-mono text-[14px] font-medium">9:41</span>
          <div className="absolute top-3 left-1/2 -translate-x-1/2 w-2.5 h-2.5 bg-black rounded-full" />
          <div className="flex items-center gap-1.5">
            <Wifi size={16} className="text-white" />
            <Battery size={16} className="text-white" />
          </div>
        </div>

        {/* Content */}
        <div className="absolute inset-0 pt-[44px] pb-[48px]">
          {children}
        </div>

        {/* Nav bar */}
        <div className="absolute bottom-0 left-0 right-0 z-50 flex items-center justify-center" style={{ height: 48 }}>
          <div className="w-[108px] h-[3px] bg-white/30 rounded-full" />
        </div>
      </div>
    </div>
  );
}
