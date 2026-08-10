import { useState } from 'react'

interface ChipInputProps {
  values: string[]
  onChange: (values: string[]) => void
  placeholder?: string
  suggestions?: string[]
}

/** Free-text tag entry with one-tap suggestions, for services and payment methods. */
export function ChipInput({ values, onChange, placeholder, suggestions = [] }: ChipInputProps) {
  const [draft, setDraft] = useState('')

  const add = (value: string) => {
    const trimmed = value.trim()

    if (trimmed && !values.includes(trimmed)) {
      onChange([...values, trimmed])
    }

    setDraft('')
  }

  const unused = suggestions.filter((suggestion) => !values.includes(suggestion))

  return (
    <div>
      {values.length > 0 && (
        <div className="admin-chip-input" style={{ marginBottom: 8 }}>
          {values.map((value) => (
            <span className="admin-chip" key={value}>
              {value}
              <button type="button" onClick={() => onChange(values.filter((v) => v !== value))} aria-label={`Remove ${value}`}>
                ×
              </button>
            </span>
          ))}
        </div>
      )}

      <input
        type="text"
        value={draft}
        placeholder={placeholder}
        onChange={(e) => setDraft(e.target.value)}
        onKeyDown={(e) => {
          // Enter must not submit the surrounding form.
          if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault()
            add(draft)
          }

          if (e.key === 'Backspace' && draft === '' && values.length > 0) {
            onChange(values.slice(0, -1))
          }
        }}
        onBlur={() => draft && add(draft)}
      />

      {unused.length > 0 && (
        <div className="admin-chip-input" style={{ marginTop: 8 }}>
          {unused.map((suggestion) => (
            <button
              key={suggestion}
              type="button"
              className="admin-chip"
              style={{ cursor: 'pointer' }}
              onClick={() => add(suggestion)}
            >
              + {suggestion}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
