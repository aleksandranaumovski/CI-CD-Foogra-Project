import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { diner } from '../lib/services'
import { toApiError } from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { Loader, ErrorState } from '../components/ui/States'
import { formatDate } from './MyBookingsPage'

/**
 * The template's `confirm.html`, driven by a real reference. Guests reach it
 * with `?email=` appended, which is how the API authorises the lookup.
 */
export function BookingConfirmationPage() {
  const { reference = '' } = useParams()
  const [searchParams] = useSearchParams()
  const { isAuthenticated } = useAuth()
  const email = searchParams.get('email') ?? ''

  const query = useQuery({
    queryKey: ['booking', reference, email],
    queryFn: () => diner.findBooking(reference, email),
    enabled: Boolean(reference),
  })

  if (query.isPending) {
    return <div className="container margin_60_40"><Loader label="Fetching your booking…" /></div>
  }

  if (query.isError) {
    const error = toApiError(query.error)

    return (
      <div className="container margin_60_40">
        <ErrorState
          message={
            error.status === 403
              ? 'We need the email address this booking was made with before we can show it.'
              : error.message
          }
        />
        <p className="text-center">
          <Link to="/booking-lookup" className="btn_1">Look up a booking</Link>
          {!isAuthenticated && (
            <>
              {' '}
              <Link to="/restaurants" className="btn_1 outline">Browse restaurants</Link>
            </>
          )}
        </p>
      </div>
    )
  }

  const booking = query.data

  return (
    <div className="container margin_60_40">
      <div className="row justify-content-center">
        <div className="col-lg-8">
          <div className="admin-panel" style={{ textAlign: 'center', padding: 40 }}>
            <i className="icon_check_alt2" style={{ fontSize: 62, color: '#24713c' }} />
            <h1 style={{ fontSize: 26, marginTop: 15 }}>Table requested</h1>
            <p className="foogra-muted">
              We have sent the details to <strong>{booking.guest.email}</strong>. The restaurant will
              confirm shortly.
            </p>

            <div
              className="foogra-alert success"
              style={{ display: 'inline-block', marginTop: 10, fontSize: 18, letterSpacing: 1 }}
            >
              Reference: <strong>{booking.reference}</strong>
            </div>
          </div>

          <div className="admin-panel">
            <h3>Your reservation</h3>

            <div className="foogra-booking-card" style={{ border: 'none', padding: 0, marginBottom: 0 }}>
              <img
                src={booking.restaurant?.thumbnail ?? '/img/location_list_placeholder.png'}
                alt={booking.restaurant?.name ?? ''}
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
                  <div><dt>Date</dt><dd>{formatDate(booking.booking_date)}</dd></div>
                  <div><dt>Time</dt><dd>{booking.booking_time}</dd></div>
                  <div><dt>Party</dt><dd>{booking.party_size} guest{booking.party_size === 1 ? '' : 's'}</dd></div>
                  <div><dt>Booked by</dt><dd>{booking.guest.name}</dd></div>
                  {booking.discount_percent ? (
                    <div><dt>Discount</dt><dd>-{booking.discount_percent}%</dd></div>
                  ) : null}
                </dl>

                {booking.notes && <p className="foogra-muted" style={{ marginTop: 10 }}>Note: “{booking.notes}”</p>}
              </div>
            </div>

            <p className="foogra-muted" style={{ marginTop: 20 }}>
              No money has been charged. In development the confirmation email lands in Mailpit at{' '}
              <a href="http://localhost:8025" target="_blank" rel="noreferrer">localhost:8025</a>.
            </p>

            <div className="foogra-form-actions">
              {isAuthenticated && <Link to="/bookings" className="btn_1">All my bookings</Link>}
              <Link to="/restaurants" className="btn_1 outline">Keep browsing</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
