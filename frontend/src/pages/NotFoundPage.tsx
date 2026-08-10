import { Link } from 'react-router-dom'

export function NotFoundPage() {
  return (
    <div className="container margin_60_40">
      <div className="foogra-empty">
        <i className="icon_error-triangle_alt" />
        <h4>We could not find that page</h4>
        <p>The link may be out of date, or the restaurant may have been removed.</p>
        <div className="foogra-form-actions" style={{ justifyContent: 'center' }}>
          <Link to="/" className="btn_1">Back to home</Link>
          <Link to="/restaurants" className="btn_1 outline">Browse restaurants</Link>
        </div>
      </div>
    </div>
  )
}
