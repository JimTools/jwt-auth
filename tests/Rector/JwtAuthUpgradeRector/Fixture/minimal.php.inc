<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use Tuupola\Middleware\JwtAuthentication as JWTAuth;

$auth = new JWTAuth([
  'secret' => getenv('JWT_SECRET'),
  'secure' => true,
  'relaxed' => ['localhost', '127.0.0.1'],
  'algorithm' => ['HS256'],
  'header' => 'Authorization',
  'regexp' => '/Bearer\s+(.*)$/i',
  'cookie' => 'token',
  'attribute' => 'token',
  'logger' => null,
  'before' => null,
  'after' => null,
  'error' => null,
  'garbage' => 'value',
]);

?>
-----
<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Secret;
use JimTools\JwtAuth\Middleware\JwtAuthentication as JWTAuth;
$auth = new JWTAuth(new Options(), new FirebaseDecoder(new Secret(getenv('JWT_SECRET'), 'HS256')));

?>
