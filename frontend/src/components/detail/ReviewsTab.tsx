import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { catalogue, diner } from '../../lib/services'
import { AVATAR_FALLBACK, toApiError } from '../../lib/api'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { Loader, EmptyState } from '../ui/States'
import { Pagination } from '../ui/Pagination'
import { ReviewForm } from './ReviewForm'
import type { RatingBreakdown, Restaurant, Review } from '../../lib/types'

interface ReviewsTabProps {
  restaurant: Restaurant
  breakdown: RatingBreakdown
}

const SORTS = [
  { value: 'recent', label: 'Most recent' },
  { value: 'helpful', label: 'Most helpful' },
  { value: 'highest', label: 'Highest rated' },
  { value: 'lowest', label: 'Lowest rated' },
]

/** The detail page's Reviews tab: score summary, review list, and the form. */
export function ReviewsTab({ restaurant, breakdown }: ReviewsTabProps) {
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState('recent')
  const [writing, setWriting] = useState(false)
  const { isAuthenticated, user } = useAuth()
  const { openSignIn } = useUiStore()

  const reviews = useQuery({
    queryKey: ['reviews', restaurant.slug, page, sort],
    queryFn: () => catalogue.reviews(restaurant.slug, page, sort),
  })

  const ownsThisVenue = user?.id === restaurant.owner?.id
  const alreadyReviewed = reviews.data?.data.some((review) => review.author?.id === user?.id) ?? false

  return (
    <div className="card-body reviews">
      {/* ------------------------------------------------------ score summary */}
      <div className="row add_bottom_45 d-flex align-items-center">
        <div className="col-md-3">
          <div id="review_summary">
            <strong>{breakdown.overall.toFixed(1)}</strong>
            <em>{breakdown.label}</em>
            <small>Based on {breakdown.total} review{breakdown.total === 1 ? '' : 's'}</small>
          </div>
        </div>

        <div className="col-md-9 reviews_sum_details">
          <div className="row">
            <div className="col-md-6">
              <ScoreBar label="Food Quality" score={breakdown.food} />
              <ScoreBar label="Service" score={breakdown.service} />
            </div>
            <div className="col-md-6">
              <ScoreBar label="Location" score={breakdown.location} />
              <ScoreBar label="Price" score={breakdown.price} />
            </div>
          </div>
        </div>
      </div>

      {/* ------------------------------------------------------------ toolbar */}
      <div className="foogra-review-toolbar">
        <div className="styled-select">
          <select value={sort} onChange={(e) => { setSort(e.target.value); setPage(1) }} aria-label="Sort reviews">
            {SORTS.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </select>
        </div>

        {!writing && (
          <button
            type="button"
            className="btn_1"
            onClick={() => (isAuthenticated ? setWriting(true) : openSignIn())}
            disabled={ownsThisVenue || alreadyReviewed}
            title={
              ownsThisVenue
                ? 'Owners cannot review their own restaurant'
                : alreadyReviewed
                  ? 'You have already reviewed this restaurant'
                  : undefined
            }
          >
            Leave a review
          </button>
        )}
      </div>

      {writing && (
        <ReviewForm
          restaurant={restaurant}
          onDone={() => { setWriting(false); setPage(1); void reviews.refetch() }}
          onCancel={() => setWriting(false)}
        />
      )}

      {/* ------------------------------------------------------- review list */}
      {reviews.isPending && <Loader label="Loading reviews…" />}

      {reviews.data && reviews.data.data.length === 0 && (
        <EmptyState icon="icon_comment_alt" title="No reviews yet">
          Be the first to tell other diners what this place is like.
        </EmptyState>
      )}

      <div id="reviews">
        {reviews.data?.data.map((review) => (
          <ReviewCard key={review.id} review={review} restaurant={restaurant} />
        ))}
      </div>

      {reviews.data && (
        <Pagination
          currentPage={reviews.data.meta.current_page}
          lastPage={reviews.data.meta.last_page}
          onChange={setPage}
        />
      )}
    </div>
  )
}

function ScoreBar({ label, score }: { label: string; score: number }) {
  return (
    <>
      <h6>{label}</h6>
      <div className="row">
        <div className="col-xl-10 col-lg-9 col-9">
          <div className="progress">
            <div
              className="progress-bar"
              role="progressbar"
              style={{ width: `${Math.max(0, Math.min(100, score * 10))}%` }}
              aria-valuenow={score * 10}
              aria-valuemin={0}
              aria-valuemax={100}
            />
          </div>
        </div>
        <div className="col-xl-2 col-lg-3 col-3"><strong>{score.toFixed(1)}</strong></div>
      </div>
    </>
  )
}

function ReviewCard({ review, restaurant }: { review: Review; restaurant: Restaurant }) {
  const { isAuthenticated, user } = useAuth()
  const { openSignIn, notify } = useUiStore()
  const queryClient = useQueryClient()
  const [replying, setReplying] = useState(false)
  const [replyBody, setReplyBody] = useState('')

  const isAuthor = user?.id === review.author?.id
  const canReply = user?.id === restaurant.owner?.id || user?.role === 'admin'

  const vote = useMutation({
    mutationFn: (helpful: boolean) =>
      review.votes.mine === helpful ? diner.removeVote(review.id) : diner.voteReview(review.id, helpful),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['reviews', restaurant.slug] }),
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  const reply = useMutation({
    mutationFn: () => diner.replyToReview(review.id, replyBody),
    onSuccess: () => {
      notify('Reply published.')
      setReplying(false)
      setReplyBody('')
      void queryClient.invalidateQueries({ queryKey: ['reviews', restaurant.slug] })
    },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  const onVote = (helpful: boolean) => {
    if (!isAuthenticated) {
      openSignIn()

      return
    }

    vote.mutate(helpful)
  }

  return (
    <div className="review_card">
      <div className="row">
        <div className="col-md-2 user_info">
          <figure>
            <img src={review.author?.avatar_url ?? AVATAR_FALLBACK} alt={review.author?.name ?? 'Diner'} />
          </figure>
          <h5>{review.author?.name ?? 'A diner'}</h5>
        </div>

        <div className="col-md-10 review_content">
          <div className="clearfix add_bottom_15">
            <span className="rating">
              {review.ratings.overall.toFixed(1)}<small>/10</small> <strong>Rating average</strong>
            </span>
            <em>{formatPublished(review.published_at ?? review.created_at)}</em>
          </div>

          <h4>&ldquo;{review.title}&rdquo;</h4>
          <p>{review.body}</p>

          <ul>
            <li>
              <a
                href="#0"
                className={review.votes.mine === true ? 'active' : undefined}
                onClick={(e) => { e.preventDefault(); onVote(true) }}
              >
                <i className="icon_like" />
                <span>Useful{review.votes.helpful > 0 ? ` (${review.votes.helpful})` : ''}</span>
              </a>
            </li>
            <li>
              <a
                href="#0"
                className={review.votes.mine === false ? 'active' : undefined}
                onClick={(e) => { e.preventDefault(); onVote(false) }}
              >
                <i className="icon_dislike" />
                <span>Not useful{review.votes.unhelpful > 0 ? ` (${review.votes.unhelpful})` : ''}</span>
              </a>
            </li>
            {canReply && !review.reply && !isAuthor && (
              <li>
                <a href="#0" onClick={(e) => { e.preventDefault(); setReplying(!replying) }}>
                  <i className="arrow_back" /> <span>Reply</span>
                </a>
              </li>
            )}
          </ul>

          {replying && (
            <form
              className="foogra-reply-form"
              onSubmit={(e) => { e.preventDefault(); reply.mutate() }}
            >
              <textarea
                className="form-control"
                rows={3}
                required
                minLength={5}
                placeholder="Reply as the restaurant…"
                value={replyBody}
                onChange={(e) => setReplyBody(e.target.value)}
              />
              <div className="foogra-form-actions">
                <button type="submit" className="btn_1" disabled={reply.isPending}>
                  {reply.isPending ? 'Publishing…' : 'Publish reply'}
                </button>
                <button type="button" className="btn_1 outline" onClick={() => setReplying(false)}>
                  Cancel
                </button>
              </div>
            </form>
          )}
        </div>
      </div>

      {review.reply && (
        <div className="row reply">
          <div className="col-md-2 user_info">
            <figure>
              <img src={review.reply.author?.avatar_url ?? AVATAR_FALLBACK} alt="" />
            </figure>
          </div>
          <div className="col-md-10">
            <div className="review_content">
              <strong>Reply from {restaurant.name}</strong>
              <em>{formatPublished(review.reply.created_at)}</em>
              <p><br />{review.reply.body}</p>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}

/** "54 minutes ago" for anything recent, an absolute date beyond a week. */
function formatPublished(iso: string): string {
  const then = new Date(iso)
  const minutes = Math.round((Date.now() - then.getTime()) / 60000)

  if (minutes < 1) return 'Published just now'
  if (minutes < 60) return `Published ${minutes} minute${minutes === 1 ? '' : 's'} ago`

  const hours = Math.round(minutes / 60)
  if (hours < 24) return `Published ${hours} hour${hours === 1 ? '' : 's'} ago`

  const days = Math.round(hours / 24)
  if (days <= 7) return `Published ${days} day${days === 1 ? '' : 's'} ago`

  return `Published ${then.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`
}
