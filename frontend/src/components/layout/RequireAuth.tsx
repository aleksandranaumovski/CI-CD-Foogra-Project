import { useEffect } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import type { ReactNode } from 'react'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { Loader } from '../ui/States'

interface RequireAuthProps {
  children: ReactNode
  /** Restricts to admins and restaurant owners. */
  staffOnly?: boolean
  /** Restricts to admins alone. */
  adminOnly?: boolean
}

/**
 * Route guard. Waits for the stored token to be resolved before deciding, so a
 * page refresh on a protected route does not bounce the user to the home page
 * before the session has loaded.
 */
export function RequireAuth({ children, staffOnly = false, adminOnly = false }: RequireAuthProps) {
  const { loading, isAuthenticated, isStaff, isAdmin } = useAuth()
  const { openSignIn, notify } = useUiStore()
  const location = useLocation()

  useEffect(() => {
    if (!loading && !isAuthenticated) {
      openSignIn()
    }
  }, [loading, isAuthenticated, openSignIn])

  useEffect(() => {
    if (loading || !isAuthenticated) return

    if (adminOnly && !isAdmin) notify('That area is for administrators only.', 'error')
    else if (staffOnly && !isStaff) notify('That area is for restaurant owners and administrators.', 'error')
  }, [loading, isAuthenticated, adminOnly, staffOnly, isAdmin, isStaff, notify])

  if (loading) {
    return <div className="container margin_60_40"><Loader label="Checking your session…" /></div>
  }

  if (!isAuthenticated) {
    return <Navigate to="/" replace state={{ from: location.pathname }} />
  }

  if (adminOnly && !isAdmin) return <Navigate to="/admin" replace />
  if (staffOnly && !isStaff) return <Navigate to="/" replace />

  return <>{children}</>
}
