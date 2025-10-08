# Updgrading from 1.x to 2.x

v2 is a massive overall of the package and with this comes breaking changes, the list below outlines some of the most
notable changes but it's not extensive.

- Minimum PHP 8.1+
- Namespace changed
- Dropped support for PSR-7 (double pass)
- Options moved into class
- Secrets moved into class
- Logger removed
- removed error handler
- Before and After handlers changed

The recommended upgrade path is to use [Rector](https://github.com/rectorphp/rector) this will automate the bulk of the
changes however there are something's which cannot be be automated and will need manual intervention, see
[limitations](#limitations) for more details

Error handling is now upto the developer to impliment, the middleware will throw exceptions which need to
be handled at the global level. for more infomation about which exceptions can be thrown see
[DecoderInterface](./src/Decoder/DecoderInterface.php) all exception extend
`JimTools\JwtAuth\Exceptions\AuthorizationException`.

## Rector (Recommended)

v2 is shipped with customer rector rule which will, can be used to automate the bulk of the work, you will need to
require rector into your dev dependencies.

### Limitations

There are some limitation with this rule around the `before` and `after` handlers, new files cannot be created so any
closure will be converted into a anonymous class, the output doesn't create valid php code. you will manually have to
move each anonymous class into a new file and conver this into a standard class i.e. `BeforeHandler`

If the options are passed to the middleware via a variable instead of an inline array the rector rule will throw an
exception. currently only inline arrays are supports.

### Steps


```shell
composer require rector/rector --dev
```

create the config file `rector.php` in the root of the project and add the upgrade rule.

```php
use Rector\Config\RectorConfig;
use JimTools\JwtAuth\Rector\JwtAuthUpgradeRector;

return RectorConfig::configure()
    ->withPaths([
      __DIR__ . '/src',
      // other paths
    ])
    ->withRules([
        JwtAuthUpgradeRector::class,
    ]);
```

Run rector in dry run mode, this will show the changes that will be made before applying them, once you are ready to
have the changes applied remove the `--dry-run` flag.

```shell
vendor/bin/rector process --dry-run
```

## Manual

### Namespace

namespace has changed to reflect new ownership.

```diff
- Tuupola\Middleware\JwtAuthentication
+ JimTools\JwtAuth\Middleware\JwtAuthentication
```

### Middleware Options

Options have changed from an array to class for better type safety, for a full details please see
[Options](./src/Options.php)

```diff
- new JwtAuthentication(['secret' => 'tooManySecrets'])
+ new JwtAuthentication(new JimTools\JwtAuth\Options('tooManySecrets'))
```

### Before Handler

The `before` handler must be a class which extends the interface `JimTools\JwtAuth\Handlers\BeforeHandlerInterface`.

```diff
- new JwtAuthentication([
-   'secret' => 'tooManySecrets',
-   'before' => function($request, $arguments) {
-       return $request->withAttribute("test", "test");
-   },
- ]);
+ new JwtAuthentication(
+   new Options([
+     'before' => new MyBeforeHandler(),
+   ]))
```

Example implimentation

```php
use JimTools\JwtAuth\Handlers\BeforeHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;

class MyBeforeHandler implements BeforeHandlerInterface
{
    public function __invoke(ServerRequestInterface $request, array $arguments): ServerRequestInterface
    {
        return $request->withAttribute("test", "test");
    }
}
```


### After Handler

The `after` handler must be a class which extends the interface `JimTools\JwtAuth\Handlers\AfterHandlerInterface`.

```diff
- new JwtAuthentication([
-   'secret' => 'tooManySecrets',
-   'after' => function($response, $arguments) {
-       return $response->withHeader("X-Brawndo", "plants crave");
-   },
- ]);
+ new JwtAuthentication(
+   new Options([
+     'after' => new MyAfterHandler(),
+   ]))
```

Example implementation

```php
use JimTools\JwtAuth\Handlers\AfterHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class MyAfterHandler implements AfterHandlerInterface
{
    public function __invoke(ResponseInterface $response, array $arguments): ResponseInterface
    {
        return $response->withHeader("X-Brawndo", "plants crave");
    }
}
```

### Rules Option

The rules options have been moved into its own argument on `JwtAuthentication`

### Secret Option

The secrets has been moved from the options to the [Decoder](./src/Decoder/DecoderInterface.php)

### Middleware

The `JwtAuthentication` middleware now exceptions three parameters, [Options](./src/Options.php),
[Decoder](./src/Decoder/FirebaseDecoder.php) and an array of [Rules](./src/Rules/RuleInterface.php)

```diff
+ new JwtAuthentication(
+   new Options(...),
+   new FirebaseDecoder(...),
+   [new RequestMethodRule(), new RequestPathRule()],
+);
```

### Decoder

The decoding of the JWT has been move to a dependency of `JwtAuthentication`, the decoder needs to have the
secrets passed to it.


```diff
-new JwtAuthentication([
-   'secrets' => 'tooManySecrets',
-]);
+new JwtAuthentication(
+   new Options(),
+   new FirebaseDecoder(new Secret('tooManySecrets', 'HS256'))
+);
```

If you are decoing multiple tokens you need to supply mutliple secrets with the `kid` populated.

```diff
-new JwtAuthentication([
-   'secrets' => ['alpha' => 'keepItSecret', 'beta' => 'keepItSafe'],
-   'algorithm' => ['alpha' => 'HS256', 'beta' => 'HS256'],
-]);
+new JwtAuthentication(
+   new Options(),
+   new FirebaseDecoder(
+       new Secret('tooManySecrets', 'HS256','alpha'),
+       new Secret('keepItSafe', 'HS256', 'beta'),
+   )
+);
```

## Found Bug

If you've come across a bug or have a question please give feedback by create an issues or a discussions.
