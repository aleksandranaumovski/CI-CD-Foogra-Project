import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../../context/AuthContext'
import { useUiStore } from '../../context/UiContext'
import { toApiError } from '../../lib/api'
import { diner } from '../../lib/services'

interface WishlistButtonProps {
  slug: string
  saved: boolean
  /** `heart` for the card overlay, `hero` for the detail page's button. */
  variant?: 'heart' | 'hero'
}

/**
 * Optimistically flips the heart, then reconciles with the server. Signed-out
 * visitors get the sign-in dialog instead of a silent failure.
 */
export function WishlistButton({ slug, saved, variant = 'heart' }: WishlistButtonProps) {
  const { isAuthenticated } = useAuth()
  const { openSignIn, notify } = useUiStore()
  const queryClient = useQueryClient()

  const mutation = useMutation({
    mutationFn: () => diner.toggleWishlist(slug),
    onSuccess: (result) => {
      notify(result.message)
      void queryClient.invalidateQueries({ queryKey: ['wishlist'] })
      void queryClient.invalidateQueries({ queryKey: ['restaurants'] })
      void queryClient.invalidateQueries({ queryKey: ['restaurant', slug] })
    },
    onError: (error) => notify(toApiError(error).message, 'error'),
  })

  const onClick = (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()

    if (!isAuthenticated) {
      openSignIn()

      return
    }

    mutation.mutate()
  }

  const label = saved ? 'Remove from wishlist' : 'Save to wishlist'

  if (variant === 'hero') {
    return (
      <a href="#0" className={`btn_hero wishlist${saved ? ' active' : ''}`} onClick={onClick} title={label}>
        <i className="icon_heart" />
        {saved ? 'Saved' : 'Wishlist'}
      </a>
    )
  }

  return (
    <button
      type="button"
      className={`foogra-heart${saved ? ' is-saved' : ''}`}
      onClick={onClick}
      disabled={mutation.isPending}
      aria-label={label}
      title={label}
    >
      <i className={saved ? 'icon_heart' : 'icon_heart_alt'} />
    </button>
  )
}
