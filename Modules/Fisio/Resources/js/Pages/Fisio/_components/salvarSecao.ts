// Envio da seção "Exames e receitas" (US-FISIO-063).
import { router } from '@inertiajs/react';
import { enviar as requisitar, errosDe } from '../Public/_components/portal';

// A seção mora dentro de <Deferred>: visita Inertia a desmontaria e perderia o que foi digitado e o
// erro (lápide §5 2026-10-13). Por isso o envio é JSON e só a prop da seção recarrega.
export async function mandar(metodo: 'POST' | 'PUT', url: string, corpo?: unknown, recarregar: string[] = ['documentos', 'medicamentos']): Promise<Record<string, string> | null> {
  try {
    const r = await requisitar(metodo, url, corpo);
    if (r.status >= 200 && r.status < 300) {
      router.reload({ only: recarregar });
      return null;
    }
    const e = errosDe(r.json);
    return Object.keys(e).length ? e : { geral: 'Não foi possível salvar. Tente de novo.' };
  } catch {
    return { geral: 'Sem conexão. Tente de novo.' };
  }
}
