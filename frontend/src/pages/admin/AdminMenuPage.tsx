import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { toApiError } from '../../lib/api'
import { useUiStore } from '../../context/UiContext'
import { Loader, ErrorState, EmptyState } from '../../components/ui/States'
import { ConfirmButton } from '../../components/admin/ConfirmButton'
import type { Dish, MenuSection } from '../../lib/types'

/** Full CRUD over a restaurant's menu sections and dishes. */
export function AdminMenuPage() {
  const { slug = '' } = useParams()
  const { notify } = useUiStore()
  const queryClient = useQueryClient()
  const [newSection, setNewSection] = useState('')

  const restaurant = useQuery({
    queryKey: ['admin', 'restaurant', slug],
    queryFn: () => admin.restaurant(slug),
  })

  const restaurantId = restaurant.data?.id

  const sections = useQuery({
    queryKey: ['admin', 'menu', restaurantId],
    queryFn: () => admin.menuSections(restaurantId!),
    enabled: Boolean(restaurantId),
  })

  const refresh = () => void queryClient.invalidateQueries({ queryKey: ['admin', 'menu', restaurantId] })

  const createSection = useMutation({
    mutationFn: (isSpecial: boolean) =>
      admin.createMenuSection({
        restaurant_id: restaurantId,
        name: isSpecial ? 'Special Offers' : newSection.trim(),
        is_special_offers: isSpecial,
        sort_order: isSpecial ? 99 : (sections.data?.length ?? 0),
      }),
    onSuccess: () => { notify('Section added.'); setNewSection(''); refresh() },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  if (restaurant.isPending) return <Loader label="Loading menu…" />
  if (restaurant.isError) {
    return <ErrorState message={toApiError(restaurant.error).message} onRetry={() => restaurant.refetch()} />
  }

  const hasSpecials = sections.data?.some((section) => section.is_special_offers) ?? false

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>Menu — {restaurant.data.name}</h1>
          <p>Sections appear on the detail page in the order below.</p>
        </div>
        <div className="foogra-form-actions" style={{ margin: 0 }}>
          <Link to={`/admin/restaurants/${slug}`} className="btn_1 outline small">Edit restaurant</Link>
          <Link to={`/restaurants/${slug}`} target="_blank" className="btn_1 outline small">View page</Link>
        </div>
      </div>

      <div className="admin-panel">
        <h3>Add a section</h3>
        <div className="admin-filters">
          <input
            type="text"
            placeholder="e.g. Starters, Main Course, Dessert"
            value={newSection}
            onChange={(e) => setNewSection(e.target.value)}
            style={{ minWidth: 260 }}
          />
          <button
            type="button"
            className="btn_1 small"
            disabled={!newSection.trim() || createSection.isPending}
            onClick={() => createSection.mutate(false)}
          >
            Add section
          </button>
          {!hasSpecials && (
            <button
              type="button"
              className="btn_1 outline small"
              onClick={() => createSection.mutate(true)}
              disabled={createSection.isPending}
            >
              + Special Offers block
            </button>
          )}
        </div>
      </div>

      {sections.isPending && <Loader />}

      {sections.data?.length === 0 && (
        <div className="admin-panel">
          <EmptyState icon="icon_menu-square_alt2" title="This menu is empty">
            Add your first section above, then start adding dishes.
          </EmptyState>
        </div>
      )}

      {sections.data?.map((section) => (
        <SectionPanel key={section.id} section={section} onChanged={refresh} />
      ))}
    </>
  )
}

function SectionPanel({ section, onChanged }: { section: MenuSection; onChanged: () => void }) {
  const { notify } = useUiStore()
  const [renaming, setRenaming] = useState(false)
  const [name, setName] = useState(section.name)
  const [adding, setAdding] = useState(false)

  const fail = (error: unknown) => notify(toApiError(error).message, 'error')

  const rename = useMutation({
    mutationFn: () => admin.updateMenuSection(section.id, { name }),
    onSuccess: () => { notify('Section renamed.'); setRenaming(false); onChanged() },
    onError: fail,
  })

  const remove = useMutation({
    mutationFn: () => admin.deleteMenuSection(section.id),
    onSuccess: () => { notify('Section deleted.'); onChanged() },
    onError: fail,
  })

  return (
    <div className="admin-panel">
      <div className="d-flex justify-content-between align-items-center flex-wrap gap-2" style={{ marginBottom: 16 }}>
        {renaming ? (
          <div className="admin-filters" style={{ margin: 0 }}>
            <input value={name} onChange={(e) => setName(e.target.value)} autoFocus />
            <button type="button" className="btn_1 small" onClick={() => rename.mutate()}>Save</button>
            <button type="button" className="btn_1 outline small" onClick={() => { setName(section.name); setRenaming(false) }}>
              Cancel
            </button>
          </div>
        ) : (
          <h3 style={{ margin: 0, border: 'none', padding: 0 }}>
            {section.name}
            {section.is_special_offers && <span className="foogra-badge approved" style={{ marginLeft: 8 }}>special offers</span>}
            <small className="foogra-muted" style={{ marginLeft: 8, fontWeight: 400 }}>
              {section.dishes?.length ?? 0} dish{(section.dishes?.length ?? 0) === 1 ? '' : 'es'}
            </small>
          </h3>
        )}

        <div className="actions" style={{ display: 'flex', gap: 6 }}>
          {!renaming && (
            <button type="button" className="icon-btn" title="Rename" onClick={() => setRenaming(true)}>
              <i className="icon_pencil-edit" />
            </button>
          )}
          <ConfirmButton
            className="icon-btn danger"
            title="Delete section"
            confirmLabel="!"
            onConfirm={() => remove.mutate()}
          >
            <i className="icon_trash_alt" />
          </ConfirmButton>
        </div>
      </div>

      <div className="admin-table-scroll">
        <table className="admin-table">
          <thead>
            <tr><th>Dish</th><th>Price</th><th>Available</th><th /></tr>
          </thead>
          <tbody>
            {section.dishes?.map((dish) => (
              <DishRow key={dish.id} dish={dish} onChanged={onChanged} />
            ))}
            {(section.dishes?.length ?? 0) === 0 && (
              <tr><td colSpan={4} className="foogra-muted">No dishes in this section yet.</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {adding ? (
        <DishForm sectionId={section.id} onDone={() => { setAdding(false); onChanged() }} onCancel={() => setAdding(false)} />
      ) : (
        <div className="foogra-form-actions">
          <button type="button" className="btn_1 outline small" onClick={() => setAdding(true)}>+ Add dish</button>
        </div>
      )}
    </div>
  )
}

function DishRow({ dish, onChanged }: { dish: Dish; onChanged: () => void }) {
  const { notify } = useUiStore()
  const [editing, setEditing] = useState(false)

  const fail = (error: unknown) => notify(toApiError(error).message, 'error')

  const toggle = useMutation({
    mutationFn: () => admin.toggleDish(dish.id),
    onSuccess: () => onChanged(),
    onError: fail,
  })

  const remove = useMutation({
    mutationFn: () => admin.deleteDish(dish.id),
    onSuccess: () => { notify('Dish removed.'); onChanged() },
    onError: fail,
  })

  if (editing) {
    return (
      <tr>
        <td colSpan={4}>
          <DishForm
            dish={dish}
            sectionId={dish.menu_section_id}
            onDone={() => { setEditing(false); onChanged() }}
            onCancel={() => setEditing(false)}
          />
        </td>
      </tr>
    )
  }

  return (
    <tr>
      <td>
        <strong>{dish.name}</strong>
        {dish.is_vegetarian && <span className="foogra-veg">V</span>}
        {dish.description && <><br /><small className="foogra-muted">{dish.description}</small></>}
      </td>
      <td>£{dish.price.toFixed(2)}</td>
      <td>
        <button
          type="button"
          className={`foogra-badge ${dish.is_available ? 'confirmed' : 'cancelled'}`}
          style={{ border: 'none', cursor: 'pointer' }}
          onClick={() => toggle.mutate()}
          title="Toggle availability"
        >
          {dish.is_available ? 'available' : 'sold out'}
        </button>
      </td>
      <td>
        <div className="actions">
          <button type="button" className="icon-btn" title="Edit" onClick={() => setEditing(true)}>
            <i className="icon_pencil-edit" />
          </button>
          <ConfirmButton className="icon-btn danger" title="Delete" confirmLabel="!" onConfirm={() => remove.mutate()}>
            <i className="icon_trash_alt" />
          </ConfirmButton>
        </div>
      </td>
    </tr>
  )
}

function DishForm({
  dish,
  sectionId,
  onDone,
  onCancel,
}: {
  dish?: Dish
  sectionId: number
  onDone: () => void
  onCancel: () => void
}) {
  const { notify } = useUiStore()
  const [form, setForm] = useState({
    name: dish?.name ?? '',
    description: dish?.description ?? '',
    price: dish?.price?.toString() ?? '',
    is_vegetarian: dish?.is_vegetarian ?? false,
  })

  const save = useMutation({
    mutationFn: () => {
      const payload = {
        name: form.name,
        description: form.description || null,
        price: Number(form.price),
        is_vegetarian: form.is_vegetarian,
      }

      return dish
        ? admin.updateDish(dish.id, payload)
        : admin.createDish({ ...payload, menu_section_id: sectionId })
    },
    onSuccess: () => { notify(dish ? 'Dish updated.' : 'Dish added.'); onDone() },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  return (
    <div style={{ padding: 16, background: '#fafafa', borderRadius: 5, marginTop: 12 }}>
      <div className="admin-form-grid">
        <div>
          <label>Dish name</label>
          <input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        </div>
        <div>
          <label>Price (£)</label>
          <input
            type="number"
            min={0}
            step="0.01"
            value={form.price}
            onChange={(e) => setForm({ ...form, price: e.target.value })}
            required
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
              checked={form.is_vegetarian}
              onChange={(e) => setForm({ ...form, is_vegetarian: e.target.checked })}
            />
            Vegetarian
          </label>
        </div>
      </div>

      <div className="foogra-form-actions">
        <button
          type="button"
          className="btn_1 small"
          onClick={() => save.mutate()}
          disabled={save.isPending || !form.name || form.price === ''}
        >
          {save.isPending ? 'Saving…' : dish ? 'Save dish' : 'Add dish'}
        </button>
        <button type="button" className="btn_1 outline small" onClick={onCancel}>Cancel</button>
      </div>
    </div>
  )
}
