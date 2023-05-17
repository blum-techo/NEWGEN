<?php

require_once 'YClasses/YandexMusicParser.php';

$url = "https://music.yandex.ru/artist/4952161/tracks";
$authorPage = new \YClasses\YandexMusicParser($url);

$authorPage->getXPathFromURL();
if($authorPage->getArtistData()){
    echo "success";
}

?>
