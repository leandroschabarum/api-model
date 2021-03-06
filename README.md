# ApiModel
### _REST API based models_

![GitHub release (latest by date)](https://img.shields.io/github/v/release/leandroschabarum/api-model?color=0074b7) ![Packagist Downloads](https://img.shields.io/packagist/dt/ordnael/api-model?color=0074b7) ![GitHub issues](https://img.shields.io/github/issues/leandroschabarum/api-model?color=59981a) ![GitHub](https://img.shields.io/github/license/leandroschabarum/api-model?color=59981a)

Extendable class for REST API based models that integrates common Laravel's Model class functionalities.

----

### Usage

After running the artisan command to generate your ApiModel, it is necessary to add
your endpoints to the respective API class that is generated along with the model.

This is the interface used to manipulate API resources over HTTP requests and is critical
for the inner workings of your model. The ApiModel should behave similar to the Model class from Laravel.

### Artisan Command

```bash
php artisan make:apimodel <name>
```

----

#### Status

Currently in development. Any bugs or feature requests can be opened under Issues (templates are available).
