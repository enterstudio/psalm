<?php
namespace Psalm\Tests;

use Psalm\Checker\FileChecker;
use Psalm\Checker\ProjectChecker;
use Psalm\Provider\Providers;

class FileUpdateTest extends TestCase
{
    /**
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        FileChecker::clearCache();

        $this->file_provider = new \Psalm\Tests\Provider\FakeFileProvider();

        $config = new TestConfig();

        $providers = new Providers(
            $this->file_provider,
            new \Psalm\Tests\Provider\ParserInstanceCacheProvider(),
            null,
            null,
            new Provider\FakeFileReferenceCacheProvider()
        );

        $this->project_checker = new ProjectChecker(
            $config,
            $providers,
            false,
            true,
            ProjectChecker::TYPE_CONSOLE,
            1,
            false
        );

        $this->project_checker->infer_types_from_usage = true;
    }

    /**
     * @dataProvider providerTestValidUpdates
     *
     * @param array<string, string> $start_files
     * @param array<string, string> $end_files
     * @param array<string, string> $error_levels
     *
     * @return void
     */
    public function testValidInclude(
        array $start_files,
        array $end_files,
        array $initial_correct_methods,
        array $unaffected_correct_methods,
        array $error_levels = []
    ) {
        $this->project_checker->cache_results = true;

        $codebase = $this->project_checker->getCodebase();

        $config = $codebase->config;

        foreach ($error_levels as $error_type => $error_level) {
            $config->setCustomErrorLevel($error_type, $error_level);
        }

        foreach ($start_files as $file_path => $contents) {
            $this->file_provider->registerFile($file_path, $contents);
            $codebase->addFilesToAnalyze([$file_path => $file_path]);
        }

        $codebase->scanFiles();

        $this->assertSame([], $codebase->analyzer->getCorrectMethods());

        $codebase->analyzer->analyzeFiles($this->project_checker, 1, false);

        $this->assertSame(
            $initial_correct_methods,
            $codebase->analyzer->getCorrectMethods()
        );

        foreach ($end_files as $file_path => $contents) {
            $this->file_provider->registerFile($file_path, $contents);
        }

        $codebase->reloadFiles($this->project_checker, array_keys($end_files));

        foreach ($end_files as $file_path => $_) {
            $codebase->addFilesToAnalyze([$file_path => $file_path]);
        }

        $codebase->scanFiles();
        $codebase->analyzer->loadCachedResults($this->project_checker);

        $this->assertSame(
            $unaffected_correct_methods,
            $codebase->analyzer->getCorrectMethods()
        );
    }

    /**
     * @dataProvider providerTestInvalidUpdates
     *
     * @param array<string, string> $start_files
     * @param array<string, string> $end_files
     * @param array<string, string> $error_levels
     *
     * @return void
     */
    public function testErrorAfterUpdate(
        array $start_files,
        array $end_files,
        string $error_message,
        array $error_levels = []
    ) {
        $this->project_checker->cache_results = true;

        $codebase = $this->project_checker->getCodebase();

        $config = $codebase->config;

        foreach ($error_levels as $error_type => $error_level) {
            $config->setCustomErrorLevel($error_type, $error_level);
        }

        foreach ($start_files as $file_path => $contents) {
            $this->file_provider->registerFile($file_path, $contents);
            $codebase->addFilesToAnalyze([$file_path => $file_path]);
        }

        $codebase->scanFiles();

        $codebase->analyzer->analyzeFiles($this->project_checker, 1, false);

        foreach ($end_files as $file_path => $contents) {
            $this->file_provider->registerFile($file_path, $contents);
        }

        $codebase->reloadFiles($this->project_checker, array_keys($end_files));

        foreach ($end_files as $file_path => $_) {
            $codebase->addFilesToAnalyze([$file_path => $file_path]);
        }

        $codebase->scanFiles();

        $this->expectException('\Psalm\Exception\CodeException');
        $this->expectExceptionMessageRegexp('/\b' . preg_quote($error_message, '/') . '\b/');

        $codebase->analyzer->analyzeFiles($this->project_checker, 1, false);
    }

    /**
     * @return array
     */
    public function providerTestValidUpdates()
    {
        return [
            'basicRequire' => [
                'start_files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'A.php' => '<?php
                        namespace Foo;

                        class A{
                            public function fooFoo(): void {

                            }

                            public function barBar(): string {
                                return "hello";
                            }
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'B.php' => '<?php
                        namespace Foo;

                        class B {
                            public function foo(): void {
                                (new A)->fooFoo();
                            }

                            public function bar() : void {
                                echo (new A)->barBar();
                            }

                            public function noReturnType() {}
                        }',
                ],
                'end_files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'A.php' => '<?php
                        namespace Foo;

                        class A{
                            public function fooFoo(?string $foo = null): void {

                            }

                            public function barBar(): string {
                                return "hello";
                            }
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'B.php' => '<?php
                        namespace Foo;

                        class B {
                            public function foo(): void {
                                (new A)->fooFoo();
                            }

                            public function bar() : void {
                                echo (new A)->barBar();
                            }

                            public function noReturnType() {}
                        }',
                ],
                'initial_correct_methods' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'A.php' => [
                        'foo\a::foofoo' => true,
                        'foo\a::barbar' => true,
                    ],
                    getcwd() . DIRECTORY_SEPARATOR . 'B.php' => [
                        'foo\b::foo' => true,
                        'foo\b::bar' => true,
                        'foo\b::noreturntype' => true,
                    ],
                ],
                'unaffected_correct_methods' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'A.php' => [
                        'foo\a::barbar' => true
                    ],
                    getcwd() . DIRECTORY_SEPARATOR . 'B.php' => [
                        'foo\b::bar' => true,
                        'foo\b::noreturntype' => true,
                    ],
                ],
                [
                    'MissingReturnType' => \Psalm\Config::REPORT_INFO,
                ]
            ],
        ];
    }

    /**
     * @return array
     */
    public function providerTestInvalidUpdates()
    {
        return [
            'invalidateParentCaller' => [
                'start_files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'A.php' => '<?php
                        namespace Foo;

                        class A {
                            public function foo() : void {}
                        }',
                    getcwd() . DIRECTORY_SEPARATOR . 'B.php' => '<?php
                        namespace Foo;

                        class B extends A { }',
                    getcwd() . DIRECTORY_SEPARATOR . 'C.php' => '<?php
                        namespace Foo;

                        class C {
                            public function bar() : void {
                                (new B)->foo();
                            }
                        }',
                ],
                'end_files' => [
                    getcwd() . DIRECTORY_SEPARATOR . 'A.php' => '<?php
                        namespace Foo;

                        class A { }',
                    getcwd() . DIRECTORY_SEPARATOR . 'B.php' => '<?php
                        namespace Foo;

                        class B extends A { }',
                    getcwd() . DIRECTORY_SEPARATOR . 'C.php' => '<?php
                        namespace Foo;

                        class C {
                            public function bar() : void {
                                (new B)->foo();
                            }
                        }',
                ],
                'error_message' => 'UndefinedMethod'
            ],
        ];
    }
}