import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { toApiError } from '../../lib/api'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { Loader, ErrorState, EmptyState } from '../../components/ui/States'
import { ConfirmButton } from '../../components/admin/ConfirmButton'
import type { Category } from '../../lib/types'

/** Icon classes shipped with the Foogra icon font. */
const ICONS = [
  'icon-food_icon_pizza', 'icon-food_icon_sushi', 'icon-food_icon_burgher',
  'icon-food_icon_vegetarian', 'icon-food_icon_cake_2', 'icon-food_icon_chinese',
  'icon-food_icon_burrito', 'icon-food_icon_restaurant', 'icon-food_icon_beer',
  'icon-food_icon_coffee',
]

export function AdminCategoriesPage() {
  const { isAdmin } = useAuth()
  const { notify } = useUiStore()
  const queryClient = useQueryClient()

  const [editing, setEditing] = useState<Category | 'new' | null>(null)

  const query = useQuery({ queryKey: ['admin', 'categories'], queryFn: admin.categories })

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['admin', 'categories'] })
    void queryClient.invalidateQueries({ queryKey: ['categories'] })
  }

  const remove = useMutation({
    mutationFn: (slug: string) => admin.deleteCategory(slug),
    onSuccess: () => { notify('Category deleted.'); refresh() },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>Categories</h1>
          <p>
            {isAdmin
              ? 'The cuisine taxonomy shown on the home page and in the listing filters.'
              : 'Read-only — only administrators can change the taxonomy.'}
          </p>
        </div>
        {isAdmin && (
          <button type="button" className="btn_1" onClick={() => setEditing('new')}>+ New category</button>
        )}
      </div>

      {editing && (
        <CategoryForm
          category={editing === 'new' ? undefined : editing}
          onDone={() => { setEditing(null); refresh() }}
          onCancel={() => setEditing(null)}
        />
      )}

      <div className="admin-panel">
        {query.isPending && <Loader />}
        {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}

        {query.data?.length === 0 && <EmptyState icon="icon_tags_alt" title="No categories yet" />}

        {query.data && query.data.length > 0 && (
          <div className="admin-table-scroll">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Category</th>
                  <th>Slug</th>
                  <th>Avg price</th>
                  <th>Restaurants</th>
                  <th>Active</th>
                  {isAdmin && <th />}
                </tr>
              </thead>
              <tbody>
                {query.data.map((category) => (
                  <tr key={category.id}>
                    <td>
                      <i className={category.icon} style={{ fontSize: 22, marginRight: 10, verticalAlign: 'middle' }} />
                      <strong>{category.name}</strong>
                    </td>
                    <td><code>{category.slug}</code></td>
                    <td>£{Number(category.average_price).toFixed(2)}</td>
                    <td>{category.restaurants_count ?? 0}</td>
                    <td>
                      <span className={`foogra-badge ${category.is_active ? 'approved' : 'cancelled'}`}>
                        {category.is_active ? 'active' : 'hidden'}
                      </span>
                    </td>
                    {isAdmin && (
                      <td>
                        <div className="actions">
                          <button type="button" className="icon-btn" title="Edit" onClick={() => setEditing(category)}>
                            <i className="icon_pencil-edit" />
                          </button>
                          <ConfirmButton
                            className="icon-btn danger"
                            title="Delete"
                            confirmLabel="!"
                            disabled={(category.restaurants_count ?? 0) > 0}
                            onConfirm={() => remove.mutate(category.slug)}
                          >
                            <i className="icon_trash_alt" />
                          </ConfirmButton>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {isAdmin && (
          <p className="foogra-muted" style={{ marginTop: 14 }}>
            A category that still has restaurants cannot be deleted — move them first, or set it to
            hidden so it disappears from the public filters without losing the data.
          </p>
        )}
      </div>
    </>
  )
}

function CategoryForm({
  category,
  onDone,
  onCancel,
}: {
  category?: Category
  onDone: () => void
  onCancel: () => void
}) {
  const { notify } = useUiStore()
  const [form, setForm] = useState({
    name: category?.name ?? '',
    icon: category?.icon ?? ICONS[0],
    description: category?.description ?? '',
    average_price: category?.average_price?.toString() ?? '40',
    sort_order: category?.sort_order?.toString() ?? '0',
    is_active: category?.is_active ?? true,
  })

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        name: form.name,
        icon: form.icon,
        description: form.description || null,
        average_price: Number(form.average_price),
        sort_order: Number(form.sort_order),
        is_active: form.is_active,
      }

      return category ? admin.updateCategory(category.slug, payload) : admin.createCategory(payload)
    },
    onSuccess: () => { notify(category ? 'Category updated.' : 'Category created.'); onDone() },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  return (
    <div className="admin-panel">
      <h3>{category ? `Edit “${category.name}”` : 'New category'}</h3>

      <div className="admin-form-grid">
        <div>
          <label>Name</label>
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </div>

        <div>
          <label>Icon</label>
          <select value={form.icon} onChange={(e) => setForm({ ...form, icon: e.target.value })}>
            {ICONS.map((icon) => <option key={icon} value={icon}>{icon.replace('icon-food_icon_', '')}</option>)}
          </select>
          <div style={{ marginTop: 8 }}>
            <i className={form.icon} style={{ fontSize: 34 }} />
          </div>
        </div>

        <div>
          <label>Average price</label>
          <input
            type="number"
            min={0}
            step="0.01"
            value={form.average_price}
            onChange={(e) => setForm({ ...form, average_price: e.target.value })}
          />
        </div>

        <div>
          <label>Sort order</label>
          <input
            type="number"
            min={0}
            value={form.sort_order}
            onChange={(e) => setForm({ ...form, sort_order: e.target.value })}
          />
        </div>

        <div className="full">
          <label>Description</label>
          <textarea
            rows={2}
            value={form.description ?? ''}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
          />
        </div>

        <div>
          <label style={{ fontWeight: 400, display: 'flex', gap: 8, alignItems: 'center' }}>
            <input
              type="checkbox"
              style={{ width: 'auto' }}
              checked={form.is_active}
              onChange={(e) => setForm({ ...form, is_active: e.target.checked })}
            />
            Show in public filters
          </label>
        </div>
      </div>

      <div className="foogra-form-actions">
        <button type="button" className="btn_1" onClick={() => save.mutate()} disabled={save.isPending || !form.name}>
          {save.isPending ? 'Saving…' : category ? 'Save category' : 'Create category'}
        </button>
        <button type="button" className="btn_1 outline" onClick={onCancel}>Cancel</button>
      </div>
    </div>
  )
}
