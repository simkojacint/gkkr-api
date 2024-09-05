<?php

use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'gkkr-api'], function(){
    Route::group(['prefix' => 'repository'], function(){
        $controller = \FuturewebCMS2024\Gkkr\Controllers\Api\RepositoryController::class;

        Route::get('list', [$controller, 'repositoryList']);
        Route::get('show/{id}', [$controller, 'repositoryShow']);
        Route::get('tags/{id}', [$controller, 'repositoryTags']);
        Route::get('data/{id}', [$controller, 'repositoryData']);

        Route::get('install/{id}/{tag?}', [$controller, 'repositoryInstall']);
        Route::get('uninstall/{id}/{tag?}', [$controller, 'repositoryUninstall']);
    });

    Route::group(['prefix' => 'component'], function(){
        $controller = \FuturewebCMS2024\Gkkr\Controllers\Api\ComponentController::class;

        Route::get('list', [$controller, 'componentList']);
        Route::get('dependency-list/{id}', [$controller, 'componentDependencyList']);
        Route::get('show/{id}', [$controller, 'componentShow']);
        Route::get('tags/{id}', [$controller, 'componentTags']);
        Route::get('data/{id}', [$controller, 'componentData']);

        Route::get('install/{id}/{tag?}', [$controller, 'componentInstall']);
        Route::get('uninstall/{id}/{tag?}', [$controller, 'componentUninstall']);
    });
});