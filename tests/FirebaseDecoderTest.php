<?php

declare(strict_types=1);

namespace JimTools\JwtAuth\Test;

use DateTime;
use Firebase\JWT\JWT;
use JimTools\JwtAuth\Decoder\DecoderInterface;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Exceptions\BeforeValidException;
use JimTools\JwtAuth\Exceptions\DomainException;
use JimTools\JwtAuth\Exceptions\ExpiredException;
use JimTools\JwtAuth\Exceptions\InvalidArgumentException;
use JimTools\JwtAuth\Exceptions\SignatureInvalidException;
use JimTools\JwtAuth\Exceptions\UnexpectedValueException;
use JimTools\JwtAuth\Secret;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FirebaseDecoder::class), UsesClass(Secret::class)]
final class FirebaseDecoderTest extends TestCase
{
    private DecoderInterface $decoder;
    private ?string $secret = null;

    #[Override]
    protected function setUp(): void
    {
        $this->secret = base64_encode(random_bytes(128));
        $this->decoder = new FirebaseDecoder(new Secret($this->secret, 'HS256', 'acme'));
    }

    public function testInvalidArgumentExceptionIsThrown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new FirebaseDecoder())->decode('..');
    }

    #[DataProvider('provideUnexpectedValueExceptionIsThrownCases')]
    public function testUnexpectedValueExceptionIsThrown(string $token): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->decoder->decode($token);
    }

    /**
     * @return iterable<array<string>>
     */
    public static function provideUnexpectedValueExceptionIsThrownCases(): iterable
    {
        yield 'empty' => [''];

        yield 'malformed' => ['x.y'];
    }

    public function testDomainExceptionIsThrown(): void
    {
        $this->expectException(DomainException::class);
        $this->decoder->decode('x.y.z');
    }

    public function testSignatureInvalidExceptionIsThrown(): void
    {
        $this->expectException(SignatureInvalidException::class);

        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImFjbWUifQ.eyJpYXQiOjE1MTYyMzkwMjJ9.nope';
        $this->decoder->decode($token);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideBeforeValidExceptionIsThrownCases')]
    public function testBeforeValidExceptionIsThrown(array $payload): void
    {
        $this->expectException(BeforeValidException::class);
        $token = $this->encode($payload);

        $this->decoder->decode($token);
    }

    /**
     * @return iterable<array<array{iat:int, nbf?:int}>>
     */
    public static function provideBeforeValidExceptionIsThrownCases(): iterable
    {
        yield 'nbf is in the future' => [['iat' => time(), 'nbf' => (new DateTime('+5 minutes'))->getTimestamp()]];

        yield 'iat is in the future' => [['iat' => (new DateTime('+5 minutes'))->getTimestamp()]];
    }

    public function testExpiredExceptionIsThrown(): void
    {
        $this->expectException(ExpiredException::class);
        $token = $this->encode(['exp' => (new DateTime('-5 minutes'))->getTimestamp()]);

        $this->decoder->decode($token);
    }

    public function testHappyPath(): void
    {
        $now = time();
        $token = JWT::encode(['iat' => $now], $this->secret, 'HS256', null, []);
        $decoded = (new FirebaseDecoder(new Secret($this->secret, 'HS256')))->decode($token);

        self::assertSame($now, $decoded['iat']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encode(array $payload): string
    {
        return JWT::encode($payload, $this->secret, 'HS256', 'acme');
    }
}
