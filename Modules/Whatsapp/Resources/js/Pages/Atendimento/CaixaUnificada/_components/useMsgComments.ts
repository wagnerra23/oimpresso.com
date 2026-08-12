// useMsgComments — notas internas por-mensagem (port inbox-cur `useMsgComments`).
// localStorage per-user (SEM DB — mesmo anti-hook de useInboxFavs; charter §Anti-hooks:
// "favoritos localStorage SEM DB"). Escopo: { [convId]: { [msgIdx]: MsgComment[] } }.
// DB compartilhado entre a equipe = US futura (decisão registrada no charter 2026-06-18).

import { useEffect, useState } from 'react';

export interface MsgComment {
  author: string;
  text: string;
  when: string;
}

const LS_KEY = 'oimpresso.inbox.msgComments';
type Store = Record<string, Record<string, MsgComment[]>>;

export function useMsgComments(convId: number | null) {
  const [store, setStore] = useState<Store>(() => {
    try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}'); } catch { return {}; }
  });
  useEffect(() => {
    try { localStorage.setItem(LS_KEY, JSON.stringify(store)); } catch { /* quota/private mode */ }
  }, [store]);

  const key = convId == null ? null : String(convId);

  const forMsg = (msgIdx: number): MsgComment[] => (key ? store[key]?.[String(msgIdx)] ?? [] : []);

  const add = (msgIdx: number, text: string) => {
    if (!key) return;
    const when = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    setStore(prev => {
      const conv = prev[key] ?? {};
      const list = conv[String(msgIdx)] ?? [];
      return { ...prev, [key]: { ...conv, [String(msgIdx)]: [...list, { author: 'você', text, when }] } };
    });
  };

  const remove = (msgIdx: number, i: number) => {
    if (!key) return;
    setStore(prev => {
      const conv = { ...(prev[key] ?? {}) };
      const list = (conv[String(msgIdx)] ?? []).filter((_, idx) => idx !== i);
      if (list.length) conv[String(msgIdx)] = list; else delete conv[String(msgIdx)];
      return { ...prev, [key]: conv };
    });
  };

  return { forMsg, add, remove };
}
