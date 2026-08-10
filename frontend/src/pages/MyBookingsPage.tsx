import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { diner } from '../lib/services'
import { toApiError } from '../lib/api'
import { useUiStore } from '../context/UiContext'
import { Loader, EmptyState, ErrorState } from '../components/ui/States'
import type { Booking } from '../lib/types'

type Scope = 'upcoming' | 'past' | 'all'

export function MyBookingsPage() {
  const [scope, setScope] = useState<Scope>('upcoming')
  const query = useQuery({ queryKey: ['bookings', scope], queryFn: () => diner.myBookings(scope) })

  return (
    <div className="container">
      <div className="foogra-page-head">
        <h1>My bookings</h1>
        <p className="foogra-muted">Every table you have reserved through Foogra.</p>
      </div>

      <div className="foogra-tabs">
        {(['upcoming', 'past', 'all'] as Scope[]).map((value) => (
          <button
            key={value}
            type="button"
            className={scope === value ? 'active' : undefined}
            onClick={() => setScope(value)}
          >
            {value === 'upcoming' ? 'Upcoming' : value === 'past' ? 'Past' : 'All'}
          </button>
        ))}
      </div>

      {query.isPending && <Loader label="Loading your bookings…" />}
      {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}

      {query.data?.data.length === 0 && (
        <EmptyState icon="icon_calendar" title="No bookings here yet">
          <Link to="/restaurants">Find a restaurant</Link> and reserve a table.
        </EmptyState>
      )}

      {query.data?.data.map((booking) => <BookingRow key={booking.id} booking={booking} />)}
    </div>
  )
}

function BookingRow({ booking }: { booking: Booking }) {
  const { notify } = useUiStore()
  const queryClient = useQueryClient()
  const [confirming, setConfirming] = useState(false)

  const cancel = useMutation({
    mutationFn: () => diner.cancelBooking(booking.reference),
    onSuccess: () => {
      notify('Booking cancelled.')
      setConfirming(false)
      void queryClient.invalidateQueries({ queryKey: ['bookings'] })
    },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  const canCancel = booking.status === 'pending' || booking.status === 'confirmed'

  return (
    <div className="foogra-booking-card">
      <img
        src={booking.restaurant?.thumbnail ?? '/img/location_list_placeholder.png'}
        alt={booking.restaurant?.name ?? 'Restaurant'}
      />

      <div className="details">
        <div className="d-flex justify-content-between align-items-start gap-2 flex-wrap">
          <h4>
            {booking.restaurant ? (
              <Link to={`/restaurants/${booking.restaurant.slug}`}>{booking.restaurant.name}</Link>
            ) : (
              'Restaurant'
            )}
          </h4>
          <span className={`foogra-badge ${booking.status}`}>{booking.status_label}</span>
        </div>

        <small className="foogra-muted">{booking.restaurant?.address}</small>

        <dl>
          <div><dt>Reference</dt><dd>{booking.reference}</dd></div>
          <div><dt>Date</dt><dd>{formatDate(booking.booking_date)}</dd></div>
          <div><dt>Time</dt><dd>{booking.booking_time}</dd></div>
          <div><dt>Party</dt><dd>{booking.party_size}</dd></div>
          {booking.discount_percent ? (
            <div><dt>Discount</dt><dd>-{booking.discount_percent}%</dd></div>
          ) : null}
        </dl>

        {booking.notes && <p className="foogra-muted" style={{ marginTop: 8 }}>Note: “{booking.notes}”</p>}
        {booking.cancellation_reason && (
          <p className="foogra-muted" style={{ marginTop: 8 }}>Cancelled: {booking.cancellation_reason}</p>
        )}

        <div className="foogra-form-actions">
          <Link to={`/booking/${booking.reference}`} className="btn_1 outline small">View details</Link>

          {canCancel && !confirming && (
            <button type="button" className="btn_1 outline small" onClick={() => setConfirming(true)}>
              Cancel booking
            </button>
          )}

          {confirming && (
            <>
              <button
                type="button"
                className="btn_1 danger small"
                onClick={() => cancel.mutate()}
                disabled={cancel.isPending}
              >
                {cancel.isPending ? 'Cancelling…' : 'Yes, cancel it'}
              </button>
              <button type="button" className="btn_1 outline small" onClick={() => setConfirming(false)}>
                Keep it
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  )
}

export function formatDate(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}
