// Botão "Exportar prontuário (PDF)" da ficha do paciente (UC-FPS-19).
// O PDF é gerado no servidor (rota fisio.pacientes.prontuario.pdf), que também registra a
// exportação no log de auditoria (UC-FPS-20). Link comum: o navegador baixa o arquivo.
import { FileDown } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export default function ExportarProntuarioPdf({ pacienteId }: { pacienteId: number }) {
  return (
    <Button variant="outline" asChild>
      <a href={`/fisio/pacientes/${pacienteId}/prontuario.pdf`} data-contract="fisio-exportar-prontuario">
        <FileDown className="size-4" aria-hidden="true" /> Exportar prontuário (PDF)
      </a>
    </Button>
  );
}
