<?php

namespace FuturewebCMS2024\Gkkr\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class GkkrServiceProvider
 *
 * @package FuturewebCMS2024\Gkkr\Providers
 */
class GkkrServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([$this->absolutePackageDirectory('config/') => config_path()], 'config');
        $this->loadRoutesFrom(__DIR__.'/../routes/gkkr.php');
    }

    protected function getDirectoryContents($path): array
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

        $files = [];
        foreach ($iterator as $file) {
            /**
             * @var \FilesystemIterator $file
             */
            if ($file->isDir()) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    protected function replaceFileContent(string $file, array|string $from, array|string $to): string|array
    {
        return str_replace($from, $to, file_get_contents($file));
    }

    /**
     * Converting relative path to absolute path
     *
     * @param null $path
     *
     * @return string
     */
    private function absolutePackageDirectory($path = null): false|string
    {
        return realpath(__DIR__ . '/../' . $path);
    }
}