<?php
namespace neiro;

class YaChat {
    protected const CLIENT_AUTH = "key";
    protected const X_FOLDER_ID = "folder";
    protected static $instance;
    private static $token = false;
    private static $tokenExp = false;
    private static $messages = [];

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
            self::loadTokenData();
        }

        return self::$instance;
    }

    private static function loadTokenData() {
        self::$token = file_get_contents('token_ya.txt');
        self::$tokenExp = file_get_contents('token_ya_ext.txt');
    }

    public static function getHistory() {
        return self::$messages;
    }

    public static function clearHistory() {
        self::$messages = [];
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

    public static function getToken($force = false) {
        if (!self::$token || !self::$tokenExp || self::$tokenExp < time() || $force) {
            $url = "https://iam.api.cloud.yandex.net/iam/v1/tokens";
            $data = json_encode(['yandexPassportOauthToken' => self::CLIENT_AUTH]);
            $headers = [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data),
            ];
            $result = self::httpRequest($url, $headers, $data);

            if (!empty($result["iamToken"])) {
                self::$token = $result["iamToken"];
                file_put_contents('token_ya.txt', self::$token);
                self::$tokenExp = strtotime($result["expiresAt"]);
                file_put_contents('token_ya_ext.txt', self::$tokenExp);
            } else {
                self::clearTokenData();
            }
        }

        return self::$token;
    }

    private static function clearTokenData() {
        self::$token = false;
        file_put_contents('token_ya.txt', '');
        self::$tokenExp = false;
        file_put_contents('token_ya_ext.txt', '');
    }

    public static function question($question, $temperature = 0.6) {
        if (empty($question)) {
            return "";
        }

        $token = self::getToken();
        if (!$token) {
            return "";
        }

        $url = "https://llm.api.cloud.yandex.net/foundationModels/v1/completion";
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'x-folder-id: ' . self::X_FOLDER_ID,
        ];

        $messages = self::$messages;
        $messages[] = ["role" => "user", "text" => $question];

        $data = [
            "modelUri" => "gpt://" . self::X_FOLDER_ID . "/yandexgpt-lite/latest",
            "completionOptions" => [
                "stream" => false,
                "temperature" => $temperature,
                "max_tokens" => 2000,
            ],
            "messages" => $messages,
        ];
        $result = self::httpRequest($url, $headers, json_encode($data));

        $answer = $result['result']['alternatives'][0]['message']['text'] ?? "";

        if (!empty($answer)) {
            self::updateHistoryWithAnswer($messages, $answer);
        }

        return $answer;
    }

    private static function updateHistoryWithAnswer(&$messages, $answer) {
        $messages[] = ["role" => "assistant", "text" => $answer];
        self::$messages = $messages;

        if (count($messages) > 20) {
            self::clearHistory();
        }
    }
}

