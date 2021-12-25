<?php
/**
 * Class RestClient
 * Wraps HTTP calls using cURL, aimed for accessing and testing RESTful webservice. 
 * By Diogo Souza da Silva <manifesto@manifesto.blog.br>
 */
class RestClient {

     private $curl ;
     private $url ;
     private $response ="";
     private $headers = array();

     private $method="GET";
     private $params=null;
     private $contentType = null;
     private $timeOut = null;
     private $file =null;
	 private $request_headers = array();
     /**
      * Private Constructor, sets default options
      */
     private function __construct() {
         $this->curl = curl_init();
         curl_setopt($this->curl,CURLOPT_RETURNTRANSFER,true);
         curl_setopt($this->curl,CURLOPT_AUTOREFERER,true); // This make sure will follow redirects
         curl_setopt($this->curl,CURLOPT_FOLLOWLOCATION,true); // This too
         curl_setopt($this->curl,CURLOPT_HEADER,true); // THis verbose option for extracting the headers
		 curl_setopt($this->curl,CURLOPT_USERAGENT, "PHP/".phpversion() );
		 }

     /**
      * Execute the call to the webservice
      * @return RestClient
      */ 
     public function execute() {
         if($this->method === "POST") {
             curl_setopt($this->curl,CURLOPT_POST,true);
             curl_setopt($this->curl,CURLOPT_POSTFIELDS,$this->params);
         } else if($this->method == "GET"){
             curl_setopt($this->curl,CURLOPT_HTTPGET,true);
             $this->treatURL();
         } else if($this->method === "PUT") {
             curl_setopt($this->curl,CURLOPT_PUT,true);
             $this->treatURL();
             $this->file = tmpFile();
             fwrite($this->file,$this->params);
             fseek($this->file,0);
             curl_setopt($this->curl,CURLOPT_INFILE,$this->file);
             curl_setopt($this->curl,CURLOPT_INFILESIZE,strlen($this->params));
         } else {
             curl_setopt($this->curl,CURLOPT_CUSTOMREQUEST,$this->method);
         }
         if($this->contentType != null) {
			$this->request_headers[] = "Content-Type: ".$this->contentType;

         }
         if($this->timeOut != null) {
         	 curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT ,$this->timeOut); 
			 curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeOut); //timeout in seconds
         }
		debug($this->request_headers,'curl headers');
		curl_setopt($this->curl,CURLOPT_HTTPHEADER, $this->request_headers);
		debug($this->url,'curl $this-url');
		curl_setopt($this->curl,CURLOPT_URL,$this->url);
		$r = curl_exec($this->curl);
		$this->treatResponse($r); // Extract the headers and response
		return $this ;
     }

     /**
      * Treats URL
      */
	private function treatURL(){
		if(is_array($this->params) && count($this->params) >= 1) { // Transform parameters in key/value pars in URL
			if(!strpos($this->url,'?'))
				$this->url .= '?' ;
			$i = 0;
			foreach($this->params as $k=>$v) {
				if ($i) $this->url .= "&";
				$this->url .= urlencode($k)."=".urlencode($v);
				$i++;
			}
		}
		return $this->url;
	}
     /*
      * Treats the Response for extracting the Headers and Response
      */ 
     private function treatResponse($r) {
        if($r == null or strlen($r) < 1) {
			$this->headers['code'] = "408";
			$this->headers['message'] = "Request Timeout";
			$this->response = "Request Timeout";
            return;
        }
		debug($r,'curl response');
        $parts  = explode("\n\r",$r); // HTTP packets define that Headers end in a blank line (\n\r) where starts the body
        while(preg_match('@HTTP/1.[0-1] 100 Continue@',$parts[0]) or preg_match("@Moved@",$parts[0])) {
            // Continue header must be bypass
            for($i=1;$i<count($parts);$i++) {
                $parts[$i - 1] = trim($parts[$i]);
            }
            unset($parts[count($parts) - 1]);
        }
        preg_match("@Content-Type: ([a-zA-Z0-9-]+/?[a-zA-Z0-9-]*)@",$parts[0],$reg);// This extract the content type
		if (empty($reg)) {
			$reg[] = "";
			$reg[] = "";
			$reg[] = "";
		}
		$this->headers['content-type'] = $reg[1];
		debug($parts[0],'parts_0');

		preg_match("@HTTP/[1-2](.[0-1])? ([0-9]{3}) ([a-zA-Z ]+)?@",$parts[0],$reg); // This extracts the response header Code and Message
		debug($reg,'matches');
		$this->headers['code'] = $reg[2];
		if (array_key_exists(3, $reg)) $this->headers['message'] = $reg[3];
		$this->response = "";
		
		for($i=1;$i<count($parts);$i++) {//This make sure that exploded response get back togheter
			if($i > 1) {
				$this->response .= "\n\r";
			}
			$this->response .= $parts[$i];
		}
		$this->response = ltrim($this->response,"\n");
     }

     /*
      * @return array
      */
     public function getHeaders() {
        return $this->headers;
     }

     /*
      * @return string
      */ 
     public function getResponse() {
         return $this->response;
     }

     /*
      * HTTP response code (404,401,200,etc)
      * @return int
      */
     public function getResponseCode() {
         return (int) $this->headers['code'];
     }
     
     /*
      * HTTP response message (Not Found, Continue, etc )
      * @return string
      */
     public function getResponseMessage() {
         return $this->headers['message'];
     }

     /*
      * Content-Type (text/plain, application/xml, etc)
      * @return string
      */
     public function getResponseContentType() {
         return $this->headers['content-type'];
     }

     /**
      * This sets that will not follow redirects
      * @return RestClient
      */
     public function setNoFollow() {
         curl_setopt($this->curl,CURLOPT_AUTOREFERER,false);
         curl_setopt($this->curl,CURLOPT_FOLLOWLOCATION,false);
         return $this;
     }

     /**
      * This closes the connection and release resources
      * @return RestClient
      */
     public function close() {
         curl_close($this->curl);
         $this->curl = null ;
         if($this->file !=null) {
             fclose($this->file);
         }
         return $this ;
     }

     /**
      * Sets the URL to be Called
      * @return RestClient
      */
     public function setUrl($url) {
         $this->url = $url; 
         return $this;
     }

     /**
      * Set the Content-Type of the request to be send
      * Format like "application/xml" or "text/plain" or other
      * @param string $contentType
      * @return RestClient
      */
     public function setContentType($contentType) {
         $this->contentType = $contentType;
         return $this;
     }

     /**
      * Set the Content-Type of the request to be send
      * Format like "application/xml" or "text/plain" or other
      * @param string $contentType
      * @return RestClient
      */
     public function setTimeout($timeOut) {
         $this->timeOut = $timeOut;
         return $this;
     }
     
     /**
      * Set the Credentials for BASIC Authentication
      * @param Array (
			'method' = "BASIC"/"OAUTH1"/"OAUTH2"
			'username' 
			'password' 
      * @param string $pass
      * @return RestClient
      */
     public function setCredentials($credentials, $method=NULL, $url = NULL, $body = NULL) {
		if (isset($credentials)) {
			if ($credentials['method'] == "BASIC") { 
				if($credentials['username'] != null) {
					curl_setopt($this->curl,CURLOPT_HTTPAUTH,CURLAUTH_BASIC);
					curl_setopt($this->curl,CURLOPT_USERPWD,"{$credentials['username']}:{$credentials['password']}");
				}
			} elseif ($credentials['method'] == "OAUTH1") {
				if($credentials['username'] != null) {
					$consumer = new OAuthConsumer($credentials['username'], $credentials['password']);
					$request = OAuthRequest::from_consumer_and_token($consumer, NULL,$method , $url, $body);
					$request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);
					$this->request_headers[] = $request->to_header();
				}
			} elseif ($credentials['method'] == "OAUTH2") {
				if($credentials['username'] != null) {
									curl_setopt($this->curl,CURLOPT_REFERER,"aview.htm");
									curl_setopt($this->curl,CURLOPT_USERPWD,"{$credentials['username']}:{$credentials['password']}");
							}
			} elseif ($credentials['method'] == "BEAR") {
					// curl_setopt($this->curl,CURLOPT_HTTPAUTH,CURLAUTH_BEARER );
					//curl_easy_setopt($this->curl, CURLOPT_XOAUTH2_BEARER, $credentials['api_key']);
					$authorization = "Authorization: Bearer ". $credentials['api_key'];
					$this->request_headers[] = $authorization;
			} elseif ($credentials['method'] == "DIGEST") {
							if($credentials['username'] != null) {
									curl_setopt($this->curl,CURLOPT_HTTPAUTH,CURLAUTH_DIGEST);
									curl_setopt($this->curl,CURLOPT_REFERER,"aview.htm");
									curl_setopt($this->curl,CURLOPT_USERPWD,"{$credentials['username']}:{$credentials['password']}");
							}
			}
		}
        return $this;
     }

     /**
      * Set the Request HTTP Method
      * For now, only accepts GET and POST
      * @param string $method
      * @return RestClient
      */
     public function setMethod($method) {
         $this->method=$method;
         return $this;
     }

     /**
      * Set Parameters to be send on the request
      * It can be both a key/value par array (as in array("key"=>"value"))
      * or a string containing the body of the request, like a XML, JSON or other
      * Proper content-type should be set for the body if not a array
      * @param mixed $params
      * @return RestClient
      */
     public function setParameters($params) {
         $this->params=$params;
         return $this;
     }

     /**
      * Creates the RESTClient
      * @param string $url=null [optional]
      * @return RestClient
      */
     public static function createClient($url=null) {
         $client = new RestClient ;
         if($url != null) {
             $client->setUrl($url);
         }
         return $client;
     }

     /**
      * Convenience method wrapping a commom POST call
      * @param string $url
      * @param mixed params
      * @param string $credentials=null [optional]
      * @param string $contentType="multpary/form-data" [optional] commom post (multipart/form-data) as default
      * @return RestClient
      */
