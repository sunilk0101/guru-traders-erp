<?php

$srcPath = $argv[1] ?? 'storage/app/public/company-profile/gt-logo.png';
$outPaths = array_slice($argv, 2) ?: [
    'storage/app/public/company-profile/gt-logo.png',
    'public/images/gt-logo.png',
];

if (! function_exists('imagecreatefrompng')) {
    fwrite(STDERR, "GD extension missing\n");
    exit(1);
}

$src = @imagecreatefrompng($srcPath) ?: @imagecreatefromjpeg($srcPath);
if (! $src) {
    fwrite(STDERR, "Cannot read {$srcPath}\n");
    exit(1);
}

$w = imagesx($src);
$h = imagesy($src);
$nw = 420;
$nh = (int) max(1, round($h * ($nw / $w)));

$dst = imagecreatetruecolor($nw, $nh);
imagealphablending($dst, false);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

foreach ($outPaths as $out) {
    $dir = dirname($out);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    imagepng($dst, $out, 6);
    echo $out.' '.filesize($out).PHP_EOL;
}

imagedestroy($src);
imagedestroy($dst);
