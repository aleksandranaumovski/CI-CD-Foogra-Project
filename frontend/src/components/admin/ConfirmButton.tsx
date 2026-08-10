import { useEffect, useState } from 'react'
import type { ReactNode } from 'react'

interface ConfirmButtonProps {
  children: ReactNode
  onConfirm: () => void
  confirmLabel?: string
  className?: string
  title?: string
  disabled?: boolean
}

/**
 * Two-step destructive action. The first click swaps the label for a
 * confirmation; a second click within five seconds commits. Avoids
 * `window.confirm`, which blocks the page and cannot be styled.
 */
export function ConfirmButton({
  children,
  onConfirm,
  confirmLabel = 'Sure?',
  className = 'btn_1 outline small',
  title,
  disabled = false,
}: ConfirmButtonProps) {
  const [armed, setArmed] = useState(false)

  useEffect(() => {
    if (!armed) return

    const timer = window.setTimeout(() => setArmed(false), 5000)

    return () => window.clearTimeout(timer)
  }, [armed])

  return (
    <button
      type="button"
      className={armed ? `${className} danger` : className}
      title={title}
      disabled={disabled}
      onClick={() => {
        if (armed) {
          onConfirm()
          setArmed(false)
        } else {
          setArmed(true)
        }
      }}
    >
      {armed ? confirmLabel : children}
    </button>
  )
}
