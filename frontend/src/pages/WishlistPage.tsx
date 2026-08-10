import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { diner } from '../lib/services'
import { toApiError } from '../lib/api'
import { RestaurantStrip } from '../components/ui/RestaurantStrip'
import { StripSkeleton, EmptyState, ErrorState } from '../components/ui/States'

export function WishlistPage() {
  const query = useQuery({ queryKey: ['wishlist'], queryFn: diner.wishlist })

  return (
    <div className="container">
      <div className="foogra-page-head">
        <h1>Your wishlist</h1>
        <p className="foogra-muted">
          {query.data ? `${query.data.length} saved restaurant${query.data.length === 1 ? '' : 's'}` : 'Places you saved for later'}
        </p>
      </div>

      {query.isPending && (
        <div className="row">
          {Array.from({ length: 3 }).map((_, i) => (
            <div className="col-xl-4 col-lg-6 col-md-6" key={i}><StripSkeleton /></div>
          ))}
        </div>
      )}

      {query.isError && <ErrorState message={toApiError(query.error).message} onRetry={() => query.refetch()} />}

      {query.data?.length === 0 && (
        <EmptyState icon="icon_heart_alt" title="Nothing saved yet">
          Tap the heart on any restaurant to keep it here.{' '}
          <Link to="/restaurants">Start browsing</Link>.
        </EmptyState>
      )}

      <div className="row">
        {query.data?.map((restaurant) => (
          <div className="col-xl-4 col-lg-6 col-md-6" key={restaurant.id}>
            <RestaurantStrip restaurant={restaurant} />
          </div>
        ))}
      </div>
    </div>
  )
}
