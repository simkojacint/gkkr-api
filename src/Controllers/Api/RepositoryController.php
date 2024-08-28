<?php

namespace FuturewebCMS2024\Gkkr\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class RepositoryController extends BasicApiController
{

    public function repositoryInstall(Request $request, $id, string $version = null)
    {
        $apiName = 'repository';
        $dataName = 'package';
        $configName = 'providers';

        return $this->defaultInstall($apiName, $dataName, $configName, $id, $version);
    }

    public function repositoryUninstall(Request $request, $id, $version = null)
    {
        return $this->defaultUninstall('repository', $id, $version);
    }

    public function repositoryList(Request $request)
    {
        $response = $this->webApi->get($this->getUrl('repository/list'));

        return $response->json();
    }

    public function repositoryShow(Request $request, $id)
    {
        $response = $this->webApi->get($this->getUrl('repository/show/' . $id));

        return $response->json();
    }

    public function repositoryTags(Request $request, $id)
    {
        $response = $this->webApi->get($this->getUrl('repository/tags/' . $id));

        return $response->json();
    }

    public function repositoryData(Request $request, $id)
    {
        $response = $this->webApi->get($this->getUrl('repository/data/' . $id));

        return $response->json();
    }
}
