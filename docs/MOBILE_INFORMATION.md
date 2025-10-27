# Mobile Development Reference
**Last Updated:** October 27, 2025

## Current Status
✅ Mobile CSS fixes applied to `style.css` lines 1070-1170  
✅ Apache caching fixed (1hr cache, respects query strings)  
✅ Service worker cache: `v6-20251027-0659`  
⚠️ Users may need to clear browser cache once to see updates

## Critical Files
- `/public_html/assets/css/style.css` - All mobile styles in `@media (max-width: 768px)`
- `/public_html/service-worker.js` - Cache version control
- `/src/helpers.php` - `getAssetVersion()` uses git hash
- `/etc/apache2/sites-available/irmajosh.com.conf` - Cache headers

## Mobile Breakpoint (768px)
All mobile fixes in single `@media (max-width: 768px)` block at line 1070.

### Navigation (FIXED)
```css
.header-nav {
    justify-content: flex-start;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.nav-link {
    flex-shrink: 0;
    white-space: nowrap;
    font-size: 0.875rem;
}
```

### Tabs (FIXED)
```css
.tabs {
    margin-left: 0;
    margin-right: 0;
    max-width: 100vw;
    width: 100%;
}
```

### Grids (FIXED)
```css
.dashboard-grid,
.action-grid,
.card-grid {
    grid-template-columns: 1fr;
}
```

### Modals (FIXED)
```css
.modal-content { width: 95%; }
.modal-footer {
    flex-direction: column-reverse;
}
.modal-footer .btn { width: 100%; }
```

### Page Actions (FIXED)
```css
.page-actions {
    width: 100%;
    flex-wrap: wrap;
}
```

## Cache Management

### Apache Headers
- **CSS/JS:** `max-age=3600, must-revalidate` (1 hour)
- **Images:** `max-age=86400` (1 day)
- **Service Worker:** `no-cache, no-store` (never cached)

### Force Cache Update
```bash
./scripts/bust_cache.sh
# OR manually:
touch public_html/assets/css/style.css
sudo systemctl reload php8.4-fpm
```

### User Cache Clear (one-time needed)
1. Chrome menu → Site settings → Clear & reset
2. OR use incognito mode to verify

## Common Issues

**Horizontal scroll on mobile:**
- Check: `overflow-x: hidden` on body
- Check: `max-width: 100%` on wide elements
- Check: Grid `minmax()` not too wide for viewport

**Navigation cuts off:**
- Must have `overflow-x: auto` on `.header-nav`
- Must have `flex-shrink: 0` on `.nav-link`

**Old CSS showing:**
- Run `./scripts/bust_cache.sh`
- User clears browser cache
- Check service worker version updated

## Testing Checklist
- [ ] Navigation scrolls, Quick Add accessible
- [ ] No horizontal page scroll
- [ ] Tabs scroll within viewport
- [ ] Cards stack vertically
- [ ] Modals display fully
- [ ] Touch scrolling smooth (`-webkit-overflow-scrolling: touch`)

## Key Rules
1. All mobile CSS in one breakpoint block
2. Update service worker version after CSS changes
3. Apache cache now respects versions (fixed)
4. Run `bust_cache.sh` after updates
5. Test in incognito to bypass cache

## Viewport
```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```
Already in `layout.php` - don't touch.

## CSS Variables (Dark Theme)
```css
--spacing-xs: 0.25rem
--spacing-sm: 0.5rem
--spacing-md: 1rem
--spacing-lg: 1.5rem
--spacing-xl: 2rem
```
Use these for consistent mobile padding.
