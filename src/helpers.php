<?php

use Symfony\Component\VarDumper\VarDumper;

if (!function_exists('dump')) {
    /**
     * @author Nicolas Grekas <p@tchwork.com>
     */
    function dump($var, ...$moreVars)
    {
        VarDumper::dump($var);

        foreach ($moreVars as $v) {
            VarDumper::dump($v);
        }

        $debug_backtrace = debug_backtrace();
        VarDumper::dump('Route: ' . $debug_backtrace[0]['file'].':'.$debug_backtrace[0]['line']);

        if (1 < func_num_args()) {
            return func_get_args();
        }

        return $var;
    }
}

if (!function_exists('dd')) {
    /**
     * @param ...$vars
     */
    function dd(...$vars)
    {
        foreach ($vars as $v) {
            VarDumper::dump($v);
        }

        $debug_backtrace = debug_backtrace();
        VarDumper::dump('Route: ' . $debug_backtrace[0]['file'].':'.$debug_backtrace[0]['line']);

        exit(1);
    }
}

/**
 * Adds a route to redirect place into headers.
 *
 * @param  string|null  $to
 * @param  int  $status
 * @param  array  $headers
 * @param  bool|null  $secure
 * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
 */
function redirect($to = null, $status = 302, $headers = [], $secure = null)
{
    /** @var Illuminate\Routing\Redirector $redirect_response */
    $redirect_response = app('redirect');

    if (is_null($to)) {
        return $redirect_response;
    }

    $headers = ['Route' => trim(_getRealRoute(debug_backtrace()))] + $headers;

    return $redirect_response->to($to, $status, $headers, $secure);
}

/**
 * @param int $status
 * @param array $headers
 * @param false $fallback
 *
 * @return mixed
 */
function back($status = 302, $headers = [], $fallback = false)
{
    $headers = ['Route' => trim(_getRealRoute(debug_backtrace()))] + $headers;

    return app('redirect')->back($status, $headers, $fallback);
}

/**
 * @param $message
 * @param array $context
 */
function _dd($data, $has_die = true, array $context = [], $clean_log = true)
{
    $debug_backtrace = debug_backtrace();

    foreach ($debug_backtrace as $row) {
        if (strpos($row['file'] ?? '', '/vendor/') === false) {
            continue;
        }

        [$app_dir, ] = explode('/vendor/', $row['file'], );

        break;
    }

    $app_dir .= '/storage/logs/laravel.log';

    if ($clean_log && is_file($app_dir)) {
        unlink($app_dir);
    }

    if (
        $data instanceof Illuminate\Database\Eloquent\Model
        || $data instanceof Illuminate\Support\Collection
    ) {
        $data = $data->toArray();
    }

    Illuminate\Support\Facades\Log::debug('' .
        "\n" .
        "----------------\n" .
        print_r($data, true) . (!is_array($data) ? "\n" : '')  .
        "----------------\n" .
        "Route: " . $debug_backtrace[0]['file'] . ':' . $debug_backtrace[0]['line'] . "\n" .
        "\n",
        $context
    );

    $has_die && dd('exit');
}

/**
 * @param array $debug_backtrace // pass result of the function _getRealRoute(debug_backtrace())
 *
 * @return string
 */
function _getRealRoute(array $debug_backtrace, $return_full_trace = false): string
{
    $route = '';

    foreach ($debug_backtrace as $trace) {
        if (
            strpos($trace['file'] ?? '', 'vendor') !== false
            || strpos($trace['file'] ?? '', 'app/Exceptions/Handler.php') !== false
            || strpos($trace['file'] ?? '', '/storage/framework/views/') !== false
        ) {
//            continue;
        }

        foreach ($trace['args'] as $index => &$arg) {
            if (is_array($arg)) {
                $arg = array_keys($arg);

                continue;
            }

            if (!is_object($arg)) {
                continue;
            }

            $arg = get_class($arg);
        }

        $route .= "\n" . ($trace['file'] ?? '') . ':' . ($trace['line'] ?? '') . "                          " . ($trace['class'] ?? '') . ($trace['type'] ?? '') . $trace['function'] . '(' . str_replace('\/', '/', str_replace('\\\\', '\\', trim(json_encode($trace['args']), '[]'))) . ')';

        if (!$return_full_trace) {
            break;
        }
    }

    return $route;
}

/**
 * Build a full trace from a called place
 *
 * @param array $data
 * @param bool $has_die
 */
function _trace($data = [], bool $has_die = true)
{
    print_r(nl2br((new Exception())->getTraceAsString()). "\n");

    $has_die ? dd($data) : dump($data);
}
