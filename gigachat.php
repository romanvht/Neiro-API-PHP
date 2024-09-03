<?php
namespace neiro;

class Gigachat {
  protected const CLIENT_AUTH = "key";

  protected static $instance;
  private static $token = false;
  private static $tokenExp = false;
  private static $messages = [];

  public static function getInstance() {
    if (is_null(self::$instance)) {
      self::$instance = new self();
      self::$token = file_get_contents('token.txt');
      self::$tokenExp = file_get_contents('token_ext.txt');
    }
    return self::$instance;
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

  private static function get($url, $headers, $data) {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_SSL_VERIFYPEER => 0,
      CURLOPT_POST => 1,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_RETURNTRANSFER => 1,
    ]);
    if (!empty($data)) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    }

    $result = curl_exec($curl);
    return json_decode($result, true);
  }

  private static function get_image($token, $image) {
    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL =>
        'https://gigachat.devices.sberbank.ru/api/v1/files/' .
        $image .
        '/content',
      CURLOPT_SSL_VERIFYPEER => 0,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => [
        'Accept: application/jpg',
        'Authorization: Bearer ' . $token,
      ],
    ]);

    $result = curl_exec($curl);

    if ($result) {
      file_put_contents("/var/www/html/img/" . $image . ".jpg", $result);
      $img = "img/" . $image . ".jpg";
    } else {
      $img = false;
    }

    return $img;
  }

  private static function guidv4($data = null) {
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  public static function getToken($force = false) {
    if (
      !self::$token ||
      !self::$tokenExp ||
      self::$tokenExp < time() ||
      $force === true
    ) {
      $url = "https://ngw.devices.sberbank.ru:9443/api/v2/oauth";
      $headers = [
        'Authorization: Bearer ' . self::CLIENT_AUTH,
        'RqUID: ' . self::guidv4(),
        'Content-Type: application/x-www-form-urlencoded',
      ];
      $data = [
        'scope' => 'GIGACHAT_API_PERS',
      ];
      $result = self::get($url, $headers, http_build_query($data));

      if (!empty($result["access_token"])) {
        self::$token = $result["access_token"];
        file_put_contents('token.txt', self::$token);
        self::$tokenExp = round($result["expires_at"] / 1000);
        file_put_contents('token_ext.txt', self::$tokenExp);
      } else {
        self::$token = false;
        file_put_contents('token.txt', '');
        self::$tokenExp = false;
        file_put_contents('token_ext.txt', '');
      }
    }
    return self::$token;
  }

  public static function answer($question, $temperature = 0.7) {
    $answer = "";
    if (!empty($question)) {
      $tok = self::getToken();

      $no_history = false;
      if (preg_match('/нарисуй|изобрази/uis', $question, $matches)) {
        $no_history = true;
        $temperature = 1;
      }

      if ($tok) {
        $url = "https://gigachat.devices.sberbank.ru/api/v1/chat/completions";
        $headers = [
          'Authorization: Bearer ' . $tok,
          'Content-Type: application/json',
          'Accept: application/json',
        ];
        if ($no_history === false) {
          $messages = self::$messages;
        }
        $messages[] = [
          "role" => "user",
          "content" => $question,
        ];
        $data = [
          "model" => "GigaChat:latest",
          "temperature" => $temperature,
          "max_tokens" => 1024,
          "messages" => $messages,
        ];
        $result = self::get($url, $headers, json_encode($data));

        $answer = $result["choices"][0]["message"]["content"];

        if (!empty($answer)) {
          if ($no_history === false) {
            $messages[] = [
              "role" => "assistant",
              "content" => $answer,
            ];

            self::$messages = $messages;
          }

          if (count($messages) > 1000) {
            self::clearHistory();
          }

          preg_match_all('/<img[^>]*?src=\"(.*)\"/iU', $answer, $imageSearch);
          if (isset($imageSearch[1][0])) {
            $answer = preg_replace('/<img(?:\\s[^<>]*)?>/i', '', $answer);
            $image = self::get_image($tok, $imageSearch[1][0]);
            $answer .= 'http://82.147.71.126/' . $image;
          }
        }
      }
    }
    return $answer;
  }
}

?>
