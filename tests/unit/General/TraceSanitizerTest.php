<?php

namespace Tests\Unit\General;

use Appwrite\Utopia\View;
use PHPUnit\Framework\TestCase;

class TraceSanitizerTest extends TestCase
{
    private string $errorViewPath;

    protected function setUp(): void
    {
        $this->errorViewPath = __DIR__.'/../../../app/views/general/error.phtml';
    }

    public function test_error_view_renders_without_warnings_when_trace_has_circular_refs(): void
    {
        $a = new \stdClass;
        $b = new \stdClass;
        $a->child = $b;
        $b->parent = $a;

        $trace = [
            [
                'file' => '/test/File.php',
                'line' => 42,
                'function' => 'testFunc',
                'args' => [$a, 'string_arg', 123],
            ],
        ];

        $view = new View($this->errorViewPath);
        $view
            ->setParam('title', 'Test Error')
            ->setParam('development', true)
            ->setParam('code', 500)
            ->setParam('message', 'Test message')
            ->setParam('type', 'test_error')
            ->setParam('trace', $trace)
            ->setParam('exception', null, false);

        $output = $view->render(false);

        $this->assertStringNotContainsString('Warning', $output);
        $this->assertStringNotContainsString('var_export', $output);
        $this->assertStringContainsString('testFunc', $output);
    }

    public function test_error_view_renders_without_trace_in_production(): void
    {
        $a = new \stdClass;
        $b = new \stdClass;
        $a->child = $b;
        $b->parent = $a;

        $trace = [
            [
                'file' => '/test/File.php',
                'line' => 42,
                'function' => 'testFunc',
                'args' => [$a, 'string_arg'],
            ],
        ];

        $view = new View($this->errorViewPath);
        $view
            ->setParam('title', 'Test Error')
            ->setParam('development', false)
            ->setParam('code', 500)
            ->setParam('message', 'Test message')
            ->setParam('type', 'test_error')
            ->setParam('trace', $trace)
            ->setParam('exception', null, false);

        $output = $view->render(false);

        // In production, trace is not displayed
        $this->assertStringNotContainsString('testFunc', $output);
        $this->assertStringNotContainsString('Warning', $output);
    }

    public function test_error_view_handles_closure_in_trace_args(): void
    {
        $closure = function () {
            return 'test';
        };

        $trace = [
            [
                'file' => '/test/File.php',
                'line' => 42,
                'function' => 'testFunc',
                'args' => [$closure, 'string_arg'],
            ],
        ];

        $view = new View($this->errorViewPath);
        $view
            ->setParam('title', 'Test Error')
            ->setParam('development', true)
            ->setParam('code', 500)
            ->setParam('message', 'Test message')
            ->setParam('type', 'test_error')
            ->setParam('trace', $trace)
            ->setParam('exception', null, false);

        $output = $view->render(false);

        $this->assertStringNotContainsString('Warning', $output);
        $this->assertStringNotContainsString('var_export', $output);
    }

    public function test_sanitize_trace_args_function(): void
    {
        $a = new \stdClass;
        $b = new \stdClass;
        $a->child = $b;
        $b->parent = $a;

        $trace = [
            [
                'file' => '/test/File.php',
                'line' => 42,
                'function' => 'testFunc',
                'args' => [$a, 'string_arg', 123],
            ],
        ];

        $sanitized = array_map(fn (array $frame): array => array_merge($frame, [
            'args' => isset($frame['args']) ? $this->sanitizeTraceArgs($frame['args']) : [],
        ]), $trace);

        $exported = \var_export($sanitized, true);
        $this->assertNotEmpty($exported);
        $this->assertStringNotContainsString('stdClass::__set_state', $exported);

        $json = \json_encode($sanitized, JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);
    }

    private function sanitizeTraceArgs(array $args, array &$visited = []): array
    {
        $result = [];
        foreach ($args as $key => $value) {
            if (\is_object($value)) {
                $hash = \spl_object_id($value);
                if (\in_array($hash, $visited, true)) {
                    $result[$key] = '*RECURSION* '.$value::class;
                } else {
                    $visited[] = $hash;
                    $result[$key] = $value::class.'#'.$hash;
                }
            } elseif (\is_array($value)) {
                $result[$key] = $this->sanitizeTraceArgs($value, $visited);
            } elseif (\is_resource($value)) {
                $result[$key] = '(resource)';
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
