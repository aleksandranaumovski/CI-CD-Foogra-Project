import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import type { ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { tokenStore } from '../lib/api'
import { auth as authApi, type Credentials, type RegistrationPayload } from '../lib/services'
import type { User } from '../lib/types'

interface AuthContextValue {
  user: User | null
  /** True until the stored token has been checked against the API. */
  loading: boolean
  isAuthenticated: boolean
  isStaff: boolean
  isAdmin: boolean
  login: (credentials: Credentials) => Promise<User>
  register: (payload: RegistrationPayload) => Promise<User>
  logout: () => Promise<void>
  refresh: () => Promise<void>
  setUser: (user: User) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const queryClient = useQueryClient()

  // Resolve the stored token once on boot, so a refresh keeps the session.
  useEffect(() => {
    let cancelled = false

    async function bootstrap() {
      if (!tokenStore.get()) {
        setLoading(false)

        return
      }

      try {
        const me = await authApi.me()
        if (!cancelled) setUser(me)
      } catch {
        // The interceptor already cleared the dead token.
        if (!cancelled) setUser(null)
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    void bootstrap()

    return () => {
      cancelled = true
    }
  }, [])

  const login = useCallback(
    async (credentials: Credentials) => {
      const { user: signedIn, token } = await authApi.login(credentials)
      tokenStore.set(token)
      setUser(signedIn)
      // Wishlist flags and vote state differ per user — refetch everything.
      await queryClient.invalidateQueries()

      return signedIn
    },
    [queryClient],
  )

  const register = useCallback(
    async (payload: RegistrationPayload) => {
      const { user: created, token } = await authApi.register(payload)
      tokenStore.set(token)
      setUser(created)
      await queryClient.invalidateQueries()

      return created
    },
    [queryClient],
  )

  const logout = useCallback(async () => {
    try {
      await authApi.logout()
    } catch {
      // Signing out locally matters more than the server round trip succeeding.
    }

    tokenStore.clear()
    setUser(null)
    queryClient.clear()
  }, [queryClient])

  const refresh = useCallback(async () => {
    if (!tokenStore.get()) return

    try {
      setUser(await authApi.me())
    } catch {
      setUser(null)
    }
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      isAuthenticated: user !== null,
      isStaff: user?.role === 'admin' || user?.role === 'owner',
      isAdmin: user?.role === 'admin',
      login,
      register,
      logout,
      refresh,
      setUser,
    }),
    [user, loading, login, register, logout, refresh],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used inside an <AuthProvider>.')
  }

  return context
}
