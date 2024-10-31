<?php

/* Copyright 2011 Ryan Nutt - Aelora Web Services LLC
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public Licese, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation,Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

/*
Plugin Name: rTwit
Plugin URI: http://www.nutt.net/tag/rtwit/
Description: Insert a specific Twitter feed on any post or page on your blog
Author: Ryan Nutt
Version: 0.1.1
Author URI: http://www.nutt.net
*/

add_action('admin_menu', 'rtwit_create_menu');
add_filter('pre_update_option_rtwit_options', 'rtwit_update_options');
add_shortcode('rtwit', 'rtwit_shortcode_handler');

register_activation_hook(__FILE__,'rtwit_activate');
register_deactivation_hook(__FILE__,'rtwit_deactivate');

add_action('the_posts', 'rtwit_add_css');

//wp_register_style('rtwit-css', plugins_url('rTwit.css', __FILE__));
//wp_enqueue_style('rtwit-css');
function rtwit_create_menu() {
    add_options_page('rTwit Options', 'rTwit', 'manage_options', 'rTwit-options', 'rtwit_options');
}

/** Code to setup the plugin for use */
function rtwit_activate() {
    add_option('rtwit_options', rtwit_defaults(), '', 'no');
}

/** Code to cleanup if they decide to deactivate plugin */
function rtwit_deactivate() {
    delete_option('rtwit_options');
}

/** Filter for updating the options    */
function rtwit_update_options($data)  {
    
    $current = get_option('rtwit_options');
    $defaults = rtwit_defaults();

    if ( ! is_array($current)) {
        $current = $defaults;
    }
    else {
        $current = array_merge($defaults, $current);
    }

    /* Now we need to clean up $data and put the information into options
     * if appropriate and in correct format...
     */
    if (isset($data['cache']) && is_array($data['cache'])) {
        $current['cache'] = $data['cache'];
    }
    if (isset($data['cacheLength']) && (int)$data['cacheLength'] > 0) {
        $current['cacheLength'] = (int)$data['cacheLength'];
    }
    if (isset($data['tweetCount']) && (int)$data['tweetCount'] > 0) {
        $current['tweetCount'] = (int)$data['tweetCount'];
    }
    if ($current['useCache'] == 'on') {
        $current['useCache'] = true;
    }
    if ($current['newWindow'] == 'on') {
        $current['newWindow'] == true;
    }

    if (isset($data['defaultAccount'])) {
        $current['defaultAccount'] = trim($data['defaultAccount']);
    }
    if (isset($data['htmlBefore'])) {
        $current['htmlBefore'] = trim($data['htmlBefore']);
    }
    if (isset($data['htmlAfter'])) {
        $current['htmlAfter'] = trim($data['htmlAfter']); 
    }

    /* Check for the checkbox values.  We'll assume that if tweetCount
     * and defaultAccount are set in $data then it's coming from
     * the options page admin-side and the checkbox values need to
     * be updated.
     */
    if (isset($data['defaultAccount']) && isset($data['tweetCount'])) {
        $current['useCache'] = (bool)$data['useCache'];
        $current['newWindow'] = (bool)$data['newWindow'];
        $current['loadCSS'] = (bool)$data['loadCSS'];
        if (isset($_POST['rtwit-clear-cache']) && $_POST['rtwit-clear-cache']==1) {
            $current['cache'] = array(); 
        }
    }
    
    return $current;
}

/**
 * Callback to handle the short codes.
 *
 * This function is pretty much a cleanup.  The actual output is handed off to
 * rtwit_get_code.
 */
