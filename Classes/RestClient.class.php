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

     /**
      * Private Constructor, sets default options
      */
     private function __construct() {
         $this->curl = curl_init();
         curl_setopt($this->curl,CURLOPT_RETURNTRANSFER,true);
         curl_setopt($this->curl,CURLOPT_AUTOREFERER,true); // This make sure will follow redirects
         curl_setopt($this->curl,CURLOPT_FOLLOWLOCATION,true); // This too
         curl_setopt($this->curl,CURLOPT_HEADER,true); // THis verbose option for extracting the headers

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
             curl_setopt($this->curl,CURLOPT_HTTPHEADER,array("Content-Type: ".$this->contentType));
         }
         if($this->timeOut != null) {
         	 curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT ,$this->timeOut); 
			 curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeOut); //timeout in seconds
         }
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
		preg_match("@HTTP/1.[0-1] ([0-9]{3}) ([a-zA-Z ]+)@",$parts[0],$reg); // This extracts the response header Code and Message
		$this->headers['code'] = $reg[1];
		$this->headers['message'] = $reg[2];
		$this->response = "";
		for($i=1;$i<count($parts);$i++) {//This make sure that exploded response get back togheter
			if($i > 1) {
				$this->response .= "\n\r";
			}
			$this->response .= $parts[$i];
		}
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
				curl_setopt($this->curl, CURLOPT_HTTPHEADER, array($request->to_header()));  
			}
		} elseif ($credentials['method'] == "OAUTH2") {
			if($credentials['username'] != null) {
//
//  Get token here
//
$baseURL='https://o7xvo6j2e2.execute-api.us-east-2.amazonaws.com/v0';
//$scopes=urlencode('708f8c17-1d0e-4647-95fe-f16cf2abd82f 8ef2cf6a-dfa3-43ed-8661-0c7a00e2274a 26fdf438-46e0-442a-aa07-9b91074f4136 589fb94e-c524-48ad-bd6f-ab9fcec84b08 4719cd61-155f-4203-808d-42b9cbf4e651 7f44a19b-8545-4408-a442-490c76d2b016');
//$url = $baseURL.'/auth/tokens?grantTypeCode=CLIENT_CREDENTIALS&tokenTypeCode=JWT&state=ACTIVE&tokenAccessTypeCode=AUTHZ&scopes='.$scopes;

// Authorizations
$consoleClientId = '430e7b34-164a-11e8-b642-0ed5f89f718b';
$url = $baseURL.'/auth/authorizations?clientId='.$consoleClientId.'&responseTypeCode=TOKEN&state=xyz&requestedScope=CompanyScope';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch,CURLOPT_HTTPHEADER,array("Content-Type: ".$this->contentType, "client.auth.response.use-redirect: false"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
//curl_setopt($ch, CURLOPT_POSTFIELDS, array());
$data = curl_exec($ch);
//echo "<pre>";
//var_dump($data);
//echo "</pre>";
$json_data= json_decode($data);
//$token=$json_data->data->accessToken;
$sessionId=$json_data->data->sessionId;
curl_close($ch);

// Session Login
$url = $baseURL.'/auth/sessions/'.$sessionId;
$ch = curl_init();
$params = json_encode(array (
	"userDefinedId" => $credentials['username'] ,
	"userPassword" => $credentials['password'] ,
	"deviceFingerprint" => "111",
	"statusCode" => "ACTIVE", 
	"state" => "validstate", 
	"sessionTypeCode" => "IMPLICIT_AUTHN",
	"clientId" => $consoleClientId
));
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-Type: ".$this->contentType, "client.auth.response.use-redirect: false"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
$data = curl_exec($ch);
if (curl_error($ch)) {
    $error_msg = curl_error($ch);
}
echo "<pre>";
var_dump($data);
echo "</pre>";
$json_data= json_decode($data);
$initialToken=$json_data->data->token;
$userId=$json_data->data->userId;
curl_close($ch);

// Session Login
$url = $baseURL.'/auth/tokens/exchanges';
$ch = curl_init();
$params = json_encode(array (
	"userId" => $userId ,
	"tenantId" =>  "c8dacbcf-b04e-4d9b-a26e-266b69e9adf2"
));
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_HTTPHEADER,array("Content-Type: ".$this->contentType, "Authorization: ". $initialToken));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
$data = curl_exec($ch);
if (curl_error($ch)) {
    $error_msg = curl_error($ch);
}
echo "<pre>";
var_dump($data);
echo "</pre>";
$json_data= json_decode($data);
$jwtToken=$json_data->data->accessToken;
curl_close($ch);
curl_setopt($this->curl,CURLOPT_HTTPHEADER,array("Content-Type: ".$this->contentType, "Authorization: ".$jwtToken, "client.unique-transaction.id: "."hello-there"));




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
     public static function get($url,array $params=null,$credentials=null, $timeout=null) {
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
