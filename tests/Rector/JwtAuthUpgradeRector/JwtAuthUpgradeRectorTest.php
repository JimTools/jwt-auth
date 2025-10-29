<?php

declare(strict_types=1);

namespace JimTools\JwtAuth\Test\Rector\JwtAuthUpgradeRector;

use JimTools\JwtAuth\Rector\JwtAuthUpgradeRector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Rector\Testing\Fixture\FixtureFileFinder;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

/**
 * @internal
 */
#[CoversClass(JwtAuthUpgradeRector::class), Group('rector')]
final class JwtAuthUpgradeRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideCases')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /**
     * @return iterable<FixtureFileFinder>
     */
    public static function provideCases(): iterable
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/config.php';
    }
}
