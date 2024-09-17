<?php
namespace neiro;

class Gigachat {
    protected const CLIENT_AUTH = "key";
    protected const SYSTEM_PROMPT = "Ты виртуальный помощник";
    protected static $instance;
    private static $token = false;
    private static $tokenExp = false;
    private static $messages = [];

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::loadTokenData();
            self::$messages[] = [
              "role" => "system",
              "content" => self::SYSTEM_PROMPT,
            ];
        }

        return self::$instance;
    }

    private static function loadTokenData() {
        self::$token = file_get_contents('token.txt');
        self::$tokenExp = file_get_contents('token_ext.txt');
    }

    public static function getHistory() {
        return self::$messages;
    }

    public static function clearHistory() {
        self::$messages = [
          [
            "role" => "system",
            "content" => self::SYSTEM_PROMPT,
          ]
        ];
    }

    public static function updateHistory($messages) {
        self::$messages = $messages;
    }

    private static function httpRequest($url, $headers, $data = null) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => !empty($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        $result = curl_exec($curl);

        return json_decode($result, true);
    }

    private static function downloadImage($token, $imageId) {
        $url = 'https://gigachat.devices.sberbank.ru/api/v1/files/' . $imageId . '/content';
        $headers = [
            'Accept: application/jpg',
            'Authorization: Bearer ' . $token,
        ];

        $result = self::httpRequest($url, $headers);

        if ($result) {
            $filePath = "/var/www/html/img/" . $imageId . ".jpg";
            file_put_contents($filePath, $result);
            return "img/" . $imageId . ".jpg";
        }

        return false;
    }

    private static function generateGuid($data = null) {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function getToken($force = false) {
        if (!self::$token || !self::$tokenExp || self::$tokenExp < time() || $force) {
            $url = "https://ngw.devices.sberbank.ru:9443/api/v2/oauth";
            $headers = [
                'Authorization: Bearer ' . self::CLIENT_AUTH,
                'RqUID: ' . self::generateGuid(),
                'Content-Type: application/x-www-form-urlencoded',
            ];
            $data = [
                'scope' => 'GIGACHAT_API_PERS',
            ];
            $result = self::httpRequest($url, $headers, http_build_query($data));

            if (!empty($result["access_token"])) {
                self::$token = $result["access_token"];
                file_put_contents('token.txt', self::$token);
                self::$tokenExp = round($result["expires_at"] / 1000);
                file_put_contents('token_ext.txt', self::$tokenExp);
            } else {
                self::clearTokenData();
            }
        }

        return self::$token;
    }

    private static function clearTokenData() {
        self::$token = false;
        file_put_contents('token.txt', '');
        self::$tokenExp = false;
        file_put_contents('token_ext.txt', '');
    }

    public static function question($question, $temperature = 0.7) {
        if (empty($question)) {
            return "";
        }

        $token = self::getToken();
        if (!$token) {
            return "";
        }

        $url = "https://gigachat.devices.sberbank.ru/api/v1/chat/completions";
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $messages = self::$messages;
        $messages[] = ["role" => "user", "content" => $question];

        $data = [
            "model" => "GigaChat:latest",
            "temperature" => $temperature,
            "max_tokens" => 1024,
            "messages" => $messages,
        ];

        $result = self::httpRequest($url, $headers, json_encode($data));

        $answer = $result["choices"][0]["message"]["content"] ?? "";
        $answer = self::processImagesInAnswer($answer, $token);

        if (!empty($answer)) {
            self::updateHistoryWithAnswer($messages, $answer);
        }

        return $answer;
    }

    private static function updateHistoryWithAnswer(&$messages, $answer) {
        $messages[] = ["role" => "assistant", "content" => $answer];
        self::$messages = $messages;

        if (count(self::$messages) > 20) {
          self::clearHistory();
        }
    }

    private static function processImagesInAnswer($answer, $token) {
        preg_match_all('/<img[^>]*?src=\"(.*)\"/iU', $answer, $imageSearch);
        
        if (isset($imageSearch[1][0])) {
            $answer = preg_replace('/<img(?:\\s[^<>]*)?>/i', '', $answer);
            $image = self::downloadImage($token, $imageSearch[1][0]);
            $answer .= 'http://82.147.71.126/' . $image;
        }

        return $answer;
    }
}