//   public static function post($url,$params=null,$user=null,$pwd=null,$contentType="multipart/form-data",$timeout=null) {
     public static function post($url,$params=null,$credentials=null,$contentType="application/json", $timeout=null) {
         return self::call("POST",$url,$params,$credentials,$contentType,$timeout);
     }

     /**
      * Convenience method wrapping a commom PUT call
      * @param string $url
      * @param string $body 
      * @param string $credentials=null [optional]
      * @param string $contentType=null [optional] 
      * @return RestClient
      */
     public static function put($url,$body,$credentials=null,$contentType="application/json",$timeout=null) {
         return self::call("PUT",$url,$body,$credentials,$contentType,$timeout);
     }

     /**
      * Convenience method wrapping a commom GET call
      * @param string $url
      * @param array params
      * @param string $credentials [optional]
      * @return RestClient
      */
     public static function get($url,array $params=null, $credentials=null, $timeout=null) {
         return self::call("GET",$url,$params,$credentials,"application/json",$timeout);
     }

     /**
      * Convenience method wrapping a commom delete call
      * @param string $url
      * @param array params
      * @param string $credentials=null [optional]
      * @return RestClient
      */
     public static function delete($url,array $params=null,$credentials=null,$timeout=null) {
         return self::call("DELETE",$url,$params,$credentials, "application/json",$timeout);
     }

     /**
      * Convenience method wrapping a commom custom call
      * @param string $method
      * @param string $url
      * @param string $body 
      * @param string $credentials=null [optional]
      * @param string $contentType=null [optional] 
      * @return RestClient
      */
     public static function call($method,$url,$body,$credentials,$contentType="application/json",$timeout=null) {

         return self::createClient($url)
             ->setParameters($body)
             ->setContentType($contentType)
             ->setMethod($method)
             ->setCredentials($credentials, $method, $url, $body)
             ->setTimeout($timeout)
             ->execute()
             ->close();
     }
}
?>
