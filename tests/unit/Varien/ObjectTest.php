<?php

/**
 * @copyright  For copyright and license information, read the COPYING.txt file.
 * @link       /COPYING.txt
 * @license    Open Software License (OSL 3.0)
 * @package    OpenMage_Tests
 */

declare(strict_types=1);

namespace OpenMage\Tests\Unit\Varien;

use Generator;
use PHPUnit\Framework\TestCase;
use stdClass;
use Varien_Exception;
use Varien_Object;

final class ObjectTest extends TestCase
{
    public Varien_Object $subject;

    public function setUp(): void
    {
        $this->subject = new Varien_Object();
    }

    /**
     * @dataProvider provideGetDataData
     * @param mixed $expectedResult
     * @param string $setKey
     * @param mixed $setValue
     * @param string|int|null $index
     *
     * @group Varien_Object
     */
    public function testGetData($expectedResult, $setKey, $setValue, string $key, $index = null): void
    {
        $this->subject->setData($setKey, $setValue);
        static::assertSame($expectedResult, $this->subject->getData($key, $index));
    }

    public function provideGetDataData(): Generator
    {
        yield 'empty_key' => [
            ['empty_key' => ['empty_value']],
            'empty_key',
            ['empty_value'],
            '',
        ];
        yield 'string' => [
            'value',
            'string',
            'value',
            'string',
        ];
        yield 'int' => [
            1,
            'int',
            1,
            'int',
        ];
        yield 'numeric' => [
            '1',
            'numeric',
            '1',
            'numeric',
        ];
        yield 'array' => [
            ['string', 1],
            'array',
            ['string', 1],
            'array',
        ];
        yield 'array_index_int' => [
            'string',
            'array_index_int',
            ['string', 1],
            'array_index_int',
            0,
        ];
        yield 'array_index_int_invalid' => [
            null,
            'array_index_int_invalid',
            ['string', 1],
            'array_index_int_invalid',
            999,
        ];
        yield 'array_index_string' => [
            1,
            'array_index_string',
            ['string' => 'string', 'int' => 1],
            'array_index_string',
            'int',
        ];
        yield 'array_index_string_string' => [
            null,
            'array_index_string_string',
            'some_string',
            'array_index_string_string',
            'not-exists',
        ];
        yield 'array_index_string_varien_object' => [
            [],
            'array_index_string_varien_object',
            new Varien_Object(['array' => []]),
            'array_index_string_varien_object',
            'array',
        ];
        yield 'array_index_string_std_class' => [
            null,
            'array_index_string_std_class',
            new stdClass(),
            'array_index_string_std_class',
            'not-exists',
        ];
        yield 'array_nested' => [
            1,
            'array_nested',
            ['nested' => ['string' => 'string', 'int' => 1]],
            'array_nested/nested/int',
        ];
        yield 'array_nested_invalid_key' => [
            null,
            'array_nested',
            ['nested' => ['string' => 'string', 'int' => 1]],
            'array_nested/nested/invalid_key',
        ];
        yield 'array_nested_empty_key' => [
            null,
            'array_nested',
            ['nested' => ['string' => 'string', 'int' => '']],
            'array_nested/nested/',
        ];
        yield 'array_nested_string' => [
            'some"\n"string',
            'array_nested_string',
            ['nested' => 'some"\n"string'],
            'array_nested_string/nested',
        ];
        yield 'array_nested_varien_object' => [
            null,
            'array_nested_varien_object',
            new Varien_Object(),
            'array_nested_varien_object/nested',
        ];
        yield 'array_nested_std_class' => [
            null,
            'array_nested_std_class',
            new stdClass(),
            'array_nested_std_class/nested',
        ];
        yield 'array_nested_key_not_exists' => [
            null,
            'array_nested_key_not_exists',
            ['nested' => ['string' => 'string', 'int' => 1]],
            'array_nested_key_not_exists_test/nested/int',
        ];
    }

    /**
     * @dataProvider provideToString
     * @group Varien_Object
     */
    public function testToString(string $expectedResult, string $format): void
    {
        $this->subject->setString0('0');
        $this->subject->setString1('one');
        $this->subject->setString2('two');
        $this->subject->setString3('three');

        static::assertSame($expectedResult, $this->subject->toString($format));
    }

    public function provideToString(): Generator
    {
        yield 'no format' => [
            '0, one, two, three',
            '',
        ];
        yield 'valid' => [
            '0 one two',
            '{{string0}} {{string1}} {{string2}}',
        ];
        yield 'invalid' => [
            'three  0',
            '{{string3}} {{string_not_exists}} {{string0}}',
        ];
    }

    /**
     * @group Varien_Object
     */
    public function testGetSetUnsData(): void
    {
        static::assertTrue($this->subject->isEmpty());
        $this->subject->setABC('abc');
        $this->subject->setData('efg', 'efg');
        $this->subject->set123('123');
        $this->subject->setData('345', '345');
        $this->subject->setKeyAFirst('value_a_first');
        $this->subject->setData('key_a_2nd', 'value_a_2nd');
        $this->subject->setKeyA3rd('value_a_3rd');
        $this->subject->setData('left', 'over');
        static::assertFalse($this->subject->isEmpty());

        static::assertSame('abc', $this->subject->getData('a_b_c'));
        static::assertSame('abc', $this->subject->getABC());
        $this->subject->unsetData('a_b_c');

        static::assertSame('efg', $this->subject->getData('efg'));
        static::assertSame('efg', $this->subject->getEfg());
        $this->subject->unsEfg();

        static::assertSame('123', $this->subject->getData('123'));
        static::assertSame('123', $this->subject->get123());
        $this->subject->uns123();

        $this->subject->unsetData('345');

        static::assertSame('value_a_first', $this->subject->getData('key_a_first'));
        static::assertSame('value_a_first', $this->subject->getKeyAFirst());
        $this->subject->unsetData('key_a_first');

        static::assertSame('value_a_2nd', $this->subject->getData('key_a_2nd'));
        static::assertSame('value_a_2nd', $this->subject->getKeyA_2nd());
        $this->subject->unsetData('key_a_2nd');

        static::assertSame('value_a_3rd', $this->subject->getData('key_a3rd'));
        static::assertSame('value_a_3rd', $this->subject->getKeyA3rd());
        $this->subject->unsetData('key_a3rd');

        static::assertSame(['left' => 'over'], $this->subject->getData());

        $this->subject->unsetData();
        static::assertSame([], $this->subject->getData());
        static::assertTrue($this->subject->isEmpty());

        try {
            /** @phpstan-ignore-next-line */
            $this->subject->notData();
            static::fail('Invalid __call');
        } catch (Varien_Exception $exception) {
            static::assertStringStartsWith('Invalid method', $exception->getMessage());
        }
    }

    /**
     * @group Varien_Object
     */
    public function testOffset(): void
    {
        static::assertFalse($this->subject->offsetExists('off'));

        $this->subject->offsetSet('off', 'set');
        static::assertTrue($this->subject->offsetExists('off'));
        static::assertSame('set', $this->subject->offsetGet('off'));
        static::assertNull($this->subject->offsetGet('not-exists'));

        $this->subject->offsetUnset('off');
        static::assertFalse($this->subject->offsetExists('off'));
    }
}
