<?php

    define('DOMAIN_NAME', $_SERVER['SERVER_NAME']);
    $protocol = (!empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on')) ? 'https' : 'http';
    define('WEBSITE_URL', $protocol . '://' . $_SERVER['HTTP_HOST'] . '/');
    define('SUB_VERSION_STR', '.9999099');
    define('KEYS_FILE', '/var/www/ytPrivate/keys.txt');

    define('MUSIC_VERSION', '2' . SUB_VERSION_STR);
    define('CLIENT_VERSION', '1' . SUB_VERSION_STR);
    define('UI_KEY', 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8'); // this isn't a YouTube Data API v3 key
    define('USER_AGENT', 'Firefox/100');

    function isRedirection($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code == 302) {
            detectedAsSendingUnusualTraffic();
        }
        return $code == 303;
    }

    function getRemote($url, $opts = [])
    {
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            $opts = [
                'http' => [
                    'follow_location' => false
                ]
            ];
            $context = stream_context_create($opts);
            $result = file_get_contents($url, false, $context);
            if (str_contains($result, 'https://www.google.com/sorry/index?continue=')) {
                detectedAsSendingUnusualTraffic();
            }
        }
        return $result;
    }

    function detectedAsSendingUnusualTraffic()
    {
        $error = [
            'code' => 400,
            'message' => 'YouTube has detected unusual traffic from this YouTube operational API instance. Please try your request again later or see alternatives at https://github.com/Benjamin-Loison/YouTube-operational-API/issues/11',
        ];
        $result = [
            'error' => $error
        ];
        die(json_encode($result, JSON_PRETTY_PRINT));
    }

    function getJSON($url, $opts = [])
    {
        return json_decode(getRemote($url, $opts), true);
    }

    function getJSONFromHTMLScriptPrefix($html, $scriptPrefix)
    {
        $html = explode(';</script>', explode('">' . $scriptPrefix, $html, 3)[1], 2)[0];
        return json_decode($html, true);
    }

    function getJSONStringFromHTML($html, $scriptVariable = '', $prefix = 'var ')
    {
        // don't use as default variable because getJSONFromHTML call this function with empty string
        if ($scriptVariable === '') {
            $scriptVariable = 'ytInitialData';
        }
        return explode(';</script>', explode('">' . $prefix . $scriptVariable . ' = ', $html, 3)[1], 2)[0];
    }

    function getJSONFromHTML($url, $opts = [], $scriptVariable = '', $prefix = 'var ')
    {
        $res = getRemote($url, $opts);
        $res = getJSONStringFromHTML($res, $scriptVariable, $prefix);
        return json_decode($res, true);
    }

    function checkRegex($regex, $str)
    {
        return preg_match('/^' . $regex . '$/', $str) === 1;
    }

    function isContinuationToken($continuationToken)
    {
        return checkRegex('[A-Za-z0-9=]+', $continuationToken);
    }

    function isContinuationTokenAndVisitorData($continuationTokenAndVisitorData)
    {
        return checkRegex('[A-Za-z0-9=]+,[A-Za-z0-9=\-_]+', $continuationTokenAndVisitorData);
    }

    function isPlaylistId($playlistId)
    {
        return checkRegex('[a-zA-Z0-9-_]+', $playlistId);
    }

    function isUsername($forUsername)
    {
        return checkRegex('[a-zA-Z0-9]+', $forUsername);
    }

    function isChannelId($channelId)
    {
        return checkRegex('[a-zA-Z0-9-_]{24}', $channelId);
    }

    function isVideoId($videoId)
    {
        return checkRegex('[a-zA-Z0-9-_]{11}', $videoId);
    }

    function isHashTag($hashTag)
    {
        return true; // checkRegex('[a-zA-Z0-9_]+', $hashTag); // 'é' is a valid hashtag for instance
    }

    function isSAPISIDHASH($SAPISIDHASH)
    {
        return checkRegex('[1-9][0-9]{9}_[a-f0-9]{40}', $SAPISIDHASH);
    }

    function isQuery($q)
    {
        return true; // should restrain
    }

    function isClipId($clipId)
    {
        return checkRegex('[a-zA-Z0-9-_]{36}', $clipId); // may be more precise
    }

    function isEventType($eventType)
    {
        return in_array($eventType, ['completed', 'live', 'upcoming']);
    }

    function isPositiveInteger($s)
    {
        return preg_match("/^\d+$/", $s);
    }

    function isYouTubeDataAPIV3Key($youtubeDataAPIV3Key)
    {
        return checkRegex('AIzaSy[A-D][a-zA-Z0-9-_]{32}', $youtubeDataAPIV3Key);
    }

    function isHandle($handle)
    {
        return checkRegex('[a-zA-Z0-9-_.]{3,}', $handle);
    }

    function doesPathExist($json, $path)
    {
        $parts = explode('/', $path);
        $partsCount = count($parts);
        if ($partsCount == 1) {
            return array_key_exists($path, $json);
        }
        return array_key_exists($parts[0], $json) && doesPathExist($json[$parts[0]], join('/', array_slice($parts, 1, $partsCount - 1)));
    }

    // assume path checked before
    function getValue($json, $path)
    {
        $parts = explode('/', $path);
        $partsCount = count($parts);
        if ($partsCount == 1) {
            return $json[$path];
        }
        return getValue($json[$parts[0]], join('/', array_slice($parts, 1, $partsCount - 1)));
    }

    function getIntValue($unitCount, $unit)
    {
        $unitCount = str_replace(' ' . $unit . 's', '', $unitCount);
        $unitCount = str_replace(' ' . $unit, '', $unitCount);
        $unitCount = str_replace('K', '*1000', $unitCount);
        $unitCount = str_replace('M', '*1000000', $unitCount);
        if(checkRegex('[0-9.*KM]+', $unitCount)) {
            $unitCount = eval('return ' . $unitCount . ';');
        }
        return $unitCount;
    }

    if (!function_exists('str_contains')) {
        function str_contains($haystack, $needle)
        {
            return strpos($haystack, $needle) !== false;
        }
    }

    if (!function_exists('str_starts_with')) {
        function str_starts_with($haystack, $needle)
        {
            return strpos($haystack, $needle) === 0;
        }
    }

    if (!function_exists('str_ends_with')) {
        function str_ends_with($haystack, $needle)
        {
            $length = strlen($needle);
            return $length > 0 ? substr($haystack, -$length) === $needle : true;
        }
    }
