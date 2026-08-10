import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { admin, diner } from '../../lib/services'
import { toApiError } from '../../lib/api'
import { useUiStore } from '../../context/UiContext'
import { Loader, ErrorState, EmptyState } from '../../components/ui/States'
import { Pagination } from '../../components/ui/Pagination'
import { ConfirmButton } from '../../components/admin/ConfirmButton'
import type { Review } from '../../lib/types'

/** The moderation queue: approve, reject, reply to or delete each review. */
export function AdminReviewsPage() {
  const [searchParams, setSearchParams] = useSearchParams()
  const [page, setPage] = useState(1)
  const status = searchParams.get('status') ?? ''

  const query = useQuery({
    queryKey: ['admin', 'reviews', { page, status }],
    queryFn: () => admin.reviews({ page, status, per_page: 15 }),
    placeholderData: keepPreviousData,
  })

  const pending = query.data?.meta.pending_total ?? 0

  return (
    <>
      <div className="admin-topbar">
        <div>
          <h1>Reviews</h1>
          <p>
            {pending > 0
              ? `${pending} review${pending === 1 ? '' : 's'} waiting for a decision.`
              : 'Nothing waiting — the queue is clear.'}
          </p>
        </div>
      </div>

      <div className="admin-panel">
        <div className="foogra-tabs">
          {[
            { value: '', label: 'All' },
            { value: 'pending', label: `Pending${pending > 0 ? ` (${pending})` : ''}` },
            { value: 'approved', label: 'Published' },
            { value: 'rejected', label: 'Rejected' },
          ].map((tab) => (
            <button
              key={tab.value}
              type="button"
              className={status === tab.value ? 'active' : undefined}
              onClick={() => {
                setPage(1)
                const next = new URLSearchParams(searchParams)
                tab.value ? next.set('status', tab.value) : next.delete('status')
                setSearchParams(next)
              }}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {query.isPending && !query.data && <Loader />}
        {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}

        {query.data?.data.length === 0 && (
          <EmptyState icon="icon_comment_alt" title="No reviews here" />
        )}

        {query.data?.data.map((review) => <ModerationRow key={review.id} review={review} />)}

        {query.data && (
          <Pagination
            currentPage={query.data.meta.current_page}
            lastPage={query.data.meta.last_page}
            onChange={setPage}
          />
        )}
      </div>
    </>
  )
}

function ModerationRow({ review }: { review: Review }) {
  const { notify } = useUiStore()
  const queryClient = useQueryClient()
  const [replying, setReplying] = useState(false)
  const [replyBody, setReplyBody] = useState(review.reply?.body ?? '')

  const refresh = () => {
    void queryClient.invalidateQueries({ queryKey: ['admin'] })
    void queryClient.invalidateQueries({ queryKey: ['restaurants'] })
  }

  const fail = (error: unknown) => notify(toApiError(error).message, 'error')

  const moderate = useMutation({
    mutationFn: (status: 'approved' | 'rejected' | 'pending') => admin.moderateReview(review.id, status),
    onSuccess: (updated) => { notify(`Review ${updated.status}.`); refresh() },
    onError: fail,
  })

  const remove = useMutation({
    mutationFn: () => admin.deleteReview(review.id),
    onSuccess: () => { notify('Review deleted.'); refresh() },
    onError: fail,
  })

  const reply = useMutation({
    mutationFn: () => diner.replyToReview(review.id, replyBody),
    onSuccess: () => { notify('Reply published.'); setReplying(false); refresh() },
    onError: fail,
  })

  return (
    <div style={{ padding: '18px 0', borderBottom: '1px solid #f0f0f0' }}>
      <div className="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div style={{ flex: 1, minWidth: 260 }}>
          <div className="d-flex align-items-center gap-2 flex-wrap" style={{ marginBottom: 6 }}>
            <strong style={{ fontSize: 16 }}>“{review.title}”</strong>
            <span className={`foogra-badge ${review.status}`}>{review.status}</span>
            <span className="foogra-badge">{review.ratings.overall.toFixed(1)} / 10</span>
          </div>

          <div className="foogra-muted" style={{ fontSize: 13, marginBottom: 8 }}>
            {review.author?.name ?? 'A diner'} on{' '}
            {review.restaurant ? (
              <Link to={`/restaurants/${review.restaurant.slug}`} target="_blank">{review.restaurant.name}</Link>
            ) : (
              'a restaurant'
            )}
            {' · '}
            food {review.ratings.food} · service {review.ratings.service} ·
            location {review.ratings.location} · price {review.ratings.price}
          </div>

          <p style={{ marginBottom: 8 }}>{review.body}</p>

          {review.reply && !replying && (
            <div className="foogra-alert success">
              <strong>Your reply:</strong> {review.reply.body}
            </div>
          )}

          {replying && (
            <div style={{ marginTop: 10 }}>
              <textarea
                className="form-control"
                rows={3}
                value={replyBody}
                onChange={(e) => setReplyBody(e.target.value)}
                placeholder="Reply publicly as the restaurant…"
              />
              <div className="foogra-form-actions">
                <button
                  type="button"
                  className="btn_1 small"
                  onClick={() => reply.mutate()}
                  disabled={reply.isPending || replyBody.trim().length < 5}
                >
                  {reply.isPending ? 'Publishing…' : 'Publish reply'}
                </button>
                <button type="button" className="btn_1 outline small" onClick={() => setReplying(false)}>
                  Cancel
                </button>
              </div>
            </div>
          )}
        </div>

        <div className="foogra-form-actions" style={{ margin: 0, flexDirection: 'column', alignItems: 'stretch', minWidth: 150 }}>
          {review.status !== 'approved' && (
            <button type="button" className="btn_1 small" onClick={() => moderate.mutate('approved')}>
              Approve
            </button>
          )}
          {review.status !== 'rejected' && (
            <button type="button" className="btn_1 outline small" onClick={() => moderate.mutate('rejected')}>
              Reject
            </button>
          )}
          {!replying && (
            <button type="button" className="btn_1 outline small" onClick={() => setReplying(true)}>
              {review.reply ? 'Edit reply' : 'Reply'}
            </button>
          )}
          <ConfirmButton className="btn_1 outline small" onConfirm={() => remove.mutate()}>
            Delete
          </ConfirmButton>
        </div>
      </div>
    </div>
  )
}
