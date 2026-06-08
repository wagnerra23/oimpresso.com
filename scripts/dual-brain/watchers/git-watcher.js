/**
 * Git watcher — poll periódico de novos commits no repo.
 * Lê HEAD e compara com last seen sha. Para cada commit novo, emite evento.
 */

import { execSync } from 'node:child_process';

export class GitWatcher {
  constructor({ repoPath, intervalMs = 30000, onCommit }) {
    this.repoPath  = repoPath;
    this.intervalMs = intervalMs;
    this.onCommit  = onCommit;
    this.lastSha   = null;
    this.timer     = null;
  }

  exec(cmd) {
    return execSync(cmd, { cwd: this.repoPath, encoding: 'utf-8' }).trim();
  }

  async start() {
    // Inicializa lastSha com HEAD atual — não dispara para histórico, só novos
    this.lastSha = this.exec('git rev-parse HEAD');
    console.log(`[git] watcher inicializado em ${this.repoPath} @ ${this.lastSha.slice(0, 8)}`);

    this.timer = setInterval(() => this.tick(), this.intervalMs);
  }

  stop() {
    if (this.timer) clearInterval(this.timer);
    this.timer = null;
  }

  tick() {
    try {
      const currentSha = this.exec('git rev-parse HEAD');
      if (currentSha === this.lastSha) return;

      // Lista commits entre lastSha e HEAD, do mais antigo ao mais novo
      const range = `${this.lastSha}..${currentSha}`;
      const log = this.exec(`git log --pretty=format:"%H|%s" ${range}`);
      const lines = log.split('\n').filter(Boolean).reverse();

      for (const line of lines) {
        const [sha, ...subjectParts] = line.split('|');
        const subject = subjectParts.join('|');
        const files   = this.exec(`git diff-tree --no-commit-id --name-only -r ${sha}`)
          .split('\n')
          .filter(Boolean);
        this.onCommit({ sha, subject, files });
      }
      this.lastSha = currentSha;
    } catch (e) {
      console.error('[git] tick falhou:', e.message);
    }
  }
}
