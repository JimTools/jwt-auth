<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use Tuupola\Middleware\JwtAuthentication;

$auth = new JwtAuthentication([
  'secret' => $_ENV['JWT_SECRET'],
  'path' => ['/api'],
  'ignore' => ['/api/auth'],
]);

?>
-----
<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use JimTools\JwtAuth\Middleware\JwtAuthentication;

$auth = new JwtAuthentication(new JimTools\JwtAuth\Options(), new JimTools\JwtAuth\Decoder\FirebaseDecoder(new JimTools\JwtAuth\Secret($_ENV['JWT_SECRET'], 'HS256')), [new JimTools\JwtAuth\Rules\RequestPathRule(path: ['/', '/api'], ignore: ['/api/auth']), new JimTools\JwtAuth\Rules\RequestMethodRule()]);

?>
