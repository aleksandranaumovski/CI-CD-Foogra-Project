import { createContext, useCallback, useContext, useMemo, useState } from 'react'
import type { ReactNode } from 'react'

interface Toast {
  id: number
  message: string
  tone: 'success' | 'error'
}

interface UiContextValue {
  signInOpen: boolean
  openSignIn: () => void
  closeSignIn: () => void
  toasts: Toast[]
  notify: (message: string, tone?: Toast['tone']) => void
  dismiss: (id: number) => void
}

const UiContext = createContext<UiContextValue | null>(null)

let toastId = 0

/** Cross-cutting UI state: the sign-in modal and the toast stack. */
export function UiProvider({ children }: { children: ReactNode }) {
  const [signInOpen, setSignInOpen] = useState(false)
  const [toasts, setToasts] = useState<Toast[]>([])

  const dismiss = useCallback((id: number) => {
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }, [])

  const notify = useCallback(
    (message: string, tone: Toast['tone'] = 'success') => {
      const id = ++toastId
      setToasts((current) => [...current, { id, message, tone }])
      window.setTimeout(() => dismiss(id), 4500)
    },
    [dismiss],
  )

  const value = useMemo<UiContextValue>(
    () => ({
      signInOpen,
      openSignIn: () => setSignInOpen(true),
      closeSignIn: () => setSignInOpen(false),
      toasts,
      notify,
      dismiss,
    }),
    [signInOpen, toasts, notify, dismiss],
  )

  return <UiContext.Provider value={value}>{children}</UiContext.Provider>
}

export function useUiStore(): UiContextValue {
  const context = useContext(UiContext)

  if (!context) {
    throw new Error('useUiStore must be used inside a <UiProvider>.')
  }

  return context
}
