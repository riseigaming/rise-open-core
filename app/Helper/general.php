<?php


function frontView($viewPath, $viewData = []){
    $viewName = \App\Support\CmsThemeResolver::viewName($viewPath, defined('activeTheme') ? activeTheme : null);

    return view($viewName, $viewData);
}


function generateWarmRandomColor() {
    $red = mt_rand(200, 255); // 200 ile 255 arasında rastgele bir değer
    $green = mt_rand(100, 200); // 100 ile 200 arasında rastgele bir değer
    $blue = mt_rand(0, 100); // 0 ile 100 arasında rastgele bir değer

    // RGB değerlerini hex koduna çevir
    $hexColor = sprintf("#%02X%02X%02X", $red, $green, $blue);

    return $hexColor;
}

function getUserAgentData(){
    $userAgent = request()->userAgent();

    // Tarayıcı ismini almak için düzenli ifade kullanımı
    if (preg_match('/(Chrome|Firefox|Safari|Edge|IE|Opera|Mobile\sSafari)/i', $userAgent, $matches)) {
        $browserName = $matches[0];
    } else {
        $browserName = 'Bilinmiyor';
    }

// İşletim sistemi bilgisini almak için düzenli ifade kullanımı
    if (preg_match('/(Windows NT \d\.\d|Windows|Linux|Mac OS X|Android|iOS)/i', $userAgent, $matches)) {
        $osName = $matches[0];
    } else {
        $osName = 'Bilinmiyor';
    }

    return [
        "userAgent" => $userAgent,
        "browser" => $browserName,
        "os" => $osName,
    ];
}

function getUppercaseFirstChar($string){
    return mb_strtoupper(mb_substr($string, 0, 1));
}

function getUppercaseFirstTwoChar($string){
    return mb_strtoupper(mb_substr($string, 0, 2));
}
