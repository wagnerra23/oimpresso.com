// NaturezaLancamentoSelect — escolha da natureza de operação ao emitir a NF-e (onda 5b).
//
// Lista as naturezas criadas na tela de Tributação (GET /nfe-brasil/tributacao/naturezas/lancamento)
// que servem para uma NF-e de saída normal. O endpoint responde 404 com a flag
// useV2NfeNaturezas desligada e 403 sem a permissão de emitir: nos dois casos a lista fica
// vazia e o seletor não aparece, e a emissão segue como antes (sem operacao_id).
// RUNBOOK-tributacao §Naturezas, onda 5.
import { useEffect, useState } from 'react';

export interface NaturezaLancamento {
  id: number;
  nome: string;
  cfop: string | null;
  cfop_interestadual: string | null;
}

/** Sentinela da opção padrão: o Radix Select não aceita value vazio. */
export const NATUREZA_PADRAO = '__padrao__';

export function useNaturezasLancamento(enabled = true): NaturezaLancamento[] {
  const [naturezas, setNaturezas] = useState<NaturezaLancamento[]>([]);

  useEffect(() => {
    if (!enabled) return;
    let vivo = true;
    fetch('/nfe-brasil/tributacao/naturezas/lancamento', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((res) => (res.ok ? res.json() : { naturezas: [] }))
      .then((json) => {
        if (vivo) setNaturezas(Array.isArray(json?.naturezas) ? json.naturezas : []);
      })
      .catch(() => {
        if (vivo) setNaturezas([]);
      });
    return () => {
      vivo = false;
    };
  }, [enabled]);

  return naturezas;
}

/** operacao_id a mandar no POST de emissão; null = natureza padrão (venda). */
export function operacaoIdDaEscolha(valor: string): number | null {
  const id = Number(valor);
  return valor !== NATUREZA_PADRAO && Number.isInteger(id) && id > 0 ? id : null;
}
