<?php

declare(strict_types=1);

namespace JimTools\JwtAuth\Rector;

use InvalidArgumentException;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Middleware\JwtAuthentication;
use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Rules\RequestMethodRule;
use JimTools\JwtAuth\Rules\RequestPathRule;
use JimTools\JwtAuth\Rules\RuleInterface;
use JimTools\JwtAuth\Secret;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Rector\Rector\AbstractRector;
use RuntimeException;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

use function count;
use function in_array;

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

                $newUse = [new UseItem($this->replaceUse($name), $use->alias)];

                return new Use_($newUse);
            }

            return $node;
        }

        if ($node instanceof New_) {
            $name = $this->getName($node->class);
            if ($name !== 'Tuupola\Middleware\JwtAuthentication') {
                return null;
            }

            $options = $node->getArgs()[0];
            if (!$options->value instanceof Array_) {
                throw new RuntimeException('Can only parse options which are arrays');
            }

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
        return new RuleDefinition('Upgrades JwtAuthentication from v1 to v2', []);
    }

    /**
     * @param Arg[] $optionArgs
     * @param Arg[] $decoderArgs
     *
     * @return Arg[]
     */
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

    /**
     * @return array{0:Arg[],1:Arg[],2:null|Array_}
     */
    private function extractArgs(Array_ $options): array
    {
        $paths = null;
        $ignore = null;
        $rules = null;
        $secret = null;
        $optionArgs = [];
        foreach ($options->items as $item) {
            $key = $item->key->value ?? '';
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

                if ($key === 'ignore' && $val instanceof Array_) {
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

                    /** @var ArrayItem $sItem */
                    foreach ($val->items as $sItem) {
                        if ($item->key === null || !isset($sItem->key->value)) {
                            throw new InvalidArgumentException('secret key is empty');
                        }
                        $secret[$sItem->key->value] = $sItem->value;
                    }
                }

                continue;
            }

            if ($key === 'algorithm' && $val instanceof Array_) {
                $algo = [];

                /** @var ArrayItem $aItem */
                foreach ($val->items as $aItem) {
                    if ($aItem->key !== null) {
                        $aItem->key->value ?? throw new InvalidArgumentException('algorithm key is empty');

                        $algo[$aItem->key->value] = $aItem->value;

                        continue;
                    }

                    $algo[] = $aItem->value;
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

        if ($rules === null && $paths instanceof Array_) {
            $rules = $this->createRules($paths, $ignore);
        }

        // no algo defined so default
        if (!isset($algo)) {
            $algo = [new String_('HS256')];
        }

        return [$optionArgs, $this->createDecoderArgs($secret ?? [], $algo), $rules];
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

    private function isOptionDefault(string $key, Expr $val): bool
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
                static fn (ArrayItem $item) => $item->value->value ?? throw new RuntimeException('relaxed item value is empty'),
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
            $args[] = new Arg($ignore, name: new Identifier('ignore'));
        }

        $pathObj = new New_(new Name(RequestPathRule::class), $args);

        return new Array_(
            [
                new ArrayItem($pathObj),
                new ArrayItem(new New_(new Name(RequestMethodRule::class))),
            ]
        );
    }

    /**
     * @param array<array-key,Expr> $secrets
     * @param array<array-key,Expr> $algorithms
     *
     * @return array<array-key,Arg>
     */
    private function createDecoderArgs(array $secrets, array $algorithms): array
    {
        if (empty($secrets)) {
            throw new InvalidArgumentException('secrets argument is empty');
        }

        $keyObjects = [];
        $hasMany = count($algorithms) > 1;
        foreach ($algorithms as $kid => $algo) {
            $keyId = !is_numeric($kid) ? $kid : $algo->value ?? throw new InvalidArgumentException('algorithms value is null');

            $args = [
                new Arg($secrets[$kid] ?? $secrets[0]),
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
                throw new InvalidArgumentException('unknown class name'); // @codeCoverageIgnore
        }
    }
}
