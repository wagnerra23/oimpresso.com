/**
 * Log watcher — poll do laravel.log, captura linhas com nível ERROR/CRITICAL.
 * Mantém offset persistente em memória; ao restart, começa do EOF (não reprocessa histórico).
 */

import { existsSync, statSync, openSync, readSync, closeSync } from 'node:fs';

const ERROR_RE = /\[(ERROR|CRITICAL|EMERGENCY|ALERT)\]/;

export class LogWatcher {
  constructor({ logPath, intervalMs = 5000, onError }) {
    this.logPath  = logPath;
    this.intervalMs = intervalMs;
    this.onError  = onError;
    this.offset   = 0;
    this.timer    = null;
  }

  start() {
    if (!existsSync(this.logPath)) {
      console.warn(`[log] arquivo não existe: ${this.logPath} (vai criar quando Laravel logar)`);
      this.offset = 0;
    } else {
      this.offset = statSync(this.logPath).size;
      console.log(`[log] watcher inicializado @ offset ${this.offset}`);
    }
    this.timer = setInterval(() => this.tick(), this.intervalMs);
  }

  stop() {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
  }

  tick() {
    try {
      if (!existsSync(this.logPath)) return;
      const size = statSync(this.logPath).size;

      // Truncação detectada (rotação) — reseta offset
      if (size < this.offset) this.offset = 0;
      if (size === this.offset) return;

      const fd = openSync(this.logPath, 'r');
      const length = size - this.offset;
      const buffer = Buffer.alloc(length);
      readSync(fd, buffer, 0, length, this.offset);
      closeSync(fd);

      const text = buffer.toString('utf-8');
      const lines = text.split('\n');
      for (const line of lines) {
        if (ERROR_RE.test(line)) {
          this.onError({ line: line.slice(0, 500) });
        }
      }
      this.offset = size;
    } catch (e) {
      console.error('[log] tick falhou:', e.message);
    }
  }
}
