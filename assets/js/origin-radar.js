/**
 * Kaupang Attribute Suite — origin taste radar (vanilla JS).
 *
 * Mirror of kaupang_attribute_suite_render_taste_radar_svg() in PHP. Both implementations
 * MUST produce visually identical output for the same input.
 *
 * Axis definitions come from window.wcRasTasteAxes (localized by
 * origin-frontend.php). Callers pass a profile and an axes map.
 *
 * Semantics (identical to PHP):
 *   - Rings at 25/75% (dotted), 50% (dashed) and 100% (solid), always drawn.
 *   - Spokes drawn for every defined axis, always.
 *   - Value polygon connects only axes with non-null values, in axis order.
 *   - Null axes are SKIPPED (fewer-sided polygon), not collapsed to center.
 *   - Labels render only for axes with non-null values; the highest-scoring
 *     axis/axes carry `wc-ras-radar__label--peak`.
 *   - If no axis is rated, returns empty string.
 *
 * Example:
 *   const profile = { acidity: 6, sweetness: 7, bitterness: null,
 *                     body: 5, fruit: 8, floral: null,
 *                     earth: null, spice: 4 };
 *   const svg = window.WcRasOriginRadar.render(profile);
 *   // → SVG string with pentagon polygon (5 rated of 8), 8 spokes, 4 rings.
 *
 * @package Kaupang\AttributeSuite
 */
(function () {
    'use strict';

    /**
     * Render a taste radar as SVG markup.
     *
     * @param {Object} profile  { axis_slug: 0..10 | null }
     * @param {Object} [axes]   { axis_slug: "Label" } — defaults to window.wcRasTasteAxes
     * @param {Object} [opts]   { size:number (height; width is 1.25×), showLabels:bool, showGrid:bool, maxValue:number }
     * @returns {string} SVG markup, or empty string if no axis is rated.
     */
    function render(profile, axes, opts) {
        axes = axes || window.wcRasTasteAxes || {};
        opts = opts || {};
        const size       = opts.size       != null ? opts.size       : 320;
        const showLabels = opts.showLabels != null ? opts.showLabels : true;
        const showGrid   = opts.showGrid   != null ? opts.showGrid   : true;
        const maxValue   = opts.maxValue   != null ? opts.maxValue   : 10;

        if (!profile || typeof profile !== 'object') { return ''; }
        const axisKeys = Object.keys(axes);
        if (!axisKeys.length) { return ''; }

        // Collect rated axes
        const rated = {};
        axisKeys.forEach(function (key) {
            const raw = profile[key];
            if (raw == null || raw === '') { return; }
            let v = parseInt(raw, 10);
            if (isNaN(v)) { return; }
            if (v < 0) { v = 0; }
            if (v > maxValue) { v = maxValue; }
            rated[key] = v;
        });
        if (!Object.keys(rated).length) { return ''; }
        const peak = Math.max.apply(null, Object.keys(rated).map(function (k) { return rated[k]; }));

        const width = Math.round(size * 1.25);
        const cx = width / 2;
        const cy = size / 2;
        const padding = showLabels ? Math.max(40, size * 0.15) : 12;
        const radius = (size / 2) - padding;
        const n = axisKeys.length;

        // Axis positions (full-value endpoints) keyed by slug
        const positions = {};
        axisKeys.forEach(function (key, i) {
            const theta = -Math.PI / 2 + (2 * Math.PI * i) / n;
            positions[key] = {
                theta: theta,
                ex: round2(cx + radius * Math.cos(theta)),
                ey: round2(cy + radius * Math.sin(theta)),
                label: axes[key]
            };
        });

        let svg = '<svg class="wc-ras-radar" viewBox="0 0 ' + width + ' ' + size + '" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">';

        // Grid: half ring dashed, full ring solid, spokes
        if (showGrid) {
            svg += '<g class="wc-ras-radar__grid">';
            [0.25, 0.75].forEach(function (level) {
                svg += '<circle class="wc-ras-radar__ring wc-ras-radar__ring--quarter" cx="' + cx + '" cy="' + cy + '" r="' + round2(radius * level) + '" fill="none" stroke="currentColor" stroke-opacity="0.3" stroke-width="0.75" stroke-dasharray="1 3"/>';
            });
            svg += '<circle class="wc-ras-radar__ring wc-ras-radar__ring--half" cx="' + cx + '" cy="' + cy + '" r="' + round2(radius * 0.5) + '" fill="none" stroke="currentColor" stroke-opacity="0.42" stroke-width="0.75" stroke-dasharray="2 4"/>';
            svg += '<circle class="wc-ras-radar__ring wc-ras-radar__ring--full" cx="' + cx + '" cy="' + cy + '" r="' + radius + '" fill="none" stroke="currentColor" stroke-opacity="0.45" stroke-width="1"/>';
            axisKeys.forEach(function (key) {
                const p = positions[key];
                svg += '<line class="wc-ras-radar__spoke" x1="' + cx + '" y1="' + cy + '" x2="' + p.ex + '" y2="' + p.ey + '" stroke="currentColor" stroke-opacity="0.22" stroke-width="0.75"/>';
            });
            svg += '</g>';
        }

        // Polygon over rated axes only
        const points = [];
        axisKeys.forEach(function (key) {
            if (!(key in rated)) { return; }
            const ratio = rated[key] / maxValue;
            const theta = positions[key].theta;
            const px = cx + radius * ratio * Math.cos(theta);
            const py = cy + radius * ratio * Math.sin(theta);
            points.push(round2(px) + ',' + round2(py));
        });

        if (points.length >= 3) {
            svg += '<polygon class="wc-ras-radar__polygon" points="' + points.join(' ') + '" fill="currentColor" fill-opacity="0.42" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>';
        } else if (points.length === 2) {
            const a = points[0].split(',');
            const b = points[1].split(',');
            svg += '<line class="wc-ras-radar__line" x1="' + a[0] + '" y1="' + a[1] + '" x2="' + b[0] + '" y2="' + b[1] + '" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>';
        } else {
            const p = points[0].split(',');
            svg += '<circle class="wc-ras-radar__point" cx="' + p[0] + '" cy="' + p[1] + '" r="3.5" fill="currentColor"/>';
        }

        // Vertex marks on rated axes
        points.forEach(function (pt) {
            const p = pt.split(',');
            svg += '<circle class="wc-ras-radar__vertex" cx="' + p[0] + '" cy="' + p[1] + '" r="3.5" fill="currentColor"/>';
        });

        // Labels (only for rated axes)
        if (showLabels) {
            svg += '<g class="wc-ras-radar__labels">';
            axisKeys.forEach(function (key) {
                if (!(key in rated)) { return; }
                const pos = positions[key];
                const lx = cx + radius * 1.16 * Math.cos(pos.theta);
                const ly = cy + radius * 1.16 * Math.sin(pos.theta);
                let anchor = 'middle';
                if (lx > cx + 2)      { anchor = 'start'; }
                else if (lx < cx - 2) { anchor = 'end'; }
                const cls = 'wc-ras-radar__label' + (rated[key] === peak && peak > 0 ? ' wc-ras-radar__label--peak' : '');
                svg += '<text class="' + cls + '" x="' + round2(lx) + '" y="' + round2(ly) + '" text-anchor="' + anchor + '" dominant-baseline="middle" font-size="12" fill="currentColor">' + escapeXml(pos.label) + '</text>';
            });
            svg += '</g>';
        }

        svg += '</svg>';
        return svg;
    }

    function round2(n) {
        return Math.round(n * 100) / 100;
    }

    function escapeXml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    window.WcRasOriginRadar = { render: render };
})();
