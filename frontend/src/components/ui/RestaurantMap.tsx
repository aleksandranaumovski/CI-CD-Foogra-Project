import { useEffect, useMemo } from 'react'
import { MapContainer, Marker, Popup, TileLayer, Circle, useMap, useMapEvents } from 'react-leaflet'
import { Icon } from 'leaflet'
import { Link } from 'react-router-dom'
import 'leaflet/dist/leaflet.css'
import type { RestaurantCard } from '../../lib/types'

/**
 * The listing page's map, replacing the template's Google Maps `#map` div.
 *
 * The template's variant needs a billed Google API key; its OpenStreetMap
 * variant uses Leaflet, so this follows that one — same markup and `.map`
 * class, so `listing.css` (which already sets height: 500px) styles it
 * unchanged. Same approach the port took for Owl Carousel -> Embla.
 */

/**
 * Leaflet's own marker images resolve relative to its CSS and break under a
 * bundler, so use the template's pin — it matches the theme and is already in
 * the public folder. 35x49, anchored at the point.
 */
const PIN = new Icon({
  iconUrl: '/img/map-marker.png',
  iconSize: [35, 49],
  iconAnchor: [17, 49],
  popupAnchor: [0, -46],
})

interface Props {
  restaurants: RestaurantCard[]
  /** Current radius-filter centre, if the user has set one. */
  centre: { lat: number; lng: number } | null
  radiusKm: number | null
  /** Clicking the map re-centres the radius filter. */
  onPickCentre: (lat: number, lng: number) => void
}

/** Roughly central London — matches ListingPage's default. */
const FALLBACK_CENTRE: [number, number] = [51.5074, -0.1278]

/** Keeps the viewport on the pins as filters change. */
function FitToPins({ points }: { points: [number, number][] }) {
  const map = useMap()

  useEffect(() => {
    if (points.length === 0) return

    if (points.length === 1) {
      map.setView(points[0], 14)
      return
    }

    map.fitBounds(points, { padding: [40, 40], maxZoom: 15 })
  }, [map, points])

  return null
}

function ClickToRecentre({ onPick }: { onPick: (lat: number, lng: number) => void }) {
  useMapEvents({
    click: (event) => onPick(event.latlng.lat, event.latlng.lng),
  })

  return null
}

/**
 * Leaflet measures its container on creation. Inside the collapsible panel the
 * container is display:none at that moment, so it comes out 0px and renders as
 * grey tiles until something invalidates the size.
 */
function InvalidateOnShow() {
  const map = useMap()

  useEffect(() => {
    const timer = window.setTimeout(() => map.invalidateSize(), 0)

    return () => window.clearTimeout(timer)
  }, [map])

  return null
}

export function RestaurantMap({ restaurants, centre, radiusKm, onPickCentre }: Props) {
  const plottable = useMemo(
    () => restaurants.filter((r) => r.latitude !== null && r.longitude !== null),
    [restaurants],
  )

  const points = useMemo(
    () => plottable.map((r) => [r.latitude as number, r.longitude as number] as [number, number]),
    [plottable],
  )

  const initialCentre: [number, number] = centre
    ? [centre.lat, centre.lng]
    : (points[0] ?? FALLBACK_CENTRE)

  return (
    <div className="map" role="region" aria-label="Map of matching restaurants">
      <MapContainer center={initialCentre} zoom={12} scrollWheelZoom style={{ height: '100%', width: '100%' }}>
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />

        <InvalidateOnShow />
        <FitToPins points={points} />
        <ClickToRecentre onPick={onPickCentre} />

        {/* The radius the listing is actually filtering by. */}
        {centre && radiusKm ? (
          <Circle
            center={[centre.lat, centre.lng]}
            radius={radiusKm * 1000}
            pathOptions={{ color: '#f5a623', weight: 1, fillOpacity: 0.08 }}
          />
        ) : null}

        {plottable.map((restaurant) => (
          <Marker
            key={restaurant.id}
            position={[restaurant.latitude as number, restaurant.longitude as number]}
            icon={PIN}
          >
            <Popup>
              <strong>{restaurant.name}</strong>
              <br />
              {restaurant.address}
              {restaurant.city ? `, ${restaurant.city}` : ''}
              <br />
              <span>
                {restaurant.rating.score > 0
                  ? `${restaurant.rating.score.toFixed(1)} ${restaurant.rating.label}`
                  : 'No reviews yet'}
              </span>
              {typeof restaurant.distance_km === 'number' ? <span> · {restaurant.distance_km} km</span> : null}
              <br />
              <Link to={`/restaurants/${restaurant.slug}`}>View restaurant</Link>
            </Popup>
          </Marker>
        ))}
      </MapContainer>

      {plottable.length < restaurants.length ? (
        <p className="foogra-muted" style={{ padding: '8px 0 0' }}>
          {restaurants.length - plottable.length} of {restaurants.length} on this page have no coordinates yet.
        </p>
      ) : null}
    </div>
  )
}
