<?php

namespace FuturewebCMS2024\Gkkr\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class BasicApiController extends Controller
{
    /**
     * @var \Illuminate\Http\Client\PendingRequest
     */
    protected $webApi;

    public function __construct()
    {
        $this->webApi = Http::withToken(config('app.gkkr_api.token'))->withHeaders([
            'X-Host' => parse_url(config('app.url'))['host'],
        ]);
    }

    /**
     * @param string $apiName
     * @param string $dataName
     * @param string $configName
     * @param $id
     * @param $version
     * @return array
     */
    protected function defaultInstall(string $apiName, string $dataName, string $configName, $id, $version = null): JsonResponse
    {
        $response = $this->webApi->get($this->getUrl($apiName . '/install/' . $id . '/' . $version));
        $data = $response->json();
        if (is_null($version)) {
            $version = $data['version'];
        }

        if (is_string($data)) {
            return response()->json($data, 403);
        }

        $packagePath = base_path('packages/' . $apiName . '/raw/' . $data[$dataName] . '/');
        File::makeDirectory($packagePath, 0775, true, true);

        $filePath = $packagePath . $data['fileName'];
        File::put($filePath, base64_decode($data['data']));

        $this->extractTarBall($filePath, $packagePath);

        $dirs = array_filter(glob($packagePath . '*'), 'is_dir');
        $packageExtractedPath = $dirs[0];
        $finalPackagePath = dirname(dirname(dirname($dirs[0]))) . '/' . $data[$dataName];
        $gkkrProviders = base_path('config/gkkr_' . $configName . '.php');

        if (File::isDirectory($finalPackagePath)) {
            File::deleteDirectory($finalPackagePath);
        }
        File::move($packageExtractedPath, $finalPackagePath);

        $packageComposerJson = file_get_contents($finalPackagePath . '/composer.json');
        $packageComposerJson = json_decode($packageComposerJson, true);

        $composerJson = file_get_contents(base_path('composer.json'));
        $composerJson = json_decode($composerJson, true);

        $namespacePivot = [];

        shell_exec('git config --global --add safe.directory /var/www/html');

        list($composerJsonChanged, $namespace, $composerJson, $namespacePivot) = $this->collectComposerJsonChanges($packageComposerJson, $composerJson, $finalPackagePath, $namespacePivot);

        if ($composerJsonChanged) {
            File::put(base_path('composer.json'), str_replace('\\/', '/', json_encode($composerJson, JSON_PRETTY_PRINT)));
            shell_exec('cd /var/www/html && /usr/bin/composer dump-autoload');
            shell_exec('cd /var/www/html/ && git add composer.json');
        }

        $providers = array_filter(glob($finalPackagePath . '/src/Providers/*.php'), 'is_file');
        ob_start();

        $configApp = include $gkkrProviders;
        ob_get_clean();
        $configAppChanged = false;
        $publishableProviders = [];
        list($configApp, $publishableProviders, $configAppChanged) = $this->collectProviders($providers, $namespacePivot, $configApp, $publishableProviders, $configAppChanged);

        $publishOutput = [];
        $migrations = [];
        $files = [];
        $seedersInstalled = [];
        $filesException = ['composer.json', 'config/gkkr_providers.php', 'config/gkkr_components.php'];

        if ($configAppChanged) {
            File::put($gkkrProviders, "<?php return \r\n" . var_export($configApp, true) . ";");
            shell_exec('git add ' . $gkkrProviders);

            if (!empty($publishableProviders)) {
                foreach ($publishableProviders as $provider) {
                    $published = shell_exec('php /var/www/html/artisan vendor:publish --provider="' . $provider . '"');
                    [$pub, $git, $dirs] = $this->processPublished($published);
                    if (!empty($git)) {
                        $currentGitUser = shell_exec('cd /var/www/html && git config user.name');
                        $currentGitEmail = shell_exec('cd /var/www/html && git config user.email');
                        $commitCommand = 'cd /var/www/html/ && git config user.name "System" && git config user.email "nobody@dev.futureweb.hu" && git commit -m "Installed ' . $provider . '"';
                        $execOutput = shell_exec($commitCommand);
                        Log::debug('git commit changes: ' . $execOutput . '|Command: ' . $commitCommand);
                        shell_exec('cd /var/www/html/ && git config user.name "' . $currentGitUser . '" && git config user.email "' . $currentGitEmail . '"');

                        $gitCommitHash = trim(shell_exec('git log -1 --pretty="%h"'));
                        $changes = shell_exec('cd /var/www/html && git show --name-only "' . $gitCommitHash . '"');
                        $changedFiles = explode("\n", $changes);

                        foreach ($changedFiles as $file) {
                            if (!empty($file) && !in_array($file, $filesException) && file_exists(base_path($file))) {
                                $files[] = $file;
                            }
                        }
                    }
                    $publishOutput[] = $published;


                }

                $migrationsDir = $finalPackagePath . '/src/database/migrations/';
                if (is_dir($migrationsDir)) {
                    $_migrations = array_filter(scandir($migrationsDir), function ($item) {
                        return $item !== '.' && $item !== '..' ? $item : false;
                    });
                    if (count($_migrations) > 0) {
                        shell_exec('php /var/www/html/artisan migrate');
                        foreach ($_migrations as $migration) {
                            $migrations[] = 'database/migrations/' . $migration;
                        }
                    }

                }
                $seedersDir = $finalPackagePath . '/src/database/seeders/';
                if (is_dir($seedersDir)) {
                    $seeders = array_filter(scandir($seedersDir), function ($item) {
                        return $item != '.' && $item != '..' ? $item : false;
                    });
                    if (count($seeders) > 0) {
                        foreach ($seeders as $seeder) {
                            shell_exec('php /var/www/html/artisan db:seed --class="' . substr($seeder, 0, -4) . '"');
                            $seedersInstalled[] = 'database/seeders/' . $seeder;
                        }
                    }
                }
            }
        }

        $installedData = [
            'type' => $apiName,
            'id' => $id,
            'version' => $version,
            'migrations' => $migrations,
            'files' => $files,
            'providers' => $publishableProviders,
            'config_file' => $gkkrProviders,
            'seeders' => $seedersInstalled,
        ];
        if ($apiName == 'repository') {
            $installedProviders = storage_path('installed_providers.php');

            if (!File::exists($installedProviders)) {
                File::put($installedProviders, "<?php return \r\n" . var_export([], true) . ";");
            }

            $dataInstalled = include $installedProviders;
        }

        $filePath = 'gkkr/' . $apiName . '_' . $id . (!is_null($version) ? ('_' . $version) : '') . '.log';

        Storage::put($filePath, json_encode($installedData, JSON_PRETTY_PRINT));

        $composer = json_decode(File::get(base_path('composer.json')), true);

        $configFile = include $gkkrProviders;
        foreach ($publishableProviders as $provider) {
            if (in_array($provider, $configFile)) {
                if (($key = array_search($provider, $configFile)) !== false) {
                    unset($configFile[$key]);
                }
            }

            if ($apiName == 'repository') {
                if (!in_array($provider, $dataInstalled)) {
                    $dataInstalled[] = $provider;
                }
            }

            $temp = explode('\\', $provider);
            $providerPackage = $temp[0] . '\\' . $temp[1] . '\\';

            if (isset($composer['autoload']['psr-4'][$providerPackage])) {
                unset($composer['autoload']['psr-4'][$providerPackage]);
            }


        }
        File::put(base_path('composer.json'), json_encode($composer, JSON_PRETTY_PRINT));

        File::put($gkkrProviders, "<?php return \r\n" . var_export($configFile, true) . ";");

        if ($apiName == 'repository') {
            File::put($installedProviders, "<?php return \r\n" . var_export($dataInstalled, true) . ";");
        }

        return response()->json($installedData);
    }

