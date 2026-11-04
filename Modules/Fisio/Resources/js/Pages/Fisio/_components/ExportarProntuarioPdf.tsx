// Botões de exportação da ficha do paciente.
// "Exportar prontuário (PDF)" (UC-FPS-19): a história inteira, para a guarda.
// "Laudo de evolução (PDF)" (UC-FLD-01): síntese de uma página para médico ou convênio
// (protótipo prototipo-ui/cowork/Wagner/prototipos/fisio/Laudo.dc.html).
// Os dois PDFs são gerados no servidor, que registra cada exportação no log de auditoria
// (UC-FPS-20, UC-FLD-02). Link comum: o navegador baixa o arquivo.
import { FileDown, FileText } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export default function ExportarProntuarioPdf({ pacienteId }: { pacienteId: number }) {
  return (
    <>
      <Button variant="outline" asChild>
        <a href={`/fisio/pacientes/${pacienteId}/prontuario.pdf`} data-contract="fisio-exportar-prontuario">
          <FileDown className="size-4" aria-hidden="true" /> Exportar prontuário (PDF)
        </a>
      </Button>
      <Button variant="outline" asChild>
        <a href={`/fisio/pacientes/${pacienteId}/laudo.pdf`} data-contract="fisio-exportar-laudo">
          <FileText className="size-4" aria-hidden="true" /> Laudo de evolução (PDF)
        </a>
      </Button>
    </>
  );
}
