import { Link } from '@inertiajs/react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

// Saúde fiscal — playbook Fiscal thread 14 (UC-NFTR-24/25). Forma: aba "Saúde fiscal" do
// protótipo `fiscal-tributacao.jsx` (card data-contract="saude-fiscal"). O que bloqueia vem do
// servidor (`SaudeFiscalService`): a tela não decide severidade nem conta nada sozinha.

export interface PendenciaFiscal {
  tipo: string;
  bloqueante: boolean;
  titulo: string;
  contagem: number;
  ids: number[];
  url: string | null;
}

export interface SaudePayload {
  pronto: boolean;
  bloqueantes: number;
  itens: PendenciaFiscal[];
}

const ACAO: Record<string, string> = {
  ncm_padrao_invalido: 'Escolher NCM padrão',
  certificado_invalido: 'Enviar certificado',
  certificado_vencendo: 'Enviar novo certificado',
  emitido_com_ncm_padrao: 'Revisar produtos',
  produto_sem_ncm: 'Revisar produtos',
};

function detalhe(p: PendenciaFiscal): string | null {
  if (p.tipo === 'produto_sem_ncm' || p.tipo === 'emitido_com_ncm_padrao') {
    return `${p.contagem} produto(s). A nota sai com o NCM padrão da empresa no lugar.`;
  }
  return null;
}

export default function SaudeFiscal({ saude }: { saude: SaudePayload }) {
  const { itens, pronto, bloqueantes } = saude;

  return (
    <Card data-contract="saude-fiscal" aria-label="Pendências que viram rejeição">
      <CardHeader className="pb-3">
        <CardTitle className="text-base flex flex-wrap items-center justify-between gap-2">
          <span>Vai virar rejeição se ninguém mexer · {itens.length}</span>
          {pronto ? (
            <Badge variant="success">Pronto pra emitir</Badge>
          ) : (
            <Badge variant="danger">Não pronto · {bloqueantes} bloqueante(s)</Badge>
          )}
        </CardTitle>
        <p className="text-xs text-muted-foreground font-mono">checado ao abrir a tela</p>
      </CardHeader>
      <CardContent>
        {itens.length === 0 ? (
          <div className="text-sm">
            <p className="font-medium">Nada pendente</p>
            <p className="text-muted-foreground">Cadastro e regras prontos pra emitir sem rejeição conhecida.</p>
          </div>
        ) : (
          <ul className="divide-y divide-border">
            {itens.map((p) => (
              <li key={p.tipo} data-sev={p.bloqueante ? 'bad' : 'warn'} className="flex items-start gap-3 py-3">
                <span
                  aria-hidden="true"
                  className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${p.bloqueante ? 'bg-destructive' : 'bg-warning'}`}
                />
                <div className="min-w-0 flex-1 text-sm">
                  <p className="font-medium">
                    <span className="sr-only">{p.bloqueante ? 'Bloqueia a emissão: ' : 'Aviso: '}</span>
                    {p.titulo}
                  </p>
                  {detalhe(p) && <p className="text-muted-foreground">{detalhe(p)}</p>}
                </div>
                {p.url && (
                  <Button asChild size="sm" variant={p.bloqueante ? 'default' : 'outline'}>
                    <Link href={p.url}>{ACAO[p.tipo] ?? 'Resolver'}</Link>
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}
