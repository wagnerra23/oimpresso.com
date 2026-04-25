import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import SiteHeader from '@/Components/Site/SiteHeader';
import SiteFooter from '@/Components/Site/SiteFooter';

interface SiteLayoutProps {
  title?: string;
  children: ReactNode;
}

export default function SiteLayout({ title, children }: SiteLayoutProps) {
  return (
    <>
      {title ? <Head title={title} /> : <Head />}
      <div className="min-h-screen bg-background text-foreground antialiased">
        <SiteHeader />
        <main>{children}</main>
        <SiteFooter />
      </div>
    </>
  );
}
