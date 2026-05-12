import { EventEmitter } from 'node:events';
import { rm } from 'node:fs/promises';
import QRCode from 'qrcode';
import makeWASocket, {
  Browsers,
  fetchLatestBaileysVersion,
  type WASocket,
  type proto,
} from '@whiskeysockets/baileys';
import type { Logger } from 'pino';
import type { Env } from '../config/env';
import {
  banDetectedCounter,
  messageLagHistogram,
  recvCounter,
  sendCounter,
  sessionAgeGauge,
  sessionStateGauge,
} from '../observability/metrics';
import type { WebhookDispatcher } from '../webhook/WebhookDispatcher';
import { analyzeDisconnect } from './banDetector';
import { instanceDir, loadAuthState } from './authState';

export type InstanceState = 'idle' | 'connecting' | 'qr_required' | 'connected' | 'disconnected' | 'banned';

export interface InstanceMeta {
  instance_id: string;
  business_uuid: string;
  business_id?: number;
}

export interface InstanceSnapshot {
  instance_id: string;
  business_uuid: string;
  state: InstanceState;
  display_phone: string | null;
  last_seen: string | null;
  session_age_seconds: number | null;
  qr: string | null;
  ban_reason: string | null;
}

export interface SendTextInput {
  to: string;
  text: string;
}

export interface SendMediaInput {
  to: string;
  media_url: string;
  type: 'image' | 'document' | 'audio' | 'video';
  caption?: string;
  filename?: string;
  mimetype?: string;
}

export interface SendResult {
  message_id: string;
  status: 'sent' | 'queued';
}

// US-WA-080 — import histórico Baileys
export interface FetchHistoryInput {
  jid: string;
  count: number;
  before_id: string;
  before_ts: number;
  from_me?: boolean;
  timeout_ms?: number;
}

export interface FetchHistoryMessage {
  key: {
    remoteJid?: string | null;
    fromMe?: boolean | null;
    id?: string | null;
    participant?: string | null;
  };
  message: unknown;
  push_name: string | null;
  timestamp: number | null;
}

export interface FetchHistoryResult {
  count: number;
  has_more: boolean;
  oldest_id: string | null;
  oldest_ts: number | null;
  messages: FetchHistoryMessage[];
  // Sentinel quando WhatsApp não devolve nada dentro do timeout — caller
  // sabe que precisa parar a paginação.
  empty: boolean;
}

export class Instance extends EventEmitter {
  private socket: WASocket | null = null;
  private state: InstanceState = 'idle';
  private currentQr: string | null = null;
  private displayPhone: string | null = null;
  private lastSeen: Date | null = null;
  private connectedAt: Date | null = null;
  private banReason: string | null = null;
  private reconnectTimer: NodeJS.Timeout | null = null;

  constructor(
    public readonly meta: InstanceMeta,
    private readonly env: Env,
    private readonly logger: Logger,
    private readonly webhook: WebhookDispatcher,
  ) {
    super();
  }

  snapshot(): InstanceSnapshot {
    return {
      instance_id: this.meta.instance_id,
      business_uuid: this.meta.business_uuid,
      state: this.state,
      display_phone: this.displayPhone,
      last_seen: this.lastSeen?.toISOString() ?? null,
      session_age_seconds: this.connectedAt
        ? Math.floor((Date.now() - this.connectedAt.getTime()) / 1000)
        : null,
      qr: this.state === 'qr_required' ? this.currentQr : null,
      ban_reason: this.banReason,
    };
  }

