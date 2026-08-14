import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import DOMPurify from 'dompurify';
import Swal from 'sweetalert2';
import { marked } from 'marked';

import { openBuyModal, startLiveQuotes } from './live-quotes';

/*
 * Everything below used to arrive from cdn.jsdelivr.net at runtime, which made
 * the whole interface hostage to a third party the server cannot reach
 * reliably: with jsdelivr blocked the mobile menu stopped opening, the login
 * page's password toggle did nothing, and the AI modal rendered an empty box
 * because DOMPurify was missing and the code fell back to ''. Bundling them
 * turns those from runtime failures into build-time guarantees.
 *
 * They are attached to `window` because the views call them from inline
 * handlers and <script> blocks — Blade templates are not modules and cannot
 * import. That is the same surface the CDN <script> tags exposed, so no view
 * needs to change for this.
 */
window.Alpine = Alpine;
// Called from onclick handlers in the dashboard and detail views, which had
// their own identical copy of this each — and each baked the price in at
// render time, so neither survived the page updating itself.
window.openBuyModal = openBuyModal;
window.Chart = Chart;
window.DOMPurify = DOMPurify;
window.Swal = Swal;
window.marked = marked;

/*
 * ECharts is only used by the heatmap and it is by far the largest of these —
 * around 1MB before compression, several times everything else combined. Left
 * as a static import it would be downloaded by every visitor to every page to
 * serve one. Imported on demand instead, so the pages that do not draw a
 * treemap never pay for it.
 *
 * Returns the same promise on repeated calls, so a page that resizes or
 * re-renders does not re-request the chunk.
 */
let echartsPromise = null;

window.loadECharts = () => {
    echartsPromise ??= import('echarts').then((module) => {
        window.echarts = module;

        return module;
    });

    return echartsPromise;
};

/*
 * Chart.js paints its axis labels and gridlines onto a canvas, so no CSS
 * reaches them: left alone they stay near-black on a dark card. This used to
 * sit in an inline <head> script, which worked while Chart.js came from a
 * blocking CDN tag and stopped working the moment it moved into this bundle —
 * @vite emits a module, modules are deferred, so the inline script now runs
 * before Chart exists.
 *
 * Charts are found by walking the canvases rather than by each view
 * registering itself, so no chart page has to know about this.
 */
function applyChartTheme() {
    const dark = document.documentElement.classList.contains('dark');

    Chart.defaults.color = dark ? '#a3b0c8' : '#64748b';
    Chart.defaults.borderColor = dark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(100, 116, 139, 0.15)';
}

applyChartTheme();

window.addEventListener('theme-changed', () => {
    applyChartTheme();

    document.querySelectorAll('canvas').forEach((canvas) => {
        Chart.getChart(canvas)?.update();
    });
});

// Interval comes from the server so it can be tuned per deployment without a
// rebuild, and so it can be turned off entirely.
startLiveQuotes(Number(document.querySelector('meta[name="live-poll-seconds"]')?.content ?? 0));

Alpine.start();