    protected function defaultUninstall(string $apiName, $id, $version = null)
    {
        $uninstallOutput = [];
        $dir = glob(storage_path('app/gkkr') . '/' . $apiName . '_' . $id . '_*.log');

        $filePath = false;

        if (!empty($dir)) {
            $dir = array_reverse($dir);
            $filePath = reset($dir);
        }

        if ($filePath && File::exists($filePath)) {
            $content = File::get($filePath);
            $data = json_decode($content, true);

            if ($data['type'] !== $apiName) {
                return response()->json('Package type missmatch!', 422);
            }

            if (isset($data['migrations'])) {
                foreach ($data['migrations'] as $migration) {
                    $uninstallOutput[] = 'Rolling back migration: ' . $migration;
                    $uninstallOutput[] = shell_exec('php /var/www/html/artisan migrate:rollback --path=' . $migration);
                }
            }

            if (isset($data['seeders'])) {
                foreach ($data['seeders'] as $seeder) {
                    $uninstallOutput[] = 'Remove seeder data: ' . $seeder;

                    $seederClass = $this->getNamespaceAndClassFromFile(base_path($seeder));

                    if (!is_null($seederClass['fqcn'])) {
                        $seederInstance = new $seederClass['fqcn']();

                        if (method_exists($seederInstance, 'remove')) {
                            $seederInstance->remove();
                            $uninstallOutput[] = 'Data removed for seeder: ' . $seeder;
                        } else {
                            $uninstallOutput[] = 'No remove() method found for seeder: ' . $seeder;
                        }

                    }
                }
            }


            Log::debug('Starting file deletions...');
            $gitCommands = [];
            $dirs = [];

            foreach ($data['files'] as $file) {
                $abs = base_path($file);

                if (File::exists($abs) && !str_contains($abs, 'app/Http/Controllers/Api')) {
                    $deleteResult = File::delete($abs);
                    $uninstallOutput[] = $deleteResult
                        ? 'File deleted: ' . $abs
                        : 'Failed to delete file: ' . $abs;

                    $dirs[] = dirname($abs);
                    $gitCommands[] = 'cd /var/www/html/ && git add ' . $file;
                }
            }

            $gitOutput = [];
            foreach ($gitCommands as $gitCommand) {
                $gitOutput[] = ['input' => $gitCommand, 'output' => shell_exec($gitCommand)];
            }


            Log::debug('Git commands executed:', $gitOutput);
            Log::debug('Directories affected:', $dirs);

        }

        $commitMessage = 'Automated commit: Removed files during uninstallation';
        $gitCommitCommand = 'cd /var/www/html/ && git commit -m ' . escapeshellarg($commitMessage);

        $gitCommitOutput = shell_exec($gitCommitCommand);

        if ($gitCommitOutput === null) {
            $uninstallOutput[] = 'Git commit failed. Check your repository status.';
        } else {
            $uninstallOutput[] = 'Git commit successful: ' . $commitMessage;
        }


        $installedProviders = storage_path('installed_providers.php');

        if (File::exists($installedProviders)) {
            $dataInstalled = include $installedProviders;

            if (count($data['providers'])) {
                foreach ($data['providers'] as $provider) {
                    if (in_array($provider, $dataInstalled)) {
                        $index = array_search($provider, $dataInstalled);
                        unset($dataInstalled[$index]);
                    }
                }

                File::put($installedProviders, "<?php return \r\n" . var_export($dataInstalled, true) . ";");
            }
        }


        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        Log::debug('Uninstall output: ' . implode("\n", $uninstallOutput));
        return $uninstallOutput;
    }

