Debug and implement interaction with a web form on a target URL using the clicker's Docker browser.

## Usage
/debug-form <url> [description of what to do on the page]

## Environment

All commands run inside Docker: `docker compose exec clicker <cmd>`
Chrome binary: `/root/.cache/ms-playwright/chromium-1169/chrome-linux/chrome`
VNC available at localhost:6080 for visual confirmation.
Compiled JS is in `dist/src/` — after editing `.ts` source, run `docker compose exec clicker npx tsc` to rebuild.

## Workflow

### 1. Launch browser and dump page structure

Write a temporary script `src/clicker/test-form.js` that:
- Launches `rebrowser-playwright` chromium (headless: false, --no-sandbox, `--remote-debugging-port=<port>`)
- Navigates to the target URL
- **Waits 5 seconds** for dynamic content (shadow DOM widgets load asynchronously via JS)
- Dumps via `page.evaluate()`: `document.title`, all buttons (text + data-testid + class), all inputs, all iframes, all divs with class names
- Lists `page.frames()` with URLs and button counts per frame
- **Always runs CDP shadow DOM scan** (step 2) — many Seznam/CMP elements are in closed shadow DOM and invisible to `page.evaluate()`
- Logs everything to console

Run: `docker compose exec clicker node test-form.js`

### 2. Shadow DOM detection via raw CDP WebSocket

Regular `page.evaluate()` and Playwright locators CANNOT access **closed shadow DOM** — `element.shadowRoot` returns `null`.

**CRITICAL: DO NOT use `page.context().newCDPSession(page)` with rebrowser-playwright!**
It creates a conflicting CDP session that, upon detach, destroys Playwright's internal session. All subsequent `page.goto()` and `page.evaluate()` calls will crash with "session closed".

**Safe approach — raw WebSocket to Chrome's debugging port:**

```ts
import * as http from 'node:http';

// 1. Launch with --remote-debugging-port
const debugPort = 9222 + Math.floor(Math.random() * 1000);
const browser = await chromium.launch({
  headless: false,
  args: ['--no-sandbox', '--disable-dev-shm-usage', `--remote-debugging-port=${debugPort}`],
});

// 2. Find page target via HTTP
const pages = await httpGetJson(`http://127.0.0.1:${debugPort}/json/list`);
const target = pages.find(p => p.url.includes('target-domain.com'));
const wsUrl = target.webSocketDebuggerUrl;

// 3. Connect via raw WebSocket (use bundled ws from rebrowser-playwright)
const pwCorePath = require.resolve('rebrowser-playwright').replace(/index\.js$/, '');
const { ws: WebSocket } = require(`${pwCorePath}node_modules/playwright-core/lib/utilsBundle`);

const ws = new WebSocket(wsUrl);

// 4. Send CDP commands through this independent connection
const { root } = await send('DOM.getDocument', { depth: -1, pierce: true });

// 5. Recursive search — walks children, shadowRoots, and contentDocument
function findNode(node, predicate) {
  if (predicate(node)) return node;
  for (const child of node.children || []) {
    const f = findNode(child, predicate);
    if (f) return f;
  }
  for (const sr of node.shadowRoots || []) {
    const f = findNode(sr, predicate);
    if (f) return f;
  }
  if (node.contentDocument) {
    const f = findNode(node.contentDocument, predicate);
    if (f) return f;
  }
  return null;
}

// 6. Get bounding box for clicking
const { model } = await send('DOM.getBoxModel', { nodeId: btn.nodeId });
const pts = model.content; // [x1,y1, x2,y2, x3,y3, x4,y4]
const box = { x: pts[0], y: pts[1], width: pts[2] - pts[0], height: pts[5] - pts[1] };

