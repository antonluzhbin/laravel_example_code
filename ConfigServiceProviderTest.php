<?php

/**
 * Тестируем обработку вложенных конфигураций.
 *
 * @author Anton Luzhbin
 */

use Illuminate\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Config\Repository;
use App\Providers\ConfigServiceProvider;

class ConfigServiceProviderTest extends TestCase
{
    /**
     * @var Application
     */
    var $app;
    /**
     * @var Filesystem
     */
    var $f;

    /**
     * Настройка.
     */
    public function setUp()
    {
        // Init an Illuminate Application
        // Set the environment to a fake foo
        // create an empty instance of Config
        // and populate it with some sample items
        $this->app = new Application();
        $this->app['env'] = 'setting_test';
        putenv('APP_PROJECT=project1');
        putenv('APP_ENV=env1');
        $this->app->setBasePath(sys_get_temp_dir());
        $this->app->instance('config', new Repository());

        $this->f = new Filesystem();
        $this->f->deleteDirectory(config_path());
        $this->f->makeDirectory(config_path(), 0755, true, true);
    }

    public function tearDown()
    {
        $this->f->deleteDirectory(config_path());
    }

    public function testSetUp()
    {
        $this->assertEquals(env('APP_PROJECT'), 'project1');
        $this->assertEquals(env('APP_ENV'), 'env1');
    }

    function testIsAssoc()
    {
        $provider = new ConfigServiceProvider($this->app);
        $this->assertFalse($provider->isAssoc([1]));
        $this->assertFalse($provider->isAssoc([1, new stdClass()]));
        $this->assertTrue($provider->isAssoc(["foo" => ["v1", "v2"]]));
        $this->assertTrue($provider->isAssoc([1, 'foo' => new stdClass()]));
    }

    public function testIsKeysMixin()
    {
        $provider = new ConfigServiceProvider($this->app);
        $this->assertFalse($provider->isKeysMixin([]));
        $this->assertFalse($provider->isKeysMixin([1, 2]));
        $this->assertFalse($provider->isKeysMixin(["foo" => ["v1", "v2"]]));
        $this->assertTrue($provider->isKeysMixin([1, 'foo' => new stdClass()]));
    }

    /**
     * Тестируем мерж неассоциативных массивов.
     */
    public function testMergeNonAssoc()
    {
        $this->writeConfigCode('app.php', "['a', 'b']");
        $this->writeConfigCode('app.project1.php', "['c']");
        $config = $this->setupServiceProvider();
        $this->assertEquals(['c'], $config);
    }

    /**
     * Тестируем мерж неассоциативных массивов.
     */
    public function testMergeAssocAndNonAssoc()
    {
        $this->writeConfigCode('app.php', "[ 'key' => 'a' ]");
        $this->writeConfigCode('app.project1.php', "['b']");
        $config = $this->setupServiceProvider();
        $this->assertEquals(['b'], $config);
    }

    /**
     * Тестируем замену параметров (строки, разные типы).
     */
    public function testReplaceParam()
    {
        $this->writeConfigCode('app.php', "[
	        'test_string_replace' => 'string app config'
	    ]");

        $this->writeConfigCode('app.project1.php', "[
            'test_string_replace' => 'string schet config',
            'test_string_to_array_assoc' => [ 'key_schet' => 'value_schet' ]
        ]");

        $this->writeConfigCode('app.project1.env1.php', "[
            'test_string1' => 'string local config',
            'test_array_assoc_to_string' => 'string local'
        ]");

        $config = $this->setupServiceProvider();

        $this->assertArrayNotHasKey(0, $config);
        $this->assertEquals($config['test_string_replace'],
            "string schet config");
        $this->assertEquals($config['test_string1'], "string local config");
        $this->assertEquals($config['test_array_assoc_to_string'],
            'string local');
        $this->assertEquals($config['test_string_to_array_assoc'],
            ['key_schet' => 'value_schet']);
    }

    /**
     * Тестируем замену параметров (массивы).
     */
    public function testReplaceArrayParam()
    {
        $this->writeConfigCode('app.php', "[
            'test_not_replaced' => [ 'key' => 'schet' ],
	    ]");

        $this->writeConfigCode('app.project1.php', "[
            'test_array_assoc' => [ 'key1' => 'schet' ],
            'test_array_not_assoc' => [ 'test1', 'test2' ]
        ]");

        $this->writeConfigCode('app.project1.env1.php', "[
            'test_array_assoc' => [ 'key1' => 'schet', 'key2' => 'novaplat' ],
            'test_array_not_assoc' => [ 'test3' ]
        ]");

        $config = $this->setupServiceProvider();

        $this->assertEquals(
            ['key' => 'schet'],
            $config['test_not_replaced']
        );
        $this->assertEquals(
            ['key1' => 'schet', 'key2' => 'novaplat'],
            $config['test_array_assoc']
        );
        $this->assertEquals(
            [ 'test3' ],
            $config['test_array_not_assoc']
        );
    }

    /**
     * Тестируем замену параметров при высокой вложенности.
     */
    public function testNestingLevel()
    {
        $this->writeConfigCode('app.php',
            "[ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => '123' ] ] ] ] ] ] ] ] ] ] ] ] ] ] ] ]");

        $this->writeConfigCode('app.project1.php',
            "[ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => [ 't' => '456' ] ] ] ] ] ] ] ] ] ] ] ] ] ] ] ]");

        $this->setupServiceProvider();

        $this->assertEquals(
            456,
            $this->app['config']->get('app.t.t.t.t.t.t.t.t.t.t.t.t.t.t.t.t')
        );
    }

    /**
     * Тестируем установку параметров через set().
     */
    public function testEmptyParam()
    {
        $this->app['config']->set('app', ['test_string' => "before provider"]);
        $this->setupServiceProvider();
        $this->assertEquals(
            $this->app['config']->get('app')['test_string'],
            "before provider"
        );

        $this->app['config']->set('app', ['test_string' => "after provider"]);
        $this->assertEquals(
            $this->app['config']->get('app')['test_string'],
            "after provider"
        );
    }

    protected function setupServiceProvider()
    {
        $provider = new ConfigServiceProvider($this->app);
        $this->app->register($provider);
        return $this->app['config']->get('app');
    }

    protected function writeConfigCode($file_name, $config_array)
    {
        $code = '<?php return '.$config_array.';';
        $this->f->put(config_path().'/'.$file_name, $code);
    }
}
