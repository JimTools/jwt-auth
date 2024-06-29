<?php

declare(strict_types=1);

namespace JimTools\JwtAuth\Rector;

use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Rules\RequestMethodRule;
use JimTools\JwtAuth\Rules\RequestPathRule;
use JimTools\JwtAuth\Rules\RuleInterface;
use JimTools\JwtAuth\Secret;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rector\Rector\AbstractRector;
use RuntimeException;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function count;
use function in_array;
use function is_array;

final class JwtAuthUpgradeRector extends AbstractRector
{
    private const KNOWN_KEYS = [
        'rules',
        'secret',
        'secure',
        'relaxed',
        'algorithm',
        'header',
        'regexp',
        'cookie',
        'attribute',
        'path',
        'ignore',
        'before',
        'after',
        'error',
        'logger',
    ];

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [
            Use_::class,
            New_::class,
        ];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node instanceof Use_) {
            foreach ($node->uses as $use) {
                $name = $this->getName($use->name);
                if (!$this->inNamespace($name)) {
                    continue;
                }

                $newUse = new UseUse($this->replaceUse($name), $use->alias);

                return new Use_([$newUse]);
            }

            return $node;
        }

        if ($node instanceof New_) {
            $name = $this->getName($node->class);
            if ($name !== 'Tuupola\Middleware\JwtAuthentication') {
                return null;
            }

            $options = $node->getArgs()[0];
            [$optionArgs, $decoderArgs, $rules] = $this->extractArgs($options->value);
            $args = $this->replaceArgs($optionArgs, $decoderArgs, $rules);
            $node->args = $args;

            return $node;
        }

        return null;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Upgrades JwtAuthentication from v1 to v2');
    }

    private function replaceArgs(array $optionArgs, array $decoderArgs, ?Array_ $rules): array
    {
        $optionObj = new Name(Options::class);
        $decoder = new Name(FirebaseDecoder::class);

        return array_filter([
            new Arg(new New_($optionObj, $optionArgs)),
            new Arg(new New_($decoder, $decoderArgs)),
            $rules !== null ? new Arg($rules) : null,
        ]);
    }

    private function extractArgs(Array_ $options): array
    {
        $paths = null;
        $ignore = null;
        $rules = null;
        $secret = null;
        $optionArgs = [];
        foreach ($options->items as $item) {
            $key = $item->key->value;
            $val = $item->value;
            if (!in_array($key, self::KNOWN_KEYS, true)) {
                continue;
            }

            if ($key === 'before' && $val instanceof Closure) {
                $val = $this->convertBefore($val);
            }

            if ($key === 'after' && $val instanceof Closure) {
                $val = $this->convertAfter($val);
            }

            if (in_array($key, ['error', 'logger', 'path', 'ignore'], true)) {
                if ($key === 'path') {
                    $paths = $val;
                }

                if ($key === 'ignore') {
                    $ignore = $val;
                }

                continue;
            }

            if ($this->isOptionDefault($key, $val)) {
                continue;
            }

            if ($val instanceof Array_ && count($val->items) < 1) {
                continue;
            }

            if ($key === 'rules' && $val instanceof Array_) {
                $rules = $val;

                continue;
            }

            if ($key === 'secret') {
                $secret = [$val];
                if ($val instanceof Array_) {
                    $secret = [];
                    foreach ($val->items as $item) {
                        $secret[$item->key->value] = $item->value;
                    }
                }

                continue;
            }

            if ($key === 'algorithm') {
                $algo = [];
                foreach ($val->items as $item) {
                    if ($item->key === null) {
                        $algo[] = $item->value;
                    } else {
                        $algo[$item->key->value] = $item->value;
                    }
                }

                continue;
            }

            if (
                $key !== 'attribute'
                && $val instanceof ConstFetch
                && $this->getName($val->name) === 'null'
            ) {
                continue;
            }

            $map = ['secure' => 'isSecure'];
            $mappedKey = $map[$key] ?? $key;

            $optionArgs[] = new Arg($val, name: new Identifier($mappedKey));
        }

        if ($rules === null) {
            $rules = $this->createRules($paths, $ignore);
        }

        // no algo defined so default
        if (!isset($algo)) {
            $algo = [new String_('HS256')];
        }

        return [$optionArgs, $this->createDecoderArgs($secret, $algo), $rules];
    }

    private function convertAfter(Closure $val): New_
    {
        $resp = new Identifier(ResponseInterface::class);
        $val->returnType = $resp;
        $val->params[0]->type = $resp;
        $val->params[1]->type = new Identifier('array');

        $after = new Class_(
            null,
            [
                'implements' => [
                    new Name('JimTools\JwtAuth\Handlers\AfterHandlerInterface'),
                ],
                'stmts' => [
                    new ClassMethod(
                        '__invoke',
                        [
                            'flags' => Class_::MODIFIER_PUBLIC,
                            'params' => $val->params,
                            'returnType' => $resp,
                            'stmts' => $val->stmts,
                        ],
                    ),
                ],
            ],
            $val->getAttributes()
        );

        return new New_($after);
    }

    private function convertBefore(Closure $val): New_
    {
        $req = new Identifier(ServerRequestInterface::class);
        $val->returnType = $req;
        $val->params[0]->type = $req;
        $val->params[1]->type = new Identifier('array');

        $before = new Class_(
            null,
            [
                'implements' => [
                    new Name('JimTools\JwtAuth\Handlers\BeforeHandlerInterface'),
                ],
                'stmts' => [
                    new ClassMethod(
                        '__invoke',
                        [
                            'flags' => Class_::MODIFIER_PUBLIC,
                            'params' => $val->params,
                            'returnType' => $req,
                            'stmts' => $val->stmts,
                        ],
                    ),
                ],
            ],
            $val->getAttributes()
        );

        return new New_($before);
    }

    private function isOptionDefault(string $key, $val): bool
    {
        if (
            $key === 'secure'
            && $val instanceof ConstFetch
            && $this->getName($val) === 'true'
        ) {
            return true;
        }

        if ($key === 'relaxed' && $val instanceof Array_) {
            $items = array_map(
                static fn (ArrayItem $i) => $i->value->value,
                $val->items
            );

            return $items === ['localhost', '127.0.0.1'];
        }

        if (
            $key === 'header'
            && $val instanceof String_
            && $val->value === 'Authorization'
        ) {
            return true;
        }

        if (
            $key === 'regexp'
            && $val instanceof String_
            && $val->value === '/Bearer\s+(.*)$/i'
        ) {
            return true;
        }

        if (
            in_array($key, ['cookie', 'attribute'], true)
            && $val instanceof String_
            && $val->value === 'token'
        ) {
            return true;
        }

        return false;
    }

    private function createRules(?Array_ $paths, ?Array_ $ignore): ?Array_
    {
        if ($paths === null && $ignore === null) {
            return null;
        }

        if ($paths !== null) {
            array_unshift($paths->items, new ArrayItem(new String_('/')));
            $args[] = new Arg($paths, name: new Identifier('path'));
        }

        if ($ignore !== null) {
            $args[] = new Arg($ignore, name: new Identifier('ignnore'));
        }

        $pathObj = new New_(new Name(RequestPathRule::class), $args);

        return new Array_(
            [
                new ArrayItem($pathObj),
                new ArrayItem(new New_(new Name(RequestMethodRule::class))),
            ]
        );
    }

    private function createDecoderArgs(array $secrets, array $algorithms): array
    {
        $keyObjects = [];
        $hasMany = count($algorithms) > 1;
        foreach ($algorithms as $kid => $algo) {
            $keyId = !is_numeric($kid) ? $kid : $algo->value;

            $secret = $secrets;
            if (is_array($secret) || $secret instanceof ArrayAccess) {
                $secret = $secrets[$kid] ?? $secrets[0];
            }

            $args = [
                new Arg($secret),
                new Arg($algo),
            ];

            if ($hasMany === true) {
                $args[] = new Arg(new String_($keyId));
            }

            $keyObjects[] = new Arg(new New_(new Name(Secret::class), $args));
        }

        return $keyObjects;
    }

    private function inNamespace(string $name): bool
    {
        return in_array($name, [
            'Tuupola\Middleware\JwtAuthentication',
            'Tuupola\Middleware\JwtAuthentication\RequestMethodRule',
            'Tuupola\Middleware\JwtAuthentication\RequestPathRule',
            'Tuupola\Middleware\JwtAuthentication\RuleInterface',
        ], true);
    }

    private function replaceUse(string $name): Name
    {
        switch ($name) {
            case 'Tuupola\Middleware\JwtAuthentication':
                return new Name(JwtAuthentication::class);

            case 'Tuupola\Middleware\JwtAuthentication\RequestMethodRule':
                return new Name(RequestMethodRule::class);

            case 'Tuupola\Middleware\JwtAuthentication\RequestPathRule':
                return new Name(RequestPathRule::class);

            case 'Tuupola\Middleware\JwtAuthentication\RuleInterface':
                return new Name(RuleInterface::class);

            default:
                throw new RuntimeException('unknown class name');
        }
    }
}
