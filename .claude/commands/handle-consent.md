Handle a cookie/CMP consent dialog that blocks navigation on a target site.

## Usage
/handle-consent <adapter_file> <consent_page_url_substring> [button_identifier]

Example: `/handle-consent seznam.adapter.ts cmp.seznam.cz cw-button-agree-with-ads`

## Problem

Many Czech/EU sites (Seznam, Sbazar, etc.) show a consent dialog before allowing access. These dialogs typically:
- Redirect to a subdomain like `cmp.seznam.cz/nastaveni-souhlasu`
- Render UI inside **closed shadow DOM** (container: `div.szn-cmp-dialog-container`)
- Have buttons like "Souhlasím" (agree), "Předplatit" (subscribe), "Podrobné nastavení" (settings)
- Are invisible to standard Playwright locators and `page.evaluate()`

## How to identify the consent page

1. Navigate to the target site and check if URL redirects to a consent page
2. If `page.evaluate()` finds no buttons matching the visible UI — it's closed shadow DOM
3. Confirm with CDP scan (see `/debug-form`)

## Solution pattern

### In the adapter: add a `acceptCmp()` method

```ts
import {findShadowElementBox} from '../utils/cdpShadowQuery';
import {createCursor, type Actions} from 'ghost-cursor-playwright';

private async acceptCmp(page: Page, cursor: Actions, debugPort: number, uploadPath: string): Promise<void> {
  // CRITICAL: wait for shadow DOM widget to load (async JS injection)
  await page.waitForTimeout(3000);
  await page.screenshot({path: `${uploadPath}/screenshot_cmp.png`, fullPage: true});

  // Primary: raw CDP WebSocket pierces closed shadow DOM
  try {
    console.log('[CMP] Searching for consent button via CDP shadow DOM...');
    const btnBox = await findShadowElementBox(
      debugPort,
      'cmp.seznam.cz',      // URL substring to find the right CDP target
      'data-testid',         // attribute name
      'cw-button-agree-with-ads',  // attribute value
    );
    console.log('[CMP] Button box:', btnBox);
    if (btnBox) {
      await cursor.randomMove();
      await page.waitForTimeout(Math.floor(Math.random() * 800) + 400);
      await cursor.click({target: btnBox, waitBeforeClick: [300, 600]});
      console.log('[CMP] Clicked consent button, waiting for navigation...');
      await page.waitForURL(url => !url.toString().includes('cmp.seznam.cz'), {timeout: 15000});
      console.log('[CMP] Navigated to:', page.url());
      return;
    }
    console.log('[CMP] Button not found via CDP');
  } catch (e) {
    console.error('[CMP] CDP approach failed:', e.message);
  }

  // Fallback: standard selectors (in case consent is NOT in shadow DOM)
  const fallbackSelectors = [
    'button[data-testid="cw-button-agree-with-ads"]',
    'button[data-testid="cmp-btn-accept-all"]',
    '#cmp-btn-accept-all',
  ];
  for (const selector of fallbackSelectors) {
    try {
      await cursor.randomMove();
      await page.waitForTimeout(Math.floor(Math.random() * 500) + 300);
      await cursor.click({target: selector, waitBeforeClick: [200, 500]});
      await page.waitForURL(url => !url.toString().includes('cmp.'), {timeout: 15000});
      return;
    } catch {
      // try next selector
    }
  }
}
```

### In the adapter: call `acceptCmp()` in the navigation loop

```ts
// In upload() — browser must be launched with --remote-debugging-port
const debugPort = 9222 + Math.floor(Math.random() * 1000);
browser = await this.stealthBrowserService.launch([`--remote-debugging-port=${debugPort}`]);
context = await this.stealthBrowserService.newContext(browser, { /* options */ });

const page = await context.newPage();
const ghostCursor = await createCursor(page as any);
const cursor = ghostCursor.actions;

await page.goto('https://target-site.com/target-page', {timeout: 60000});

// Handle CMP/login redirects in a loop
for (let attempt = 0; attempt < 4; attempt++) {
  const currentUrl = page.url();
  console.log(`[Adapter] Attempt ${attempt}, URL: ${currentUrl}`);

  if (currentUrl.includes('/target-page')) break;

  if (currentUrl.includes('cmp.')) {
    await this.acceptCmp(page, cursor, debugPort, uploadPath);
  } else if (currentUrl.includes('login.')) {
    await this.authenticate(page, cursor, credential, uploadPath);
  }

  if (!page.url().includes('/target-page')) {
    await page.goto('https://target-site.com/target-page', {timeout: 60000});
  }
}
```

## Key requirements

1. **Browser launch**: must include `--remote-debugging-port=<port>` for CDP access
2. **Wait before CDP**: `await page.waitForTimeout(3000)` — shadow DOM widgets load asynchronously
3. **Use ghost-cursor**: never `page.click()`, always `cursor.click()` with Bézier movement
4. **Fallback selectors**: some consent dialogs are NOT in shadow DOM — try standard selectors as fallback
5. **Loop pattern**: consent → login → target page — handle each redirect type in a loop with max attempts

## Seznam CMP specifics

- URL pattern: `cmp.seznam.cz/nastaveni-souhlasu?service=...&return_url=...`
- Shadow host: `div.szn-cmp-dialog-container`
- Accept button: `<button data-testid="cw-button-agree-with-ads">Souhlasím</button>`
- After click: redirects to `login.szn.cz` (if not authenticated) or the return URL
- Script source: `https://cmp.seznam.cz/js/cmp2/scmp-cw.js`

## Debugging

If the consent button is not being clicked:
1. Check VNC at localhost:6080 to see the actual page
2. Run `/debug-form <url>` to dump the DOM structure via CDP
3. Verify the button's `data-testid` and `nodeName` match what you pass to `findShadowElementBox`
4. Check logs for `[CMP]` messages to see if the button box was found
5. If box is found but click doesn't work — the bounding box might be off-screen (page needs scrolling)

## Reference
- `src/clicker/src/adapters/seznam.adapter.ts` — full working implementation
- `src/clicker/src/utils/cdpShadowQuery.ts` — CDP shadow DOM utility
- `src/clicker/src/browser/stealth-browser.service.ts` — browser launcher