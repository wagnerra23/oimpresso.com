import { Wifi, Battery, Signal } from 'lucide-react';
import { cn } from '@/lib/utils';

interface IOSDeviceProps {
  children: React.ReactNode;
  className?: string;
}

export function IOSDevice({ children, className }: IOSDeviceProps) {
  return (
    <div
      className={cn(
        'relative flex-shrink-0 overflow-hidden',
        className
      )}
      style={{
        width: 393,
        height: 852,
        borderRadius: 54,
        border: '12px solid #1a1a1c',
        boxShadow: '0 0 0 1px #2a2a2c, 0 25px 80px rgba(0,0,0,0.6), 0 10px 30px rgba(0,0,0,0.4)',
        background: '#000',
      }}
    >
      {/* Inner screen */}
      <div className="absolute inset-0 overflow-hidden" style={{ borderRadius: 44 }}>
        {/* Status bar */}
        <div
          className="absolute top-0 left-0 right-0 z-50 flex items-start justify-between px-8 pt-3"
          style={{ height: 59 }}
        >
          <span className="text-white font-mono text-[15px] font-semibold mt-1">9:41</span>
          <div className="absolute top-0 left-1/2 -translate-x-1/2 w-[150px] h-[34px] bg-black rounded-b-[20px]" />
          <div className="flex items-center gap-1.5 mt-1 z-10">
            <Signal size={16} className="text-white" />
            <Wifi size={16} className="text-white" />
            <Battery size={16} className="text-white" />
          </div>
        </div>

        {/* Content */}
        <div className="absolute inset-0 pt-[59px] pb-[34px]">
          {children}
        </div>

        {/* Home indicator */}
        <div className="absolute bottom-2 left-1/2 -translate-x-1/2 z-50">
          <div className="w-[134px] h-[5px] bg-white/30 rounded-full" />
        </div>
      </div>
    </div>
  );
}
