<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Tag\TaggedValue;
use Symfony\Component\Yaml\Yaml;

class DumperTest extends TestCase
{
    protected $parser;
    protected $dumper;
    protected $path;

    protected $array = [
        '' => 'bar',
        'foo' => '#bar',
        'foo\'bar' => [],
        'bar' => [1, 'foo', ['a' => 'A']],
        'foobar' => [
            'foo' => 'bar',
            'bar' => [1, 'foo'],
            'foobar' => [
                'foo' => 'bar',
                'bar' => [1, 'foo'],
            ],
        ],
    ];

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->dumper = new Dumper();
        $this->path = __DIR__.'/Fixtures';
    }

    protected function tearDown(): void
    {
        $this->parser = null;
        $this->dumper = null;
        $this->path = null;
        $this->array = null;
    }

    public function testIndentationInConstructor()
    {
        $dumper = new Dumper(7);
        $expected = <<<'EOF'
'': bar
foo: '#bar'
"foo'bar": {  }
bar:
       - 1
       - foo
       -
              a: A
foobar:
       foo: bar
       bar:
              - 1
              - foo
       foobar:
              foo: bar
              bar:
                     - 1
                     - foo

EOF;
        $this->assertEquals($expected, $dumper->dump($this->array, 4, 0));
    }

    public function testSpecifications()
    {
        $files = $this->parser->parse(file_get_contents($this->path.'/index.yml'));
        foreach ($files as $file) {
            $yamls = file_get_contents($this->path.'/'.$file.'.yml');

            // split YAMLs documents
            foreach (preg_split('/^---( %YAML\:1\.0)?/m', $yamls) as $yaml) {
                if (!$yaml) {
                    continue;
                }

                $test = $this->parser->parse($yaml);
                if (isset($test['dump_skip']) && $test['dump_skip']) {
                    continue;
                } elseif (isset($test['todo']) && $test['todo']) {
                    // TODO
                } else {
                    eval('$expected = '.trim($test['php']).';');
                    $this->assertSame($expected, $this->parser->parse($this->dumper->dump($expected, 10)), $test['test']);
                }
            }
        }
    }

    public function testInlineLevel()
    {
        $expected = <<<'EOF'
{ '': bar, foo: '#bar', "foo'bar": {  }, bar: [1, foo, { a: A }], foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } } }
EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, -10), '->dump() takes an inline level argument');
        $this->assertEquals($expected, $this->dumper->dump($this->array, 0), '->dump() takes an inline level argument');

        $expected = <<<'EOF'
'': bar
foo: '#bar'
"foo'bar": {  }
bar: [1, foo, { a: A }]
foobar: { foo: bar, bar: [1, foo], foobar: { foo: bar, bar: [1, foo] } }

EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, 1), '->dump() takes an inline level argument');

        $expected = <<<'EOF'
'': bar
foo: '#bar'
"foo'bar": {  }
bar:
    - 1
    - foo
    - { a: A }
foobar:
    foo: bar
    bar: [1, foo]
    foobar: { foo: bar, bar: [1, foo] }

EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, 2), '->dump() takes an inline level argument');

        $expected = <<<'EOF'
'': bar
foo: '#bar'
"foo'bar": {  }
bar:
    - 1
    - foo
    -
        a: A
foobar:
    foo: bar
    bar:
        - 1
        - foo
    foobar:
        foo: bar
        bar: [1, foo]

EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, 3), '->dump() takes an inline level argument');

        $expected = <<<'EOF'
'': bar
foo: '#bar'
"foo'bar": {  }
bar:
    - 1
    - foo
    -
        a: A
foobar:
    foo: bar
    bar:
        - 1
        - foo
    foobar:
        foo: bar
        bar:
            - 1
            - foo

