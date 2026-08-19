/*
 * Keeps the dashboard's figures current without reloading the page.
 *
 * The table was rendered once and never asked the server again, so a tab left
 * open all morning still showed nine o'clock prices at three — a longer and
 * far less predictable delay than the cron everyone worries about, and one the
 * page gave no hint of.
 *
 * Deliberately polling rather than websockets: the underlying data is written
 * by a cron a few times an hour, so a socket would spend its life idle while
 * adding a persistent connection per viewer and a broadcast server to run. A
 * request every twenty seconds against tables this app already owns is the
 * cheaper answer to the same question.
 */

const ENDPOINT = '/api/quotes/live';

/** Rows currently on the page, keyed by the ticker they display. */
function rowsByTicker() {
    const rows = {};

    document.querySelectorAll('[data-quote-row]').forEach((row) => {
        rows[row.dataset.quoteRow] = row;
    });

    return rows;
}

function setText(row, field, value) {
    const el = row.querySelector(`[data-quote="${field}"]`);

    // Only touch the DOM when the value actually changed, so text stays
    // selectable and screen readers are not told about updates that are not
    // updates.
    if (el && value !== undefined && value !== null && el.textContent !== String(value)) {
        el.textContent = value;
    }
}

/*
 * Swaps one set of colour classes for another without knowing the palette.
 *
 * The element carries the classes that are currently its colour in
 * data-swap-class, so those are exactly what comes off; the replacement is
 * whatever the server sent. Nothing here knows that a strong score is emerald
 * or that a missing VWAP is grey — those bands live once, in
 * App\Support\Format, and restating them in JavaScript is precisely how a
 * green badge ends up sitting behind a red number.
 */
function swapClasses(el, next) {
    if (!el || !next || el.dataset.swapClass === next) return;

    (el.dataset.swapClass ?? '').split(' ').filter(Boolean).forEach((c) => el.classList.remove(c));
    next.split(' ').filter(Boolean).forEach((c) => el.classList.add(c));

    el.dataset.swapClass = next;
}

function applyQuote(row, quote) {
    setText(row, 'price', quote.price);
    setText(row, 'change', quote.change_pct ? `(${quote.change_pct})` : '(n/a)');
    setText(row, 'entry', quote.entry);
    setText(row, 'tp', quote.take_profit);
    setText(row, 'tp-pct', quote.take_profit_pct);
    setText(row, 'sl', quote.stop_loss);
    setText(row, 'sl-pct', quote.stop_loss_pct);
    setText(row, 'value', quote.value_transaction);

    // The stock detail header quotes the same move in a longer form.
    setText(row, 'change-line', quote.change_line);

    swapClasses(row.querySelector('[data-quote="price-line"]'), quote.change_class);
    swapClasses(row.querySelector('[data-quote="change-line-wrap"]'), quote.change_class);
    swapClasses(row.querySelector('[data-quote="verdict-circle"]'), quote.verdict_class);

    // The explanation for a missing percentage belongs to whichever element
    // carries it on this page, and has to leave again once there is a real
    // number to show.
    ['price-line', 'change-line-wrap'].forEach((field) => {
        const el = row.querySelector(`[data-quote="${field}"]`);
        if (!el) return;

        if (quote.change_issue) {
            el.title = quote.change_issue;
        } else {
            el.removeAttribute('title');
        }
    });

    const flow = row.querySelector('[data-quote="flow"]');
    if (flow) {
        swapClasses(flow, quote.flow_class);
        flow.textContent = quote.flow ?? '-';

        // The tooltip explains an absent badge, so it has to leave when the
        // badge arrives — otherwise a row that has since been given a VWAP
        // still claims it is waiting for one.
        if (quote.flow === null) {
            flow.title = 'VWAP belum tersedia — jalankan idx:update-realtime-quotes';
        } else {
            flow.removeAttribute('title');
        }
    }

    const score = row.querySelector('[data-score-value]');
    if (score) {
        score.textContent = quote.score;
    }

    swapClasses(row.querySelector('[data-score-badge]'), quote.score_class);
}

/*
 * The header badge, which says how old the figures are.
 *
 * Every string and every colour arrives finished, because this is the same
 * badge Blade rendered on first paint: rebuilding the wording here from
 * timestamps and flags gave a header that changed its mind about the format
 * the moment the first poll landed.
 */
function applyFreshness(freshness) {
    const badge = document.querySelector('[data-freshness]');
    if (!badge || !freshness) return;

    const text = badge.querySelector('[data-freshness-text]');
    if (text && freshness.label) text.textContent = freshness.label;

    if (freshness.title) badge.title = freshness.title;

    swapClasses(badge, freshness.text_class);
    swapClasses(badge.querySelector('[data-freshness-dot]'), freshness.dot_class);
}

/*
 * Opens the buy dialog for whatever price the reader can currently see.
 *
 * The price used to be baked into the onclick when the page was rendered,
 * which was harmless while the page never changed and wrong the moment it
 * did: after a poll the row showed one price and the dialog pre-filled
 * another. Reading it back out of the row means the two cannot disagree.
 */
export function openBuyModal(ticker) {
    const el = document.querySelector(`[data-quote-row="${ticker}"] [data-quote="price"]`);
    const price = Number((el?.textContent ?? '').replace(/[^0-9]/g, '')) || 0;

    window.dispatchEvent(new CustomEvent('open-buy-modal', { detail: { ticker, price } }));
}

export function startLiveQuotes(intervalSeconds) {
    if (!intervalSeconds || intervalSeconds < 1) return;
    if (!document.querySelector('[data-quote-row]')) return;

    const fast = intervalSeconds * 1000;
    // The idle rate, used when the exchange is shut and as the ceiling on the
    // failure backoff. Rare enough to cost nothing, frequent enough that a
    // page left open overnight is moving again within minutes of the bell.
    const slow = 5 * 60 * 1000;

    let timer = null;
    let inFlight = false;
    let failures = 0;

    // A chain of timeouts rather than an interval, because the right delay is
    // not a constant: it depends on whether the market is open and on whether
    // the last request worked.
    function schedule(delay) {
        clearTimeout(timer);
        timer = setTimeout(tick, delay);
    }

    async function tick() {
        // A hidden tab is not being read, so it does not need fetching — the
        // visibilitychange handler below catches it up the moment it is.
        if (inFlight || document.hidden) {
            return schedule(fast);
        }

        const rows = rowsByTicker();
        const tickers = Object.keys(rows);

        if (tickers.length === 0) {
            return schedule(slow);
        }

        inFlight = true;

        try {
            const res = await fetch(`${ENDPOINT}?tickers=${encodeURIComponent(tickers.join(','))}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            const payload = await res.json();

            Object.entries(payload.quotes ?? {}).forEach(([ticker, quote]) => {
                if (rows[ticker]) applyQuote(rows[ticker], quote);
            });

            applyFreshness(payload.freshness);
            failures = 0;

            // Nothing is written outside the trading window, so asking every
            // twenty seconds is pure waste — but the market does open again,
            // and a tab opened before the bell should not sit dead until
            // somebody thinks to reload it.
            schedule(payload.market_open ? fast : slow);
        } catch (e) {
            failures += 1;

            // Back off rather than hammer a server that is already failing,
            // and keep trying rather than give up: stopping dead after a bad
            // minute leaves a page that looks live and is not.
            schedule(Math.min(fast * 2 ** failures, slow));
        } finally {
            inFlight = false;
        }
    }

    schedule(fast);

    // Catch up immediately on returning to the tab, rather than showing a
    // stale figure until the next tick.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) schedule(0);
    });
}
