// Roteiro padrão da OS da gráfica (US-COMVIS-003 · decisão [W] 2026-10-26).
// Toda OS que nasce do orçamento recebe as etapas deste roteiro e entra no quadro do PCP.
// Fala com GET/PUT /comunicacao-visual/api/roteiro-padrao (só quem cuida do catálogo).
import { useEffect, useState } from 'react';
import { Route } from 'lucide-react';

import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Inline, Stack } from '@/Components/layout';

interface Roteiro {
  id: number;
  nome: string;
  familia: string;
}

const NENHUM = '__nenhum__';

async function api(method: 'GET' | 'PUT', body?: unknown): Promise<{ roteiro_id: number | null; roteiros?: Roteiro[] }> {
  const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
  const res = await fetch('/comunicacao-visual/api/roteiro-padrao', {
    method,
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    throw new Error((data && data.message) || `Não consegui falar com o servidor agora (erro ${res.status}).`);
  }
  return data;
}

export function RoteiroPadraoCard() {
  const [roteiros, setRoteiros] = useState<Roteiro[] | null>(null);
  const [atual, setAtual] = useState<string>(NENHUM);
  const [escolhido, setEscolhido] = useState<string>(NENHUM);
  const [salvando, setSalvando] = useState(false);
  const [msg, setMsg] = useState<{ tipo: 'ok' | 'erro'; texto: string } | null>(null);

  useEffect(() => {
    api('GET')
      .then((r) => {
        const v = r.roteiro_id ? String(r.roteiro_id) : NENHUM;
        setRoteiros(r.roteiros ?? []);
        setAtual(v);
        setEscolhido(v);
      })
      .catch((e: Error) => {
        setRoteiros([]);
        setMsg({ tipo: 'erro', texto: e.message });
      });
  }, []);

  async function salvar() {
    setSalvando(true);
    setMsg(null);
    try {
      const r = await api('PUT', { roteiro_id: escolhido === NENHUM ? null : Number(escolhido) });
      const v = r.roteiro_id ? String(r.roteiro_id) : NENHUM;
      setAtual(v);
      setEscolhido(v);
      setMsg({ tipo: 'ok', texto: v === NENHUM ? 'As OS novas nascem sem etapas.' : 'As OS novas já nascem com este roteiro.' });
    } catch (e) {
      setMsg({ tipo: 'erro', texto: e instanceof Error ? e.message : 'Falha inesperada ao salvar.' });
    } finally {
      setSalvando(false);
    }
  }

  return (
    <Card data-contract="roteiro-padrao">
      <CardHeader>
        <CardTitle className="text-sm font-medium">
          <Inline asChild>
            <span>
              <Route className="h-4 w-4 text-primary" aria-hidden="true" />
              Roteiro da produção
            </span>
          </Inline>
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        <p className="text-sm text-muted-foreground">
          Toda OS que nasce de um orçamento aprovado recebe as etapas deste roteiro e aparece no quadro da produção.
        </p>
        {roteiros === null ? (
          <p className="text-sm text-muted-foreground">Carregando roteiros…</p>
        ) : roteiros.length === 0 && !msg ? (
          <p className="text-sm text-muted-foreground">
            Nenhum roteiro ativo. Cadastre um na tela de Roteiros da produção.
          </p>
        ) : (
          <Stack gap={2} className="sm:flex-row sm:items-end">
            <div className="flex-1 space-y-1">
              <Label htmlFor="roteiro-padrao">Roteiro padrão</Label>
              <Select value={escolhido} onValueChange={setEscolhido}>
                <SelectTrigger id="roteiro-padrao">
                  <SelectValue placeholder="Escolha um roteiro" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NENHUM}>Nenhum (OS nasce sem etapas)</SelectItem>
                  {roteiros.map((r) => (
                    <SafeSelectItem key={r.id} value={String(r.id)}>
                      {r.nome} · {r.familia}
                    </SafeSelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Button type="button" onClick={salvar} disabled={salvando || escolhido === atual}>
              {salvando ? 'Salvando…' : 'Salvar roteiro'}
            </Button>
          </Stack>
        )}
        {msg && (
          <p role="status" className={msg.tipo === 'erro' ? 'text-sm text-destructive' : 'text-sm text-muted-foreground'}>
            {msg.texto}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
