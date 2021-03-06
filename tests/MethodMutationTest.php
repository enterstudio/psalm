<?php
namespace Psalm\Tests;

use Psalm\Internal\Analyzer\FileAnalyzer;
use Psalm\Context;

class MethodMutationTest extends TestCase
{
    /**
     * @return void
     */
    public function testControllerMutation()
    {
        $this->addFile(
            'somefile.php',
            '<?php
                class User {
                    /** @var string */
                    public $name;

                    /**
                     * @param string $name
                     */
                    protected function __construct($name) {
                        $this->name = $name;
                    }

                    /** @return User|null */
                    public static function loadUser(int $id) {
                        if ($id === 3) {
                            $user = new User("bob");
                            return $user;
                        }

                        return null;
                    }
                }

                class UserViewData {
                    /** @var string|null */
                    public $name;
                }

                class Response {
                    public function __construct (UserViewData $viewdata) {}
                }

                class UnauthorizedException extends Exception { }

                class Controller {
                    /** @var UserViewData */
                    public $user_viewdata;

                    /** @var string|null */
                    public $title;

                    public function __construct() {
                        $this->user_viewdata = new UserViewData();
                    }

                    public function setUser(): void
                    {
                        $user_id = (int)$_GET["id"];

                        if (!$user_id) {
                            throw new UnauthorizedException("No user id supplied");
                        }

                        $user = User::loadUser($user_id);

                        if (!$user) {
                            throw new UnauthorizedException("User not found");
                        }

                        $this->user_viewdata->name = $user->name;
                    }
                }

                class FooController extends Controller {
                    public function barBar(): Response {
                        $this->setUser();

                        if (rand(0, 1)) {
                            $this->title = "hello";
                        }

                        return new Response($this->user_viewdata);
                    }
                }'
        );

        new FileAnalyzer($this->project_analyzer, 'somefile.php', 'somefile.php');
        $this->project_analyzer->getCodebase()->scanFiles();
        $method_context = new Context();
        $method_context->collect_mutations = true;
        $this->project_analyzer->getMethodMutations(
            'FooController::barBar',
            $method_context,
            'somefile.php',
            'somefile.php'
        );

        $this->assertSame('UserViewData', (string)$method_context->vars_in_scope['$this->user_viewdata']);
        $this->assertSame('string', (string)$method_context->vars_in_scope['$this->user_viewdata->name']);
        /** @psalm-suppress InvalidScalarArgument */
        $this->assertTrue($method_context->vars_possibly_in_scope['$this->title']);
    }

    /**
     * @return void
     */
    public function testNotSettingUser()
    {
        $this->addFile(
            'somefile.php',
            '<?php
                class User {}

                class FooController {
                    /** @var User|null */
                    public $user;

                    public function doThingWithUser(): array
                    {
                        if (!$this->user) {
                            return [];
                        }

                        return ["hello"];
                    }

                    public function barBar(): void {
                        $this->user = rand(0, 1) ? new User() : null;

                        $this->doThingWithUser();
                    }
                }'
        );

        new FileAnalyzer($this->project_analyzer, 'somefile.php', 'somefile.php');
        $this->project_analyzer->getCodebase()->scanFiles();
        $method_context = new Context();
        $method_context->collect_mutations = true;
        $this->project_analyzer->getMethodMutations(
            'FooController::barBar',
            $method_context,
            'somefile.php',
            'somefile.php'
        );

        $this->assertSame('null|User', (string)$method_context->vars_in_scope['$this->user']);
    }

    /**
     * @return void
     */
    public function testParentControllerSet()
    {
        $this->addFile(
            'somefile.php',
            '<?php
                class Foo { }

                class Controller {
                    /** @var Foo|null */
                    public $foo;

                    public function __construct() {
                        $this->foo = new Foo();
                    }
                }

                class FooController extends Controller {
                    public function __construct() {
                        parent::__construct();
                    }
                }'
        );

        new FileAnalyzer($this->project_analyzer, 'somefile.php', 'somefile.php');
        $this->project_analyzer->getCodebase()->scanFiles();
        $method_context = new Context();
        $method_context->collect_mutations = true;
        $this->project_analyzer->getMethodMutations(
            'FooController::__construct',
            $method_context,
            'somefile.php',
            'somefile.php'
        );

        $this->assertSame('Foo', (string)$method_context->vars_in_scope['$this->foo']);
    }

    /**
     * @return void
     */
    public function testTraitMethod()
    {
        $this->addFile(
            'somefile.php',
            '<?php
                class Foo { }

                trait T {
                    private function setFoo(): void {
                        $this->foo = new Foo();
                    }
                }

                class FooController {
                    use T;

                    /** @var Foo|null */
                    public $foo;

                    public function __construct() {
                        $this->setFoo();
                    }
                }'
        );

        new FileAnalyzer($this->project_analyzer, 'somefile.php', 'somefile.php');
        $this->project_analyzer->getCodebase()->scanFiles();
        $method_context = new Context();
        $method_context->collect_mutations = true;
        $this->project_analyzer->getMethodMutations(
            'FooController::__construct',
            $method_context,
            'somefile.php',
            'somefile.php'
        );

        $this->assertSame('Foo', (string)$method_context->vars_in_scope['$this->foo']);
    }
}
