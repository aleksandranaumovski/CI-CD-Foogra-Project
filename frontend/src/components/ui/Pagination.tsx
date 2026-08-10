interface PaginationProps {
  currentPage: number
  lastPage: number
  onChange: (page: number) => void
}

/**
 * The template's `.pagination_fg` control. Long ranges collapse to
 * `1 … 4 5 6 … 20` so the bar never wraps.
 */
export function Pagination({ currentPage, lastPage, onChange }: PaginationProps) {
  if (lastPage <= 1) return null

  const pages = pageWindow(currentPage, lastPage)

  return (
    <nav className="pagination_fg" aria-label="Pagination">
      <a
        href="#0"
        onClick={(e) => { e.preventDefault(); currentPage > 1 && onChange(currentPage - 1) }}
        aria-disabled={currentPage === 1}
        className={currentPage === 1 ? 'disabled' : undefined}
      >
        «
      </a>

      {pages.map((page, index) =>
        page === '…' ? (
          <span key={`gap-${index}`} className="foogra-page-gap">…</span>
        ) : (
          <a
            key={page}
            href="#0"
            className={page === currentPage ? 'active' : undefined}
            aria-current={page === currentPage ? 'page' : undefined}
            onClick={(e) => { e.preventDefault(); onChange(page) }}
          >
            {page}
          </a>
        ),
      )}

      <a
        href="#0"
        onClick={(e) => { e.preventDefault(); currentPage < lastPage && onChange(currentPage + 1) }}
        aria-disabled={currentPage === lastPage}
        className={currentPage === lastPage ? 'disabled' : undefined}
      >
        »
      </a>
    </nav>
  )
}

/** Always shows the first page, the last page, and two neighbours either side. */
function pageWindow(current: number, last: number): (number | '…')[] {
  if (last <= 7) {
    return Array.from({ length: last }, (_, i) => i + 1)
  }

  const pages = new Set<number>([1, last, current])

  for (let offset = 1; offset <= 2; offset++) {
    if (current - offset > 1) pages.add(current - offset)
    if (current + offset < last) pages.add(current + offset)
  }

  const sorted = [...pages].sort((a, b) => a - b)
  const out: (number | '…')[] = []

  sorted.forEach((page, index) => {
    if (index > 0 && page - sorted[index - 1] > 1) out.push('…')
    out.push(page)
  })

  return out
}
