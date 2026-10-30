// Chamadas da consulta de clientes gravando (UC-V393/V394). Os endpoints são os do Create:
// a busca é a do `CustomerSearchAutocomplete` e o cadastro é o do `QuickAddCustomerSheet`
// (POST /contacts, ContactController@store). Nenhuma rota nova — o SellsV3Controller segue
// sem escrita (UC-V302). Cópia local de propósito: a V3 não importa `Sells/_components` (UC-V303).

import { clienteDaBusca, corpoNovoCliente, type ClienteDaBusca, type ClienteDaGravacao } from './cliente-gravacao-dominio';

const CABECALHOS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

/** Busca os clientes do business da sessão (o endpoint filtra por business_id). */
export async function buscarClientes(termo: string, signal?: AbortSignal): Promise<ClienteDaGravacao[]> {
  const res = await fetch(`/contacts/customers?${new URLSearchParams({ q: termo })}`, {
    headers: CABECALHOS,
    credentials: 'same-origin',
    signal,
  });
  if (!res.ok) return [];
  const dados = await res.json();
  const lista: ClienteDaBusca[] = Array.isArray(dados) ? dados : (dados?.data ?? []);
  return lista.slice(0, 20).map(clienteDaBusca);
}

/** Cadastra o cliente mínimo e o devolve pronto para a venda; erro vira mensagem. */
export async function criarCliente(dados: { nome: string; doc?: string; fone?: string }): Promise<ClienteDaGravacao> {
  const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
  const res = await fetch('/contacts', {
    method: 'POST',
    headers: { ...CABECALHOS, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
    credentials: 'same-origin',
    body: JSON.stringify(corpoNovoCliente(dados)),
  });
  const corpo = await res.json().catch(() => ({}));
  const criado = corpo?.data ?? corpo;
  if (!res.ok || !Number(criado?.id)) {
    const primeiro = corpo?.errors ? Object.values(corpo.errors as Record<string, string[]>)[0]?.[0] : null;
    throw new Error(primeiro ?? corpo?.msg ?? corpo?.message ?? 'Não foi possível cadastrar o cliente.');
  }
  return clienteDaBusca({ id: Number(criado.id), text: String(criado.name ?? dados.nome), mobile: criado.mobile ?? null });
}
