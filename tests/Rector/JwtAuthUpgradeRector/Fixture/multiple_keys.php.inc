<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use Tuupola\Middleware\JwtAuthentication;

$auth = new JwtAuthentication([
  'secret' => [
    'a' => 'secret1',
    'b' => 'secret2',
  ],
  'algorithm' => ['a' => 'HS256', 'b' => 'HS256'],
  'logger' => new \stdClass,
  'rules' => [],
]);

?>
-----
<?php

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector\Fixture;

use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Secret;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
$auth = new JwtAuthentication(new Options(), new FirebaseDecoder(new Secret('secret1', 'HS256', 'a'), new Secret('secret2', 'HS256', 'b')));

?>
