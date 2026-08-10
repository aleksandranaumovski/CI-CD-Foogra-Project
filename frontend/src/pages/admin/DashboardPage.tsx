import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { admin } from '../../lib/services'
import { toApiError } from '../../lib/api'
import { useAuth } from '../../context/AuthContext'
import { Loader, ErrorState, EmptyState } from '../../components/ui/States'
import { formatDate } from '../MyBookingsPage'

export function DashboardPage() {
  const { user, isAdmin } = useAuth()
  const query = useQuery({ queryKey: ['admin', 'stats'], queryFn: admin.stats })

  if (query.isPending) return <Loader label="Loading your dashboard…" />
  if (query.isError) {
    return <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />
  }

  const stats = query.data
  const peakBookings = Math.max(1, ...stats.bookings_trend.map((d) => d.bookings))

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>Good to see you, {user?.name.split(' ')[0]}</h1>
          <p>
            {isAdmin
              ? 'Platform-wide figures across every restaurant in the directory.'
              : 'Figures for the restaurants you own.'}
          </p>
        </div>
        <Link to="/admin/restaurants/new" className="btn_1">+ New restaurant</Link>
      </div>

      {/* ------------------------------------------------------------ headline */}
      <div className="stat-grid">
        <div className="stat-card">
          <div className="label">Restaurants</div>
          <div className="value">{stats.restaurants.total}</div>
          <div className="hint">{stats.restaurants.published} published · {stats.restaurants.draft} draft</div>
        </div>
        <div className="stat-card">
          <div className="label">Bookings today</div>
          <div className="value accent">{stats.bookings.today}</div>
          <div className="hint">{stats.bookings.upcoming} upcoming in total</div>
        </div>
        <div className="stat-card">
          <div className="label">Covers next 7 days</div>
          <div className="value">{stats.bookings.covers_next_7_days}</div>
          <div className="hint">{stats.bookings.pending} awaiting confirmation</div>
        </div>
        <div className="stat-card">
          <div className="label">Average score</div>
          <div className="value">{stats.reviews.average_score.toFixed(1)}</div>
          <div className="hint">across {stats.reviews.approved} published reviews</div>
        </div>
        {stats.reviews.pending > 0 && (
          <div className="stat-card">
            <div className="label">Needs moderation</div>
            <div className="value accent">{stats.reviews.pending}</div>
            <div className="hint"><Link to="/admin/reviews?status=pending">Review the queue →</Link></div>
          </div>
        )}
        {stats.users && (
          <div className="stat-card">
            <div className="label">Registered users</div>
            <div className="value">{stats.users.total}</div>
            <div className="hint">{stats.users.owners} owners · {stats.users.customers} diners</div>
          </div>
        )}
      </div>

      <div className="row">
        <div className="col-lg-7">
          {/* -------------------------------------------------------- trend */}
          <div className="admin-panel">
            <h3>Bookings taken — last 14 days</h3>
            <div className="trend-chart">
              {stats.bookings_trend.map((day) => (
                <div className="bar" key={day.date} title={`${day.date}: ${day.bookings}`}>
                  <span style={{ height: `${(day.bookings / peakBookings) * 100}px` }} />
                </div>
              ))}
            </div>
            <div className="trend-labels">
              <span>{stats.bookings_trend[0]?.date}</span>
              <span>peak {peakBookings}/day</span>
              <span>{stats.bookings_trend.at(-1)?.date}</span>
            </div>
          </div>

          {/* ------------------------------------------------ recent bookings */}
          <div className="admin-panel">
            <h3>Latest bookings</h3>

            {stats.recent_bookings.length === 0 ? (
              <EmptyState icon="icon_calendar" title="No bookings yet" />
            ) : (
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
                    </tr>
                  </thead>
                  <tbody>
                    {stats.recent_bookings.map((booking) => (
                      <tr key={booking.id}>
                        <td><code>{booking.reference}</code></td>
                        <td>{booking.guest.name}</td>
                        <td>{booking.restaurant?.name ?? '—'}</td>
                        <td>{formatDate(booking.booking_date)} · {booking.booking_time}</td>
                        <td>{booking.party_size}</td>
                        <td><span className={`foogra-badge ${booking.status}`}>{booking.status_label}</span></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="foogra-form-actions">
              <Link to="/admin/bookings" className="btn_1 outline small">Open the reservation book</Link>
            </div>
          </div>
        </div>

        <div className="col-lg-5">
          {/* ------------------------------------------------ top restaurants */}
          <div className="admin-panel">
            <h3>Best performing</h3>

            {stats.top_restaurants.length === 0 ? (
              <EmptyState icon="icon_house_alt" title="Nothing published yet" />
            ) : (
              <table className="admin-table">
                <thead>
                  <tr><th>Restaurant</th><th>Score</th><th>Bookings</th></tr>
                </thead>
                <tbody>
                  {stats.top_restaurants.map((restaurant) => (
                    <tr key={restaurant.id}>
                      <td><Link to={`/restaurants/${restaurant.slug}`}>{restaurant.name}</Link></td>
                      <td>{restaurant.rating.toFixed(1)} <small className="foogra-muted">({restaurant.reviews})</small></td>
                      <td>{restaurant.bookings}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {/* ------------------------------------------------ moderation queue */}
          <div className="admin-panel">
            <h3>Waiting for moderation</h3>

            {stats.reviews_awaiting_moderation.length === 0 ? (
              <EmptyState icon="icon_check_alt2" title="Queue is clear" />
            ) : (
              <>
                {stats.reviews_awaiting_moderation.map((review) => (
                  <div key={review.id} style={{ paddingBottom: 12, marginBottom: 12, borderBottom: '1px solid #f4f4f4' }}>
                    <strong>“{review.title}”</strong>
                    <div className="foogra-muted" style={{ fontSize: 12 }}>
                      {review.author?.name} on {review.restaurant?.name} · scored{' '}
                      {review.ratings.overall.toFixed(1)}
                    </div>
                  </div>
                ))}
                <Link to="/admin/reviews?status=pending" className="btn_1 outline small">
                  Moderate {stats.reviews.pending} review{stats.reviews.pending === 1 ? '' : 's'}
                </Link>
              </>
            )}
          </div>
        </div>
      </div>
    </>
  )
}