function rtwit_shortcode_handler($atts, $content=null, $code = '') {


    /* Get the default options.  We'll overridge them if the appropriate
     * tags are available in $atts
     */
    $opts = get_option('rtwit_options');
     
    $opts['account'] = strtolower((isset($atts['account'])) ? $atts['account'] : $opts['defaultAccount']);
    $opts['useCache'] = (isset($atts['cache'])) ? (bool)$atts['cache']: $opts['useCache'];
    $opts['tweetCount'] = (isset($atts['count']) && (int)$atts['count'] > 0) ? (int)$atts['count'] : $opts['tweetCount'];
    $opts['cacheLength'] = (isset($atts['cache_length']) && (int)$atts['cache_length'] > 0) ? (int)$atts['cache_length'] : $opts['cacheLength'];
    $opts['newWindow'] = (isset($atts['new_window'])) ? (bool)$atts['new_window'] : $opts['newWindow'];
    $opts['htmlBefore'] = (isset($atts['html_before'])) ? $atts['html_before'] : $opts['htmlBefore'];
    $opts['htmlAfter'] = (isset($atts['html_after'])) ? $atts['html_after'] : $opts['htmlAfter']; 
    
    // We need to clean up a few things
    $opts['account'] = trim($opts['account']);
    $opts['account'] = preg_replace('/^@/', '', $opts['account']);

    

    /* If there still isn't an account available we'll just return
     * a message as an HTML comment so nothing shows on the screen
     * but the owner can still see what's going on.
     */
    if ($opts['account'] == '') {
        return "<!-- No account set for rTwit -->\n";
    }

    return rtwit_get_code($opts);

}

function rtwit_add_css($posts) {
    $opts = get_option('rtwit_options');

    if ($opts['loadCSS']) {

        if (empty($posts)) {
            return $posts;
        }

        $found = false;
        foreach ($posts as $post) {
            //var_dump($post->post_content); echo "\n";
            if (stripos($post->post_content, '[rtwit') !== false) {
                $found = true;
                break;
            }
        }
        if ($found) {
            wp_register_style('rtwit-css', plugins_url('rTwit.css', __FILE__));
            wp_enqueue_style('rtwit-css');
        }
    }

    return $posts; 
}

/**
 * Build and return the code needed to display the plugin
 *
 * Calls itself recursively with $useCache=false if the cache is on but
 * has expired.
 */
function rtwit_get_code($opts, $cache=true) {
    
    if ($opts['useCache'] == false || !$cache) {

        $url = 'http://twitter.com/statuses/user_timeline.json?screen_name=' . $opts['account'] . '&count=' . $opts['tweetCount'];

        if (!class_exists('WP_Http')) {
            include_once(ABSPATH . WPINC . '/class-http.php');
        }
        $req = new WP_Http;
        $result = $req->request($url);
        
        if (!isset($result['response']['code']) || $result['response']['code'] != 200) {
            $cacheData = '<!-- error retrieving from Twitter -->'; 
        }
        else {
            $json = json_decode($result['body'], true); 
            if (!$json || !is_array($json)) {
                $cacheData = '<!-- error with json -->';
            }
            else if (count($json) < 1) {
                $cacheData = '<!-- no records in twitter -->';
            }
            else {
                $cacheData = '';
                if ($opts['htmlBefore']) {
                    $cacheData .= $opts['htmlBefore'];
                }
                $cacheData .= '<div class="rtwit-wrapper">';

                foreach ($json as $tweet) {
                    $cacheData .= '<div class="rtwit-tweet">';
                    $cacheData .= '<div class="rtwit-tweet-text">';
                    $cacheData .= rtwit_format_tweet($tweet['text'], (bool)$opts['newWindow']);
                    $cacheData .= '</div>'; //.rtwit-tweet-text
                    $cacheData .= '<div class="rtwit-tweet-date">';
                    $cacheData .= date(get_option('date_format').' '.get_option('time_format'), strtotime($tweet['created_at']));

                    $cacheData .= '</div>';

                    $cacheData .= '</div>'; //.rtwit-tweet
                }
                $cacheData .= '</div>'; //.rtwit-wrapper
            }
        }

        if ($opts['htmlAfter']) {
            $cacheData .= $opts['htmlAfter'];
        }

        /* Save the data back into the cache.  We're doing this anyway because
         * this function is called recursively to rebuild the cache.
         */
        $acct = strtolower($opts['account']);
        $opts = get_option('rtwit-options');
        $opts['cache'][$acct] = array(
            'tweetCount' => count($json),
            'tweetsRequested' => $opts['tweetCount'],
            'data' => $cacheData,
            'cacheDate' => date('U')
        );
        update_option('rtwit_options', $opts); 
        return $cacheData;
    }
    else {
        
        // Try getting it from the cache if possible.
        if (isset($opts['cache'][$opts['account']]['cacheDate'])) {
            $diff = date('U') - $opts['cache'][$opts['account']]['cacheDate'];
            $diff /= 60;  
            if ($diff < $opts['cacheLength']) {
                return $opts['cache'][$opts['account']]['data'];
            }
            else {
                return rtwit_get_code($opts, false);
            }
        }
        else { 
            return rtwit_get_code($opts, false);
        }
 
    }
}

