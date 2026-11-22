<?php
declare(strict_types=1);

/**
 * Inline SVG chart helpers (no JavaScript, no external library).
 *
 * The site CSP is `script-src 'self'` and blocks CDN scripts, so charts are
 * rendered as pure SVG generated in PHP. Every helper returns a complete
 * <svg> string and escapes all labels.
 *
 * Palette helpers use the brand colours so charts match the UI.
 */

/**
 * Render a donut (ring) chart from segments.
 *
 * @param array<int,array{label:string,value:int,color:string}> $segments
 * @param int    $size      Pixel width/height of the square SVG.
 * @param int    $thickness Ring thickness in px.
 * @param string $centerTop Big text in the middle (e.g. total).
 * @param string $centerSub Small text under it.
 */
function donutChart(array $segments, int $size = 200, int $thickness = 34, string $centerTop = '', string $centerSub = ''): string
{
    $total = 0;
    foreach ($segments as $seg) {
        $total += (int) $seg['value'];
    }

    $cx = $size / 2;
    $cy = $size / 2;
    $r  = ($size - $thickness) / 2;
    $circ = 2 * M_PI * $r;

    // Empty state ring.
    $svg  = '<svg class="donut" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="Donut chart">';
    $svg .= '<circle class="donut-track" cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke-width="' . $thickness . '"/>';

    if ($total > 0) {
        $offset = 0.0;
        foreach ($segments as $seg) {
            $value = (int) $seg['value'];
            if ($value <= 0) {
                continue;
            }
            $len   = ($value / $total) * $circ;
            $gap   = $circ - $len;
            $color = $seg['color'];
            $label = $seg['label'];
            // stroke-dasharray = visible length, gap; rotate so it starts at top.
            $svg .= '<circle class="donut-seg" cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '"'
                  . ' fill="none" stroke="' . e($color) . '" stroke-width="' . $thickness . '"'
                  . ' stroke-dasharray="' . round($len, 2) . ' ' . round($gap, 2) . '"'
                  . ' stroke-dashoffset="' . round(-$offset, 2) . '"'
                  . ' transform="rotate(-90 ' . $cx . ' ' . $cy . ')"'
                  . '><title>' . e($label) . ': ' . $value . '</title></circle>';
            $offset += $len;
        }
    }

    if ($centerTop !== '') {
        $svg .= '<text class="donut-center-value" x="' . $cx . '" y="' . ($cy - 2) . '" text-anchor="middle" dominant-baseline="middle">' . e($centerTop) . '</text>';
    }
    if ($centerSub !== '') {
        $svg .= '<text class="donut-center-sub" x="' . $cx . '" y="' . ($cy + 22) . '" text-anchor="middle" dominant-baseline="middle">' . e($centerSub) . '</text>';
    }

    $svg .= '</svg>';

    return $svg;
}

/**
 * Render an accessible legend list matching a donut chart's segments.
 *
 * @param array<int,array{label:string,value:int,color:string}> $segments
 */
function donutLegend(array $segments): string
{
    $html = '<ul class="chart-legend">';
    foreach ($segments as $seg) {
        $value = (int) $seg['value'];
        $color = $seg['color'];
        $label = $seg['label'];
        $html .= '<li class="legend-item">'
               . '<span class="legend-swatch" style="background:' . e($color) . '"></span>'
               . '<span class="legend-label">' . e($label) . '</span>'
               . '<span class="legend-value">' . number_format($value) . '</span>'
               . '</li>';
    }
    $html .= '</ul>';

    return $html;
}

/**
 * Render a line (area) chart.
 *
 * @param array<int,array{label:string,value:int}> $points Ordered left-to-right.
 * @param int    $width
 * @param int    $height
 * @param string $stroke   Line colour.
 */
function lineChart(array $points, int $width = 520, int $height = 240, string $stroke = '#E91E63'): string
{
    $n = count($points);
    if ($n === 0) {
        return '';
    }

    $padL = 34;
    $padR = 14;
    $padT = 16;
    $padB = 30;

    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;

    $maxVal = 1;
    foreach ($points as $p) {
        $maxVal = max($maxVal, (int) $p['value']);
    }

    // X positions evenly spaced; single point sits centred.
    $stepX = $n > 1 ? $plotW / ($n - 1) : 0;

    $coords = [];
    $i = 0;
    foreach ($points as $p) {
        $x = $n > 1 ? $padL + $stepX * $i : $padL + $plotW / 2;
        $ratio = (int) $p['value'] / $maxVal;
        $y = $padT + $plotH - ($ratio * $plotH);
        $coords[] = ['x' => $x, 'y' => $y, 'label' => $p['label'], 'value' => (int) $p['value']];
        $i++;
    }

    $svg  = '<svg class="line-chart" viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" height="' . $height . '" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Line chart">';

    // Horizontal gridlines + y-axis labels (4 steps).
    $steps = 4;
    for ($s = 0; $s <= $steps; $s++) {
        $gy = $padT + ($plotH / $steps) * $s;
        $val = (int) round($maxVal * (1 - $s / $steps));
        $svg .= '<line class="chart-grid" x1="' . $padL . '" y1="' . round($gy, 1) . '" x2="' . ($width - $padR) . '" y2="' . round($gy, 1) . '"/>';
        $svg .= '<text class="chart-axis-y" x="' . ($padL - 8) . '" y="' . round($gy + 4, 1) . '" text-anchor="end">' . $val . '</text>';
    }

    // Area fill path.
    if ($n > 1) {
        $linePts = [];
        foreach ($coords as $c) {
            $linePts[] = round($c['x'], 1) . ',' . round($c['y'], 1);
        }
        $areaPts = $padL . ',' . ($padT + $plotH) . ' ' . implode(' ', $linePts) . ' ' . ($width - $padR) . ',' . ($padT + $plotH);
        $svg .= '<polygon class="chart-area" points="' . $areaPts . '" fill="' . e($stroke) . '"/>';
        $svg .= '<polyline class="chart-line" points="' . implode(' ', $linePts) . '" fill="none" stroke="' . e($stroke) . '"/>';
    }

    // Points + x-axis labels.
    foreach ($coords as $c) {
        $svg .= '<circle class="chart-dot" cx="' . round($c['x'], 1) . '" cy="' . round($c['y'], 1) . '" r="4" fill="' . e($stroke) . '"><title>' . e($c['label']) . ': ' . $c['value'] . '</title></circle>';
        $svg .= '<text class="chart-axis-x" x="' . round($c['x'], 1) . '" y="' . ($height - 10) . '" text-anchor="middle">' . e($c['label']) . '</text>';
    }

    $svg .= '</svg>';

    return $svg;
}