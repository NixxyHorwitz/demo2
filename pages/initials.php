<?php
// pages/initials.php — 3D green sphere with person silhouette (VOLTA-style)
$size    = max(60, min(400, (int)($_GET['size'] ?? 200)));
$h       = $size / 2;
$head_cy = round($h * 0.72, 1);
$head_r  = round($h * 0.40, 1);
$body_cy = round($h * 1.70, 1);
$body_rx = round($h * 0.72, 1);
$body_ry = round($h * 0.58, 1);
$hl_cx   = round($h * 0.68, 1);
$hl_cy   = round($h * 0.54, 1);
$hl_r    = round($h * 0.38, 1);
header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
  <defs>
    <radialGradient id="sph" cx="35%" cy="30%" r="70%">
      <stop offset="0%"   stop-color="#a3e05a"/>
      <stop offset="45%"  stop-color="#5fa832"/>
      <stop offset="100%" stop-color="#1e3d0f"/>
    </radialGradient>
    <radialGradient id="hl" cx="50%" cy="50%" r="50%">
      <stop offset="0%"   stop-color="#ffffff" stop-opacity="0.45"/>
      <stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="sh" cx="50%" cy="50%" r="50%">
      <stop offset="0%"   stop-color="#0a1f04" stop-opacity="0.4"/>
      <stop offset="100%" stop-color="#0a1f04" stop-opacity="0"/>
    </radialGradient>
    <clipPath id="circ"><circle cx="{$h}" cy="{$h}" r="{$h}"/></clipPath>
  </defs>
  <circle cx="{$h}" cy="{$h}" r="{$h}" fill="url(#sph)"/>
  <ellipse cx="{$h}" cy="{$size}" rx="{$h}" ry="{$head_r}" fill="url(#sh)" clip-path="url(#circ)"/>
  <g clip-path="url(#circ)" opacity="0.88">
    <circle cx="{$h}" cy="{$head_cy}" r="{$head_r}" fill="#d4f5a0"/>
    <ellipse cx="{$h}" cy="{$body_cy}" rx="{$body_rx}" ry="{$body_ry}" fill="#d4f5a0"/>
  </g>
  <ellipse cx="{$hl_cx}" cy="{$hl_cy}" rx="{$hl_r}" ry="{$hl_r}" fill="url(#hl)" clip-path="url(#circ)"/>
</svg>
SVG;