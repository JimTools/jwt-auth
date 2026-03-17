<?php

declare(strict_types=1);

namespace JimTools\JwtAuth\Middleware;

use JimTools\JwtAuth\Decoder\DecoderInterface;
use JimTools\JwtAuth\Exceptions\AuthorizationException;
use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Rules\RequestMethodRule;
use JimTools\JwtAuth\Rules\RequestPathRule;
use JimTools\JwtAuth\Rules\RuleInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use SplStack;
use Throwable;

use function in_array;
use function sprintf;

final class JwtAuthentication implements MiddlewareInterface
{
    /**
     * The rules stack.
     *
     * @var SplStack<RuleInterface>
     */
    private SplStack $rules;

    /**
     * @param RuleInterface[] $rules
     */
    public function __construct(
        private readonly Options $options,
        private readonly DecoderInterface $decoder,
        ?array $rules = null,
    ) {
        // Setup stack for rules
        $this->rules = new SplStack();
        if ($rules === null) {
            $this->rules->push(new RequestMethodRule());
            $this->rules->push(new RequestPathRule());
        } else {
            foreach ($rules as $rule) {
                $this->rules->push($rule);
            }
        }
    }

    /**
     * @throws AuthorizationException
     * @throws RuntimeException if http is detected while secure mode is enabled.
     *
     * Process a request in PSR-15 style and return a response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // If rules say we should not authenticate call next and return.
        if (false === $this->shouldAuthenticate($request)) {
            return $handler->handle($request);
        }

        $this->checkSecureConfig($request);

        $token = $this->fetchToken($request);

        try {
            $decoded = $this->decoder->decode($token);
        } catch (Throwable $ex) {
            throw new AuthorizationException('', 0, $ex);
        }

        $params = [
            'decoded' => $decoded,
            'token' => $token,
        ];

        // Add decoded token to request as attribute when requested.
        if ($this->options->attribute) {
            $request = $request->withAttribute($this->options->attribute, $decoded);
        }

        // Modify $request before calling next middleware.
        $before = $this->options->before;
        if ($before !== null) {
            $request = $before($request, $params);
        }

        // Everything ok, call next middleware.
        $response = $handler->handle($request);

        // Modify $response before returning.
        $after = $this->options->after;
        if ($after !== null) {
            return $after($response, $params);
        }

        return $response;
    }

    /**
     * Set all rules in the stack.
     */
    public function withRules(RuleInterface ...$rules): self
    {
        $new = clone $this;
        $new->rules = new SplStack();
        // Add the rules
        foreach ($rules as $callable) {
            $new = $new->addRule($callable);
        }

        return $new;
    }

    /**
     * Add a rule to the stack.
     */
    public function addRule(RuleInterface $rule): self
    {
        $new = clone $this;
        $new->rules = clone $this->rules;
        $new->rules->push($rule);

        return $new;
    }

    /**
     * Check if middleware should authenticate.
     */
    private function shouldAuthenticate(ServerRequestInterface $request): bool
    {
        // If any of the rules in stack return false will not authenticate
        foreach ($this->rules as $callable) {
            if (false === $callable($request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws AuthorizationException
     *
     * Fetch the access token
     */
    private function fetchToken(ServerRequestInterface $request): string
    {
        // Check for token in header.
        $header = $request->getHeaderLine($this->options->header);

        if (false === empty($header)) {
            if (preg_match($this->options->regexp, $header, $matches)) {
                return $matches[1];
            }
        }

        // Token not found in header try a cookie.
        $cookieParams = $request->getCookieParams();

        if (isset($cookieParams[$this->options->cookie])) {
            if (preg_match($this->options->regexp, $cookieParams[$this->options->cookie], $matches)) {
                return $matches[1];
            }

            return $cookieParams[$this->options->cookie];
        }

        throw AuthorizationException::noTokenFound();
    }

    /**
     * @throws RuntimeException
     */
    private function checkSecureConfig(ServerRequestInterface $request): void
    {
        $scheme = $request->getUri()->getScheme();
        $host = $request->getUri()->getHost();
        if ('https' !== $scheme && true === $this->options->isSecure) {
            if (!in_array($host, $this->options->relaxed, true)) {
                $message = sprintf(
                    'Insecure use of middleware over %s denied by configuration.',
                    strtoupper($scheme)
                );

                throw new RuntimeException($message);
            }
        }
    }
}
