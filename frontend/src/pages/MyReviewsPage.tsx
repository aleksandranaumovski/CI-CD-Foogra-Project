import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { diner } from '../lib/services'
import { toApiError } from '../lib/api'
import { useUiStore } from '../context/UiContext'
import { Loader, EmptyState, ErrorState } from '../components/ui/States'
import { Pagination } from '../components/ui/Pagination'
import type { Review } from '../lib/types'

export function MyReviewsPage() {
  const [page, setPage] = useState(1)
  const query = useQuery({ queryKey: ['my-reviews', page], queryFn: () => diner.myReviews(page) })

  return (
    <div className="container">
      <div className="foogra-page-head">
        <h1>My reviews</h1>
        <p className="foogra-muted">
          Reviews are checked before they appear publicly, so a new one may sit as “pending” for a while.
        </p>
      </div>

      {query.isPending && <Loader label="Loading your reviews…" />}
      {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}

      {query.data?.data.length === 0 && (
        <EmptyState icon="icon_pencil-edit" title="You have not written a review yet">
          Been somewhere good? <Link to="/restaurants">Find it</Link> and tell other diners about it.
        </EmptyState>
      )}

      {query.data?.data.map((review) => <MyReviewRow key={review.id} review={review} />)}

      {query.data && (
        <Pagination
          currentPage={query.data.meta.current_page}
          lastPage={query.data.meta.last_page}
          onChange={setPage}
        />
      )}
    </div>
  )
}

function MyReviewRow({ review }: { review: Review }) {
  const { notify } = useUiStore()
  const queryClient = useQueryClient()
  const [confirming, setConfirming] = useState(false)

  const remove = useMutation({
    mutationFn: () => diner.deleteReview(review.id),
    onSuccess: () => {
      notify('Review deleted.')
      void queryClient.invalidateQueries({ queryKey: ['my-reviews'] })
    },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  return (
    <div className="foogra-booking-card">
      <img
        src={review.restaurant?.thumbnail ?? '/img/location_list_placeholder.png'}
        alt={review.restaurant?.name ?? ''}
      />

      <div className="details">
        <div className="d-flex justify-content-between align-items-start gap-2 flex-wrap">
          <h4>
            {review.restaurant ? (
              <Link to={`/restaurants/${review.restaurant.slug}`}>{review.restaurant.name}</Link>
            ) : (
              'Restaurant'
            )}
          </h4>
          <span className={`foogra-badge ${review.status}`}>{review.status}</span>
        </div>

        <p style={{ marginTop: 8 }}>
          <strong>“{review.title}”</strong> — scored <strong>{review.ratings.overall.toFixed(1)}</strong>/10
        </p>
        <p className="foogra-muted">{review.body}</p>

        {review.reply && (
          <div className="foogra-alert success" style={{ marginTop: 10 }}>
            <strong>Reply from the restaurant:</strong> {review.reply.body}
          </div>
        )}

        <div className="foogra-form-actions">
          {!confirming ? (
            <button type="button" className="btn_1 outline small" onClick={() => setConfirming(true)}>
              Delete review
            </button>
          ) : (
            <>
              <button
                type="button"
                className="btn_1 danger small"
                onClick={() => remove.mutate()}
                disabled={remove.isPending}
              >
                {remove.isPending ? 'Deleting…' : 'Yes, delete'}
              </button>
              <button type="button" className="btn_1 outline small" onClick={() => setConfirming(false)}>
                Cancel
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
