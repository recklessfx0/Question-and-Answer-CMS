# Frontend design and verification

## Platform audit

The application renders PHP templates through a shared layout and route-specific controllers. The router also serves the public Markdown alternates, sitemaps, robots directives, and crawler guides. The frontend uses a shared stylesheet and plain JavaScript for editing, uploads, mentions, media previews, reporting, reactions, voting, and member filtering. Public HTML works without hydration. The repository contains no bookmarks or saved-question route.

The initial review found repeated desktop and mobile styling, a large unminified asset pair, no standalone installation manifest, and secondary navigation that competed with tasks on smaller screens. The first Lighthouse pass also identified insufficient link contrast on pale green and inline links distinguished only by color.

## Design system

| Role | Value |
| --- | --- |
| Action and link green | `#296B5D` |
| Illustration green | `#78A99A` |
| Tinted surface | `#D9EAE3` |
| Page background | `#F7F9F6` |
| Content surface | `#FFFFFF` |
| Text | `#202B27` |

System fonts render without a font request. Sections use compact spacing on phones and fluid padding above tablet widths. Functional icons share a 24-unit view box and 1.5-unit rounded stroke. Isometric drawings are reserved for feed, topic, and empty-state surfaces. Interactive elements retain text or an accessible name.

The shared shell covers public pages, account screens, discussions, moderation, and administration. Phones get a safe-area-aware bottom navigation bar. Wide feeds can show topic links alongside the question list. Active links use native navigation semantics, prose links carry underlines, and reduced-motion preferences suppress transitions.

## Build and delivery

Run from the CMS root:

```text
node scripts/build-frontend.mjs
node tests/frontend_budget.mjs
```

The build uses esbuild only at build time. Deploy the generated CSS and JavaScript alongside the source assets, including `math.min.js` and its license. PHP selects the minified files when they are current. The budget check limits initial CSS to 20 KB and initial JavaScript to 50 KB after gzip. Apache can compress static CSS, JavaScript, and SVG when `mod_deflate` is enabled.

Answers and replies support fenced code, lists, links, quote blocks, and equations. Use dollar delimiters for inline math and double dollar delimiters on separate lines for a display equation. Equations load a local KaTeX build on demand for native MathML. The published Markdown endpoint preserves the original code and TeX source.

The service worker registers on HTTPS or loopback. It caches only versioned shell assets from a fixed list. HTML and API responses stay on the network, including account, moderation, and administrator pages. A local fallback explains how to return online when navigation fails.

## Search and crawler behavior

The redesign preserves public routes, canonical URLs, server-rendered discussions, Markdown alternates, and sitemaps. Closed and duplicate questions omit QAPage markup. An open question can declare zero answers, but rich-result eligibility requires an answer. [Google Search Central Q&A guidance](https://developers.google.com/search/docs/appearance/structured-data/qapage)

## Measured performance

Lighthouse 13.6.0 ran against the local PHP demo with Edge. Mobile used Lighthouse’s default profile. Desktop used its desktop preset.

| Page and profile | Performance | Accessibility | Best practices | SEO | LCP | CLS | TBT | Transfer |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Homepage, mobile | 100 | 100 | 100 | 100 | 1,203 ms | 0 | 0 ms | 126,872 B |
| Discussion, mobile | 100 | 100 | 100 | 100 | 1,202 ms | 0 | 10 ms | 144,096 B |
| Homepage, desktop | 100 | 100 | 100 | 100 | 323 ms | 0 | 0 ms | 126,871 B |
| Discussion, desktop | 100 | 100 | 100 | 100 | 324 ms | 0 | 0 ms | 144,096 B |

The minified stylesheet is 15,941 bytes gzip. Full page JavaScript plus the navigation shell is 7,488 bytes gzip. A text-only feed uses the 361-byte gzip navigation shell, and the 1,068-byte gzip worker registers after load. The local server reports uncompressed asset transfers, and both sampled pages met the 150 KB total budget in that setup.

The initial mobile runs transferred 171,446 bytes for the feed and scored 92 for accessibility on both pages. The release checks cleared the contrast and inline-link findings. Lighthouse still flagged back/forward cache restrictions associated with `no-store`. An earlier desktop discussion run recorded a transient browser socket error loading the icon and scored 96 for best practices. Its isolated rerun loaded the icon and scored 100.

These lab scores describe two public templates. TBT is a lab measure, and field INP requires real-user data. Safari, Firefox, physical phone testing, assistive-technology testing, and a complete WCAG 2.2 AA conformance review remain open.

## Verification

- PHP lint passed for 108 files. The PHP suites passed 59 checks.
- The compressed asset budget and service worker regression tests passed. The worker test covers an unavailable network, versioned asset replacement, and exclusion of private pages and form submissions from its cache.
- The Edge viewport pass covered 56 public page and width combinations from 320 to 1440 px. It checked overflow, menu keyboard focus, manifest and icon delivery, active worker control, and canonical HTML and Markdown discovery. Edge’s network emulation did not exercise the offline navigation fallback, which was verified by the worker test.
- A separate Edge pass signed in through the normal browser form and checked 48 authenticated page and width combinations. It covered question authoring, profile settings, notifications, moderation, and admin screens, plus preserved drafts and report dialog focus.