// 7. Close WebSocket — Playwright's session is untouched
ws.close();
```

**Reusable utility:** `src/clicker/src/utils/cdpShadowQuery.ts` — `findShadowElementBox(debugPort, pageUrl, attrName, attrValue, nodeName?)`

### 3. CDP tree dump for debugging

When standard locators find nothing, dump the full CDP tree to see what's really on the page:

```js
function walkTree(node, depth = 0) {
  const prefix = '  '.repeat(depth);
  const attrs = node.attributes || [];
  const attrStr = [];
  for (let i = 0; i < attrs.length; i += 2) {
    attrStr.push(`${attrs[i]}="${attrs[i+1]}"`);
  }
  if (node.nodeName === 'BUTTON' || node.nodeName === 'A' || node.nodeName === 'INPUT' ||
      getAttr(node, 'data-testid') || getAttr(node, 'role') === 'button') {
    console.log(`${prefix}<${node.nodeName.toLowerCase()} ${attrStr.join(' ')}> nodeId=${node.nodeId}`);
  }
  for (const sr of node.shadowRoots || []) {
    console.log(`${prefix}[SHADOW ROOT]`);
    walkTree(sr, depth + 1);
  }
  for (const child of node.children || []) walkTree(child, depth + 1);
  if (node.contentDocument) walkTree(node.contentDocument, depth + 1);
}
```

### 4. Attribute extraction from CDP nodes

CDP node attributes are a flat array: `['class', 'foo', 'data-testid', 'bar', 'id', 'baz']`.

```ts
function getAttr(node, name) {
  const attrs = node.attributes || [];
  for (let i = 0; i < attrs.length; i += 2) {
    if (attrs[i] === name) return attrs[i + 1];
  }
  return null;
}
```

### 5. Clicking elements

**Never use `page.click()` or `locator.click()`** — they teleport the cursor instantly.

Always use `ghost-cursor-playwright`:
```ts
import { createCursor } from 'ghost-cursor-playwright';

const ghost = await createCursor(page);
const cursor = ghost.actions;

// For normal elements (not in shadow DOM):
await cursor.click({ target: 'button#submit', waitBeforeClick: [200, 500] });

// For shadow DOM elements — get bounding box via CDP, then click coordinates:
await cursor.randomMove();
await page.waitForTimeout(Math.floor(Math.random() * 500) + 300);
await cursor.click({ target: box, waitBeforeClick: [300, 600] });
```

### 6. Typing into fields

```ts
// Click the field first with ghost-cursor, then type
await cursor.click({ target: 'input#name', waitBeforeClick: [200, 500] });
await page.locator('input#name').pressSequentially('text', {
  delay: Math.floor(Math.random() * 50) + 50
});

// Random pause between form sections
await page.waitForTimeout(Math.floor(Math.random() * 500) + 300);
```

### 7. What does NOT work for closed shadow DOM

These approaches were tested and confirmed to FAIL:
- `page.evaluate(() => el.shadowRoot)` — returns `null` for closed shadow
- `page.locator('button[data-testid="..."]')` — cannot pierce closed shadow
- `page.getByText('...')` / `page.getByRole(...)` — cannot pierce closed shadow
- `page.addInitScript()` to patch `attachShadow` — raises stealthPct in rebrowser-playwright
- CDP `DOM.querySelectorAll` — does NOT pierce shadow DOM
- CDP `DOM.performSearch` with `includeUserAgentShadowDOM: true` — does NOT find elements in closed shadow
- **`page.context().newCDPSession(page)`** — DESTROYS rebrowser-playwright's internal CDP session after detach. Fatal. Never use.

**Only raw WebSocket CDP with `DOM.getDocument({ depth: -1, pierce: true })` + recursive tree walk works.**

### 8. Timing: wait for dynamic content

**Critical lesson**: shadow DOM widgets (like Seznam CMP) load asynchronously. After `page.goto()`, the `load` event fires but the shadow DOM content may not exist yet.

- In test scripts: `await page.waitForTimeout(5000)` after `goto`
- In adapter code: `await page.waitForTimeout(3000)` before CDP queries
- `[rebrowser-patches][frames._context] cannot get world` warnings in stderr are non-fatal — ignore them

### 9. Implementing the solution

Once you've identified the correct selectors and approach via the test script:
1. Add the interaction logic to the appropriate adapter method
2. For shadow DOM elements, use `findShadowElementBox()` from `src/clicker/src/utils/cdpShadowQuery.ts`
3. Launch browser with `--remote-debugging-port` via `stealthBrowserService.launch([...extraArgs])`
4. Delete `test-form.js` when done
5. Run `docker compose exec clicker npx tsc --noEmit` to verify compilation (ignore pre-existing `amhuman.spec.ts` error)

## Reference implementation
- `src/clicker/src/adapters/seznam.adapter.ts` — `acceptCmp()` for shadow DOM consent button + `authenticate()` for login form + `upload()` for full flow
- `src/clicker/src/utils/cdpShadowQuery.ts` — reusable utility for finding elements in closed shadow DOM
- `src/clicker/src/browser/stealth-browser.service.ts` — browser launcher with `extraArgs` support
