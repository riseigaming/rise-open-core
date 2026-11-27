<?php

$riseChessBase=[
    '067675b8c470' => '#:value1',
    '0f3b01d4f56e' => 'Standard Campaign',
    '14072d76225f' => 'Campaign Type',
    '25654c797a97' => 'Rise Back Office',
    '26ea5ad8c44c' => 'example.png',
    '2d9faadc2657' => ':value1',
    '4d08b52e1175' => 'example.avif',
    '52adf5ffecfa' => 'Language Code',
    'af0168acac06' => 'Previous location: :value1 · Deleted: :value2',
    'b854423b6eea' => ':value1 · :value2',
    'b96355a12ac0' => 'Add New',
    'c36cd1a07d39' => 'New Tab',
    'c7cf1d9739d4' => 'Language inactive',
    'd8ad60fe3cea' => 'Active CMS language',
    'f52d942c22d3' => ':value1:value2',
];

return array_replace_recursive($riseChessBase,require __DIR__.'/chess_cockpit_extra.php');
