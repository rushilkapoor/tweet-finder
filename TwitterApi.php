<?php

/**
 * Trivial Twitter API class to accept a request and display filtered tweets satisfying some condition
 */
class TwitterApi {
    
    private $consumer_key;
    private $consumer_secret;
    private $oauth_access_token;
    private $oauth_access_token_secret;
    private $base_url;
    private $request_method;
    private $query;
    
    /**
     * Creates the API object and initializes the variables.
     *
     * @param array $credentials    Set of credentials of the user
     * 
     * @throws Exception When cURL isn't installed or incorrect credentials are sent
     */
    public function __construct($credentials) {
        if(!in_array('curl', get_loaded_extensions())){
            throw new Exception("Install curl to run the application.");
        }
        if (!isset($credentials['consumerKey']) || !isset($credentials['consumerSecret'])
            || !isset($credentials['oauthAccessToken']) || !isset($credentials['oauthAccessTokenSecret'])) {
            throw new Exception('Your credentials are incorrect or incomplete.');
        }
        $this->consumer_key = $credentials['consumerKey'];
        $this->consumer_secret = $credentials['consumerSecret'];
        $this->oauth_access_token = $credentials['oauthAccessToken'];
        $this->oauth_access_token_secret = $credentials['oauthAccessTokenSecret'];       
    }
    
    /**
     * Initializes other variables. Subject to change for every request call.
     *
     * @param string $base_url  Base url of API request
     * @param string $request_method    Type of API request
     * @param string $query Query parameters of the request
     */
    private function initializeParameters($base_url, $request_method, $query) {
        $this->base_url = $base_url;
        $this->request_method = $request_method;
        $this->query = $query;
    }
    
    /**
     * Creates base string for Oauth signature
     * 
     * @param string $parameters    Associative array of keys and values of request  
     * 
     * @return string $base   Base string 
     */
    private function createBaseString($parameters) {
        $key_val = array();
        ksort($parameters);
        foreach($parameters as $key => $value){
            $key_val[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $base = ($this->request_method . '&' . rawurlencode($this->base_url) . '&' . rawurlencode(implode('&', $key_val)));
        return $base;
    }
    
    /**
     * Build the Oauth object using class variables and parameters of API request
     * Essential for cURL request later
     *
     * @return array $oauth
     */
    private function createOauth() {
        $oauth = array(
            'oauth_consumer_key' => $this->consumer_key,
            'oauth_nonce' => time(),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_token' => $this->oauth_access_token,
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0'
        );
        $query_trim = str_replace('?', '', $this->query);
        $query_array = explode('=', $query_trim);
        $oauth[$query_array[0]] = $query_array[1];
        
        $base_string = $this->createBaseString($oauth);
        $secret_key = rawurlencode($this->consumer_secret) . '&' . rawurlencode($this->oauth_access_token_secret);
        $oauth_signature = base64_encode(hash_hmac('sha1', $base_string, $secret_key, true));
        $oauth['oauth_signature'] = $oauth_signature;
        return $oauth;
    }
    
    /**
     * Created Authorization header used bu cURL
     * 
     * @return string $auth_header  Comma separated header used in cURL request   
     */
    private function createAuthHeader() {     
        $oauth = $this->createOauth();
        $auth_header = 'Authorization: OAuth ';
        $values = array();
        foreach($oauth as $key => $value) {
            if (in_array($key, array('oauth_consumer_key', 'oauth_nonce', 'oauth_signature',
                'oauth_signature_method', 'oauth_timestamp', 'oauth_token', 'oauth_version'))) {
                $values[] = "$key=\"" . rawurlencode($value) . "\"";
            }
        }
        $auth_header .= implode(', ', $values);
        return $auth_header;
    }
    
    /**
     * Executes the actual request to Twitter API and receives JSON data
     *
     * @throws Exception    When an error occurs during cURL reuqest
     * 
     * @return string $result   JSON response from Twitter API
     */
    private function executeRequest() {
        $auth_header = $this->createAuthHeader();
        $query_array = explode('=', $this->query);
        $header = array($auth_header, 'Expect:');
        $options = array( CURLOPT_HTTPHEADER => $header,
                      CURLOPT_HEADER => false,
                      CURLOPT_URL => ($this->base_url . $query_array[0] . '=' . urlencode($query_array[1])),
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_SSL_VERIFYPEER => false);
        $feed = curl_init();
        curl_setopt_array($feed, $options);
        $result = curl_exec($feed);
        if (($error = curl_error($feed)) !== '') {
            curl_close($feed);
            throw new Exception($error);
        }
        curl_close($feed);
        return $result;
    }

    /**
     * Displays the tweets satisfying the conditions of retweeted atlest once
     * 
     * @param array   $result   JSON encoded response from the Twitter API
     */
    private function filterResult($result) {
        $decode = json_decode($result, true);
        if(isset($decode['errors'])){
            print_r($result);
            echo "\n";
            return;
        }
        echo "\nFollowing tweets are fetched as per the conditions : \n\n";
        $index = 1;
        if(isset($decode['statuses'])){
            foreach ($decode['statuses'] as $tweet) {
                if($tweet['retweet_count'] > 0){
                    echo "Tweet No. " . $index++ . " => ";
                    print_r($tweet['text']);
                    echo "\n\n";
                }
            }
        }
    }
    
    /**
     * Public function called from the class object. Calls other private functions to perform the task
     * 
     * @param string $base_url  Base url of API request
     * @param string $request_method    Type of API request
     * @param string $query Query parameters of the request
     */
    public function makeRequest($base_url, $request_method, $query) {
        $this->initializeParameters($base_url, $request_method, $query);
        $result = $this->executeRequest();
        $this->filterResult($result);
    }
    
}