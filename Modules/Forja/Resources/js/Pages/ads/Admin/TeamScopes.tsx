// @ads
//   tela: /ads/admin/team-scopes
//   adrs: governance per-user × module (caso Maiara)

import React, { useState, type ReactNode } from 'react'
import AppShellV2 from '@/Layouts/AppShellV2'
import { Deferred, router } from '@inertiajs/react'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Switch } from '@/Components/ui/switch'
import { Skeleton } from '@/Components/ui/skeleton'
import PageHeader from '@/Components/shared/PageHeader'
import KpiGrid from '@/Components/shared/KpiGrid'
import KpiCard from '@/Components/shared/KpiCard'
import { ShieldCheck, ShieldAlert, Eye, Edit3, Play, GitCommit } from 'lucide-react'

interface ModuleAccess {
  module: string
  can_read: boolean
  can_write: boolean
  can_execute_tools: boolean
  can_commit: boolean
  expires_at: string | null
}

interface User {
  id: number
  name: string
  email: string
  modules: ModuleAccess[]
  modules_count: number
}

interface Props {
  // users e modules vêm via Inertia::defer — undefined no first render até o async fetch resolver
  users?: User[]
  modules?: string[]
}

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v)

const TeamScopes: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = ({ users, modules }) => {
  // Guardas defensivas: props deferidas são undefined no first render.
  const us = users ?? []
  const mods = modules ?? []
  const [selectedUser, setSelectedUser] = useState<User | null>(us[0] ?? null)

  const totalGrants = us.reduce((acc, u) => acc + u.modules_count, 0)
  const usersWithAccess = us.filter(u => u.modules_count > 0).length

  const grant = (userId: number, module: string, perms: Partial<ModuleAccess>) => {
    router.post('/ads/admin/team-scopes/grant', {
      user_id: userId,
      module,
      can_read: perms.can_read ?? true,
      can_write: perms.can_write ?? false,
      can_execute_tools: perms.can_execute_tools ?? false,
      can_commit: perms.can_commit ?? false,
    }, { preserveScroll: true, preserveState: false })
  }

  const revoke = (userId: number, module: string) => {
    if (! confirm(`Revogar todo acesso ao módulo ${module}?`)) return
    router.post('/ads/admin/team-scopes/revoke', { user_id: userId, module }, { preserveScroll: true, preserveState: false })
  }

  const userAccess = (user: User, module: string): ModuleAccess | null => {
    return user.modules.find(m => m.module === module) ?? null
  }

  return (
    <div className="mx-auto max-w-7xl p-6 space-y-4">
      <PageHeader
        icon="shield-check"
        title="ADS — Team Scopes"
        description="Controle granular de quem pode tocar quais módulos. Camada extra acima do PolicyEngine — Wagner define por usuário. WriteFileTool consulta antes de escrever; sem entrada aqui = DENY default."
      />

      <KpiGrid cols={3}>
        <KpiCard icon="users"        tone="info"    label="Devs do business" value={num(us.length)}           description="Total de usuários" />
        <KpiCard icon="shield-check" tone="success" label="Com acesso ativo"  value={num(usersWithAccess)}     description="Pelo menos 1 módulo" />
        <KpiCard icon="layers"       tone="default" label="Total de grants"   value={num(totalGrants)}         description="Pares (user × module)" />
      </KpiGrid>

      <Card className="border-amber-300 bg-amber-50/50">
        <CardContent className="py-4 text-sm">
          <strong className="text-foreground">Como funciona:</strong>{' '}
          quando dev junior abre Claude Code com token MCP dele, qualquer tentativa de
          <code className="bg-background px-1 rounded mx-1">write_file</code>
          ou <code className="bg-background px-1 rounded">git_commit_wip</code> chama
          <code className="bg-background px-1 rounded mx-1">UserScopeService::canWriteToPath()</code>
          ANTES da execução. Se o módulo extraído do path não estiver liberado pra esse user, retorna
          <code className="bg-background px-1 rounded mx-1">user_scope_denied</code> e a operação morre no servidor.
          <br />
          <strong>Regra do servidor &gt; regra local:</strong> o dev pode editar files no editor dele, mas commit/push
          em branch protegida só passa pelo ADS.
        </CardContent>
      </Card>

      <Deferred data={['users', 'modules']} fallback={<Skeleton className="h-64 w-full" />}>
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {/* Sidebar de usuários */}
        <div>
          <Card>
            <CardHeader><CardTitle className="text-base">Usuários do business</CardTitle></CardHeader>
            <CardContent className="p-0">
              <ul className="divide-y divide-border">
                {us.map(u => (
                  <li key={u.id}>
                    <button
                      type="button"
                      onClick={() => setSelectedUser(u)}
                      className={`w-full text-left px-4 py-3 hover:bg-muted/50 ${selectedUser?.id === u.id ? 'bg-primary/5' : ''}`}
                    >
                      <div className="font-medium text-sm">{u.name}</div>
                      <div className="text-xs text-muted-foreground">{u.email}</div>
                      <Badge variant="outline" className="text-xs mt-1">
                        {u.modules_count} módulo{u.modules_count !== 1 ? 's' : ''}
                      </Badge>
                    </button>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        </div>

        {/* Matriz de permissões do user selecionado */}
        <div className="lg:col-span-2">
          {selectedUser && (
            <Card>
              <CardHeader>
                <CardTitle className="text-base">
                  Permissões: {selectedUser.name}
                </CardTitle>
                <p className="text-sm text-muted-foreground">
                  {selectedUser.email} · ID #{selectedUser.id}
                </p>
              </CardHeader>
              <CardContent className="p-0">
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
                      <tr>
                        <th className="text-left px-4 py-2">Módulo</th>
                        <th className="text-center px-2 py-2" title="Read">
                          <Eye className="w-4 h-4 mx-auto" /></th>
                        <th className="text-center px-2 py-2" title="Write">
                          <Edit3 className="w-4 h-4 mx-auto" /></th>
                        <th className="text-center px-2 py-2" title="Execute Tools">
                          <Play className="w-4 h-4 mx-auto" /></th>
                        <th className="text-center px-2 py-2" title="Commit">
                          <GitCommit className="w-4 h-4 mx-auto" /></th>
                        <th className="text-right px-4 py-2"></th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {mods.map(mod => {
                        const access = userAccess(selectedUser, mod)
                        const hasAny = access !== null
                        return (
                          <tr key={mod} className={hasAny ? 'bg-emerald-50/30' : ''}>
                            <td className="px-4 py-2">
                              <code className="text-xs">{mod}</code>
                            </td>
                            <td className="text-center px-2 py-2">
                              <Switch
                                checked={access?.can_read ?? false}
                                onCheckedChange={(v) => grant(selectedUser.id, mod, {
                                  can_read: v,
                                  can_write: access?.can_write ?? false,
                                  can_execute_tools: access?.can_execute_tools ?? false,
                                  can_commit: access?.can_commit ?? false,
                                })}
                              />
                            </td>
                            <td className="text-center px-2 py-2">
                              <Switch
                                checked={access?.can_write ?? false}
                                onCheckedChange={(v) => grant(selectedUser.id, mod, {
                                  can_read: true,
                                  can_write: v,
                                  can_execute_tools: access?.can_execute_tools ?? false,
                                  can_commit: access?.can_commit ?? false,
                                })}
                              />
                            </td>
                            <td className="text-center px-2 py-2">
                              <Switch
                                checked={access?.can_execute_tools ?? false}
                                onCheckedChange={(v) => grant(selectedUser.id, mod, {
                                  can_read: true,
                                  can_write: access?.can_write ?? false,
                                  can_execute_tools: v,
                                  can_commit: access?.can_commit ?? false,
                                })}
                              />
                            </td>
                            <td className="text-center px-2 py-2">
                              <Switch
                                checked={access?.can_commit ?? false}
                                onCheckedChange={(v) => grant(selectedUser.id, mod, {
                                  can_read: true,
                                  can_write: access?.can_write ?? false,
                                  can_execute_tools: access?.can_execute_tools ?? false,
                                  can_commit: v,
                                })}
                              />
                            </td>
                            <td className="text-right px-4 py-2">
                              {hasAny && (
                                <Button size="sm" variant="ghost" onClick={() => revoke(selectedUser.id, mod)}>
                                  Revogar
                                </Button>
                              )}
                            </td>
                          </tr>
                        )
                      })}
                    </tbody>
                  </table>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
      </Deferred>

      <Card className="bg-muted/30">
        <CardContent className="py-3 text-xs text-muted-foreground">
          <p><strong className="text-foreground">Endpoint pra Claude Code consultar:</strong></p>
          <pre className="bg-background p-2 rounded mt-1 text-xs">GET /api/ads/scope/check?user_id=X&path=Modules/Y/Z.php</pre>
          <p className="mt-2">Resposta: <code>{`{ "allowed": false, "reason": "user_has_no_write_access_to_module_NFSe" }`}</code></p>
          <p className="mt-2">
            <strong className="text-foreground">Sync local → ADS:</strong>{' '}
            sessões Claude Code do dev junior são ingeridas via <code>cc-watcher</code> (já em prod) →{' '}
            <code>mcp_cc_sessions</code>. Wagner audita tudo em <code>/copiloto/admin/cc-sessions</code>.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}

TeamScopes.layout = (page: ReactNode) => (
  <AppShellV2 title="ADS — Team Scopes" breadcrumbItems={[{ label: 'ADS' }, { label: 'Team Scopes' }]}>
    {page}
  </AppShellV2>
)

export default TeamScopes
