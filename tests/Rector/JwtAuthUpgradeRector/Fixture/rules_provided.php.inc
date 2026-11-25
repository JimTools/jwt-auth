<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use Tuupola\Middleware\JwtAuthentication;
use Tuupola\Middleware\JwtAuthentication\RequestMethodRule;
use Tuupola\Middleware\JwtAuthentication\RequestPathRule;

$auth = new JwtAuthentication([
  'secret' => 'secret',
  'path' => ['/api'],
  'ignore' => ['/api/auth'],
  'rules' => [
    new RequestMethodRule(['HEAD']),
    new RequestPathRule(['/api/v1'], ['/api/v1/auth'])
  ]
]);

?>
-----
<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Secret;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
use JimTools\JwtAuth\Rules\RequestMethodRule;
use JimTools\JwtAuth\Rules\RequestPathRule;
$auth = new JwtAuthentication(new Options(), new FirebaseDecoder(new Secret('secret', 'HS256')), [
  new RequestMethodRule(['HEAD']),
  new RequestPathRule(['/api/v1'], ['/api/v1/auth'])
]);

?>
