import { useUiStore } from '../../context/UiContext'

/** Bottom-right toast stack for save confirmations and API errors. */
export function Toasts() {
  const { toasts, dismiss } = useUiStore()

  if (toasts.length === 0) return null

  return (
    <div className="foogra-toasts" role="status" aria-live="polite">
      {toasts.map((toast) => (
        <div key={toast.id} className={`foogra-toast ${toast.tone}`} onClick={() => dismiss(toast.id)}>
          <i className={toast.tone === 'success' ? 'icon_check_alt2' : 'icon_error-triangle_alt'} />
          <span>{toast.message}</span>
        </div>
      ))}
    </div>
  )
}
