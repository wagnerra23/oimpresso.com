// CRM-25 — a janela "Converter «lead» em cliente" do protótipo (`crm-blade.jsx` → `TelaLeads`, `converter`).
//
// O operador escolhe o estágio de vida que o contato fica depois da conversão e confirma em
// "Converter e abrir em Clientes". O estágio é gravado ANTES da conversão: a rota de estágio só
// alcança lead (UC-CRMLD-16), e depois da conversão o contato já é cliente.

import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { useCockpit } from '@/Hooks/useCockpit';
import { converterLead, moverEstagio } from '../../_components/lead';

/** Estágio sugerido: o que se chama "Cliente…", senão o atual do lead. */
function sugerido(estagios: { value: string; label: string }[], atual: string | null | undefined) {
  return estagios.find((e) => /^cliente/i.test(e.label))?.value ?? atual ?? null;
}

export default function ConverterLead({ lead, estagios, onFechar }: {
  lead: { id: number; nome: string; estagio_id?: string | null };
  estagios: { value: string; label: string }[];
  onFechar: () => void;
}) {
  const cockpit = useCockpit();
  const [estagio, setEstagio] = useState<string | null>(() => sugerido(estagios, lead.estagio_id));
  const [enviando, setEnviando] = useState(false);

  const confirmar = async () => {
    setEnviando(true);
    if (estagio && estagio !== lead.estagio_id && !(await moverEstagio(lead.id, estagio))) {
      toast.error('Não foi possível gravar o estágio. Nada foi convertido.');
      setEnviando(false);
      return;
    }
    if (!(await converterLead(lead.id))) {
      toast.error('Não foi possível converter o lead.');
      setEnviando(false);
      return;
    }
    toast.success(`${lead.nome} convertido em cliente — abrindo o cadastro.`);
    window.location.assign(`/contacts/${lead.id}`);
  };

  return (
    <Dialog open onOpenChange={(aberto) => { if (!aberto && !enviando) onFechar(); }}>
      <DialogContent container={cockpit} data-contract="crm-leads-converter" className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Converter {lead.nome} em cliente</DialogTitle>
          <DialogDescription>
            O contato deixa de ser lead e passa a cliente. Escolha em que estágio de vida ele fica depois da conversão.
          </DialogDescription>
        </DialogHeader>
        <div role="group" aria-label="Estágio depois da conversão" className="flex flex-wrap gap-2">
          {estagios.map((e) => (
            <Button key={e.value} type="button" size="sm" variant={estagio === e.value ? 'default' : 'outline'}
              aria-pressed={estagio === e.value} className={estagio === e.value ? 'text-white' : undefined}
              onClick={() => setEstagio(e.value)}>{e.label}</Button>
          ))}
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onFechar} disabled={enviando}>Cancelar</Button>
          <Button className="text-white" onClick={confirmar} disabled={enviando}>Converter e abrir em Clientes</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
