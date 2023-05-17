<?php

namespace YClasses;

use DOMDocument;
use DOMXPath;
use PDO;

class YandexMusicParser
{
    //Максимальное время ответа сервера.
    public const SERVER_TIMEOUT = 120;

    //Константы для полключения к БД.
    public const DBHOST = 'localhost';
    public const DBNAME = 'newgen';
    public const DBUSER = 'root';
    public const DBPASS = '';

    public $url, $verifySSL;
    protected $xpath;

    /**
     * @param $url Ссылка на треки артиста.
     * @param bool $verifySSL Отключение проверки SLL.
     */
    public function __construct($url, bool $verifySSL = false)
    {
        $this->url = $url;
        $this->verifySSL = $verifySSL;
    }

    /**
     * @return DOMXPath
     */
    public function getXPathFromURL()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::SERVER_TIMEOUT);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySSL);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySSL);

        $html = curl_exec($ch);
        curl_close($ch);

        if ($html === false) {
            throw new Exception('Connection failed: ' . curl_error($ch));
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        @$dom->loadHTML($html);
        $this->xpath = new DOMXPath($dom);

        return $this->xpath;
    }

    /**
     * @return bool True если артист и его треки успешно добавленны в базу.
     */
    public function getArtistData()
    {
        //Получить информацию об исполнителе.
        $trackNode = $this->xpath->query('//div[contains(@class, "d-generic-page-head__main")]')->item(0);

        $authorInfo = [
            'author_id' => $this->xpath->query('.//button[contains( @class, "button-play")]', $trackNode)->item(0)->attributes->item(2)->value,
            'name' => $this->xpath->query('.//h1', $trackNode)->item(0)->textContent,
            'listeners' => (int)str_replace(' ', '', $this->xpath->query('.//div[contains( @class, "page-artist__summary")]/span', $trackNode)->item(0)->textContent),
            'likes' => (int)str_replace(' ', '', $this->xpath->query('.//span[contains( @class, "d-button__label")]', $trackNode)->item(0)->textContent)
        ];

        $tracks = [];
        // Получить информацию о треках исполнителя.
        $trackNodes = $this->xpath->query('//div[contains(@class, "d-track typo-track d-track_selectable")]');

        foreach ($trackNodes as $trackNode) {
            $track_id = $trackNode->attributes->item(1)->value;
            $name = $this->xpath->query('.//a[contains( @class, "d-track__title deco-link deco-link_stronger")]', $trackNode)->item(0)->textContent;
            $album = $this->xpath->query('.//div[@class="d-track__overflowable-wrapper"]/div[@class="d-track__meta"]/a', $trackNode)->item(0)->textContent;
            $duration = $this->xpath->query('.//span[contains( @class, "typo-track deco-typo-secondary")]', $trackNode)->item(0)->textContent;
            $tracks[] = ['track_id' => $track_id, 'name' => $name, 'album' => $album, 'duration' => $duration];
        }

        try {
            $pdo = new PDO("mysql:host=" . self::DBHOST . ";dbname=" . self::DBNAME, self::DBUSER, self::DBPASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare('REPLACE INTO author (author_id, name, listeners, likes) VALUES (:author_id, :name, :listeners, :likes)');
            $stmt->bindParam(':author_id', $authorInfo['author_id']);
            $stmt->bindParam(':name', $authorInfo['name']);
            $stmt->bindParam(':listeners', $authorInfo['listeners']);
            $stmt->bindParam(':likes', $authorInfo['likes']);
            $stmt->execute();

            foreach ($tracks as $track) {
                $stmt = $pdo->prepare('REPLACE INTO track (track_id, name, album, duration, author_id) VALUES (:track_id, :name, :album, :duration, :author_id )');
                $stmt->bindParam(':track_id', $track['track_id']);
                $stmt->bindParam(':name', $track['name']);
                $stmt->bindParam(':album', $track['album']);
                $stmt->bindParam(':duration', $track['duration']);
                $stmt->bindParam(':author_id', $authorInfo['author_id']);
                $stmt->execute();
            }

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }
}