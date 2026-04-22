import { Head } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { toast } from 'sonner';
import { useFlash } from '@/Hooks/usePageProps';

interface AppLayoutProps {
  title?: string;
  children: ReactNode;
}

export default function AppLayout({ title, children }: AppLayoutProps) {
  const flash = useFlash();

  useEffect(() => {
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.info) toast.info(flash.info);
  }, [flash.success, flash.error, flash.info]);

  return (
    <>
      {title ? <Head title={title} /> : <Head />}
      <div className="min-h-screen bg-background text-foreground">
        {children}
      </div>
    </>
  );
}
