import { Link, NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../context/AuthContext'
import { admin } from '../../lib/services'
import { Logo } from '../../components/layout/Logo'
import { Toasts } from '../../components/layout/Toasts'

/** The dashboard shell: sidebar navigation plus the routed page. */
export function AdminLayout() {
  const { user, isAdmin, logout } = useAuth()
  const navigate = useNavigate()

  // Powers the "needs moderation" badge next to Reviews.
  const stats = useQuery({ queryKey: ['admin', 'stats'], queryFn: admin.stats, staleTime: 60_000 })
  const pendingReviews = stats.data?.reviews.pending ?? 0

  return (
    <div className="admin-shell">
      <aside className="admin-sidebar">
        <div className="brand">
          <Link to="/" className="foogra-logo">
            <Logo width={124} />
          </Link>
          <small>{isAdmin ? 'ADMIN DASHBOARD' : 'OWNER DASHBOARD'}</small>
        </div>

        <nav>
          <NavLink to="/admin" end><i className="icon-grid-2" />Overview</NavLink>
          <NavLink to="/admin/restaurants"><i className="icon_house_alt" />Restaurants</NavLink>
          <NavLink to="/admin/bookings"><i className="icon_calendar" />Bookings</NavLink>
          <NavLink to="/admin/reviews">
            <i className="icon_comment_alt" />Reviews
            {pendingReviews > 0 && <span className="count">{pendingReviews}</span>}
          </NavLink>
          <NavLink to="/admin/categories"><i className="icon_tags_alt" />Categories</NavLink>
          {isAdmin && <NavLink to="/admin/users"><i className="icon_profile" />Users</NavLink>}
        </nav>

        <div className="foot">
          <strong style={{ color: '#fff' }}>{user?.name}</strong>
          <div style={{ fontSize: 12, color: '#6f7480', marginBottom: 8 }}>{user?.role_label}</div>
          <Link to="/">← Back to the site</Link>
          <a
            href="#0"
            onClick={async (e) => { e.preventDefault(); await logout(); navigate('/') }}
          >
            Sign out
          </a>
        </div>
      </aside>

      <div className="admin-main">
        <Outlet />
      </div>

      <Toasts />
    </div>
  )
}
