import { useState } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { toApiError } from '../../lib/api'
import { useUiStore } from '../../context/UiContext'
import { Loader, ErrorState, EmptyState } from '../../components/ui/States'
import { Pagination } from '../../components/ui/Pagination'
import { ConfirmButton } from '../../components/admin/ConfirmButton'
import { formatDate } from '../MyBookingsPage'
import type { Booking, BookingStatus } from '../../lib/types'

/** The reservation book — filter, confirm, seat, complete or cancel. */
export function AdminBookingsPage() {
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const query = useQuery({
    queryKey: ['admin', 'bookings', { page, status, search, from, to }],
    queryFn: () => admin.bookings({ page, status, q: search, from, to, per_page: 20 }),
    placeholderData: keepPreviousData,
  })

  const today = new Date().toISOString().slice(0, 10)

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>Bookings</h1>
          <p>{query.data ? `${query.data.meta.total} reservation${query.data.meta.total === 1 ? '' : 's'}` : 'Your reservation book'}</p>
        </div>
      </div>

      <div className="admin-panel">
        <div className="admin-filters">
          <input
            type="search"
            placeholder="Reference, name or email…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1) }}
          />
          <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">Any status</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="seated">Seated</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
          <label style={{ fontSize: 12, color: '#777' }}>
            From <input type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} />
          </label>
          <label style={{ fontSize: 12, color: '#777' }}>
            To <input type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} />
          </label>
          <button
            type="button"
            className="btn_1 outline small"
            onClick={() => { setFrom(today); setTo(today); setPage(1) }}
          >
            Today
          </button>
          {(from || to || status || search) && (
            <button
              type="button"
              className="btn_1 outline small"
              onClick={() => { setFrom(''); setTo(''); setStatus(''); setSearch(''); setPage(1) }}
            >
              Clear
            </button>
          )}
        </div>

        {query.isPending && !query.data && <Loader />}
        {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}

        {query.data?.data.length === 0 && (
          <EmptyState icon="icon_calendar" title="No bookings match those filters" />
        )}

        {query.data && query.data.data.length > 0 && (
          <div className="admin-table-scroll">
            <table className="admin-table">
              <thead>
                <tr>
                  <th>Reference</th>
                  <th>Guest</th>
                  <th>Restaurant</th>
                  <th>When</th>
                  <th>Party</th>
                  <th>Status</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {query.data.data.map((booking) => <BookingRow key={booking.id} booking={booking} />)}
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

/** The next sensible state for each status, so the row shows one clear action. */
const NEXT_STATE: Partial<Record<BookingStatus, { to: BookingStatus; label: string }>> = {
  pending: { to: 'confirmed', label: 'Confirm' },
  confirmed: { to: 'seated', label: 'Mark seated' },
  seated: { to: 'completed', label: 'Mark complete' },
}

function BookingRow({ booking }: { booking: Booking }) {
  const { notify } = useUiStore()
  const queryClient = useQueryClient()

  const refresh = () => void queryClient.invalidateQueries({ queryKey: ['admin'] })
  const fail = (error: unknown) => notify(toApiError(error).message, 'error')

  const transition = useMutation({
    mutationFn: (status: BookingStatus) => admin.transitionBooking(booking.reference, status),
    onSuccess: (updated) => { notify(`Booking marked as ${updated.status_label}.`); refresh() },
    onError: fail,
  })

  const remove = useMutation({
    mutationFn: () => admin.deleteBooking(booking.reference),
    onSuccess: () => { notify('Booking deleted.'); refresh() },
    onError: fail,
  })

  const next = NEXT_STATE[booking.status]

  return (
    <tr>
      <td><code>{booking.reference}</code></td>
      <td>
        <strong>{booking.guest.name}</strong>
        <br />
        <small className="foogra-muted">{booking.guest.email}</small>
        {booking.guest.phone && <><br /><small className="foogra-muted">{booking.guest.phone}</small></>}
      </td>
      <td>{booking.restaurant?.name ?? '—'}</td>
      <td>
        {formatDate(booking.booking_date)}
        <br />
        <strong>{booking.booking_time}</strong>
      </td>
      <td>{booking.party_size}</td>
      <td>
        <span className={`foogra-badge ${booking.status}`}>{booking.status_label}</span>
        {booking.notes && (
          <>
            <br />
            <small className="foogra-muted" title={booking.notes}>
              “{booking.notes.slice(0, 40)}{booking.notes.length > 40 ? '…' : ''}”
            </small>
          </>
        )}
      </td>
      <td>
        <div className="actions">
          {next && (
            <button
              type="button"
              className="btn_1 small"
              onClick={() => transition.mutate(next.to)}
              disabled={transition.isPending}
            >
              {next.label}
            </button>
          )}
          {booking.status !== 'cancelled' && booking.status !== 'completed' && (
            <ConfirmButton className="btn_1 outline small" onConfirm={() => transition.mutate('cancelled')}>
              Cancel
            </ConfirmButton>
          )}
          <ConfirmButton className="icon-btn danger" title="Delete" confirmLabel="!" onConfirm={() => remove.mutate()}>
            <i className="icon_trash_alt" />
          </ConfirmButton>
        </div>
      </td>
    </tr>
  )
}
