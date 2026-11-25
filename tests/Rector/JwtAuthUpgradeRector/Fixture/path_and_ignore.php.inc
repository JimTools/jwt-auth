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

use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Secret;
use JimTools\JwtAuth\Rules\RequestPathRule;
use JimTools\JwtAuth\Rules\RequestMethodRule;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
$auth = new JwtAuthentication(new Options(), new FirebaseDecoder(new Secret($_ENV['JWT_SECRET'], 'HS256')), [new RequestPathRule(path: ['/', '/api'], ignore: ['/api/auth']), new RequestMethodRule()]);

?>
