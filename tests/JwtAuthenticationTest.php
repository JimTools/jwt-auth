<?php

declare(strict_types=1);

namespace JimTools\JwtAuth\Test;

use Closure;
use DateTimeImmutable;
use Equip\Dispatch\MiddlewareCollection;
use Firebase\JWT\JWT;
use JimTools\JwtAuth\Decoder\DecoderInterface;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Exceptions\AuthorizationException;
use JimTools\JwtAuth\Handlers\AfterHandlerInterface;
use JimTools\JwtAuth\Handlers\BeforeHandlerInterface;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Rules\RequestMethodRule;
use JimTools\JwtAuth\Rules\RequestPathRule;
use JimTools\JwtAuth\Secret;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * @internal
 *
 * @phpstan-type TokenClaims array{iss: string, aud: string, sub: string, scope: array<string>}
 */
#[CoversClass(JwtAuthentication::class)]
#[UsesClass(Secret::class), UsesClass(Options::class), UsesClass(FirebaseDecoder::class)]
#[UsesClass(RequestMethodRule::class), UsesClass(RequestPathRule::class), UsesClass(AuthorizationException::class)]
final class JwtAuthenticationTest extends TestCase
{
    /** @codingStandardsIgnoreStart */
    public static string $acmeToken = '';
    public static string $betaToken = '';
    public static string $expired = '';

    /**
     * @var array<string, mixed>
     */
    public static array $acmeTokenArray = [
        'iss' => 'Acme Toothpicks Ltd',
        'aud' => 'www.example.com',
        'sub' => 'someone@example.com',
        'scope' => ['read', 'write', 'delete'],
    ];

    /**
     * @var array<string, mixed>
     */
    public static array $betaTokenArray = [
        'iss' => 'Beta Sponsorship Ltd',
        'aud' => 'www.example.com',
        'sub' => 'someone@example.com',
        'scope' => ['read'],
    ];

    private JwtAuthentication $middleware;

    private DecoderInterface $decoder;

    public static function setUpBeforeClass(): void
    {
        $data = [
            'iat' => (new DateTimeImmutable())->getTimestamp(),
            'exp' => (new DateTimeImmutable('+5 minutes'))->getTimestamp(),
        ];

        self::$acmeTokenArray = array_merge(self::$acmeTokenArray, $data);
        self::$betaTokenArray = array_merge(self::$betaTokenArray, $data);

        self::$acmeToken = JWT::encode(self::$acmeTokenArray, 'supersecretkeyyoushouldnotcommittogithub', 'HS256', 'acme');
        self::$betaToken = JWT::encode(self::$betaTokenArray, 'anothersecretkeyfornevertocommittogithub', 'HS256', 'beta');
        self::$expired = JWT::encode(['exp' => (new DateTimeImmutable('-5 minutes'))->getTimestamp()], 'supersecretkeyyoushouldnotcommittogithub', 'HS256', 'acme');
    }

    #[Override]
    protected function setUp(): void
    {
        $this->decoder = new FirebaseDecoder(
            new Secret('supersecretkeyyoushouldnotcommittogithub', 'HS256', 'acme'),
            new Secret('anothersecretkeyfornevertocommittogithub', 'HS256', 'beta'),
        );

        $this->middleware = new JwtAuthentication(new Options(), $this->decoder);
    }

    public function testShouldThrowAuthorizationExceptionWithoutToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');
        $collection = new MiddlewareCollection([$this->middleware]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Token not found.');
        $collection->dispatch($request, $this->defaultclosure());
    }

    public function testShouldReturn200WithTokenFromHeader(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('X-Token', 'Bearer ' . self::$acmeToken);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(header: 'X-Token'),
                $this->decoder,
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithTokenFromHeaderWithCustomRegexp(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('X-Token', self::$acmeToken);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(header: 'X-Token', regexp: '/(.*)/'),
                $this->decoder
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithTokenFromCookie(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withCookieParams(['nekot' => self::$acmeToken]);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(cookie: 'nekot'),
                $this->decoder
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithTokenFromBearerCookie(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withCookieParams(['nekot' => 'Bearer ' . self::$acmeToken]);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(cookie: 'nekot'),
                $this->decoder
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithSecretArray(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$betaToken);

        $collection = new MiddlewareCollection([$this->middleware]);

        $response = $collection->dispatch($request, $this->defaultclosure());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldThrowExceptionWithWrongSecrets(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$betaToken);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                new FirebaseDecoder(
                    new Secret('supersecretkeyyoushouldnotcommittogithub', 'HS256', 'xxxx'),
                    new Secret('anothersecretkeyfornevertocommittogithub', 'HS256', 'yyyy'),
                )
            ),
        ]);

        $this->expectException(AuthorizationException::class);
        $collection->dispatch($request, $this->defaultclosure());
    }

