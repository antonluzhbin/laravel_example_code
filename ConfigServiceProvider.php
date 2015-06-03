<?php namespace App\Providers;

use Symfony\Component\Finder\Finder;
use Illuminate\Support\ServiceProvider;
use Exception;

class ConfigServiceProvider extends ServiceProvider
{

    /**
     * Overwrite any vendor / package configuration.
     *
     * This service provider is intended to provide a convenient location for you
     * to overwrite any "vendor" or package configuration that you may want to
     * modify before the application handles the incoming request / command.
     *
     * @return void
     */
    public function register()
    {
        config([
            //
        ]);

        $config = app('config');

        foreach ($this->getConfigsPrefixes() as $config_prefix) {
            $settings = [];

            /**
             * Настройки приложения.
             */
            $appSettingsPath = (new \SplFileInfo(config_path().'/'.$config_prefix
                .'.php'))->getRealPath();
            if (file_exists($appSettingsPath) && !is_dir($appSettingsPath)) {
                $settings = include $appSettingsPath;
                $settings = [$config_prefix => $settings];
            }

            if (env('APP_PROJECT')) {
                /**
                 * Настройки проекта.
                 */
                $appProjectSettingsPath = (new \SplFileInfo(config_path().'/'
                    .$config_prefix.'.'.env('APP_PROJECT').'.php'))->getRealPath();
                if (file_exists($appProjectSettingsPath)
                    && !is_dir($appProjectSettingsPath)
                ) {
                    $settingsProject = require $appProjectSettingsPath;
                    $settingsProject = [$config_prefix => $settingsProject];
                    $settings = $this->arrayMerge($settings, $settingsProject);
                }

                if (env('APP_ENV')) {
                    /**
                     * Настройки окружения.
                     */
                    $appEnvSettingsPath = (new \SplFileInfo(config_path().'/'
                        .$config_prefix.'.'.env('APP_PROJECT').'.'.env('APP_ENV')
                        .'.php'))->getRealPath();
                    if (file_exists($appEnvSettingsPath)
                        && !is_dir($appEnvSettingsPath)
                    ) {
                        $settingsEnv = require $appEnvSettingsPath;
                        $settingsEnv = [$config_prefix => $settingsEnv];
                        $settings = $this->arrayMerge($settings, $settingsEnv);
                    }
                }
            }

            /**
             * Сохранение в конфиг.
             */
            foreach ($settings as $key => $val) {
                $config->set($key, $val);
            }
        }
    }

    /**
     * Получение списка префиксов.
     *
     * @return Array
     */
    protected function getConfigsPrefixes()
    {
        $envConfigPath = (new \SplFileInfo(config_path()))->getRealPath();
        if (!file_exists($envConfigPath) || !is_dir($envConfigPath)) {
            return [];
        }

        /**
         * @var \Symfony\Component\Finder\SplFileInfo[] $files
         */
        $files = Finder::create()->files()->name('/^[a-zA_Z]+.php$/')
            ->in($envConfigPath);
        $arr = [];
        foreach ($files as $file) {
            $arr[] = $file->getBasename('.php');
        }

        return $arr;
    }

    /**
     * Рекурсивно объединяет два массива, с проверкой на уровень вложенности.
     * При равенстве ключей выбирается значение из второго массива.
     * Если есть вложенные массивы. то они тоже объединяются.
     *
     * @param Array $arr1 Первый массив.
     * @param Array $arr2 Второй массив.
     *
     * @return Array Результат объединения массивов.
     * @throws Exception
     */
    protected function arrayMerge($arr1, $arr2)
    {
        if($this->isKeysMixin($arr1))
            throw new \UnexpectedValueException(
                'Keys mixing in '.var_export($arr1, true)
            );

        if($this->isKeysMixin($arr2))
            throw new \UnexpectedValueException(
                'Keys mixing in '.var_export($arr2, true)
            );

        if (!$this->isAssoc($arr1) || !$this->isAssoc($arr2)) {
            return $arr2;
        } else {
            foreach ($arr1 as $key => $val) {

                if (!isset($arr2[$key])) {
                    continue;
                }

                if (gettype($val) == 'array' && gettype($arr2[$key]) == 'array'
                ) {
                    $arr1[$key] = $this->arrayMerge($arr1[$key], $arr2[$key]);
                } else {
                    $arr1[$key] = $arr2[$key];
                }
                unset($arr2[$key]);
            }

            /**
             * Обрабатываем параметры из второго массива, которых нет в первом.
             */
            foreach ($arr2 as $key => $val) {
                if (gettype($key) != 'integer') {
                    $arr1[$key] = $arr2[$key];
                }
            }

            return $arr1;
        }
    }

    /**
     * Проверка ассоциативный массив или нет.
     *
     * @param Array $arr
     *
     * @return Boolean
     */
    function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param $arr
     *
     * @return bool
     */
    function isKeysMixin($arr)
    {
        $has_integer = false;
        $has_non_integer = false;
        foreach($arr as $key => $value)
        {
            if('integer' == gettype($key))
                $has_integer = true;
            else
                $has_non_integer = true;

            if($has_integer && $has_non_integer) {
                return true;
            }
        }
        return false;
    }
}
