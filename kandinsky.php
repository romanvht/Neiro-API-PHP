<?php
namespace kandinsky;

class imageGen{
    protected const API_KEY = "-";
	protected const SECRET_KEY = "-";

    protected static $instance; 

    public static function getInstance(){
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

	private static function get($url, $headers, $data){
		$curl = curl_init();
		
		if(empty($data))$post = 0;
		else $post = 1;
		
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_POST => $post,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => 1,
        ]);
        if(!empty($data)){
			if(!empty($data['params'])){
				$json_curlfile = new \CURLStringFile($data['params'], 'request.json', 'application/json');
				curl_setopt($curl, CURLOPT_POSTFIELDS, ['model_id' => $data['model_id'], 'params' => $json_curlfile]);
			}else{
				curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			}				
        }

        $result = curl_exec($curl);         
        return json_decode($result,true);
	}
   
	private static function get_model(){
		$url = "https://api-key.fusionbrain.ai/key/api/v1/models";
		$headers = [
			'X-Key: Key '.self::API_KEY,                
			'X-Secret: Secret '.self::SECRET_KEY
		];
		$result = self::get($url, $headers, false);
        return $result[0]['id'];
	}
	
	private static function check_generation($request_id, $attempts = 10, $delay = 10){
		$url = "https://api-key.fusionbrain.ai/key/api/v1/text2image/status/";
		$headers = [
			'X-Key: Key '.self::API_KEY,                
			'X-Secret: Secret '.self::SECRET_KEY
		];
		
		while($attempts > 0){
			$data = self::get($url.$request_id, $headers, false);
			if($data['status'] == 'DONE'){
                return $data['images'];
			}
			$attempts -= 1;
			sleep($delay);
		}
		return false;
	}

    public static function promt($question){
       $model_id = self::get_model();
	   
       if(!empty($question) && !empty($model_id)){
			$url = "https://api-key.fusionbrain.ai/key/api/v1/text2image/run";
			$headers = [
				'X-Key: Key '.self::API_KEY,                
				'X-Secret: Secret '.self::SECRET_KEY
			];
			
			$data = [
				"type" => "GENERATE",
				"numImages" => 1,
				"width" => 1024,
				"height" => 1024,
				"generateParams" => [
					"query" => $question
				]
			];

			$request = self::get($url, $headers, ['model_id' => 4, 'params' => json_encode($data)]);

			$uuid = $request['uuid'];
			
			$image = self::check_generation($uuid);
			
			if($image[0]){
				file_put_contents("/var/www/html/img/".$uuid.".jpg", file_get_contents('data:image/jpg;base64,'.$image[0]));
				$result = '<img src="http://82.147.71.126/img/'.$uuid.'.jpg">';
			}else{
				$result = 'Изображение не получено';
			}
       }else{
			$result = 'Model not found';
	   }		   
       return $result;   
    }
}

?>