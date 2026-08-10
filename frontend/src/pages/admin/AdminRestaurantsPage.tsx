import { useState } from 'react'
import { Link } from 'react-router-dom'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { toApiError } from '../../lib/api'
import { useUiStore } from '../../context/UiContext'
import { Loader, ErrorState, EmptyState } from '../../components/ui/States'
import { Pagination } from '../../components/ui/Pagination'
import { ConfirmButton } from '../../components/admin/ConfirmButton'

export function AdminRestaurantsPage() {
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [trashed, setTrashed] = useState(false)

  const { notify } = useUiStore()
  const queryClient = useQueryClient()

  const query = useQuery({
    queryKey: ['admin', 'restaurants', { page, status, search, trashed }],
    queryFn: () => admin.restaurants({ page, status, q: search, trashed, per_page: 15 }),
    placeholderData: keepPreviousData,
  })

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: ['admin'] })
    void queryClient.invalidateQueries({ queryKey: ['restaurants'] })
  }

  const remove = useMutation({
    mutationFn: (slug: string) => admin.deleteRestaurant(slug),
    onSuccess: () => { notify('Restaurant moved to trash.'); invalidate() },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  const restore = useMutation({
    mutationFn: (id: number) => admin.restoreRestaurant(id),
    onSuccess: () => { notify('Restaurant restored.'); invalidate() },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>Restaurants</h1>
          <p>{query.data ? `${query.data.meta.total} record${query.data.meta.total === 1 ? '' : 's'}` : 'Create, edit and publish your venues'}</p>
        </div>
        <Link to="/admin/restaurants/new" className="btn_1">+ New restaurant</Link>
      </div>

      <div className="admin-panel">
        <div className="admin-filters">
          <input
            type="search"
            placeholder="Search by name…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          />
          <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">Any status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </select>
          <label style={{ fontSize: 13, display: 'flex', alignItems: 'center', gap: 6 }}>
            <input
              type="checkbox"
              checked={trashed}
              onChange={(e) => { setTrashed(e.target.checked); setPage(1) }}
            />
            Show trashed
          </label>
        </div>

        {query.isPending && !query.data && <Loader />}
        {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}

        {query.data?.data.length === 0 && (
          <EmptyState icon="icon_house_alt" title="No restaurants match">
            {trashed ? 'The trash is empty.' : <Link to="/admin/restaurants/new">Add your first restaurant</Link>}
          </EmptyState>
        )}

        {query.data && query.data.data.length > 0 && (
          <div className="admin-table-scroll">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Restaurant</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Score</th>
                  <th>Bookings</th>
                  <th>Avg price</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {query.data.data.map((restaurant) => (
                  <tr key={restaurant.id}>
                    <td>
                      <img
                        className="thumb"
                        src={restaurant.images.thumbnail ?? '/img/location_list_placeholder.png'}
                        alt=""
                      />
                      <span>
                        <strong>{restaurant.name}</strong>
                        <br />
                        <small className="foogra-muted">{restaurant.location.address}</small>
                      </span>
                    </td>
                    <td>{restaurant.category?.name ?? '—'}</td>
                    <td>
                      <span className={`foogra-badge ${restaurant.status}`}>{restaurant.status}</span>
                      {restaurant.is_featured && (
                        <span className="foogra-badge approved" style={{ marginLeft: 5 }}>featured</span>
                      )}
                    </td>
                    <td>
                      {restaurant.rating.score.toFixed(1)}{' '}
                      <small className="foogra-muted">({restaurant.rating.reviews_count})</small>
                    </td>
                    <td>{restaurant.bookings_count}</td>
                    <td>£{restaurant.average_price.toFixed(2)}</td>
                    <td>
                      <div className="actions">
                        {restaurant.deleted_at ? (
                          <button
                            type="button"
                            className="icon-btn"
                            title="Restore"
                            onClick={() => restore.mutate(restaurant.id)}
                          >
                            <i className="arrow_back" />
                          </button>
                        ) : (
                          <>
                            <Link
                              to={`/restaurants/${restaurant.slug}`}
                              className="icon-btn"
                              title="View public page"
                              target="_blank"
                            >
                              <i className="icon_search" />
                            </Link>
                            <Link
                              to={`/admin/restaurants/${restaurant.slug}/menu`}
                              className="icon-btn"
                              title="Edit menu"
                            >
                              <i className="icon_menu-square_alt2" />
                            </Link>
                            <Link
                              to={`/admin/restaurants/${restaurant.slug}`}
                              className="icon-btn"
                              title="Edit"
                            >
                              <i className="icon_pencil-edit" />
                            </Link>
                            <ConfirmButton
                              title="Delete"
                              className="icon-btn danger"
                              confirmLabel="Delete?"
                              onConfirm={() => remove.mutate(restaurant.slug)}
                            >
                              <i className="icon_trash_alt" />
                            </ConfirmButton>
                          </>
                        )}
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
      </div>
    </>
  )
}
