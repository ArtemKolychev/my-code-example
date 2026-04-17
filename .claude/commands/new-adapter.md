Create a new platform adapter for bazar_ai clicker service.

## Usage
/new-adapter <PlatformName> <base_url>

## Reference files
Before writing anything, read:
- `src/clicker/src/adapters/platformAdapter.interface.ts` — required interface
- `src/clicker/src/adapters/seznam.adapter.ts` — gold standard implementation, mimic its structure exactly
- `src/clicker/src/types/platform.enum.ts` — to add the new platform
- `src/clicker/src/app.module.ts` — to register the adapter

## Rules

**Browser**: Always use `StealthBrowserService`, never raw Playwright. Declare `browser` and `context` outside the try block.

**Sessions**: Call `loadCookies()` before first navigation. Call `saveCookies()` inside `authenticate()` immediately after login — not after upload. Call `saveCookies()` again at the end of a successful upload.

**Human-like input**: Use `pressSequentially(text, { delay: Math.floor(Math.random() * 50) + 50 })` for all visible text fields. Use `.fill()` only for numeric or hidden inputs. Add `waitForTimeout` with a random value between 300–800ms between major form sections.

**Mouse movement**: Never use `page.click()` or `locator.click()` directly — it teleports the cursor instantly to the element center. Always use `ghost-cursor-playwright`: create a `cursor` with `createCursor(page)` after page navigation, then call `cursor.click(locator)` for all interactive elements (buttons, dropdowns, file inputs, checkboxes). This produces Bézier-curve paths with natural overshoot and micro-jitter.

**Cleanup**: In `finally`, close `context` first then `browser`, each in its own `try/catch`.

**Errors**: On element-level failures (e.g. image upload toast), return a partial result immediately. Log full error context on catch.

## Stealth fingerprint testing

Reference test: `src/clicker/src/adapters/amhuman.spec.ts`

Every new adapter **must** pass fingerprint checks before going to production. The test suite verifies:
- **pixelscan.net** — no automation markers visible
- **creepjs** — `humanScore > 80%` (computed as `100 - max(headlessPct, likeHeadlessPct)`)

**CreepJS score extraction** — page format is `"33% headless:"`, `"25% like headless:"`, `"0% stealth:"` (percent before label):
```ts
likeHeadlessPct: extract(/(\d+(?:\.\d+)?)\s*%\s*like\s+headless/i),
headlessPct:     extract(/(\d+(?:\.\d+)?)\s*%\s+headless:/i),
stealthPct:      extract(/(\d+(?:\.\d+)?)\s*%\s*stealth/i),
```
Wait for `#fingerprint-data` to contain `%` before reading scores.

**Stealth stack**: `rebrowser-playwright` — CDP-level evasion, no JS patches needed.

- `StealthBrowserService` imports `chromium` from `rebrowser-playwright` (not `playwright-extra`, not `@playwright/test`)
- Do NOT use `playwright-extra` or `puppeteer-extra-plugin-stealth` — JS-level prototype patches are detected by CreepJS (`stealthPct` rises to ~80%)
- Do NOT add `page.addInitScript` patches — they also raise `stealthPct`
- `playwright-stealth` npm package is a useless stub. `@playwright-community/playwright-stealth` does not exist on npm.

**Version conflict warning**: `rebrowser-playwright` v1.52.0 bundles its own `playwright-core` v1.52.0, which conflicts with `@playwright/test` v1.54.1 if mixed in the same process. The test file must import `test` and `expect` from `rebrowser-playwright/test`, NOT from `@playwright/test`. Run amhuman tests with a separate config (`amhuman.playwright.config.ts`) via `node node_modules/rebrowser-playwright/cli.js test --config amhuman.playwright.config.ts`. Specify `executablePath` to the installed Chromium (e.g. `/ms-playwright/chromium-1208/chrome-linux/chrome`) since rebrowser-playwright looks for its own browser version.

**Achievable thresholds with rebrowser-playwright + Xvfb in Docker**:
- `headlessPct < 20%` — currently **0%** (CDP-level patch hides headless)
- `likeHeadlessPct < 60%` — currently **~44%** (Xvfb has no taskbar; unavoidable)
- `stealthPct < 20%` — currently **0%** (no JS patches = not detected by CreepJS)

## Steps
1. Add `<PlatformName>` to `platform.enum.ts`
2. Create `src/clicker/src/adapters/<platformname>.adapter.ts` — leave `TODO` comments for selectors that need manual inspection of the target site
3. Register in `app.module.ts`: add to `providers`, update `PLATFORM_ADAPTERS` factory and `inject` array
