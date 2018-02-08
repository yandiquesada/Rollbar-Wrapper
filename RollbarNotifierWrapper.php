<?php
namespace Utils;

/**
 * Singleton-style wrapper around Rollbar and RollbarNotifier
 *
 * Motivation to write this code was the necesity to stop posting the Auth header to Rollbar.
 * You can use headers_to_remove array to set up all the headers you don't need to send to rollbar.
 */


class RollbarWrapper extends \Rollbar
{
    public static function init($config = array(), $headers_to_remove = array(), $set_exception_handler = true, $set_error_handler = true, $report_fatal_errors = true) {
        // Heroku support
        // Use env vars for configuration, if set
        if (isset($_ENV['ROLLBAR_ACCESS_TOKEN']) && !isset($config['access_token'])) {
            $config['access_token'] = $_ENV['ROLLBAR_ACCESS_TOKEN'];
        }
        if (isset($_ENV['ROLLBAR_ENDPOINT']) && !isset($config['endpoint'])) {
            $config['endpoint'] = $_ENV['ROLLBAR_ENDPOINT'];
        }
        if (isset($_ENV['HEROKU_APP_DIR']) && !isset($config['root'])) {
            $config['root'] = $_ENV['HEROKU_APP_DIR'];
        }
        
        //Here we are creating our custom RollbarNotifier instance.
        self::$instance = new RollbarNotifierWrapper($config);
        self::$instance->set_headers_to_remove($headers_to_remove);
    
        if ($set_exception_handler) {
            set_exception_handler('Rollbar::report_exception');
        }
        if ($set_error_handler) {
            set_error_handler('Rollbar::report_php_error');
        }
        if ($report_fatal_errors) {
            register_shutdown_function('Rollbar::report_fatal_error');
        }
    
        if (self::$instance->batched) {
            register_shutdown_function('Rollbar::flush');
        }
    }
}

class RollbarNotifierWrapper extends \RollbarNotifier
{            
    protected $headers_to_remove = array();
    
    public function set_headers_to_remove($headers_to_remove){
        $this->headers_to_remove = $headers_to_remove;
    }
    
    protected function headers() {
        $headers = array();
        foreach ($this->scrub_request_params($_SERVER) as $key => $val) {
            if (substr($key, 0, 5) == 'HTTP_') {
                // convert HTTP_CONTENT_TYPE to Content-Type, HTTP_HOST to Host, etc.
                $name = strtolower(substr($key, 5));
                if (strpos($name, '_') != -1) {
                    $name = preg_replace('/ /', '-', ucwords(preg_replace('/_/', ' ', $name)));
                } else {
                    $name = ucfirst($name);
                }

                if( array_search($name, $this->headers_to_remove) === false){
                    $headers[$name] = $val;                    
                } else {
                    $headers[$name] = '*******************************';
                    continue;
                }                
            }
        }
    
        if (count($headers) > 0) {
            return $headers;
        } else {
            // serializes to emtpy json object
            return new \stdClass();
        }
    }    
}

?>
