<?
// dummy example of RestClient
include 'RestClient.class.php';
$twitter = RestClient::post( // Same for RestClient::get()
            "http://twitter.com/statuses/update.json"
            ,array("status"=>"Working with RestClient from RestServer!") 
            ,array( "method" => "BASIC", "username" => "john", "password" => "doe"
			, 30);

var_dump($twitter->getResponse());
var_dump($twitter->getResponseCode());
var_dump($twitter->getResponseMessage());
var_dump($twitter->getResponseContentType());

// Other examples
$url = "http://example";
$method = "BASIC";
$username = "user";
$password = "password";
$method = "OAUTH1";
$client_id = "fuckinglonghexstring";
$secret = "shorterbutstillfuckinglonghexstring";

$ex = RestClient::get($url);
$ex = RestClient::get($url,null,$credentials);
$ex = RestClient::get($url,array('key'=>'value'));
$ex = RestClient::get($url,array('key'=>'value'),$credentials);

//content post
$ex = RestClient::post($url);
$ex = RestClient::post($url,null,$credentials);
$ex = RestClient::post($url,array('key'=>'value'));
$ex = RestClient::post($url,array('key'=>'value'),$credentials); 
$ex = RestClient::post($url,"some text",$credentials,"text/plain");
$ex = RestClient::post($url,"{ name: 'json'}",$credentials,"application/json");
$ex = RestClient::post($url,"<xml>Or any thing</xml>",$credentials,"application/xml");

// General cases
$get = RestClient::get($url,array("q"=>"diogok.json","key"=>"value"),$credentials);
$post = RestClient::post($url,array("q"=>"diogok.json","key"=>"value"),$credentials);
$post = RestClient::post($url,"This is my json",$credentials,"text/plain");
$post = RestClient::post($url."?key=diogok","This is my json",$credentials,"text/plain");
$put = RestClient::put($url,"This is my json",$credentials,"text/plain");
$delete = RestClient::delete($url."?key=diogok",array("key"=>"value"),$credentials);
$http = RestClient::call("OPTIONS",$url."?key=diogok",array("key"=>"values"),$credentials,"text/plain");
?>