<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use Tuupola\Middleware\JwtAuthentication;

$auth = new JwtAuthentication([
  'secret' => getenv('JWT_SECRET'),
  'before' => static function($req, $args) {
    return $req->withHeader('Foo', 'Bar');
  },
  'after' => static function($res, $arguments) {
    return $res->withStatus(201);
  },
]);

?>
-----
<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Handlers\BeforeHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use JimTools\JwtAuth\Handlers\AfterHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Secret;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
$auth = new JwtAuthentication(new Options(before: new class implements BeforeHandlerInterface
{
    public function __invoke(ServerRequestInterface $req, array $args): ServerRequestInterface
    {
        return $req->withHeader('Foo', 'Bar');
    }
}, after: new class implements AfterHandlerInterface
{
    public function __invoke(ResponseInterface $res, array $arguments): ResponseInterface
    {
        return $res->withStatus(201);
    }
}), new FirebaseDecoder(new Secret(getenv('JWT_SECRET'), 'HS256')));

?>
