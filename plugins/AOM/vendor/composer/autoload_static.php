<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit5e406f6a9ae827e5412c990fd060b3d8
{
    public static $files = array (
        'a0edc8309cc5e1d60e3047b5df6b7052' => __DIR__ . '/..' . '/guzzlehttp/psr7/src/functions_include.php',
        'c964ee0ededf28c96ebd9db5099ef910' => __DIR__ . '/..' . '/guzzlehttp/promises/src/functions_include.php',
        '37a3dc5111fe8f707ab4c132ef1dbc62' => __DIR__ . '/..' . '/guzzlehttp/guzzle/src/functions_include.php',
    );

    public static $prefixLengthsPsr4 = array (
        'p' => 
        array (
            'phpDocumentor\\Reflection\\' => 25,
        ),
        'W' => 
        array (
            'Webmozart\\Assert\\' => 17,
        ),
        'S' => 
        array (
            'Symfony\\Component\\Serializer\\' => 29,
        ),
        'P' => 
        array (
            'Psr\\Log\\' => 8,
            'Psr\\Http\\Message\\' => 17,
            'Psr\\Cache\\' => 10,
        ),
        'M' => 
        array (
            'Monolog\\' => 8,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Psr7\\' => 16,
            'GuzzleHttp\\Promise\\' => 19,
            'GuzzleHttp\\' => 11,
            'Google\\Auth\\' => 12,
            'Google\\AdsApi\\' => 14,
        ),
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
        'B' => 
        array (
            'Bramus\\Monolog\\' => 15,
            'Bramus\\Ansi\\' => 12,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'phpDocumentor\\Reflection\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpdocumentor/reflection-common/src',
            1 => __DIR__ . '/..' . '/phpdocumentor/reflection-docblock/src',
            2 => __DIR__ . '/..' . '/phpdocumentor/type-resolver/src',
        ),
        'Webmozart\\Assert\\' => 
        array (
            0 => __DIR__ . '/..' . '/webmozart/assert/src',
        ),
        'Symfony\\Component\\Serializer\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/serializer',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
        ),
        'Psr\\Cache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/cache/src',
        ),
        'Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/monolog/monolog/src/Monolog',
        ),
        'GuzzleHttp\\Psr7\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/psr7/src',
        ),
        'GuzzleHttp\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/promises/src',
        ),
        'GuzzleHttp\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/guzzle/src',
        ),
        'Google\\Auth\\' => 
        array (
            0 => __DIR__ . '/..' . '/google/auth/src',
        ),
        'Google\\AdsApi\\' => 
        array (
            0 => __DIR__ . '/..' . '/googleads/googleads-php-lib/src/Google/AdsApi',
        ),
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
        'Bramus\\Monolog\\' => 
        array (
            0 => __DIR__ . '/..' . '/bramus/monolog-colored-line-formatter/src',
        ),
        'Bramus\\Ansi\\' => 
        array (
            0 => __DIR__ . '/..' . '/bramus/ansi-php/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit5e406f6a9ae827e5412c990fd060b3d8::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit5e406f6a9ae827e5412c990fd060b3d8::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
