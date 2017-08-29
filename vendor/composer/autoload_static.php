<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita560468c4508347ab845111855ac18ce
{
    public static $files = array (
        'c964ee0ededf28c96ebd9db5099ef910' => __DIR__ . '/..' . '/guzzlehttp/promises/src/functions_include.php',
        'a0edc8309cc5e1d60e3047b5df6b7052' => __DIR__ . '/..' . '/guzzlehttp/psr7/src/functions_include.php',
        '37a3dc5111fe8f707ab4c132ef1dbc62' => __DIR__ . '/..' . '/guzzlehttp/guzzle/src/functions_include.php',
    );

    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\Http\\Message\\' => 17,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Psr7\\' => 16,
            'GuzzleHttp\\Promise\\' => 19,
            'GuzzleHttp\\' => 11,
        ),
        'C' => 
        array (
            'Composer\\Installers\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\Http\\Message\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/http-message/src',
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
        'Composer\\Installers\\' => 
        array (
            0 => __DIR__ . '/..' . '/composer/installers/src/Composer/Installers',
        ),
    );

    public static $classMap = array (
        'ColdTurkey\\SellerQuiz\\FrontDesk' => __DIR__ . '/../..' . '/classes/class-frontdesk.php',
        'ColdTurkey\\SellerQuiz\\SellerQuiz' => __DIR__ . '/../..' . '/classes/class-seller-quiz.php',
        'ColdTurkey\\SellerQuiz\\SellerQuiz_Admin' => __DIR__ . '/../..' . '/classes/class-seller-quiz-admin.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita560468c4508347ab845111855ac18ce::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita560468c4508347ab845111855ac18ce::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInita560468c4508347ab845111855ac18ce::$classMap;

        }, null, ClassLoader::class);
    }
}
