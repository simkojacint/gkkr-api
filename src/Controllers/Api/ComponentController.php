<?php

namespace FuturewebCMS2024\Gkkr\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ComponentController extends BasicApiController
{
    public function componentInstall(Request $request, $id, string $version = null)
    {
        $apiName = 'component';
        $dataName = 'component';
        $configName = 'components';

        return $this->defaultInstall($apiName, $dataName, $configName, $id, $version);
    }

    public function componentUninstall(Request $request, $id, $version = null)
    {
        return $this->defaultUninstall('component', $id, $version);
    }

    public function componentList(Request $request)
    {
        $response = $this->webApi->get($this->getUrl('component/list'));

        return $response->json();
    }

    public function componentDependencyList(Request $request, $id)
    {
        $response = $this->webApi->get($this->getUrl('component/dependency-list/' . $id));

        return $response->json();
    }

    public function componentShow(Request $request, $id)
    {
        $response = $this->webApi->get($this->getUrl('component/show/' . $id));

        return $response->json();
    }

    public function componentTags(Request $request, $id)
    {
        $response = $this->webApi->get($this->getUrl('component/tags/' . $id));

        return $response->json();
    }

    public function componentData(Request $request, $id)
    {
        $response = $this->webApi->get($this->getUrl('component/data/' . $id));

        return $response->json();
    }

    public function checkDependencies(Request $request, $id)
    {
        $response = $this->componentDependencyList(new \Illuminate\Http\Request(), $id)['data'];

        $providers = storage_path('installed_providers.php');

        if (file_exists($providers)) {
            $content = include $providers;
        }

        $dependencies = [];

        if (!empty($response)) {
            foreach ($response as $key => $value) {
                $searchTerm = $value['name'] . 'ServiceProvider';

                $exists = array_filter($content, function ($item) use ($searchTerm) {
                    return strpos($item, $searchTerm) !== false;
                });

                if (empty($exists)) {
                    $dependencies[] = $value;
                }
            }

            if (!empty($dependencies)) {
                return $dependencies;
            }
        }

        return true;
    }
}
