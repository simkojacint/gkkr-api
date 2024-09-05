# GKKR API for installing modules, components to FuturewebCMS2024

## Installation:
- [ ] add the repository to your composer.json repository block:
```
{
      "type": "vcs",
      "url": "https://github.com/simkojacint/gkkr-api.git"
}
```
- [ ] install the package:
```
composer require futurewebcms2024/gkkr-api
```

- [ ] include the provider in config/app.php providers section

- [ ] after installation publish files:
```
php artisan vendor:publish --provider="FuturewebCMS2024\Gkkr\Providers\GkkrServiceProvider"
```

- [ ] after all please see the routes:
```
php artisan route:list | grep gkkr
```

<img width="644" alt="image" src="https://github.com/user-attachments/assets/e874fd78-3631-44f5-b695-c973e853b12f">

- [ ] Enjoy!
