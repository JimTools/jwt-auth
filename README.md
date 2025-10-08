# PSR-15 JWT Authentication Middleware

[![Latest Version](https://img.shields.io/packagist/v/jimtools/jwt-auth.svg?style=flat-square)](https://packagist.org/packages/jimtools/jwt-auth)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/jimtools/jwt-auth/tests.yml?branch=main&style=flat-square)](https://github.com/jimtools/jwt-auth/actions)
[![Coverage](https://img.shields.io/codecov/c/gh/jimtools/jwt-auth/main.svg?style=flat-square)](https://codecov.io/github/jimtools/jwt-auth/branch/main)

This package is a PSR-15 compliant JSON Web Token authentication middleware,
which take a JWT from the headers or cookies.

```php
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
use JimTools\JwtAuth\Exceptions\AuthorizationException;
use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Secret;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require './vendor/autoload.php';

$app = AppFactory::create();
$app->addErrorMiddleware(false, false, false)
    ->setErrorHandler(AuthorizationException::class, new MyErrorHandler());

$middleware = new JwtAuthentication(
    new Options(),
    new FirebaseDecoder(new Secret('tooManySecrets', 'HS256'))
);

$app->get('/protected', static function (Request $request, Response $response, array $args) {
    $response->getBody()->write('you will need a token');
    return $response;
})->addMiddleware($middleware);

$app->run();
```

> [!NOTE]
> V1 is still supported!! For documentation on v1.x which is compatible with `tuupola/slim-jwt-auth`
> see [1.x](https://github.com/JimTools/jwt-auth/blob/1.x/README.md)

## Install

The recommended way to install packages is through
[composer](https://getcomposer.org/).

``` shell
composer require jimtools/jwt-auth
```

## Documentation

GitHub issues are used for only to discuss bugs and new features, for support
please use GitHub discussions.

- [Documentation](https://jimtools.github.io/jwt-auth/)
- [Support](https://github.com/JimTools/jwt-auth/discussions)
- [Bugs](https://github.com/JimTools/jwt-auth/issues)

## Security

If you discover any security-related issues, please email
<james.read.18@gmail.com> instead of using the issue tracker.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
