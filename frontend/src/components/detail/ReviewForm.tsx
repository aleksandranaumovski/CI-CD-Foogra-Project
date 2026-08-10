import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { diner } from '../../lib/services'
import { fieldError, toApiError } from '../../lib/api'
import { useUiStore } from '../../context/UiContext'
import type { ApiError, Restaurant } from '../../lib/types'

interface ReviewFormProps {
  restaurant: Restaurant
  onDone: () => void
  onCancel: () => void
}

const CRITERIA = [
  { key: 'rating_food', label: 'Food quality' },
  { key: 'rating_service', label: 'Service' },
  { key: 'rating_location', label: 'Location' },
  { key: 'rating_price', label: 'Price' },
] as const

/**
 * The template's leave-a-review form, inlined into the Reviews tab. Scores are
 * out of 10 in half-point steps, matching the API's validation exactly so a
 * slider position can never produce a 422.
 */
export function ReviewForm({ restaurant, onDone, onCancel }: ReviewFormProps) {
  const { notify } = useUiStore()
  const queryClient = useQueryClient()
  const [error, setError] = useState<ApiError | null>(null)
  const [form, setForm] = useState({
    title: '',
    body: '',
    rating_food: 8,
    rating_service: 8,
    rating_location: 8,
    rating_price: 8,
  })

  const average =
    (form.rating_food + form.rating_service + form.rating_location + form.rating_price) / 4

  const submit = useMutation({
    mutationFn: () => diner.createReview(restaurant.slug, form),
    onSuccess: () => {
      notify('Thanks — your review is queued for moderation.')
      void queryClient.invalidateQueries({ queryKey: ['restaurant', restaurant.slug] })
      onDone()
    },
    onError: (err) => setError(toApiError(err)),
  })

  return (
    <form
      className="foogra-review-form"
      onSubmit={(e) => { e.preventDefault(); setError(null); submit.mutate() }}
    >
      <h4>Write a review for {restaurant.name}</h4>

      {error && !error.errors && <div className="foogra-alert error">{error.message}</div>}

      <div className="row">
        {CRITERIA.map((criterion) => (
          <div className="col-md-6" key={criterion.key}>
            <div className="foogra-score-input">
              <label htmlFor={criterion.key}>
                {criterion.label}
                <strong>{form[criterion.key].toFixed(1)}</strong>
              </label>
              <input
                id={criterion.key}
                type="range"
                min={1}
                max={10}
                step={0.5}
                value={form[criterion.key]}
                onChange={(e) => setForm({ ...form, [criterion.key]: Number(e.target.value) })}
              />
              {fieldError(error, criterion.key) && (
                <small className="foogra-field-error">{fieldError(error, criterion.key)}</small>
              )}
            </div>
          </div>
        ))}
      </div>

      <div className="foogra-review-average">
        Your overall score: <strong>{average.toFixed(1)}</strong> / 10
      </div>

      <div className="form-group">
        <label htmlFor="review_title">Headline</label>
        <input
          id="review_title"
          type="text"
          className="form-control"
          required
          minLength={3}
          maxLength={160}
          placeholder="e.g. Really great dinner"
          value={form.title}
          onChange={(e) => setForm({ ...form, title: e.target.value })}
        />
        {fieldError(error, 'title') && <small className="foogra-field-error">{fieldError(error, 'title')}</small>}
      </div>

      <div className="form-group">
        <label htmlFor="review_body">Your review</label>
        <textarea
          id="review_body"
          className="form-control"
          rows={5}
          required
          minLength={20}
          maxLength={5000}
          placeholder="What did you order? How was the service? Would you go back?"
          value={form.body}
          onChange={(e) => setForm({ ...form, body: e.target.value })}
        />
        <small className="foogra-muted">{form.body.length} / 5000 — at least 20 characters.</small>
        {fieldError(error, 'body') && <small className="foogra-field-error">{fieldError(error, 'body')}</small>}
      </div>

      <div className="foogra-form-actions">
        <button type="submit" className="btn_1" disabled={submit.isPending}>
          {submit.isPending ? 'Submitting…' : 'Submit review'}
        </button>
        <button type="button" className="btn_1 outline" onClick={onCancel}>Cancel</button>
      </div>
    </form>
  )
}
