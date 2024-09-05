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


}
