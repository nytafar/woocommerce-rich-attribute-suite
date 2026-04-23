/**
 * WooCommerce Rich Attribute Suite — origin taste radar (vanilla JS).
 *
 * Mirror of wc_ras_render_taste_radar_svg() in PHP. Both implementations
 * MUST produce visually identical output for the same input.
 *
 * Axis definitions come from window.wcRasTasteAxes (localized by
 * origin-frontend.php). Callers pass a profile and an axes map.
 *
 * Semantics (identical to PHP):
 *   - Grid circles at 25/50/75/100% of radius, always drawn.
 *   - Spokes drawn for every defined axis, always.
 *   - Value polygon connects only axes with non-null values, in axis order.
 *   - Null axes are SKIPPED (fewer-sided polygon), not collapsed to center.
 *   - Labels render only for axes with non-null values.
 *   - If no axis is rated, returns empty string.
 *
 * Example:
 *   const profile = { acidity: 6, sweetness: 7, bitterness: null,
 *                     body: 5, fruit: 8, floral: null,
 *                     earth: null, spice: 4 };
 *   const svg = window.WcRasOriginRadar.render(profile);
 *   // → SVG string with pentagon polygon (5 rated of 8), 8 spokes, 8 grid circles.
 *
 * @package WooCommerce_Rich_Attribute_Suite
 */
(function () {
    'use strict';

    /**
     * Render a taste radar as SVG markup.
     *
     * @param {Object} profile  { axis_slug: 0..10 | null }
     * @param {Object} [axes]   { axis_slug: "Label" } — defaults to window.wcRasTasteAxes
     * @param {Object} [opts]   { size:number, showLabels:bool, showGrid:bool, maxValue:number }
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

        const cx = size / 2;
        const cy = size / 2;
        const padding = showLabels ? Math.max(32, size * 0.12) : 12;
        const radius = (size / 2) - padding;
        const n = axisKeys.length;

        // Axis positions (full-value endpoints) keyed by slug
        const positions = {};
        axisKeys.forEach(function (key, i) {
            const theta = -Math.PI / 2 + (2 * Math.PI * i) / n;
            positions[key] = {
                theta: theta,
                ex: cx + radius * Math.cos(theta),
                ey: cy + radius * Math.sin(theta),
                label: axes[key]
            };
        });

        let svg = '<svg class="wc-ras-radar" viewBox="0 0 ' + size + ' ' + size + '" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">';

        // Grid
        if (showGrid) {
            svg += '<g class="wc-ras-radar__grid">';
            [0.25, 0.5, 0.75, 1.0].forEach(function (lvl) {
                const r = radius * lvl;
                svg += '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="none" stroke="currentColor" stroke-opacity="0.15" stroke-width="1"/>';
            });
            axisKeys.forEach(function (key) {
                const p = positions[key];
                svg += '<line x1="' + cx + '" y1="' + cy + '" x2="' + p.ex + '" y2="' + p.ey + '" stroke="currentColor" stroke-opacity="0.15" stroke-width="1"/>';
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
            svg += '<polygon class="wc-ras-radar__polygon" points="' + points.join(' ') + '" fill="currentColor" fill-opacity="0.22" stroke="currentColor" stroke-width="1.5"/>';
        } else if (points.length === 2) {
            const a = points[0].split(',');
            const b = points[1].split(',');
            svg += '<line class="wc-ras-radar__line" x1="' + a[0] + '" y1="' + a[1] + '" x2="' + b[0] + '" y2="' + b[1] + '" stroke="currentColor" stroke-width="2"/>';
        } else {
            const p = points[0].split(',');
            svg += '<circle class="wc-ras-radar__point" cx="' + p[0] + '" cy="' + p[1] + '" r="3" fill="currentColor"/>';
        }

        // Emphasis dots on rated vertices
        points.forEach(function (pt) {
            const p = pt.split(',');
            svg += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="2.5" fill="currentColor"/>';
        });

        // Labels (only for rated axes)
        if (showLabels) {
            svg += '<g class="wc-ras-radar__labels">';
            axisKeys.forEach(function (key) {
                if (!(key in rated)) { return; }
                const pos = positions[key];
                const lx = cx + radius * 1.12 * Math.cos(pos.theta);
                const ly = cy + radius * 1.12 * Math.sin(pos.theta);
                let anchor = 'middle';
                if (lx > cx + 2)      { anchor = 'start'; }
                else if (lx < cx - 2) { anchor = 'end'; }
                svg += '<text x="' + round2(lx) + '" y="' + round2(ly) + '" text-anchor="' + anchor + '" dominant-baseline="middle" font-size="11" fill="currentColor">' + escapeXml(pos.label) + '</text>';
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