  async connect(): Promise<void> {
    if (this.state === 'connecting' || this.state === 'connected') return;
    this.setState('connecting');
    this.banReason = null;

    const auth = await loadAuthState(this.env.SESSIONS_DIR, this.meta.instance_id);
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
      version,
      auth: auth.state,
      logger: this.logger.child({ scope: 'baileys', instance_id: this.meta.instance_id }) as never,
      browser: Browsers.appropriate('Chrome'),
      printQRInTerminal: false,
      syncFullHistory: false,
      markOnlineOnConnect: false,
      generateHighQualityLinkPreview: false,
      connectTimeoutMs: this.env.INSTANCE_CONNECT_TIMEOUT_MS,
      defaultQueryTimeoutMs: 30_000,
      retryRequestDelayMs: 500,
    });
    this.socket = sock;

    sock.ev.on('creds.update', auth.saveCreds);

    sock.ev.on('connection.update', (update) => {
      void this.handleConnectionUpdate(update);
    });

    sock.ev.on('messages.upsert', (event) => {
      if (event.type !== 'notify') return;
      for (const msg of event.messages) {
        this.handleIncomingMessage(msg);
      }
    });

    sock.ev.on('messages.update', (updates) => {
      for (const u of updates) {
        void this.webhook.dispatch({
          instance_id: this.meta.instance_id,
          business_uuid: this.meta.business_uuid,
          event: 'message_status',
          data: { key: u.key, update: u.update },
        });
      }
    });
  }

  async disconnect(): Promise<void> {
    this.clearReconnect();
    if (this.socket) {
      try {
        await this.socket.logout();
      } catch {
        try {
          this.socket.end(undefined);
        } catch {
          // socket já encerrado
        }
      }
      this.socket = null;
    }
    this.setState('disconnected');
    sessionStateGauge.set({ instance_id: this.meta.instance_id, business_id: String(this.meta.business_id ?? '') }, 0);
    void this.webhook.dispatch({
      instance_id: this.meta.instance_id,
      business_uuid: this.meta.business_uuid,
      event: 'disconnected',
      data: { reason: 'manual' },
    });
  }

  async purgeSession(): Promise<void> {
    await this.disconnect();
    const dir = instanceDir(this.env.SESSIONS_DIR, this.meta.instance_id);
    await rm(dir, { recursive: true, force: true });
  }

  async sendText(input: SendTextInput): Promise<SendResult> {
    const sock = this.requireConnected();
    const jid = normalizeJid(input.to);
    const start = Date.now();
    const sent = await sock.sendMessage(jid, { text: input.text });
    messageLagHistogram.observe({ instance_id: this.meta.instance_id }, Date.now() - start);
    sendCounter.inc({ instance_id: this.meta.instance_id, status: 'sent', kind: 'text' });
    return { message_id: sent?.key.id ?? '', status: 'sent' };
  }

  async sendMedia(input: SendMediaInput): Promise<SendResult> {
    const sock = this.requireConnected();
    const jid = normalizeJid(input.to);
    const url = { url: input.media_url };
    const optionalCaption = input.caption !== undefined ? { caption: input.caption } : {};
    const optionalFilename = input.filename !== undefined ? { fileName: input.filename } : {};

    let content;
    if (input.type === 'image') {
      content = { image: url, ...optionalCaption };
    } else if (input.type === 'video') {
      content = { video: url, ...optionalCaption };
    } else if (input.type === 'audio') {
      content = {
        audio: url,
        mimetype: input.mimetype ?? 'audio/ogg; codecs=opus',
        ptt: false,
      };
    } else {
      // document — Baileys exige mimetype obrigatório
      content = {
        document: url,
        mimetype: input.mimetype ?? 'application/octet-stream',
        ...optionalCaption,
        ...optionalFilename,
      };
    }

    const start = Date.now();
    const sent = await sock.sendMessage(jid, content);
    messageLagHistogram.observe({ instance_id: this.meta.instance_id }, Date.now() - start);
    sendCounter.inc({ instance_id: this.meta.instance_id, status: 'sent', kind: input.type });
    return { message_id: sent?.key.id ?? '', status: 'sent' };
  }

  /**
   * Puxa batch histórico de mensagens de uma conversa (US-WA-080).
   *
   * Baileys 6.7.9 API: `socket.fetchMessageHistory(count, oldestMsgKey, oldestMsgTs)`
   * envia um Peer Data Operation Request (HISTORY_SYNC_ON_DEMAND) ao
   * WhatsApp e retorna um request ID (string). A resposta chega async
   * pelo evento `messaging-history.set` (mesmo evento do pairing inicial).
   *
   * O caller PHP gerencia cursor — sempre passa `before_id` + `before_ts`
   * da mensagem mais antiga já conhecida. Daemon não cacheia msgs entre
   * chamadas.
   *
   * Retorna `empty=true` se WhatsApp não devolveu nada dentro do timeout
   * (caller sabe que chegou no início da história ou perdeu pacote).
   *
   * Anti-ban: caller deve fazer sleep 1-2s entre chamadas. Daemon não
   * impõe rate limit aqui pra deixar caller controlar.
   */
  async fetchHistory(input: FetchHistoryInput): Promise<FetchHistoryResult> {
    const sock = this.requireConnected();
    const timeoutMs = input.timeout_ms ?? 60_000;

    const oldestKey: proto.IMessageKey = {
      remoteJid: input.jid,
      id: input.before_id,
      fromMe: input.from_me ?? false,
    };

    // Promise wrapper — captura o evento `messaging-history.set` que
    // chega depois do PDO request. Filtra mensagens do `jid` alvo pra
    // evitar misturar com sync passivo de outras conversas.
    const collected = new Promise<proto.IWebMessageInfo[]>((resolve) => {
      const buffer: proto.IWebMessageInfo[] = [];

      const handler = (payload: {
        messages: proto.IWebMessageInfo[];
        peerDataRequestSessionId?: string | null;
      }): void => {
        const matched = payload.messages.filter(
          (m) => m.key?.remoteJid === input.jid,
        );
        if (matched.length === 0) return;
        buffer.push(...matched);
        // Pode chegar em chunks — aguarda timeout pra acumular tudo
        // antes de resolver. Não dá pra confiar em `isLatest` aqui pq
        // o evento vem com syncType ON_DEMAND mas Baileys não marca
        // `isLatest` consistentemente nessa request.
      };

      sock.ev.on('messaging-history.set', handler);

      const finalize = (): void => {
        sock.ev.off('messaging-history.set', handler);
        resolve(buffer);
      };

      setTimeout(finalize, timeoutMs);
    });

    // Dispara o request — retorna request ID (string), não os dados.
    await sock.fetchMessageHistory(input.count, oldestKey, input.before_ts);

    const raw = await collected;

    // Dedupe + ordenação por timestamp DESC (mais recente primeiro,
    // alinhado com o que o Baileys já faz internamente — "reverse
    // chronologically sorted").
    const dedup = new Map<string, proto.IWebMessageInfo>();
    for (const m of raw) {
      const key = m.key?.id ?? '';
      if (key && !dedup.has(key)) dedup.set(key, m);
    }
    const sorted = Array.from(dedup.values()).sort((a, b) => {
      const ta = Number(a.messageTimestamp ?? 0);
      const tb = Number(b.messageTimestamp ?? 0);
      return tb - ta;
    });

    const out = sorted.slice(0, input.count).map((m) => ({
      key: {
        remoteJid: m.key?.remoteJid ?? null,
        fromMe: m.key?.fromMe ?? null,
        id: m.key?.id ?? null,
        participant: m.key?.participant ?? null,
      },
      message: m.message,
      push_name: m.pushName ?? null,
      timestamp: m.messageTimestamp ? Number(m.messageTimestamp) : null,
    }));

    const oldest = out[out.length - 1];

    return {
      count: out.length,
      // `has_more` heurística: se devolveu o batch completo (==count),
      // assume que tem mais. Se devolveu menos, provável chegou no
      // início da história. Caller pode forçar mais 1 chamada pra
      // confirmar (e receberá empty=true).
      has_more: out.length >= input.count,
      oldest_id: oldest?.key.id ?? null,
      oldest_ts: oldest?.timestamp ?? null,
      messages: out,
      empty: out.length === 0,
    };
  }

  // ---------- internals ----------

  private requireConnected(): WASocket {
    if (this.state !== 'connected' || !this.socket) {
      const err = new Error(`Instance ${this.meta.instance_id} not connected (state=${this.state})`);
      sendCounter.inc({ instance_id: this.meta.instance_id, status: 'failed', kind: 'precondition' });
      throw err;
    }
    return this.socket;
  }

  private async handleConnectionUpdate(update: {
    connection?: 'open' | 'close' | 'connecting';
    lastDisconnect?: { error?: unknown };
    qr?: string;
  }): Promise<void> {
    if (update.qr) {
      this.currentQr = await QRCode.toDataURL(update.qr, { width: 320, margin: 1 });
      this.setState('qr_required');
      sessionStateGauge.set(
        { instance_id: this.meta.instance_id, business_id: String(this.meta.business_id ?? '') },
        0.5,
      );
      void this.webhook.dispatch({
        instance_id: this.meta.instance_id,
        business_uuid: this.meta.business_uuid,
        event: 'qr_updated',
        data: {},
      });
    }

    if (update.connection === 'open') {
      this.connectedAt = new Date();
      this.lastSeen = new Date();
      this.currentQr = null;
      const me = this.socket?.user;
      this.displayPhone = me?.id ? me.id.split(':')[0] ?? null : null;
      this.setState('connected');
      sessionStateGauge.set(
        { instance_id: this.meta.instance_id, business_id: String(this.meta.business_id ?? '') },
        1,
      );
      void this.webhook.dispatch({
        instance_id: this.meta.instance_id,
        business_uuid: this.meta.business_uuid,
        event: 'connected',
        data: { display_phone: this.displayPhone },
      });
    }

    if (update.connection === 'close') {
      const analysis = analyzeDisconnect(update.lastDisconnect?.error);
      sessionStateGauge.set(
        { instance_id: this.meta.instance_id, business_id: String(this.meta.business_id ?? '') },
        0,
      );

      if (analysis.banned) {
        this.banReason = analysis.reason;
        this.setState('banned');
        banDetectedCounter.inc({ instance_id: this.meta.instance_id });
        void this.webhook.dispatch({
          instance_id: this.meta.instance_id,
          business_uuid: this.meta.business_uuid,
          event: 'ban_detected',
          data: { reason: analysis.reason },
        });
        return;
      }

      this.setState('disconnected');
      void this.webhook.dispatch({
        instance_id: this.meta.instance_id,
        business_uuid: this.meta.business_uuid,
        event: 'session_lost',
        data: { reason: analysis.reason, will_reconnect: analysis.shouldReconnect },
      });

      if (analysis.shouldReconnect) {
        this.scheduleReconnect();
      }
    }
  }

  private handleIncomingMessage(msg: proto.IWebMessageInfo): void {
    if (!msg.message || msg.key.fromMe) return;
    this.lastSeen = new Date();
    recvCounter.inc({ instance_id: this.meta.instance_id });
    void this.webhook.dispatch({
      instance_id: this.meta.instance_id,
      business_uuid: this.meta.business_uuid,
      event: 'message',
      data: {
        key: msg.key,
        message: msg.message,
        push_name: msg.pushName,
        timestamp: msg.messageTimestamp,
      },
    });
  }

  private setState(next: InstanceState): void {
    this.state = next;
    if (this.connectedAt) {
      sessionAgeGauge.set(
        { instance_id: this.meta.instance_id },
        Math.floor((Date.now() - this.connectedAt.getTime()) / 1000),
      );
    }
    this.emit('state', next);
  }

  private scheduleReconnect(): void {
    this.clearReconnect();
    this.reconnectTimer = setTimeout(() => {
      this.connect().catch((err) =>
        this.logger.error({ err, instance_id: this.meta.instance_id }, 'reconnect failed'),
      );
    }, 5_000);
  }

  private clearReconnect(): void {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer);
      this.reconnectTimer = null;
    }
  }
}

function normalizeJid(input: string): string {
  if (input.includes('@')) return input;
  const digits = input.replace(/\D+/g, '');
  return `${digits}@s.whatsapp.net`;
}
