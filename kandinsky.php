<?php
namespace neiro;

class ImageGen {
    protected const API_KEY = "key";
    protected const SECRET_KEY = "key";
    protected static $instance;

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
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
            if (!empty($data['params'])) {
                $jsonCurlFile = new \CURLStringFile($data['params'], 'request.json', 'application/json');
                curl_setopt($curl, CURLOPT_POSTFIELDS, ['model_id' => $data['model_id'], 'params' => $jsonCurlFile]);
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
        }

        $result = curl_exec($curl);

        return json_decode($result, true);
    }

    private static function getModelId() {
        $url = "https://api-key.fusionbrain.ai/key/api/v1/models";
        $headers = [
            'X-Key: Key ' . self::API_KEY,
            'X-Secret: Secret ' . self::SECRET_KEY,
        ];
        $result = self::httpRequest($url, $headers);
        return $result[0]['id'] ?? null;
    }

    private static function checkGenerationStatus($requestId, $attempts = 10, $delay = 10) {
        $url = "https://api-key.fusionbrain.ai/key/api/v1/text2image/status/" . $requestId;
        $headers = [
            'X-Key: Key ' . self::API_KEY,
            'X-Secret: Secret ' . self::SECRET_KEY,
        ];

        while ($attempts > 0) {
            $data = self::httpRequest($url, $headers);
            if ($data['status'] === 'DONE') {
                return $data['images'];
            }
            $attempts--;
            sleep($delay);
        }

        return false;
    }

    public static function question($question) {
        $modelId = self::getModelId();

        if (empty($question) || empty($modelId)) {
            return 'Model not found';
        }

        $url = "https://api-key.fusionbrain.ai/key/api/v1/text2image/run";
        $headers = [
            'X-Key: Key ' . self::API_KEY,
            'X-Secret: Secret ' . self::SECRET_KEY,
        ];

        $promptData = self::parsePrompt($question);
        $data = [
            "type" => "GENERATE",
            "numImages" => 1,
            "width" => $promptData['size']['width'],
            "height" => $promptData['size']['height'],
            "generateParams" => ["query" => $promptData['question']],
        ];

        $request = self::httpRequest($url, $headers, ['model_id' => $modelId, 'params' => json_encode($data)]);
        $uuid = $request['uuid'] ?? null;

        if ($uuid) {
            $images = self::checkGenerationStatus($uuid);
            if ($images[0]) {
                $filePath = "/var/www/html/img/" . $uuid . ".jpg";
                file_put_contents($filePath, file_get_contents('data:image/jpg;base64,' . $images[0]));
                return 'http://82.147.71.126/img/' . $uuid . '.jpg';
            }
        }

        return 'Изображение не получено';
    }

    private static function parsePrompt($question) {
        $prompt = ['question' => $question, 'size' => ['width' => 1024, 'height' => 1024]];
        $allowedAspectRatios = ['16:9', '9:16', '3:2', '2:3'];

        if (preg_match("|$$(([0-9]{1,2}):([0-9]{1,2}))$$|si", $question, $aspectRatio)) {
            $question = str_replace($aspectRatio[0], '', $question);
            if (in_array($aspectRatio[1], $allowedAspectRatios)) {
                $prompt['size'] = self::adjustSizeForAspectRatio($aspectRatio, $prompt['size']);
            }
        }
        $prompt['question'] = $question;

        return $prompt;
    }

    private static function adjustSizeForAspectRatio($aspectRatio, $size) {
        if ($aspectRatio[2] > $aspectRatio[3]) {
            $size['height'] = floor(($size['height'] / $aspectRatio[2]) * $aspectRatio[3]);
        } else {
            $size['width'] = floor(($size['width'] / $aspectRatio[3]) * $aspectRatio[2]);
        }
        return $size;
    }
}