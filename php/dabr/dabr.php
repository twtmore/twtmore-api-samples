<?php

/**
 * First, locate your "twitter.php" file in "/common/twitter.php" and replace it with the following one
 *
 * @see http://code.google.com/p/dabr/source/browse/trunk/common/twitter.php
 */

 function twitter_update() {

    twitter_ensure_post_action();
    $status = stripslashes(trim($_POST['status']));

    $callback_key = false;
    if (mb_strlen($status, 'utf-8') > 140) {

    	$reply_to_id = null;
        if (is_numeric((string) $_POST['in_reply_to_id'])) {
            $reply_to_id = (string) $_POST['in_reply_to_id'];
        }

    	$response = post_twtmore_tweet("blabla", $status, $reply_to_id);

    	if (!$response) {
    		
    		theme('error', "<h2>twtmore error</h2><p>An unexpected error occured while posting to twtmore. Please try again.</p><hr>");
    		twitter_refresh($_POST['from'] ? $_POST['from'] : '');
    		return;
    	}

    	$status = $response->tweet->short_content;
		$callback_key = $response->callback_key;
    }

    if ($status) {

        $request = API_URL.'statuses/update.json';
        $post_data = array('source' => 'dabr', 'status' => $status);
        $in_reply_to_id = (string) $_POST['in_reply_to_id'];
        if (is_numeric($in_reply_to_id)) {
                $post_data['in_reply_to_status_id'] = $in_reply_to_id;
        }
        // Geolocation parameters
        list($lat, $long) = explode(',', $_POST['location']);
        $geo = 'N';
        if (is_numeric($lat) && is_numeric($long)) {
                $geo = 'Y';
                $post_data['lat'] = $lat;
                $post_data['long'] = $long;
                // $post_data['display_coordinates'] = 'false';
                
                // Turns out, we don't need to manually send a place ID
/*                      $place_id = twitter_get_place($lat, $long);
                if ($place_id) {
                
                        // $post_data['place_id'] = $place_id;
                }
*/                      
        }
        setcookie_year('geo', $geo);
        $b = twitter_process($request, $post_data);

        if ($callback_key) {
        	
        	// After you post to twitter
			post_twtmore_callback($callback_key, $b->id_str);
        }
    }

    twitter_refresh($_POST['from'] ? $_POST['from'] : '');
}

/**
 * Now just add ALL of the following code (the two functions and the DEFINE()) to the bottom of your twitter.php file
 * DONT FORGET TO FILL IN YOUR API KEY!!
 **/

/***********************
 ** TWTMORE FUNCTIONS **
 ***********************/

 define('TWTMORE_API_KEY', '__PUT_YOUR_API_KEY_HERE__');

/**
 * Use this function to post a tweet to twtmore, and then to Twitter API after.
 *
 * @see http://dev.twtmore.com/docs/api/shorten
 *
 * @param $tweet - The text of the tweet, > 140 characters
 * @param $username - The username of the user posting the tweet, eg "tarnfeld" or "twtmore"
 * @param $reply_to_user - If this tweet is replying, this is the username for that user
 * @param $reply_to_tweet_id - If this tweet is replying, this is the ID of the twitter status it is replying to
 *
 * @return StdClass Object or FALSE
 *
 */
function post_twtmore_tweet($username, $tweet, $reply_to_user = null, $reply_to_tweet_id = null)
{
	
	// Formulate the request
	$request = array(
		'apikey' => TWTMORE_API_KEY,
		'user' => $username,
		'tweet' => $tweet
	);
	
	// If reply
	if ($reply_to_user && $reply_to_tweet_id)
	{
		$request['reply_to_user'] = $reply_to_user;
		$request['reply_to_tweet'] = $reply_to_tweet_id;
	}
	
	// Create CURL
	$url = 'http://api.twtmore.com/v3/shorten';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
	
	// Execute CURL
	$response = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	// Close CURL
	curl_close($ch);

	// Check we have a % 200 HTTP status code, and the JSON decodes ok
	if ($code == 200 && ($resp = json_decode($response)))
	{
		return $resp;
	}
	
	// There was an error
	return false;
}

/**
 * Use this AFTER you post to twtmore AND you post to twitter and have the TWITTER STATUS ID
 *
 * @see http://dev.twtmore.com/docs/api/callback
 *
 * @param $callback_key - The "callback_key" part of the post_twtmore_tweet() response
 * @param $twitter_id - The TWITTER ID (eg: 123368090347114496) - "id_str" from the TWITTER API
 */
function post_twtmore_callback($callback_key, $twitter_id)
{
	$request = array(
		'apikey' => TWTMORE_API_KEY,
		'key' => $callback_key,
		'status_id' => $twitter_id
	);
	
	$url = 'http://api.twtmore.com/v3/callback';
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
	
	// Execute CURL
	$response = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	// We don't return anything because this API method doesn't return anything important...
}