    public function testShouldThrowExceptionnWithInvalidAlgorithm(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                new FirebaseDecoder(
                    new Secret('supersecretkeyyoushouldnotcommittogithub', 'nosuch')
                ),
            ),
        ]);

        $this->expectException(AuthorizationException::class);
        $collection->dispatch($request, $this->defaultclosure());
    }

    public function testShouldReturn200WithOptions(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withMethod('OPTIONS');

        $collection = new MiddlewareCollection([$this->middleware]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldThrowExceptionWithInvalidToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer invalid' . self::$acmeToken);

        $collection = new MiddlewareCollection([$this->middleware]);

        $this->expectException(AuthorizationException::class);
        $collection->dispatch($request, $this->defaultclosure());
    }

    public function testShouldThrowExceptionWithExpiredToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$expired);

        $collection = new MiddlewareCollection([$this->middleware]);

        $this->expectException(AuthorizationException::class);
        $collection->dispatch($request, $this->defaultclosure());
    }

    public function testShouldReturn200WithoutTokenWithPath(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/public');

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                $this->decoder,
                [new RequestMethodRule(), new RequestPathRule(['/api', '/foo'])],
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithoutTokenWithIgnore(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/ping');

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                $this->decoder,
                [
                    new RequestMethodRule(),
                    new RequestPathRule(['/api', '/foo'], ['/api/ping']),
                ]
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldNotAllowInsecure(): void
    {
        $this->expectException(RuntimeException::class);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $collection = new MiddlewareCollection([$this->middleware]);

        $response = $collection->dispatch($request, $this->defaultclosure());
    }

    public function testShouldAllowInsecure(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(isSecure: false),
                $this->decoder
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldRelaxInsecureInLocalhost(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://localhost/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $collection = new MiddlewareCollection([$this->middleware]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldRelaxInsecureInExampleCom(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(relaxed: ['example.com']),
                $this->decoder,
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldAttachToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            /** @var TokenClaims $acmeToken */
            $acmeToken = $request->getAttribute('token');

            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write($acmeToken['iss']);

            return $response;
        };

        $collection = new MiddlewareCollection([$this->middleware]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Acme Toothpicks Ltd', (string) $response->getBody());
    }

    public function testShouldAttachCustomToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            /** @var TokenClaims $acmeToken */
            $acmeToken = $request->getAttribute('nekot');

            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write($acmeToken['iss']);

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(attribute: 'nekot'),
                $this->decoder
            ),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Acme Toothpicks Ltd', (string) $response->getBody());
    }

    public function testShouldCallAfterWithProperArguments(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(after: $this->afterHandler()),
                $this->decoder,
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        /** @var array{decoded: array<string, mixed>, token: string} */
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('plants crave', $response->getHeaderLine('X-Brawndo'));
        self::assertSame(self::$acmeTokenArray, $data['decoded']);
        self::assertSame(self::$acmeToken, $data['token']);
    }

    public function testShouldCallBeforeWithProperArguments(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write(json_encode($request->getParsedBody(), JSON_THROW_ON_ERROR));

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(before: $this->beforeHandler()),
                $this->decoder
            ),
        ]);

        $response = $collection->dispatch($request, $default);

        /** @var array{decoded: array<string, mixed>, token: string} */
        $data = json_decode((string) $response->getBody(), true);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::$acmeTokenArray, (array) $data['decoded']);
        self::assertSame(self::$acmeToken, $data['token']);
    }

    public function testShouldAllowUnauthenticatedHttp(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/public/foo');

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                $this->decoder,
                [new RequestMethodRule(), new RequestPathRule(['/api', '/bar'])]
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldHandleRulesArrayBug84(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                $this->decoder,
                [
                    new RequestPathRule(['/api'], ['/api/login']),
                    new RequestMethodRule(),
                ],
            ),
        ]);

        $this->expectException(AuthorizationException::class);
        $collection->dispatch($request, $this->defaultclosure());

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/login');

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldHandleRulesArrayBug84HappyPath(): void
    {
        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                $this->decoder,
                [
                    new RequestPathRule(['/api'], ['/api/login']),
                    new RequestMethodRule(),
                ],
            ),
        ]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/login');

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldHandleDefaultPathBug118(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                $this->decoder,
                [new RequestPathRule(['/'], ['/api/login'])],
            ),
        ]);

        $this->expectException(AuthorizationException::class);
        $collection->dispatch($request, $this->defaultclosure());
    }

    public function testShouldHandleDefaultPathBug118HappyPath(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(),
                $this->decoder,
                [new RequestPathRule(['/'], ['/api/login'])],
            ),
        ]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/login');

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldUseCookieIfHeaderMissingIssue156(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withCookieParams(['token' => self::$acmeToken]);

        $collection = new MiddlewareCollection([
            new JwtAuthentication(
                new Options(header: 'X-Token', regexp: '/(.*)/'),
                $this->decoder,
            ),
        ]);

        $response = $collection->dispatch($request, $this->defaultclosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testAddRule(): void
    {
        $class = $this->middleware->addRule(new RequestPathRule([], ['/foobar']));
        $collection = new MiddlewareCollection([$class]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/foo');

        $response = $collection->dispatch($request, $this->defaultClosure());

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testWithRule(): void
    {
        $collection = new MiddlewareCollection([
            $this->middleware->withRules(new RequestPathRule(['/admin'], ['/admin/ping'])),
        ]);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com');

        $response = $collection->dispatch($request, $this->defaultClosure());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/admin')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);
        $response = $collection->dispatch($request, $this->defaultClosure());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/admin/ping');
        $response = $collection->dispatch($request, $this->defaultClosure());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    private function beforeHandler(): BeforeHandlerInterface
    {
        return new class implements BeforeHandlerInterface {
            public function __invoke(
                ServerRequestInterface $request,
                array $arguments
            ): ServerRequestInterface {
                return $request->withParsedBody($arguments)
                    ->withAttribute('test', 'invoke');
            }
        };
    }

    private function afterHandler(): AfterHandlerInterface
    {
        return new class implements AfterHandlerInterface {
            public function __invoke(ResponseInterface $response, array $arguments): ResponseInterface
            {
                $body = $response->getBody();
                $body->rewind();
                $body->write(json_encode($arguments, JSON_THROW_ON_ERROR));

                return $response->withHeader('X-Brawndo', 'plants crave');
            }
        };
    }

    private function defaultClosure(): Closure
    {
        return static function (ServerRequestInterface $request): ResponseInterface {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };
    }
}
