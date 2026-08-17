<?php
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$w = isset($_GET['w']) ? (int)$_GET['w'] : 800;
$h = isset($_GET['h']) ? (int)$_GET['h'] : 600;

$w = max(40, min(3840, $w));
$h = max(40, min(2160, $h));

$text = isset($_GET['text']) && trim($_GET['text']) !== '' ? trim($_GET['text']) : "{$w} × {$h} px";
$bg = isset($_GET['bg']) ? preg_replace('/[^0-9a-fA-F]/', '', $_GET['bg']) : '1e293b';
$color = isset($_GET['color']) ? preg_replace('/[^0-9a-fA-F]/', '', $_GET['color']) : '94a3b8';

if (strlen($bg) !== 3 && strlen($bg) !== 6) $bg = '1e293b';
if (strlen($color) !== 3 && strlen($color) !== 6) $color = '94a3b8';

$fontSize = max(13, min(64, round(min($w, $h) / 12)));
?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?php echo $w; ?>" height="<?php echo $h; ?>" viewBox="0 0 <?php echo $w; ?> <?php echo $h; ?>">
    <defs>
        <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#<?php echo htmlspecialchars($bg); ?>"/>
            <stop offset="100%" stop-color="#0f172a"/>
        </linearGradient>
    </defs>
    <rect width="100%" height="100%" fill="url(#g)"/>
    <rect x="3%" y="3%" width="94%" height="94%" fill="none" stroke="#<?php echo htmlspecialchars($color); ?>" stroke-width="2" stroke-dasharray="8 8" opacity="0.35" rx="12"/>
    <circle cx="50%" cy="<?php echo max(30, round($h/2) - ($fontSize * 1.2)); ?>" r="<?php echo round($fontSize * 0.8); ?>" fill="#<?php echo htmlspecialchars($color); ?>" opacity="0.2"/>
    <text x="50%" y="<?php echo round($h/2) + round($fontSize * 0.35); ?>" dominant-baseline="middle" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" font-size="<?php echo $fontSize; ?>px" font-weight="700" fill="#<?php echo htmlspecialchars($color); ?>" letter-spacing="1px"><?php echo htmlspecialchars($text); ?></text>
    <text x="50%" y="<?php echo round($h/2) + round($fontSize * 1.6); ?>" dominant-baseline="middle" text-anchor="middle" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif" font-size="<?php echo max(10, round($fontSize * 0.45)); ?>px" font-weight="400" fill="#<?php echo htmlspecialchars($color); ?>" opacity="0.65">مقاس الصورة الموصى به</text>
</svg>
