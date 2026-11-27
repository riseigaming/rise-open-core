<?php

$riseChessBase=[
    '067675b8c470' => '#:value1',
    '0f3b01d4f56e' => 'Standart Kampanya',
    '14072d76225f' => 'Kampanya Türü',
    '25654c797a97' => 'Rise Back Office',
    '26ea5ad8c44c' => 'örnek.png',
    '2d9faadc2657' => ':value1',
    '4d08b52e1175' => 'Örnek.avif',
    '52adf5ffecfa' => 'Dil Kodu',
    'af0168acac06' => 'Eski yer: :value1 · Silinme: :value2',
    'b854423b6eea' => ':value1 · :value2',
    'b96355a12ac0' => 'Yeni Ekle',
    'c36cd1a07d39' => 'Yeni Sekme',
    'c7cf1d9739d4' => 'Dil devre dışı',
    'd8ad60fe3cea' => 'Etkin CMS dili',
    'f52d942c22d3' => ':value1:value2',
];

return array_replace_recursive($riseChessBase,require __DIR__.'/chess_cockpit_extra.php');