    public function getNamespaceAndClassFromFile($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File does not exist: $filePath");
        }

        $content = file_get_contents($filePath);

        $namespace = null;
        $class = null;

        // Match namespace
        if (preg_match('/^namespace\s+(.+?);/m', $content, $matches)) {
            $namespace = $matches[1];
        }

        // Match class (you could expand this to include interface, trait, enum)
        if (preg_match('/^class\s+(\w+)/m', $content, $matches)) {
            $class = $matches[1];
        }

        // Optional: Match interface, trait, or enum
        if (!$class && preg_match('/^(interface|trait|enum)\s+(\w+)/m', $content, $matches)) {
            $class = $matches[2];
        }

        return [
            'namespace' => $namespace,
            'class' => $class,
            'fqcn' => $namespace && $class ? $namespace . '\\' . $class : $class
        ];
    }

    protected function processPublished(string $published)
    {
        Log::debug('publishing: ' . $published);
        $lines = explode("\n", $published);
        $gitCommands = [];
        $pub = [];
        $dirs = [];
        foreach($lines as $line) {
            if(strpos($line, 'to [') === FALSE) {
                continue;
            }

            $pub[] = $line;

            $parse = explode('to [', $line);
            $parse = explode(']', $parse[1]);
            $dir = reset($parse);
            $dirs[] = $dir;
            $gitCommands[] = 'cd /var/www/html/ && git add ' . $dir . '/*';
        }

        $gitOutput = [];
        foreach($gitCommands as $gitCommand) {
            $gitOutput[] = ['input' => $gitCommand, 'output' => shell_exec($gitCommand)];
        }

        return [
            $pub,
            $gitOutput,
            $dirs,
        ];
    }

    /**
     * @param bool|array $providers
     * @param mixed $namespacePivot
     * @param mixed $configApp
     * @param array $publishableProviders
     * @param bool $configAppChanged
     * @return array
     */
    protected function collectProviders(bool|array $providers, mixed $namespacePivot, mixed $configApp, array $publishableProviders, bool $configAppChanged): array
    {
        if (!empty($providers)) {
            foreach ($providers as $provider) {
                $provider = str_replace(base_path('/'), '', $provider);
                foreach ($namespacePivot as $dir => $namespace) {
                    if (strpos($provider, $dir) === 0) {
                        $finalClass = $namespace;
                        $finalClass .= str_replace([$dir, '.php',], '', $provider);
                        $finalClass = str_replace('/', '\\', $finalClass);

                        if ($finalClass && class_exists($finalClass) && !in_array($finalClass, $configApp)) {
                            $configApp[] = $finalClass;
                            $publishableProviders[] = $finalClass;
                            $configAppChanged = true;
                        }
                    }
                }
            }
        }

        return array($configApp, $publishableProviders, $configAppChanged);
    }

    /**
     * @param mixed $packageComposerJson
     * @param mixed $composerJson
     * @param string $finalPackagePath
     * @param array $namespacePivot
     * @return array
     */
    protected function collectComposerJsonChanges(mixed $packageComposerJson, mixed $composerJson, string $finalPackagePath, array $namespacePivot): array
    {
        $composerJsonChanged = false;
        if (isset($packageComposerJson['autoload']['psr-4'])) {
            foreach ($packageComposerJson['autoload']['psr-4'] as $namespace => $path) {
                if (!array_key_exists($namespace, $composerJson['autoload']['psr-4'])) {
                    $composerJson['autoload']['psr-4'][$namespace] = str_replace(base_path('/'), '', $finalPackagePath) . '/src/';
                    $composerJsonChanged = true;
                }
                $namespacePivot[$composerJson['autoload']['psr-4'][$namespace]] = $namespace;
            }
        }
        return array($composerJsonChanged, $namespace, $composerJson, $namespacePivot);
    }

    /**
     * @param string $filePath
     * @param string $packagePath
     * @return void
     */
    protected function extractTarBall(string $filePath, string $packagePath): void
    {
        $phar = new \PharData($filePath);
        $phar->decompress();
        $phar = new \PharData(substr($filePath, 0, -3));
        $isExtracted = true;
        try {
            $phar->extractTo($packagePath);
        } catch (\Exception $e) {
            $isExtracted = false;
        }

        if ($isExtracted) {
            File::delete($filePath);
            File::delete(substr($filePath, 0, -3));
        }
    }

    protected function getUrl(string $url)
    {
        return config('app.gkkr_api.base_url') . $url;
    }
}
