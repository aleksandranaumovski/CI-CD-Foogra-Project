import { Link } from 'react-router-dom'
import type { RestaurantCard } from '../../lib/types'
import { WishlistButton } from './WishlistButton'

interface RestaurantStripProps {
  restaurant: RestaurantCard
  /** `price` matches the listing grid; `status` matches the home carousel. */
  footer?: 'price' | 'status'
}

/**
 * A Foogra "strip" card. Class names are taken verbatim from the template so
 * the original stylesheet renders it without a single CSS override.
 */
export function RestaurantStrip({ restaurant, footer = 'price' }: RestaurantStripProps) {
  const { rating } = restaurant

  return (
    <div className="strip">
      <figure>
        {restaurant.discount_percent ? (
          <span className="ribbon off">-{restaurant.discount_percent}%</span>
        ) : null}

        <WishlistButton slug={restaurant.slug} saved={Boolean(restaurant.is_wishlisted)} />

        <img
          src={restaurant.thumbnail ?? '/img/lazy-placeholder.png'}
          className="img-fluid"
          alt={restaurant.name}
          loading="lazy"
        />

        <Link to={`/restaurants/${restaurant.slug}`} className="strip_info">
          <small>{restaurant.category?.name ?? 'Restaurant'}</small>
          <div className="item_title">
            <h3>{restaurant.name}</h3>
            <small>{restaurant.address}</small>
          </div>
        </Link>
      </figure>

      <ul>
        <li>
          {footer === 'status' ? (
            <span className={restaurant.is_open_now ? 'loc_open' : 'loc_closed'}>
              {restaurant.is_open_now ? 'Now Open' : 'Now Closed'}
            </span>
          ) : (
            <span>
              Avg. Price {Math.round(restaurant.average_price)}$
              {typeof restaurant.distance_km === 'number' && ` · ${restaurant.distance_km} km`}
            </span>
          )}
        </li>
        <li>
          <div className="score">
            <span>
              {rating.label}
              <em>
                {rating.reviews_count} {rating.reviews_count === 1 ? 'Review' : 'Reviews'}
              </em>
            </span>
            <strong>{rating.score.toFixed(1)}</strong>
          </div>
        </li>
      </ul>
    </div>
  )
}
