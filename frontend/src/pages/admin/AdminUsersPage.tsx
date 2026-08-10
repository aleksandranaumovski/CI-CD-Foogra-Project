import { useState } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { AVATAR_FALLBACK, toApiError } from '../../lib/api'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { Loader, ErrorState, EmptyState } from '../../components/ui/States'
import { Pagination } from '../../components/ui/Pagination'
import { ConfirmButton } from '../../components/admin/ConfirmButton'
import type { User, UserRole } from '../../lib/types'

/** Admin-only user administration. */
export function AdminUsersPage() {
  const { user: me } = useAuth()
  const { notify } = useUiStore()
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [role, setRole] = useState('')
  const [search, setSearch] = useState('')
  const [creating, setCreating] = useState(false)

  const query = useQuery({
    queryKey: ['admin', 'users', { page, role, search }],
    queryFn: () => admin.users({ page, role, q: search, per_page: 20 }),
    placeholderData: keepPreviousData,
  })

  const refresh = () => void queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
  const fail = (error: unknown) => notify(toApiError(error).message, 'error')

  const changeRole = useMutation({
    mutationFn: ({ id, newRole }: { id: number; newRole: UserRole }) =>
      admin.updateUser(id, { role: newRole }),
    onSuccess: () => { notify('Role updated.'); refresh() },
    onError: fail,
  })

  const remove = useMutation({
    mutationFn: (id: number) => admin.deleteUser(id),
    onSuccess: () => { notify('User deleted.'); refresh() },
    onError: fail,
  })

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>Users</h1>
          <p>{query.data ? `${query.data.meta.total} account${query.data.meta.total === 1 ? '' : 's'}` : 'Administrators, owners and diners'}</p>
        </div>
        <button type="button" className="btn_1" onClick={() => setCreating(true)}>+ New user</button>
      </div>

      {creating && <UserForm onDone={() => { setCreating(false); refresh() }} onCancel={() => setCreating(false)} />}

      <div className="admin-panel">
        <div className="admin-filters">
          <input
            type="search"
            placeholder="Name or email…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          />
          <select value={role} onChange={(e) => { setRole(e.target.value); setPage(1) }}>
            <option value="">Any role</option>
            <option value="admin">Administrators</option>
            <option value="owner">Restaurant owners</option>
            <option value="customer">Diners</option>
          </select>
        </div>

        {query.isPending && !query.data && <Loader />}
        {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}
        {query.data?.data.length === 0 && <EmptyState icon="icon_profile" title="No users match" />}

        {query.data && query.data.data.length > 0 && (
          <div className="admin-table-scroll">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Role</th>
                  <th>Restaurants</th>
                  <th>Reviews</th>
                  <th>Bookings</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {query.data.data.map((row) => (
                  <tr key={row.id}>
                    <td>
                      <img className="thumb" src={row.avatar_url ?? AVATAR_FALLBACK} alt="" />
                      <span>
                        <strong>{row.name}</strong>
                        {row.id === me?.id && <span className="foogra-badge" style={{ marginLeft: 6 }}>you</span>}
                        <br />
                        <small className="foogra-muted">{row.email}</small>
                      </span>
                    </td>
                    <td>
                      <select
                        value={row.role}
                        disabled={row.id === me?.id}
                        onChange={(e) => changeRole.mutate({ id: row.id, newRole: e.target.value as UserRole })}
                        style={{ padding: '5px 8px', border: '1px solid #ededed', borderRadius: 4, fontSize: 13 }}
                      >
                        <option value="admin">Administrator</option>
                        <option value="owner">Restaurant owner</option>
                        <option value="customer">Customer</option>
                      </select>
                    </td>
                    <td>{row.restaurants_count ?? 0}</td>
                    <td>{row.reviews_count ?? 0}</td>
                    <td>{row.bookings_count ?? 0}</td>
                    <td>
                      <div className="actions">
                        <ConfirmButton
                          className="icon-btn danger"
                          title={row.id === me?.id ? 'You cannot delete your own account' : 'Delete'}
                          confirmLabel="!"
                          disabled={row.id === me?.id || (row.restaurants_count ?? 0) > 0}
                          onConfirm={() => remove.mutate(row.id)}
                        >
                          <i className="icon_trash_alt" />
                        </ConfirmButton>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {query.data && (
          <Pagination
            currentPage={query.data.meta.current_page}
            lastPage={query.data.meta.last_page}
            onChange={setPage}
          />
        )}

        <p className="foogra-muted" style={{ marginTop: 14 }}>
          An owner who still has restaurants cannot be deleted — reassign the venues first so their
          listings, bookings and reviews are not orphaned.
        </p>
      </div>
    </>
  )
}

function UserForm({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const { notify } = useUiStore()
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    role: 'customer' as UserRole,
    phone: '',
  })

  const save = useMutation({
    mutationFn: () => admin.createUser(form) as Promise<User>,
    onSuccess: () => { notify('User created.'); onDone() },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  return (
    <div className="admin-panel">
      <h3>New user</h3>

      <div className="admin-form-grid">
        <div>
          <label>Name</label>
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </div>
        <div>
          <label>Email</label>
          <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
        </div>
        <div>
          <label>Temporary password</label>
          <input
            type="text"
            minLength={8}
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required
          />
          <small className="foogra-muted">At least 8 characters, with letters and numbers.</small>
        </div>
        <div>
          <label>Role</label>
          <select value={form.role} onChange={(e) => setForm({ ...form, role: e.target.value as UserRole })}>
            <option value="customer">Customer</option>
            <option value="owner">Restaurant owner</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
        <div>
          <label>Phone</label>
          <input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
        </div>
      </div>

      <div className="foogra-form-actions">
        <button type="button" className="btn_1" onClick={() => save.mutate()} disabled={save.isPending}>
          {save.isPending ? 'Creating…' : 'Create user'}
        </button>
        <button type="button" className="btn_1 outline" onClick={onCancel}>Cancel</button>
      </div>
    </div>
  )
}
