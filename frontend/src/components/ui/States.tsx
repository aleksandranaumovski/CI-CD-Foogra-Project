import type { ReactNode } from 'react'

/** Skeleton block used while a query is in flight. */
export function Skeleton({ height = 20, width = '100%', radius = 6, className = '' }: {
  height?: number | string
  width?: number | string
  radius?: number
  className?: string
}) {
  return <span className={`foogra-skeleton ${className}`} style={{ height, width, borderRadius: radius }} />
}

/** Placeholder shaped like a restaurant card, so the grid does not jump. */
export function StripSkeleton() {
  return (
    <div className="strip foogra-strip-skeleton">
      <figure>
        <Skeleton height={200} radius={0} />
      </figure>
      <div style={{ padding: '15px' }}>
        <Skeleton height={14} width="45%" />
        <Skeleton height={18} width="80%" className="mt-2" />
        <Skeleton height={12} width="60%" className="mt-2" />
      </div>
    </div>
  )
}

export function EmptyState({
  icon = 'icon_search',
  title,
  children,
}: {
  icon?: string
  title: string
  children?: ReactNode
}) {
  return (
    <div className="foogra-empty">
      <i className={icon} />
      <h4>{title}</h4>
      {children && <p>{children}</p>}
    </div>
  )
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="foogra-empty error">
      <i className="icon_error-triangle_alt" />
      <h4>Something went wrong</h4>
      <p>{message}</p>
      {onRetry && (
        <button type="button" className="btn_1" onClick={onRetry}>
          Try again
        </button>
      )}
    </div>
  )
}

/** Centred spinner for full-page loads. */
export function Loader({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="foogra-loader">
      <span className="foogra-spinner" />
      <span>{label}</span>
    </div>
  )
}
