<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit81e9e620cb24d057f57b1519d96cf03d
{
    public static $files = array (
        '5255c38a0faeba867671b61dfda6d864' => __DIR__ . '/..' . '/paragonie/random_compat/lib/random.php',
    );

    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'RobThree\\Auth\\' => 14,
        ),
        'P' => 
        array (
            'PHPMailer\\PHPMailer\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'RobThree\\Auth\\' => 
        array (
            0 => __DIR__ . '/..' . '/robthree/twofactorauth/lib',
        ),
        'PHPMailer\\PHPMailer\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpmailer/phpmailer/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'PHPMailer\\PHPMailer\\DSNConfigurator' => __DIR__ . '/..' . '/phpmailer/phpmailer/src/DSNConfigurator.php',
        'PHPMailer\\PHPMailer\\Exception' => __DIR__ . '/..' . '/phpmailer/phpmailer/src/Exception.php',
        'PHPMailer\\PHPMailer\\OAuth' => __DIR__ . '/..' . '/phpmailer/phpmailer/src/OAuth.php',
        'PHPMailer\\PHPMailer\\OAuthTokenProvider' => __DIR__ . '/..' . '/phpmailer/phpmailer/src/OAuthTokenProvider.php',
        'PHPMailer\\PHPMailer\\PHPMailer' => __DIR__ . '/..' . '/phpmailer/phpmailer/src/PHPMailer.php',
        'PHPMailer\\PHPMailer\\POP3' => __DIR__ . '/..' . '/phpmailer/phpmailer/src/POP3.php',
        'PHPMailer\\PHPMailer\\SMTP' => __DIR__ . '/..' . '/phpmailer/phpmailer/src/SMTP.php',
        'RobThree\\Auth\\Providers\\Qr\\BaconQrCodeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/BaconQrCodeProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\BaseHTTPQRCodeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/BaseHTTPQRCodeProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\EndroidQrCodeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/EndroidQrCodeProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\EndroidQrCodeWithLogoProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/EndroidQrCodeWithLogoProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\GoogleChartsQrCodeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/GoogleChartsQrCodeProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\HandlesDataUri' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/HandlesDataUri.php',
        'RobThree\\Auth\\Providers\\Qr\\IQRCodeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/IQRCodeProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\ImageChartsQRCodeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/ImageChartsQRCodeProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\QRException' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/QRException.php',
        'RobThree\\Auth\\Providers\\Qr\\QRServerProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/QRServerProvider.php',
        'RobThree\\Auth\\Providers\\Qr\\QRicketProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Qr/QRicketProvider.php',
        'RobThree\\Auth\\Providers\\Rng\\CSRNGProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Rng/CSRNGProvider.php',
        'RobThree\\Auth\\Providers\\Rng\\HashRNGProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Rng/HashRNGProvider.php',
        'RobThree\\Auth\\Providers\\Rng\\IRNGProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Rng/IRNGProvider.php',
        'RobThree\\Auth\\Providers\\Rng\\MCryptRNGProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Rng/MCryptRNGProvider.php',
        'RobThree\\Auth\\Providers\\Rng\\OpenSSLRNGProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Rng/OpenSSLRNGProvider.php',
        'RobThree\\Auth\\Providers\\Rng\\RNGException' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Rng/RNGException.php',
        'RobThree\\Auth\\Providers\\Time\\HttpTimeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Time/HttpTimeProvider.php',
        'RobThree\\Auth\\Providers\\Time\\ITimeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Time/ITimeProvider.php',
        'RobThree\\Auth\\Providers\\Time\\LocalMachineTimeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Time/LocalMachineTimeProvider.php',
        'RobThree\\Auth\\Providers\\Time\\NTPTimeProvider' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Time/NTPTimeProvider.php',
        'RobThree\\Auth\\Providers\\Time\\TimeException' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/Providers/Time/TimeException.php',
        'RobThree\\Auth\\TwoFactorAuth' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/TwoFactorAuth.php',
        'RobThree\\Auth\\TwoFactorAuthException' => __DIR__ . '/..' . '/robthree/twofactorauth/lib/TwoFactorAuthException.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit81e9e620cb24d057f57b1519d96cf03d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit81e9e620cb24d057f57b1519d96cf03d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit81e9e620cb24d057f57b1519d96cf03d::$classMap;

        }, null, ClassLoader::class);
    }
}