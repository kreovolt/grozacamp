<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Domain;

use Groza\Domain\Collections;
use Groza\Pages\HomePage;
use PHPUnit\Framework\TestCase;

/** Число вопросов «на главной» задано в одном месте и совпадает в счётчике, подсказке и на странице. */
final class FaqLimitTest extends TestCase
{
    public function test_home_faq_limit_has_a_single_source(): void
    {
        $faq = Collections::get('faq');
        $this->assertSame(Collections::FAQ_HOME_MAX, $faq['counter']['max']);
        $this->assertSame(Collections::FAQ_HOME_MAX, HomePage::FAQ_LIMIT);
        $help = '';
        foreach ($faq['fields'] as $f) {
            if ($f['name'] === 'onHome') {
                $help = (string) $f['help'];
            }
        }
        $this->assertStringContainsString((string) Collections::FAQ_HOME_MAX, $help);
    }
}
