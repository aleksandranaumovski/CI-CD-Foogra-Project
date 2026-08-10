/**
 * The Foogra wordmark.
 *
 * The template ships a grey "IMAGE PLACEHOLDER" graphic as `img/logo.svg`, so
 * this replaces it with a real mark. It is inlined rather than loaded via
 * `<img>` so `currentColor` resolves against the header — white over the hero
 * video, dark once the header sticks.
 */
export function Logo({ width = 140 }: { width?: number }) {
  return (
    <svg
      width={width}
      height={(width / 140) * 35}
      viewBox="0 0 140 35"
      role="img"
      aria-label="Foogra"
      fill="none"
    >
      <circle cx="16" cy="17.5" r="14" fill="#f0563f" />
      {/* A fork and a spoon, drawn simply enough to stay legible at 35px tall. */}
      <path
        d="M11.4 9.8v15.4M8.4 9.8v4.4a3 3 0 0 0 3 3 3 3 0 0 0 3-3V9.8"
        stroke="#fff"
        strokeWidth="1.7"
        strokeLinecap="round"
      />
      <path
        d="M21 25.2v-6.6M21 18.6c-1.7 0-2.8-1.5-2.8-3.7 0-2.9 1.3-5.1 2.8-5.1s2.8 2.2 2.8 5.1c0 2.2-1.1 3.7-2.8 3.7Z"
        stroke="#fff"
        strokeWidth="1.7"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <text
        x="38"
        y="25"
        fontFamily="Poppins, -apple-system, 'Segoe UI', Roboto, sans-serif"
        fontSize="23"
        fontWeight="700"
        letterSpacing="-0.6"
        fill="currentColor"
      >
        Foogra
      </text>
    </svg>
  )
}
