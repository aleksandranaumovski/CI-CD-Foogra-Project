import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { fieldError, toApiError } from '../../lib/api'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { Loader, ErrorState } from '../../components/ui/States'
import { ChipInput } from '../../components/admin/ChipInput'
import { OpeningHoursEditor } from '../../components/admin/OpeningHoursEditor'
import type { ApiError } from '../../lib/types'

interface FormState {
  name: string
  category_id: string
  tagline: string
  description: string
  address: string
  city: string
  postal_code: string
  country: string
  latitude: string
  longitude: string
  phone: string
  email: string
  website: string
  average_price: string
  discount_percent: string
  status: string
  is_featured: boolean
  services: string[]
  payment_methods: string[]
  facebook: string
  instagram: string
  twitter: string
}

const EMPTY: FormState = {
  name: '', category_id: '', tagline: '', description: '',
  address: '', city: '', postal_code: '', country: 'GB', latitude: '', longitude: '',
  phone: '', email: '', website: '',
  average_price: '25', discount_percent: '', status: 'draft', is_featured: false,
  services: [], payment_methods: [],
  facebook: '', instagram: '', twitter: '',
}

/** Create and edit form for a restaurant, including gallery and opening hours. */
export function AdminRestaurantEditPage() {
  const { slug } = useParams()
  const isNew = !slug
  const navigate = useNavigate()
  const { isAdmin } = useAuth()
  const { notify } = useUiStore()
  const queryClient = useQueryClient()

  const [form, setForm] = useState<FormState>(EMPTY)
  const [heroFile, setHeroFile] = useState<File | null>(null)
  const [thumbFile, setThumbFile] = useState<File | null>(null)
  const [error, setError] = useState<ApiError | null>(null)

  const categories = useQuery({ queryKey: ['admin', 'categories'], queryFn: admin.categories })

  const existing = useQuery({
    queryKey: ['admin', 'restaurant', slug],
    queryFn: () => admin.restaurant(slug!),
    enabled: !isNew,
  })

  // Hydrate the form once the record arrives.
  useEffect(() => {
    const restaurant = existing.data

    if (!restaurant) return

    setForm({
      name: restaurant.name,
      category_id: String(restaurant.category?.id ?? ''),
      tagline: restaurant.tagline ?? '',
      description: restaurant.description ?? '',
      address: restaurant.location.address,
      city: restaurant.location.city ?? '',
      postal_code: restaurant.location.postal_code ?? '',
      country: restaurant.location.country ?? 'GB',
      latitude: restaurant.location.latitude?.toString() ?? '',
      longitude: restaurant.location.longitude?.toString() ?? '',
      phone: restaurant.contact.phone ?? '',
      email: restaurant.contact.email ?? '',
      website: restaurant.contact.website ?? '',
      average_price: String(restaurant.average_price),
      discount_percent: restaurant.discount_percent?.toString() ?? '',
      status: restaurant.status,
      is_featured: restaurant.is_featured,
      services: restaurant.services ?? [],
      payment_methods: restaurant.payment_methods ?? [],
      facebook: restaurant.social_links?.facebook ?? '',
      instagram: restaurant.social_links?.instagram ?? '',
      twitter: restaurant.social_links?.twitter ?? '',
    })
  }, [existing.data])

  const save = useMutation({
    mutationFn: async () => {
      const payload: Record<string, unknown> = {
        name: form.name,
        category_id: Number(form.category_id),
        tagline: form.tagline || null,
        description: form.description || null,
        address: form.address,
        city: form.city || null,
        postal_code: form.postal_code || null,
        country: form.country || 'GB',
        latitude: form.latitude === '' ? null : Number(form.latitude),
        longitude: form.longitude === '' ? null : Number(form.longitude),
        phone: form.phone || null,
        email: form.email || null,
        website: form.website || null,
        average_price: Number(form.average_price),
        discount_percent: form.discount_percent === '' ? null : Number(form.discount_percent),
        status: form.status,
        services: form.services,
        payment_methods: form.payment_methods,
        social_links: {
          facebook: form.facebook || null,
          instagram: form.instagram || null,
          twitter: form.twitter || null,
        },
      }

      // Only an admin may set this — sending it as an owner is a 422.
      if (isAdmin) payload.is_featured = form.is_featured

      if (heroFile || thumbFile) {
        const data = toFormData(payload)
        if (heroFile) data.append('hero_image', heroFile)
        if (thumbFile) data.append('thumbnail', thumbFile)

        return isNew ? admin.createRestaurant(data) : admin.updateRestaurant(slug!, data)
      }

      return isNew ? admin.createRestaurant(payload) : admin.updateRestaurant(slug!, payload)
    },
    onSuccess: (restaurant) => {
      notify(isNew ? 'Restaurant created.' : 'Restaurant saved.')
      void queryClient.invalidateQueries({ queryKey: ['admin'] })
      void queryClient.invalidateQueries({ queryKey: ['restaurants'] })
      setHeroFile(null)
      setThumbFile(null)

      if (isNew || restaurant.slug !== slug) {
        navigate(`/admin/restaurants/${restaurant.slug}`, { replace: true })
      }
    },
    onError: (err) => {
      const apiError = toApiError(err)
      setError(apiError)
      notify(apiError.message, 'error')
    },
  })

  if (!isNew && existing.isPending) return <Loader label="Loading restaurant…" />
  if (!isNew && existing.isError) {
    return <ErrorState message={toApiError(existing.error).message} onRetry={() => existing.refetch()} />
  }

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((current) => ({ ...current, [key]: value }))

  const text = (key: keyof FormState) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
    set(key, e.target.value as FormState[typeof key])

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>{isNew ? 'New restaurant' : form.name || 'Edit restaurant'}</h1>
          <p>
            {isNew
              ? 'Save as a draft first, then publish when the listing is ready.'
              : <>Editing <code>{slug}</code></>}
          </p>
        </div>
        <div className="foogra-form-actions" style={{ margin: 0 }}>
          <Link to="/admin/restaurants" className="btn_1 outline small">Back to list</Link>
          {!isNew && (
            <>
              <Link to={`/admin/restaurants/${slug}/menu`} className="btn_1 outline small">Edit menu</Link>
              <Link to={`/restaurants/${slug}`} target="_blank" className="btn_1 outline small">View page</Link>
            </>
          )}
        </div>
      </div>

      {error && !error.errors && <div className="foogra-alert error">{error.message}</div>}

      <form onSubmit={(e) => { e.preventDefault(); setError(null); save.mutate() }}>
        {/* ------------------------------------------------------------ basics */}
        <div className="admin-panel">
          <h3>The basics</h3>
          <div className="admin-form-grid">
            <div className="full">
              <label htmlFor="r_name">Name *</label>
              <input id="r_name" value={form.name} onChange={text('name')} required maxLength={180} />
              <FieldError error={error} field="name" />
            </div>

            <div>
              <label htmlFor="r_category">Category *</label>
              <select id="r_category" value={form.category_id} onChange={text('category_id')} required>
                <option value="">Choose a category…</option>
                {categories.data?.map((category) => (
                  <option key={category.id} value={category.id}>{category.name}</option>
                ))}
              </select>
              <FieldError error={error} field="category_id" />
            </div>

            <div>
              <label htmlFor="r_status">Status</label>
              <select id="r_status" value={form.status} onChange={text('status')}>
                <option value="draft">Draft — not visible publicly</option>
                <option value="published">Published — live on the site</option>
                <option value="archived">Archived</option>
              </select>
            </div>

            <div className="full">
              <label htmlFor="r_tagline">Tagline</label>
              <input id="r_tagline" value={form.tagline} onChange={text('tagline')} maxLength={180} />
            </div>

            <div className="full">
              <label htmlFor="r_description">Description</label>
              <textarea id="r_description" rows={6} value={form.description} onChange={text('description')} />
              <small className="foogra-muted">Blank lines start a new paragraph on the public page.</small>
            </div>
          </div>
        </div>

        {/* ---------------------------------------------------------- location */}
        <div className="admin-panel">
          <h3>Where to find it</h3>
          <div className="admin-form-grid">
            <div className="full">
              <label htmlFor="r_address">Street address *</label>
              <input id="r_address" value={form.address} onChange={text('address')} required />
              <FieldError error={error} field="address" />
            </div>
            <div>
              <label htmlFor="r_city">City</label>
              <input id="r_city" value={form.city} onChange={text('city')} />
            </div>
            <div>
              <label htmlFor="r_postcode">Postcode</label>
              <input id="r_postcode" value={form.postal_code} onChange={text('postal_code')} />
            </div>
            <div>
              <label htmlFor="r_country">Country code</label>
              <input id="r_country" value={form.country} onChange={text('country')} maxLength={2} />
              <FieldError error={error} field="country" />
            </div>
            <div>
              <label htmlFor="r_lat">Latitude</label>
              <input id="r_lat" type="number" step="0.0000001" value={form.latitude} onChange={text('latitude')} />
              <small className="foogra-muted">Needed for the radius filter.</small>
            </div>
            <div>
              <label htmlFor="r_lng">Longitude</label>
              <input id="r_lng" type="number" step="0.0000001" value={form.longitude} onChange={text('longitude')} />
            </div>
          </div>
        </div>

        {/* ------------------------------------------------- contact & pricing */}
        <div className="admin-panel">
          <h3>Contact and pricing</h3>
          <div className="admin-form-grid">
            <div>
              <label htmlFor="r_phone">Phone</label>
              <input id="r_phone" value={form.phone} onChange={text('phone')} />
            </div>
            <div>
              <label htmlFor="r_email">Email</label>
              <input id="r_email" type="email" value={form.email} onChange={text('email')} />
              <FieldError error={error} field="email" />
            </div>
            <div>
              <label htmlFor="r_website">Website</label>
              <input id="r_website" type="url" placeholder="https://…" value={form.website} onChange={text('website')} />
              <FieldError error={error} field="website" />
            </div>
            <div>
              <label htmlFor="r_price">Average price per head *</label>
              <input
                id="r_price"
                type="number"
                min={0}
                step="0.01"
                value={form.average_price}
                onChange={text('average_price')}
                required
              />
              <FieldError error={error} field="average_price" />
            </div>
            <div>
              <label htmlFor="r_discount">Discount %</label>
              <input
                id="r_discount"
                type="number"
                min={1}
                max={90}
                value={form.discount_percent}
                onChange={text('discount_percent')}
                placeholder="No offer"
              />
              <small className="foogra-muted">Shows as the red ribbon on cards.</small>
              <FieldError error={error} field="discount_percent" />
            </div>

            {isAdmin && (
              <div>
                <label htmlFor="r_featured">Homepage placement</label>
                <label style={{ fontWeight: 400, display: 'flex', gap: 8, alignItems: 'center', marginTop: 8 }}>
                  <input
                    id="r_featured"
                    type="checkbox"
                    style={{ width: 'auto' }}
                    checked={form.is_featured}
                    onChange={(e) => set('is_featured', e.target.checked)}
                  />
                  Feature in “Popular Restaurants”
                </label>
              </div>
            )}
          </div>
        </div>

        {/* ------------------------------------------------------------ images */}
        <div className="admin-panel">
          <h3>Imagery</h3>
          <div className="admin-form-grid">
            <div>
              <label htmlFor="r_thumb">Card thumbnail</label>
              {existing.data?.images.thumbnail && (
                <img
                  src={existing.data.images.thumbnail}
                  alt=""
                  style={{ width: '100%', maxWidth: 220, borderRadius: 5, marginBottom: 8 }}
                />
              )}
              <input
                id="r_thumb"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => setThumbFile(e.target.files?.[0] ?? null)}
              />
              <FieldError error={error} field="thumbnail" />
            </div>
            <div>
              <label htmlFor="r_hero">Detail page hero</label>
              {existing.data?.images.hero && (
                <img
                  src={existing.data.images.hero}
                  alt=""
                  style={{ width: '100%', maxWidth: 220, borderRadius: 5, marginBottom: 8 }}
                />
              )}
              <input
                id="r_hero"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                onChange={(e) => setHeroFile(e.target.files?.[0] ?? null)}
              />
              <FieldError error={error} field="hero_image" />
            </div>
          </div>
        </div>

        {/* --------------------------------------------------------- amenities */}
        <div className="admin-panel">
          <h3>Amenities and links</h3>
          <div className="admin-form-grid">
            <div>
              <label>Services</label>
              <ChipInput
                values={form.services}
                onChange={(values) => set('services', values)}
                placeholder="Wifi, Parking…"
                suggestions={['Wifi', 'Parking', 'Wheelchair Accessible', 'Outdoor Seating', 'Pet Friendly', 'Takeaway']}
              />
            </div>
            <div>
              <label>Payment methods</label>
              <ChipInput
                values={form.payment_methods}
                onChange={(values) => set('payment_methods', values)}
                placeholder="Visa, Amex…"
                suggestions={['Visa', 'Mastercard', 'Amex', 'Cash', 'Apple Pay']}
              />
            </div>
            <div>
              <label htmlFor="r_fb">Facebook</label>
              <input id="r_fb" type="url" placeholder="https://facebook.com/…" value={form.facebook} onChange={text('facebook')} />
            </div>
            <div>
              <label htmlFor="r_ig">Instagram</label>
              <input id="r_ig" type="url" placeholder="https://instagram.com/…" value={form.instagram} onChange={text('instagram')} />
            </div>
            <div>
              <label htmlFor="r_tw">X / Twitter</label>
              <input id="r_tw" type="url" placeholder="https://x.com/…" value={form.twitter} onChange={text('twitter')} />
            </div>
          </div>
        </div>

        <div className="admin-panel" style={{ position: 'sticky', bottom: 0, zIndex: 5 }}>
          <div className="foogra-form-actions" style={{ marginTop: 0 }}>
            <button type="submit" className="btn_1" disabled={save.isPending}>
              {save.isPending ? 'Saving…' : isNew ? 'Create restaurant' : 'Save changes'}
            </button>
            <Link to="/admin/restaurants" className="btn_1 outline">Cancel</Link>
          </div>
        </div>
      </form>

      {/* Opening hours need an existing record, so they live outside the form. */}
      {!isNew && existing.data && (
        <OpeningHoursEditor slug={existing.data.slug} hours={existing.data.opening_hours ?? []} />
      )}
    </>
  )
}

function FieldError({ error, field }: { error: ApiError | null; field: string }) {
  const message = fieldError(error, field)

  return message ? <small className="foogra-field-error">{message}</small> : null
}

/**
 * Flattens a nested payload into FormData using PHP's bracket notation, so
 * arrays and nested objects survive a multipart upload.
 */
function toFormData(payload: Record<string, unknown>, form = new FormData(), prefix = ''): FormData {
  for (const [key, value] of Object.entries(payload)) {
    const field = prefix ? `${prefix}[${key}]` : key

    if (value === null || value === undefined) continue

    if (Array.isArray(value)) {
      value.forEach((item, index) => form.append(`${field}[${index}]`, String(item)))
    } else if (typeof value === 'object') {
      toFormData(value as Record<string, unknown>, form, field)
    } else if (typeof value === 'boolean') {
      form.append(field, value ? '1' : '0')
    } else {
      form.append(field, String(value))
    }
  }

  return form
}