/**
 * Format a tweet by adding in anchor tags as needed
 * @param <type> $tweet
 * @return <type> 
 */
function rtwit_format_tweet($tweet, $newWindow = true) {
    if ($newWindow) {
        $newWindow = ' target="_blank"';
    }
    else {
        $newWindow = '';
    }
    $tweet = preg_replace("/(http:\/\/|(www\.))(([^\s<]{4,68})[^\s<]*)/", "<a".$newWindow." href=\"http://$2$3\">$1$2$4</a>", $tweet);

    $tweet = preg_replace("/@(\w+)/", "<a".$newWindow." href=\"http://twitter.com/\\1\">@\\1</a>", $tweet);

    $tweet = preg_replace("/#(\w+)/", "<a".$newWindow." href=\"http://search.twitter.com/search?q=\\1\">#\\1</a>", $tweet);

    return $tweet;
}

/** Return an array of the default settings */
function rtwit_defaults() {
    return array(
        'useCache' => true,
        'cacheLength' => 30,
        'tweetCount' => 10,
        'newWindow' => true,
        'defaultAccount' => '',
        'htmlBefore' => '',
        'htmlAfter' => '',
        'loadCSS' => true,
        'cache' => array()
    );
}

/**
 * Function to display the options page admin-side
 */
function rtwit_options() { 
    $defaults = rtwit_defaults();
    $opts = get_option('rtwit_options');
    $opts = array_merge($defaults, $opts); 

    ?>
<style type="text/css">
.rtwit-tweet {    margin-bottom: 15px;}
.rtwit-tweet-date { display:none;}
</style>

<div class="wrap">
    <div style="float:left;">
    <h2><a href="http://www.nutt.net/tag/rtwit/" target="_blank">rTwit</a> Options</h2>
    <form method="post" action="options.php">
        <?php wp_nonce_field('update-options'); ?>
        <table class="form-table">
            <tr>
                <th scope="row">Default Twitter Account</th>
                <td><input type="text" name="rtwit_options[defaultAccount]" value="<?php echo $opts['defaultAccount']; ?>" /></td>
            </tr>
            <tr>
                <th scope="row">Default Tweet Count</th>
                <td><input type="text" name="rtwit_options[tweetCount]" value="<?php echo $opts['tweetCount']; ?>" /></td>
            </tr>
            <tr>
                <th scope="row">Use Cache</th>
                <td><input type="checkbox" name="rtwit_options[useCache]" value="1" <?php checked($opts['useCache']); ?> /></td>
            </tr>
            <tr>
                <th scope="row">Cache Length</th>
                <td><input type="text" name="rtwit_options[cacheLength]" value="<?php echo $opts['cacheLength']; ?>"> minutes</td>
            </tr>
            <tr>
                <th scope="row">Open in New Window</th>
                <td><input type="checkbox" name="rtwit_options[newWindow]" value="1" <?php checked($opts['newWindow']);?> /></td>
            </tr>
            <tr>
                <th scope="row">Load CSS</th>
                <td><input type="checkbox" name="rtwit_options[loadCSS]" value="1" <?php checked($opts['loadCSS']); ?> />
            </tr>
            <tr>
                <th scope="row">HTML Before</th>
                <td><textarea name="rtwit_options[htmlBefore]"><?php echo $opts['htmlBefore']; ?></textarea></td>
            </tr>
            <tr>
                <th scope="row">HTML After</th>
                <td><textarea name="rtwit_options[htmlAfter]"><?php echo $opts['htmlAfter']; ?></textarea></td>
            </tr>
            <tr>
                <th scope="row">Clear Cache</th>
                <td><input type="checkbox" name="rtwit-clear-cache" value="1" /></td>
            </tr>
        </table>
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="page_options" value="rtwit_options" />

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" />
        </p>

    </form>
    </div>

    <br style="clear:both;" />
</div>

    <?php
}
?>