EOF;
        $this->assertEquals($expected, $this->dumper->dump($this->array, 4), '->dump() takes an inline level argument');
        $this->assertEquals($expected, $this->dumper->dump($this->array, 10), '->dump() takes an inline level argument');
    }

    public function testObjectSupportEnabled()
    {
        $dump = $this->dumper->dump(['foo' => new A(), 'bar' => 1], 0, 0, Yaml::DUMP_OBJECT);

        $this->assertEquals('{ foo: !php/object \'O:30:"Symfony\Component\Yaml\Tests\A":1:{s:1:"a";s:3:"foo";}\', bar: 1 }', $dump, '->dump() is able to dump objects');
    }

    public function testObjectSupportDisabledButNoExceptions()
    {
        $dump = $this->dumper->dump(['foo' => new A(), 'bar' => 1]);

        $this->assertEquals('{ foo: null, bar: 1 }', $dump, '->dump() does not dump objects when disabled');
    }

    public function testObjectSupportDisabledWithExceptions()
    {
        $this->expectException(DumpException::class);
        $this->dumper->dump(['foo' => new A(), 'bar' => 1], 0, 0, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
    }

    /**
     * @dataProvider getEscapeSequences
     */
    public function testEscapedEscapeSequencesInQuotedScalar($input, $expected)
    {
        $this->assertEquals($expected, $this->dumper->dump($input));
    }

    public function getEscapeSequences()
    {
        return [
            'empty string' => ['', "''"],
            'null' => ["\x0", '"\\0"'],
            'bell' => ["\x7", '"\\a"'],
            'backspace' => ["\x8", '"\\b"'],
            'horizontal-tab' => ["\t", '"\\t"'],
            'line-feed' => ["\n", '"\\n"'],
            'vertical-tab' => ["\v", '"\\v"'],
            'form-feed' => ["\xC", '"\\f"'],
            'carriage-return' => ["\r", '"\\r"'],
            'escape' => ["\x1B", '"\\e"'],
            'space' => [' ', "' '"],
            'double-quote' => ['"', "'\"'"],
            'slash' => ['/', '/'],
            'backslash' => ['\\', '\\'],
            'del' => ["\x7f", '"\x7f"'],
            'next-line' => ["\xC2\x85", '"\\N"'],
            'non-breaking-space' => ["\xc2\xa0", '"\\_"'],
            'line-separator' => ["\xE2\x80\xA8", '"\\L"'],
            'paragraph-separator' => ["\xE2\x80\xA9", '"\\P"'],
            'colon' => [':', "':'"],
        ];
    }

    public function testBinaryDataIsDumpedBase64Encoded()
    {
        $binaryData = file_get_contents(__DIR__.'/Fixtures/arrow.gif');
        $expected = '{ data: !!binary '.base64_encode($binaryData).' }';

        $this->assertSame($expected, $this->dumper->dump(['data' => $binaryData]));
    }

    public function testNonUtf8DataIsDumpedBase64Encoded()
    {
        // "für" (ISO-8859-1 encoded)
        $this->assertSame('!!binary ZsM/cg==', $this->dumper->dump("f\xc3\x3fr"));
    }

    /**
     * @dataProvider objectAsMapProvider
     */
    public function testDumpObjectAsMap($object, $expected)
    {
        $yaml = $this->dumper->dump($object, 0, 0, Yaml::DUMP_OBJECT_AS_MAP);

        $this->assertEquals($expected, Yaml::parse($yaml, Yaml::PARSE_OBJECT_FOR_MAP));
    }

    public function objectAsMapProvider()
    {
        $tests = [];

        $bar = new \stdClass();
        $bar->class = 'classBar';
        $bar->args = ['bar'];
        $zar = new \stdClass();
        $foo = new \stdClass();
        $foo->bar = $bar;
        $foo->zar = $zar;
        $object = new \stdClass();
        $object->foo = $foo;
        $tests['stdClass'] = [$object, $object];

        $arrayObject = new \ArrayObject();
        $arrayObject['foo'] = 'bar';
        $arrayObject['baz'] = 'foobar';
        $parsedArrayObject = new \stdClass();
        $parsedArrayObject->foo = 'bar';
        $parsedArrayObject->baz = 'foobar';
        $tests['ArrayObject'] = [$arrayObject, $parsedArrayObject];

        $a = new A();
        $tests['arbitrary-object'] = [$a, null];

        return $tests;
    }

    public function testDumpingArrayObjectInstancesRespectsInlineLevel()
    {
        $deep = new \ArrayObject(['deep1' => 'd', 'deep2' => 'e']);
        $inner = new \ArrayObject(['inner1' => 'b', 'inner2' => 'c', 'inner3' => $deep]);
        $outer = new \ArrayObject(['outer1' => 'a', 'outer2' => $inner]);

        $yaml = $this->dumper->dump($outer, 2, 0, Yaml::DUMP_OBJECT_AS_MAP);

        $expected = <<<YAML
outer1: a
outer2:
    inner1: b
    inner2: c
    inner3: { deep1: d, deep2: e }

YAML;
        $this->assertSame($expected, $yaml);
    }

    public function testDumpingArrayObjectInstancesWithNumericKeysInlined()
    {
        $deep = new \ArrayObject(['d', 'e']);
        $inner = new \ArrayObject(['b', 'c', $deep]);
        $outer = new \ArrayObject(['a', $inner]);

        $yaml = $this->dumper->dump($outer, 0, 0, Yaml::DUMP_OBJECT_AS_MAP);
        $expected = <<<YAML
{ 0: a, 1: { 0: b, 1: c, 2: { 0: d, 1: e } } }
YAML;
        $this->assertSame($expected, $yaml);
    }

    public function testDumpingArrayObjectInstancesWithNumericKeysRespectsInlineLevel()
    {
        $deep = new \ArrayObject(['d', 'e']);
        $inner = new \ArrayObject(['b', 'c', $deep]);
        $outer = new \ArrayObject(['a', $inner]);
        $yaml = $this->dumper->dump($outer, 2, 0, Yaml::DUMP_OBJECT_AS_MAP);
        $expected = <<<YAML
0: a
1:
    0: b
    1: c
    2: { 0: d, 1: e }

YAML;
        $this->assertEquals($expected, $yaml);
    }

    public function testDumpEmptyArrayObjectInstanceAsMap()
    {
        $this->assertSame('{  }', $this->dumper->dump(new \ArrayObject(), 2, 0, Yaml::DUMP_OBJECT_AS_MAP));
    }

    public function testDumpEmptyStdClassInstanceAsMap()
    {
        $this->assertSame('{  }', $this->dumper->dump(new \stdClass(), 2, 0, Yaml::DUMP_OBJECT_AS_MAP));
    }

    public function testDumpingStdClassInstancesRespectsInlineLevel()
    {
        $deep = new \stdClass();
        $deep->deep1 = 'd';
        $deep->deep2 = 'e';

        $inner = new \stdClass();
        $inner->inner1 = 'b';
        $inner->inner2 = 'c';
        $inner->inner3 = $deep;

        $outer = new \stdClass();
        $outer->outer1 = 'a';
        $outer->outer2 = $inner;

        $yaml = $this->dumper->dump($outer, 2, 0, Yaml::DUMP_OBJECT_AS_MAP);

        $expected = <<<YAML
outer1: a
outer2:
    inner1: b
    inner2: c
    inner3: { deep1: d, deep2: e }

YAML;
        $this->assertSame($expected, $yaml);
    }

    public function testDumpingTaggedValueSequenceRespectsInlineLevel()
    {
        $data = [
            new TaggedValue('user', [
                'username' => 'jane',
            ]),
            new TaggedValue('names', [
                'john',
                'claire',
            ]),
        ];

        $yaml = $this->dumper->dump($data, 2);

        $expected = <<<YAML
- !user
  username: jane
- !names
  - john
  - claire

YAML;
        $this->assertSame($expected, $yaml);
    }

    public function testDumpingTaggedValueSequenceWithInlinedTagValues()
    {
        $data = [
            new TaggedValue('user', [
                'username' => 'jane',
            ]),
            new TaggedValue('names', [
                'john',
                'claire',
            ]),
        ];

        $yaml = $this->dumper->dump($data, 1);

        $expected = <<<YAML
- !user { username: jane }
- !names [john, claire]

YAML;
        $this->assertSame($expected, $yaml);
    }

    public function testDumpingTaggedValueMapRespectsInlineLevel()
    {
        $data = [
            'user1' => new TaggedValue('user', [
                'username' => 'jane',
            ]),
            'names1' => new TaggedValue('names', [
                'john',
                'claire',
            ]),
        ];

        $yaml = $this->dumper->dump($data, 2);

        $expected = <<<YAML
user1: !user
    username: jane
names1: !names
    - john
    - claire

YAML;
        $this->assertSame($expected, $yaml);
    }

    public function testDumpingTaggedValueMapWithInlinedTagValues()
    {
        $data = [
            'user1' => new TaggedValue('user', [
                'username' => 'jane',
            ]),
            'names1' => new TaggedValue('names', [
                'john',
                'claire',
            ]),
        ];

        $yaml = $this->dumper->dump($data, 1);

        $expected = <<<YAML
user1: !user { username: jane }
names1: !names [john, claire]

YAML;
        $this->assertSame($expected, $yaml);
    }

    public function testDumpingNotInlinedScalarTaggedValue()
    {
        $data = [
            'user1' => new TaggedValue('user', 'jane'),
            'user2' => new TaggedValue('user', 'john'),
        ];
        $expected = <<<YAML
user1: !user jane
user2: !user john

YAML;

        $this->assertSame($expected, $this->dumper->dump($data, 2));
    }

    public function testDumpingNotInlinedNullTaggedValue()
    {
        $data = [
            'foo' => new TaggedValue('bar', null),
        ];
        $expected = <<<YAML
foo: !bar null

YAML;

        $this->assertSame($expected, $this->dumper->dump($data, 2));
    }

    public function testDumpingMultiLineStringAsScalarBlockTaggedValue()
    {
        $data = [
            'foo' => new TaggedValue('bar', "foo\nline with trailing spaces:\n  \nbar\ninteger like line:\n123456789\nempty line:\n\nbaz"),
        ];
        $expected = "foo: !bar |\n".
            "    foo\n".
            "    line with trailing spaces:\n".
            "      \n".
            "    bar\n".
            "    integer like line:\n".
            "    123456789\n".
            "    empty line:\n".
            "    \n".
            '    baz';

        $this->assertSame($expected, $this->dumper->dump($data, 2, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    public function testDumpingInlinedMultiLineIfRnBreakLineInTaggedValue()
    {
        $data = [
            'data' => [
                'foo' => new TaggedValue('bar', "foo\r\nline with trailing spaces:\n  \nbar\ninteger like line:\n123456789\nempty line:\n\nbaz"),
            ],
        ];

        $this->assertSame(file_get_contents(__DIR__.'/Fixtures/multiple_lines_as_literal_block_for_tagged_values.yml'), $this->dumper->dump($data, 2, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    public function testDumpMultiLineStringAsScalarBlock()
    {
        $data = [
            'data' => [
                'single_line' => 'foo bar baz',
                'multi_line' => "foo\nline with trailing spaces:\n  \nbar\ninteger like line:\n123456789\nempty line:\n\nbaz",
                'multi_line_with_carriage_return' => "foo\nbar\r\nbaz",
                'nested_inlined_multi_line_string' => [
                    'inlined_multi_line' => "foo\nbar\r\nempty line:\n\nbaz",
                ],
            ],
        ];

        $this->assertSame(file_get_contents(__DIR__.'/Fixtures/multiple_lines_as_literal_block.yml'), $this->dumper->dump($data, 2, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    public function testDumpMultiLineStringAsScalarBlockWhenFirstLineHasLeadingSpace()
    {
        $data = [
            'data' => [
                'multi_line' => "    the first line has leading spaces\nThe second line does not.",
            ],
        ];

        $expected = "data:\n    multi_line: |4-\n            the first line has leading spaces\n        The second line does not.";

        $this->assertSame($expected, $this->dumper->dump($data, 2, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    public function testCarriageReturnFollowedByNewlineIsMaintainedWhenDumpingAsMultiLineLiteralBlock()
    {
        $this->assertSame("- \"a\\r\\nb\\nc\"\n", $this->dumper->dump(["a\r\nb\nc"], 2, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    public function testCarriageReturnNotFollowedByNewlineIsPreservedWhenDumpingAsMultiLineLiteralBlock()
    {
        $expected = <<<'YAML'
parent:
    foo: "bar\n\rbaz: qux"

YAML;

        $this->assertSame($expected, $this->dumper->dump([
            'parent' => [
                'foo' => "bar\n\rbaz: qux",
            ],
        ], 4, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    public function testNoExtraTrailingNewlineWhenDumpingAsMultiLineLiteralBlock()
    {
        $data = [
            "a\nb",
            "c\nd",
        ];
        $yaml = $this->dumper->dump($data, 2, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $this->assertSame("- |-\n    a\n    b\n- |-\n    c\n    d", $yaml);
        $this->assertSame($data, Yaml::parse($yaml));
    }

    public function testDumpTrailingNewlineInMultiLineLiteralBlocks()
    {
        $data = [
            'clip 1' => "one\ntwo\n",
            'clip 2' => "one\ntwo\n",
            'keep 1' => "one\ntwo\n",
            'keep 2' => "one\ntwo\n\n",
            'strip 1' => "one\ntwo",
            'strip 2' => "one\ntwo",
        ];
        $yaml = $this->dumper->dump($data, 2, 0, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $expected = <<<YAML
'clip 1': |
    one
    two
'clip 2': |
    one
    two
'keep 1': |
    one
    two
'keep 2': |+
    one
    two

'strip 1': |-
    one
    two
'strip 2': |-
    one
    two
YAML;

        $this->assertSame($expected, $yaml);
        $this->assertSame($data, Yaml::parse($yaml));
    }

    public function testZeroIndentationThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The indentation must be greater than zero');
        new Dumper(0);
    }

    public function testNegativeIndentationThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The indentation must be greater than zero');
        new Dumper(-4);
    }

    public function testDumpNullAsTilde()
    {
        $this->assertSame('{ foo: ~ }', $this->dumper->dump(['foo' => null], 0, 0, Yaml::DUMP_NULL_AS_TILDE));
    }

    public function testDumpIdeographicSpaces()
    {
        $expected = <<<YAML
alone: '　'
within_string: 'a　b'
regular_space: 'a b'

YAML;
        $this->assertSame($expected, $this->dumper->dump([
            'alone' => '　',
            'within_string' => 'a　b',
            'regular_space' => 'a b',
        ], 2));
    }
}

class A
{
    public $a = 'foo';
}
